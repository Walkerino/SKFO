<?php namespace ProcessWire;

$getImageUrlFromValue = static function($imageValue): string {
	if ($imageValue instanceof Pageimage) return (string) $imageValue->url;
	if ($imageValue instanceof Pageimages && $imageValue->count()) return (string) $imageValue->first()->url;
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

$getFirstImageUrlFromPage = static function(Page $item, array $fieldNames) use ($getImageUrlsFromValue): string {
	foreach ($fieldNames as $fieldName) {
		if (!$item->hasField($fieldName)) continue;
		$urls = $getImageUrlsFromValue($item->getUnformatted($fieldName));
		if (count($urls)) return trim((string) $urls[0]);
	}
	return '';
};

$normalizeDisplayText = static function(string $value): string {
	$decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$normalized = trim(str_replace(["\r", "\n"], ' ', $decoded));
	$normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
	return $normalized;
};

$getFirstText = static function(Page $item, array $fieldNames) use ($normalizeDisplayText): string {
	foreach ($fieldNames as $fieldName) {
		if (!$item->hasField($fieldName)) continue;
		$value = $normalizeDisplayText((string) $item->getUnformatted($fieldName));
		if ($value !== '') return $value;
	}
	return '';
};

$getComboValue = static function($value, array $keys) use ($normalizeDisplayText): string {
	$extract = static function($input, string $key): string {
		if (is_object($input) && method_exists($input, 'get')) {
			try {
				$result = $input->get($key);
				if (is_scalar($result)) return trim((string) $result);
			} catch (\Throwable $e) {
				// Skip unavailable combo keys.
			}
		}
		if (is_array($input)) {
			$result = $input[$key] ?? null;
			if (is_scalar($result)) return trim((string) $result);
		}
		return '';
	};

	foreach ($keys as $key) {
		$key = trim((string) $key);
		if ($key === '') continue;
		$result = $extract($value, $key);
		if ($result !== '') return $normalizeDisplayText($result);
	}

	if (is_string($value) && $value !== '') {
		$decoded = json_decode($value, true);
		if (is_array($decoded)) {
			foreach ($keys as $key) {
				$key = trim((string) $key);
				if ($key === '') continue;
				$result = trim((string) ($decoded[$key] ?? ''));
				if ($result !== '') return $normalizeDisplayText($result);
			}
		}
	}

	return '';
};

$extractParagraphs = static function(string $value): array {
	if (trim($value) === '') return [];
	$decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$decoded = str_replace("\r", '', $decoded);
	$decoded = preg_replace('/<\s*br\s*\/?\s*>/iu', "\n", $decoded) ?? $decoded;
	$decoded = preg_replace('/<\/p\s*>/iu', "\n\n", $decoded) ?? $decoded;
	$plain = strip_tags($decoded);
	$parts = preg_split('/\n{2,}/u', $plain) ?: [];
	$paragraphs = [];
	foreach ($parts as $part) {
		$line = trim((string) (preg_replace('/\s+/u', ' ', str_replace("\n", ' ', $part)) ?? $part));
		if ($line !== '') $paragraphs[] = $line;
	}
	return $paragraphs;
};

$sanitizeLocalUrl = static function(string $url, string $fallback = ''): string {
	$url = trim($url);
	if ($url === '') return $fallback;

	$parts = parse_url($url);
	if ($parts === false) return $fallback;
	if (!empty($parts['scheme']) || !empty($parts['host'])) return $fallback;

	$path = (string) ($parts['path'] ?? '');
	if ($path === '') $path = '/';
	if (strpos($path, '//') === 0) return $fallback;
	if ($path[0] !== '/') $path = '/' . ltrim($path, '/');

	$query = isset($parts['query']) && $parts['query'] !== '' ? '?' . (string) $parts['query'] : '';
	$fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . (string) $parts['fragment'] : '';
	return $path . $query . $fragment;
};

$sanitizeLocalImageUrl = static function(string $url): string {
	$url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	if ($url === '') return '';

	$parts = parse_url($url);
	if ($parts === false) return '';
	if (!empty($parts['scheme']) || !empty($parts['host'])) return '';

	$path = (string) ($parts['path'] ?? '');
	if ($path === '') return '';
	if ($path[0] !== '/') $path = '/' . ltrim($path, '/');
	if (preg_match('/\.(jpe?g|png|webp|gif|avif)$/i', $path) !== 1) return '';

	$query = isset($parts['query']) && $parts['query'] !== '' ? '?' . (string) $parts['query'] : '';
	return $path . $query;
};

$getFallbackImageUrlsFromPageFiles = static function(Page $item) use ($config): array {
	$pageId = (int) $item->id;
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

$normalizeLookupText = static function(string $value): string {
	$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$value = trim(str_replace(["\r", "\n"], ' ', $value));
	$value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	$value = str_replace('ё', 'е', $value);
	$value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
	$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
	return trim($value);
};

$extractLocalityFromText = static function(string $value) use ($normalizeDisplayText): string {
	$value = $normalizeDisplayText($value);
	if ($value === '') return '';

	$patterns = [
		'/(?:^|,\s*)(?:г(?:\.|ород)?|город)\s+([^,]+)/iu',
		'/(?:^|,\s*)(?:пгт|пос[её]лок\s+городского\s+типа|курорт(?:ный)?\s+пос[её]лок|рабоч(?:ий)?\s+пос[её]лок|пос(?:\.|[её]лок)?|п\.)\s+([^,]+)/iu',
		'/(?:^|,\s*)(?:с(?:\.|ело)?|село)\s+([^,]+)/iu',
		'/(?:^|,\s*)(?:аул|хутор|дер(?:\.|евня)?|станица|ст\.)\s+([^,]+)/iu',
		'/(?:^|,\s*)(?:слобода|кишлак|улус)\s+([^,]+)/iu',
	];

	foreach ($patterns as $pattern) {
		if (preg_match($pattern, $value, $matches) !== 1) continue;
		$locality = trim((string) ($matches[1] ?? ''), " \t\n\r\0\x0B,.;");
		if ($locality !== '') return $locality;
	}

	return '';
};

$extractTextValues = null;
$extractTextValues = static function($value) use (&$extractTextValues, $normalizeDisplayText): array {
	$values = [];
	if ($value === null) return $values;

	if (is_string($value) || is_numeric($value)) {
		$text = $normalizeDisplayText((string) $value);
		if ($text === '') return $values;
		$parts = preg_split('/[\n\r,;|]+/u', $text) ?: [];
		foreach ($parts as $part) {
			$label = $normalizeDisplayText((string) $part);
			if ($label !== '') $values[] = $label;
		}
		if (!count($values) && $text !== '') $values[] = $text;
		return $values;
	}

	if ($value instanceof SelectableOption) {
		$label = $normalizeDisplayText((string) $value->title);
		if ($label !== '') $values[] = $label;
		return $values;
	}

	if ($value instanceof Page) {
		$label = $normalizeDisplayText((string) $value->title);
		if ($label !== '') $values[] = $label;
		return $values;
	}

	if ($value instanceof WireArray || is_array($value) || $value instanceof Traversable) {
		foreach ($value as $item) {
			foreach ($extractTextValues($item) as $label) {
				$values[] = $label;
			}
		}
		return $values;
	}

	if (is_object($value)) {
		$props = get_object_vars($value);
		if (count($props)) {
			foreach ($props as $propValue) {
				foreach ($extractTextValues($propValue) as $label) {
					$values[] = $label;
				}
			}
			return $values;
		}

		if (method_exists($value, '__toString')) {
			$text = $normalizeDisplayText((string) $value);
			if ($text !== '') $values[] = $text;
		}
	}

	return $values;
};

$uniqueValues = static function(array $items) use ($normalizeLookupText, $normalizeDisplayText): array {
	$result = [];
	$seen = [];
	foreach ($items as $item) {
		$label = $normalizeDisplayText((string) $item);
		$key = $normalizeLookupText($label);
		if ($label === '' || $key === '' || isset($seen[$key])) continue;
		$seen[$key] = true;
		$result[] = $label;
	}
	return $result;
};

$extractAddressParts = null;
$extractAddressParts = static function($value) use (&$extractAddressParts, $extractTextValues, $uniqueValues): array {
	if ($value === null) return [];

	if (is_string($value) || is_numeric($value)) {
		return $uniqueValues($extractTextValues((string) $value));
	}

	if (is_array($value)) {
		$orderedKeys = ['i1', 'street', 'i6', 'street2', 'i2', 'city', 'i3', 'region', 'i5', 'postcode', 'zip', 'i4', 'country', 'address'];
		$parts = [];
		foreach ($orderedKeys as $key) {
			if (!array_key_exists($key, $value)) continue;
			foreach ($extractAddressParts($value[$key]) as $part) {
				$parts[] = $part;
			}
		}
		if (!count($parts)) {
			foreach ($value as $item) {
				foreach ($extractAddressParts($item) as $part) {
					$parts[] = $part;
				}
			}
		}
		return $uniqueValues($parts);
	}

	if ($value instanceof WireData && method_exists($value, 'getArray')) {
		$arrayValue = $value->getArray();
		if (is_array($arrayValue)) return $extractAddressParts($arrayValue);
	}

	if ($value instanceof WireArray || $value instanceof Traversable) {
		$parts = [];
		foreach ($value as $item) {
			foreach ($extractAddressParts($item) as $part) {
				$parts[] = $part;
			}
		}
		return $uniqueValues($parts);
	}

	if (is_object($value)) {
		$props = get_object_vars($value);
		if (count($props)) return $extractAddressParts($props);
		return $uniqueValues($extractTextValues($value));
	}

	return [];
};

$resolvePlaceLocalityViaYandex = static function(string $title, string $region = '', string $address = '') use ($config, $normalizeDisplayText, $extractLocalityFromText): string {
	static $runtimeCache = [];

	$title = $normalizeDisplayText($title);
	$region = $normalizeDisplayText($region);
	$address = $normalizeDisplayText($address);

	$queryParts = array_values(array_filter([$address, $title, $region], static fn(string $item): bool => trim($item) !== ''));
	$query = count($queryParts) ? implode(', ', $queryParts) : $title;
	if ($query === '') return '';

	$cacheKey = sha1($query);
	if (array_key_exists($cacheKey, $runtimeCache)) return (string) $runtimeCache[$cacheKey];

	$cacheDir = rtrim((string) $config->paths->cache, '/') . '/place-locality';
	$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
	$cacheTtl = 60 * 60 * 24 * 60;

	if (is_file($cacheFile) && filemtime($cacheFile) >= (time() - $cacheTtl)) {
		$cached = json_decode((string) @file_get_contents($cacheFile), true);
		$city = is_array($cached) ? $normalizeDisplayText((string) ($cached['city'] ?? '')) : '';
		$runtimeCache[$cacheKey] = $city;
		return $city;
	}

	$context = stream_context_create([
		'http' => [
			'method' => 'GET',
			'timeout' => 1.5,
			'header' => "User-Agent: Mozilla/5.0\r\nAccept-Language: ru,en;q=0.8\r\n",
		],
	]);
	$html = @file_get_contents('https://yandex.ru/maps/?text=' . rawurlencode($query), false, $context);
	if (!is_string($html) || trim($html) === '') {
		$runtimeCache[$cacheKey] = '';
		return '';
	}

	$candidates = [];
	foreach ([
		'/<meta\s+name=["\']description["\'][^>]*content=["\']([^"\']+)["\']/iu',
		'/<meta\s+property=["\']og:description["\'][^>]*content=["\']([^"\']+)["\']/iu',
		'/<meta\s+itemprop=["\']description["\'][^>]*content=["\']([^"\']+)["\']/iu',
		'/<div[^>]*class=["\'][^"\']*toponym-card-title-view__description[^"\']*["\'][^>]*>(.*?)<\/div>/iu',
	] as $pattern) {
		if (preg_match($pattern, $html, $matches) !== 1) continue;
		$candidate = $normalizeDisplayText(strip_tags(html_entity_decode((string) ($matches[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
		if ($candidate !== '') $candidates[] = $candidate;
	}

	$city = '';
	foreach ($candidates as $candidate) {
		$city = $extractLocalityFromText($candidate);
		if ($city !== '') break;
	}

	if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
	@file_put_contents($cacheFile, json_encode([
		'city' => $city,
		'query' => $query,
		'fetched_at' => time(),
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

	$runtimeCache[$cacheKey] = $city;
	return $city;
};

$defaultPlaceImage = $config->urls->templates . 'assets/image1.png';

$placeTitle = $normalizeDisplayText((string) $page->title);
if ($placeTitle === '') $placeTitle = 'Место';

$placeRegion = $getFirstText($page, ['place_region', 'region']);
$placeSummary = $getFirstText($page, ['place_summary', 'summary']);

$placeCity = $getFirstText($page, ['place_city', 'city']);
if ($placeCity === '') {
	foreach (['place_address', 'address', 'place_location', 'location', 'where'] as $addressFieldName) {
		if (!$page->hasField($addressFieldName)) continue;
		$rawAddressValue = $page->getUnformatted($addressFieldName);
		$placeCity = $getComboValue($rawAddressValue, ['city', 'i2']);
		if ($placeCity !== '') break;
		foreach ($extractAddressParts($rawAddressValue) as $addressPart) {
			$placeCity = $extractLocalityFromText((string) $addressPart);
			if ($placeCity !== '') break 2;
		}
		if ($placeCity !== '') break;
	}
}

$placeContentRaw = '';
if ($page->hasField('place_content')) $placeContentRaw = trim((string) $page->getUnformatted('place_content'));
if ($placeContentRaw === '' && $page->hasField('content')) $placeContentRaw = trim((string) $page->getUnformatted('content'));

$placeCategories = [];
foreach (['place_category', 'place_categories', 'category', 'categories', 'place_type', 'place_topic', 'topic'] as $categoryFieldName) {
	if (!$page->hasField($categoryFieldName)) continue;
	$rawCategoryValue = $page->getUnformatted($categoryFieldName);
	foreach ($extractTextValues($rawCategoryValue) as $categoryLabel) {
		$placeCategories[] = $categoryLabel;
	}
}
$placeCategories = $uniqueValues($placeCategories);
$placeCategoryLabel = count($placeCategories) ? implode(', ', $placeCategories) : 'Не указана';

$placeAddress = '';
foreach (['place_address', 'address', 'place_location', 'location', 'where'] as $addressFieldName) {
	if (!$page->hasField($addressFieldName)) continue;
	$parts = $extractAddressParts($page->getUnformatted($addressFieldName));
	if (!count($parts)) continue;
	$placeAddress = implode(', ', $parts);
	break;
}

if ($placeCity === '' && $placeAddress !== '') {
	$placeCity = $extractLocalityFromText($placeAddress);
}
if ($placeCity === '') {
	$placeCity = $resolvePlaceLocalityViaYandex($placeTitle, $placeRegion, $placeAddress);
}
$placeDisplayCity = $placeCity;

$placeTitleLookup = $normalizeLookupText($placeTitle);

$findImageByTitleInRegionCards = static function(string $titleLookup) use ($pages, $normalizeLookupText, $getImageUrlFromValue): string {
	if ($titleLookup === '' || !($pages instanceof Pages)) return '';
		$regionPages = $pages->find('template=region, include=all, status<8192, limit=250');
	foreach ($regionPages as $regionPage) {
		if (!$regionPage instanceof Page) continue;
		if (!$regionPage->hasField('region_places_cards') || !$regionPage->region_places_cards->count()) continue;
		foreach ($regionPage->region_places_cards as $card) {
			$cardTitle = $card->hasField('region_place_title') ? (string) $card->getUnformatted('region_place_title') : '';
			if ($normalizeLookupText($cardTitle) !== $titleLookup) continue;
			if (!$card->hasField('region_place_image')) continue;
			$imageUrl = $getImageUrlFromValue($card->getUnformatted('region_place_image'));
			if ($imageUrl !== '') return $imageUrl;
		}
	}
	return '';
};

$findImageByTitleInHomeCards = static function(string $titleLookup) use ($pages, $normalizeLookupText, $getImageUrlFromValue): string {
	if ($titleLookup === '' || !($pages instanceof Pages)) return '';
	$homePage = $pages->get('/');
	if (!$homePage || !$homePage->id) return '';
	if (!$homePage->hasField('dagestan_places_cards') || !$homePage->dagestan_places_cards->count()) return '';
	foreach ($homePage->dagestan_places_cards as $card) {
		$cardTitle = $card->hasField('dagestan_place_title') ? (string) $card->getUnformatted('dagestan_place_title') : '';
		if ($normalizeLookupText($cardTitle) !== $titleLookup) continue;
		if (!$card->hasField('dagestan_place_image')) continue;
		$imageUrl = $getImageUrlFromValue($card->getUnformatted('dagestan_place_image'));
		if ($imageUrl !== '') return $imageUrl;
	}
	return '';
};

$placeImageUrl = $getFirstImageUrlFromPage($page, ['place_cover_image', 'place_image', 'images']);
if ($placeImageUrl === '') {
	$fallbackPageImages = $getFallbackImageUrlsFromPageFiles($page);
	if (count($fallbackPageImages)) $placeImageUrl = trim((string) $fallbackPageImages[0]);
}
if ($placeImageUrl === '') {
	$coverFromRequest = $sanitizeLocalImageUrl((string) $input->get('cover'));
	if ($coverFromRequest !== '') $placeImageUrl = $coverFromRequest;
}
if ($placeImageUrl === '' && $placeTitleLookup !== '') {
	$placeImageUrl = $findImageByTitleInRegionCards($placeTitleLookup);
}
if ($placeImageUrl === '' && $placeTitleLookup !== '') {
	$placeImageUrl = $findImageByTitleInHomeCards($placeTitleLookup);
}
if ($placeImageUrl === '') $placeImageUrl = $defaultPlaceImage;

$placeGalleryImages = [];
$placeGalleryKeys = [];
$pushGalleryImage = static function(string $imageUrl) use (&$placeGalleryImages, &$placeGalleryKeys): void {
	$imageUrl = trim($imageUrl);
	if ($imageUrl === '' || isset($placeGalleryKeys[$imageUrl])) return;
	$placeGalleryKeys[$imageUrl] = true;
	$placeGalleryImages[] = $imageUrl;
};

foreach (['place_cover_image', 'place_image', 'place_gallery', 'gallery', 'images'] as $imageFieldName) {
	if (!$page->hasField($imageFieldName)) continue;
	foreach ($getImageUrlsFromValue($page->getUnformatted($imageFieldName)) as $galleryImageUrl) {
		$pushGalleryImage((string) $galleryImageUrl);
	}
}

if (!count($placeGalleryImages)) {
	foreach ($getFallbackImageUrlsFromPageFiles($page) as $galleryImageUrl) {
		$pushGalleryImage((string) $galleryImageUrl);
	}
}

if (!count($placeGalleryImages)) {
	$coverFromRequest = $sanitizeLocalImageUrl((string) $input->get('cover'));
	if ($coverFromRequest !== '') $pushGalleryImage($coverFromRequest);
}

$contentParagraphs = $extractParagraphs($placeContentRaw);

$placesPage = $pages->get('/places/');
$defaultBackUrl = ($placesPage && $placesPage->id) ? (string) $placesPage->url : '/regions/';
$sourceFromRequest = trim((string) $input->get('from'));
$backFromRequest = $sanitizeLocalUrl(trim((string) $input->get('back')), '');

if ($sourceFromRequest === 'home') {
	$defaultBackUrl = '/';
} elseif ($sourceFromRequest === 'regions') {
	$defaultBackUrl = '/regions/';
}

$backUrl = $backFromRequest !== '' ? $backFromRequest : $defaultBackUrl;

$detailRootLabel = 'Места';
$detailRootUrl = ($placesPage && $placesPage->id) ? (string) $placesPage->url : '/places/';
if ($backUrl === '/' || $sourceFromRequest === 'home') {
	$detailRootLabel = 'Главная';
	$detailRootUrl = '/';
} elseif (strpos($backUrl, '/regions/') === 0 || $sourceFromRequest === 'regions') {
	$detailRootLabel = 'Регионы';
	$detailRootUrl = '/regions/';
}

$middleLabel = $placeRegion;
$middleUrl = '';
if ($middleLabel !== '') {
	$middleUrl = $backUrl;
	if ($middleUrl === '' || $middleUrl === $detailRootUrl) {
		$middleUrl = '/regions/';
	}
}
$addressLabel = $placeAddress;
if ($addressLabel === '' && $placeCity !== '') {
	$addressLabel = $placeCity;
}
if ($addressLabel === '' && $placeRegion !== '') {
	$addressLabel = $placeRegion;
}

$mapQueryParts = array_values(array_filter([$placeAddress, $placeTitle, $placeRegion], static fn(string $item): bool => trim($item) !== ''));
$mapQuery = count($mapQueryParts) ? implode(', ', $mapQueryParts) : 'Северо-Кавказский федеральный округ';
$placeMapWidgetUrl = 'https://yandex.ru/map-widget/v1/?z=14&text=' . rawurlencode($mapQuery);
?>

<div id="content" class="articles-page place-page">
	<section class="section section--place-breadcrumb">
		<div class="container">
			<nav class="guides-breadcrumb" aria-label="Хлебные крошки">
				<a href="<?php echo $sanitizer->entities($detailRootUrl); ?>"><?php echo $sanitizer->entities($detailRootLabel); ?></a>
				<?php if ($middleLabel !== ''): ?>
					<span aria-hidden="true">›</span>
					<?php if ($middleUrl !== ''): ?>
						<a href="<?php echo $sanitizer->entities($middleUrl); ?>"><?php echo $sanitizer->entities($middleLabel); ?></a>
					<?php else: ?>
						<span><?php echo $sanitizer->entities($middleLabel); ?></span>
					<?php endif; ?>
				<?php endif; ?>
				<span aria-hidden="true">›</span>
				<span><?php echo $sanitizer->entities($placeTitle); ?></span>
			</nav>
		</div>
	</section>

	<section class="article-detail-section place-detail-section">
		<div class="container">
			<div class="article-detail-cover" style="background-image: url('<?php echo htmlspecialchars($placeImageUrl, ENT_QUOTES, 'UTF-8'); ?>');"></div>
			<h1 class="article-detail-title"><?php echo $sanitizer->entities($placeTitle); ?></h1>
			<?php if ($placeDisplayCity !== ''): ?>
				<p class="place-detail-city"><?php echo $sanitizer->entities($placeDisplayCity); ?></p>
			<?php endif; ?>
			<?php if ($placeSummary !== ''): ?>
				<p class="place-detail-summary"><?php echo $sanitizer->entities($placeSummary); ?></p>
			<?php endif; ?>

			<!-- <div class="place-detail-meta">
				<div class="place-detail-meta-item">
					<span class="place-detail-meta-label">Категория достопримечательности</span>
					<strong class="place-detail-meta-value"><?php echo $sanitizer->entities($placeCategoryLabel); ?></strong>
				</div>
				<?php if ($placeRegion !== ''): ?>
					<div class="place-detail-meta-item">
						<span class="place-detail-meta-label">Регион</span>
						<strong class="place-detail-meta-value"><?php echo $sanitizer->entities($placeRegion); ?></strong>
					</div>
				<?php endif; ?>
				<?php if ($placeAddress !== ''): ?>
					<div class="place-detail-meta-item">
						<span class="place-detail-meta-label">Адрес</span>
						<strong class="place-detail-meta-value"><?php echo $sanitizer->entities($placeAddress); ?></strong>
					</div>
				<?php endif; ?>
			</div> -->

			<?php if (count($contentParagraphs) > 3): ?>
				<?php
				$middle = (int) ceil(count($contentParagraphs) / 2);
				$leftParagraphs = array_slice($contentParagraphs, 0, $middle);
				$rightParagraphs = array_slice($contentParagraphs, $middle);
				?>
				<div class="article-detail-columns">
					<div class="article-detail-column">
						<?php foreach ($leftParagraphs as $paragraph): ?>
							<p><?php echo $sanitizer->entities((string) $paragraph); ?></p>
						<?php endforeach; ?>
					</div>
					<div class="article-detail-column">
						<?php foreach ($rightParagraphs as $paragraph): ?>
							<p><?php echo $sanitizer->entities((string) $paragraph); ?></p>
						<?php endforeach; ?>
					</div>
				</div>
			<?php elseif (count($contentParagraphs)): ?>
				<div class="article-detail-richtext">
					<?php foreach ($contentParagraphs as $paragraph): ?>
						<p><?php echo $sanitizer->entities((string) $paragraph); ?></p>
					<?php endforeach; ?>
				</div>
				<?php elseif ($placeSummary === ''): ?>
					<div class="article-detail-richtext">
						<p>Описание места пока не добавлено.</p>
					</div>
				<?php endif; ?>

				<?php if (count($placeGalleryImages)): ?>
					<div class="place-detail-gallery-section">
						<h2 class="tour-section-title place-detail-subtitle">Фотографии</h2>
						<div class="place-detail-gallery-grid" data-place-gallery>
							<?php foreach ($placeGalleryImages as $index => $galleryImageUrl): ?>
								<?php
								$galleryAlt = $placeTitle . ' — фото ' . ((int) $index + 1);
								$gallerySrc = htmlspecialchars((string) $galleryImageUrl, ENT_QUOTES, 'UTF-8');
								?>
								<figure class="place-detail-gallery-item">
									<button
										class="place-detail-gallery-open"
										type="button"
										data-place-gallery-item
										data-gallery-index="<?php echo (int) $index; ?>"
										data-gallery-src="<?php echo $gallerySrc; ?>"
										data-gallery-alt="<?php echo $sanitizer->entities($galleryAlt); ?>"
										aria-label="<?php echo $sanitizer->entities('Открыть ' . $galleryAlt); ?>"
									>
										<img src="<?php echo $gallerySrc; ?>" alt="<?php echo $sanitizer->entities($galleryAlt); ?>" loading="lazy" />
									</button>
								</figure>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="hotel-gallery-lightbox" data-place-gallery-modal hidden>
						<div class="hotel-gallery-lightbox-backdrop" data-gallery-close="backdrop"></div>
						<div class="hotel-gallery-lightbox-dialog" role="dialog" aria-modal="true" aria-label="Фотографии места">
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

			<div class="hotel-location-section place-location-section">
				<div class="hotel-location-card">
					<h2 class="tour-section-title">Где находится</h2>
					<?php if ($addressLabel !== ''): ?>
						<p class="hotel-location-address"><?php echo $sanitizer->entities($addressLabel); ?></p>
					<?php endif; ?>
					<div class="hotel-location-map-wrap">
						<iframe
							class="hotel-location-map"
							src="<?php echo htmlspecialchars($placeMapWidgetUrl, ENT_QUOTES, 'UTF-8'); ?>"
							loading="lazy"
							allowfullscreen
							referrerpolicy="no-referrer-when-downgrade"
							title="Карта места <?php echo $sanitizer->entities($placeTitle); ?>"
						></iframe>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
