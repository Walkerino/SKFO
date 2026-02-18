<?php namespace ProcessWire;

$regionTitle = trim((string) $page->title);
$hasGenericRegionTitle = ($regionTitle === '' || $regionTitle === 'Регион');

$toLower = static function(string $value): string {
	return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
};

$normalizeRegion = static function(string $value) use ($toLower): string {
	$value = str_replace(["\r", "\n"], ' ', trim($value));
	$value = $toLower($value);
	$value = str_replace('ё', 'е', $value);
	$value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
	$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
	return trim($value);
};

$getImageUrlFromValue = static function($imageValue): string {
	if ($imageValue instanceof Pageimage) return $imageValue->url;
	if ($imageValue instanceof Pageimages && $imageValue->count()) return $imageValue->first()->url;
	return '';
};

$formatRussianDate = static function(int $timestamp): string {
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
	$monthLabel = $months[$month] ?? '';
	return trim("{$day} {$monthLabel} {$year}");
};

$regionProfiles = [
	'dagestan' => [
		'label' => 'Республика Дагестан',
		'aliases' => ['respublika-dagestan', 'республика дагестан', 'дагестан'],
		'adventures' => [
			['title' => 'Пройти по Сулакскому каньону', 'price' => 'от 15 000₽', 'image' => ''],
			['title' => 'Подняться к аулу Гамсутль', 'price' => 'от 12 500₽', 'image' => ''],
			['title' => 'Открыть Хунзахское плато', 'price' => 'от 13 000₽', 'image' => ''],
			['title' => 'Побывать в Дербенте', 'price' => 'от 10 000₽', 'image' => ''],
			['title' => 'Доехать до бархана Сарыкум', 'price' => 'от 9 500₽', 'image' => ''],
		],
		'places' => [
			[
				'title' => 'Сулакский каньон',
				'text' => 'Одна из самых впечатляющих природных локаций региона с обзорными площадками и прогулками на катере.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
			[
				'title' => 'Гамсутль',
				'text' => 'Высокогорный аул с сильной атмосферой истории и панорамами горных склонов.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
		],
	],
	'ingushetia' => [
		'label' => 'Республика Ингушетия',
		'aliases' => ['respublika-ingushetiya', 'республика ингушетия', 'ингушетия'],
		'adventures' => [
			['title' => 'Пройти Джейрахское ущелье', 'price' => 'от 14 000₽', 'image' => ''],
			['title' => 'Исследовать башенные комплексы', 'price' => 'от 11 000₽', 'image' => ''],
			['title' => 'Увидеть Ляжгинский водопад', 'price' => 'от 9 000₽', 'image' => ''],
			['title' => 'Подняться к перевалу Цей-Лоам', 'price' => 'от 12 000₽', 'image' => ''],
			['title' => 'Съездить в древние аулы', 'price' => 'от 10 500₽', 'image' => ''],
		],
		'places' => [
			[
				'title' => 'Джейрахское ущелье',
				'text' => 'Гордость Ингушетии: башенные комплексы, горные маршруты и природные смотровые точки.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
			[
				'title' => 'Таргимская котловина',
				'text' => 'Историческое сердце региона с памятниками средневековой архитектуры.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
		],
	],
	'chechnya' => [
		'label' => 'Чеченская Республика',
		'aliases' => ['chechenskaya-respublika', 'чеченская республика', 'чечня'],
		'adventures' => [
			['title' => 'Посетить озеро Кезеной-Ам', 'price' => 'от 15 000₽', 'image' => ''],
			['title' => 'Открыть Аргунское ущелье', 'price' => 'от 13 000₽', 'image' => ''],
			['title' => 'Доехать до Нихалойских водопадов', 'price' => 'от 10 500₽', 'image' => ''],
			['title' => 'Пройти маршрут в Итум-Кали', 'price' => 'от 12 000₽', 'image' => ''],
			['title' => 'Побывать в Грозном и окрестностях', 'price' => 'от 9 500₽', 'image' => ''],
		],
		'places' => [
			[
				'title' => 'Озеро Кезеной-Ам',
				'text' => 'Самое большое высокогорное озеро Северного Кавказа с чистой водой и альпийскими видами.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
			[
				'title' => 'Аргунское ущелье',
				'text' => 'Живописная дорога через горные хребты, башни и древние поселения.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
		],
	],
	'kabardino-balkaria' => [
		'label' => 'Кабардино-Балкарская Республика',
		'aliases' => ['kabardino-balkarskaya-respublika', 'кабардино балкарская республика', 'кабардино балкария', 'кбр'],
		'adventures' => [
			['title' => 'Взойти на Эльбрус', 'price' => 'от 18 000₽', 'image' => ''],
			['title' => 'Доехать до Чегемских водопадов', 'price' => 'от 11 500₽', 'image' => ''],
			['title' => 'Погулять по Баксанскому ущелью', 'price' => 'от 12 500₽', 'image' => ''],
			['title' => 'Посетить Голубые озера', 'price' => 'от 9 500₽', 'image' => ''],
			['title' => 'Исследовать Верхнюю Балкарию', 'price' => 'от 13 500₽', 'image' => ''],
		],
		'places' => [
			[
				'title' => 'Эльбрус',
				'text' => 'Главная вершина Кавказа и центр активного горного туризма в регионе.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
			[
				'title' => 'Чегемские водопады',
				'text' => 'Каскад водопадов в узком ущелье с выразительными скалами и маршрутами.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
		],
	],
	'karachay-cherkessia' => [
		'label' => 'Карачаево-Черкесская Республика',
		'aliases' => ['karachaevo-cherkesskaya-respublika', 'карачаево черкесская республика', 'карачаево черкесия', 'кчр'],
		'adventures' => [
			['title' => 'Покататься в Архызе', 'price' => 'от 14 500₽', 'image' => ''],
			['title' => 'Пройти маршруты Домбая', 'price' => 'от 13 000₽', 'image' => ''],
			['title' => 'Подняться на Софийские водопады', 'price' => 'от 11 500₽', 'image' => ''],
			['title' => 'Посетить Тебердинский заповедник', 'price' => 'от 10 500₽', 'image' => ''],
			['title' => 'Открыть перевалы ущелий', 'price' => 'от 12 000₽', 'image' => ''],
		],
		'places' => [
			[
				'title' => 'Домбай',
				'text' => 'Горный курорт с канатными дорогами, альпийскими лугами и насыщенными треками.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
			[
				'title' => 'Архыз',
				'text' => 'Популярная локация для всесезонного отдыха и маршрутов в высокогорье.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
		],
	],
	'north-ossetia' => [
		'label' => 'Республика Северная Осетия',
		'aliases' => ['respublika-severnaya-osetiya', 'республика северная осетия', 'северная осетия', 'осетия'],
		'adventures' => [
			['title' => 'Проехать по Куртатинскому ущелью', 'price' => 'от 12 500₽', 'image' => ''],
			['title' => 'Посетить Даргавс', 'price' => 'от 10 000₽', 'image' => ''],
			['title' => 'Подняться к Мидаграбинским водопадам', 'price' => 'от 11 500₽', 'image' => ''],
			['title' => 'Исследовать Цейское ущелье', 'price' => 'от 13 000₽', 'image' => ''],
			['title' => 'Открыть Кармадонское ущелье', 'price' => 'от 10 500₽', 'image' => ''],
		],
		'places' => [
			[
				'title' => 'Даргавс',
				'text' => 'Историко-культурный памятник Осетии в окружении горных пейзажей.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
			[
				'title' => 'Куртатинское ущелье',
				'text' => 'Горная долина с древними сооружениями, смотровыми площадками и маршрутами.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
		],
	],
	'stavropol' => [
		'label' => 'Ставропольский край',
		'aliases' => ['stavropolskiy-kray', 'ставропольский край', 'ставрополье'],
		'adventures' => [
			['title' => 'Отдохнуть в Кисловодске', 'price' => 'от 10 500₽', 'image' => ''],
			['title' => 'Погулять по Железноводску', 'price' => 'от 9 000₽', 'image' => ''],
			['title' => 'Посетить Пятигорск', 'price' => 'от 8 500₽', 'image' => ''],
			['title' => 'Съездить в Ессентуки', 'price' => 'от 8 000₽', 'image' => ''],
			['title' => 'Расслабиться в термах', 'price' => 'от 9 500₽', 'image' => ''],
		],
		'places' => [
			[
				'title' => 'Кисловодский парк',
				'text' => 'Крупнейший городской курортный парк с терренкурами и панорамными видами.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
			[
				'title' => 'Гора Машук',
				'text' => 'Символ Пятигорска и одна из ключевых обзорных точек региона.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
		],
	],
];

$regionProfile = null;
$normalizedRegionTitle = $normalizeRegion($regionTitle);
foreach ($regionProfiles as $profile) {
	foreach ($profile['aliases'] as $alias) {
		$normalizedAlias = $normalizeRegion((string) $alias);
		if ($normalizedAlias !== '' && ($normalizedAlias === $normalizedRegionTitle || (string) $alias === $page->name)) {
			$regionProfile = $profile;
			break 2;
		}
	}
}

if (!$regionProfile) {
	$regionProfile = [
		'label' => $regionTitle,
		'aliases' => [$page->name, $regionTitle],
		'adventures' => [
			['title' => 'Открыть главные маршруты региона', 'price' => 'от 10 000₽', 'image' => ''],
			['title' => 'Провести день в природных локациях', 'price' => 'от 9 500₽', 'image' => ''],
			['title' => 'Выбрать экскурсию по местным достопримечательностям', 'price' => 'от 11 000₽', 'image' => ''],
			['title' => 'Собрать насыщенный уикенд-маршрут', 'price' => 'от 12 000₽', 'image' => ''],
			['title' => 'Найти тур для семьи или компании', 'price' => 'от 10 500₽', 'image' => ''],
		],
		'places' => [
			[
				'title' => 'Главная локация региона',
				'text' => 'Одна из самых популярных точек для путешествий и экскурсий.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
			[
				'title' => 'Панорамный маршрут',
				'text' => 'Живописный маршрут с красивыми видами и удобным доступом.',
				'image' => $config->urls->templates . 'assets/image1.png',
			],
		],
	];
}

$regionLabel = trim((string) ($regionProfile['label'] ?? '')) !== '' ? (string) $regionProfile['label'] : $regionTitle;
if ($hasGenericRegionTitle && $regionLabel !== '') {
	$regionTitle = $regionLabel;
}
if ($regionTitle === '') {
	$regionTitle = 'Регион';
}
$regionAliasLookup = [];
foreach ((array) ($regionProfile['aliases'] ?? []) as $alias) {
	$normalizedAlias = $normalizeRegion((string) $alias);
	if ($normalizedAlias !== '') {
		$regionAliasLookup[$normalizedAlias] = true;
	}
}

$regionAboutTitleBySlug = [
	'respublika-dagestan' => 'Дагестане',
	'respublika-ingushetiya' => 'Ингушетии',
	'chechenskaya-respublika' => 'Чеченской Республике',
	'kabardino-balkarskaya-respublika' => 'Кабардино-Балкарской Республике',
	'karachaevo-cherkesskaya-respublika' => 'Карачаево-Черкесской Республике',
	'respublika-severnaya-osetiya' => 'Северной Осетии',
	'stavropolskiy-kray' => 'Ставропольском крае',
];

$regionAboutTitle = '';
if ($page->hasField('region_articles_heading')) {
	$regionAboutTitle = trim((string) $page->region_articles_heading);
}
if ($regionAboutTitle === '') {
	$regionAboutTitle = 'Интересное о ' . ($regionAboutTitleBySlug[$page->name] ?? $regionLabel);
}

$matchesCurrentRegion = static function(string $value) use ($normalizeRegion, $regionAliasLookup): bool {
	$normalizedValue = $normalizeRegion($value);
	return $normalizedValue !== '' && isset($regionAliasLookup[$normalizedValue]);
};

$homePage = $pages->get('/');

$adventureCards = [];
if ($page->hasField('region_adventures_cards') && $page->region_adventures_cards->count()) {
	foreach ($page->region_adventures_cards as $card) {
		$imageUrl = '';
		if ($card->hasField('region_adventure_image')) {
			$imageUrl = $getImageUrlFromValue($card->getUnformatted('region_adventure_image'));
		}

		$title = $card->hasField('region_adventure_title') ? trim((string) $card->region_adventure_title) : '';
		$price = $card->hasField('region_adventure_price') ? trim((string) $card->region_adventure_price) : '';
		if ($title === '' && $price === '' && $imageUrl === '') continue;

		$adventureCards[] = [
			'title' => $title,
			'region' => $regionLabel,
			'price' => $price,
			'image' => $imageUrl,
		];
	}
}

if (!count($adventureCards) && $homePage && $homePage->id && $homePage->hasField('hot_tours_cards') && $homePage->hot_tours_cards->count()) {
	foreach ($homePage->hot_tours_cards as $card) {
		$cardRegion = $card->hasField('hot_tour_region') ? trim((string) $card->hot_tour_region) : '';
		if (!$matchesCurrentRegion($cardRegion)) continue;

		$imageUrl = '';
		if ($card->hasField('hot_tour_image')) {
			$imageUrl = $getImageUrlFromValue($card->getUnformatted('hot_tour_image'));
		}

		$title = $card->hasField('hot_tour_title') ? trim((string) $card->hot_tour_title) : '';
		$price = $card->hasField('hot_tour_price') ? trim((string) $card->hot_tour_price) : '';
		if ($title === '' && $price === '' && $imageUrl === '') continue;

		$adventureCards[] = [
			'title' => $title,
			'region' => $regionLabel,
			'price' => $price,
			'image' => $imageUrl,
		];
	}
}

if (!count($adventureCards)) {
	foreach ((array) ($regionProfile['adventures'] ?? []) as $card) {
		$adventureCards[] = [
			'title' => trim((string) ($card['title'] ?? '')),
			'region' => $regionLabel,
			'price' => trim((string) ($card['price'] ?? '')),
			'image' => trim((string) ($card['image'] ?? '')),
		];
	}
}

$interestingPlaces = [];
if ($page->hasField('region_places_cards') && $page->region_places_cards->count()) {
	foreach ($page->region_places_cards as $card) {
		$imageUrl = '';
		if ($card->hasField('region_place_image')) {
			$imageUrl = $getImageUrlFromValue($card->getUnformatted('region_place_image'));
		}

		$title = $card->hasField('region_place_title') ? trim((string) $card->region_place_title) : '';
		$text = $card->hasField('region_place_text') ? trim((string) $card->region_place_text) : '';
		if ($title === '' && $text === '' && $imageUrl === '') continue;

		$interestingPlaces[] = [
			'title' => $title,
			'text' => $text,
			'image' => $imageUrl,
		];
	}
}

if (!count($interestingPlaces) && $homePage && $homePage->id && $homePage->hasField('actual_cards') && $homePage->actual_cards->count()) {
	foreach ($homePage->actual_cards as $card) {
		$cardRegion = $card->hasField('card_region') ? trim((string) $card->card_region) : '';
		if (!$matchesCurrentRegion($cardRegion)) continue;

		$imageUrl = '';
		if ($card->hasField('card_image')) {
			$imageUrl = $getImageUrlFromValue($card->getUnformatted('card_image'));
		}

		$title = $card->hasField('card_title') ? trim((string) $card->card_title) : '';
		$text = $card->hasField('card_text') ? trim((string) $card->card_text) : '';
		if ($title === '' && $text === '' && $imageUrl === '') continue;

		$interestingPlaces[] = [
			'title' => $title,
			'text' => $text,
			'image' => $imageUrl,
		];
	}
}

if (!count($interestingPlaces)) {
	foreach ((array) ($regionProfile['places'] ?? []) as $card) {
		$interestingPlaces[] = [
			'title' => trim((string) ($card['title'] ?? '')),
			'text' => trim((string) ($card['text'] ?? '')),
			'image' => trim((string) ($card['image'] ?? '')),
		];
	}
}

if (count($interestingPlaces) % 2 !== 0) {
	array_pop($interestingPlaces);
}

if (!count($interestingPlaces)) {
	$interestingPlaces = [
		[
			'title' => 'Интересное место региона',
			'text' => 'Знаковая точка региона, которую стоит посетить в первую очередь.',
			'image' => $config->urls->templates . 'assets/image1.png',
		],
		[
			'title' => 'Еще одна локация региона',
			'text' => 'Маршрут с красивыми видами и атмосферой местной культуры.',
			'image' => $config->urls->templates . 'assets/image1.png',
		],
	];
}

$regionArticles = [];
if ($page->hasField('region_articles_cards') && $page->region_articles_cards->count()) {
	foreach ($page->region_articles_cards as $card) {
		$imageUrl = '';
		if ($card->hasField('region_article_image')) {
			$imageUrl = $getImageUrlFromValue($card->getUnformatted('region_article_image'));
		}

		$timestamp = 0;
		if ($card->hasField('region_article_date')) {
			$dateRaw = $card->getUnformatted('region_article_date');
			if (is_numeric($dateRaw)) {
				$timestamp = (int) $dateRaw;
			} elseif (is_string($dateRaw)) {
				$timestamp = strtotime($dateRaw) ?: 0;
			}
		}

		$title = $card->hasField('region_article_title') ? trim((string) $card->region_article_title) : '';
		$topic = $card->hasField('region_article_topic') ? trim((string) $card->region_article_topic) : '';
		$url = $card->hasField('region_article_url') ? trim((string) $card->region_article_url) : '';
		$isFresh = $card->hasField('region_article_is_fresh') ? (bool) $card->region_article_is_fresh : false;

		if ($title === '' && $topic === '' && $imageUrl === '' && $timestamp <= 0) continue;

		$regionArticles[] = [
			'title' => $title,
			'date' => $timestamp > 0 ? $formatRussianDate($timestamp) : '',
			'datetime' => $timestamp > 0 ? date('Y-m-d', $timestamp) : '',
			'topic' => $topic,
			'image' => $imageUrl,
			'url' => $url !== '' ? $url : '/articles/',
			'is_fresh' => $isFresh,
		];
	}
}

if (!count($regionArticles)) {
	$regionArticles = [
		[
			'title' => "Как подготовиться к первому путешествию в {$regionLabel}",
			'date' => '1 февраля 2026',
			'datetime' => '2026-02-01',
			'topic' => 'Советы туристам',
			'image' => $config->urls->templates . 'assets/image1.png',
			'url' => '/articles/',
			'is_fresh' => true,
		],
		[
			'title' => 'Душа Кавказа в поэзии и традициях',
			'date' => '22 декабря 2025',
			'datetime' => '2025-12-22',
			'topic' => 'Культура и традиции',
			'image' => $config->urls->templates . 'assets/image1.png',
			'url' => '/articles/',
			'is_fresh' => false,
		],
		[
			'title' => 'Горнолыжный сезон: советы и лайфхаки',
			'date' => '16 декабря 2025',
			'datetime' => '2025-12-16',
			'topic' => 'Советы туристам',
			'image' => $config->urls->templates . 'assets/image1.png',
			'url' => '/articles/',
			'is_fresh' => false,
		],
		[
			'title' => 'Что взять с собой в поездку по региону',
			'date' => '8 декабря 2025',
			'datetime' => '2025-12-08',
			'topic' => 'Полезные подборки',
			'image' => $config->urls->templates . 'assets/image1.png',
			'url' => '/articles/',
			'is_fresh' => false,
		],
	];
}

$regionArticles = array_slice($regionArticles, 0, 4);
$leadArticle = $regionArticles[0] ?? [
	'title' => '',
	'date' => '',
	'datetime' => '',
	'topic' => '',
	'image' => '',
	'url' => '/articles/',
	'is_fresh' => false,
];
$sideArticles = array_slice($regionArticles, 1, 3);

$forumTitle = 'Форум СКФО';
$forumSubtitle = "Делимся опытом и помогаем\nдруг другу планировать поездки";
$forumButtonText = 'Присоединиться';
$forumImageUrl = $config->urls->templates . 'assets/image1.png';
?>

<div id="content" class="region-page">
	<section class="reviews-hero">
		<div class="container">
			<div class="region-hero-inner">
				<h1 class="reviews-title"><?php echo $sanitizer->entities($regionTitle); ?></h1>
				<a class="region-back-btn" href="/regions/">Вернуться назад</a>
			</div>
		</div>
	</section>

	<section class="section section--hot-tours">
		<div class="container-hot-tours">
			<div class="hot-tours-header">
				<h2 class="section-title">На встречу к приключениям</h2>
				<div class="hot-tours-actions">
					<button class="circle-btn circle-btn--prev hot-tours-prev" type="button" aria-label="Предыдущие туры"></button>
					<button class="circle-btn circle-btn--next hot-tours-next" type="button" aria-label="Следующие туры"></button>
				</div>
			</div>
			<div class="hot-tours-grid">
				<div class="hot-tours-track">
					<?php foreach ($adventureCards as $card): ?>
						<?php
							$backgroundStyle = '';
							if (!empty($card['image'])) {
								$image = htmlspecialchars((string) $card['image'], ENT_QUOTES, 'UTF-8');
								$backgroundStyle = " style=\"background-image: url('{$image}');\"";
							}
						?>
						<article class="hot-tour-card">
							<div class="hot-tour-image"<?php echo $backgroundStyle; ?>></div>
							<div class="hot-tour-body">
								<h3 class="hot-tour-title"><?php echo $sanitizer->entities((string) $card['title']); ?></h3>
								<div class="hot-tour-region"><?php echo $sanitizer->entities((string) $card['region']); ?></div>
								<div class="hot-tour-footer">
									<span class="hot-tour-price"><?php echo $sanitizer->entities((string) $card['price']); ?></span>
								</div>
							</div>
						</article>
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

	<section class="section section--actual">
		<div class="container actual-grid">
			<?php foreach ($interestingPlaces as $card): ?>
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
							<span class="tag-location"><?php echo $sanitizer->entities($regionLabel); ?></span>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
		</section>

		<section class="section section--region-articles">
			<div class="container">
				<h2 class="region-articles-title"><?php echo $sanitizer->entities($regionAboutTitle); ?></h2>
				<div class="region-articles-card">
					<a class="region-article region-article--lead" href="<?php echo $sanitizer->entities((string) $leadArticle['url']); ?>">
						<div class="region-article-media" style="background-image: url('<?php echo htmlspecialchars((string) $leadArticle['image'], ENT_QUOTES, 'UTF-8'); ?>');">
							<?php if (!empty($leadArticle['is_fresh'])): ?>
								<span class="region-article-badge">Свежая статья</span>
							<?php endif; ?>
							</div>
							<div class="region-article-content">
								<time class="region-article-date" datetime="<?php echo $sanitizer->entities((string) ($leadArticle['datetime'] ?? '')); ?>">
									<?php echo $sanitizer->entities((string) $leadArticle['date']); ?>
								</time>
								<h3 class="region-article-title"><?php echo $sanitizer->entities((string) $leadArticle['title']); ?></h3>
								<p class="region-article-topic"><?php echo $sanitizer->entities((string) $leadArticle['topic']); ?></p>
							</div>
						</a>

						<div class="region-article-list">
							<?php foreach ($sideArticles as $article): ?>
								<a class="region-article region-article--side" href="<?php echo $sanitizer->entities((string) $article['url']); ?>">
									<div class="region-article-content">
										<time class="region-article-date" datetime="<?php echo $sanitizer->entities((string) ($article['datetime'] ?? '')); ?>">
											<?php echo $sanitizer->entities((string) $article['date']); ?>
										</time>
										<h3 class="region-article-title"><?php echo $sanitizer->entities((string) $article['title']); ?></h3>
									<p class="region-article-topic"><?php echo $sanitizer->entities((string) $article['topic']); ?></p>
								</div>
								<div class="region-article-side-thumb" style="background-image: url('<?php echo htmlspecialchars((string) $article['image'], ENT_QUOTES, 'UTF-8'); ?>');"></div>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</section>

		<section class="section section--forum">
			<div class="container">
				<div class="forum-card">
				<div class="forum-card-inner">
					<h2 class="forum-title"><?php echo $sanitizer->entities($forumTitle); ?></h2>
					<p class="forum-subtitle"><?php echo nl2br($sanitizer->entities($forumSubtitle)); ?></p>
					<button class="forum-button" type="button"><?php echo $sanitizer->entities($forumButtonText); ?></button>
				</div>
				<img class="forum-image" src="<?php echo $sanitizer->entities($forumImageUrl); ?>" alt="<?php echo $sanitizer->entities($forumTitle); ?>" />
			</div>
		</div>
	</section>
</div>
