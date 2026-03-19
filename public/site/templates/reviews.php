<?php namespace ProcessWire;

require_once __DIR__ . '/_reviews_moderation.php';

$reviewTables = [
	'tour' => 'tour_reviews',
	'hotel' => 'hotel_reviews',
];

$reviewError = '';
$reviewSuccess = '';
$reviewAuthorValue = '';
$reviewTextValue = '';
$reviewRatingValue = 5;
$reviewSubjectValue = '';
$flashPrefix = 'reviews_form_';
$pullFlash = static function($session, string $key) {
	$value = $session->get($key);
	$session->remove($key);
	return $value;
};
$setFlash = static function($session, string $key, $value): void {
	if ($value === '' || $value === null) {
		$session->remove($key);
		return;
	}
	$session->set($key, $value);
};
$textLength = static function(string $value): int {
	return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
};
$firstLetter = static function(string $value): string {
	$value = trim($value);
	if ($value === '') return '?';
	return function_exists('mb_substr') ? mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($value, 0, 1));
};
$avatarColorKeys = ['blue', 'yellow', 'gray', 'red', 'green', 'cyan', 'purple'];
$avatarClassMap = [
	'blue' => 'is-blue',
	'yellow' => 'is-yellow',
	'gray' => 'is-gray',
	'red' => 'is-red',
	'green' => 'is-green',
	'cyan' => 'is-cyan',
	'purple' => 'is-purple',
];
$normalizeUploadedFiles = static function($filesField): array {
	if (!is_array($filesField) || !isset($filesField['name'])) return [];

	$names = $filesField['name'];
	$tmpNames = $filesField['tmp_name'] ?? [];
	$errors = $filesField['error'] ?? [];
	$sizes = $filesField['size'] ?? [];

	if (!is_array($names)) {
		$names = [$names];
		$tmpNames = [is_array($tmpNames) ? '' : (string) $tmpNames];
		$errors = [is_array($errors) ? UPLOAD_ERR_NO_FILE : (int) $errors];
		$sizes = [is_array($sizes) ? 0 : (int) $sizes];
	}

	$normalized = [];
	$total = count($names);
	for ($i = 0; $i < $total; $i++) {
		$normalized[] = [
			'name' => trim((string) ($names[$i] ?? '')),
			'tmp_name' => (string) ($tmpNames[$i] ?? ''),
			'error' => (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE),
			'size' => (int) ($sizes[$i] ?? 0),
		];
	}

	return $normalized;
};
$prepareReviewUploads = static function($filesField) use ($normalizeUploadedFiles): array {
	$maxPhotos = 12;
	$maxFileSize = 8 * 1024 * 1024;
	$allowedMimeExt = [
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/webp' => 'webp',
		'image/gif' => 'gif',
	];
	$validFiles = [];
	$normalizedFiles = $normalizeUploadedFiles($filesField);

	foreach ($normalizedFiles as $file) {
		$errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
		if ($errorCode === UPLOAD_ERR_NO_FILE) continue;
		if ($errorCode !== UPLOAD_ERR_OK) {
			return ['files' => [], 'error' => 'Не удалось загрузить один из файлов. Попробуйте снова.'];
		}

		if (count($validFiles) >= $maxPhotos) {
			return ['files' => [], 'error' => 'Можно загрузить не более 12 фотографий к одному отзыву.'];
		}

		$tmpName = (string) ($file['tmp_name'] ?? '');
		$fileSize = (int) ($file['size'] ?? 0);
		if ($tmpName === '' || !is_uploaded_file($tmpName)) {
			return ['files' => [], 'error' => 'Не удалось обработать загруженный файл.'];
		}
		if ($fileSize < 1) {
			return ['files' => [], 'error' => 'Один из файлов пустой.'];
		}
		if ($fileSize > $maxFileSize) {
			return ['files' => [], 'error' => 'Размер каждой фотографии должен быть не больше 8 МБ.'];
		}

		$mimeType = '';
		if (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			if ($finfo !== false) {
				$detectedMime = finfo_file($finfo, $tmpName);
				if (is_string($detectedMime)) {
					$mimeType = trim($detectedMime);
				}
				finfo_close($finfo);
			}
		}
		if ($mimeType === '' && function_exists('mime_content_type')) {
			$detectedMime = mime_content_type($tmpName);
			if (is_string($detectedMime)) {
				$mimeType = trim($detectedMime);
			}
		}
		if ($mimeType === '' || !isset($allowedMimeExt[$mimeType])) {
			return ['files' => [], 'error' => 'Поддерживаются только изображения JPG, PNG, WEBP и GIF.'];
		}

		$validFiles[] = [
			'tmp_name' => $tmpName,
			'ext' => $allowedMimeExt[$mimeType],
		];
	}

	return ['files' => $validFiles, 'error' => ''];
};
$storeReviewPhotos = static function(int $reviewId, array $validFiles, string $scope = '') use ($config, $log): array {
	if ($reviewId < 1 || !count($validFiles)) {
		return ['photos' => [], 'warning' => ''];
	}

	$scope = trim(preg_replace('/[^a-z0-9_-]+/i', '', $scope) ?? '');
	$reviewDirRelative = '/reviews/' . ($scope !== '' ? ($scope . '/') : '') . $reviewId . '/';
	$reviewDirPath = rtrim((string) $config->paths->assets, '/') . $reviewDirRelative;
	$reviewDirUrl = rtrim((string) $config->urls->assets, '/') . $reviewDirRelative;
	if (!is_dir($reviewDirPath) && !@mkdir($reviewDirPath, 0755, true)) {
		$log->save('errors', 'reviews page photo upload error: failed to create directory ' . $reviewDirPath);
		return ['photos' => [], 'warning' => 'Отзыв сохранён, но фотографии загрузить не удалось.'];
	}

	$storedPhotos = [];
	$hasMoveError = false;
	foreach ($validFiles as $index => $file) {
		$timestampPart = date('YmdHis');
		try {
			$randomPart = bin2hex(random_bytes(3));
		} catch (\Throwable $e) {
			$randomPart = (string) mt_rand(100000, 999999);
		}
		$fileName = sprintf('%02d-%s-%s.%s', (int) $index + 1, $timestampPart, $randomPart, (string) ($file['ext'] ?? 'jpg'));
		$targetPath = $reviewDirPath . $fileName;
		if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $targetPath)) {
			$hasMoveError = true;
			continue;
		}
		@chmod($targetPath, 0644);
		$storedPhotos[] = $reviewDirUrl . rawurlencode($fileName);
	}

	if ($hasMoveError) {
		$log->save('errors', 'reviews page photo upload warning: not all files moved for review #' . $reviewId);
	}

	$warning = $hasMoveError ? 'Часть фотографий не удалось загрузить.' : '';
	return ['photos' => $storedPhotos, 'warning' => $warning];
};
$authUser = isset($skfoAuthUser) && is_array($skfoAuthUser) ? $skfoAuthUser : null;
$isAuthLoggedIn = $authUser !== null;
$resolveReviewAuthor = static function(?array $user): string {
	if (!$user) return '';

	$name = trim((string) ($user['name'] ?? ''));
	if ($name !== '') return $name;

	$email = trim((string) ($user['email'] ?? ''));
	if ($email !== '') {
		$emailName = strstr($email, '@', true);
		if (is_string($emailName) && trim($emailName) !== '') {
			return trim($emailName);
		}
		return $email;
	}

	return 'Пользователь';
};
$resolveTourTitle = static function(Page $tourPage): string {
	$title = $tourPage->hasField('tour_title') ? trim((string) $tourPage->getUnformatted('tour_title')) : '';
	if ($title === '') $title = trim((string) $tourPage->title);
	if ($title === '') $title = trim((string) $tourPage->name);
	return $title;
};
$resolveHotelTitle = static function(Page $hotelPage): string {
	$title = trim((string) $hotelPage->title);
	if ($title === '' && $hotelPage->hasField('hotel_title')) {
		$title = trim((string) $hotelPage->getUnformatted('hotel_title'));
	}
	if ($title === '') $title = trim((string) $hotelPage->name);
	return $title;
};
$buildReviewSubject = static function(string $type, int $id): string {
	$type = trim($type);
	if ($type === '' || $id < 1) return '';
	return $type . ':' . $id;
};
$parseReviewSubject = static function(string $value): array {
	$value = trim($value);
	if (preg_match('/^(tour|hotel):(\d+)$/', $value, $matches) !== 1) {
		return ['type' => '', 'id' => 0, 'subject' => ''];
	}
	$type = (string) ($matches[1] ?? '');
	$id = (int) ($matches[2] ?? 0);
	if ($id < 1) return ['type' => '', 'id' => 0, 'subject' => ''];
	return ['type' => $type, 'id' => $id, 'subject' => $type . ':' . $id];
};

$tourOptions = [];
$tourOptionMap = [];
try {
	$tourPages = $pages->find('template=tour, include=all, check_access=0, status<1024, sort=title, limit=500');
	foreach ($tourPages as $tourPage) {
		if (!$tourPage instanceof Page || !$tourPage->id) continue;

		$tourTitle = $resolveTourTitle($tourPage);
		if ($tourTitle === '') continue;

		$tourOption = [
			'id' => (int) $tourPage->id,
			'title' => $tourTitle,
			'url' => (string) $tourPage->url,
		];
		$tourOptions[] = $tourOption;
		$tourOptionMap[(int) $tourPage->id] = $tourOption;
	}
} catch (\Throwable $e) {
	$log->save('errors', "reviews page tours list error: " . $e->getMessage());
}
$hotelOptions = [];
$hotelOptionMap = [];
try {
	$hotelPages = $pages->find('template=hotel, include=all, check_access=0, status<1024, sort=title, limit=500');
	foreach ($hotelPages as $hotelPage) {
		if (!$hotelPage instanceof Page || !$hotelPage->id) continue;

		$hotelTitle = $resolveHotelTitle($hotelPage);
		if ($hotelTitle === '') continue;

		$hotelOption = [
			'id' => (int) $hotelPage->id,
			'title' => $hotelTitle,
			'url' => (string) $hotelPage->url,
		];
		$hotelOptions[] = $hotelOption;
		$hotelOptionMap[(int) $hotelPage->id] = $hotelOption;
	}
} catch (\Throwable $e) {
	$log->save('errors', "reviews page hotels list error: " . $e->getMessage());
}
$reviewTargetMap = [];
foreach ($tourOptions as $tourOption) {
	$targetId = (int) ($tourOption['id'] ?? 0);
	if ($targetId < 1) continue;
	$subject = $buildReviewSubject('tour', $targetId);
	$reviewTargetMap[$subject] = [
		'type' => 'tour',
		'id' => $targetId,
		'title' => (string) ($tourOption['title'] ?? ''),
		'url' => (string) ($tourOption['url'] ?? ''),
	];
}
foreach ($hotelOptions as $hotelOption) {
	$targetId = (int) ($hotelOption['id'] ?? 0);
	if ($targetId < 1) continue;
	$subject = $buildReviewSubject('hotel', $targetId);
	$reviewTargetMap[$subject] = [
		'type' => 'hotel',
		'id' => $targetId,
		'title' => (string) ($hotelOption['title'] ?? ''),
		'url' => (string) ($hotelOption['url'] ?? ''),
	];
}
$reviewTargetTypeLabels = [
	'tour' => 'Тур',
	'hotel' => 'Отель',
];
$hasReviewTargets = count($reviewTargetMap) > 0;

$reviewError = (string) ($pullFlash($session, $flashPrefix . 'error') ?? '');
$reviewSuccess = (string) ($pullFlash($session, $flashPrefix . 'success') ?? '');
$reviewTextValue = (string) ($pullFlash($session, $flashPrefix . 'text') ?? '');
$reviewRatingFlash = (int) ($pullFlash($session, $flashPrefix . 'rating') ?? 0);
$reviewSubjectFlash = trim((string) ($pullFlash($session, $flashPrefix . 'subject') ?? ''));
if ($reviewSubjectFlash !== '' && isset($reviewTargetMap[$reviewSubjectFlash])) {
	$reviewSubjectValue = $reviewSubjectFlash;
}
if ($reviewRatingFlash >= 1 && $reviewRatingFlash <= 5) {
	$reviewRatingValue = $reviewRatingFlash;
}
$reviewSubjectRequest = trim((string) $input->get('review_subject'));
if ($reviewSubjectRequest !== '' && isset($reviewTargetMap[$reviewSubjectRequest])) {
	$reviewSubjectValue = $reviewSubjectRequest;
}
$reviewAuthorValue = $resolveReviewAuthor($authUser);

if ($input->requestMethod() === 'POST' && $input->post('review_form') === 'reviews_page') {
	$reviewAuthorValue = $resolveReviewAuthor($authUser);
	$reviewTextValue = trim((string) $input->post('review_text'));
	$reviewRatingValue = (int) $input->post('review_rating');
	$reviewSubjectRaw = trim((string) $input->post('review_subject'));
	if ($reviewSubjectRaw === '') {
		$legacyTourId = (int) $input->post('review_tour_id');
		if ($legacyTourId > 0) {
			$reviewSubjectRaw = $buildReviewSubject('tour', $legacyTourId);
		}
	}
	$reviewSubjectParsed = $parseReviewSubject($reviewSubjectRaw);
	$reviewTargetType = (string) ($reviewSubjectParsed['type'] ?? '');
	$reviewTargetId = (int) ($reviewSubjectParsed['id'] ?? 0);
	$reviewSubjectValue = (string) ($reviewSubjectParsed['subject'] ?? '');
	$reviewTarget = $reviewSubjectValue !== '' ? ($reviewTargetMap[$reviewSubjectValue] ?? null) : null;
	$preparedReviewPhotos = $prepareReviewUploads($_FILES['review_photos'] ?? null);

	try {
		$csrfValid = $session->CSRF->validate();
	} catch (\Throwable $e) {
		$csrfValid = false;
	}

	if (!$isAuthLoggedIn) {
		$reviewError = 'Чтобы оставить отзыв, войдите в свой аккаунт.';
	} elseif (!$csrfValid) {
		$reviewError = 'Ошибка безопасности формы. Обновите страницу и попробуйте снова.';
	} elseif (!$hasReviewTargets) {
		$reviewError = 'Сейчас нет доступных туров и отелей для отзывов.';
	} elseif (!$reviewTarget || $reviewTargetId < 1 || !isset($reviewTables[$reviewTargetType])) {
		$reviewError = 'Выберите тур или отель из списка.';
	} elseif ($reviewTextValue === '' || $textLength($reviewTextValue) < 8) {
		$reviewError = 'Добавьте текст отзыва (минимум 8 символов).';
	} elseif ($reviewRatingValue < 1 || $reviewRatingValue > 5) {
		$reviewError = 'Выберите оценку от 1 до 5.';
	} elseif (($preparedReviewPhotos['error'] ?? '') !== '') {
		$reviewError = (string) $preparedReviewPhotos['error'];
	} else {
		$reviewTable = (string) $reviewTables[$reviewTargetType];
		$photoScope = $reviewTargetType === 'tour' ? '' : $reviewTargetType;
		try {
			skfoReviewsEnsureTable($database, $reviewTable);
			skfoReviewsBackfillHashes($database, $reviewTable, 50);

			$moderation = skfoReviewsBuildModerationDecision($database, $reviewTable, $reviewTargetId, $reviewTextValue);
			$moderationStatus = (string) ($moderation['status'] ?? 'approved');
			$contentHash = (string) ($moderation['content_hash'] ?? '');
			$moderationFlags = skfoReviewsEncodeFlags((array) ($moderation['flags'] ?? []));

			$avatarColorKey = $avatarColorKeys[array_rand($avatarColorKeys)];
			$insertReview = $database->prepare(
				"INSERT INTO `$reviewTable` (`page_id`, `author`, `review_text`, `rating`, `avatar_color`, `photos_json`, `content_hash`, `moderation_status`, `moderation_flags`)
				VALUES (:page_id, :author, :review_text, :rating, :avatar_color, :photos_json, :content_hash, :moderation_status, :moderation_flags)"
			);
			$insertReview->bindValue(':page_id', $reviewTargetId, \PDO::PARAM_INT);
			$insertReview->bindValue(':author', $reviewAuthorValue, \PDO::PARAM_STR);
			$insertReview->bindValue(':review_text', $reviewTextValue, \PDO::PARAM_STR);
			$insertReview->bindValue(':rating', $reviewRatingValue, \PDO::PARAM_INT);
			$insertReview->bindValue(':avatar_color', $avatarColorKey, \PDO::PARAM_STR);
			$insertReview->bindValue(':photos_json', '', \PDO::PARAM_STR);
			$insertReview->bindValue(':content_hash', $contentHash, \PDO::PARAM_STR);
			$insertReview->bindValue(':moderation_status', $moderationStatus, \PDO::PARAM_STR);
			$insertReview->bindValue(':moderation_flags', $moderationFlags, \PDO::PARAM_STR);
			$insertReview->execute();

			$photoUploadWarning = '';
			$createdReviewId = (int) $database->lastInsertId();
			if ($createdReviewId > 0 && count((array) ($preparedReviewPhotos['files'] ?? []))) {
				$storedPhotosResult = $storeReviewPhotos($createdReviewId, (array) $preparedReviewPhotos['files'], $photoScope);
				$photosJson = skfoReviewsEncodePhotos((array) ($storedPhotosResult['photos'] ?? []));
				$updateReviewPhotos = $database->prepare("UPDATE `$reviewTable` SET `photos_json`=:photos_json WHERE `id`=:id");
				$updateReviewPhotos->bindValue(':photos_json', $photosJson, \PDO::PARAM_STR);
				$updateReviewPhotos->bindValue(':id', $createdReviewId, \PDO::PARAM_INT);
				$updateReviewPhotos->execute();

				$photoUploadWarning = trim((string) ($storedPhotosResult['warning'] ?? ''));
			}

			if ($moderationStatus === 'blocked') {
				$banLabels = skfoReviewsBanwordLabels((array) ($moderation['ban_categories'] ?? []));
				$banSuffix = count($banLabels) ? (' (' . implode(', ', $banLabels) . ')') : '';
				$reviewError = 'Отзыв не опубликован: обнаружены запрещенные темы' . $banSuffix . '.';
				if ($photoUploadWarning !== '') {
					$reviewError .= ' ' . $photoUploadWarning;
				}
			} elseif ($moderationStatus === 'duplicate') {
				$reviewSuccess = 'Похожий отзыв уже есть. Ваш отзыв отправлен на модерацию.';
				if ($photoUploadWarning !== '') {
					$reviewSuccess .= ' ' . $photoUploadWarning;
				}
				$reviewTextValue = '';
				$reviewRatingValue = 5;
			} else {
				$reviewSuccess = 'Спасибо! Ваш отзыв отправлен.';
				if ($photoUploadWarning !== '') {
					$reviewSuccess .= ' ' . $photoUploadWarning;
				}
				$reviewTextValue = '';
				$reviewRatingValue = 5;
			}
		} catch (\Throwable $e) {
			$reviewError = 'Не удалось сохранить отзыв. Попробуйте позже.';
			$log->save('errors', "reviews page save error: " . $e->getMessage());
		}
	}

	if ($reviewError !== '') {
		$setFlash($session, $flashPrefix . 'error', $reviewError);
		$setFlash($session, $flashPrefix . 'text', $reviewTextValue);
		$setFlash($session, $flashPrefix . 'rating', $reviewRatingValue);
		$setFlash($session, $flashPrefix . 'subject', $reviewSubjectValue);
	} else {
		$setFlash($session, $flashPrefix . 'success', $reviewSuccess);
		$session->remove($flashPrefix . 'text');
		$session->remove($flashPrefix . 'rating');
		$session->remove($flashPrefix . 'subject');
	}

	$redirectUrl = (string) $page->url;
	if ($reviewSubjectValue !== '') {
		$redirectUrl .= '?review_subject=' . rawurlencode($reviewSubjectValue);
	}
	$session->redirect($redirectUrl . '#reviews-form');
}

$reviews = [];
try {
	foreach ($reviewTables as $targetType => $reviewTable) {
		$targetOptions = $targetType === 'hotel' ? $hotelOptionMap : $tourOptionMap;
		if (!count($targetOptions)) continue;

		$targetIds = array_keys($targetOptions);
		if (!count($targetIds)) continue;

		skfoReviewsEnsureTable($database, $reviewTable);
		skfoReviewsBackfillHashes($database, $reviewTable, 50);

		$placeholders = [];
		foreach ($targetIds as $index => $targetId) {
			$placeholders[] = ':target_id_' . $index;
		}

		$selectReviewsSql = "SELECT `id`, `page_id`, `author`, `review_text`, `rating`, `avatar_color`, `created_at`
			FROM `$reviewTable`
			WHERE `moderation_status`='approved'
			AND `page_id` IN (" . implode(', ', $placeholders) . ")
			ORDER BY `created_at` DESC, `id` DESC
			LIMIT 500";
		$selectReviews = $database->prepare($selectReviewsSql);
		foreach ($targetIds as $index => $targetId) {
			$selectReviews->bindValue(':target_id_' . $index, (int) $targetId, \PDO::PARAM_INT);
		}
		$selectReviews->execute();
		$rawReviews = $selectReviews->fetchAll(\PDO::FETCH_ASSOC) ?: [];

		foreach ($rawReviews as $rawReview) {
			$targetId = (int) ($rawReview['page_id'] ?? 0);
			if (!isset($targetOptions[$targetId])) continue;

			$rawReview['subject'] = [
				'type' => $targetType,
				'title' => (string) ($targetOptions[$targetId]['title'] ?? ''),
				'url' => (string) ($targetOptions[$targetId]['url'] ?? ''),
			];
			$reviews[] = $rawReview;
		}
	}
	usort($reviews, static function(array $a, array $b): int {
		$leftCreated = (string) ($a['created_at'] ?? '');
		$rightCreated = (string) ($b['created_at'] ?? '');
		if ($leftCreated === $rightCreated) {
			return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
		}
		return strcmp($rightCreated, $leftCreated);
	});
} catch (\Throwable $e) {
	$log->save('errors', "reviews page read error: " . $e->getMessage());
}

$csrfTokenName = $session->CSRF->getTokenName();
$csrfTokenValue = $session->CSRF->getTokenValue();
?>

<div id="content" class="reviews-page">
	<section class="hero reviews-hero">
		<div class="container hero-inner reviews-hero-inner">
			<h1 class="reviews-title">Ваши честные<br />Отзывы</h1>
			<div class="hero-tabs" aria-label="Разделы">
				<div class="hero-tabs-group" role="tablist">
					<span class="tab-indicator" aria-hidden="true"></span>
					<span class="tab-hover" aria-hidden="true"></span>
					<a class="hero-tab" href="/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/tour.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Поездки</span>
					</a>
					<a class="hero-tab" href="/hotels/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/hotel.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Жильё</span>
					</a>
					<a class="hero-tab is-active" href="/reviews/" role="tab" aria-selected="true">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/reviews.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Отзывы</span>
					</a>
					<a class="hero-tab" href="/guides/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/human.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Гиды</span>
					</a>
					<a class="hero-tab" href="/regions/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Регионы</span>
					</a>
					<a class="hero-tab" href="/places/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/location_on_nav.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Места</span>
					</a>
					<a class="hero-tab" href="/articles/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/journal.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Статьи</span>
					</a>
				</div>
				<a class="hero-tab hero-tab-forum" href="https://club.skfo.ru" target="_blank" rel="noopener noreferrer" aria-label="Форум">
					<img src="<?php echo $config->urls->templates; ?>assets/icons/forum.svg" alt="" aria-hidden="true" />
					<span>Форум</span>
					<img class="hero-tab-external" src="<?php echo $config->urls->templates; ?>assets/icons/external_site.svg" alt="" aria-hidden="true" />
				</a>
			</div>
		</div>
	</section>

	<section class="reviews-content" id="reviews-form">
		<div class="container reviews-layout">
			<div class="reviews-list-card">
				<div class="reviews-list">
					<?php foreach ($reviews as $review): ?>
						<?php
						$rating = max(1, min(5, (int) ($review['rating'] ?? 5)));
						$starsFilled = str_repeat('★', $rating);
						$starsEmpty = str_repeat('★', 5 - $rating);
						$author = (string) ($review['author'] ?? 'Гость');
						$avatarColorKey = (string) ($review['avatar_color'] ?? '');
						$reviewSubject = isset($review['subject']) && is_array($review['subject']) ? $review['subject'] : null;
						$reviewSubjectType = $reviewSubject ? (string) ($reviewSubject['type'] ?? '') : '';
						$reviewSubjectTitle = $reviewSubject ? (string) ($reviewSubject['title'] ?? '') : '';
						$reviewSubjectUrl = $reviewSubject ? (string) ($reviewSubject['url'] ?? '') : '';
						$reviewSubjectPrefix = (string) ($reviewTargetTypeLabels[$reviewSubjectType] ?? 'Объект');
						if (!isset($avatarClassMap[$avatarColorKey])) {
							$index = abs(crc32($author)) % count($avatarColorKeys);
							$avatarColorKey = $avatarColorKeys[$index];
						}
						$avatarClass = $avatarClassMap[$avatarColorKey];
						?>
						<article class="review-item">
							<div class="review-top">
								<span class="review-avatar <?php echo $avatarClass; ?>" aria-hidden="true"><?php echo $sanitizer->entities($firstLetter($author)); ?></span>
								<div class="review-meta">
									<div class="review-author-line">
										<strong class="review-author"><?php echo $sanitizer->entities($author); ?></strong>
										<span class="review-stars-inline" aria-label="Оценка <?php echo $rating; ?> из 5">
											<span class="is-filled"><?php echo $starsFilled; ?></span><?php if ($starsEmpty !== ''): ?><span class="is-empty"><?php echo $starsEmpty; ?></span><?php endif; ?>
										</span>
									</div>
									<?php if ($reviewSubjectTitle !== ''): ?>
										<?php $reviewSubjectLabel = $reviewSubjectPrefix . ': ' . $reviewSubjectTitle; ?>
										<?php if ($reviewSubjectUrl !== ''): ?>
											<a class="review-tour-title" href="<?php echo $sanitizer->entities($reviewSubjectUrl); ?>"><?php echo $sanitizer->entities($reviewSubjectLabel); ?></a>
										<?php else: ?>
											<p class="review-tour-title"><?php echo $sanitizer->entities($reviewSubjectLabel); ?></p>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							</div>
							<p class="review-text"><?php echo nl2br($sanitizer->entities((string) ($review['review_text'] ?? ''))); ?></p>
						</article>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="reviews-form-card">
				<h2 class="reviews-form-title">Оставить отзыв</h2>
				<?php if ($reviewSuccess !== ''): ?>
					<div class="review-message is-success"><?php echo $sanitizer->entities($reviewSuccess); ?></div>
				<?php endif; ?>
				<?php if ($reviewError !== ''): ?>
					<div class="review-message is-error"><?php echo $sanitizer->entities($reviewError); ?></div>
				<?php endif; ?>
				<?php if (!$isAuthLoggedIn): ?>
					<div class="review-message">Чтобы оставить отзыв, войдите в свой профиль.</div>
					<button class="reviews-submit reviews-auth-submit" type="button" data-auth-open data-auth-mode="login">Войти в профиль</button>
				<?php else: ?>
					<?php if (!$hasReviewTargets): ?>
						<div class="review-message is-error">Сейчас нет доступных туров и отелей для отзывов.</div>
					<?php else: ?>
						<form class="reviews-form" method="post" action="#reviews-form" enctype="multipart/form-data">
							<input type="hidden" name="review_form" value="reviews_page" />
							<input type="hidden" name="<?php echo $sanitizer->entities($csrfTokenName); ?>" value="<?php echo $sanitizer->entities($csrfTokenValue); ?>" />

							<label class="reviews-field">
								<span>Тур или отель</span>
								<select name="review_subject" required aria-label="Выберите тур или отель">
									<option value="" disabled<?php echo $reviewSubjectValue === '' ? ' selected' : ''; ?>>Выберите тур или отель</option>
									<?php if (count($tourOptions)): ?>
										<optgroup label="Туры">
											<?php foreach ($tourOptions as $tourOption): ?>
												<?php
												$tourId = (int) ($tourOption['id'] ?? 0);
												$tourTitle = (string) ($tourOption['title'] ?? '');
												if ($tourId < 1 || $tourTitle === '') continue;
												$subjectValue = $buildReviewSubject('tour', $tourId);
												$isSelected = $reviewSubjectValue === $subjectValue;
												?>
												<option value="<?php echo $sanitizer->entities($subjectValue); ?>"<?php echo $isSelected ? ' selected' : ''; ?>>
													<?php echo $sanitizer->entities($tourTitle); ?>
												</option>
											<?php endforeach; ?>
										</optgroup>
									<?php endif; ?>
									<?php if (count($hotelOptions)): ?>
										<optgroup label="Отели">
											<?php foreach ($hotelOptions as $hotelOption): ?>
												<?php
												$hotelId = (int) ($hotelOption['id'] ?? 0);
												$hotelTitle = (string) ($hotelOption['title'] ?? '');
												if ($hotelId < 1 || $hotelTitle === '') continue;
												$subjectValue = $buildReviewSubject('hotel', $hotelId);
												$isSelected = $reviewSubjectValue === $subjectValue;
												?>
												<option value="<?php echo $sanitizer->entities($subjectValue); ?>"<?php echo $isSelected ? ' selected' : ''; ?>>
													<?php echo $sanitizer->entities($hotelTitle); ?>
												</option>
											<?php endforeach; ?>
										</optgroup>
									<?php endif; ?>
								</select>
							</label>

							<label class="reviews-field">
								<textarea name="review_text" rows="7" maxlength="3000" required placeholder="Ваш честный отзыв..." aria-label="Ваш честный отзыв"><?php echo $sanitizer->entities($reviewTextValue); ?></textarea>
							</label>

							<div class="reviews-field">
								<div class="reviews-stars-input" role="radiogroup" aria-label="Оценка от 1 до 5">
									<?php for ($i = 5; $i >= 1; $i--): ?>
										<?php $isChecked = $reviewRatingValue === $i; ?>
										<input id="review-rating-<?php echo $i; ?>" type="radio" name="review_rating" value="<?php echo $i; ?>"<?php echo $isChecked ? ' checked' : ''; ?> required />
										<label for="review-rating-<?php echo $i; ?>" aria-label="<?php echo $i; ?>"></label>
									<?php endfor; ?>
								</div>
							</div>

							<label class="reviews-field reviews-field--file">
								<span>Фотографии</span>
								<input type="file" name="review_photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple />
								<small>До 12 фото, форматы JPG/PNG/WEBP/GIF, размер до 8 МБ.</small>
							</label>

							<button class="reviews-submit" type="submit">Отправить</button>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>
</div>
