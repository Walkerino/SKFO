<?php namespace ProcessWire;

require_once __DIR__ . '/_reviews_moderation.php';

$reviewTable = 'tour_reviews';
$reviewDashboardStatusOptions = [
    'all' => 'Все',
    'approved' => 'Одобрены',
    'duplicate' => 'Дубликаты',
    'blocked' => 'Заблокированы',
    'hidden' => 'Скрыты',
];
$reviewDashboardCurrentPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
if ($reviewDashboardCurrentPath === '') {
    $reviewDashboardCurrentPath = (string) $config->urls->admin;
}
$buildReviewDashboardUrl = static function (string $statusFilter, string $query) use ($reviewDashboardCurrentPath, $reviewDashboardStatusOptions): string {
    $params = [];
    if (isset($reviewDashboardStatusOptions[$statusFilter]) && $statusFilter !== 'all') {
        $params['review_status'] = $statusFilter;
    }
    $query = trim($query);
    if ($query !== '') {
        $params['review_query'] = $query;
    }

    $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    return $queryString !== '' ? ($reviewDashboardCurrentPath . '?' . $queryString) : $reviewDashboardCurrentPath;
};

$reviewAction = trim((string) $input->post('review_admin_action'));
if ($input->requestMethod() === 'POST' && in_array($reviewAction, ['review_update_status', 'review_delete'], true)) {
    $redirectStatusFilter = trim((string) $input->post('review_status_filter'));
    $redirectQueryFilter = trim((string) $input->post('review_query_filter'));
    $redirectUrl = $buildReviewDashboardUrl($redirectStatusFilter, $redirectQueryFilter);

    $csrfValid = false;
    try {
        $csrfValid = $session->CSRF->validate();
    } catch (\Throwable $e) {
        $csrfValid = false;
    }

    if (!$csrfValid) {
        $session->error('Ошибка CSRF. Обновите страницу и повторите.');
        $session->redirect($redirectUrl);
    }

    $reviewId = (int) $input->post('review_id');
    if ($reviewId < 1) {
        $session->error('Некорректный ID отзыва.');
        $session->redirect($redirectUrl);
    }

    try {
        skfoReviewsEnsureTable($database, $reviewTable);
        skfoReviewsBackfillHashes($database, $reviewTable, 400);

        $getReview = $database->prepare("SELECT `id`, `moderation_flags` FROM `$reviewTable` WHERE `id`=:id LIMIT 1");
        $getReview->bindValue(':id', $reviewId, \PDO::PARAM_INT);
        $getReview->execute();
        $reviewRow = $getReview->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!is_array($reviewRow)) {
            $session->error('Отзыв не найден.');
            $session->redirect($redirectUrl);
        }

        if ($reviewAction === 'review_delete') {
            $deleteReview = $database->prepare("DELETE FROM `$reviewTable` WHERE `id`=:id");
            $deleteReview->bindValue(':id', $reviewId, \PDO::PARAM_INT);
            $deleteReview->execute();
            $session->message('Отзыв удалён.');
            $session->redirect($redirectUrl);
        }

        $newStatus = trim((string) $input->post('review_status'));
        $allowedStatuses = ['approved', 'duplicate', 'hidden', 'blocked'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            $session->error('Неизвестный статус отзыва.');
            $session->redirect($redirectUrl);
        }

        $flags = skfoReviewsDecodeFlags((string) ($reviewRow['moderation_flags'] ?? ''));
        $flags = array_values(array_filter($flags, static function (string $flag): bool {
            return trim($flag) !== 'duplicate';
        }));
        if ($newStatus === 'duplicate') {
            $flags[] = 'duplicate';
        }
        $flagsRaw = skfoReviewsEncodeFlags($flags);

        $updateReview = $database->prepare(
            "UPDATE `$reviewTable`
            SET `moderation_status`=:moderation_status, `moderation_flags`=:moderation_flags
            WHERE `id`=:id"
        );
        $updateReview->bindValue(':moderation_status', $newStatus, \PDO::PARAM_STR);
        $updateReview->bindValue(':moderation_flags', $flagsRaw, \PDO::PARAM_STR);
        $updateReview->bindValue(':id', $reviewId, \PDO::PARAM_INT);
        $updateReview->execute();

        $session->message('Статус отзыва обновлён.');
    } catch (\Throwable $e) {
        $session->error('Не удалось обновить отзыв.');
        $log->save('errors', 'dashboard reviews action error: ' . $e->getMessage());
    }

    $session->redirect($redirectUrl);
}

wire()->addHookAfter('Dashboard::getPanels', function ($event) use ($reviewTable, $reviewDashboardStatusOptions) {
    /* Get list of panels */
    $panels = $event->return;

    $reviewStats = [
        'total' => 0,
        'approved' => 0,
        'duplicate' => 0,
        'blocked' => 0,
        'last_7_days' => 0,
    ];
    $reviewStatusCounts = [
        'all' => 0,
        'approved' => 0,
        'duplicate' => 0,
        'blocked' => 0,
        'hidden' => 0,
    ];
    $reviewRows = [];
    $reviewStatusFilter = trim((string) wire('input')->get('review_status'));
    if (!isset($reviewDashboardStatusOptions[$reviewStatusFilter])) $reviewStatusFilter = 'all';
    $reviewQuery = trim((string) wire('input')->get('review_query'));

    try {
        $database = wire('database');
        skfoReviewsEnsureTable($database, $reviewTable);
        skfoReviewsBackfillHashes($database, $reviewTable, 500);
        $reviewStatsStmt = $database->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN moderation_status='approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN moderation_status='duplicate' THEN 1 ELSE 0 END) AS duplicate_count,
                SUM(CASE WHEN moderation_status='blocked' THEN 1 ELSE 0 END) AS blocked,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS last_7_days
            FROM `$reviewTable`"
        );
        $reviewStatsRow = $reviewStatsStmt ? ($reviewStatsStmt->fetch(\PDO::FETCH_ASSOC) ?: []) : [];
        $reviewStats['total'] = (int) ($reviewStatsRow['total'] ?? 0);
        $reviewStats['approved'] = (int) ($reviewStatsRow['approved'] ?? 0);
        $reviewStats['duplicate'] = (int) ($reviewStatsRow['duplicate_count'] ?? 0);
        $reviewStats['blocked'] = (int) ($reviewStatsRow['blocked'] ?? 0);
        $reviewStats['last_7_days'] = (int) ($reviewStatsRow['last_7_days'] ?? 0);

        $reviewStatusCounts['all'] = $reviewStats['total'];
        $statusCountsStmt = $database->query("SELECT `moderation_status`, COUNT(*) AS cnt FROM `$reviewTable` GROUP BY `moderation_status`");
        $statusCountRows = $statusCountsStmt ? ($statusCountsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        foreach ($statusCountRows as $statusCountRow) {
            $statusName = trim((string) ($statusCountRow['moderation_status'] ?? ''));
            if (isset($reviewStatusCounts[$statusName])) {
                $reviewStatusCounts[$statusName] = (int) ($statusCountRow['cnt'] ?? 0);
            }
        }

        $where = [];
        $bindings = [];
        if ($reviewStatusFilter !== 'all') {
            $where[] = "`moderation_status`=:moderation_status";
            $bindings[':moderation_status'] = ['value' => $reviewStatusFilter, 'type' => \PDO::PARAM_STR];
        }
        if ($reviewQuery !== '') {
            $where[] = "(`author` LIKE :review_query OR `review_text` LIKE :review_query)";
            $bindings[':review_query'] = ['value' => '%' . $reviewQuery . '%', 'type' => \PDO::PARAM_STR];
        }

        $reviewsSql = "SELECT `id`, `page_id`, `author`, `review_text`, `rating`, `moderation_status`, `moderation_flags`, `content_hash`, `created_at`
            FROM `$reviewTable`";
        if (count($where)) {
            $reviewsSql .= ' WHERE ' . implode(' AND ', $where);
        }
        $reviewsSql .= ' ORDER BY `created_at` DESC, `id` DESC LIMIT 200';

        $reviewsStmt = $database->prepare($reviewsSql);
        foreach ($bindings as $bindingName => $bindingData) {
            $reviewsStmt->bindValue($bindingName, $bindingData['value'], $bindingData['type']);
        }
        $reviewsStmt->execute();
        $rawReviewRows = $reviewsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $hashes = [];
        $pageIds = [];
        foreach ($rawReviewRows as $rawReviewRow) {
            $contentHash = trim((string) ($rawReviewRow['content_hash'] ?? ''));
            if ($contentHash !== '') $hashes[$contentHash] = true;
            $pageId = (int) ($rawReviewRow['page_id'] ?? 0);
            if ($pageId > 0) $pageIds[$pageId] = true;
        }

        $duplicateCountByKey = [];
        if (count($hashes) && count($pageIds)) {
            $hashKeys = array_keys($hashes);
            $pageIdKeys = array_keys($pageIds);
            $hashPlaceholders = [];
            $pagePlaceholders = [];
            $dupSql = "SELECT `page_id`, `content_hash`, COUNT(*) AS cnt FROM `$reviewTable` WHERE `content_hash` IN (";
            foreach ($hashKeys as $hashIndex => $hashValue) {
                $placeholder = ':hash_' . $hashIndex;
                $hashPlaceholders[] = $placeholder;
            }
            $dupSql .= implode(', ', $hashPlaceholders) . ') AND `page_id` IN (';
            foreach ($pageIdKeys as $pageIdIndex => $pageIdValue) {
                $placeholder = ':page_id_' . $pageIdIndex;
                $pagePlaceholders[] = $placeholder;
            }
            $dupSql .= implode(', ', $pagePlaceholders) . ') GROUP BY `page_id`, `content_hash`';

            $dupStmt = $database->prepare($dupSql);
            foreach ($hashKeys as $hashIndex => $hashValue) {
                $dupStmt->bindValue(':hash_' . $hashIndex, $hashValue, \PDO::PARAM_STR);
            }
            foreach ($pageIdKeys as $pageIdIndex => $pageIdValue) {
                $dupStmt->bindValue(':page_id_' . $pageIdIndex, (int) $pageIdValue, \PDO::PARAM_INT);
            }
            $dupStmt->execute();
            $dupRows = $dupStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($dupRows as $dupRow) {
                $hashValue = trim((string) ($dupRow['content_hash'] ?? ''));
                $pageId = (int) ($dupRow['page_id'] ?? 0);
                if ($hashValue === '' || $pageId < 1) continue;
                $duplicateCountByKey[$pageId . '|' . $hashValue] = (int) ($dupRow['cnt'] ?? 0);
            }
        }

        $pageLabelById = [];
        foreach ($rawReviewRows as $rawReviewRow) {
            $pageId = (int) ($rawReviewRow['page_id'] ?? 0);
            if ($pageId < 1 || isset($pageLabelById[$pageId])) continue;

            $reviewPage = wire('pages')->get($pageId);
            if ($reviewPage && $reviewPage->id) {
                $pageTitle = trim((string) $reviewPage->title);
                $pagePath = trim((string) $reviewPage->path);
                $label = $pagePath !== '' ? $pagePath : ('ID ' . $pageId);
                if ($pageTitle !== '') $label .= ' (' . $pageTitle . ')';
                $pageLabelById[$pageId] = $label;
            } else {
                $pageLabelById[$pageId] = 'ID ' . $pageId;
            }
        }

        foreach ($rawReviewRows as $rawReviewRow) {
            $status = trim((string) ($rawReviewRow['moderation_status'] ?? ''));
            if ($status === '') $status = 'approved';
            $flags = skfoReviewsDecodeFlags((string) ($rawReviewRow['moderation_flags'] ?? ''));
            $contentHash = trim((string) ($rawReviewRow['content_hash'] ?? ''));
            $pageId = (int) ($rawReviewRow['page_id'] ?? 0);
            $duplicateCount = $contentHash !== '' ? (int) ($duplicateCountByKey[$pageId . '|' . $contentHash] ?? 0) : 0;

            $banCategories = [];
            foreach ($flags as $flag) {
                if (strpos($flag, 'ban-') === 0) {
                    $banCategory = trim(substr($flag, 4));
                    if ($banCategory !== '') $banCategories[] = $banCategory;
                }
            }
            $flagLabels = [];
            if (in_array('duplicate', $flags, true)) $flagLabels[] = 'Дубликат';
            foreach (skfoReviewsBanwordLabels(array_values(array_unique($banCategories))) as $banLabel) {
                $flagLabels[] = 'Бан: ' . $banLabel;
            }

            $reviewRows[] = [
                'id' => (int) ($rawReviewRow['id'] ?? 0),
                'author' => trim((string) ($rawReviewRow['author'] ?? 'Гость')),
                'review_text' => trim((string) ($rawReviewRow['review_text'] ?? '')),
                'rating' => max(1, min(5, (int) ($rawReviewRow['rating'] ?? 5))),
                'status' => $status,
                'status_label' => skfoReviewsStatusLabel($status),
                'status_class' => preg_replace('/[^a-z0-9_-]+/i', '-', $status) ?: 'unknown',
                'flag_labels' => array_values(array_unique($flagLabels)),
                'duplicate_count' => $duplicateCount,
                'page_label' => (string) ($pageLabelById[$pageId] ?? ('ID ' . $pageId)),
                'created_at_label' => (static function (string $rawDate): string {
                    $rawDate = trim($rawDate);
                    if ($rawDate === '') return '';
                    $timestamp = strtotime($rawDate);
                    if ($timestamp === false || $timestamp < 1) return $rawDate;
                    return date('d.m.Y H:i', $timestamp);
                })((string) ($rawReviewRow['created_at'] ?? '')),
            ];
        }
    } catch (\Throwable $e) {
        wire('log')->save('errors', 'admin dashboard reviews stats error: ' . $e->getMessage());
    }

    // Define regions with their IDs
    $regions = [
        '1215' => 'Республика Дагестан',
        '1216' => 'Республика Ингушетия',
        '1217' => 'Кабардино-Балкарская Республика',
        '1218' => 'Карачаево-Черкесская Республика',
        '1219' => 'Республика Северная Осетия — Алания',
        '1220' => 'Чеченская Республика',
        '1221' => 'Ставропольский край'
    ];

    // Define templates to track
    $templates = ['article', 'place', 'hotel', 'tour', 'car', 'guide'];

    // Build statistics array
    $stats = [];
    foreach ($templates as $template) {
        $stats[$template] = [];
        foreach (array_keys($regions) as $region_id) {
            $count = wire('pages')->count("template=$template, region=$region_id");
            $stats[$template][$region_id] = $count;
        }
    }

    // Prepare chart datasets
    $datasets = [];
    $colors = [
        'rgba(75, 192, 192, 0.6)', // Article
        'rgba(255, 99, 132, 0.6)',  // Place
        'rgba(54, 162, 235, 0.6)',  // Hotel
        'rgba(255, 206, 86, 0.6)',   // Tour
        'rgba(153, 102, 255, 0.6)', // Car
        'rgba(255, 159, 64, 0.6)',  // Guide
    ];

    foreach ($templates as $index => $template) {
        $templateObj = wire('templates')->get($template);
        $templateLabel = $templateObj && $templateObj->label ? $templateObj->label : ucfirst($template); // Use label if available, else capitalize template name
        $datasets[] = [
            'label' => $templateLabel,
            'data' => array_values($stats[$template]),
            'backgroundColor' => $colors[$index],
            'borderColor' => str_replace('0.6', '1', $colors[$index]),
            'borderWidth' => 1
        ];
    }

    // Add chart panel
    $panels->add([
        'size' => 'full',
        'panel' => 'chart',
        'title' => 'Статистика по регионам',
        'data' => [
            'chart' => [
                'type' => 'bar', // Stacked bar chart
                'data' => [
                    'labels' => array_values($regions), // Region names as x-axis labels
                    'datasets' => $datasets
                ],
                'options' => [
                    'aspectRatio' => 2.5,
                    'scales' => [
                        'xAxes' => [
                            ['gridLines' => ['display' => false]]
                        ],
                        'yAxes' => [
                            [
                                'stacked' => true, // Enable stacking
                                'ticks' => [
                                    'beginAtZero' => true,
                                    'stepSize' => 1 // Ensure integer steps for counts
                                ]
                            ]
                        ]
                    ],
                    'plugins' => [
                        'legend' => [
                            'display' => true,
                            'position' => 'top'
                        ]
                    ]
                ]
            ]
        ]
    ]);

    // Preserve existing panels
    $panels->add([
        'size' => 'mini',
        'panel' => 'number',
        'title' => 'Статьи',
        'data' => [
            'number' => wire('pages')->count('template=article'),
            'detail' => 'Всего статей',
            'trend' => 'none'
        ]
    ]);

    $panels->add([
        'size' => 'full',
        'panel' => 'template',
        'title' => 'Модерация отзывов',
        'data' => [
            'template' => 'dashboard/reviews-moderation.php',
            'variables' => [
                'reviewRows' => $reviewRows,
                'reviewStatusOptions' => $reviewDashboardStatusOptions,
                'reviewStatusFilter' => $reviewStatusFilter,
                'reviewQuery' => $reviewQuery,
                'reviewStatusCounts' => $reviewStatusCounts,
                'csrfTokenName' => wire('session')->CSRF->getTokenName(),
                'csrfTokenValue' => wire('session')->CSRF->getTokenValue(),
            ]
        ],
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'number',
        'title' => 'Достопримечательности',
        'data' => [
            'number' => wire('pages')->count('template=place'),
            'detail' => 'Всего мест',
            'trend' => 'none'
        ]
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'number',
        'title' => 'Санатории и отели',
        'data' => [
            'number' => wire('pages')->count('template=hotel'),
            'detail' => 'Всего отелей',
            'trend' => 'none'
        ]
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'number',
        'title' => 'Туры',
        'data' => [
            'number' => wire('pages')->count('template=tour'),
            'detail' => 'Всего туров',
            'trend' => 'none'
        ]
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'number',
        'title' => 'Отзывы',
        'data' => [
            'number' => $reviewStats['total'],
            'detail' => 'Всего отзывов',
            'trend' => 'none'
        ]
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'number',
        'title' => 'Отзывы (7 дней)',
        'data' => [
            'number' => $reviewStats['last_7_days'],
            'detail' => 'Новые за 7 дней',
            'trend' => 'none'
        ]
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'number',
        'title' => 'Одобрены отзывы',
        'data' => [
            'number' => $reviewStats['approved'],
            'detail' => 'Публичные отзывы',
            'trend' => 'none'
        ]
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'number',
        'title' => 'Дубликаты отзывов',
        'data' => [
            'number' => $reviewStats['duplicate'],
            'detail' => 'На модерации как дубликаты',
            'trend' => 'none'
        ]
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'number',
        'title' => 'Блок по бан-словам',
        'data' => [
            'number' => $reviewStats['blocked'],
            'detail' => 'Заблокированные отзывы',
            'trend' => 'none'
        ]
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'add-new',
        'title' => 'Новая страница',
        'data' => [
            'display' => 'list',
        ],
    ]);

    $panels->add([
        'size' => 'normal',
        'panel' => 'collection',
        'title' => 'Статьи',
        'data' => [
            'collection' => 'template=article, limit=12, sort=sort',
            'sortable' => true,
            'headers' => false,
            'columns' => ['title', 'region'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'collection',
        'title' => 'Лендинги',
        'data' => [
            'collection' => 'template=lp, limit=12, include=hidden',
            'sortable' => true,
            'headers' => true,
            'columns' => ['title'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'full',
        'panel' => 'collection',
        'title' => 'Места',
        'data' => [
            'collection' => 'template=place, limit=10',
            'sortable' => true,
            'headers' => false,
            'columns' => ['title', 'region'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'full',
        'panel' => 'collection',
        'title' => 'Отели',
        'data' => [
            'collection' => 'template=hotel, limit=10, sort=date',
            'sortable' => true,
            'headers' => true,
            'columns' => ['title', 'hotel_info.type', 'region', 'hotel_info.stars', 'hotel_info.rooms', 'hotel_conditions.check_in', 'hotel_conditions.check_out'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'full',
        'panel' => 'collection',
        'title' => 'Туры',
        'data' => [
            'collection' => 'template=tour, limit=10',
            'sortable' => true,
            'headers' => true,
            'columns' => ['title', 'tour.qty', 'tour.duration', 'guide'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'full',
        'panel' => 'collection',
        'title' => 'Машины',
        'data' => [
            'collection' => 'template=car, limit=10',
            'sortable' => true,
            'headers' => true,
            'columns' => ['car_info.type', 'car_info.year', 'car_info.make', 'car_info.model', 'car_info.seats'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'collection',
        'title' => 'Гиды',
        'data' => [
            'collection' => 'template=guide, limit=10',
            'sortable' => true,
            'headers' => false,
            'columns' => ['title'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'normal',
        'panel' => 'collection',
        'title' => 'Регионы',
        'data' => [
            'collection' => 'template=region, limit=10',
            'sortable' => true,
            'headers' => false,
            'columns' => ['title'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

    $panels->add([
        'size' => 'mini',
        'panel' => 'collection',
        'title' => 'Города',
        'data' => [
            'collection' => 'template=city, limit=10',
            'sortable' => true,
            'headers' => false,
            'columns' => ['title'],
            'actions' => ['view', 'edit', 'trash'],
        ],
    ]);

});

wire()->addHookAfter('Dashboard::getSettings', function ($event) {
    $event->return->displayIcons = true;
});

/* Make sure to add the hook *before* the default admin process */
require $config->paths->adminTemplates . 'controller.php';
