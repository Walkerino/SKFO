<?php namespace ProcessWire;

$normalizeText = static function(string $value): string {
	$decoded = $value;
	for ($i = 0; $i < 3; $i++) {
		$next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		if ($next === $decoded) break;
		$decoded = $next;
	}
	$decoded = trim(str_replace(["\r", "\n"], ' ', $decoded));
	$decoded = preg_replace('/\s+/u', ' ', $decoded) ?? $decoded;
	return trim($decoded);
};

$toLower = static function(string $value): string {
	return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
};

$firstLetter = static function(string $value): string {
	$value = trim($value);
	if ($value === '') return '?';
	return function_exists('mb_substr') ? mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($value, 0, 1));
};

$avatarColorKeys = ['blue', 'yellow', 'gray', 'red', 'green', 'cyan', 'purple'];
$avatarClassMap = [
	'blue' => 'is-blue',
	'yellow' => 'is-yellow',
	'gray' => 'is-gray',
	'red' => 'is-red',
	'green' => 'is-green',
	'cyan' => 'is-cyan',
	'purple' => 'is-purple',
];

$truncateText = static function(string $value, int $maxLength = 240): string {
	$value = trim(strip_tags($value));
	if ($value === '') return '';
	$length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
	if ($length <= $maxLength) return $value;
	$slice = function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
	return rtrim($slice) . '...';
};

$getImageUrlFromValue = static function($imageValue): string {
	if ($imageValue instanceof Pageimage) return (string) $imageValue->url;
	if ($imageValue instanceof Pageimages && $imageValue->count()) return (string) $imageValue->first()->url;
	return '';
};

$getComboValue = static function($value, array $keys) use ($normalizeText): string {
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
		if ($result !== '') return $normalizeText($result);
	}

	if (is_string($value) && $value !== '') {
		$decoded = json_decode($value, true);
		if (is_array($decoded)) {
			foreach ($keys as $key) {
				$key = trim((string) $key);
				if ($key === '') continue;
				$result = trim((string) ($decoded[$key] ?? ''));
				if ($result !== '') return $normalizeText($result);
			}
		}
	}

	return '';
};

$getGuideCity = static function(Page $guidePage) use ($normalizeText, $getComboValue): string {
	$city = '';
	if ($guidePage->hasField('address')) {
		$city = $getComboValue($guidePage->getUnformatted('address'), ['city', 'i2']);
	}
	if ($city === '' && $guidePage->hasField('region')) {
		$regionValue = $guidePage->getUnformatted('region');
		if ($regionValue instanceof Page) {
			$city = $normalizeText((string) $regionValue->title);
		} elseif (is_scalar($regionValue)) {
			$city = $normalizeText((string) $regionValue);
		}
	}
	if ($city === '') $city = 'СКФО';
	return $city;
};

$getGuideDescription = static function(Page $guidePage) use ($normalizeText, $truncateText): string {
	$rawText = '';
	foreach (['summary', 'content'] as $fieldName) {
		if (!$guidePage->hasField($fieldName)) continue;
		$rawText = trim((string) $guidePage->getUnformatted($fieldName));
		if ($rawText !== '') break;
	}
	$rawText = $normalizeText($rawText);
	if ($rawText === '') {
		return 'Описание гида скоро появится.';
	}
	return $truncateText($rawText, 260);
};

$getGuideImage = static function(Page $guidePage) use ($getImageUrlFromValue): string {
	foreach (['logo', 'images'] as $fieldName) {
		if (!$guidePage->hasField($fieldName)) continue;
		$imageUrl = $getImageUrlFromValue($guidePage->getUnformatted($fieldName));
		if ($imageUrl !== '') return $imageUrl;
	}
	return '';
};

$collectGuideTours = static function(Page $guidePage) use ($pages): array {
	$tourMap = [];

	$appendTour = static function($tourPage) use (&$tourMap): void {
		if (!$tourPage instanceof Page || !$tourPage->id) return;
		if ($tourPage->template && $tourPage->template->name !== 'tour') return;
		$tourMap[(int) $tourPage->id] = $tourPage;
	};

	if ($guidePage->hasField('tours')) {
		$guideTours = $guidePage->getUnformatted('tours');
		if ($guideTours instanceof PageArray) {
			foreach ($guideTours as $tourPage) $appendTour($tourPage);
		}
	}

	if (isset($pages) && $pages instanceof Pages) {
		try {
				$linkedTours = $pages->find('template=tour, include=all, status<8192, check_access=0, sort=title, limit=300, guide=' . (int) $guidePage->id);
			foreach ($linkedTours as $tourPage) $appendTour($tourPage);
		} catch (\Throwable $e) {
			// Ignore selector issues if legacy field is missing.
		}
	}

	return array_values($tourMap);
};

$getGuideReviewStats = static function(array $tourIds) use ($database): array {
	$tourIds = array_values(array_unique(array_filter(array_map('intval', $tourIds), static fn(int $id): bool => $id > 0)));
	if (!count($tourIds) || !($database instanceof WireDatabasePDO)) {
		return ['count' => 0, 'avg' => 0.0];
	}

	$placeholders = implode(',', array_fill(0, count($tourIds), '?'));
	$sql = "SELECT COUNT(*) AS cnt, AVG(rating) AS avg_rating FROM tour_reviews WHERE moderation_status='approved' AND page_id IN ($placeholders)";
	$stmt = $database->prepare($sql);
	foreach ($tourIds as $index => $tourId) {
		$stmt->bindValue($index + 1, $tourId, \PDO::PARAM_INT);
	}
	$stmt->execute();
	$row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

	return [
		'count' => max(0, (int) ($row['cnt'] ?? 0)),
		'avg' => max(0.0, (float) ($row['avg_rating'] ?? 0)),
	];
};

$plural = static function(int $value, string $one, string $few, string $many): string {
	$value = abs($value) % 100;
	$last = $value % 10;
	if ($value > 10 && $value < 20) return $many;
	if ($last > 1 && $last < 5) return $few;
	if ($last === 1) return $one;
	return $many;
};

$formatRating = static function(float $value): string {
	return str_replace('.', ',', number_format($value, 1, '.', ''));
};

$isCmsEditor = isset($user) && $user instanceof User && $user->isLoggedin() && ($user->isSuperuser() || $user->hasPermission('page-edit'));

$guideMap = [];
$appendGuide = static function($guidePage) use (&$guideMap, $isCmsEditor): void {
	if (!$guidePage instanceof Page || !$guidePage->id) return;
	if (!$guidePage->template || $guidePage->template->name !== 'guide') return;
	if (!$isCmsEditor && $guidePage->hasStatus(Page::statusUnpublished)) return;
	$guideMap[(int) $guidePage->id] = $guidePage;
};

if ($page->hasField('guide')) {
	$guideFieldValue = $page->getUnformatted('guide');
	if ($guideFieldValue instanceof PageArray) {
		foreach ($guideFieldValue as $guidePage) $appendGuide($guidePage);
	} elseif ($guideFieldValue instanceof Page) {
		$appendGuide($guideFieldValue);
	}
}

if (!count($guideMap)) {
	$childrenSelector = $isCmsEditor
		? 'template=guide, include=all, status<8192, sort=title'
		: 'template=guide, sort=title';
	foreach ($page->children($childrenSelector) as $childGuidePage) {
		$appendGuide($childGuidePage);
	}
}

if (!count($guideMap) && isset($pages) && $pages instanceof Pages) {
	$fallbackSelector = $isCmsEditor
		? 'template=guide, include=all, status<8192, check_access=0, sort=title, limit=500'
		: 'template=guide, sort=title, limit=500';
	foreach ($pages->find($fallbackSelector) as $fallbackGuidePage) {
		$appendGuide($fallbackGuidePage);
	}
}

$guideCards = [];
foreach ($guideMap as $guidePage) {
	$guideName = $normalizeText((string) $guidePage->title);
	if ($guideName === '') continue;

	$tourPages = $collectGuideTours($guidePage);
	$tourIds = array_map(static fn(Page $tourPage): int => (int) $tourPage->id, $tourPages);
	$reviewStats = $getGuideReviewStats($tourIds);
	$tourCount = count($tourPages);

	$guideCards[] = [
		'name' => $guideName,
		'city' => $getGuideCity($guidePage),
		'description' => $getGuideDescription($guidePage),
		'image' => $getGuideImage($guidePage),
		'url' => (string) $guidePage->url,
		'tour_count' => $tourCount,
		'review_count' => (int) ($reviewStats['count'] ?? 0),
		'rating' => (float) ($reviewStats['avg'] ?? 0.0),
	];
}

usort($guideCards, static function(array $left, array $right) use ($toLower): int {
	return strcmp($toLower((string) ($left['name'] ?? '')), $toLower((string) ($right['name'] ?? '')));
});

$totalGuides = count($guideCards);
$guidesCountLabel = $totalGuides . ' ' . $plural($totalGuides, 'гид', 'гида', 'гидов');
$pageTitle = trim((string) $page->title);
if ($pageTitle === '') $pageTitle = 'Гиды Кавказа';
?>

<div id="content" class="guides-page">
	<section class="reviews-hero">
		<div class="container hero-inner reviews-hero-inner">
			<h1 class="reviews-title">ГИДЫ<br />КАВКАЗА</h1>
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
					<a class="hero-tab is-active" href="/guides/" role="tab" aria-selected="true">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/human.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Гиды</span>
					</a>
					<a class="hero-tab" href="/regions/" role="tab" aria-selected="false">
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

	<section class="section section--guides-list">
		<div class="container">
			<!-- <nav class="guides-breadcrumb" aria-label="Хлебные крошки">
				<a href="/">Все экскурсии</a>
				<span aria-hidden="true">›</span>
				<a href="/regions/">Кавказ</a>
				<span aria-hidden="true">›</span>
				<span><?php echo $sanitizer->entities($pageTitle); ?></span>
			</nav> -->

			<!-- <h2 class="section-title guides-page-title"><?php echo $sanitizer->entities($pageTitle); ?></h2>
			<a class="guides-all-tours-btn" href="/">Посмотреть все экскурсии</a> -->
			<p class="guides-page-count"><?php echo $sanitizer->entities($guidesCountLabel); ?> на Кавказе</p>

			<?php if ($totalGuides > 0): ?>
				<div class="guides-list">
					<?php foreach ($guideCards as $guideCard): ?>
						<?php
						$guideUrl = trim((string) ($guideCard['url'] ?? ''));
						if ($guideUrl === '') $guideUrl = '/guides/';
						$tourCount = max(0, (int) ($guideCard['tour_count'] ?? 0));
						$reviewCount = max(0, (int) ($guideCard['review_count'] ?? 0));
						$ratingValue = max(0.0, (float) ($guideCard['rating'] ?? 0.0));
						$offersLabel = $tourCount . ' ' . $plural($tourCount, 'предложение', 'предложения', 'предложений');
						$reviewsLabel = $reviewCount . ' ' . $plural($reviewCount, 'отзыв', 'отзыва', 'отзывов');
						$guideNameLabel = (string) ($guideCard['name'] ?? '');
						$avatarColorKey = $avatarColorKeys[abs(crc32($guideNameLabel)) % count($avatarColorKeys)];
						$avatarClass = $avatarClassMap[$avatarColorKey];
						?>
						<article class="guide-card">
							<div class="guide-card-main">
								<a class="guide-card-avatar-link" href="<?php echo $sanitizer->entities($guideUrl); ?>">
									<?php if ((string) ($guideCard['image'] ?? '') !== ''): ?>
										<img class="guide-card-avatar" src="<?php echo htmlspecialchars((string) ($guideCard['image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $sanitizer->entities('Фото гида ' . $guideNameLabel); ?>" />
									<?php else: ?>
										<span class="guide-card-avatar-placeholder review-avatar <?php echo $avatarClass; ?>" aria-hidden="true"><?php echo $sanitizer->entities($firstLetter($guideNameLabel)); ?></span>
									<?php endif; ?>
								</a>
								<div class="guide-card-body">
									<h2 class="guide-card-name"><a href="<?php echo $sanitizer->entities($guideUrl); ?>"><?php echo $sanitizer->entities($guideNameLabel); ?></a></h2>
									<p class="guide-card-city"><?php echo $sanitizer->entities('Гид в ' . (string) ($guideCard['city'] ?? 'СКФО')); ?></p>
									<div class="guide-card-meta">
										<span class="guide-card-rating">★ <?php echo $sanitizer->entities($formatRating($ratingValue)); ?></span>
										<span class="guide-card-reviews"><?php echo $sanitizer->entities($reviewsLabel); ?></span>
									</div>
									<p class="guide-card-description"><?php echo $sanitizer->entities((string) ($guideCard['description'] ?? '')); ?></p>
								</div>
							</div>
							<div class="guide-card-action">
								<a class="guide-card-offers-btn" href="<?php echo $sanitizer->entities($guideUrl); ?>"><?php echo $sanitizer->entities($offersLabel); ?></a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<div class="guides-empty">Список гидов пока пуст. Добавьте гидов в разделе контента.</div>
			<?php endif; ?>
		</div>
	</section>
</div>
