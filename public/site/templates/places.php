<?php namespace ProcessWire;

$getImageUrlFromValue = static function($imageValue): string {
	if ($imageValue instanceof Pageimage) return $imageValue->url;
	if ($imageValue instanceof Pageimages && $imageValue->count()) return $imageValue->first()->url;
	return '';
};

$toLower = static function(string $value): string {
	$value = trim($value);
	return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
};

$normalizeWhitespace = static function(string $value): string {
	$value = trim(str_replace(["\r", "\n"], ' ', $value));
	return preg_replace('/\s+/u', ' ', $value) ?? $value;
};

$truncateText = static function(string $value, int $length = 190): string {
	$value = trim(strip_tags($value));
	if ($value === '') return '';
	$currentLength = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	if ($currentLength <= $length) return $value;
	return (function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length)) . '...';
};

$getFirstText = static function(Page $item, array $fieldNames) use ($normalizeWhitespace): string {
	foreach ($fieldNames as $fieldName) {
		if (!$item->hasField($fieldName)) continue;
		$value = $normalizeWhitespace((string) $item->$fieldName);
		if ($value !== '') return $value;
	}
	return '';
};

$defaultPlaceImage = $config->urls->templates . 'assets/image1.png';
$placesCatalog = [];
$regionOptionsMap = [];

if (isset($pages) && $pages instanceof Pages) {
	$placePages = $pages->find('template=place, include=all, sort=title, limit=1000');
	foreach ($placePages as $placePage) {
		if (!$placePage instanceof Page) continue;
		$title = trim((string) $placePage->title);
		if ($title === '') continue;

		$region = $getFirstText($placePage, ['place_region', 'region']);
		$summaryRaw = $getFirstText($placePage, ['place_summary', 'summary', 'place_content', 'content']);
		$summary = $truncateText($summaryRaw, 180);
		$imageUrl = '';
		foreach (['place_cover_image', 'images'] as $imageField) {
			if (!$placePage->hasField($imageField)) continue;
			$imageUrl = $getImageUrlFromValue($placePage->getUnformatted($imageField));
			if ($imageUrl !== '') break;
		}
		if ($imageUrl === '') $imageUrl = $defaultPlaceImage;

		if ($region !== '') {
			$regionOptionsMap[$toLower($region)] = $region;
		}

		$placesCatalog[] = [
			'title' => $title,
			'region' => $region,
			'summary' => $summary,
			'image' => $imageUrl,
			'url' => (string) $placePage->url,
		];
	}
}

$regionOptions = array_values($regionOptionsMap);
sort($regionOptions, SORT_NATURAL | SORT_FLAG_CASE);

$searchQuery = trim((string) $input->get('q'));
$searchRegion = trim((string) $input->get('region'));
$queryNeedle = $toLower($searchQuery);
$regionNeedle = $toLower($searchRegion);

$filteredPlaces = array_values(array_filter($placesCatalog, static function(array $place) use ($queryNeedle, $regionNeedle, $toLower): bool {
	$title = trim((string) ($place['title'] ?? ''));
	$region = trim((string) ($place['region'] ?? ''));
	$summary = trim((string) ($place['summary'] ?? ''));
	if ($regionNeedle !== '') {
		$haystackRegion = $toLower($region);
		if ($haystackRegion === '' || strpos($haystackRegion, $regionNeedle) === false) return false;
	}
	if ($queryNeedle !== '') {
		$haystack = $toLower($title . ' ' . $region . ' ' . $summary);
		if (strpos($haystack, $queryNeedle) === false) return false;
	}
	return true;
}));

$perPage = 12;
$currentPage = max(1, (int) $input->get('page'));
$totalPlaces = count($filteredPlaces);
$totalPages = $totalPlaces > 0 ? (int) ceil($totalPlaces / $perPage) : 1;
if ($currentPage > $totalPages) $currentPage = $totalPages;
$offset = ($currentPage - 1) * $perPage;
$visiblePlaces = array_slice($filteredPlaces, $offset, $perPage);

$buildPageUrl = static function(int $pageNumber) use ($page, $searchQuery, $searchRegion): string {
	$params = [];
	if ($searchQuery !== '') $params['q'] = $searchQuery;
	if ($searchRegion !== '') $params['region'] = $searchRegion;
	if ($pageNumber > 1) $params['page'] = (string) $pageNumber;
	$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
	return $page->url . ($query !== '' ? '?' . $query : '');
};
?>

<div id="content" class="places-page">
	<section class="section section--hotels-results">
		<div class="container">
			<h1 class="section-title">Места СКФО</h1>

			<form class="hero-search hotels-search" action="<?php echo $sanitizer->entities($page->url); ?>" method="get">
				<div class="hero-search-fields hotels-search-fields">
					<label class="hero-field hero-field-where<?php echo $searchQuery !== '' ? ' is-filled' : ''; ?>">
						<span class="sr-only">Поиск места</span>
						<input type="text" name="q" placeholder="Название или описание" value="<?php echo $sanitizer->entities($searchQuery); ?>" />
						<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
					</label>
					<label class="hero-field<?php echo $searchRegion !== '' ? ' is-filled' : ''; ?>">
						<span class="sr-only">Регион</span>
						<input type="text" name="region" placeholder="Регион" list="places-region-list" value="<?php echo $sanitizer->entities($searchRegion); ?>" />
						<img src="<?php echo $config->urls->templates; ?>assets/icons/location_on.svg" alt="" aria-hidden="true" />
					</label>
				</div>
				<datalist id="places-region-list">
					<?php foreach ($regionOptions as $regionOption): ?>
						<option value="<?php echo $sanitizer->entities($regionOption); ?>"></option>
					<?php endforeach; ?>
				</datalist>
				<button class="search-btn" type="submit">Найти места</button>
			</form>

			<?php if (count($visiblePlaces)): ?>
				<div class="hotels-grid">
					<?php foreach ($visiblePlaces as $place): ?>
						<?php
						$title = trim((string) ($place['title'] ?? ''));
						$region = trim((string) ($place['region'] ?? ''));
						$summary = trim((string) ($place['summary'] ?? ''));
						$imageUrl = trim((string) ($place['image'] ?? ''));
						$placeUrl = trim((string) ($place['url'] ?? ''));
						if ($placeUrl === '') $placeUrl = $page->url;
						?>
						<article class="hotel-card">
							<div class="hotel-card-media"<?php echo $imageUrl !== '' ? " style=\"background-image: url('" . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . "');\"" : ''; ?>></div>
							<h2 class="hotel-card-title"><?php echo $sanitizer->entities($title); ?></h2>
							<?php if ($region !== ''): ?>
								<p class="hotel-card-location"><?php echo $sanitizer->entities($region); ?></p>
							<?php endif; ?>
							<?php if ($summary !== ''): ?>
								<p class="hotel-card-location"><?php echo $sanitizer->entities($summary); ?></p>
							<?php endif; ?>
							<div class="hotel-card-footer">
								<a class="hotel-card-btn" href="<?php echo $sanitizer->entities($placeUrl); ?>">Подробнее</a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>

				<?php if ($totalPages > 1): ?>
					<nav class="hotels-pagination" aria-label="Страницы мест">
						<?php for ($i = 1; $i <= $totalPages; $i++): ?>
							<a class="hotels-pagination-link<?php echo $i === $currentPage ? ' is-active' : ''; ?>" href="<?php echo $sanitizer->entities($buildPageUrl($i)); ?>">
								<?php echo $i; ?>
							</a>
						<?php endfor; ?>
					</nav>
				<?php endif; ?>
			<?php else: ?>
				<div class="hotels-empty">
					Места по текущему фильтру не найдены.
				</div>
			<?php endif; ?>
		</div>
	</section>
</div>
