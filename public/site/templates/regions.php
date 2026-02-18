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

		$regions[] = [
			'title' => $title,
			'subtitle' => $subtitle,
			'image' => $imageUrl,
			'url' => $url,
		];
	}
}

if (!count($regions)) {
	foreach ($defaultRegions as $item) {
		$slug = (string) ($item['slug'] ?? '');
		$url = $slug !== '' && isset($regionUrlsBySlug[$slug]) ? $regionUrlsBySlug[$slug] : '/regions/';
		$regions[] = [
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
		<div class="container">
			<h1 class="reviews-title">РЕГИОНЫ<br />КАВКАЗА</h1>
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
