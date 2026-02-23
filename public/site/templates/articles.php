<?php namespace ProcessWire;

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

$slugify = static function(string $value) use ($transliterateRu): string {
	$value = trim($value);
	if ($value === '') return '';
	$value = $transliterateRu($value);
	$value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	$value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
	$value = trim($value, '-');
	return $value;
};

$normalizeTopic = static function(string $value): string {
	$value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	$value = str_replace('ё', 'е', $value);
	$value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
	$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
	return trim($value);
};

$splitToParagraphs = static function(string $value): array {
	$value = trim(str_replace("\r", '', $value));
	if ($value === '') return [];
	$parts = preg_split('/\n{2,}/', $value) ?: [];
	$paragraphs = [];
	foreach ($parts as $part) {
		$line = trim((string) (preg_replace('/\n+/', ' ', $part) ?? $part));
		if ($line !== '') $paragraphs[] = $line;
	}
	return $paragraphs;
};

$extractArticleSlugFromUrl = static function(string $url) use ($slugify): string {
	$url = trim($url);
	if ($url === '') return '';

	$parts = parse_url($url);
	if (is_array($parts) && !empty($parts['query'])) {
		$queryParams = [];
		parse_str((string) $parts['query'], $queryParams);
		if (!empty($queryParams['article'])) {
			return $slugify((string) $queryParams['article']);
		}
	}

	$path = trim((string) ($parts['path'] ?? ''), '/');
	if ($path !== '' && preg_match('#^articles/([^/]+)/?$#', $path, $matches)) {
		return $slugify((string) ($matches[1] ?? ''));
	}

	return '';
};

$buildArticleUrl = static function(string $title, string $url = '') use ($slugify): string {
	$url = trim($url);
	if ($url !== '' && $url !== '/articles' && $url !== '/articles/') {
		return $url;
	}
	$slug = $slugify($title);
	if ($slug === '') return '/articles/';
	return '/articles/?article=' . rawurlencode($slug);
};

$sanitizeLocalUrl = static function(string $url, string $fallback = '/articles/'): string {
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

$addQueryParams = static function(string $url, array $params) use ($sanitizeLocalUrl): string {
	$baseUrl = $sanitizeLocalUrl($url, '/articles/');
	$parts = parse_url($baseUrl);
	if ($parts === false) return $baseUrl;

	$existing = [];
	if (!empty($parts['query'])) {
		parse_str((string) $parts['query'], $existing);
	}

	foreach ($params as $key => $value) {
		$key = (string) $key;
		if ($key === '') continue;
		$value = trim((string) $value);
		if ($value === '') continue;
		$existing[$key] = $value;
	}

	$path = (string) ($parts['path'] ?? '/articles/');
	$query = count($existing) ? '?' . http_build_query($existing, '', '&', PHP_QUERY_RFC3986) : '';
	$fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . (string) $parts['fragment'] : '';
	return $path . $query . $fragment;
};

$withArticleContext = static function(string $articleUrl, string $source, string $backUrl) use ($addQueryParams, $sanitizeLocalUrl): string {
	$cleanBack = $sanitizeLocalUrl($backUrl, '/articles/');
	return $addQueryParams($articleUrl, [
		'from' => $source,
		'back' => $cleanBack,
	]);
};

$topicMap = [
	'tips' => 'Советы туристам',
	'culture' => 'Культура и традиции',
	'gastronomy' => 'Гастрономия',
	'myths' => 'Мифы и легенды',
	'people' => 'Люди и истории',
];

$topicKeywords = [
	'tips' => ['советы туристам', 'советы'],
	'culture' => ['культура и традиции', 'культура', 'традиции'],
	'gastronomy' => ['гастрономия', 'кухня', 'блюда'],
	'myths' => ['мифы и легенды', 'мифы', 'легенды'],
	'people' => ['люди и истории', 'люди', 'истории'],
];

$resolveTopicSlug = static function(string $topic) use ($topicMap, $topicKeywords, $normalizeTopic): string {
	$normalizedTopic = $normalizeTopic($topic);
	if ($normalizedTopic === '') return '';

	foreach ($topicMap as $slug => $topicLabel) {
		if ($normalizeTopic((string) $topicLabel) === $normalizedTopic) return $slug;
		foreach ((array) ($topicKeywords[$slug] ?? []) as $keyword) {
			if ($keyword === '') continue;
			if (strpos($normalizedTopic, $normalizeTopic((string) $keyword)) !== false) return $slug;
		}
	}

	return '';
};

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$selectedTopicSlug = trim((string) $input->get('topic'));
$selectedArticleSlug = trim((string) $input->get('article'));
$sourceFromRequest = trim((string) $input->get('from'));
$backFromRequestRaw = trim((string) $input->get('back'));
$backFromRequest = $sanitizeLocalUrl($backFromRequestRaw, '');

if ($selectedArticleSlug === '' && preg_match('#^/articles/([^/]+)/?$#', (string) $requestPath, $matches)) {
	$selectedArticleSlug = trim((string) ($matches[1] ?? ''));
}
$selectedArticleSlug = $slugify($selectedArticleSlug);

if (!isset($topicMap[$selectedTopicSlug])) {
	$selectedTopicSlug = '';
}

$sourceTopicSlug = '';
if (strpos($sourceFromRequest, 'topic:') === 0) {
	$sourceTopicSlug = trim(substr($sourceFromRequest, 6));
	if (!isset($topicMap[$sourceTopicSlug])) $sourceTopicSlug = '';
}

$defaultCoverUrl = $config->urls->templates . 'assets/image1.png';
$defaultArticleParagraphs = [
	'Величественные горные цепи и похожие на застывшие облака ледники, высокогорные озера, бурные реки и срывающиеся откуда-то с неба грохочущие водопады поражают воображение, захватывают дух и дарят незабываемые впечатления.',
	'Неслучайно созданная природой живописная красота Северного Кавказа вдохновляла поэтов, художников, композиторов и писателей на создание произведений, ставших частью культурного наследия.',
	'Невозможно сосчитать, сколько на Северном Кавказе природных и рукотворных достопримечательностей. Почти все они овеяны легендами, старинными сказаниями и преданиями народов региона.',
	'Курорты Северного Кавказа принимают гостей круглый год. Каждое время года привлекательно по-своему, поэтому каждый может выбрать формат отдыха: активный, романтический или семейный.',
	'Летом начинается сезон рафтинга и треккинга, работают экотропы и маршруты для любителей высоты. Зимой можно кататься на лыжах, сноуборде и отправляться в снежные походы.',
	'На центральных улицах городов и курортных поселков проходят фестивали, концерты и гастрономические ярмарки. Путешествие по Кавказу всегда насыщено событиями.',
	'Все регионы Северного Кавказа славятся своей национальной кухней, традициями и гостеприимством. В каждом регионе можно найти уникальные маршруты, музеи и природные парки.',
	'Сайт СКФО.РУ поможет вам выбрать направление и спланировать поездку с учетом ваших интересов, бюджета и желаемого уровня активности.'
];

$normalizeArticles = static function(array $articles) use ($buildArticleUrl, $extractArticleSlugFromUrl, $slugify, $splitToParagraphs, $defaultCoverUrl): array {
	$normalized = [];
	foreach ($articles as $article) {
		$title = trim((string) ($article['title'] ?? ''));
		if ($title === '') continue;

		$url = $buildArticleUrl($title, trim((string) ($article['url'] ?? '')));
		$slug = $extractArticleSlugFromUrl($url);
		if ($slug === '') $slug = $slugify($title);

		$paragraphs = [];
		if (!empty($article['paragraphs']) && is_array($article['paragraphs'])) {
			foreach ((array) $article['paragraphs'] as $paragraph) {
				$line = trim((string) $paragraph);
				if ($line !== '') $paragraphs[] = $line;
			}
		} elseif (!empty($article['content'])) {
			$paragraphs = $splitToParagraphs((string) $article['content']);
		}

		$normalized[] = [
			'slug' => $slug,
			'title' => $title,
			'date' => trim((string) ($article['date'] ?? '')),
			'datetime' => trim((string) ($article['datetime'] ?? '')),
			'topic' => trim((string) ($article['topic'] ?? '')),
			'image' => trim((string) ($article['image'] ?? '')) !== '' ? trim((string) $article['image']) : $defaultCoverUrl,
			'url' => $url,
			'paragraphs' => $paragraphs,
		];
	}
	return $normalized;
};

$mapCatalogArticlePage = static function(Page $articlePage) use ($getImageUrlFromValue, $formatRussianDate): array {
	$timestamp = $articlePage->hasField('article_publish_date') ? (int) $articlePage->getUnformatted('article_publish_date') : 0;
	$title = trim((string) $articlePage->title);
	$topic = $articlePage->hasField('article_topic') ? trim((string) $articlePage->article_topic) : '';
	$image = $articlePage->hasField('article_cover_image') ? $getImageUrlFromValue($articlePage->getUnformatted('article_cover_image')) : '';
	$content = $articlePage->hasField('article_content') ? trim((string) $articlePage->article_content) : '';

	return [
		'title' => $title,
		'date' => $timestamp > 0 ? $formatRussianDate($timestamp) : '',
		'datetime' => $timestamp > 0 ? date('Y-m-d', $timestamp) : '',
		'topic' => $topic,
		'image' => $image,
		'url' => '/articles/?article=' . rawurlencode((string) $articlePage->name),
		'content' => $content,
	];
};

$catalogArticlesRaw = [];
if (isset($pages) && $pages instanceof Pages) {
	$catalogArticlePages = $pages->find('template=article, include=all, sort=-article_publish_date, limit=500');
	foreach ($catalogArticlePages as $articlePage) {
		$item = $mapCatalogArticlePage($articlePage);
		if (trim((string) ($item['title'] ?? '')) === '') continue;
		$catalogArticlesRaw[] = $item;
	}
}
$catalogArticles = $normalizeArticles($catalogArticlesRaw);

$todayArticlesRaw = [];
if ($page->hasField('articles_today_refs') && $page->articles_today_refs->count()) {
	foreach ($page->articles_today_refs as $articlePage) {
		if (!$articlePage instanceof Page) continue;
		$item = $mapCatalogArticlePage($articlePage);
		if (trim((string) ($item['title'] ?? '')) === '') continue;
		$todayArticlesRaw[] = $item;
	}
}
if (!count($todayArticlesRaw) && $page->hasField('region_articles_cards') && $page->region_articles_cards->count()) {
	foreach ($page->region_articles_cards as $card) {
		$imageUrl = $card->hasField('region_article_image') ? $getImageUrlFromValue($card->getUnformatted('region_article_image')) : '';

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

		$content = '';
		foreach (['region_article_content', 'region_article_text', 'article_content', 'article_text'] as $fieldName) {
			if ($card->hasField($fieldName)) {
				$content = trim((string) $card->$fieldName);
				if ($content !== '') break;
			}
		}

		if ($title === '' && $topic === '' && $imageUrl === '' && $timestamp <= 0) continue;

		$todayArticlesRaw[] = [
			'title' => $title,
			'date' => $timestamp > 0 ? $formatRussianDate($timestamp) : '',
			'datetime' => $timestamp > 0 ? date('Y-m-d', $timestamp) : '',
			'topic' => $topic,
			'image' => $imageUrl,
			'url' => $url,
			'content' => $content,
		];
	}
}

if (!count($todayArticlesRaw) && count($catalogArticlesRaw)) {
	$todayArticlesRaw = array_slice($catalogArticlesRaw, 0, 4);
}

if (!count($todayArticlesRaw)) {
	$todayArticlesRaw = [
		[
			'title' => 'Как подготовиться к первому путешествию в Дагестан',
			'date' => '1 февраля 2026',
			'datetime' => '2026-02-01',
			'topic' => 'Советы туристам',
			'image' => $defaultCoverUrl,
			'url' => '',
		],
		[
			'title' => 'Душа Кавказа в поэзии и традициях',
			'date' => '22 декабря 2025',
			'datetime' => '2025-12-22',
			'topic' => 'Культура и традиции',
			'image' => $defaultCoverUrl,
			'url' => '',
		],
		[
			'title' => 'Эльбрус - величественная вершина Кавказских гор',
			'date' => '22 декабря 2025',
			'datetime' => '2025-12-22',
			'topic' => 'Мифы и легенды',
			'image' => $defaultCoverUrl,
			'url' => '',
		],
		[
			'title' => 'Популярные блюда народной кухни Кабардино-Балкарии',
			'date' => '1 февраля 2026',
			'datetime' => '2026-02-01',
			'topic' => 'Гастрономия',
			'image' => $defaultCoverUrl,
			'url' => '',
		],
	];
}

$todayArticles = array_slice($normalizeArticles($todayArticlesRaw), 0, 4);
$leadArticle = $todayArticles[0] ?? [
	'slug' => '',
	'title' => '',
	'date' => '',
	'datetime' => '',
	'topic' => '',
	'image' => $defaultCoverUrl,
	'url' => '/articles/',
	'paragraphs' => [],
];
$sideArticles = array_slice($todayArticles, 1, 3);

$isFirstTimeTopic = static function(string $topic) use ($resolveTopicSlug, $normalizeTopic): bool {
	if ($resolveTopicSlug($topic) === 'tips') return true;
	return $normalizeTopic($topic) === $normalizeTopic('Советы туристам');
};

$buildArticleDedupeKey = static function(array $article) use ($extractArticleSlugFromUrl, $slugify): string {
	$title = trim((string) ($article['title'] ?? ''));
	$url = trim((string) ($article['url'] ?? ''));
	$slug = $extractArticleSlugFromUrl($url);
	if ($slug === '' && $title !== '') $slug = $slugify($title);
	if ($slug !== '') return 'slug:' . $slug;
	if ($title !== '') {
		$titleKey = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
		return 'title:' . $titleKey;
	}
	return '';
};

$firstTimeArticlesRaw = [];
$firstTimeArticleKeys = [];
$addFirstTimeArticle = static function(array $article) use (&$firstTimeArticlesRaw, &$firstTimeArticleKeys, $buildArticleDedupeKey): void {
	$title = trim((string) ($article['title'] ?? ''));
	if ($title === '') return;

	$key = $buildArticleDedupeKey($article);
	if ($key !== '' && isset($firstTimeArticleKeys[$key])) return;

	if ($key !== '') $firstTimeArticleKeys[$key] = true;
	$firstTimeArticlesRaw[] = $article;
};

if ($page->hasField('articles_first_time_refs') && $page->articles_first_time_refs->count()) {
	foreach ($page->articles_first_time_refs as $articlePage) {
		if (!$articlePage instanceof Page) continue;
		$item = $mapCatalogArticlePage($articlePage);
		if (!$isFirstTimeTopic((string) ($item['topic'] ?? ''))) continue;
		$addFirstTimeArticle($item);
	}
}

if (count($catalogArticlesRaw)) {
	foreach ($catalogArticlesRaw as $item) {
		if (!$isFirstTimeTopic((string) ($item['topic'] ?? ''))) continue;
		$addFirstTimeArticle($item);
	}
}

if (!count($firstTimeArticlesRaw) && $page->hasField('article_first_time_cards') && $page->article_first_time_cards->count()) {
	foreach ($page->article_first_time_cards as $card) {
		$imageUrl = $card->hasField('article_first_time_image') ? $getImageUrlFromValue($card->getUnformatted('article_first_time_image')) : '';

		$timestamp = 0;
		if ($card->hasField('article_first_time_date')) {
			$dateRaw = $card->getUnformatted('article_first_time_date');
			if (is_numeric($dateRaw)) {
				$timestamp = (int) $dateRaw;
			} elseif (is_string($dateRaw)) {
				$timestamp = strtotime($dateRaw) ?: 0;
			}
		}

		$title = $card->hasField('article_first_time_title') ? trim((string) $card->article_first_time_title) : '';
		$topic = $card->hasField('article_first_time_topic') ? trim((string) $card->article_first_time_topic) : '';
		$url = $card->hasField('article_first_time_url') ? trim((string) $card->article_first_time_url) : '';

		$content = '';
		foreach (['article_first_time_content', 'article_first_time_text', 'article_content', 'article_text'] as $fieldName) {
			if ($card->hasField($fieldName)) {
				$content = trim((string) $card->$fieldName);
				if ($content !== '') break;
			}
		}

		if ($title === '' && $topic === '' && $imageUrl === '' && $timestamp <= 0) continue;
		$resolvedTopic = $topic !== '' ? $topic : 'Советы туристам';
		if (!$isFirstTimeTopic($resolvedTopic)) continue;

		$addFirstTimeArticle([
			'title' => $title,
			'date' => $timestamp > 0 ? $formatRussianDate($timestamp) : '',
			'datetime' => $timestamp > 0 ? date('Y-m-d', $timestamp) : '',
			'topic' => $resolvedTopic,
			'image' => $imageUrl,
			'url' => $url,
			'content' => $content,
		]);
	}
}

if (!count($firstTimeArticlesRaw)) {
	$firstTimeArticlesRaw = [
		[
			'title' => 'Как подготовиться к первому путешествию в Дагестан',
			'date' => '22 декабря 2025',
			'datetime' => '2025-12-22',
			'topic' => 'Советы туристам',
			'image' => $defaultCoverUrl,
			'url' => '',
		],
		[
			'title' => 'Что взять с собой в поездку по Кавказу',
			'date' => '18 декабря 2025',
			'datetime' => '2025-12-18',
			'topic' => 'Советы туристам',
			'image' => $defaultCoverUrl,
			'url' => '',
		],
		[
			'title' => 'Как выбрать сезон для первой поездки',
			'date' => '12 декабря 2025',
			'datetime' => '2025-12-12',
			'topic' => 'Советы туристам',
			'image' => $defaultCoverUrl,
			'url' => '',
		],
		[
			'title' => 'Маршрут на 3 дня для новичка',
			'date' => '9 декабря 2025',
			'datetime' => '2025-12-09',
			'topic' => 'Советы туристам',
			'image' => $defaultCoverUrl,
			'url' => '',
		],
	];
}

$firstTimeArticles = $normalizeArticles($firstTimeArticlesRaw);

$regionCatalogRaw = [];
if (isset($pages) && $pages instanceof Pages) {
	$regionPages = $pages->find('template=region, include=all, limit=200');
	foreach ($regionPages as $regionPage) {
		if (!$regionPage->hasField('region_articles_cards') || !$regionPage->region_articles_cards->count()) continue;
		foreach ($regionPage->region_articles_cards as $card) {
			$imageUrl = $card->hasField('region_article_image') ? $getImageUrlFromValue($card->getUnformatted('region_article_image')) : '';

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

			$content = '';
			foreach (['region_article_content', 'region_article_text', 'article_content', 'article_text'] as $fieldName) {
				if ($card->hasField($fieldName)) {
					$content = trim((string) $card->$fieldName);
					if ($content !== '') break;
				}
			}

			if ($title === '' && $topic === '' && $imageUrl === '' && $timestamp <= 0) continue;

			$regionCatalogRaw[] = [
				'title' => $title,
				'date' => $timestamp > 0 ? $formatRussianDate($timestamp) : '',
				'datetime' => $timestamp > 0 ? date('Y-m-d', $timestamp) : '',
				'topic' => $topic,
				'image' => $imageUrl,
				'url' => $url,
				'content' => $content,
			];
		}
	}
}
$regionCatalogArticles = $normalizeArticles($regionCatalogRaw);

$articlesBySlug = [];
foreach (array_merge($todayArticles, $firstTimeArticles) as $article) {
	$slug = trim((string) ($article['slug'] ?? ''));
	if ($slug === '') continue;
	if (!isset($articlesBySlug[$slug])) {
		$articlesBySlug[$slug] = $article;
	}
}

foreach (array_merge($regionCatalogArticles, $catalogArticles) as $article) {
	$slug = trim((string) ($article['slug'] ?? ''));
	if ($slug === '') continue;

	if (!isset($articlesBySlug[$slug])) {
		$articlesBySlug[$slug] = $article;
		continue;
	}

	$existing = $articlesBySlug[$slug];
	if (($existing['image'] ?? '') === $defaultCoverUrl && ($article['image'] ?? '') !== '' && ($article['image'] ?? '') !== $defaultCoverUrl) {
		$existing['image'] = $article['image'];
	}
	if (trim((string) ($existing['date'] ?? '')) === '' && trim((string) ($article['date'] ?? '')) !== '') {
		$existing['date'] = $article['date'];
		$existing['datetime'] = $article['datetime'] ?? '';
	}
	if (trim((string) ($existing['topic'] ?? '')) === '' && trim((string) ($article['topic'] ?? '')) !== '') {
		$existing['topic'] = $article['topic'];
	}
	if (!count((array) ($existing['paragraphs'] ?? [])) && count((array) ($article['paragraphs'] ?? []))) {
		$existing['paragraphs'] = $article['paragraphs'];
	}
	if (trim((string) ($existing['url'] ?? '')) === '/articles/' && trim((string) ($article['url'] ?? '')) !== '') {
		$existing['url'] = $article['url'];
	}
	$articlesBySlug[$slug] = $existing;
}

$allArticles = array_values($articlesBySlug);

$selectedArticle = null;
if ($selectedArticleSlug !== '' && isset($articlesBySlug[$selectedArticleSlug])) {
	$selectedArticle = $articlesBySlug[$selectedArticleSlug];
}

if ($selectedArticleSlug !== '' && $selectedArticle === null) {
	$fallbackTitle = trim(str_replace('-', ' ', $selectedArticleSlug));
	$selectedArticle = [
		'slug' => $selectedArticleSlug,
		'title' => $fallbackTitle !== '' ? $fallbackTitle : 'Статья',
		'date' => '',
		'datetime' => '',
		'topic' => '',
		'image' => $defaultCoverUrl,
		'url' => '/articles/?article=' . rawurlencode($selectedArticleSlug),
		'paragraphs' => $defaultArticleParagraphs,
	];
}

if ($selectedArticle && !count((array) ($selectedArticle['paragraphs'] ?? []))) {
	$selectedArticle['paragraphs'] = $defaultArticleParagraphs;
}

$topicArticles = [];
if ($selectedArticle === null && $selectedTopicSlug !== '') {
	$keywords = array_map($normalizeTopic, (array) ($topicKeywords[$selectedTopicSlug] ?? []));
	foreach ($allArticles as $article) {
		$normalizedTopic = $normalizeTopic((string) ($article['topic'] ?? ''));
		foreach ($keywords as $keyword) {
			if ($keyword !== '' && $normalizedTopic !== '' && strpos($normalizedTopic, $keyword) !== false) {
				$topicArticles[] = $article;
				break;
			}
		}
	}

	if (!count($topicArticles)) {
		$fallbackTitleByTopic = [
			'tips' => 'Как подготовиться к первому путешествию в Дагестан',
			'culture' => 'Душа Кавказа в поэзии и традициях',
			'gastronomy' => 'Популярные блюда народной кухни Кабардино-Балкарии',
			'myths' => 'Эльбрус - величественная вершина Кавказских гор',
			'people' => 'Люди Кавказа: истории и традиции семей',
		];
		$fallbackTitle = (string) ($fallbackTitleByTopic[$selectedTopicSlug] ?? 'Полезная статья о путешествии по Кавказу');
		$topicArticles[] = [
			'slug' => $slugify($fallbackTitle),
			'title' => $fallbackTitle,
			'date' => '1 февраля 2026',
			'datetime' => '2026-02-01',
			'topic' => (string) ($topicMap[$selectedTopicSlug] ?? ''),
			'image' => $defaultCoverUrl,
			'url' => $buildArticleUrl($fallbackTitle),
			'paragraphs' => [],
		];
	}

	$seed = $topicArticles;
	$seedCount = count($seed);
	while ($seedCount > 0 && count($topicArticles) < 6) {
		$topicArticles[] = $seed[count($topicArticles) % $seedCount];
	}
	$topicArticles = array_slice($topicArticles, 0, 6);
}

$articlesListUrl = '/articles/';
$listSource = 'articles';
if ($selectedTopicSlug !== '') {
	$articlesListUrl = '/articles/?topic=' . rawurlencode($selectedTopicSlug);
	$listSource = 'topic:' . $selectedTopicSlug;
}

$detailRootLabel = 'Статьи';
$detailRootUrl = '/articles/';
$detailMiddleLabel = '';
$detailMiddleUrl = '';

if ($selectedArticle) {
	if ($sourceFromRequest === 'home') {
		$detailRootLabel = 'Главная';
		$detailRootUrl = '/';
	} else {
		$detailRootLabel = 'Статьи';
		$detailRootUrl = '/articles/';
	}

	if ($sourceTopicSlug !== '' && isset($topicMap[$sourceTopicSlug])) {
		$detailMiddleLabel = (string) $topicMap[$sourceTopicSlug];
		$detailMiddleUrl = '/articles/?topic=' . rawurlencode($sourceTopicSlug);
	}
}

$articleBackUrl = '/articles/';
if ($selectedArticle) {
	if ($backFromRequest !== '') {
		$articleBackUrl = $backFromRequest;
	} elseif ($sourceFromRequest === 'home') {
		$articleBackUrl = '/';
	} elseif ($sourceTopicSlug !== '' && isset($topicMap[$sourceTopicSlug])) {
		$articleBackUrl = '/articles/?topic=' . rawurlencode($sourceTopicSlug);
	} else {
		$articleBackUrl = '/articles/';
	}
}

$forumTitle = 'Форум СКФО';
$forumSubtitle = "Делимся опытом и помогаем\nдруг другу планировать поездки";
$forumButtonText = 'Присоединиться';
$forumImageUrl = $config->urls->templates . 'assets/image1.png';
?>

<div id="content" class="articles-page">
	<?php if ($selectedArticle): ?>
		<section class="article-hero-strip">
			<div class="container article-breadcrumb-row">
				<a class="article-back-btn" href="<?php echo $sanitizer->entities($articleBackUrl); ?>" aria-label="Назад к предыдущей странице"></a>
				<div class="article-breadcrumb">
					<a href="<?php echo $sanitizer->entities($detailRootUrl); ?>"><?php echo $sanitizer->entities($detailRootLabel); ?></a>
					<?php if ($detailMiddleLabel !== '' && $detailMiddleUrl !== ''): ?>
						<span class="article-breadcrumb-sep">-></span>
						<a href="<?php echo $sanitizer->entities($detailMiddleUrl); ?>"><?php echo $sanitizer->entities($detailMiddleLabel); ?></a>
					<?php endif; ?>
					<span class="article-breadcrumb-sep">-></span>
					<span class="article-breadcrumb-current"><?php echo $sanitizer->entities((string) ($selectedArticle['title'] ?? '')); ?></span>
				</div>
			</div>
		</section>

		<section class="article-detail-section">
			<div class="container">
				<div class="article-detail-cover" style="background-image: url('<?php echo htmlspecialchars((string) ($selectedArticle['image'] ?? $defaultCoverUrl), ENT_QUOTES, 'UTF-8'); ?>');"></div>
				<h1 class="article-detail-title"><?php echo $sanitizer->entities((string) ($selectedArticle['title'] ?? '')); ?></h1>
				<?php if (!empty($selectedArticle['date'])): ?>
					<time class="article-detail-date" datetime="<?php echo $sanitizer->entities((string) ($selectedArticle['datetime'] ?? '')); ?>"><?php echo $sanitizer->entities((string) $selectedArticle['date']); ?></time>
				<?php endif; ?>

				<?php
				$articleParagraphs = (array) ($selectedArticle['paragraphs'] ?? []);
				if (!count($articleParagraphs)) $articleParagraphs = $defaultArticleParagraphs;
				$middle = (int) ceil(count($articleParagraphs) / 2);
				$leftParagraphs = array_slice($articleParagraphs, 0, $middle);
				$rightParagraphs = array_slice($articleParagraphs, $middle);
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
			</div>
		</section>
	<?php else: ?>
		<section class="articles-hero">
			<div class="container">
				<h1 class="articles-title">Полезное<br />для поездок</h1>
				<div class="articles-hero-tags" aria-label="Категории статей">
					<?php foreach ($topicMap as $topicSlug => $topicTitle): ?>
						<a class="articles-hero-tag<?php echo $selectedTopicSlug === $topicSlug ? ' is-active' : ''; ?>" href="/articles/?topic=<?php echo $sanitizer->entities($topicSlug); ?>">
							<?php echo $sanitizer->entities($topicTitle); ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<?php if ($selectedTopicSlug !== ''): ?>
			<section class="section section--articles-topic">
				<div class="container">
					<h2 class="articles-topic-title"><?php echo $sanitizer->entities((string) ($topicMap[$selectedTopicSlug] ?? 'Статьи')); ?></h2>
						<div class="articles-topic-grid">
							<?php foreach ($topicArticles as $article): ?>
								<a class="articles-topic-card" href="<?php echo $sanitizer->entities($withArticleContext((string) ($article['url'] ?? '/articles/'), $listSource, $articlesListUrl)); ?>">
									<div class="articles-topic-card-media" style="background-image: url('<?php echo htmlspecialchars((string) ($article['image'] ?? $defaultCoverUrl), ENT_QUOTES, 'UTF-8'); ?>');"></div>
									<div class="articles-topic-card-body">
									<time class="articles-topic-card-date" datetime="<?php echo $sanitizer->entities((string) ($article['datetime'] ?? '')); ?>">
										<?php echo $sanitizer->entities((string) ($article['date'] ?? '')); ?>
									</time>
									<h3 class="articles-topic-card-title"><?php echo $sanitizer->entities((string) ($article['title'] ?? '')); ?></h3>
									<p class="articles-topic-card-tag"><?php echo $sanitizer->entities((string) ($article['topic'] ?? '')); ?></p>
								</div>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php else: ?>
			<section class="section section--region-articles section--articles-read">
				<div class="container">
					<h2 class="region-articles-title">Читают сегодня</h2>
					<div class="region-articles-card">
						<a class="region-article region-article--lead" href="<?php echo $sanitizer->entities($withArticleContext((string) $leadArticle['url'], $listSource, $articlesListUrl)); ?>">
							<div class="region-article-media" style="background-image: url('<?php echo htmlspecialchars((string) $leadArticle['image'], ENT_QUOTES, 'UTF-8'); ?>');"></div>
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
								<a class="region-article region-article--side" href="<?php echo $sanitizer->entities($withArticleContext((string) $article['url'], $listSource, $articlesListUrl)); ?>">
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

			<section class="section section--articles-first-time">
				<div class="container">
					<h2 class="articles-first-title">Впервые на Кавказе ?</h2>
					<div class="articles-first-grid">
						<?php foreach ($firstTimeArticles as $article): ?>
							<a class="articles-first-card" href="<?php echo $sanitizer->entities($withArticleContext((string) $article['url'], $listSource, $articlesListUrl)); ?>">
								<div class="articles-first-card-media" style="background-image: url('<?php echo htmlspecialchars((string) $article['image'], ENT_QUOTES, 'UTF-8'); ?>');"></div>
								<div class="articles-first-card-content">
									<time class="articles-first-card-date" datetime="<?php echo $sanitizer->entities((string) ($article['datetime'] ?? '')); ?>">
										<?php echo $sanitizer->entities((string) $article['date']); ?>
									</time>
									<h3 class="articles-first-card-title"><?php echo $sanitizer->entities((string) $article['title']); ?></h3>
									<p class="articles-first-card-tag"><?php echo $sanitizer->entities((string) $article['topic']); ?></p>
								</div>
							</a>
						<?php endforeach; ?>
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
		<?php endif; ?>
	<?php endif; ?>
</div>
