<?php namespace ProcessWire;

$defaultRegions = [
	[
		'slug' => 'kabardino-balkarskaya-respublika',
		'title' => "Кабардино-Балкарская\nРеспублика",
		'subtitle' => "Эльбрус и ущелья",
		'image' => '',
	],
	[
		'slug' => 'karachaevo-cherkesskaya-respublika',
		'title' => "Карачаево-Черкесская\nРеспублика",
		'subtitle' => "Домбай и Архыз",
		'image' => '',
	],
	[
		'slug' => 'respublika-dagestan',
		'title' => "Республика Дагестан",
		'subtitle' => "Каньоны, аулы и море впечатлений",
		'image' => '',
	],
	[
		'slug' => 'respublika-ingushetiya',
		'title' => "Республика Ингушетия",
		'subtitle' => "Башни, легенды и горные долины",
		'image' => '',
	],
	[
		'slug' => 'respublika-severnaya-osetiya',
		'title' => "Республика\nСеверная Осетия",
		'subtitle' => "Перевалы и древние тропы",
		'image' => '',
	],
	[
		'slug' => 'stavropolskiy-kray',
		'title' => "Ставропольский край",
		'subtitle' => "Курорты, парки и мягкий южный ритм",
		'image' => '',
	],
	[
		'slug' => 'chechenskaya-respublika',
		'title' => "Чеченская Республика",
		'subtitle' => "Горные дороги и мощные виды",
		'image' => '',
	],
];

$defaultRegionsBySlug = [];
$defaultRegionOrder = [];
foreach ($defaultRegions as $index => $item) {
	$slug = (string) ($item['slug'] ?? '');
	if ($slug === '') continue;
	$defaultRegionsBySlug[$slug] = $item;
	$defaultRegionOrder[$slug] = $index;
}

$regionUrlsBySlug = [];
foreach ($defaultRegions as $item) {
	$slug = (string) ($item['slug'] ?? '');
	if ($slug === '') continue;

	$regionPath = '/regions/' . $slug . '/';
	$regionPage = $pages->get($regionPath);
	$regionUrlsBySlug[$slug] = ($regionPage && $regionPage->id) ? $regionPage->url : $regionPath;
}

$normalizeRegionText = static function(string $value): string {
	$value = trim(str_replace("\n", ' ', $value));
	$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
	return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
};

$normalizeRegionMatch = static function(string $value): string {
	$value = str_replace(["\r", "\n"], ' ', trim($value));
	$value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	$value = str_replace('ё', 'е', $value);
	$value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
	$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
	return trim($value);
};

$regionAliasesBySlug = [
	'respublika-dagestan' => ['respublika-dagestan', 'республика дагестан', 'дагестан'],
	'respublika-ingushetiya' => ['respublika-ingushetiya', 'республика ингушетия', 'ингушетия'],
	'chechenskaya-respublika' => ['chechenskaya-respublika', 'чеченская республика', 'чечня'],
	'kabardino-balkarskaya-respublika' => ['kabardino-balkarskaya-respublika', 'кабардино балкарская республика', 'кабардино балкария', 'кбр'],
	'karachaevo-cherkesskaya-respublika' => ['karachaevo-cherkesskaya-respublika', 'карачаево черкесская республика', 'карачаево черкесия', 'кчр'],
	'respublika-severnaya-osetiya' => ['respublika-severnaya-osetiya', 'республика северная осетия', 'северная осетия', 'осетия'],
	'stavropolskiy-kray' => ['stavropolskiy-kray', 'ставропольский край', 'ставрополье'],
];

$extractImageUrl = static function($imageValue): string {
	if ($imageValue instanceof Pageimage) return $imageValue->url;
	if ($imageValue instanceof Pageimages && $imageValue->count()) return $imageValue->first()->url;
	return '';
};

$extractImageUrls = static function($imageValue): array {
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

$getFirstImageUrlFromPage = static function(Page $contentPage, array $fieldNames) use ($extractImageUrls, $getFallbackImageUrlsFromPageFiles): string {
	foreach ($fieldNames as $fieldName) {
		$fieldName = trim((string) $fieldName);
		if ($fieldName === '' || !$contentPage->hasField($fieldName)) continue;
		$urls = $extractImageUrls($contentPage->getUnformatted($fieldName));
		if (count($urls)) return trim((string) $urls[0]);
	}

	$fallbackUrls = $getFallbackImageUrlsFromPageFiles($contentPage);
	if (count($fallbackUrls)) return trim((string) $fallbackUrls[0]);

	return '';
};

$getFirstRegionMediaCoverImage = static function(Page $regionPage) use ($extractImageUrl, $getFirstImageUrlFromPage, $pages, $normalizeRegionMatch, $regionAliasesBySlug): string {
	if ($regionPage->hasField('region_media_gallery') && $regionPage->region_media_gallery->count()) {
		foreach ($regionPage->region_media_gallery as $mediaCard) {
			if (!$mediaCard->hasField('region_media_image')) continue;
			$url = $extractImageUrl($mediaCard->getUnformatted('region_media_image'));
			if ($url !== '') return $url;
		}
	}

	if ($regionPage->hasField('region_places_cards') && $regionPage->region_places_cards->count()) {
		foreach ($regionPage->region_places_cards as $card) {
			if (!$card->hasField('region_place_image')) continue;
			$url = $extractImageUrl($card->getUnformatted('region_place_image'));
			if ($url !== '') return $url;
		}
	}

	if ($regionPage->hasField('region_adventures_cards') && $regionPage->region_adventures_cards->count()) {
		foreach ($regionPage->region_adventures_cards as $card) {
			if (!$card->hasField('region_adventure_image')) continue;
			$url = $extractImageUrl($card->getUnformatted('region_adventure_image'));
			if ($url !== '') return $url;
		}
	}

	if ($regionPage->hasField('region_articles_cards') && $regionPage->region_articles_cards->count()) {
		foreach ($regionPage->region_articles_cards as $card) {
			if (!$card->hasField('region_article_image')) continue;
			$url = $extractImageUrl($card->getUnformatted('region_article_image'));
			if ($url !== '') return $url;
		}
	}

	if ($regionPage->hasField('region_featured_places') && $regionPage->region_featured_places->count()) {
		foreach ($regionPage->region_featured_places as $placePage) {
			if (!$placePage instanceof Page) continue;
			$url = $getFirstImageUrlFromPage($placePage, ['place_image', 'images']);
			if ($url !== '') return $url;
		}
	}

	if ($regionPage->hasField('region_featured_tours') && $regionPage->region_featured_tours->count()) {
		foreach ($regionPage->region_featured_tours as $tourPage) {
			if (!$tourPage instanceof Page) continue;
			$url = $getFirstImageUrlFromPage($tourPage, ['tour_cover_image', 'images']);
			if ($url !== '') return $url;
		}
	}

	if ($regionPage->hasField('region_featured_articles') && $regionPage->region_featured_articles->count()) {
		foreach ($regionPage->region_featured_articles as $articlePage) {
			if (!$articlePage instanceof Page) continue;
			$url = $getFirstImageUrlFromPage($articlePage, ['article_cover_image', 'images']);
			if ($url !== '') return $url;
		}
	}

	$regionSlug = trim((string) $regionPage->name);
	$aliasLookup = [];
	$regionAliases = $regionAliasesBySlug[$regionSlug] ?? [$regionSlug, (string) $regionPage->title];
	foreach ($regionAliases as $alias) {
		$normalizedAlias = $normalizeRegionMatch((string) $alias);
		if ($normalizedAlias !== '') $aliasLookup[$normalizedAlias] = true;
	}
	$matchesCurrentRegion = static function(string $value) use ($normalizeRegionMatch, $aliasLookup): bool {
		$normalizedValue = $normalizeRegionMatch($value);
		return $normalizedValue !== '' && isset($aliasLookup[$normalizedValue]);
	};

	$placePages = $pages->find('template=place, include=all, sort=title, limit=500');
	foreach ($placePages as $placePage) {
		if (!$placePage instanceof Page) continue;
		$placeRegion = $placePage->hasField('place_region') ? trim((string) $placePage->getUnformatted('place_region')) : '';
		if (!$matchesCurrentRegion($placeRegion)) continue;
		$url = $getFirstImageUrlFromPage($placePage, ['place_image', 'images']);
		if ($url !== '') return $url;
	}

	$tourPages = $pages->find('template=tour, include=all, sort=title, limit=500');
	foreach ($tourPages as $tourPage) {
		if (!$tourPage instanceof Page) continue;
		$tourRegion = $tourPage->hasField('tour_region') ? trim((string) $tourPage->getUnformatted('tour_region')) : '';
		if ($tourRegion === '' && $tourPage->hasField('region')) {
			$tourRegion = trim((string) $tourPage->getUnformatted('region'));
		}
		if (!$matchesCurrentRegion($tourRegion)) continue;
		$url = $getFirstImageUrlFromPage($tourPage, ['tour_cover_image', 'images']);
		if ($url !== '') return $url;
	}

	return '';
};

$slugByTitle = [];
$slugBySubtitle = [];
foreach ($defaultRegions as $item) {
	$slug = (string) ($item['slug'] ?? '');
	if ($slug === '') continue;

	$titleKey = $normalizeRegionText((string) ($item['title'] ?? ''));
	$subtitleKey = $normalizeRegionText((string) ($item['subtitle'] ?? ''));

	if ($titleKey !== '') $slugByTitle[$titleKey] = $slug;
	if ($subtitleKey !== '') $slugBySubtitle[$subtitleKey] = $slug;
}

$regions = [];
$regionsBySlugFromCards = [];

if ($page->hasField('region_cards') && $page->region_cards->count()) {
	foreach ($page->region_cards as $index => $card) {
		$imageUrl = '';
		if ($card->hasField('region_card_image')) {
			$cardImage = $card->getUnformatted('region_card_image');
			if ($cardImage instanceof Pageimage) {
				$imageUrl = $cardImage->url;
			} elseif ($cardImage instanceof Pageimages && $cardImage->count()) {
				$imageUrl = $cardImage->first()->url;
			}
		}

		$title = $card->hasField('region_card_title') ? trim((string) $card->region_card_title) : '';
		$subtitle = $card->hasField('region_card_description') ? trim((string) $card->region_card_description) : '';

		if ($title === '' && $subtitle === '' && $imageUrl === '') {
			continue;
		}

		$titleKey = $normalizeRegionText($title);
		$subtitleKey = $normalizeRegionText($subtitle);
		$resolvedSlug = '';

		if ($titleKey !== '' && isset($slugByTitle[$titleKey])) {
			$resolvedSlug = $slugByTitle[$titleKey];
		} elseif ($subtitleKey !== '' && isset($slugBySubtitle[$subtitleKey])) {
			$resolvedSlug = $slugBySubtitle[$subtitleKey];
		} else {
			$defaultItem = $defaultRegions[$index] ?? null;
			$resolvedSlug = is_array($defaultItem) ? ((string) ($defaultItem['slug'] ?? '')) : '';
		}

		$url = $resolvedSlug !== '' && isset($regionUrlsBySlug[$resolvedSlug]) ? $regionUrlsBySlug[$resolvedSlug] : '/regions/';

		if ($imageUrl === '' && $resolvedSlug !== '') {
			$regionDetailPage = $pages->get('/regions/' . $resolvedSlug . '/');
			if ($regionDetailPage instanceof Page && $regionDetailPage->id) {
				$imageUrl = $getFirstRegionMediaCoverImage($regionDetailPage);
			}
		}

		$regionData = [
			'slug' => $resolvedSlug,
			'title' => $title,
			'subtitle' => $subtitle,
			'image' => $imageUrl,
			'url' => $url,
		];
		$regions[] = $regionData;
		if ($resolvedSlug !== '') $regionsBySlugFromCards[$resolvedSlug] = $regionData;
	}
}

$regionDetailPages = $page->children('template=region, include=all, sort=sort, limit=200');
if ($regionDetailPages instanceof PageArray && $regionDetailPages->count()) {
	$regions = [];
	foreach ($regionDetailPages as $regionDetailPage) {
		$slug = trim((string) $regionDetailPage->name);
		$defaultItem = $slug !== '' && isset($defaultRegionsBySlug[$slug]) ? $defaultRegionsBySlug[$slug] : null;
		$cardData = $slug !== '' && isset($regionsBySlugFromCards[$slug]) ? $regionsBySlugFromCards[$slug] : null;

		$title = '';
		if ($regionDetailPage->hasField('region_card_title')) {
			$title = trim((string) $regionDetailPage->region_card_title);
		}
		if ($title === '' && is_array($cardData)) {
			$title = trim((string) ($cardData['title'] ?? ''));
		}
		if ($title === '') $title = trim((string) $regionDetailPage->title);
		if ($title === '' && is_array($defaultItem)) $title = (string) ($defaultItem['title'] ?? '');

		$subtitle = '';
		if ($regionDetailPage->hasField('region_card_description')) {
			$subtitle = trim((string) $regionDetailPage->region_card_description);
		}
		if ($subtitle === '' && is_array($cardData)) {
			$subtitle = trim((string) ($cardData['subtitle'] ?? ''));
		}
		if ($subtitle === '' && is_array($defaultItem)) $subtitle = (string) ($defaultItem['subtitle'] ?? '');

		$image = '';
		if ($regionDetailPage->hasField('region_card_image')) {
			$image = $extractImageUrl($regionDetailPage->getUnformatted('region_card_image'));
		}
		if ($image === '' && is_array($cardData)) {
			$image = trim((string) ($cardData['image'] ?? ''));
		}
		if ($image === '') {
			$image = $getFirstRegionMediaCoverImage($regionDetailPage);
		}
		if ($image === '' && is_array($defaultItem)) $image = trim((string) ($defaultItem['image'] ?? ''));

		if ($title === '' && $subtitle === '' && $image === '') continue;

		$regions[] = [
			'slug' => $slug,
			'title' => $title,
			'subtitle' => $subtitle,
			'image' => $image,
			'url' => (string) $regionDetailPage->url,
		];
	}

	usort($regions, static function(array $a, array $b) use ($defaultRegionOrder): int {
		$aSlug = (string) ($a['slug'] ?? '');
		$bSlug = (string) ($b['slug'] ?? '');
		$aOrder = isset($defaultRegionOrder[$aSlug]) ? (int) $defaultRegionOrder[$aSlug] : 9999;
		$bOrder = isset($defaultRegionOrder[$bSlug]) ? (int) $defaultRegionOrder[$bSlug] : 9999;
		if ($aOrder === $bOrder) {
			return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
		}
		return $aOrder <=> $bOrder;
	});
}

if (!count($regions)) {
	foreach ($defaultRegions as $item) {
		$slug = (string) ($item['slug'] ?? '');
		$url = $slug !== '' && isset($regionUrlsBySlug[$slug]) ? $regionUrlsBySlug[$slug] : '/regions/';
		$regions[] = [
			'slug' => $slug,
			'title' => (string) ($item['title'] ?? ''),
			'subtitle' => (string) ($item['subtitle'] ?? ''),
			'image' => (string) ($item['image'] ?? ''),
			'url' => $url,
		];
	}
}
?>

<div id="content" class="regions-page">
	<section class="reviews-hero">
		<div class="container hero-inner reviews-hero-inner">
			<h1 class="reviews-title">РЕГИОНЫ<br />КАВКАЗА</h1>
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
					<a class="hero-tab" href="/reviews/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/reviews.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Отзывы</span>
					</a>
					<a class="hero-tab" href="/guides/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/human.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Гиды</span>
					</a>
					<a class="hero-tab is-active" href="/regions/" role="tab" aria-selected="true">
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

	<section class="regions-content">
		<div class="container">
			<div class="regions-grid">
				<?php $shouldCenterLastCard = count($regions) % 3 === 1; ?>
				<?php foreach ($regions as $index => $region): ?>
					<?php
					$imageUrl = trim((string) $region['image']);
					$hasImage = $imageUrl !== '';
					$mediaStyle = $hasImage
						? " style=\"background-image: url('" . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . "');\""
						: '';
					$isLastCard = $index === count($regions) - 1;
					$centerLastCard = $isLastCard && $shouldCenterLastCard;
					?>
					<article class="regions-card<?php echo $centerLastCard ? ' regions-card--center' : ''; ?>">
						<div class="regions-card-media<?php echo $hasImage ? '' : ' regions-card-media--placeholder'; ?>"<?php echo $mediaStyle; ?>></div>
						<h2 class="regions-card-title"><?php echo nl2br($sanitizer->entities((string) $region['title'])); ?></h2>
						<p class="regions-card-subtitle"><?php echo nl2br($sanitizer->entities((string) $region['subtitle'])); ?></p>
						<a class="regions-card-btn" href="<?php echo $sanitizer->entities((string) $region['url']); ?>">Узнать больше</a>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
</div>
