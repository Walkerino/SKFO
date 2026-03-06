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

$placeTitle = $normalizeDisplayText((string) $page->title);
if ($placeTitle === '') $placeTitle = 'Место';

$placeRegion = $getFirstText($page, ['place_region', 'region']);
$placeSummary = $getFirstText($page, ['place_summary', 'summary']);
$placeContentRaw = '';
if ($page->hasField('place_content')) $placeContentRaw = trim((string) $page->getUnformatted('place_content'));
if ($placeContentRaw === '' && $page->hasField('content')) $placeContentRaw = trim((string) $page->getUnformatted('content'));

$placeTitleLookup = $normalizeLookupText($placeTitle);

$findImageByTitleInRegionCards = static function(string $titleLookup) use ($pages, $normalizeLookupText, $getImageUrlFromValue): string {
	if ($titleLookup === '' || !($pages instanceof Pages)) return '';
	$regionPages = $pages->find('template=region, include=all, limit=250');
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
if ($placeImageUrl === '') $placeImageUrl = $config->urls->templates . 'assets/image1.png';

$contentParagraphs = $extractParagraphs($placeContentRaw);
if (!count($contentParagraphs) && $placeSummary !== '') {
	$contentParagraphs[] = $placeSummary;
}

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
?>

<div id="content" class="articles-page place-page">
	<section class="article-hero-strip">
		<div class="container article-breadcrumb-row">
			<a class="article-back-btn" href="<?php echo $sanitizer->entities($backUrl); ?>" aria-label="Назад к предыдущей странице"></a>
			<div class="article-breadcrumb">
				<a href="<?php echo $sanitizer->entities($detailRootUrl); ?>"><?php echo $sanitizer->entities($detailRootLabel); ?></a>
				<?php if ($middleLabel !== ''): ?>
					<span class="article-breadcrumb-sep">-&gt;</span>
					<span><?php echo $sanitizer->entities($middleLabel); ?></span>
				<?php endif; ?>
				<span class="article-breadcrumb-sep">-&gt;</span>
				<span class="article-breadcrumb-current"><?php echo $sanitizer->entities($placeTitle); ?></span>
			</div>
		</div>
	</section>

	<section class="article-detail-section">
		<div class="container">
			<div class="article-detail-cover" style="background-image: url('<?php echo htmlspecialchars($placeImageUrl, ENT_QUOTES, 'UTF-8'); ?>');"></div>
			<h1 class="article-detail-title"><?php echo $sanitizer->entities($placeTitle); ?></h1>
			<?php if ($placeRegion !== ''): ?>
				<p class="article-detail-date"><?php echo $sanitizer->entities('Регион: ' . $placeRegion); ?></p>
			<?php endif; ?>

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
			<?php else: ?>
				<div class="article-detail-richtext">
					<p>Описание места пока не добавлено.</p>
				</div>
			<?php endif; ?>
		</div>
	</section>
</div>
