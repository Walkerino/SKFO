<?php namespace ProcessWire;

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

$regionTitle = $normalizeDisplayText((string) $page->title);
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

$getFirstImageUrlFromPage = static function(Page $contentPage, array $fieldNames) use ($getImageUrlsFromPage): string {
	$urls = $getImageUrlsFromPage($contentPage, $fieldNames);
	return count($urls) ? (string) $urls[0] : '';
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

$transliterateRu = static function(string $value): string {
	$map = [
		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh',
		'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
		'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts',
		'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
		'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd', 'Е' => 'e', 'Ё' => 'e', 'Ж' => 'zh',
		'З' => 'z', 'И' => 'i', 'Й' => 'y', 'К' => 'k', 'Л' => 'l', 'М' => 'm', 'Н' => 'n', 'О' => 'o',
		'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't', 'У' => 'u', 'Ф' => 'f', 'Х' => 'h', 'Ц' => 'ts',
		'Ч' => 'ch', 'Ш' => 'sh', 'Щ' => 'sch', 'Ъ' => '', 'Ы' => 'y', 'Ь' => '', 'Э' => 'e', 'Ю' => 'yu', 'Я' => 'ya',
	];
	return strtr($value, $map);
};

$slugifyArticle = static function(string $value) use ($transliterateRu): string {
	$value = trim($value);
	if ($value === '') return '';
	$value = $transliterateRu($value);
	$value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	$value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
	$value = trim($value, '-');
	return $value;
};

$appendArticleQuery = static function(string $url, array $params): string {
	$parts = parse_url($url);
	if ($parts === false) return $url;

	$queryParams = [];
	if (!empty($parts['query'])) {
		parse_str((string) $parts['query'], $queryParams);
	}
	foreach ($params as $key => $value) {
		$key = trim((string) $key);
		$value = trim((string) $value);
		if ($key === '' || $value === '') continue;
		$queryParams[$key] = $value;
	}

	$path = (string) ($parts['path'] ?? '/articles/');
	$query = count($queryParams) ? '?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986) : '';
	$fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . (string) $parts['fragment'] : '';
	return $path . $query . $fragment;
};

$appendLocalQueryParams = static function(string $url, array $params): string {
	$url = trim($url);
	if ($url === '') return '';

	$parts = parse_url($url);
	if ($parts === false) return $url;
	if (!empty($parts['scheme']) || !empty($parts['host'])) return $url;

	$queryParams = [];
	if (!empty($parts['query'])) parse_str((string) $parts['query'], $queryParams);
	foreach ($params as $key => $value) {
		$key = trim((string) $key);
		$value = trim((string) $value);
		if ($key === '' || $value === '') continue;
		$queryParams[$key] = $value;
	}

	$path = (string) ($parts['path'] ?? '/');
	if ($path === '') $path = '/';
	if ($path[0] !== '/') $path = '/' . ltrim($path, '/');
	$query = count($queryParams) ? '?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986) : '';
	$fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . (string) $parts['fragment'] : '';
	return $path . $query . $fragment;
};

$buildArticleUrl = static function(string $title, string $url = '', string $source = '', string $back = '') use ($slugifyArticle, $appendArticleQuery): string {
	$url = trim($url);
	if ($url !== '' && $url !== '/articles' && $url !== '/articles/') {
		$articleUrl = $url;
	} else {
		$slug = $slugifyArticle($title);
		$articleUrl = $slug === '' ? '/articles/' : '/articles/?article=' . rawurlencode($slug);
	}

	return $appendArticleQuery($articleUrl, [
		'from' => $source,
		'back' => $back,
	]);
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

$regionLabelRaw = $normalizeDisplayText((string) ($regionProfile['label'] ?? ''));
$regionLabel = $regionLabelRaw !== '' ? $regionLabelRaw : $regionTitle;
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

$regionStoryBySlug = [
	'respublika-dagestan' => [
		'lead' => 'Дагестан объединяет морское побережье, каньоны и высокогорные аулы: здесь в одной поездке можно увидеть сразу несколько климатических зон.',
		'paragraphs' => [
			'Маршруты обычно строят от побережья Каспия к горным районам: Дербент, Сулакский каньон, Хунзах и Гуниб хорошо сочетаются в одном сценарии путешествия.',
			'Регион подходит для тех, кто хочет насыщенные выезды с большим количеством локаций, гастрономией и погружением в локальную культуру.',
		],
		'facts' => [
			['label' => 'Формат отдыха', 'value' => 'активные выезды и фотомаршруты'],
			['label' => 'Лучший сезон', 'value' => 'апрель–октябрь'],
			['label' => 'Оптимальная длительность', 'value' => '4-7 дней'],
		],
		'highlights' => ['Сулакский каньон', 'Дербент', 'Горные аулы', 'Панорамные плато'],
	],
	'respublika-ingushetiya' => [
		'lead' => 'Ингушетия — регион компактных, но очень выразительных маршрутов: башенные комплексы, ущелья и спокойный темп поездки.',
		'paragraphs' => [
			'Ключевые точки сосредоточены в Джейрахском районе, поэтому логистика для коротких поездок удобная и позволяет увидеть много за 2-3 дня.',
			'Поездка сюда хорошо подходит для культурно-исторического туризма и треков средней сложности по горным долинам.',
		],
		'facts' => [
			['label' => 'Формат отдыха', 'value' => 'история, треккинг, смотровые'],
			['label' => 'Лучший сезон', 'value' => 'май–октябрь'],
			['label' => 'Оптимальная длительность', 'value' => '3-5 дней'],
		],
		'highlights' => ['Башенные комплексы', 'Джейрахское ущелье', 'Горные перевалы', 'Исторические села'],
	],
	'chechenskaya-respublika' => [
		'lead' => 'Чеченская Республика сочетает современную городскую инфраструктуру и выразительные горные маршруты в южной части региона.',
		'paragraphs' => [
			'Наиболее популярный сценарий включает Грозный, Аргунское ущелье, Кезеной-Ам и выезды в Итум-Калинский район.',
			'Это направление удобно для комбинированных поездок: часть маршрута в городе, часть — в горах с насыщенной природной программой.',
		],
		'facts' => [
			['label' => 'Формат отдыха', 'value' => 'комбинированные city + mountains'],
			['label' => 'Лучший сезон', 'value' => 'май–сентябрь'],
			['label' => 'Оптимальная длительность', 'value' => '3-6 дней'],
		],
		'highlights' => ['Кезеной-Ам', 'Аргунское ущелье', 'Грозный', 'Нихалойские водопады'],
	],
	'kabardino-balkarskaya-respublika' => [
		'lead' => 'Кабардино-Балкария — один из главных центров горного туризма на Кавказе с маршрутами разной сложности вокруг Эльбруса.',
		'paragraphs' => [
			'Регион позволяет собрать программу под любой уровень подготовки: канатные дороги, треккинг, водопады и ущелья в рамках одной поездки.',
			'Популярные базы размещения сосредоточены в Приэльбрусье, что делает логистику по ключевым точкам особенно удобной.',
		],
		'facts' => [
			['label' => 'Формат отдыха', 'value' => 'горы, треккинг, outdoor'],
			['label' => 'Лучший сезон', 'value' => 'круглый год'],
			['label' => 'Оптимальная длительность', 'value' => '4-8 дней'],
		],
		'highlights' => ['Эльбрус', 'Чегемские водопады', 'Баксанское ущелье', 'Голубые озера'],
	],
	'karachaevo-cherkesskaya-respublika' => [
		'lead' => 'Карачаево-Черкесия известна курортами Домбай и Архыз, альпийскими лугами и большим выбором треков.',
		'paragraphs' => [
			'Маршруты региона подходят для всех сезонов: летом — тропы и водопады, зимой — горнолыжная инфраструктура и панорамные виды.',
			'Это направление часто выбирают для семейных поездок и коротких выездов на 3-4 дня благодаря развитой туристической базе.',
		],
		'facts' => [
			['label' => 'Формат отдыха', 'value' => 'курортный и активный'],
			['label' => 'Лучший сезон', 'value' => 'круглый год'],
			['label' => 'Оптимальная длительность', 'value' => '3-6 дней'],
		],
		'highlights' => ['Домбай', 'Архыз', 'Софийские водопады', 'Тебердинский заповедник'],
	],
	'respublika-severnaya-osetiya' => [
		'lead' => 'Северная Осетия даёт сочетание мощных ущелий, исторических памятников и удобных маршрутов выходного дня.',
		'paragraphs' => [
			'Цейское, Куртатинское и Кармадонское ущелья формируют базу для насыщенных автопутешествий с большим количеством смотровых площадок.',
			'Регион особенно интересен любителям истории: древние поселения и некрополи здесь органично сочетаются с природными локациями.',
		],
		'facts' => [
			['label' => 'Формат отдыха', 'value' => 'road-trip и история'],
			['label' => 'Лучший сезон', 'value' => 'май–октябрь'],
			['label' => 'Оптимальная длительность', 'value' => '3-5 дней'],
		],
		'highlights' => ['Даргавс', 'Куртатинское ущелье', 'Цей', 'Мидаграбинские водопады'],
	],
	'stavropolskiy-kray' => [
		'lead' => 'Ставропольский край — комфортная база для спокойного отдыха, санаторного формата и поездок по городам КМВ.',
		'paragraphs' => [
			'Кисловодск, Пятигорск, Ессентуки и Железноводск удобно комбинируются в одном маршруте с акцентом на парки, терренкуры и минеральные источники.',
			'Регион хорошо подходит для семейных и оздоровительных поездок, а также как точка старта для выездов в горные районы соседних республик.',
		],
		'facts' => [
			['label' => 'Формат отдыха', 'value' => 'курортный и оздоровительный'],
			['label' => 'Лучший сезон', 'value' => 'круглый год'],
			['label' => 'Оптимальная длительность', 'value' => '3-7 дней'],
		],
		'highlights' => ['Кисловодск', 'Пятигорск', 'Курортные парки', 'Термальные комплексы'],
	],
];

$regionStory = $regionStoryBySlug[$page->name] ?? [
	'lead' => "Регион {$regionLabel} подходит для путешествий в формате активных выездов и насыщенных маршрутов.",
	'paragraphs' => [
		"Сценарий поездки по {$regionLabel} можно собрать под любой темп: от спокойных прогулок до полноценной активной программы.",
		'В карточках ниже собраны ключевые маршруты, места и материалы для подготовки к поездке.',
	],
	'facts' => [
		['label' => 'Формат отдыха', 'value' => 'смешанный'],
		['label' => 'Лучший сезон', 'value' => 'круглый год'],
		['label' => 'Оптимальная длительность', 'value' => '3-6 дней'],
	],
	'highlights' => [],
];

$regionLeadText = trim((string) ($regionStory['lead'] ?? ''));
$regionDescriptionParagraphs = array_values(array_filter(
	array_map(static fn($item): string => trim((string) $item), (array) ($regionStory['paragraphs'] ?? [])),
	static fn(string $item): bool => $item !== ''
));
$regionFactItems = [];
foreach ((array) ($regionStory['facts'] ?? []) as $fact) {
	if (!is_array($fact)) continue;
	$label = trim((string) ($fact['label'] ?? ''));
	$value = trim((string) ($fact['value'] ?? ''));
	if ($label === '' || $value === '') continue;
	$regionFactItems[] = ['label' => $label, 'value' => $value];
}
$regionHighlightItems = array_values(array_filter(
	array_map(static fn($item): string => trim((string) $item), (array) ($regionStory['highlights'] ?? [])),
	static fn(string $item): bool => $item !== ''
));

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

$regionTextNeedlesMap = [];
foreach (array_merge((array) ($regionProfile['aliases'] ?? []), [$regionLabel, $regionTitle, $page->name]) as $alias) {
	$normalizedAlias = $normalizeRegion(str_replace('-', ' ', (string) $alias));
	if ($normalizedAlias === '') continue;
	$aliasLength = function_exists('mb_strlen') ? mb_strlen($normalizedAlias, 'UTF-8') : strlen($normalizedAlias);
	if ($aliasLength < 3) continue;
	$regionTextNeedlesMap[$normalizedAlias] = true;
}
$regionTextNeedles = array_keys($regionTextNeedlesMap);

$textMentionsCurrentRegion = static function(string $value) use ($normalizeRegion, $regionTextNeedles): bool {
	$normalizedText = $normalizeRegion($value);
	if ($normalizedText === '' || !count($regionTextNeedles)) return false;
	foreach ($regionTextNeedles as $needle) {
		if ($needle !== '' && strpos($normalizedText, $needle) !== false) return true;
	}
	return false;
};

$homePage = $pages->get('/');
$tourUrlByTitle = [];
$tourPagesForLinks = $pages->find('template=tour, include=all, sort=title, limit=500');
foreach ($tourPagesForLinks as $tourPageForLink) {
	if (!$tourPageForLink instanceof Page) continue;
	$tourTitleForLink = $tourPageForLink->hasField('tour_title') ? trim((string) $tourPageForLink->getUnformatted('tour_title')) : '';
	if ($tourTitleForLink === '') $tourTitleForLink = trim((string) $tourPageForLink->title);
	if ($tourTitleForLink === '') continue;
	$tourUrlByTitle[$toLower($tourTitleForLink)] = (string) $tourPageForLink->url;
}

$adventureCards = [];
if ($page->hasField('region_featured_tours') && $page->region_featured_tours->count()) {
	foreach ($page->region_featured_tours as $tourPage) {
		if (!$tourPage instanceof Page) continue;
		$imageUrl = $getFirstImageUrlFromPage($tourPage, ['tour_cover_image', 'images']);
		$title = $tourPage->hasField('tour_title') ? trim((string) $tourPage->getUnformatted('tour_title')) : '';
		if ($title === '') $title = trim((string) $tourPage->title);
		$price = $tourPage->hasField('tour_price') ? $normalizeTourPrice((string) $tourPage->getUnformatted('tour_price')) : '';
		if ($title === '' && $price === '' && $imageUrl === '') continue;

		$adventureCards[] = [
			'title' => $title,
			'region' => $regionLabel,
			'price' => $price,
			'image' => $imageUrl,
			'url' => (string) $tourPage->url,
		];
	}
}

if (!count($adventureCards)) {
	$tourPagesByRegion = $pages->find('template=tour, include=all, sort=title, limit=500');
	foreach ($tourPagesByRegion as $tourPage) {
		if (!$tourPage instanceof Page) continue;
		$tourRegion = $tourPage->hasField('tour_region') ? trim((string) $tourPage->getUnformatted('tour_region')) : '';
		if ($tourRegion === '' && $tourPage->hasField('region')) {
			$tourRegion = trim((string) $tourPage->getUnformatted('region'));
		}
		if (!$matchesCurrentRegion($tourRegion)) continue;

		$imageUrl = $getFirstImageUrlFromPage($tourPage, ['tour_cover_image', 'images']);
		$title = $tourPage->hasField('tour_title') ? trim((string) $tourPage->getUnformatted('tour_title')) : '';
		if ($title === '') $title = trim((string) $tourPage->title);
		$price = $tourPage->hasField('tour_price') ? $normalizeTourPrice((string) $tourPage->getUnformatted('tour_price')) : '';
		if ($title === '' && $price === '' && $imageUrl === '') continue;

		$adventureCards[] = [
			'title' => $title,
			'region' => $regionLabel,
			'price' => $price,
			'image' => $imageUrl,
			'url' => (string) $tourPage->url,
		];
	}
}

if (!count($adventureCards) && $page->hasField('region_adventures_cards') && $page->region_adventures_cards->count()) {
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
			'url' => isset($tourUrlByTitle[$toLower($title)]) ? $tourUrlByTitle[$toLower($title)] : '',
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

		$title = $card->hasField('hot_tour_title') ? $normalizeDisplayText((string) $card->hot_tour_title) : '';
		$price = $card->hasField('hot_tour_price') ? $normalizeTourPrice((string) $card->getUnformatted('hot_tour_price')) : '';
		if ($title === '' && $price === '' && $imageUrl === '') continue;

		$adventureCards[] = [
			'title' => $title,
			'region' => $regionLabel,
			'price' => $price,
			'image' => $imageUrl,
			'url' => isset($tourUrlByTitle[$toLower($title)]) ? $tourUrlByTitle[$toLower($title)] : '',
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
			'url' => isset($tourUrlByTitle[$toLower(trim((string) ($card['title'] ?? '')))]) ? $tourUrlByTitle[$toLower(trim((string) ($card['title'] ?? '')))] : '',
		];
	}
}

$placeUrlByTitle = [];
$placePagesByRegion = [];
$regionPlacePages = $pages->find('template=place, include=all, sort=title, limit=300');
foreach ($regionPlacePages as $placePage) {
	if (!$placePage instanceof Page) continue;
	$placeRegion = $placePage->hasField('place_region') ? trim((string) $placePage->getUnformatted('place_region')) : '';
	if (!$matchesCurrentRegion($placeRegion)) continue;

	$placePagesByRegion[] = $placePage;
	$title = $normalizeDisplayText((string) $placePage->title);
	if ($title === '') continue;
	$titleKey = $toLower($title);
	if (!isset($placeUrlByTitle[$titleKey])) {
		$placeUrlByTitle[$titleKey] = trim((string) $placePage->url);
	}
}

$interestingPlaces = [];
if ($page->hasField('region_featured_places') && $page->region_featured_places->count()) {
	foreach ($page->region_featured_places as $placePage) {
		if (!$placePage instanceof Page) continue;
		$imageUrl = $getFirstImageUrlFromPage($placePage, ['place_image', 'images']);
		$title = $normalizeDisplayText((string) $placePage->title);
		$text = $placePage->hasField('place_summary') ? $normalizeDisplayText((string) $placePage->place_summary) : '';
		if ($title === '' && $text === '' && $imageUrl === '') continue;

		$interestingPlaces[] = [
			'title' => $title,
			'text' => $text,
			'image' => $imageUrl,
			'url' => trim((string) $placePage->url),
		];
	}
}

if (!count($interestingPlaces) && $page->hasField('region_places_cards') && $page->region_places_cards->count()) {
	foreach ($page->region_places_cards as $card) {
		$imageUrl = '';
		if ($card->hasField('region_place_image')) {
			$imageUrl = $getImageUrlFromValue($card->getUnformatted('region_place_image'));
		}

		$title = $card->hasField('region_place_title') ? $normalizeDisplayText((string) $card->region_place_title) : '';
		$text = $card->hasField('region_place_text') ? $normalizeDisplayText((string) $card->region_place_text) : '';
		if ($text === '' && $imageUrl === '') continue;
		if ($title === '' && $text === '' && $imageUrl === '') continue;
		$titleKey = $title !== '' ? $toLower($title) : '';

		$interestingPlaces[] = [
			'title' => $title,
			'text' => $text,
			'image' => $imageUrl,
			'url' => $titleKey !== '' ? trim((string) ($placeUrlByTitle[$titleKey] ?? '')) : '',
		];
	}
}

if (count($interestingPlaces) < 2) {
	$existingPlaceKeys = [];
	foreach ($interestingPlaces as $existingPlace) {
		$existingTitle = trim((string) ($existingPlace['title'] ?? ''));
		if ($existingTitle === '') continue;
		$existingPlaceKeys[$toLower($existingTitle)] = true;
	}

	foreach ($placePagesByRegion as $placePage) {
		if (!$placePage instanceof Page) continue;
		$imageUrl = $getFirstImageUrlFromPage($placePage, ['place_image', 'images']);

		$title = $normalizeDisplayText((string) $placePage->title);
		$text = $placePage->hasField('place_summary') ? $normalizeDisplayText((string) $placePage->place_summary) : '';
		if ($title === '' && $text === '' && $imageUrl === '') continue;
		$placeKey = $title !== '' ? $toLower($title) : '';
		if ($placeKey !== '' && isset($existingPlaceKeys[$placeKey])) continue;

		$interestingPlaces[] = [
			'title' => $title,
			'text' => $text,
			'image' => $imageUrl,
			'url' => trim((string) $placePage->url),
		];
		if ($placeKey !== '') $existingPlaceKeys[$placeKey] = true;
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

		$title = $card->hasField('card_title') ? $normalizeDisplayText((string) $card->card_title) : '';
		$text = $card->hasField('card_text') ? $normalizeDisplayText((string) $card->card_text) : '';
		if ($title === '' && $text === '' && $imageUrl === '') continue;
		$titleKey = $title !== '' ? $toLower($title) : '';

		$interestingPlaces[] = [
			'title' => $title,
			'text' => $text,
			'image' => $imageUrl,
			'url' => $titleKey !== '' ? trim((string) ($placeUrlByTitle[$titleKey] ?? '')) : '',
		];
	}
}

$regionPlaceholderImage = trim((string) ($config->urls->templates . 'assets/image1.png'));
$interestingPlaces = array_values(array_filter(
	$interestingPlaces,
	static function(array $card) use ($regionPlaceholderImage): bool {
		$image = trim((string) ($card['image'] ?? ''));
		return $image !== '' && $image !== $regionPlaceholderImage;
	}
));

if (count($interestingPlaces) % 2 !== 0 && count($interestingPlaces) > 1) {
	array_pop($interestingPlaces);
}

$defaultRegionPlaceholderImage = $config->urls->templates . 'assets/image1.png';
$regionArticles = [];
$regionArticlePageIds = [];

$articleMatchesCurrentRegion = static function(Page $articlePage) use ($matchesCurrentRegion, $textMentionsCurrentRegion): bool {
	if ($articlePage->hasField('article_region')) {
		$articleRegion = trim((string) $articlePage->getUnformatted('article_region'));
		if ($articleRegion !== '' && $matchesCurrentRegion($articleRegion)) return true;
	}

	$title = trim((string) $articlePage->title);
	$topic = $articlePage->hasField('article_topic') ? trim((string) $articlePage->getUnformatted('article_topic')) : '';
	$excerpt = $articlePage->hasField('article_excerpt') ? trim((string) $articlePage->getUnformatted('article_excerpt')) : '';
	$content = $articlePage->hasField('article_content') ? trim(strip_tags((string) $articlePage->getUnformatted('article_content'))) : '';
	return $textMentionsCurrentRegion("{$title} {$topic} {$excerpt} {$content}");
};

$addRegionArticleFromPage = static function(Page $articlePage) use (
	&$regionArticles,
	&$regionArticlePageIds,
	$articleMatchesCurrentRegion,
	$getFirstImageUrlFromPage,
	$formatRussianDate,
	$normalizeDisplayText,
	$buildArticleUrl,
	$page,
	$defaultRegionPlaceholderImage
): void {
	$pageId = (int) $articlePage->id;
	if ($pageId <= 0 || isset($regionArticlePageIds[$pageId])) return;
	if (!$articleMatchesCurrentRegion($articlePage)) return;

	$imageUrl = $getFirstImageUrlFromPage($articlePage, ['article_cover_image', 'images']);
	$timestamp = $articlePage->hasField('article_publish_date') ? (int) $articlePage->getUnformatted('article_publish_date') : 0;
	$title = $normalizeDisplayText((string) $articlePage->title);
	$topic = $articlePage->hasField('article_topic') ? $normalizeDisplayText((string) $articlePage->getUnformatted('article_topic')) : '';
	if ($title === '' && $topic === '' && $imageUrl === '' && $timestamp <= 0) return;

	$regionArticlePageIds[$pageId] = true;
	$regionArticles[] = [
		'title' => $title,
		'date' => $timestamp > 0 ? $formatRussianDate($timestamp) : '',
		'datetime' => $timestamp > 0 ? date('Y-m-d', $timestamp) : '',
		'topic' => $topic,
		'image' => $imageUrl !== '' ? $imageUrl : $defaultRegionPlaceholderImage,
		'url' => $buildArticleUrl($title, '/articles/?article=' . rawurlencode((string) $articlePage->name), 'region', (string) $page->url),
		'is_fresh' => false,
	];
};

if ($page->hasField('region_featured_articles') && $page->region_featured_articles->count()) {
	foreach ($page->region_featured_articles as $articlePage) {
		if (!$articlePage instanceof Page) continue;
		$addRegionArticleFromPage($articlePage);
	}
}

if (count($regionArticles) < 4) {
	$catalogArticlePages = $pages->find('template=article, include=all, sort=-article_publish_date, limit=500');
	foreach ($catalogArticlePages as $articlePage) {
		if (!$articlePage instanceof Page) continue;
		$addRegionArticleFromPage($articlePage);
		if (count($regionArticles) >= 4) break;
	}
}

$regionArticles = array_slice($regionArticles, 0, 4);

$regionMediaItems = [];
$regionMediaKeys = [];
$addRegionMediaItem = static function(string $imageUrl, string $title, string $source = 'Фото') use (&$regionMediaItems, &$regionMediaKeys, $defaultRegionPlaceholderImage): void {
	$imageUrl = trim($imageUrl);
	$title = trim($title);
	$source = trim($source);
	if ($imageUrl === '' || $title === '') return;
	if ($imageUrl === $defaultRegionPlaceholderImage) return;

	$key = $imageUrl . '|' . $title . '|' . $source;
	if (isset($regionMediaKeys[$key])) return;
	$regionMediaKeys[$key] = true;
	$regionMediaItems[] = [
		'image' => $imageUrl,
		'title' => $title,
		'source' => $source === '' ? 'Фото' : $source,
	];
};

$addRegionMediaFromValue = static function($imageValue, string $title, string $source = 'Фото') use ($addRegionMediaItem): void {
	if ($imageValue instanceof Pageimage) {
		$addRegionMediaItem((string) $imageValue->url, $title, $source);
		return;
	}
	if ($imageValue instanceof Pageimages && $imageValue->count()) {
		foreach ($imageValue as $image) {
			if (!$image instanceof Pageimage) continue;
			$addRegionMediaItem((string) $image->url, $title, $source);
		}
	}
};

$addRegionMediaFromPage = static function(Page $contentPage, array $fieldNames, string $title, string $source = 'Фото') use ($addRegionMediaItem, $getImageUrlsFromPage): void {
	foreach ($getImageUrlsFromPage($contentPage, $fieldNames) as $imageUrl) {
		$addRegionMediaItem((string) $imageUrl, $title, $source);
	}
};

if ($page->hasField('region_media_gallery') && $page->region_media_gallery->count()) {
	foreach ($page->region_media_gallery as $mediaCard) {
		$mediaTitle = $mediaCard->hasField('region_media_title') ? trim((string) $mediaCard->region_media_title) : '';
		if ($mediaTitle === '') $mediaTitle = $regionTitle;
		if ($mediaCard->hasField('region_media_image')) {
			$addRegionMediaFromValue($mediaCard->getUnformatted('region_media_image'), $mediaTitle, 'Фото');
		}
	}
}

if (!count($regionMediaItems)) {
	if ($page->hasField('region_card_image')) {
		$addRegionMediaFromValue($page->getUnformatted('region_card_image'), $regionTitle, 'Обложка региона');
	}

	if ($page->hasField('region_places_cards') && $page->region_places_cards->count()) {
		foreach ($page->region_places_cards as $card) {
			$mediaTitle = $card->hasField('region_place_title') ? $normalizeDisplayText((string) $card->region_place_title) : '';
			if ($mediaTitle === '') $mediaTitle = $regionTitle;
			if ($card->hasField('region_place_image')) {
				$addRegionMediaFromValue($card->getUnformatted('region_place_image'), $mediaTitle, 'Место');
			}
		}
	}

	if ($page->hasField('region_adventures_cards') && $page->region_adventures_cards->count()) {
		foreach ($page->region_adventures_cards as $card) {
			$mediaTitle = $card->hasField('region_adventure_title') ? trim((string) $card->region_adventure_title) : '';
			if ($mediaTitle === '') $mediaTitle = $regionTitle;
			if ($card->hasField('region_adventure_image')) {
				$addRegionMediaFromValue($card->getUnformatted('region_adventure_image'), $mediaTitle, 'Маршрут');
			}
		}
	}

	if ($page->hasField('region_articles_cards') && $page->region_articles_cards->count()) {
		foreach ($page->region_articles_cards as $card) {
			$mediaTitle = $card->hasField('region_article_title') ? trim((string) $card->region_article_title) : '';
			if ($mediaTitle === '') $mediaTitle = $regionTitle;
			if ($card->hasField('region_article_image')) {
				$addRegionMediaFromValue($card->getUnformatted('region_article_image'), $mediaTitle, 'Материал');
			}
		}
	}

	if ($page->hasField('region_featured_places') && $page->region_featured_places->count()) {
		foreach ($page->region_featured_places as $placePage) {
			if (!$placePage instanceof Page) continue;
			$mediaTitle = trim((string) $placePage->title);
			if ($mediaTitle === '') $mediaTitle = $regionTitle;
			$addRegionMediaFromPage($placePage, ['place_image', 'images'], $mediaTitle, 'Место');
		}
	}

	$placePagesForMedia = $pages->find('template=place, include=all, sort=title, limit=300');
	foreach ($placePagesForMedia as $placePage) {
		if (!$placePage instanceof Page) continue;
		$placeRegion = $placePage->hasField('place_region') ? trim((string) $placePage->getUnformatted('place_region')) : '';
		if (!$matchesCurrentRegion($placeRegion)) continue;
		$mediaTitle = trim((string) $placePage->title);
		if ($mediaTitle === '') $mediaTitle = $regionTitle;
		$addRegionMediaFromPage($placePage, ['place_image', 'images'], $mediaTitle, 'Место');
	}

	if ($page->hasField('region_featured_tours') && $page->region_featured_tours->count()) {
		foreach ($page->region_featured_tours as $tourPage) {
			if (!$tourPage instanceof Page) continue;
			$mediaTitle = $tourPage->hasField('tour_title') ? trim((string) $tourPage->getUnformatted('tour_title')) : '';
			if ($mediaTitle === '') $mediaTitle = trim((string) $tourPage->title);
			if ($mediaTitle === '') $mediaTitle = $regionTitle;
			if ($tourPage->hasField('tour_cover_image')) {
				$addRegionMediaFromValue($tourPage->getUnformatted('tour_cover_image'), $mediaTitle, 'Маршрут');
			}
			if ($tourPage->hasField('images')) {
				$addRegionMediaFromValue($tourPage->getUnformatted('images'), $mediaTitle, 'Маршрут');
			}
		}
	}

	if ($page->hasField('region_featured_articles') && $page->region_featured_articles->count()) {
		foreach ($page->region_featured_articles as $articlePage) {
			if (!$articlePage instanceof Page) continue;
			$mediaTitle = trim((string) $articlePage->title);
			if ($mediaTitle === '') $mediaTitle = $regionTitle;
			if ($articlePage->hasField('article_cover_image')) {
				$addRegionMediaFromValue($articlePage->getUnformatted('article_cover_image'), $mediaTitle, 'Материал');
			}
			if ($articlePage->hasField('images')) {
				$addRegionMediaFromValue($articlePage->getUnformatted('images'), $mediaTitle, 'Материал');
			}
		}
	}

	foreach ($interestingPlaces as $placeMediaItem) {
		$addRegionMediaItem(
			trim((string) ($placeMediaItem['image'] ?? '')),
			trim((string) ($placeMediaItem['title'] ?? '')),
			'Место'
		);
	}

	foreach ($adventureCards as $adventureMediaItem) {
		$addRegionMediaItem(
			trim((string) ($adventureMediaItem['image'] ?? '')),
			trim((string) ($adventureMediaItem['title'] ?? '')),
			'Маршрут'
		);
	}

	foreach ($regionArticles as $articleMediaItem) {
		$addRegionMediaItem(
			trim((string) ($articleMediaItem['image'] ?? '')),
			trim((string) ($articleMediaItem['title'] ?? '')),
			'Материал'
		);
	}
}

$regionMediaItems = array_values($regionMediaItems);

$forumTitle = 'Форум СКФО';
$forumSubtitle = "Делимся опытом и помогаем\nдруг другу планировать поездки";
$forumButtonText = 'Присоединиться';
$forumImageUrl = $config->urls->templates . 'assets/image1.png';
$forumExternalUrl = 'https://club.skfo.ru';
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

	<section class="section section--region-overview">
		<div class="container">
			<div class="region-overview-grid">
				<article class="region-overview-card">
					<h2 class="region-overview-title">О регионе</h2>
					<?php if ($regionLeadText !== ''): ?>
						<p class="region-overview-lead"><?php echo $sanitizer->entities($regionLeadText); ?></p>
					<?php endif; ?>
					<?php foreach ($regionDescriptionParagraphs as $paragraph): ?>
						<p class="region-overview-text"><?php echo $sanitizer->entities($paragraph); ?></p>
					<?php endforeach; ?>
					<?php if (count($regionHighlightItems)): ?>
						<div class="region-overview-highlights">
							<?php foreach ($regionHighlightItems as $highlight): ?>
								<span class="region-highlight-chip"><?php echo $sanitizer->entities($highlight); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</article>
				<aside class="region-overview-facts">
					<?php foreach ($regionFactItems as $fact): ?>
						<div class="region-fact-card">
							<span class="region-fact-label"><?php echo $sanitizer->entities((string) ($fact['label'] ?? '')); ?></span>
							<strong class="region-fact-value"><?php echo $sanitizer->entities((string) ($fact['value'] ?? '')); ?></strong>
						</div>
					<?php endforeach; ?>
				</aside>
			</div>
		</div>
	</section>

	<section class="section section--region-media">
		<div class="container">
			<div class="region-media-header">
				<h2 class="section-title">Медиа региона</h2>
			</div>
			<?php if (count($regionMediaItems)): ?>
				<div class="region-media-grid" data-region-gallery data-region-media-grid data-region-media-limit="8">
					<?php foreach ($regionMediaItems as $index => $mediaItem): ?>
						<?php
							$mediaUrl = trim((string) ($mediaItem['image'] ?? ''));
							$mediaImage = htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8');
							$mediaTitle = trim((string) ($mediaItem['title'] ?? ''));
							$mediaAlt = $mediaTitle !== '' ? $mediaTitle : ('Медиа региона ' . ((int) $index + 1));
							$mediaLabel = 'фото';
						?>
						<article class="region-media-card">
							<button
								class="region-media-trigger"
								type="button"
								data-region-gallery-item
								data-gallery-index="<?php echo (int) $index; ?>"
								data-gallery-src="<?php echo $mediaImage; ?>"
								data-gallery-alt="<?php echo $sanitizer->entities($mediaAlt); ?>"
								aria-label="<?php echo $sanitizer->entities('Открыть ' . $mediaLabel . ' ' . ((int) $index + 1)); ?>"
							>
								<div class="region-media-thumb">
									<img class="region-media-thumb-media" src="<?php echo $mediaImage; ?>" alt="<?php echo $sanitizer->entities($mediaAlt); ?>" loading="lazy" />
								</div>
							</button>
						</article>
					<?php endforeach; ?>
				</div>
				<?php if (count($regionMediaItems) > 8): ?>
					<div class="region-media-actions">
						<button class="region-media-more-btn" type="button" data-region-media-more aria-expanded="false">Показать всё</button>
					</div>
				<?php endif; ?>
			<?php else: ?>
				<div class="region-media-empty">Для этого региона пока нет загруженных фото.</div>
			<?php endif; ?>
		</div>
	</section>
	<?php if (count($regionMediaItems)): ?>
		<div class="hotel-gallery-lightbox" data-region-gallery-modal hidden>
			<div class="hotel-gallery-lightbox-backdrop" data-gallery-close="backdrop"></div>
			<div class="hotel-gallery-lightbox-dialog" role="dialog" aria-modal="true" aria-label="Медиа региона">
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
							$cardUrl = trim((string) ($card['url'] ?? ''));
							$isCardLink = $cardUrl !== '';
							if (!empty($card['image'])) {
								$image = htmlspecialchars((string) $card['image'], ENT_QUOTES, 'UTF-8');
								$backgroundStyle = " style=\"background-image: url('{$image}');\"";
							}
						?>
						<?php if ($isCardLink): ?>
							<a class="hot-tour-card" href="<?php echo $sanitizer->entities($cardUrl); ?>" aria-label="<?php echo $sanitizer->entities((string) $card['title']); ?>">
						<?php else: ?>
							<article class="hot-tour-card">
						<?php endif; ?>
							<div class="hot-tour-image"<?php echo $backgroundStyle; ?>></div>
							<div class="hot-tour-body">
								<h3 class="hot-tour-title"><?php echo $sanitizer->entities((string) $card['title']); ?></h3>
								<div class="hot-tour-region"><?php echo $sanitizer->entities((string) $card['region']); ?></div>
								<div class="hot-tour-footer">
									<span class="hot-tour-price"><?php echo $sanitizer->entities((string) $card['price']); ?></span>
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

	<?php if (count($interestingPlaces)): ?>
	<section class="section section--actual section--actual-slider" data-actual-slider>
		<div class="container">
			<div class="actual-grid">
				<div class="actual-track">
					<?php foreach ($interestingPlaces as $card): ?>
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
						if ($cardUrl !== '') {
							$cardUrl = $appendLocalQueryParams($cardUrl, [
								'from' => 'region',
								'back' => (string) $page->url,
								'cover' => trim((string) ($card['image'] ?? '')),
							]);
						}
						$isCardLink = $cardUrl !== '';
						if (!empty($card['image'])) {
							$image = htmlspecialchars((string) $card['image'], ENT_QUOTES, 'UTF-8');
							$backgroundStyle = " style=\"background-image: linear-gradient(135deg, rgba(17, 24, 39, 0.25), rgba(17, 24, 39, 0.15)), url('{$image}');\"";
						}
						?>
						<?php if ($isCardLink): ?>
							<a class="actual-card" href="<?php echo $sanitizer->entities($cardUrl); ?>" aria-label="<?php echo $sanitizer->entities((string) $card['title']); ?>">
						<?php else: ?>
							<article class="actual-card">
						<?php endif; ?>
							<div class="actual-card-image"<?php echo $backgroundStyle; ?>></div>
							<div class="actual-card-body">
								<h3 class="actual-card-title"><?php echo $sanitizer->entities((string) $card['title']); ?></h3>
								<p class="actual-card-text"><?php echo $sanitizer->entities((string) $card['text']); ?></p>
								<div class="actual-card-footer">
									<span class="tag-location"><?php echo $sanitizer->entities($regionLabel); ?></span>
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
			<div class="actual-slider-progress" data-actual-progress<?php echo count($interestingPlaces) > 2 ? '' : ' hidden'; ?>>
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

		<section class="section section--region-articles">
			<div class="container">
				<h2 class="region-articles-title"><?php echo $sanitizer->entities($regionAboutTitle); ?></h2>
				<?php if (count($regionArticles)): ?>
					<div class="region-articles-card">
						<div class="region-article-list region-article-list--stack">
							<?php foreach ($regionArticles as $article): ?>
								<a class="region-article region-article--stack" href="<?php echo $sanitizer->entities((string) $article['url']); ?>">
									<div class="region-article-side-thumb" style="background-image: linear-gradient(135deg, rgba(17, 24, 39, 0.25), rgba(17, 24, 39, 0.15)), url('<?php echo htmlspecialchars((string) $article['image'], ENT_QUOTES, 'UTF-8'); ?>');"></div>
									<div class="region-article-content">
										<?php if (!empty($article['is_fresh'])): ?>
											<span class="region-article-badge region-article-badge--inline">Свежая статья</span>
										<?php endif; ?>
										<?php if ((string) ($article['date'] ?? '') !== ''): ?>
											<time class="region-article-date" datetime="<?php echo $sanitizer->entities((string) ($article['datetime'] ?? '')); ?>">
												<?php echo $sanitizer->entities((string) $article['date']); ?>
											</time>
										<?php endif; ?>
										<h3 class="region-article-title"><?php echo $sanitizer->entities((string) $article['title']); ?></h3>
										<?php if ((string) ($article['topic'] ?? '') !== ''): ?>
											<p class="region-article-topic"><?php echo $sanitizer->entities((string) $article['topic']); ?></p>
										<?php endif; ?>
									</div>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php else: ?>
					<div class="region-media-empty">Для этого региона пока нет статей в базе.</div>
				<?php endif; ?>
			</div>
		</section>

		<section class="section section--forum">
			<div class="container">
				<div class="forum-card">
				<div class="forum-card-inner">
					<h2 class="forum-title"><?php echo $sanitizer->entities($forumTitle); ?></h2>
					<p class="forum-subtitle"><?php echo nl2br($sanitizer->entities($forumSubtitle)); ?></p>
					<a class="forum-button" href="<?php echo $forumExternalUrl; ?>" target="_blank" rel="noopener noreferrer"><?php echo $sanitizer->entities($forumButtonText); ?></a>
				</div>
				<img class="forum-image" src="<?php echo $sanitizer->entities($forumImageUrl); ?>" alt="<?php echo $sanitizer->entities($forumTitle); ?>" />
			</div>
		</div>
	</section>
</div>
