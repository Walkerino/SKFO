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

$getImageUrlFromValue = static function($imageValue): string {
	if ($imageValue instanceof Pageimage) return (string) $imageValue->url;
	if ($imageValue instanceof Pageimages && $imageValue->count()) return (string) $imageValue->first()->url;
	return '';
};

$formatDateRu = static function(int $timestamp): string {
	if ($timestamp <= 0) return '';
	$months = [
		1 => 'января',
		2 => 'февраля',
		3 => 'марта',
		4 => 'апреля',
		5 => 'мая',
		6 => 'июня',
		7 => 'июля',
		8 => 'августа',
		9 => 'сентября',
		10 => 'октября',
		11 => 'ноября',
		12 => 'декабря',
	];
	$day = (int) date('j', $timestamp);
	$month = (int) date('n', $timestamp);
	$year = (int) date('Y', $timestamp);
	return $day . ' ' . ($months[$month] ?? '') . ' ' . $year;
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
$tourType = $page->hasField('tour_type') ? trim((string) $page->tour_type) : '';
$tourFormat = $page->hasField('tour_format') ? trim((string) $page->tour_format) : '';
$tourLanguage = $page->hasField('tour_language') ? trim((string) $page->tour_language) : '';
$tourDates = $page->hasField('tour_dates') ? trim((string) $page->tour_dates) : '';
$tourMeetingPoint = $page->hasField('tour_meeting_point') ? trim((string) $page->tour_meeting_point) : '';
$tourMeals = $page->hasField('tour_meals') ? trim((string) $page->tour_meals) : '';
$tourWhatToTake = $page->hasField('tour_what_to_take') ? trim((string) $page->tour_what_to_take) : '';
$tourSeatsLeft = $page->hasField('tour_seats_left') ? max(0, (int) $page->tour_seats_left) : 0;
$tourIsHot = $page->hasField('tour_is_hot') ? ((int) $page->tour_is_hot === 1) : false;
$tourDiscountPercent = $page->hasField('tour_discount_percent') ? max(0, (int) $page->tour_discount_percent) : 0;
$tourDisclaimer = $page->hasField('tour_disclaimer') ? trim((string) $page->tour_disclaimer) : '';

$tourDiscountDeadlineTimestamp = 0;
if ($page->hasField('tour_discount_deadline')) {
	$discountRaw = $page->getUnformatted('tour_discount_deadline');
	if (is_numeric($discountRaw)) {
		$tourDiscountDeadlineTimestamp = (int) $discountRaw;
	} elseif (is_string($discountRaw) && trim($discountRaw) !== '') {
		$parsedDiscountTime = strtotime($discountRaw);
		if ($parsedDiscountTime !== false) $tourDiscountDeadlineTimestamp = (int) $parsedDiscountTime;
	}
}

$linkedGuide = null;
if ($page->hasField('guide')) {
	$guideValue = $page->getUnformatted('guide');
	if ($guideValue instanceof Page) {
		$linkedGuide = $guideValue;
	} elseif ($guideValue instanceof PageArray && $guideValue->count()) {
		$firstGuide = $guideValue->first();
		if ($firstGuide instanceof Page) $linkedGuide = $firstGuide;
	}
}

$tourGuideName = $page->hasField('tour_guide_name') ? trim((string) $page->tour_guide_name) : '';
if ($tourGuideName === '' && $linkedGuide instanceof Page) {
	$tourGuideName = trim((string) $linkedGuide->title);
}
if ($tourGuideName === '') $tourGuideName = 'Команда SKFO';

$tourGuidePhotoUrl = '';
if ($page->hasField('tour_guide_photo')) {
	$tourGuidePhotoUrl = $getImageUrlFromValue($page->getUnformatted('tour_guide_photo'));
}
if ($tourGuidePhotoUrl === '' && $linkedGuide instanceof Page) {
	foreach (['logo', 'images'] as $guideImageField) {
		if (!$linkedGuide->hasField($guideImageField)) continue;
		$tourGuidePhotoUrl = $getImageUrlFromValue($linkedGuide->getUnformatted($guideImageField));
		if ($tourGuidePhotoUrl !== '') break;
	}
}

$tourGuideExperienceYears = $page->hasField('tour_guide_experience_years') ? max(0, (int) $page->tour_guide_experience_years) : 0;
$tourGuideTouristsCount = $page->hasField('tour_guide_tourists_count') ? max(0, (int) $page->tour_guide_tourists_count) : 0;
$tourGuideAttestationNumber = $page->hasField('tour_guide_attestation_number') ? trim((string) $page->tour_guide_attestation_number) : '';
$tourGuideRegistryUrl = $page->hasField('tour_guide_registry_url') ? trim((string) $page->tour_guide_registry_url) : '';

if ($linkedGuide instanceof Page) {
	if ($tourGuideExperienceYears <= 0 && $linkedGuide->hasField('guide_experience_years')) {
		$tourGuideExperienceYears = max(0, (int) $linkedGuide->getUnformatted('guide_experience_years'));
	}
	if ($tourGuideTouristsCount <= 0 && $linkedGuide->hasField('guide_tourists_count')) {
		$tourGuideTouristsCount = max(0, (int) $linkedGuide->getUnformatted('guide_tourists_count'));
	}
	if ($tourGuideAttestationNumber === '' && $linkedGuide->hasField('guide_attestation_number')) {
		$tourGuideAttestationNumber = trim((string) $linkedGuide->getUnformatted('guide_attestation_number'));
	}
	if ($tourGuideRegistryUrl === '' && $linkedGuide->hasField('guide_registry_url')) {
		$tourGuideRegistryUrl = trim((string) $linkedGuide->getUnformatted('guide_registry_url'));
	}
}
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

$tourMedia = [];
$tourMediaKeys = [];
$addTourMediaImage = static function(string $url) use (&$tourMedia, &$tourMediaKeys): void {
	$url = trim($url);
	if ($url === '') return;
	if (isset($tourMediaKeys[$url])) return;
	$tourMediaKeys[$url] = true;
	$tourMedia[] = $url;
};

if ($page->hasField('tour_cover_image')) {
	$cover = $page->getUnformatted('tour_cover_image');
	if ($cover instanceof Pageimage) {
		$addTourMediaImage($cover->url);
	} elseif ($cover instanceof Pageimages && $cover->count()) {
		foreach ($cover as $image) {
			if (!$image instanceof Pageimage) continue;
			$addTourMediaImage($image->url);
		}
	}
}

if (!count($tourMedia) && $page->hasField('images')) {
	$images = $page->getUnformatted('images');
	if ($images instanceof Pageimage) {
		$addTourMediaImage($images->url);
	} elseif ($images instanceof Pageimages && $images->count()) {
		foreach ($images as $image) {
			if (!$image instanceof Pageimage) continue;
			$addTourMediaImage($image->url);
		}
	}
}

if (count($tourMedia) > 12) $tourMedia = array_slice($tourMedia, 0, 12);

$tourImageUrl = $tourMedia[0] ?? '';
$tourHeroMainMedia = $tourImageUrl;
$tourHeroThumbMedia = count($tourMedia) > 1 ? array_slice($tourMedia, 1, 4, true) : [];
$tourHeroVisibleCount = $tourHeroMainMedia !== '' ? 1 + count($tourHeroThumbMedia) : 0;
$tourHeroHiddenCount = max(0, count($tourMedia) - $tourHeroVisibleCount);
$tourHeroHiddenMedia = $tourHeroHiddenCount > 0
	? array_slice($tourMedia, $tourHeroVisibleCount, null, true)
	: [];

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
if ($tourType === '') $tourType = 'Джип-тур';
if ($tourFormat === '') $tourFormat = 'Групповой';
if ($tourLanguage === '') $tourLanguage = 'Русский';
if ($tourDates === '') $tourDates = 'Ближайшие даты по запросу';
if ($tourMeetingPoint === '') $tourMeetingPoint = 'Махачкала, 08:00';
if ($tourMeals === '') $tourMeals = 'Уточняйте у организатора';
if ($tourWhatToTake === '') $tourWhatToTake = 'Удобную обувь и воду';
if ($tourDisclaimer === '') {
	$tourDisclaimer = 'Проверьте аттестацию гида в федеральном реестре Минэкономразвития. SKFO.RU — витрина: ответственность за оказание услуги несёт исполнитель.';
}
if ($tourGuideRegistryUrl === '') {
	$tourGuideRegistryUrl = 'https://economy.gov.ru/';
}
$tourGuideRegistryHref = $tourGuideRegistryUrl;
if ($tourGuideRegistryHref !== '' && preg_match('#^https?://#i', $tourGuideRegistryHref) !== 1) {
	$tourGuideRegistryHref = 'https://' . ltrim($tourGuideRegistryHref, '/');
}
$tourGuideProfileUrl = ($linkedGuide instanceof Page && $linkedGuide->id) ? (string) $linkedGuide->url : '';

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

$tourReviewCount = count($tourReviews);
$tourReviewAverage = 0.0;
if ($tourReviewCount > 0) {
	$ratingSum = 0;
	foreach ($tourReviews as $reviewRow) {
		$ratingSum += max(1, min(5, (int) ($reviewRow['rating'] ?? 5)));
	}
	$tourReviewAverage = $ratingSum / $tourReviewCount;
}
$tourReviewAverageLabel = $tourReviewAverage > 0 ? str_replace('.', ',', number_format($tourReviewAverage, 1, '.', '')) : '0,0';
$tourReviewSummaryLabel = $tourReviewCount > 0
	? ($tourReviewAverageLabel . ' (' . $tourReviewCount . ')')
	: 'Пока без оценок';
$tourReviewsPreview = array_slice($tourReviews, 0, 3);

$tourDiscountDeadlineLabel = $tourDiscountDeadlineTimestamp > 0 ? $formatDateRu($tourDiscountDeadlineTimestamp) : '';
$tourDiscountLabel = '';
if ($tourDiscountPercent > 0 && $tourDiscountDeadlineLabel !== '') {
	$tourDiscountLabel = 'Скидка ' . $tourDiscountPercent . '% до ' . $tourDiscountDeadlineLabel;
} elseif ($tourDiscountPercent > 0) {
	$tourDiscountLabel = 'Скидка ' . $tourDiscountPercent . '% при раннем бронировании';
}

$tourCtaLabel = 'Забронировать';
if ($tourSeatsLeft > 0 && $tourSeatsLeft <= 6) {
	$tourCtaLabel = 'Осталось ' . $tourSeatsLeft . ' мест';
}

$tourHeaderTags = [];
$tourHeaderTags[] = ['label' => $tourRegion, 'tone' => 'region'];
$tourHeaderTags[] = ['label' => $tourType, 'tone' => 'base'];
$tourHeaderTags[] = ['label' => $tourFormat, 'tone' => 'base'];
$tourHeaderTags[] = ['label' => $tourDuration, 'tone' => 'base'];
$tourHeaderTags[] = ['label' => $tourLanguage, 'tone' => 'base'];
$tourHeaderTags[] = ['label' => $tourDifficulty, 'tone' => 'base'];
if ($tourIsHot) $tourHeaderTags[] = ['label' => 'Горячее', 'tone' => 'hot'];
if ($tourSeatsLeft > 0 && $tourSeatsLeft <= 6) $tourHeaderTags[] = ['label' => 'Осталось ' . $tourSeatsLeft . ' мест', 'tone' => 'warn'];
if ($tourDiscountPercent > 0) $tourHeaderTags[] = ['label' => 'Скидка ' . $tourDiscountPercent . '%', 'tone' => 'sale'];

$tourDetailRows = [
	['icon' => '', 'label' => 'Даты выезда', 'value' => $tourDates],
	['icon' => '', 'label' => 'Группа', 'value' => $tourGroup],
	['icon' => '', 'label' => 'Встреча', 'value' => $tourMeetingPoint],
	['icon' => '', 'label' => 'Питание', 'value' => $tourMeals],
	['icon' => '', 'label' => 'Что взять', 'value' => $tourWhatToTake],
];

?>

<div id="content" class="tour-page">
	<section class="tour-hero">
		<div class="container">
			<div class="tour-hero-shape">
				<div class="tour-hero-layout<?php echo $heroCompactClass; ?>">
						<div class="tour-hero-main">
							<h1 class="tour-title"><?php echo $sanitizer->entities($tourTitle); ?></h1>
							<p class="tour-description"><?php echo nl2br($sanitizer->entities($tourDescription)); ?></p>
							<div class="tour-header-tags" aria-label="Ключевые характеристики тура">
								<?php foreach ($tourHeaderTags as $tag): ?>
									<?php
									$tagLabel = trim((string) ($tag['label'] ?? ''));
									if ($tagLabel === '') continue;
									$tagTone = trim((string) ($tag['tone'] ?? 'base'));
									$tagToneClass = preg_replace('/[^a-z0-9_-]+/i', '', $tagTone) ?: 'base';
									?>
									<span class="tour-header-tag tour-header-tag--<?php echo $tagToneClass; ?>">
										<?php echo $sanitizer->entities($tagLabel); ?>
									</span>
								<?php endforeach; ?>
							</div>
							<div class="tour-hero-rating" aria-label="Рейтинг тура">
								<span class="tour-hero-rating-star" aria-hidden="true">★</span>
								<span><?php echo $sanitizer->entities($tourReviewSummaryLabel); ?></span>
							</div>
						</div>
						<div class="tour-hero-media">
							<div class="tour-badge">
								<img src="<?php echo $config->urls->templates; ?>assets/icons/location_on.svg" alt="" aria-hidden="true" />
								<span><?php echo $sanitizer->entities($tourRegion); ?></span>
							</div>
							<?php if ($tourHeroMainMedia !== ''): ?>
								<div
									class="tour-hero-gallery"
									data-tour-hero-gallery
									data-thumb-count="<?php echo (int) max(0, min(4, count($tourHeroThumbMedia))); ?>"
								>
									<figure class="tour-hero-gallery-main">
										<button
											class="tour-hero-gallery-trigger"
											type="button"
											data-tour-hero-gallery-item
											data-gallery-index="0"
											data-gallery-src="<?php echo htmlspecialchars($tourHeroMainMedia, ENT_QUOTES, 'UTF-8'); ?>"
											data-gallery-alt="<?php echo $sanitizer->entities('Фото тура 1'); ?>"
											aria-label="<?php echo $sanitizer->entities('Открыть фото 1'); ?>"
										>
											<img
												src="<?php echo htmlspecialchars($tourHeroMainMedia, ENT_QUOTES, 'UTF-8'); ?>"
												alt="<?php echo $sanitizer->entities('Фото тура 1'); ?>"
												loading="eager"
												fetchpriority="high"
											/>
										</button>
									</figure>
									<?php if (count($tourHeroThumbMedia)): ?>
										<div class="tour-hero-gallery-strip">
											<?php $visibleThumbCount = count($tourHeroThumbMedia); ?>
											<?php $visibleThumbOrder = 0; ?>
											<?php foreach ($tourHeroThumbMedia as $thumbIndex => $tourMediaImage): ?>
												<?php
												$visibleThumbOrder++;
												$isMoreTile = $tourHeroHiddenCount > 0 && $visibleThumbOrder === $visibleThumbCount;
												$thumbPhotoNumber = (int) $thumbIndex + 1;
												$thumbLabel = $isMoreTile
													? ('Открыть галерею, ещё ' . (int) $tourHeroHiddenCount . ' фото')
													: ('Открыть фото ' . $thumbPhotoNumber);
												?>
												<figure class="tour-hero-gallery-thumb<?php echo $isMoreTile ? ' is-more' : ''; ?>">
													<button
														class="tour-hero-gallery-trigger"
														type="button"
														data-tour-hero-gallery-item
														data-gallery-index="<?php echo (int) $thumbIndex; ?>"
														data-gallery-src="<?php echo htmlspecialchars($tourMediaImage, ENT_QUOTES, 'UTF-8'); ?>"
														data-gallery-alt="<?php echo $sanitizer->entities('Фото тура ' . $thumbPhotoNumber); ?>"
														aria-label="<?php echo $sanitizer->entities($thumbLabel); ?>"
													>
														<img
															src="<?php echo htmlspecialchars($tourMediaImage, ENT_QUOTES, 'UTF-8'); ?>"
															alt="<?php echo $sanitizer->entities('Фото тура ' . $thumbPhotoNumber); ?>"
															loading="lazy"
														/>
														<?php if ($isMoreTile): ?>
															<span class="tour-hero-gallery-more">+<?php echo (int) $tourHeroHiddenCount; ?><small> фото</small></span>
														<?php endif; ?>
													</button>
												</figure>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
									<?php if (count($tourHeroHiddenMedia)): ?>
										<div class="tour-hero-gallery-hidden" hidden aria-hidden="true">
											<?php foreach ($tourHeroHiddenMedia as $hiddenIndex => $tourMediaImage): ?>
												<button
													type="button"
													data-tour-hero-gallery-item
													data-gallery-index="<?php echo (int) $hiddenIndex; ?>"
													data-gallery-src="<?php echo htmlspecialchars($tourMediaImage, ENT_QUOTES, 'UTF-8'); ?>"
													data-gallery-alt="<?php echo $sanitizer->entities('Фото тура ' . ((int) $hiddenIndex + 1)); ?>"
													tabindex="-1"
												></button>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>
							<?php else: ?>
								<div class="tour-cover"></div>
							<?php endif; ?>
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
						<?php foreach ($tourDetailRows as $detailRow): ?>
							<?php
							$detailValue = trim((string) ($detailRow['value'] ?? ''));
							if ($detailValue === '') continue;
							?>
							<div>
								<dt>
									<?php if (trim((string) ($detailRow['icon'] ?? '')) !== ''): ?>
										<span class="tour-meta-icon" aria-hidden="true"><?php echo $sanitizer->entities((string) ($detailRow['icon'] ?? '')); ?></span>
									<?php endif; ?>
									<?php echo $sanitizer->entities((string) ($detailRow['label'] ?? '')); ?>
								</dt>
								<dd><?php echo $sanitizer->entities($detailValue); ?></dd>
							</div>
						<?php endforeach; ?>
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
							<div class="tour-price">от <?php echo $sanitizer->entities($tourPrice); ?></div>
							<div class="tour-price-caption">за человека</div>
							<?php if ($tourDiscountLabel !== ''): ?>
								<div class="tour-price-discount"><?php echo $sanitizer->entities($tourDiscountLabel); ?></div>
							<?php endif; ?>
						</div>
						<button class="tour-book-btn<?php echo $tourSeatsLeft > 0 && $tourSeatsLeft <= 6 ? ' is-warning' : ''; ?>" type="button" data-contacts-open>
							<?php echo $sanitizer->entities($tourCtaLabel); ?>
						</button>
					</div>
					<p class="tour-booking-note">Бронирование напрямую у гида</p>
				</div>
			</div>
		</section>

		<section class="tour-guide-trust">
			<div class="container tour-guide-trust-layout">
				<article class="tour-guide-card">
					<h2 class="tour-section-title">Гид / организатор</h2>
					<div class="tour-guide-head">
						<?php if ($tourGuidePhotoUrl !== ''): ?>
							<img class="tour-guide-avatar" src="<?php echo htmlspecialchars($tourGuidePhotoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $sanitizer->entities('Фото гида ' . $tourGuideName); ?>" />
						<?php else: ?>
							<span class="tour-guide-avatar tour-guide-avatar--placeholder review-avatar is-blue" aria-hidden="true"><?php echo $sanitizer->entities($firstLetter($tourGuideName)); ?></span>
						<?php endif; ?>
						<div class="tour-guide-summary">
							<h3 class="tour-guide-name">
								<?php if ($tourGuideProfileUrl !== ''): ?>
									<a href="<?php echo $sanitizer->entities($tourGuideProfileUrl); ?>"><?php echo $sanitizer->entities($tourGuideName); ?></a>
								<?php else: ?>
									<?php echo $sanitizer->entities($tourGuideName); ?>
								<?php endif; ?>
							</h3>
							<div class="tour-guide-stats">
								<?php if ($tourGuideExperienceYears > 0): ?>
									<span><?php echo $sanitizer->entities($tourGuideExperienceYears . ' лет опыта'); ?></span>
								<?php endif; ?>
								<?php if ($tourGuideTouristsCount > 0): ?>
									<span><?php echo $sanitizer->entities(number_format($tourGuideTouristsCount, 0, '', ' ') . '+ туристов'); ?></span>
								<?php endif; ?>
								<?php if ($tourGuideExperienceYears <= 0 && $tourGuideTouristsCount <= 0): ?>
									<span>Опыт подтверждается отзывами путешественников</span>
								<?php endif; ?>
							</div>
						</div>
					</div>
					<div class="tour-guide-meta">
						<div class="tour-guide-meta-row">
							<span class="tour-guide-meta-label">Аттестация:</span>
							<span class="tour-guide-meta-value"><?php echo $sanitizer->entities($tourGuideAttestationNumber !== '' ? ('№ ' . $tourGuideAttestationNumber) : 'номер уточняется'); ?></span>
						</div>
						<div class="tour-guide-meta-row">
							<span class="tour-guide-meta-label">Рейтинг:</span>
							<span class="tour-guide-meta-value">★ <?php echo $sanitizer->entities($tourReviewSummaryLabel); ?></span>
						</div>
						<?php if ($tourGuideRegistryHref !== ''): ?>
							<a class="tour-guide-registry-link" href="<?php echo $sanitizer->entities($tourGuideRegistryHref); ?>" target="_blank" rel="noopener noreferrer">
								Проверить в реестре
							</a>
						<?php endif; ?>
					</div>
				</article>

				<article class="tour-trust-card">
					<h2 class="tour-section-title">Проверка и прозрачность</h2>
					<p class="tour-trust-disclaimer"><?php echo $sanitizer->entities($tourDisclaimer); ?></p>
					<?php if (count($tourReviewsPreview)): ?>
						<div class="tour-trust-reviews">
							<h3 class="tour-trust-reviews-title">Свежие отзывы</h3>
							<ul class="tour-trust-reviews-list">
								<?php foreach ($tourReviewsPreview as $previewReview): ?>
									<?php
									$previewAuthor = trim((string) ($previewReview['author'] ?? 'Гость'));
									if ($previewAuthor === '') $previewAuthor = 'Гость';
									$previewRating = max(1, min(5, (int) ($previewReview['rating'] ?? 5)));
									$previewText = trim((string) ($previewReview['review_text'] ?? ''));
									if ($previewText === '') continue;
									$previewTextShort = function_exists('mb_substr')
										? mb_substr($previewText, 0, 120, 'UTF-8')
										: substr($previewText, 0, 120);
									if ((function_exists('mb_strlen') ? mb_strlen($previewText, 'UTF-8') : strlen($previewText)) > 120) $previewTextShort .= '...';
									?>
									<li>
										<span class="tour-trust-review-head"><?php echo $sanitizer->entities($previewAuthor); ?> · ★ <?php echo $previewRating; ?></span>
										<span class="tour-trust-review-text"><?php echo $sanitizer->entities($previewTextShort); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				</article>
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
		<?php if ($tourHeroMainMedia !== ''): ?>
			<div class="hotel-gallery-lightbox" data-tour-hero-gallery-modal hidden>
				<div class="hotel-gallery-lightbox-backdrop" data-gallery-close="backdrop"></div>
				<div class="hotel-gallery-lightbox-dialog" role="dialog" aria-modal="true" aria-label="Фотографии тура">
					<button class="hotel-gallery-close" type="button" data-gallery-close="button" aria-label="Закрыть">×</button>
					<button class="hotel-gallery-nav hotel-gallery-nav--prev" type="button" data-gallery-nav="prev" aria-label="Предыдущее фото"></button>
					<figure class="hotel-gallery-stage">
						<img src="" alt="" data-gallery-image />
					</figure>
					<button class="hotel-gallery-nav hotel-gallery-nav--next" type="button" data-gallery-nav="next" aria-label="Следующее фото"></button>
					<div class="hotel-gallery-counter" data-gallery-counter></div>
				</div>
			</div>
		<?php endif; ?>
	</div>
