<?php namespace ProcessWire;

// Template file for “home” template used by the homepage
// ------------------------------------------------------
// The #content div in this file will replace the #content div in _main.php
// when the Markup Regions feature is enabled, as it is by default. 
// You can also append to (or prepend to) the #content div, and much more. 
// See the Markup Regions documentation:
// https://processwire.com/docs/front-end/output/markup-regions/

$normalizeRegionOption = static function(string $value): string {
	$value = trim(str_replace(["\r", "\n"], ' ', $value));
	$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
	return $value;
};

$regionOptions = [];
$regionOptionKeys = [];

$addRegionOption = static function(string $value) use (&$regionOptions, &$regionOptionKeys, $normalizeRegionOption): void {
	$normalizedValue = $normalizeRegionOption($value);
	if ($normalizedValue === '') return;

	$key = function_exists('mb_strtolower') ? mb_strtolower($normalizedValue, 'UTF-8') : strtolower($normalizedValue);
	if (isset($regionOptionKeys[$key])) return;

	$regionOptionKeys[$key] = true;
	$regionOptions[] = $normalizedValue;
};

$regionsPage = $pages->get('/regions/');
if ($regionsPage && $regionsPage->id && $regionsPage->hasField('region_cards') && $regionsPage->region_cards->count()) {
	foreach ($regionsPage->region_cards as $card) {
		$title = $card->hasField('region_card_title') ? (string) $card->region_card_title : '';
		$addRegionOption($title);
	}
}

if (!count($regionOptions)) {
	foreach (
		[
			'Кабардино-Балкарская Республика',
			'Карачаево-Черкесская Республика',
			'Республика Дагестан',
			'Республика Ингушетия',
			'Республика Северная Осетия',
			'Ставропольский край',
			'Чеченская Республика',
		] as $regionTitle
	) {
		$addRegionOption($regionTitle);
	}
}

$toLower = static function(string $value): string {
	$value = trim($value);
	return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
};

$normalizeDisplayText = static function(string $value): string {
	$decoded = $value;
	for ($i = 0; $i < 3; $i++) {
		$next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		if ($next === $decoded) break;
		$decoded = $next;
	}
	$value = trim(str_replace(["\r", "\n"], ' ', $decoded));
	$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
	return $value;
};

$formatDaysLabel = static function(int $days): string {
	$days = max(0, $days);
	$mod10 = $days % 10;
	$mod100 = $days % 100;
	if ($mod10 === 1 && $mod100 !== 11) return $days . ' день';
	if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) return $days . ' дня';
	return $days . ' дней';
};

$normalizeTourDuration = static function(string $value) use ($formatDaysLabel): string {
	$value = trim($value);
	if ($value === '') return '';
	if (preg_match('/\d+/u', $value, $matches) !== 1) return $value;

	$days = (int) ($matches[0] ?? 0);
	if ($days <= 0) return $value;

	return $formatDaysLabel($days);
};

$extractTourPriceAmount = static function(string $raw): int {
	$raw = trim($raw);
	if ($raw === '') return 0;

	if (stripos($raw, 'ft-table-col-price') !== false) {
		if (preg_match('/<td[^>]*class\s*=\s*["\'][^"\']*ft-table-col-price[^"\']*["\'][^>]*>(.*?)<\/td>/is', $raw, $matches) === 1) {
			$priceCellText = trim(strip_tags(html_entity_decode((string) ($matches[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
			$priceCellDigits = preg_replace('/[^\d]+/', '', $priceCellText) ?? '';
			if ($priceCellDigits !== '') return (int) $priceCellDigits;
		}
	}

	$visibleText = trim(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
	$digits = preg_replace('/[^\d]+/', '', $visibleText) ?? '';
	if ($digits === '') return 0;
	return (int) $digits;
};

$normalizeTourPrice = static function(string $raw) use ($extractTourPriceAmount): string {
	$amount = $extractTourPriceAmount($raw);
	if ($amount > 0) return number_format($amount, 0, '', ' ') . ' ₽';
	return trim(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
};

$getImageUrlFromValue = static function($imageValue): string {
	if ($imageValue instanceof Pageimage) return $imageValue->url;
	if ($imageValue instanceof Pageimages && $imageValue->count()) return $imageValue->first()->url;
	return '';
};

$getImageUrlsFromValue = static function($imageValue): array {
	$urls = [];
	if ($imageValue instanceof Pageimage) {
		$urls[] = (string) $imageValue->url;
	}
	if ($imageValue instanceof Pageimages && $imageValue->count()) {
		foreach ($imageValue as $image) {
			if (!$image instanceof Pageimage) continue;
			$urls[] = (string) $image->url;
		}
	}
	return $urls;
};

$getFallbackImageUrlsFromPageFiles = static function(Page $contentPage) use ($config): array {
	$pageId = (int) $contentPage->id;
	if ($pageId <= 0) return [];

	$dir = rtrim((string) $config->paths->files, '/');
	if ($dir === '') return [];
	$dir .= '/' . $pageId;
	if (!is_dir($dir)) return [];

	$entries = scandir($dir);
	if (!is_array($entries)) return [];

	$candidates = [];
	foreach ($entries as $entry) {
		$entry = trim((string) $entry);
		if ($entry === '' || $entry === '.' || $entry === '..') continue;
		if (preg_match('/\.\d+x\d+\./', $entry) === 1) continue;
		if (preg_match('/\.(jpe?g|png|webp|gif|avif)$/i', $entry) !== 1) continue;
		$path = $dir . '/' . $entry;
		if (!is_file($path)) continue;
		$candidates[] = $entry;
	}
	if (!count($candidates)) return [];

	natcasesort($candidates);
	$base = rtrim((string) $config->urls->files, '/') . '/' . $pageId . '/';
	$urls = [];
	foreach ($candidates as $filename) {
		$urls[] = $base . rawurlencode((string) $filename);
	}
	return $urls;
};

$getImageUrlsFromPage = static function(Page $contentPage, array $fieldNames) use ($getImageUrlsFromValue, $getFallbackImageUrlsFromPageFiles): array {
	$urls = [];
	$seen = [];
	foreach ($fieldNames as $fieldName) {
		$fieldName = trim((string) $fieldName);
		if ($fieldName === '' || !$contentPage->hasField($fieldName)) continue;
		$items = $getImageUrlsFromValue($contentPage->getUnformatted($fieldName));
		foreach ($items as $itemUrl) {
			$itemUrl = trim((string) $itemUrl);
			if ($itemUrl === '' || isset($seen[$itemUrl])) continue;
			$seen[$itemUrl] = true;
			$urls[] = $itemUrl;
		}
	}

	if (count($urls)) return $urls;

	foreach ($getFallbackImageUrlsFromPageFiles($contentPage) as $itemUrl) {
		$itemUrl = trim((string) $itemUrl);
		if ($itemUrl === '' || isset($seen[$itemUrl])) continue;
		$seen[$itemUrl] = true;
		$urls[] = $itemUrl;
	}
	return $urls;
};

$getFirstTextFromPage = static function(Page $item, array $fieldNames) use ($normalizeDisplayText): string {
	foreach ($fieldNames as $fieldName) {
		if (!$item->hasField($fieldName)) continue;
		$value = $normalizeDisplayText((string) $item->$fieldName);
		if ($value !== '') return $value;
	}
	return '';
};

$getFirstImageUrlFromPage = static function(Page $item, array $fieldNames) use ($getImageUrlsFromPage): string {
	$urls = $getImageUrlsFromPage($item, $fieldNames);
	return count($urls) ? (string) $urls[0] : '';
};

$truncateText = static function(string $value, int $length = 180): string {
	$value = trim(strip_tags($value));
	if ($value === '') return '';
	$currentLength = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	if ($currentLength <= $length) return $value;
	$cut = function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
	return rtrim($cut) . '...';
};

$formatArticleDateFromPage = static function(Page $articlePage): string {
	$timestamp = 0;
	if ($articlePage->hasField('article_publish_date')) {
		$timestamp = (int) $articlePage->getUnformatted('article_publish_date');
	}
	if ($timestamp <= 0) {
		$timestamp = (int) $articlePage->getUnformatted('created');
	}
	return $timestamp > 0 ? date('d.m.Y', $timestamp) : '';
};

$titleFromName = static function(string $name): string {
	$name = trim($name);
	if ($name === '') return '';
	$title = str_replace(['-', '_'], ' ', $name);
	$title = preg_replace('/\s+/u', ' ', $title) ?? $title;
	$title = trim($title);
	if ($title === '') return '';
	return function_exists('mb_convert_case') ? mb_convert_case($title, MB_CASE_TITLE, 'UTF-8') : ucwords($title);
};

$loadLegacyTitlesByIds = static function(array $pageIds) use ($database): array {
	$pageIds = array_values(array_unique(array_map('intval', $pageIds)));
	$pageIds = array_values(array_filter($pageIds, static fn(int $id): bool => $id > 0));
	if (!count($pageIds)) return [];

	$placeholders = implode(',', array_fill(0, count($pageIds), '?'));
	$sql = "SELECT pages_id, data FROM field_title WHERE pages_id IN ($placeholders)";
	$stmt = $database->prepare($sql);
	foreach ($pageIds as $index => $id) {
		$stmt->bindValue($index + 1, $id, \PDO::PARAM_INT);
	}
	$stmt->execute();

	$map = [];
	while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
		$pageId = (int) ($row['pages_id'] ?? 0);
		$title = trim((string) ($row['data'] ?? ''));
		if ($pageId > 0 && $title !== '') $map[$pageId] = $title;
	}
	return $map;
};

$defaultCardImage = $config->urls->templates . 'assets/image1.png';

$tourTypeOptions = [
	'group' => 'Групповой',
	'individual' => 'Индивидуальный',
];

$seasonalityOptions = [
	'year-round' => 'Круглый год',
	'spring' => 'Весна',
	'summer' => 'Лето',
	'autumn' => 'Осень',
	'winter' => 'Зима',
];

$difficultyOptions = ['Базовая', 'Средняя', 'Высокая'];
$difficultyOptionKeys = [];
foreach ($difficultyOptions as $difficultyLabel) {
	$difficultyOptionKeys[$toLower($difficultyLabel)] = true;
}

$addDifficultyOption = static function(string $value) use (&$difficultyOptions, &$difficultyOptionKeys, $normalizeDisplayText, $toLower): void {
	$value = $normalizeDisplayText($value);
	if ($value === '') return;

	$key = $toLower($value);
	if (isset($difficultyOptionKeys[$key])) return;

	$difficultyOptionKeys[$key] = true;
	$difficultyOptions[] = $value;
};

$normalizeTourTypeKey = static function(string $value) use ($normalizeDisplayText, $toLower): string {
	$value = $normalizeDisplayText($value);
	if ($value === '') return '';

	$needle = str_replace('ё', 'е', $toLower($value));
	if (
		strpos($needle, 'индив') !== false ||
		strpos($needle, 'персон') !== false ||
		strpos($needle, 'private') !== false
	) {
		return 'individual';
	}

	if (
		strpos($needle, 'групп') !== false ||
		strpos($needle, 'мини-групп') !== false ||
		strpos($needle, 'чел') !== false ||
		preg_match('/\d+\s*[-–—]\s*\d+/u', $needle) === 1
	) {
		return 'group';
	}

	return '';
};

$extractTourDifficultyLabel = static function(Page $tourPage) use ($normalizeDisplayText): string {
	$difficulty = '';
	if ($tourPage->hasField('tour_difficulty_level')) {
		$value = $tourPage->getUnformatted('tour_difficulty_level');
		if ($value instanceof SelectableOptionArray && $value->count()) {
			$selected = $value->first();
			if ($selected instanceof SelectableOption) $difficulty = trim((string) $selected->title);
		} elseif ($value instanceof SelectableOption) {
			$difficulty = trim((string) $value->title);
		}
	}

	if ($difficulty === '' && $tourPage->hasField('tour_difficulty')) {
		$difficulty = trim((string) $tourPage->getUnformatted('tour_difficulty'));
	}

	return $normalizeDisplayText($difficulty);
};

$extractSeasonalityKeys = static function(string $value) use ($toLower): array {
	$value = str_replace('ё', 'е', $toLower(trim($value)));
	if ($value === '') return [];

	$keys = [];
	$contains = static function(string $needle) use ($value): bool {
		return strpos($value, $needle) !== false;
	};

	if (
		$contains('круглогод') ||
		$contains('круглый год') ||
		$contains('весь год') ||
		$contains('все сез') ||
		$contains('всесезон')
	) {
		$keys[] = 'year-round';
		$keys[] = 'spring';
		$keys[] = 'summer';
		$keys[] = 'autumn';
		$keys[] = 'winter';
	}

	if ($contains('весн') || $contains('март') || $contains('апрел') || $contains('май')) {
		$keys[] = 'spring';
	}

	if ($contains('лет') || $contains('июн') || $contains('июл') || $contains('август')) {
		$keys[] = 'summer';
	}

	if ($contains('осен') || $contains('сентябр') || $contains('октябр') || $contains('ноябр')) {
		$keys[] = 'autumn';
	}

	if ($contains('зим') || $contains('декабр') || $contains('январ') || $contains('феврал')) {
		$keys[] = 'winter';
	}

	return array_values(array_unique($keys));
};

$searchRegion = $normalizeDisplayText((string) $input->get('where'));
$searchTourType = trim((string) $input->get('tour_type'));
$searchDifficulty = $normalizeDisplayText((string) $input->get('difficulty'));
$searchSeasonality = trim((string) $input->get('seasonality'));

if ($searchTourType !== '' && !isset($tourTypeOptions[$searchTourType])) $searchTourType = '';
if ($searchSeasonality !== '' && !isset($seasonalityOptions[$searchSeasonality])) $searchSeasonality = '';

$homeDisplayPage = $page;
if (isset($pages) && $pages instanceof Pages && !$homeDisplayPage->hasField('home_featured_tours')) {
	$rootHomePage = $pages->get('/');
	if ($rootHomePage instanceof Page && $rootHomePage->id) {
		$homeDisplayPage = $rootHomePage;
	}
}

$isTourSearchSubmitted = trim((string) $input->get('search_tours')) === '1';

$toursCatalog = [];

if (isset($pages) && $pages instanceof Pages) {
	$tourPages = $pages->find('template=tour, include=all, check_access=0, status<1024, sort=title, limit=500');
	$tourPageIds = [];
	foreach ($tourPages as $tourPage) {
		if ($tourPage instanceof Page && $tourPage->id) $tourPageIds[] = (int) $tourPage->id;
	}
	$legacyTourTitlesById = $loadLegacyTitlesByIds($tourPageIds);

	foreach ($tourPages as $tourPage) {
		if (!$tourPage instanceof Page) continue;

		$title = $tourPage->hasField('tour_title') ? trim((string) $tourPage->getUnformatted('tour_title')) : '';
		if ($title === '') $title = trim((string) $tourPage->title);
		if ($title === '') $title = trim((string) ($legacyTourTitlesById[(int) $tourPage->id] ?? ''));
		if ($title === '') $title = $titleFromName((string) $tourPage->name);
		if ($title === '') continue;

			$imageUrl = $getFirstImageUrlFromPage($tourPage, ['tour_cover_image', 'images']);
			if ($imageUrl === '') $imageUrl = $defaultCardImage;
			$tourTypeKey = $normalizeTourTypeKey($getFirstTextFromPage($tourPage, ['tour_type', 'tour_format', 'tour_group_type', 'tour_group']));
			$tourDifficulty = $extractTourDifficultyLabel($tourPage);
			$tourSeasonalityKeys = $extractSeasonalityKeys($getFirstTextFromPage($tourPage, ['tour_season']));
			if ($tourDifficulty !== '') $addDifficultyOption($tourDifficulty);

			$toursCatalog[] = [
				'title' => $title,
				'region' => $getFirstTextFromPage($tourPage, ['tour_region', 'region']),
				'type_key' => $tourTypeKey,
				'type_label' => $tourTypeOptions[$tourTypeKey] ?? '',
				'difficulty' => $tourDifficulty,
				'seasonality_keys' => $tourSeasonalityKeys,
				'price' => $tourPage->hasField('tour_price') ? $normalizeTourPrice((string) $tourPage->getUnformatted('tour_price')) : '',
				'duration' => $tourPage->hasField('tour_duration') ? $normalizeTourDuration((string) $tourPage->tour_duration) : '',
				'image' => $imageUrl,
				'url' => (string) $tourPage->url,
			];
	}
}

if (!count($toursCatalog) && $homeDisplayPage->hasField('home_featured_tours') && $homeDisplayPage->home_featured_tours->count()) {
	$featuredTourIds = [];
	foreach ($homeDisplayPage->home_featured_tours as $tourPage) {
		if ($tourPage instanceof Page && $tourPage->id) $featuredTourIds[] = (int) $tourPage->id;
	}
	$legacyFeaturedTitlesById = $loadLegacyTitlesByIds($featuredTourIds);

	foreach ($homeDisplayPage->home_featured_tours as $tourPage) {
		if (!$tourPage instanceof Page) continue;
		$title = $tourPage->hasField('tour_title') ? trim((string) $tourPage->getUnformatted('tour_title')) : '';
		if ($title === '') $title = trim((string) $tourPage->title);
		if ($title === '') $title = trim((string) ($legacyFeaturedTitlesById[(int) $tourPage->id] ?? ''));
		if ($title === '') $title = $titleFromName((string) $tourPage->name);
		if ($title === '') continue;

			$imageUrl = $getFirstImageUrlFromPage($tourPage, ['tour_cover_image', 'images']);
			if ($imageUrl === '') $imageUrl = $defaultCardImage;
			$tourTypeKey = $normalizeTourTypeKey($getFirstTextFromPage($tourPage, ['tour_type', 'tour_format', 'tour_group_type', 'tour_group']));
			$tourDifficulty = $extractTourDifficultyLabel($tourPage);
			$tourSeasonalityKeys = $extractSeasonalityKeys($getFirstTextFromPage($tourPage, ['tour_season']));
			if ($tourDifficulty !== '') $addDifficultyOption($tourDifficulty);

			$toursCatalog[] = [
				'title' => $title,
				'region' => $getFirstTextFromPage($tourPage, ['tour_region', 'region']),
				'type_key' => $tourTypeKey,
				'type_label' => $tourTypeOptions[$tourTypeKey] ?? '',
				'difficulty' => $tourDifficulty,
				'seasonality_keys' => $tourSeasonalityKeys,
				'price' => $tourPage->hasField('tour_price') ? $normalizeTourPrice((string) $tourPage->getUnformatted('tour_price')) : '',
				'duration' => $tourPage->hasField('tour_duration') ? $normalizeTourDuration((string) $tourPage->tour_duration) : '',
				'image' => $imageUrl,
				'url' => (string) $tourPage->url,
			];
	}
}

$placesCatalog = [];
if (isset($pages) && $pages instanceof Pages) {
	$placePages = $pages->find('template=place, include=all, check_access=0, status<1024, sort=title, limit=1000');
	foreach ($placePages as $placePage) {
		if (!$placePage instanceof Page) continue;
		$title = trim((string) $placePage->title);
		if ($title === '') continue;

		$region = $getFirstTextFromPage($placePage, ['place_region', 'region']);
		$summarySource = $getFirstTextFromPage($placePage, ['place_summary', 'summary', 'content']);
		$imageUrl = $getFirstImageUrlFromPage($placePage, ['place_image', 'place_cover_image', 'images']);
		if ($imageUrl === '') $imageUrl = $defaultCardImage;

		$placesCatalog[] = [
			'title' => $title,
			'region' => $region,
			'summary' => $truncateText($summarySource, 180),
			'image' => $imageUrl,
			'url' => (string) $placePage->url,
		];

		if ($region !== '') $addRegionOption($region);
	}
}

$placeUrlByTitle = [];
foreach ($placesCatalog as $placeCard) {
	$placeTitle = trim((string) ($placeCard['title'] ?? ''));
	$placeUrl = trim((string) ($placeCard['url'] ?? ''));
	if ($placeTitle === '' || $placeUrl === '') continue;
	$placeUrlByTitle[$toLower($placeTitle)] = $placeUrl;
}

$appendLocalQueryParams = static function(string $url, array $params): string {
	$url = trim($url);
	if ($url === '') return '';

	$parts = parse_url($url);
	if ($parts === false) return $url;
	if (!empty($parts['scheme']) || !empty($parts['host'])) return $url;

	$query = [];
	if (!empty($parts['query'])) parse_str((string) $parts['query'], $query);

	foreach ($params as $key => $value) {
		$key = trim((string) $key);
		$value = trim((string) $value);
		if ($key === '' || $value === '') continue;
		$query[$key] = $value;
	}

	$path = (string) ($parts['path'] ?? '/');
	if ($path === '') $path = '/';
	if ($path[0] !== '/') $path = '/' . ltrim($path, '/');
	$queryString = count($query) ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '';
	$fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . (string) $parts['fragment'] : '';
	return $path . $queryString . $fragment;
};

$articlesCatalog = [];
if (isset($pages) && $pages instanceof Pages) {
	$articlePages = $pages->find('template=article, include=all, check_access=0, status<1024, sort=-article_publish_date, limit=300');
	foreach ($articlePages as $articlePage) {
		if (!$articlePage instanceof Page) continue;
		$title = trim((string) $articlePage->title);
		if ($title === '') continue;

		$topic = $getFirstTextFromPage($articlePage, ['article_topic', 'section']);
		$imageUrl = $getFirstImageUrlFromPage($articlePage, ['article_cover_image', 'images']);

		$articlesCatalog[] = [
			'title' => $title,
			'topic' => $topic,
			'date' => $formatArticleDateFromPage($articlePage),
			'image' => $imageUrl,
			'url' => '/articles/?' . http_build_query([
				'article' => (string) $articlePage->name,
				'from' => 'home',
				'back' => '/',
			], '', '&', PHP_QUERY_RFC3986),
		];
	}
}

foreach ($toursCatalog as $tourItem) {
	$tourRegion = trim((string) ($tourItem['region'] ?? ''));
	if ($tourRegion !== '') $addRegionOption($tourRegion);
}

$knownRegionOptions = array_flip($regionOptions);
if ($searchRegion !== '' && !isset($knownRegionOptions[$searchRegion])) {
	$searchRegion = '';
}

if ($searchDifficulty !== '' && !isset($difficultyOptionKeys[$toLower($searchDifficulty)])) {
	$searchDifficulty = '';
}

$regionFieldClass = $searchRegion !== '' ? ' is-filled' : '';
$tourTypeFieldClass = $searchTourType !== '' ? ' is-filled' : '';
$difficultyFieldClass = $searchDifficulty !== '' ? ' is-filled' : '';
$seasonalityFieldClass = $searchSeasonality !== '' ? ' is-filled' : '';

$filteredTours = [];
if ($isTourSearchSubmitted) {
	$regionNeedle = $toLower($searchRegion);
	$typeNeedle = trim($searchTourType);
	$difficultyNeedle = $toLower($searchDifficulty);
	$seasonalityNeedle = trim($searchSeasonality);

	$filteredTours = array_values(array_filter($toursCatalog, static function(array $tour) use ($regionNeedle, $typeNeedle, $difficultyNeedle, $seasonalityNeedle, $toLower): bool {
		if ($regionNeedle !== '') {
			$region = $toLower(trim((string) ($tour['region'] ?? '')));
			if (strpos($region, $regionNeedle) === false) return false;
		}

		if ($typeNeedle !== '') {
			$typeKey = trim((string) ($tour['type_key'] ?? ''));
			if ($typeKey !== $typeNeedle) return false;
		}

		if ($difficultyNeedle !== '') {
			$tourDifficulty = $toLower(trim((string) ($tour['difficulty'] ?? '')));
			if ($tourDifficulty === '' || $tourDifficulty !== $difficultyNeedle) return false;
		}

		if ($seasonalityNeedle !== '') {
			$tourSeasonality = $tour['seasonality_keys'] ?? [];
			if (!is_array($tourSeasonality) || !in_array($seasonalityNeedle, $tourSeasonality, true)) return false;
		}

		return true;
	}));
}

$forumExternalUrl = 'https://club.skfo.ru';

?>

<div id="content">
	<section class="hero">
		<div class="container hero-inner">
			<h1 class="hero-title">
				ТВОЙ КАВКАЗ<br />
				НАЧИНАЕТСЯ ЗДЕСЬ
			</h1>
			<div class="hero-tabs" aria-label="Разделы">
				<div class="hero-tabs-group" role="tablist">
					<span class="tab-indicator" aria-hidden="true"></span>
					<span class="tab-hover" aria-hidden="true"></span>
					<a class="hero-tab is-active" href="/" role="tab" aria-selected="true">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/tour.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Туры</span>
					</a>
					<a class="hero-tab" href="/hotels/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/hotel.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Отели</span>
					</a>
					<a class="hero-tab" href="/reviews/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/reviews.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Отзывы</span>
					</a>
					<a class="hero-tab" href="/regions/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Регионы</span>
					</a>
					<a class="hero-tab" href="/articles/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/journal.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Статьи</span>
					</a>
				</div>
				<a class="hero-tab hero-tab-forum" href="<?php echo $forumExternalUrl; ?>" target="_blank" rel="noopener noreferrer" aria-label="Форум">
					<img src="<?php echo $config->urls->templates; ?>assets/icons/forum.svg" alt="" aria-hidden="true" />
					<span>Форум</span>
					<img class="hero-tab-external" src="<?php echo $config->urls->templates; ?>assets/icons/external_site.svg" alt="" aria-hidden="true" />
				</a>
			</div>
			<form class="hero-search hero-search--compact" action="<?php echo $sanitizer->entities($page->url); ?>" method="get">
				<input type="hidden" name="search_tours" value="1" />
				<div class="hero-search-fields">
					<label class="hero-field hero-field-where<?php echo $regionFieldClass; ?>">
						<span class="sr-only">Регион</span>
						<select name="where">
							<option value="">Регион</option>
							<?php foreach ($regionOptions as $regionOption): ?>
								<option value="<?php echo $sanitizer->entities($regionOption); ?>"<?php echo $searchRegion === $regionOption ? ' selected' : ''; ?>>
									<?php echo $sanitizer->entities($regionOption); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
					</label>
					<label class="hero-field<?php echo $tourTypeFieldClass; ?>">
						<span class="sr-only">Тип тура</span>
						<select name="tour_type">
							<option value="">Тип тура</option>
							<?php foreach ($tourTypeOptions as $tourTypeKey => $tourTypeLabel): ?>
								<option value="<?php echo $sanitizer->entities($tourTypeKey); ?>"<?php echo $searchTourType === $tourTypeKey ? ' selected' : ''; ?>>
									<?php echo $sanitizer->entities($tourTypeLabel); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<img src="<?php echo $config->urls->templates; ?>assets/icons/human.svg" alt="" aria-hidden="true" />
					</label>
					<label class="hero-field<?php echo $difficultyFieldClass; ?>">
						<span class="sr-only">Сложность</span>
						<select name="difficulty">
							<option value="">Сложность</option>
							<?php foreach ($difficultyOptions as $difficultyOption): ?>
								<option value="<?php echo $sanitizer->entities($difficultyOption); ?>"<?php echo $searchDifficulty === $difficultyOption ? ' selected' : ''; ?>>
									<?php echo $sanitizer->entities($difficultyOption); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<img src="<?php echo $config->urls->templates; ?>assets/icons/tour.svg" alt="" aria-hidden="true" />
					</label>
					<label class="hero-field<?php echo $seasonalityFieldClass; ?>">
						<span class="sr-only">Сезонность</span>
						<select name="seasonality">
							<option value="">Сезонность</option>
							<?php foreach ($seasonalityOptions as $seasonalityKey => $seasonalityLabel): ?>
								<option value="<?php echo $sanitizer->entities($seasonalityKey); ?>"<?php echo $searchSeasonality === $seasonalityKey ? ' selected' : ''; ?>>
									<?php echo $sanitizer->entities($seasonalityLabel); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<img src="<?php echo $config->urls->templates; ?>assets/icons/when.svg" alt="" aria-hidden="true" />
					</label>
				</div>
				<button class="search-btn" type="submit">Применить</button>
			</form>
		</div>
	</section>

	<?php if ($isTourSearchSubmitted): ?>
		<section class="section section--hotels-results section--home-tours-results">
			<div class="container">
				<h2 class="section-title home-tours-results-title">Подходящие туры</h2>
				<?php if (count($filteredTours)): ?>
					<div class="hotels-grid">
						<?php foreach ($filteredTours as $tour): ?>
							<?php
							$tourImage = trim((string) ($tour['image'] ?? ''));
							$tourRegion = trim((string) ($tour['region'] ?? ''));
							$tourPrice = trim((string) ($tour['price'] ?? ''));
							$tourDuration = trim((string) ($tour['duration'] ?? ''));
							$tourUrl = trim((string) ($tour['url'] ?? ''));
							if ($tourPrice === '') $tourPrice = 'Цена уточняется';
							?>
							<article class="hotel-card">
								<div class="hotel-card-media"<?php echo $tourImage !== '' ? " style=\"background-image: url('" . htmlspecialchars($tourImage, ENT_QUOTES, 'UTF-8') . "');\"" : ''; ?>>
									<!-- <?php if ($tourDuration !== ''): ?>
										<span class="hotel-card-rating"><?php echo $sanitizer->entities($tourDuration); ?></span>
									<?php endif; ?> -->
								</div>
								<h2 class="hotel-card-title"><?php echo $sanitizer->entities((string) ($tour['title'] ?? '')); ?></h2>
								<p class="hotel-card-location"><?php echo $sanitizer->entities($tourRegion); ?></p>
								<ul class="hotel-card-amenities" aria-label="Параметры тура">
									<?php if ($tourDuration !== ''): ?>
										<li class="hotel-card-amenity">
											<span class="hotel-card-amenity-icon"><?php echo $sanitizer->entities($tourDuration); ?></span>
										</li>
									<?php endif; ?>
								</ul>
								<div class="hotel-card-footer">
									<div class="hotel-card-price"><?php echo $sanitizer->entities($tourPrice); ?></div>
									<?php if ($tourUrl !== ''): ?>
										<a class="hotel-card-btn" href="<?php echo $sanitizer->entities($tourUrl); ?>">Подробнее</a>
									<?php else: ?>
										<button class="hotel-card-btn" type="button">Подробнее</button>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
					<?php else: ?>
						<div class="hotels-empty">
							По выбранным фильтрам туры не найдены. Измените параметры и попробуйте снова.
						</div>
					<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

		<section class="section section--hot-tours">
		<?php
		$tourUrlByTitle = [];
		foreach ($toursCatalog as $tourItem) {
			$tourTitleForLink = $normalizeDisplayText((string) ($tourItem['title'] ?? ''));
			$tourUrlForLink = trim((string) ($tourItem['url'] ?? ''));
			if ($tourTitleForLink === '' || $tourUrlForLink === '') continue;
			$tourUrlByTitle[$toLower($tourTitleForLink)] = $tourUrlForLink;
		}

		$hotToursCards = [];

		if ($homeDisplayPage->hasField('home_featured_tours') && $homeDisplayPage->home_featured_tours->count()) {
			foreach ($homeDisplayPage->home_featured_tours as $tourPage) {
				if (!$tourPage instanceof Page) continue;
				$imageUrl = $getFirstImageUrlFromPage($tourPage, ['tour_cover_image', 'images']);
				if ($imageUrl === '') $imageUrl = $defaultCardImage;
				$title = $tourPage->hasField('tour_title') ? $normalizeDisplayText((string) $tourPage->getUnformatted('tour_title')) : '';
				if ($title === '') $title = $normalizeDisplayText((string) $tourPage->title);
				$region = $getFirstTextFromPage($tourPage, ['tour_region', 'region']);
				$price = $tourPage->hasField('tour_price') ? $normalizeTourPrice((string) $tourPage->getUnformatted('tour_price')) : '';
				if ($title === '' && $region === '' && $price === '' && $imageUrl === '') continue;

				$hotToursCards[] = [
					'title' => $title,
					'region' => $region,
					'price' => $price !== '' ? $price : 'Цена уточняется',
					'image' => $imageUrl,
					'url' => (string) $tourPage->url,
				];
			}
		}

		if (!count($hotToursCards) && count($toursCatalog)) {
			$hotToursCards = array_slice($toursCatalog, 0, 10);
			foreach ($hotToursCards as $index => $tourCard) {
				$hotToursCards[$index]['price'] = trim((string) ($tourCard['price'] ?? '')) !== '' ? trim((string) $tourCard['price']) : 'Цена уточняется';
			}
		}

		if (!count($hotToursCards) && $homeDisplayPage->hasField('hot_tours_cards') && $homeDisplayPage->hot_tours_cards->count()) {
			foreach ($homeDisplayPage->hot_tours_cards as $card) {
				$imageUrl = '';
				if ($card->hasField('hot_tour_image')) {
					$imageUrl = $getImageUrlFromValue($card->getUnformatted('hot_tour_image'));
				}
				if ($imageUrl === '') $imageUrl = $defaultCardImage;
				$hotToursCards[] = [
					'title' => $card->hasField('hot_tour_title') ? $normalizeDisplayText((string) $card->hot_tour_title) : '',
					'region' => $card->hasField('hot_tour_region') ? $normalizeDisplayText((string) $card->hot_tour_region) : '',
					'price' => $card->hasField('hot_tour_price') ? $normalizeTourPrice((string) $card->getUnformatted('hot_tour_price')) : 'Цена уточняется',
					'image' => $imageUrl,
					'url' => '',
				];
				$lastIndex = count($hotToursCards) - 1;
				$lastTitleKey = $toLower((string) ($hotToursCards[$lastIndex]['title'] ?? ''));
				if ($lastTitleKey !== '' && isset($tourUrlByTitle[$lastTitleKey])) {
					$hotToursCards[$lastIndex]['url'] = $tourUrlByTitle[$lastTitleKey];
				}
			}
		}
		?>
		<div class="container-hot-tours">
			<div class="hot-tours-header">
				<h2 class="section-title">Чем заняться этим летом?</h2>
				<div class="hot-tours-actions">
					<button class="circle-btn circle-btn--prev hot-tours-prev" type="button" aria-label="Предыдущие туры"></button>
					<button class="circle-btn circle-btn--next hot-tours-next" type="button" aria-label="Следующие туры"></button>
				</div>
			</div>
				<div class="hot-tours-grid">
					<div class="hot-tours-track">
						<?php foreach ($hotToursCards as $card): ?>
							<?php
							$backgroundStyle = '';
							$cardUrl = trim((string) ($card['url'] ?? ''));
							$isCardLink = $cardUrl !== '';
							if (!empty($card['image'])) {
								$image = htmlspecialchars($card['image'], ENT_QUOTES, 'UTF-8');
								$backgroundStyle = " style=\"background-image: linear-gradient(135deg, rgba(17, 24, 39, 0.2), rgba(17, 24, 39, 0.1)), url('{$image}');\"";
							}
							?>
							<?php if ($isCardLink): ?>
								<a class="hot-tour-card" href="<?php echo $sanitizer->entities($cardUrl); ?>" aria-label="<?php echo $sanitizer->entities((string) $card['title']); ?>">
							<?php else: ?>
								<article class="hot-tour-card">
							<?php endif; ?>
								<div class="hot-tour-image"<?php echo $backgroundStyle; ?>></div>
								<div class="hot-tour-body">
									<h3 class="hot-tour-title"><?php echo $sanitizer->entities($card['title']); ?></h3>
									<div class="hot-tour-region"><?php echo $sanitizer->entities($card['region']); ?></div>
									<div class="hot-tour-footer">
										<span class="hot-tour-price"><?php echo $sanitizer->entities($card['price']); ?></span>
									</div>
								</div>
							<?php if ($isCardLink): ?>
								</a>
							<?php else: ?>
								</article>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
			<div class="hot-tours-footer">
				<button class="hot-tours-more-btn" type="button">
					<span>Показать всё</span>
				</button>
			</div>
		</div>
	</section>

		<?php
		$actualCards = [];
		$actualCardKeys = [];

		$addActualCard = static function(array $card) use (&$actualCards, &$actualCardKeys, $toLower, $defaultCardImage, $normalizeDisplayText): void {
			$title = $normalizeDisplayText((string) ($card['title'] ?? ''));
			$text = $normalizeDisplayText((string) ($card['text'] ?? ''));
			$region = $normalizeDisplayText((string) ($card['region'] ?? ''));
			$image = trim((string) ($card['image'] ?? ''));
			$url = trim((string) ($card['url'] ?? ''));

			if ($image === '' || $image === $defaultCardImage) return;
			if ($title === '' && $text === '') return;
			if ($region === '') $region = 'Северный Кавказ';

			$key = $toLower($title . '|' . $region . '|' . $image);
			if (isset($actualCardKeys[$key])) return;
			$actualCardKeys[$key] = true;

			$actualCards[] = [
				'title' => $title,
				'text' => $text,
				'region' => $region,
				'image' => $image,
				'url' => $url,
			];
		};

		if ($homeDisplayPage->hasField('home_actual_places') && $homeDisplayPage->home_actual_places->count()) {
			foreach ($homeDisplayPage->home_actual_places as $placePage) {
				if (!$placePage instanceof Page) continue;
				$imageUrl = $getFirstImageUrlFromPage($placePage, ['place_image', 'place_cover_image', 'images']);
				$addActualCard([
					'title' => trim((string) $placePage->title),
					'text' => $truncateText($getFirstTextFromPage($placePage, ['place_summary', 'summary', 'content']), 180),
					'region' => $getFirstTextFromPage($placePage, ['place_region', 'region']),
					'image' => $imageUrl,
					'url' => (string) $placePage->url,
				]);
			}
		}

		if (count($placesCatalog)) {
			foreach ($placesCatalog as $placeCard) {
				$imageUrl = trim((string) ($placeCard['image'] ?? ''));
				$addActualCard([
					'title' => trim((string) ($placeCard['title'] ?? '')),
					'text' => trim((string) ($placeCard['summary'] ?? '')),
					'region' => trim((string) ($placeCard['region'] ?? '')),
					'image' => $imageUrl,
					'url' => trim((string) ($placeCard['url'] ?? '')),
				]);
			}
		}

		if ($homeDisplayPage->hasField('actual_cards') && $homeDisplayPage->actual_cards->count()) {
			foreach ($homeDisplayPage->actual_cards as $card) {
				$imageUrl = '';
				if ($card->hasField('card_image')) {
					$imageUrl = $getImageUrlFromValue($card->getUnformatted('card_image'));
				}
				$addActualCard([
					'title' => $card->hasField('card_title') ? trim((string) $card->card_title) : '',
					'text' => $card->hasField('card_text') ? trim((string) $card->card_text) : '',
					'region' => $card->hasField('card_region') ? trim((string) $card->card_region) : '',
					'image' => $imageUrl,
					'url' => '',
				]);
			}
		}

			if (count($actualCards) > 24) {
				$actualCards = array_slice($actualCards, 0, 24);
			}
	?>
	<?php if (count($actualCards)): ?>
	<section class="section section--actual section--actual-slider" data-actual-slider>
		<div class="container">
			<div class="actual-grid">
				<div class="actual-track">
					<?php foreach ($actualCards as $card): ?>
						<?php
						$backgroundStyle = '';
						if (!empty($card['image'])) {
							$image = htmlspecialchars((string) $card['image'], ENT_QUOTES, 'UTF-8');
							$backgroundStyle = " style=\"background-image: linear-gradient(135deg, rgba(17, 24, 39, 0.25), rgba(17, 24, 39, 0.15)), url('{$image}');\"";
						}
						?>
						<article class="actual-card">
							<div class="actual-card-image"<?php echo $backgroundStyle; ?>></div>
							<div class="actual-card-body">
								<h3 class="actual-card-title"><?php echo $sanitizer->entities((string) $card['title']); ?></h3>
								<p class="actual-card-text"><?php echo $sanitizer->entities((string) $card['text']); ?></p>
								<div class="actual-card-footer">
									<span class="tag-location"><?php echo $sanitizer->entities((string) $card['region']); ?></span>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="actual-slider-progress" data-actual-progress<?php echo count($actualCards) > 2 ? '' : ' hidden'; ?>>
				<div
					class="actual-slider-progress-track"
					data-actual-progress-track
					role="slider"
					tabindex="0"
					aria-label="Слайд интересных мест"
					aria-valuemin="0"
					aria-valuemax="0"
					aria-valuenow="0"
				>
					<span class="actual-slider-progress-fill" data-actual-progress-fill></span>
				</div>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<section class="section section--journal">
		<?php
		$mapHomeJournalArticle = static function(Page $articlePage) use ($getFirstTextFromPage, $getFirstImageUrlFromPage, $formatArticleDateFromPage, $defaultCardImage): array {
			$imageUrl = $getFirstImageUrlFromPage($articlePage, ['article_cover_image', 'images']);
			if ($imageUrl === '') $imageUrl = $defaultCardImage;
			return [
				'title' => trim((string) $articlePage->title),
				'topic' => $getFirstTextFromPage($articlePage, ['article_topic', 'section']),
				'date' => $formatArticleDateFromPage($articlePage),
				'image' => $imageUrl,
				'url' => '/articles/?' . http_build_query([
					'article' => (string) $articlePage->name,
					'from' => 'home',
					'back' => '/',
				], '', '&', PHP_QUERY_RFC3986),
			];
		};

		$homeJournalArticles = [];
		$homeJournalSlugs = [];
		$addHomeJournalArticle = static function(array $item) use (&$homeJournalArticles, &$homeJournalSlugs): void {
			$title = trim((string) ($item['title'] ?? ''));
			if ($title === '') return;

			$url = trim((string) ($item['url'] ?? ''));
			$slugKey = '';
			if ($url !== '') {
				$urlQuery = parse_url($url, PHP_URL_QUERY);
				if (is_string($urlQuery) && $urlQuery !== '') {
					parse_str($urlQuery, $params);
					$slugKey = trim((string) ($params['article'] ?? ''));
				}
			}
			if ($slugKey === '') {
				$slugKey = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
			}
			if ($slugKey !== '' && isset($homeJournalSlugs[$slugKey])) return;

			if ($slugKey !== '') $homeJournalSlugs[$slugKey] = true;
			$homeJournalArticles[] = $item;
		};

		if ($homeDisplayPage->hasField('home_featured_articles') && $homeDisplayPage->home_featured_articles->count()) {
			foreach ($homeDisplayPage->home_featured_articles as $articlePage) {
				if (!$articlePage instanceof Page || !$articlePage->id) continue;
				$addHomeJournalArticle($mapHomeJournalArticle($articlePage));
			}
		}

		if (count($homeJournalArticles) < 2) {
			foreach (array_slice($articlesCatalog, 0, 10) as $articleItem) {
				$addHomeJournalArticle($articleItem);
			}
		}

		if (!count($homeJournalArticles) && count($articlesCatalog)) {
			$homeJournalArticles[] = $articlesCatalog[0];
		}

		if (!count($homeJournalArticles)) {
			$homeJournalArticles[] = [
				'title' => 'Как подготовиться к первому путешествию в Дагестан',
				'topic' => 'Советы туристам',
				'date' => '22.12.2025',
				'image' => '',
				'url' => '/articles/?article=kak-podgotovitsya-k-pervomu-puteshestviyu-v-dagestan&from=home&back=%2F',
			];
		}
		?>
		<div class="container">
			<div class="journal-card">
				<div class="journal-card-header">
					<h2 class="journal-title">Статьи СКФО</h2>
					<p class="journal-subtitle">
						Читайте и планируйте поездки </br> по гайдам, маршрутам, советам
					</p>
					<a class="journal-button" href="/articles/">Выбрать статью</a>
				</div>
				<div class="journal-articles" aria-live="polite">
					<?php foreach ($homeJournalArticles as $index => $homeJournalArticle): ?>
						<a class="journal-article<?php echo $index === 0 ? ' is-active' : ''; ?>" href="<?php echo $sanitizer->entities((string) $homeJournalArticle['url']); ?>">
							<?php
							$journalImageStyle = '';
							$journalImageClass = '';
							if (trim((string) $homeJournalArticle['image']) !== '') {
								$journalImage = htmlspecialchars((string) $homeJournalArticle['image'], ENT_QUOTES, 'UTF-8');
								$journalImageStyle = " style=\"background-image: url('{$journalImage}');\"";
								$journalImageClass = ' has-image';
							}
							?>
							<div class="journal-article-image journal-article-image--1<?php echo $journalImageClass; ?>"<?php echo $journalImageStyle; ?>></div>
							<div class="journal-article-content">
								<div class="journal-article-meta">
									<span class="journal-article-date"><?php echo $sanitizer->entities((string) $homeJournalArticle['date']); ?></span>
								</div>
								<h3 class="journal-article-title">
									<?php echo $sanitizer->entities((string) $homeJournalArticle['title']); ?>
								</h3>
								<span class="journal-article-tag"><?php echo $sanitizer->entities((string) $homeJournalArticle['topic']); ?></span>
							</div>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>

	<section class="section section--places">
			<?php
			$dagestanPlacesCards = [];

			if ($homeDisplayPage->hasField('home_featured_places') && $homeDisplayPage->home_featured_places->count()) {
				foreach ($homeDisplayPage->home_featured_places as $placePage) {
					if (!$placePage instanceof Page) continue;
					$imageUrl = $getFirstImageUrlFromPage($placePage, ['place_image', 'place_cover_image', 'images']);
					if ($imageUrl === '') $imageUrl = $defaultCardImage;
					$title = trim((string) $placePage->title);
					if ($title === '' && $imageUrl === '') continue;

					$dagestanPlacesCards[] = [
						'title' => $title,
						'image' => $imageUrl,
						'url' => (string) $placePage->url,
					];
				}
			}

			if (!count($dagestanPlacesCards) && count($placesCatalog)) {
				foreach ($placesCatalog as $placeCard) {
					$region = $toLower((string) ($placeCard['region'] ?? ''));
					if ($region === '' || strpos($region, 'дагестан') === false) continue;
					$dagestanPlacesCards[] = [
						'title' => trim((string) ($placeCard['title'] ?? '')),
						'image' => trim((string) ($placeCard['image'] ?? '')),
						'url' => trim((string) ($placeCard['url'] ?? '')),
					];
				}
			}

			if (!count($dagestanPlacesCards) && count($placesCatalog)) {
				foreach (array_slice($placesCatalog, 0, 10) as $placeCard) {
					$dagestanPlacesCards[] = [
						'title' => trim((string) ($placeCard['title'] ?? '')),
						'image' => trim((string) ($placeCard['image'] ?? '')),
						'url' => trim((string) ($placeCard['url'] ?? '')),
					];
				}
			}

			if (!count($dagestanPlacesCards) && $homeDisplayPage->hasField('dagestan_places_cards') && $homeDisplayPage->dagestan_places_cards->count()) {
				foreach ($homeDisplayPage->dagestan_places_cards as $card) {
					$imageUrl = '';
					if ($card->hasField('dagestan_place_image')) {
						$imageUrl = $getImageUrlFromValue($card->getUnformatted('dagestan_place_image'));
					}
					if ($imageUrl === '') $imageUrl = $defaultCardImage;
					$title = $card->hasField('dagestan_place_title') ? trim((string) $card->dagestan_place_title) : '';
					$titleKey = $toLower($title);

					$dagestanPlacesCards[] = [
						'title' => $title,
						'image' => $imageUrl,
						'url' => $titleKey !== '' ? trim((string) ($placeUrlByTitle[$titleKey] ?? '')) : '',
					];
				}
			}

			$dagestanPlacesWithPhoto = array_values(array_filter(
				$dagestanPlacesCards,
				static function(array $card) use ($defaultCardImage): bool {
					$imageUrl = trim((string) ($card['image'] ?? ''));
					return $imageUrl !== '' && $imageUrl !== $defaultCardImage;
				}
			));
			if (count($dagestanPlacesWithPhoto)) {
				$dagestanPlacesCards = $dagestanPlacesWithPhoto;
			}

			$dagestanHasSlider = count($dagestanPlacesCards) > 5;
			?>
			<div class="container">
				<div class="places-banner<?php echo $dagestanHasSlider ? ' places-banner--slider' : ''; ?>">
					<div class="places-banner-header">
						<h2 class="section-title section-title--places">Что насчет Дагестана?</h2>
						<div class="places-banner-actions">
							<button class="circle-btn circle-btn--prev places-prev" type="button" aria-label="Предыдущие места"></button>
							<button class="circle-btn circle-btn--next places-next" type="button" aria-label="Следующие места"></button>
						</div>
					</div>
					<div class="places-grid">
						<div class="places-track">
							<?php foreach ($dagestanPlacesCards as $card): ?>
								<?php
								$backgroundStyle = '';
								$cardTitle = trim((string) ($card['title'] ?? ''));
								$cardUrl = trim((string) ($card['url'] ?? ''));
								if ($cardUrl === '' && $cardTitle !== '') {
									$cardTitleKey = $toLower($cardTitle);
									if (isset($placeUrlByTitle[$cardTitleKey])) {
										$cardUrl = trim((string) $placeUrlByTitle[$cardTitleKey]);
									}
								}
								if ($cardUrl === '' && $cardTitle !== '') {
									$cardUrl = '/places/?q=' . rawurlencode($cardTitle);
								}
								$cardUrl = $appendLocalQueryParams($cardUrl, [
									'from' => 'home',
									'back' => '/',
									'cover' => trim((string) ($card['image'] ?? '')),
								]);
								$isCardLink = $cardUrl !== '';
								if (!empty($card['image'])) {
									$image = htmlspecialchars($card['image'], ENT_QUOTES, 'UTF-8');
									$backgroundStyle = " style=\"background-image: linear-gradient(135deg, rgba(17, 24, 39, 0.2), rgba(17, 24, 39, 0.1)), url('{$image}');\"";
								}
								?>
								<?php if ($isCardLink): ?>
									<a class="place-card" href="<?php echo $sanitizer->entities($cardUrl); ?>" aria-label="<?php echo $sanitizer->entities((string) $card['title']); ?>">
								<?php else: ?>
									<article class="place-card">
								<?php endif; ?>
									<div class="place-card-image"<?php echo $backgroundStyle; ?>></div>
									<h3 class="place-card-title"><?php echo $sanitizer->entities($card['title']); ?></h3>
								<?php if ($isCardLink): ?>
									</a>
								<?php else: ?>
									</article>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="places-footer">
						<button class="places-more-btn" type="button">
						<span>Показать всё</span>
					</button>
				</div>
			</div>
		</div>
	</section>

	<section class="section section--forum">
		<div class="container">
			<div class="forum-card">
				<div class="forum-card-inner">
					<h2 class="forum-title">Форум СКФО</h2>
					<p class="forum-subtitle">
						Делимся опытом и помогаем<br />
						друг другу планировать поездки
					</p>
					<a class="forum-button" href="<?php echo $forumExternalUrl; ?>" target="_blank" rel="noopener noreferrer">Присоединиться</a>
				</div>
				<img class="forum-image" src="<?php echo $config->urls->templates; ?>assets/image1.png" alt="Форум СКФО" />
			</div>
		</div>
	</section>
</div>
