<?php namespace ProcessWire;

$rows = isset($reviewRows) && is_array($reviewRows) ? $reviewRows : [];
$statusOptions = isset($reviewStatusOptions) && is_array($reviewStatusOptions) ? $reviewStatusOptions : [];
$statusFilter = trim((string) ($reviewStatusFilter ?? 'all'));
$searchQuery = trim((string) ($reviewQuery ?? ''));
$statusCounts = isset($reviewStatusCounts) && is_array($reviewStatusCounts) ? $reviewStatusCounts : [];
$tokenName = trim((string) ($csrfTokenName ?? ''));
$tokenValue = trim((string) ($csrfTokenValue ?? ''));

if (!isset($statusOptions[$statusFilter])) {
    $statusFilter = 'all';
}
?>

<div class="reviews-dashboard">
    <form class="reviews-dashboard__filters" method="get">
        <label>
            <span>Статус</span>
            <select name="review_status">
                <?php foreach ($statusOptions as $statusKey => $statusTitle): ?>
                    <option value="<?php echo $sanitizer->entities((string) $statusKey); ?>"<?php echo $statusFilter === $statusKey ? ' selected' : ''; ?>>
                        <?php echo $sanitizer->entities((string) $statusTitle); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Поиск</span>
            <input type="text" name="review_query" value="<?php echo $sanitizer->entities($searchQuery); ?>" placeholder="Автор или текст" />
        </label>
        <div class="reviews-dashboard__filter-actions">
            <button type="submit">Применить</button>
            <a href="?">Сбросить</a>
        </div>
    </form>

    <div class="reviews-dashboard__stats">
        <div class="reviews-dashboard__stat"><strong><?php echo (int) ($statusCounts['all'] ?? 0); ?></strong><span>Всего</span></div>
        <div class="reviews-dashboard__stat"><strong><?php echo (int) ($statusCounts['approved'] ?? 0); ?></strong><span>Одобрены</span></div>
        <div class="reviews-dashboard__stat"><strong><?php echo (int) ($statusCounts['duplicate'] ?? 0); ?></strong><span>Дубликаты</span></div>
        <div class="reviews-dashboard__stat"><strong><?php echo (int) ($statusCounts['blocked'] ?? 0); ?></strong><span>Заблокированы</span></div>
        <div class="reviews-dashboard__stat"><strong><?php echo (int) ($statusCounts['hidden'] ?? 0); ?></strong><span>Скрыты</span></div>
    </div>

    <div class="reviews-dashboard__list">
        <?php foreach ($rows as $row): ?>
            <?php
            $reviewText = trim((string) ($row['review_text'] ?? ''));
            $reviewTextLength = function_exists('mb_strlen') ? mb_strlen($reviewText, 'UTF-8') : strlen($reviewText);
            if ($reviewTextLength > 500) {
                $reviewText = function_exists('mb_substr') ? mb_substr($reviewText, 0, 500, 'UTF-8') : substr($reviewText, 0, 500);
                $reviewText = rtrim($reviewText) . '...';
            }
            $statusClass = trim((string) ($row['status_class'] ?? 'unknown'));
            if ($statusClass === '') $statusClass = 'unknown';
            ?>
            <article class="reviews-dashboard__item">
                <header class="reviews-dashboard__item-head">
                    <div>
                        <strong><?php echo $sanitizer->entities((string) ($row['author'] ?? 'Гость')); ?></strong>
                        <div class="reviews-dashboard__meta"><?php echo $sanitizer->entities((string) ($row['created_at_label'] ?? '')); ?></div>
                    </div>
                    <div class="reviews-dashboard__badges">
                        <span class="reviews-dashboard__badge is-status-<?php echo $sanitizer->entities($statusClass); ?>">
                            <?php echo $sanitizer->entities((string) ($row['status_label'] ?? '')); ?>
                        </span>
                        <span class="reviews-dashboard__badge">★ <?php echo (int) ($row['rating'] ?? 5); ?>/5</span>
                        <?php if ((int) ($row['duplicate_count'] ?? 0) > 1): ?>
                            <span class="reviews-dashboard__badge is-warning">Повторов: <?php echo (int) ($row['duplicate_count'] ?? 0); ?></span>
                        <?php endif; ?>
                        <?php foreach ((array) ($row['flag_labels'] ?? []) as $flagLabel): ?>
                            <span class="reviews-dashboard__badge is-danger"><?php echo $sanitizer->entities((string) $flagLabel); ?></span>
                        <?php endforeach; ?>
                    </div>
                </header>

                <p class="reviews-dashboard__text"><?php echo nl2br($sanitizer->entities($reviewText)); ?></p>
                <div class="reviews-dashboard__meta">Страница: <?php echo $sanitizer->entities((string) ($row['page_label'] ?? '')); ?></div>

                <div class="reviews-dashboard__actions">
                    <?php if (($row['status'] ?? '') !== 'approved'): ?>
                        <form method="post">
                            <input type="hidden" name="review_admin_action" value="review_update_status" />
                            <input type="hidden" name="review_status" value="approved" />
                            <input type="hidden" name="review_id" value="<?php echo (int) ($row['id'] ?? 0); ?>" />
                            <input type="hidden" name="review_status_filter" value="<?php echo $sanitizer->entities($statusFilter); ?>" />
                            <input type="hidden" name="review_query_filter" value="<?php echo $sanitizer->entities($searchQuery); ?>" />
                            <input type="hidden" name="<?php echo $sanitizer->entities($tokenName); ?>" value="<?php echo $sanitizer->entities($tokenValue); ?>" />
                            <button type="submit">Одобрить</button>
                        </form>
                    <?php endif; ?>

                    <?php if (($row['status'] ?? '') !== 'duplicate'): ?>
                        <form method="post">
                            <input type="hidden" name="review_admin_action" value="review_update_status" />
                            <input type="hidden" name="review_status" value="duplicate" />
                            <input type="hidden" name="review_id" value="<?php echo (int) ($row['id'] ?? 0); ?>" />
                            <input type="hidden" name="review_status_filter" value="<?php echo $sanitizer->entities($statusFilter); ?>" />
                            <input type="hidden" name="review_query_filter" value="<?php echo $sanitizer->entities($searchQuery); ?>" />
                            <input type="hidden" name="<?php echo $sanitizer->entities($tokenName); ?>" value="<?php echo $sanitizer->entities($tokenValue); ?>" />
                            <button type="submit">Дубликат</button>
                        </form>
                    <?php endif; ?>

                    <?php if (($row['status'] ?? '') !== 'hidden'): ?>
                        <form method="post">
                            <input type="hidden" name="review_admin_action" value="review_update_status" />
                            <input type="hidden" name="review_status" value="hidden" />
                            <input type="hidden" name="review_id" value="<?php echo (int) ($row['id'] ?? 0); ?>" />
                            <input type="hidden" name="review_status_filter" value="<?php echo $sanitizer->entities($statusFilter); ?>" />
                            <input type="hidden" name="review_query_filter" value="<?php echo $sanitizer->entities($searchQuery); ?>" />
                            <input type="hidden" name="<?php echo $sanitizer->entities($tokenName); ?>" value="<?php echo $sanitizer->entities($tokenValue); ?>" />
                            <button type="submit">Скрыть</button>
                        </form>
                    <?php endif; ?>

                    <form method="post" onsubmit="return confirm('Удалить отзыв?');">
                        <input type="hidden" name="review_admin_action" value="review_delete" />
                        <input type="hidden" name="review_id" value="<?php echo (int) ($row['id'] ?? 0); ?>" />
                        <input type="hidden" name="review_status_filter" value="<?php echo $sanitizer->entities($statusFilter); ?>" />
                        <input type="hidden" name="review_query_filter" value="<?php echo $sanitizer->entities($searchQuery); ?>" />
                        <input type="hidden" name="<?php echo $sanitizer->entities($tokenName); ?>" value="<?php echo $sanitizer->entities($tokenValue); ?>" />
                        <button class="is-danger" type="submit">Удалить</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (!count($rows)): ?>
            <div class="reviews-dashboard__empty">По текущему фильтру отзывов нет.</div>
        <?php endif; ?>
    </div>
</div>
