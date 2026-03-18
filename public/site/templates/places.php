<?php namespace ProcessWire;

$getImageUrlFromValue = static function($imageValue): string {
	if ($imageValue instanceof Pageimage) return (string) $imageValue->url;
	if ($imageValue instanceof Pageimages && $imageValue->count()) return (string) $imageValue->first()->url;
	return '';
};

$toLower = static function(string $value): string {
	$value = trim($value);
	return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
};

$normalizeWhitespace = static function(string $value): string {
	$decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$decoded = trim(str_replace(["\r", "\n"], ' ', $decoded));
	return preg_replace('/\s+/u', ' ', $decoded) ?? $decoded;
};

$normalizeLookup = static function(string $value) use ($toLower): string {
	$value = str_replace('ё', 'е', $toLower($value));
	$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
	return trim($value);
};

$truncateText = static function(string $value, int $length = 190): string {
	$value = trim(strip_tags($value));
	if ($value === '') return '';
	$currentLength = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	if ($currentLength <= $length) return $value;
	$cut = function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
	return rtrim($cut) . '...';
};

$getFirstText = static function(Page $item, array $fieldNames) use ($normalizeWhitespace): string {
	foreach ($fieldNames as $fieldName) {
		if (!$item->hasField($fieldName)) continue;
		$value = $normalizeWhitespace((string) $item->getUnformatted($fieldName));
		if ($value !== '') return $value;
	}
	return '';
};

$extractTextValues = null;
$extractTextValues = static function($value) use (&$extractTextValues, $normalizeWhitespace): array {
	$values = [];

	if ($value === null) return $values;

	if (is_string($value) || is_numeric($value)) {
		$text = $normalizeWhitespace((string) $value);
		if ($text === '') return $values;
		$parts = preg_split('/[\n\r,;|]+/u', $text) ?: [];
		foreach ($parts as $part) {
			$label = $normalizeWhitespace((string) $part);
			if ($label !== '') $values[] = $label;
		}
		if (!count($values) && $text !== '') $values[] = $text;
		return $values;
	}

	if ($value instanceof SelectableOption) {
		$label = $normalizeWhitespace((string) $value->title);
		if ($label !== '') $values[] = $label;
		return $values;
	}

	if ($value instanceof Page) {
		$label = $normalizeWhitespace((string) $value->title);
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
			$text = $normalizeWhitespace((string) $value);
			if ($text !== '') $values[] = $text;
		}
	}

	return $values;
};

$getCategoryValuesFromPage = static function(Page $item) use ($extractTextValues, $normalizeLookup): array {
	$fieldNames = [
		'place_category',
		'place_categories',
		'category',
		'categories',
		'place_type',
		'place_topic',
		'topic',
	];

	$categories = [];
	$keys = [];
	foreach ($fieldNames as $fieldName) {
		if (!$item->hasField($fieldName)) continue;
		$rawValue = $item->getUnformatted($fieldName);
		foreach ($extractTextValues($rawValue) as $label) {
			$key = $normalizeLookup($label);
			if ($key === '' || isset($keys[$key])) continue;
			$keys[$key] = true;
			$categories[] = $label;
		}
	}

	return $categories;
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

$getFirstImageUrlFromPage = static function(Page $item, array $fieldNames) use ($getImageUrlFromValue, $getFallbackImageUrlsFromPageFiles): string {
	foreach ($fieldNames as $fieldName) {
		if (!$item->hasField($fieldName)) continue;
		$imageUrl = $getImageUrlFromValue($item->getUnformatted($fieldName));
		if ($imageUrl !== '') return $imageUrl;
	}

	$fallbackImages = $getFallbackImageUrlsFromPageFiles($item);
	if (count($fallbackImages)) return trim((string) $fallbackImages[0]);

	return '';
};

$defaultPlaceImage = $config->urls->templates . 'assets/image1.png';
$defaultCategories = [
	'Альплагеря',
	'Концертные площадки',
	'Музеи',
	'Наука',
	'Памятники',
	'Памятники архитектуры',
	'Парки',
	'Природа',
	'Храмы и церкви',
];

$placesMap = [];
$placeUrlByTitleLookup = [];

$mergePlaceCard = static function(array $incoming) use (&$placesMap, $normalizeWhitespace, $normalizeLookup, $defaultPlaceImage): void {
	$title = $normalizeWhitespace((string) ($incoming['title'] ?? ''));
	if ($title === '') return;

	$key = $normalizeLookup($title);
	if ($key === '') return;

	$region = $normalizeWhitespace((string) ($incoming['region'] ?? ''));
	$summary = $normalizeWhitespace((string) ($incoming['summary'] ?? ''));
	$image = trim((string) ($incoming['image'] ?? ''));
	$url = trim((string) ($incoming['url'] ?? ''));
	$categories = $incoming['categories'] ?? [];
	if (!is_array($categories)) $categories = [];
	if (!count($categories)) $categories = ['Природа'];

	$categoryMap = [];
	foreach ($categories as $categoryLabel) {
		$label = $normalizeWhitespace((string) $categoryLabel);
		$categoryKey = $normalizeLookup($label);
		if ($label === '' || $categoryKey === '') continue;
		$categoryMap[$categoryKey] = $label;
	}
	if (!count($categoryMap)) {
		$categoryMap[$normalizeLookup('Природа')] = 'Природа';
	}

	$imageIsDefault = $image === '' || $image === $defaultPlaceImage;
	if ($image === '') $image = $defaultPlaceImage;

	if (!isset($placesMap[$key])) {
		$placesMap[$key] = [
			'title' => $title,
			'region' => $region,
			'summary' => $summary,
			'image' => $image,
			'image_is_default' => $imageIsDefault,
			'url' => $url,
			'category_map' => $categoryMap,
		];
		return;
	}

	$current = $placesMap[$key];
	if ($current['region'] === '' && $region !== '') $current['region'] = $region;
	if ($current['summary'] === '' && $summary !== '') $current['summary'] = $summary;
	if ($current['url'] === '' && $url !== '') $current['url'] = $url;

	$currentImageIsDefault = (bool) ($current['image_is_default'] ?? false);
	if ($currentImageIsDefault && !$imageIsDefault) {
		$current['image'] = $image;
		$current['image_is_default'] = false;
	}

	$currentCategoryMap = $current['category_map'] ?? [];
	if (!is_array($currentCategoryMap)) $currentCategoryMap = [];
	foreach ($categoryMap as $categoryKey => $categoryLabel) {
		$currentCategoryMap[$categoryKey] = $categoryLabel;
	}
	$current['category_map'] = $currentCategoryMap;

	$placesMap[$key] = $current;
};

if (isset($pages) && $pages instanceof Pages) {
	$placePages = $pages->find('template=place, include=all, check_access=0, sort=title, limit=3000');
	foreach ($placePages as $placePage) {
		if (!$placePage instanceof Page) continue;
		$title = $normalizeWhitespace((string) $placePage->title);
		if ($title === '') continue;

		$placeTitleKey = $normalizeLookup($title);
		if ($placeTitleKey !== '' && !isset($placeUrlByTitleLookup[$placeTitleKey])) {
			$placeUrlByTitleLookup[$placeTitleKey] = trim((string) $placePage->url);
		}

		$summaryRaw = $getFirstText($placePage, ['place_summary', 'summary', 'place_content', 'content']);
		$imageUrl = $getFirstImageUrlFromPage($placePage, ['place_cover_image', 'place_image', 'images']);

		$mergePlaceCard([
			'title' => $title,
			'region' => $getFirstText($placePage, ['place_region', 'region']),
			'summary' => $truncateText($summaryRaw, 180),
			'image' => $imageUrl !== '' ? $imageUrl : $defaultPlaceImage,
			'url' => trim((string) $placePage->url),
			'categories' => $getCategoryValuesFromPage($placePage),
		]);
	}

	$regionPages = $pages->find('template=region, include=all, check_access=0, sort=title, limit=250');
	foreach ($regionPages as $regionPage) {
		if (!$regionPage instanceof Page) continue;
		$regionTitle = $getFirstText($regionPage, ['region_card_title']);
		if ($regionTitle === '') $regionTitle = $normalizeWhitespace((string) $regionPage->title);

		if ($regionPage->hasField('region_places_cards') && $regionPage->region_places_cards->count()) {
			foreach ($regionPage->region_places_cards as $card) {
				$cardTitle = $card->hasField('region_place_title') ? $normalizeWhitespace((string) $card->getUnformatted('region_place_title')) : '';
				if ($cardTitle === '') continue;

				$cardTitleKey = $normalizeLookup($cardTitle);
				$linkedPlaceUrl = $cardTitleKey !== '' ? trim((string) ($placeUrlByTitleLookup[$cardTitleKey] ?? '')) : '';
				$cardSummary = $card->hasField('region_place_text') ? $normalizeWhitespace((string) $card->getUnformatted('region_place_text')) : '';
				$cardImage = $card->hasField('region_place_image') ? $getImageUrlFromValue($card->getUnformatted('region_place_image')) : '';

				$mergePlaceCard([
					'title' => $cardTitle,
					'region' => $regionTitle,
					'summary' => $truncateText($cardSummary, 180),
					'image' => $cardImage !== '' ? $cardImage : $defaultPlaceImage,
					'url' => $linkedPlaceUrl,
					'categories' => ['Природа'],
				]);
			}
		}
	}

	$homePage = $pages->get('/');
	if ($homePage instanceof Page && $homePage->id && $homePage->hasField('dagestan_places_cards') && $homePage->dagestan_places_cards->count()) {
		foreach ($homePage->dagestan_places_cards as $card) {
			$cardTitle = $card->hasField('dagestan_place_title') ? $normalizeWhitespace((string) $card->getUnformatted('dagestan_place_title')) : '';
			if ($cardTitle === '') continue;

			$cardTitleKey = $normalizeLookup($cardTitle);
			$linkedPlaceUrl = $cardTitleKey !== '' ? trim((string) ($placeUrlByTitleLookup[$cardTitleKey] ?? '')) : '';
			$cardSummary = $card->hasField('dagestan_place_text') ? $normalizeWhitespace((string) $card->getUnformatted('dagestan_place_text')) : '';
			$cardImage = $card->hasField('dagestan_place_image') ? $getImageUrlFromValue($card->getUnformatted('dagestan_place_image')) : '';

			$mergePlaceCard([
				'title' => $cardTitle,
				'region' => 'Республика Дагестан',
				'summary' => $truncateText($cardSummary, 180),
				'image' => $cardImage !== '' ? $cardImage : $defaultPlaceImage,
				'url' => $linkedPlaceUrl,
				'categories' => ['Природа'],
			]);
		}
	}
}

$placesCatalog = [];
$regionOptionsMap = [];
$foundCategoryOptionsMap = [];
foreach ($placesMap as $item) {
	$title = trim((string) ($item['title'] ?? ''));
	if ($title === '') continue;

	$region = trim((string) ($item['region'] ?? ''));
	$summary = trim((string) ($item['summary'] ?? ''));
	$image = trim((string) ($item['image'] ?? $defaultPlaceImage));
	if ($image === '') $image = $defaultPlaceImage;
	if ($image === $defaultPlaceImage) continue;

	$categoryMap = $item['category_map'] ?? [];
	if (!is_array($categoryMap) || !count($categoryMap)) $categoryMap = [$normalizeLookup('Природа') => 'Природа'];
	$categoryKeys = array_keys($categoryMap);
	$categoryValues = array_values($categoryMap);
	$primaryCategory = trim((string) ($categoryValues[0] ?? 'Природа'));

	foreach ($categoryMap as $categoryKey => $categoryLabel) {
		$foundCategoryOptionsMap[$categoryKey] = (string) $categoryLabel;
	}
	if ($region !== '') $regionOptionsMap[$normalizeLookup($region)] = $region;

	$placesCatalog[] = [
		'title' => $title,
		'region' => $region,
		'summary' => $summary,
		'category' => $primaryCategory,
		'category_keys' => array_values(array_unique($categoryKeys)),
		'image' => $image,
		'url' => trim((string) ($item['url'] ?? '')),
	];
}

usort($placesCatalog, static function(array $left, array $right): int {
	return strnatcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
});

$regionOptions = array_values($regionOptionsMap);
sort($regionOptions, SORT_NATURAL | SORT_FLAG_CASE);

$categoryOptionsMap = [];
$appendCategoryOption = static function(string $label) use (&$categoryOptionsMap, $normalizeLookup, $normalizeWhitespace): void {
	$normalized = $normalizeWhitespace($label);
	$key = $normalizeLookup($normalized);
	if ($normalized === '' || $key === '' || isset($categoryOptionsMap[$key])) return;
	$categoryOptionsMap[$key] = $normalized;
};

foreach ($defaultCategories as $categoryOption) {
	$appendCategoryOption($categoryOption);
}

$foundCategoryOptions = array_values($foundCategoryOptionsMap);
sort($foundCategoryOptions, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($foundCategoryOptions as $categoryOption) {
	$appendCategoryOption($categoryOption);
}

$categoryOptions = array_values($categoryOptionsMap);
if (!count($categoryOptions)) $categoryOptions = ['Природа'];

$searchRegion = $normalizeWhitespace(trim((string) $input->get('region')));

$selectedCategoriesRaw = $input->get('categories');
$selectedCategories = [];
if (is_array($selectedCategoriesRaw) || $selectedCategoriesRaw instanceof WireArray) {
	foreach ($selectedCategoriesRaw as $rawCategory) {
		$normalized = $normalizeWhitespace((string) $rawCategory);
		if ($normalized !== '') $selectedCategories[] = $normalized;
	}
} elseif (is_string($selectedCategoriesRaw)) {
	$parsed = preg_split('/[\n\r,;|]+/u', $selectedCategoriesRaw) ?: [];
	foreach ($parsed as $rawCategory) {
		$normalized = $normalizeWhitespace((string) $rawCategory);
		if ($normalized !== '') $selectedCategories[] = $normalized;
	}
}

$selectedCategoryMap = [];
foreach ($selectedCategories as $selectedCategory) {
	$key = $normalizeLookup($selectedCategory);
	if ($key === '' || isset($selectedCategoryMap[$key])) continue;
	$selectedCategoryMap[$key] = $selectedCategory;
}
$selectedCategories = array_values($selectedCategoryMap);

$regionNeedle = $normalizeLookup($searchRegion);
$selectedCategoryKeys = array_keys($selectedCategoryMap);

$filteredPlaces = array_values(array_filter($placesCatalog, static function(array $place) use ($regionNeedle, $selectedCategoryKeys, $normalizeLookup): bool {
	$region = trim((string) ($place['region'] ?? ''));
	if ($regionNeedle !== '') {
		$regionKey = $normalizeLookup($region);
		if ($regionKey === '' || strpos($regionKey, $regionNeedle) === false) return false;
	}

	if (count($selectedCategoryKeys)) {
		$placeCategoryKeys = $place['category_keys'] ?? [];
		if (!is_array($placeCategoryKeys) || !count(array_intersect($selectedCategoryKeys, $placeCategoryKeys))) {
			return false;
		}
	}

	return true;
}));

$buildPlacesFilterUrl = static function(string $baseUrl, string $region, array $categories): string {
	$params = [];
	if ($region !== '') $params['region'] = $region;
	if (count($categories)) $params['categories'] = $categories;
	$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
	return $baseUrl . ($query !== '' ? '?' . $query : '');
};

$currentFiltersUrl = $buildPlacesFilterUrl((string) $page->url, $searchRegion, $selectedCategories);

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

$selectedRegionLabel = $searchRegion !== '' ? $searchRegion : 'Регион';
if (count($selectedCategories) === 0) {
	$selectedCategoriesLabel = 'Категория';
} elseif (count($selectedCategories) === 1) {
	$selectedCategoriesLabel = (string) $selectedCategories[0];
} else {
	$selectedCategoriesLabel = 'Категория (' . count($selectedCategories) . ')';
}

$forumExternalUrl = 'https://club.skfo.ru';
$totalFilteredPlaces = count($filteredPlaces);
?>

<div id="content" class="places-page">
	<section class="hero places-hero">
		<div class="container hero-inner places-hero-inner">
			<h1 class="hero-title">Что посмотреть </br>на Северном Кавказе</h1>
			<div class="hero-tabs" aria-label="Разделы">
				<div class="hero-tabs-group" role="tablist">
					<span class="tab-indicator" aria-hidden="true"></span>
					<span class="tab-hover" aria-hidden="true"></span>
					<a class="hero-tab" href="/" role="tab" aria-selected="false">
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
					<a class="hero-tab" href="/guides/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/human.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Гиды</span>
					</a>
					<a class="hero-tab" href="/regions/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Регионы</span>
					</a>
					<a class="hero-tab is-active" href="/places/" role="tab" aria-selected="true">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/location_on_nav.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Места</span>
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

			<form class="places-filters" action="<?php echo $sanitizer->entities($page->url); ?>" method="get">
				<div class="places-filters-row">
					<div class="places-filters-fields">
						<div class="places-filter-dropdown" data-places-dropdown data-places-dropdown-type="single">
							<button class="places-filter-trigger" type="button" data-places-dropdown-trigger aria-expanded="false">
								<span class="places-filter-trigger-label" data-places-dropdown-label data-default-label="Регион"><?php echo $sanitizer->entities($selectedRegionLabel); ?></span>
							</button>
							<div class="places-filter-popover" data-places-dropdown-panel>
								<label class="places-filter-option">
									<input type="radio" name="region" value=""<?php echo $searchRegion === '' ? ' checked' : ''; ?> />
									<span class="places-filter-option-text">Все регионы</span>
								</label>
								<?php foreach ($regionOptions as $regionOption): ?>
									<label class="places-filter-option">
										<input type="radio" name="region" value="<?php echo $sanitizer->entities($regionOption); ?>"<?php echo $searchRegion === $regionOption ? ' checked' : ''; ?> />
										<span class="places-filter-option-text"><?php echo $sanitizer->entities($regionOption); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="places-filter-dropdown" data-places-dropdown data-places-dropdown-type="multi">
							<button class="places-filter-trigger" type="button" data-places-dropdown-trigger aria-expanded="false">
								<span class="places-filter-trigger-label" data-places-dropdown-label data-default-label="Категория"><?php echo $sanitizer->entities($selectedCategoriesLabel); ?></span>
							</button>
							<div class="places-filter-popover places-filter-popover--categories" data-places-dropdown-panel>
								<?php
								$selectedCategoryKeysMap = [];
								foreach ($selectedCategories as $selectedCategoryValue) {
									$selectedCategoryKeysMap[$normalizeLookup($selectedCategoryValue)] = true;
								}
								?>
								<?php foreach ($categoryOptions as $categoryOption): ?>
									<?php $categoryOptionKey = $normalizeLookup($categoryOption); ?>
									<label class="places-filter-option">
										<input type="checkbox" name="categories[]" value="<?php echo $sanitizer->entities($categoryOption); ?>"<?php echo isset($selectedCategoryKeysMap[$categoryOptionKey]) ? ' checked' : ''; ?> />
										<span class="places-filter-option-text"><?php echo $sanitizer->entities($categoryOption); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<button class="search-btn places-filter-apply-btn" type="submit">Применить</button>
				</div>
			</form>
		</div>
	</section>

	<section class="section section--places-catalog">
		<div class="container">
			<?php if ($totalFilteredPlaces > 0): ?>
				<div class="places-catalog-grid" data-places-grid data-places-batch="8">
					<?php foreach ($filteredPlaces as $place): ?>
						<?php
						$title = trim((string) ($place['title'] ?? ''));
						$region = trim((string) ($place['region'] ?? ''));
						$summary = trim((string) ($place['summary'] ?? ''));
						$category = trim((string) ($place['category'] ?? 'Природа'));
						$imageUrl = trim((string) ($place['image'] ?? $defaultPlaceImage));
						$placeUrl = trim((string) ($place['url'] ?? ''));
						if ($placeUrl === '') $placeUrl = $page->url;
						$placeUrl = $appendLocalQueryParams($placeUrl, [
							'from' => 'places',
							'back' => $currentFiltersUrl,
							'cover' => $imageUrl,
						]);
						$chipLabel = $category !== '' ? $category : 'Природа';
						if (function_exists('mb_strtoupper')) {
							$chipLabel = mb_strtoupper($chipLabel, 'UTF-8');
						} else {
							$chipLabel = strtoupper($chipLabel);
						}
						$description = $summary !== '' ? $summary : ($region !== '' ? $region : 'Описание места скоро появится.');
						?>
						<article class="places-catalog-card" data-place-card>
							<a class="places-catalog-card-link" href="<?php echo $sanitizer->entities($placeUrl); ?>">
								<div class="places-catalog-card-image" style="background-image: url('<?php echo htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>');"></div>
								<span class="places-catalog-chip"><?php echo $sanitizer->entities($chipLabel); ?></span>
								<h2 class="places-catalog-card-title"><?php echo $sanitizer->entities($title); ?></h2>
								<p class="places-catalog-card-description"><?php echo $sanitizer->entities($description); ?></p>
							</a>
						</article>
					<?php endforeach; ?>
				</div>
				<?php if ($totalFilteredPlaces > 8): ?>
					<div class="places-catalog-more-wrap">
						<button class="places-catalog-more-btn" type="button" data-places-more>
							Показать еще
							<span class="places-catalog-more-arrow" aria-hidden="true"></span>
						</button>
					</div>
				<?php endif; ?>
			<?php else: ?>
				<div class="hotels-empty">Места по выбранным фильтрам не найдены.</div>
			<?php endif; ?>
		</div>
	</section>
</div>
