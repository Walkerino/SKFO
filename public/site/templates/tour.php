<?php namespace ProcessWire;

$reviewTable = 'tour_reviews';
require_once __DIR__ . '/_reviews_moderation.php';

$toLower = static function(string $value): string {
	$value = trim($value);
	return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
};

$repairBrokenCyrillicX = static function(string $value): string {
	$repaired = preg_replace('/(?<=\p{Cyrillic})\?\R+(?=\p{Cyrillic})/u', 'х', $value);
	return $repaired !== null ? $repaired : $value;
};

$normalizeIncludedItemText = static function(string $value): string {
	$line = str_replace("\xc2\xa0", ' ', $value);
	$line = trim($line);
	$line = preg_replace('/^\s*(?:[\x{2022}\x{2023}\x{25E6}\x{2043}\x{2219}•◦·▪●\-–—*]+|\d+[.)])\s*/u', '', $line) ?? $line;
	$line = preg_replace('/\s+/u', ' ', $line) ?? $line;
	return trim($line);
};

$normalizeIncludedItems = static function(array $items) use ($toLower, $normalizeIncludedItemText): array {
	$normalized = [];
	$seen = [];
	foreach($items as $item) {
		$line = $normalizeIncludedItemText((string) $item);
		if($line === '') continue;
		$key = $toLower($line);
		if(isset($seen[$key])) continue;
		$seen[$key] = true;
		$normalized[] = $line;
	}
	return $normalized;
};

$measureTextLength = static function(string $value): int {
	$value = trim(strip_tags($value));
	$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
	if($value === '') return 0;
	return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
};
$firstLetter = static function(string $value): string {
	$value = trim($value);
	if ($value === '') return '?';
	return function_exists('mb_substr') ? mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($value, 0, 1));
};

$extractTourPriceAmount = static function(string $raw): int {
	$raw = trim($raw);
	if($raw === '') return 0;

	if(stripos($raw, 'ft-table-col-price') !== false) {
		if(preg_match('/<td[^>]*class\s*=\s*["\'][^"\']*ft-table-col-price[^"\']*["\'][^>]*>(.*?)<\/td>/is', $raw, $matches) === 1) {
			$priceCellText = trim(strip_tags(html_entity_decode((string) ($matches[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
			$priceCellDigits = preg_replace('/[^\d]+/', '', $priceCellText) ?? '';
			if($priceCellDigits !== '') return (int) $priceCellDigits;
		}
	}

	$visibleText = trim(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
	$digits = preg_replace('/[^\d]+/', '', $visibleText) ?? '';
	if($digits === '') return 0;
	return (int) $digits;
};

$formatTourPrice = static function(string $raw) use ($extractTourPriceAmount): string {
	$amount = $extractTourPriceAmount($raw);
	if($amount <= 0) return '';
	return number_format($amount, 0, '', ' ') . ' ₽';
};

$tourTitle = $page->hasField('tour_title') ? trim((string) $page->tour_title) : '';
if($tourTitle === '') $tourTitle = trim((string) $page->title);
$tourRegion = $page->hasField('tour_region') ? trim((string) $page->tour_region) : '';
$tourDescription = $page->hasField('tour_description') ? trim((string) $page->tour_description) : '';
$tourPriceRaw = $page->hasField('tour_price') ? (string) $page->getUnformatted('tour_price') : '';
$tourPrice = $formatTourPrice($tourPriceRaw);
$tourDuration = $page->hasField('tour_duration') ? trim((string) $page->tour_duration) : '';
$tourGroup = $page->hasField('tour_group') ? trim((string) $page->tour_group) : '';
$tourSeason = $page->hasField('tour_season') ? trim((string) $page->tour_season) : '';
$tourDifficulty = '';
$tourDifficultyLevel = $page->hasField('tour_difficulty_level') ? $page->getUnformatted('tour_difficulty_level') : null;
if ($tourDifficultyLevel instanceof SelectableOptionArray && $tourDifficultyLevel->count()) {
	$selectedDifficulty = $tourDifficultyLevel->first();
	if ($selectedDifficulty instanceof SelectableOption) {
		$tourDifficulty = trim((string) $selectedDifficulty->title);
	}
} elseif ($tourDifficultyLevel instanceof SelectableOption) {
	$tourDifficulty = trim((string) $tourDifficultyLevel->title);
}
if ($tourDifficulty === '' && $page->hasField('tour_difficulty')) {
	$tourDifficulty = trim((string) $page->tour_difficulty);
}
$tourAge = $page->hasField('tour_age') ? trim((string) $page->tour_age) : '';

$tourImageUrl = '';
if ($page->hasField('tour_cover_image')) {
	$cover = $page->getUnformatted('tour_cover_image');
	if ($cover instanceof Pageimage) {
		$tourImageUrl = $cover->url;
	} elseif ($cover instanceof Pageimages && $cover->count()) {
		$tourImageUrl = $cover->first()->url;
	}
}

if ($tourTitle === '') $tourTitle = 'Четырехдневный тур по Дагестану';
if ($tourRegion === '') $tourRegion = 'Республика Дагестан';
if ($tourDescription === '') {
	$tourDescription = "Четырехдневный тур по самым живописным местам Дагестана: от Сулакского каньона до Гунибского района.\n\nВас ждут горные пейзажи, водопады, древние села и уникальные природные объекты.";
}
if ($tourPrice === '') $tourPrice = '23 251 ₽';
if ($tourDuration === '') $tourDuration = '4 дня';
if ($tourGroup === '') $tourGroup = '4-12 человек';
if ($tourSeason === '') $tourSeason = 'Май-Октябрь';
if ($tourDifficulty === '') $tourDifficulty = 'Базовая';
if ($tourAge === '') $tourAge = '12+';

$tourTitleLength = $measureTextLength($tourTitle);
$tourDescriptionLength = $measureTextLength($tourDescription);
$heroTextLoad = ($tourTitleLength * 2) + $tourDescriptionLength;
$heroCompactClass = '';
if ($tourTitleLength > 95 || $tourDescriptionLength > 260 || $heroTextLoad > 380) {
	$heroCompactClass = ' is-compact-2';
} elseif ($tourTitleLength > 65 || $tourDescriptionLength > 170 || $heroTextLoad > 280) {
	$heroCompactClass = ' is-compact';
}

$tourDifficultyDotsFilled = 1;
$normalizedDifficulty = $toLower($tourDifficulty);
if ($normalizedDifficulty === 'средняя') {
	$tourDifficultyDotsFilled = 2;
} elseif ($normalizedDifficulty === 'высокая') {
	$tourDifficultyDotsFilled = 3;
}

$includedRaw = $page->hasField('tour_included') ? trim((string) $page->tour_included) : '';
$includedRaw = $repairBrokenCyrillicX($includedRaw);
$includedItemsFromText = array_values(array_filter(array_map('trim', preg_split('/\R+/u', $includedRaw))));
$includedItems = $normalizeIncludedItems($includedItemsFromText);

if (!count($includedItems) && $page->hasField('tour_included_items') && $page->tour_included_items->count()) {
	$includedItemsFromRepeater = [];
	foreach ($page->tour_included_items as $itemPage) {
		$itemText = $itemPage->hasField('tour_included_item_text') ? trim((string) $itemPage->tour_included_item_text) : '';
		if ($itemText !== '') {
			$includedItemsFromRepeater[] = $itemText;
		}
	}
	$includedItems = $normalizeIncludedItems($includedItemsFromRepeater);
}

$tourDays = [];
if ($page->hasField('tour_days') && $page->tour_days->count()) {
	foreach ($page->tour_days as $day) {
		$dayImages = [];
		if ($day->hasField('tour_day_images')) {
			$images = $day->getUnformatted('tour_day_images');
			if ($images instanceof Pageimages && $images->count()) {
				foreach ($images as $img) {
					$dayImages[] = $img->url;
				}
			} elseif ($images instanceof Pageimage) {
				$dayImages[] = $images->url;
			}
		}

		$tourDays[] = [
			'title' => $day->hasField('tour_day_title') ? trim((string) $day->tour_day_title) : '',
			'description' => $day->hasField('tour_day_description') ? trim((string) $day->tour_day_description) : '',
			'images' => $dayImages,
		];
	}
}

if (!count($tourDays)) {
	$tourDays = [
		[
			'title' => 'День 1. Сулакский каньон',
			'description' => 'Знакомство с Дагестаном и главными видовыми точками маршрута.',
			'images' => [],
		],
	];
}

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
$tourReviews = [];
try {
	skfoReviewsEnsureTable($database, $reviewTable);
	skfoReviewsBackfillHashes($database, $reviewTable, 50);

	$selectTourReviews = $database->prepare(
		"SELECT `author`, `review_text`, `rating`, `avatar_color`, `photos_json`
		FROM `$reviewTable`
		WHERE `page_id`=:page_id
		AND `moderation_status`='approved'
		ORDER BY `created_at` DESC, `id` DESC
		LIMIT 200"
	);
	$selectTourReviews->bindValue(':page_id', (int) $page->id, \PDO::PARAM_INT);
	$selectTourReviews->execute();
	$tourReviews = $selectTourReviews->fetchAll(\PDO::FETCH_ASSOC) ?: [];
} catch (\Throwable $e) {
	$log->save('errors', "tour page reviews read error: " . $e->getMessage());
}

?>

<div id="content" class="tour-page">
	<section class="tour-hero">
		<div class="container">
			<div class="tour-hero-shape">
				<div class="tour-hero-layout<?php echo $heroCompactClass; ?>">
					<div class="tour-hero-main">
						<h1 class="tour-title"><?php echo $sanitizer->entities($tourTitle); ?></h1>
						<p class="tour-description"><?php echo nl2br($sanitizer->entities($tourDescription)); ?></p>
					</div>
					<div class="tour-hero-media">
						<div class="tour-badge">
							<img src="<?php echo $config->urls->templates; ?>assets/icons/location_on.svg" alt="" aria-hidden="true" />
							<span><?php echo $sanitizer->entities($tourRegion); ?></span>
						</div>
						<div class="tour-cover"<?php echo $tourImageUrl ? " style=\"background-image: url('".htmlspecialchars($tourImageUrl, ENT_QUOTES, 'UTF-8')."');\"" : ''; ?>></div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="tour-overview">
		<div class="container tour-overview-layout">
			<div class="tour-included-card">
				<h2 class="tour-section-title">Что включено</h2>
				<?php if (count($includedItems)): ?>
					<ul class="tour-included-list">
						<?php foreach ($includedItems as $item): ?>
							<li><?php echo $sanitizer->entities($item); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<div class="tour-details-card">
				<h2 class="tour-section-title">Детали тура</h2>
				<dl class="tour-meta">
					<div><dt>Длительность</dt><dd><?php echo $sanitizer->entities($tourDuration); ?></dd></div>
					<div><dt>Группа</dt><dd><?php echo $sanitizer->entities($tourGroup); ?></dd></div>
					<div>
						<dt>Сложность</dt>
						<dd>
							<?php echo $sanitizer->entities($tourDifficulty); ?>
							<span class="tour-difficulty-dots">
								<i<?php echo $tourDifficultyDotsFilled >= 1 ? ' class="is-active"' : ''; ?>></i>
								<i<?php echo $tourDifficultyDotsFilled >= 2 ? ' class="is-active"' : ''; ?>></i>
								<i<?php echo $tourDifficultyDotsFilled >= 3 ? ' class="is-active"' : ''; ?>></i>
							</span>
						</dd>
					</div>
					<div><dt>Сезонность</dt><dd><?php echo $sanitizer->entities($tourSeason); ?></dd></div>
					<div><dt>Возраст</dt><dd><?php echo $sanitizer->entities($tourAge); ?></dd></div>
				</dl>
				<div class="tour-details-footer">
					<div class="tour-price-wrap">
						<div class="tour-price"><?php echo $sanitizer->entities($tourPrice); ?></div>
						<div class="tour-price-caption">за человека</div>
					</div>
					<button class="tour-book-btn" type="button">Забронировать</button>
				</div>
			</div>
		</div>
	</section>

	<section class="tour-days">
		<div class="container">
			<!-- <h2 class="tour-section-title">Информация по дням</h2> -->
			<div class="tour-days-list">
				<?php foreach ($tourDays as $dayIndex => $day): ?>
					<article class="tour-day-card">
						<div class="tour-day-top">
							<div class="tour-day-head">
								<div class="tour-day-label">День <?php echo (int) $dayIndex + 1; ?></div>
								<h3 class="tour-day-title"><?php echo $day['title'] !== '' ? $sanitizer->entities($day['title']) : 'Маршрут дня'; ?></h3>
							</div>
							<button class="tour-day-toggle" type="button" aria-label="Раскрыть день" aria-expanded="false">
								<span class="tour-day-toggle-icon">+</span>
							</button>
						</div>
						<div class="tour-day-body">
							<p class="tour-day-description"><?php echo nl2br($sanitizer->entities($day['description'])); ?></p>
							<?php if (count($day['images'])): ?>
								<div class="tour-day-gallery">
									<button class="tour-day-gallery-prev" type="button" aria-label="Предыдущие фото"></button>
									<div class="tour-day-images">
										<?php foreach ($day['images'] as $img): ?>
											<div class="tour-day-image" style="background-image: url('<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>');"></div>
										<?php endforeach; ?>
									</div>
									<button class="tour-day-gallery-next" type="button" aria-label="Следующие фото"></button>
								</div>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="tour-reviews" data-tour-reviews-gallery>
		<div class="container">
			<div class="tour-reviews-card">
				<h2 class="tour-section-title">Отзывы о туре</h2>
				<?php if (count($tourReviews)): ?>
					<div class="tour-reviews-list">
						<?php foreach ($tourReviews as $review): ?>
						<?php
						$rating = max(1, min(5, (int) ($review['rating'] ?? 5)));
						$stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
						$author = (string) ($review['author'] ?? 'Гость');
						$reviewPhotos = skfoReviewsDecodePhotos((string) ($review['photos_json'] ?? ''));
						$reviewPhotos = array_values(array_filter(array_map('trim', $reviewPhotos), static fn(string $url): bool => $url !== ''));
						$avatarColorKey = (string) ($review['avatar_color'] ?? '');
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
										<h3 class="review-author"><?php echo $sanitizer->entities($author); ?></h3>
										<span class="review-stars" aria-label="Оценка <?php echo $rating; ?> из 5"><?php echo $stars; ?></span>
									</div>
								</div>
								<p class="review-text"><?php echo nl2br($sanitizer->entities((string) ($review['review_text'] ?? ''))); ?></p>
								<?php if (count($reviewPhotos)): ?>
									<div class="review-photo-grid">
										<?php foreach (array_slice($reviewPhotos, 0, 8) as $photoIndex => $photoUrl): ?>
											<button
												class="review-photo-item review-photo-item-btn"
												type="button"
												data-tour-review-photo
												data-gallery-type="image"
												data-gallery-src="<?php echo htmlspecialchars((string) $photoUrl, ENT_QUOTES, 'UTF-8'); ?>"
												data-gallery-alt="<?php echo $sanitizer->entities('Фото из отзыва ' . $author . ' #' . ((int) $photoIndex + 1)); ?>"
												aria-label="<?php echo $sanitizer->entities('Открыть фото из отзыва ' . $author); ?>"
												style="background-image: url('<?php echo htmlspecialchars((string) $photoUrl, ENT_QUOTES, 'UTF-8'); ?>');"
											></button>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					</div>
				<?php else: ?>
					<p class="tour-reviews-empty">
						Пока нет отзывов об этом туре.
						<a href="/reviews/?review_subject=<?php echo rawurlencode('tour:' . (int) $page->id); ?>#reviews-form">Оставить первый отзыв</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<div class="hotel-gallery-lightbox tour-review-lightbox" data-tour-review-modal hidden>
			<div class="hotel-gallery-lightbox-backdrop" data-gallery-close="backdrop"></div>
			<div class="hotel-gallery-lightbox-dialog">
				<button class="hotel-gallery-close" type="button" data-gallery-close="button" aria-label="Закрыть галерею">×</button>
				<button class="hotel-gallery-nav hotel-gallery-nav--prev" type="button" data-gallery-nav="prev" aria-label="Предыдущее фото"></button>
				<button class="hotel-gallery-nav hotel-gallery-nav--next" type="button" data-gallery-nav="next" aria-label="Следующее фото"></button>
				<figure class="hotel-gallery-stage">
					<img data-gallery-image alt="" />
				</figure>
				<div class="hotel-gallery-counter" data-gallery-counter></div>
			</div>
		</div>
	</section>
</div>
