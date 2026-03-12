<?php namespace ProcessWire;

require_once __DIR__ . '/_reviews_moderation.php';

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

$avatarColorKeys = ['blue', 'yellow', 'gray', 'red'];
$avatarClassMap = [
	'blue' => 'is-blue',
	'yellow' => 'is-yellow',
	'gray' => 'is-gray',
	'red' => 'is-red',
];

$monthNames = [
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

$truncateText = static function(string $value, int $maxLength): string {
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

$getGuideDescription = static function(Page $guidePage) use ($sanitizer): string {
	$description = '';
	foreach (['content', 'summary'] as $fieldName) {
		if (!$guidePage->hasField($fieldName)) continue;
		$description = trim((string) $guidePage->getUnformatted($fieldName));
		if ($description !== '') break;
	}
	$description = trim((string) $sanitizer->purify($description));
	if ($description === '') {
		$description = '<p>Подробное описание гида пока не заполнено.</p>';
	}
	return $description;
};

$getGuideImage = static function(Page $guidePage) use ($getImageUrlFromValue, $config): string {
	foreach (['logo', 'images'] as $fieldName) {
		if (!$guidePage->hasField($fieldName)) continue;
		$imageUrl = $getImageUrlFromValue($guidePage->getUnformatted($fieldName));
		if ($imageUrl !== '') return $imageUrl;
	}
	return $config->urls->templates . 'assets/image1.png';
};

$getFirstTextFromPage = static function(Page $item, array $fields) use ($normalizeText): string {
	foreach ($fields as $fieldName) {
		if (!$item->hasField($fieldName)) continue;
		$value = $normalizeText((string) $item->getUnformatted($fieldName));
		if ($value !== '') return $value;
	}
	return '';
};

$extractTourPriceAmount = static function(string $raw): int {
	$raw = trim($raw);
	if ($raw === '') return 0;
	if (preg_match('/(\d[\d\s]{1,})/u', strip_tags($raw), $matches) !== 1) return 0;
	$digits = preg_replace('/[^\d]+/', '', (string) ($matches[1] ?? ''));
	if ($digits === '') return 0;
	return (int) $digits;
};

$formatTourPrice = static function(Page $tourPage) use ($extractTourPriceAmount, $normalizeText): string {
	if (!$tourPage->hasField('tour_price')) return '';
	$raw = (string) $tourPage->getUnformatted('tour_price');
	$amount = $extractTourPriceAmount($raw);
	if ($amount > 0) return number_format($amount, 0, '', ' ') . ' ₽';
	return $normalizeText(strip_tags($raw));
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
			$linkedTours = $pages->find('template=tour, include=all, check_access=0, sort=title, limit=300, guide=' . (int) $guidePage->id);
			foreach ($linkedTours as $tourPage) $appendTour($tourPage);
		} catch (\Throwable $e) {
			// Ignore selector issues if legacy field is missing.
		}
	}

	return array_values($tourMap);
};

$getGuideReviewRows = static function(array $tourIds, int $limit = 8) use ($database): array {
	$tourIds = array_values(array_unique(array_filter(array_map('intval', $tourIds), static fn(int $id): bool => $id > 0)));
	if (!count($tourIds) || !($database instanceof WireDatabasePDO)) return [];

	$limit = max(1, min(30, $limit));
	$placeholders = implode(',', array_fill(0, count($tourIds), '?'));
	$sql = "SELECT id, page_id, author, review_text, rating, avatar_color, photos_json, created_at FROM tour_reviews WHERE moderation_status='approved' AND page_id IN ($placeholders) ORDER BY created_at DESC, id DESC LIMIT {$limit}";
	$stmt = $database->prepare($sql);
	foreach ($tourIds as $index => $tourId) {
		$stmt->bindValue($index + 1, $tourId, \PDO::PARAM_INT);
	}
	$stmt->execute();
	return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
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

$formatReviewDateLabel = static function(string $value) use ($monthNames): string {
	$timestamp = strtotime(trim($value));
	if ($timestamp === false || $timestamp <= 0) return '';

	$year = (int) date('Y', $timestamp);
	$currentYear = (int) date('Y');
	$month = (int) date('n', $timestamp);
	$day = (int) date('j', $timestamp);
	if ($year === $currentYear && isset($monthNames[$month])) {
		return $day . ' ' . $monthNames[$month];
	}

	return date('d.m.Y', $timestamp);
};

$formatReviewDateIso = static function(string $value): string {
	$timestamp = strtotime(trim($value));
	if ($timestamp === false || $timestamp <= 0) return '';
	return date('Y-m-d', $timestamp);
};

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

if ($database instanceof WireDatabasePDO) {
	try {
		skfoReviewsEnsureTable($database, 'tour_reviews');
		skfoReviewsBackfillHashes($database, 'tour_reviews', 50);
	} catch (\Throwable $e) {
		$log->save('errors', 'guide page reviews table ensure error: ' . $e->getMessage());
	}
}

$guideName = $normalizeText((string) $page->title);
if ($guideName === '') $guideName = 'Гид';
$guideCity = $getGuideCity($page);
$guideImageUrl = $getGuideImage($page);
$guideDescription = $getGuideDescription($page);

$tourPages = $collectGuideTours($page);
$tourIds = array_map(static fn(Page $tourPage): int => (int) $tourPage->id, $tourPages);
$reviewStats = $getGuideReviewStats($tourIds);
$reviewRows = $getGuideReviewRows($tourIds, 8);

$tourCards = [];
$tourTitleById = [];
$tourUrlById = [];
foreach ($tourPages as $tourPage) {
	$tourTitle = $getFirstTextFromPage($tourPage, ['tour_title']);
	if ($tourTitle === '') $tourTitle = $normalizeText((string) $tourPage->title);
	if ($tourTitle === '') continue;

	$tourImageUrl = '';
	foreach (['tour_cover_image', 'images'] as $imageField) {
		if (!$tourPage->hasField($imageField)) continue;
		$tourImageUrl = $getImageUrlFromValue($tourPage->getUnformatted($imageField));
		if ($tourImageUrl !== '') break;
	}
	if ($tourImageUrl === '') $tourImageUrl = $config->urls->templates . 'assets/image1.png';

	$tourRegion = $getFirstTextFromPage($tourPage, ['tour_region', 'region']);
	$tourDuration = $getFirstTextFromPage($tourPage, ['tour_duration']);
	$tourPrice = $formatTourPrice($tourPage);

	$tourCards[] = [
		'id' => (int) $tourPage->id,
		'title' => $tourTitle,
		'region' => $tourRegion,
		'duration' => $tourDuration,
		'price' => $tourPrice,
		'image' => $tourImageUrl,
		'url' => (string) $tourPage->url,
	];
	$tourTitleById[(int) $tourPage->id] = $tourTitle;
	$tourUrlById[(int) $tourPage->id] = (string) $tourPage->url;
}

$reviews = [];
$travelerPhotosMap = [];
foreach ($reviewRows as $reviewRow) {
	$tourId = (int) ($reviewRow['page_id'] ?? 0);
	$reviewText = trim((string) ($reviewRow['review_text'] ?? ''));
	$reviewPhotos = skfoReviewsDecodePhotos((string) ($reviewRow['photos_json'] ?? ''));
	foreach ($reviewPhotos as $photoUrl) {
		$travelerPhotosMap[$photoUrl] = true;
	}
	$reviews[] = [
		'author' => $normalizeText((string) ($reviewRow['author'] ?? 'Гость')),
		'rating' => max(1, min(5, (int) ($reviewRow['rating'] ?? 5))),
		'avatar_color' => trim((string) ($reviewRow['avatar_color'] ?? '')),
		'text' => $reviewText,
		'date_label' => $formatReviewDateLabel((string) ($reviewRow['created_at'] ?? '')),
		'date_iso' => $formatReviewDateIso((string) ($reviewRow['created_at'] ?? '')),
		'tour_title' => $tourTitleById[$tourId] ?? '',
		'tour_url' => $tourUrlById[$tourId] ?? '',
		'photos' => $reviewPhotos,
	];
}

$travelerPhotos = array_keys($travelerPhotosMap);
$travelerPhotosPreview = [];
$travelerPhotosMore = 0;
if (count($travelerPhotos) > 8) {
	$travelerPhotosPreview = array_slice($travelerPhotos, 0, 7);
	$travelerPhotosMore = count($travelerPhotos) - 7;
} else {
	$travelerPhotosPreview = array_slice($travelerPhotos, 0, 8);
}

$articleCards = [];
if (isset($pages) && $pages instanceof Pages) {
	$needle = $toLower($guideName);
	if ($needle !== '') {
		$articlePages = $pages->find('template=article, include=all, check_access=0, sort=-article_publish_date, limit=300');
		foreach ($articlePages as $articlePage) {
			if (!$articlePage instanceof Page || !$articlePage->id) continue;
			$articleTitle = $normalizeText((string) $articlePage->title);
			if ($articleTitle === '') continue;

			$hasExplicitGuideMention = false;
			if ($articlePage->hasField('article_guides')) {
				$linkedGuides = $articlePage->getUnformatted('article_guides');
				if ($linkedGuides instanceof PageArray) {
					foreach ($linkedGuides as $linkedGuide) {
						if ($linkedGuide instanceof Page && (int) $linkedGuide->id === (int) $page->id) {
							$hasExplicitGuideMention = true;
							break;
						}
					}
				} elseif ($linkedGuides instanceof Page) {
					$hasExplicitGuideMention = (int) $linkedGuides->id === (int) $page->id;
				}
			}

			$chunks = [$articleTitle];
			foreach (['article_excerpt', 'summary', 'content', 'article_content'] as $fieldName) {
				if (!$articlePage->hasField($fieldName)) continue;
				$chunks[] = (string) $articlePage->getUnformatted($fieldName);
			}

			if (!$hasExplicitGuideMention) {
				$haystack = $toLower($normalizeText(strip_tags(implode(' ', $chunks))));
				if ($haystack === '' || strpos($haystack, $needle) === false) continue;
			}

			$articleImage = '';
			foreach (['article_cover_image', 'images'] as $imageField) {
				if (!$articlePage->hasField($imageField)) continue;
				$articleImage = $getImageUrlFromValue($articlePage->getUnformatted($imageField));
				if ($articleImage !== '') break;
			}
			if ($articleImage === '') $articleImage = $config->urls->templates . 'assets/image1.png';

			$articleUrl = $appendLocalQueryParams((string) $articlePage->url, [
				'from' => 'guide',
				'back' => (string) $page->url,
			]);

			$articleCards[] = [
				'title' => $articleTitle,
				'summary' => $truncateText($normalizeText((string) ($chunks[1] ?? '')), 140),
				'image' => $articleImage,
				'url' => $articleUrl,
			];

			if (count($articleCards) >= 6) break;
		}
	}
}

$reviewCount = max(0, (int) ($reviewStats['count'] ?? 0));
$tourCount = count($tourCards);
?>

<div id="content" class="guide-page">
	<section class="section section--guide-profile">
		<div class="container">
			<nav class="guides-breadcrumb" aria-label="Хлебные крошки">
				<!-- <a href="/">Все экскурсии</a>
				<span aria-hidden="true">›</span> -->
				<a href="/guides/">Гиды</a>
				<span aria-hidden="true">›</span>
				<span><?php echo $sanitizer->entities($guideName); ?></span>
			</nav>

			<div class="guide-profile-card">
				<img class="guide-profile-avatar" src="<?php echo htmlspecialchars($guideImageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $sanitizer->entities('Фото гида ' . $guideName); ?>" />
				<div class="guide-profile-body">
					<h1 class="section-title guide-profile-name"><?php echo $sanitizer->entities($guideName); ?></h1>
					<p class="guide-profile-city"><?php echo $sanitizer->entities('Гид в ' . $guideCity); ?></p>
					<div class="guide-profile-meta">
						<span>★ <?php echo $sanitizer->entities($formatRating((float) ($reviewStats['avg'] ?? 0))); ?></span>
						<a class="guide-profile-meta-second-child" href="#guide-reviews"><?php echo $sanitizer->entities($reviewCount . ' ' . $plural($reviewCount, 'отзыв', 'отзыва', 'отзывов')); ?></a>
						<span><?php echo $sanitizer->entities($tourCount . ' ' . $plural($tourCount, 'предложение', 'предложения', 'предложений')); ?></span>
					</div>
					<div class="guide-profile-description"><?php echo $guideDescription; ?></div>
				</div>
			</div>
		</div>
	</section>

	<section class="section section--guide-tours">
		<div class="container">
			<h2 class="section-title guide-section-title">Туры гида</h2>
			<?php if (count($tourCards)): ?>
				<div class="guide-tours-grid">
					<?php foreach ($tourCards as $tourCard): ?>
						<article class="guide-tour-card">
							<div class="guide-tour-image" style="background-image: url('<?php echo htmlspecialchars((string) ($tourCard['image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>');"></div>
							<h3 class="guide-tour-title"><?php echo $sanitizer->entities((string) ($tourCard['title'] ?? '')); ?></h3>
							<div class="guide-tour-meta">
								<?php if (!empty($tourCard['region'])): ?><span><?php echo $sanitizer->entities((string) $tourCard['region']); ?></span><?php endif; ?>
								<?php if (!empty($tourCard['duration'])): ?><span><?php echo $sanitizer->entities((string) $tourCard['duration']); ?></span><?php endif; ?>
							</div>
							<div class="guide-tour-footer">
								<?php if (!empty($tourCard['price'])): ?><span class="guide-tour-price"><?php echo $sanitizer->entities((string) $tourCard['price']); ?></span><?php endif; ?>
								<a class="guide-tour-link" href="<?php echo $sanitizer->entities((string) ($tourCard['url'] ?? '/')); ?>">Подробнее</a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<div class="guide-empty">У этого гида пока нет опубликованных туров.</div>
			<?php endif; ?>
		</div>
	</section>

	<section id="guide-reviews" class="section section--guide-reviews" data-guide-reviews-gallery>
		<div class="container">
			<h2 class="section-title guide-section-title"><?php echo $sanitizer->entities($reviewCount . ' отзывов путешественников'); ?></h2>
			<?php if (count($reviews)): ?>
				<div class="guide-reviews-panel">
					<?php if (count($travelerPhotosPreview)): ?>
						<div class="reviews-travel-photos reviews-travel-photos--guide">
							<p class="reviews-travel-photos-title">Фотографии путешественников</p>
							<div class="reviews-travel-photos-grid">
								<?php foreach ($travelerPhotosPreview as $photoUrl): ?>
									<button
										class="reviews-travel-photo reviews-travel-photo-btn"
										type="button"
										data-guide-review-photo
										data-gallery-type="image"
										data-gallery-src="<?php echo htmlspecialchars((string) $photoUrl, ENT_QUOTES, 'UTF-8'); ?>"
										data-gallery-alt="Фото путешественников"
										aria-label="Открыть фото путешественников"
										style="background-image: url('<?php echo htmlspecialchars((string) $photoUrl, ENT_QUOTES, 'UTF-8'); ?>');"
									></button>
								<?php endforeach; ?>
								<?php if ($travelerPhotosMore > 0): ?>
									<?php $moreCover = (string) ($travelerPhotos[7] ?? $travelerPhotos[0]); ?>
									<button
										class="reviews-travel-photo reviews-travel-photo--more reviews-travel-photo-btn"
										type="button"
										data-guide-review-photo
										data-gallery-type="image"
										data-gallery-src="<?php echo htmlspecialchars($moreCover, ENT_QUOTES, 'UTF-8'); ?>"
										data-gallery-alt="Фото путешественников"
										aria-label="Открыть фото путешественников"
										style="background-image: url('<?php echo htmlspecialchars($moreCover, ENT_QUOTES, 'UTF-8'); ?>');"
									>
										<span>+<?php echo $travelerPhotosMore; ?></span>
									</button>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<div class="guide-reviews-list guide-reviews-list--single">
						<?php foreach ($reviews as $review): ?>
							<?php
							$author = (string) ($review['author'] ?? 'Гость');
							$rating = max(1, min(5, (int) ($review['rating'] ?? 5)));
							$starsFilled = str_repeat('★', $rating);
							$starsEmpty = str_repeat('★', 5 - $rating);
							$avatarColorKey = (string) ($review['avatar_color'] ?? '');
							$reviewPhotos = is_array($review['photos'] ?? null) ? $review['photos'] : [];
							if (!isset($avatarClassMap[$avatarColorKey])) {
								$index = abs(crc32($author)) % count($avatarColorKeys);
								$avatarColorKey = $avatarColorKeys[$index];
							}
							$avatarClass = $avatarClassMap[$avatarColorKey];
							?>
							<article class="guide-review-card review-item review-item--detailed">
								<div class="review-head-row">
									<div class="review-top">
										<span class="review-avatar <?php echo $avatarClass; ?>" aria-hidden="true"><?php echo $sanitizer->entities($firstLetter($author)); ?></span>
										<div class="review-meta">
											<div class="review-author-line">
												<strong class="review-author"><?php echo $sanitizer->entities($author); ?></strong>
												<span class="review-stars-inline" aria-label="Оценка <?php echo $rating; ?> из 5">
													<span class="is-filled"><?php echo $starsFilled; ?></span><?php if ($starsEmpty !== ''): ?><span class="is-empty"><?php echo $starsEmpty; ?></span><?php endif; ?>
												</span>
											</div>
											<?php if (!empty($review['tour_title'])): ?>
												<?php if (!empty($review['tour_url'])): ?>
													<a class="review-tour-title" href="<?php echo $sanitizer->entities((string) $review['tour_url']); ?>"><?php echo $sanitizer->entities((string) $review['tour_title']); ?></a>
												<?php else: ?>
													<p class="review-tour-title"><?php echo $sanitizer->entities((string) $review['tour_title']); ?></p>
												<?php endif; ?>
											<?php endif; ?>
										</div>
									</div>
									<?php if (!empty($review['date_label'])): ?>
										<time class="review-date" datetime="<?php echo $sanitizer->entities((string) ($review['date_iso'] ?? '')); ?>"><?php echo $sanitizer->entities((string) $review['date_label']); ?></time>
									<?php endif; ?>
								</div>
								<p class="guide-review-text review-text review-text--detailed"><?php echo nl2br($sanitizer->entities((string) ($review['text'] ?? ''))); ?></p>
								<?php if (count($reviewPhotos)): ?>
									<div class="review-photo-grid">
										<?php foreach (array_slice($reviewPhotos, 0, 8) as $photoIndex => $photoUrl): ?>
											<button
												class="review-photo-item review-photo-item-btn"
												type="button"
												data-guide-review-photo
												data-gallery-type="image"
												data-gallery-src="<?php echo htmlspecialchars((string) $photoUrl, ENT_QUOTES, 'UTF-8'); ?>"
												data-gallery-alt="<?php echo $sanitizer->entities('Фото из отзыва ' . $author . ' #' . ((int) $photoIndex + 1)); ?>"
												aria-label="<?php echo $sanitizer->entities('Открыть фото из отзыва ' . $author); ?>"
												style="background-image: url('<?php echo htmlspecialchars((string) $photoUrl, ENT_QUOTES, 'UTF-8'); ?>');"
											></button>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					</div>
				</div>
			<?php else: ?>
				<div class="guide-empty">Отзывы по турам этого гида пока не опубликованы.</div>
			<?php endif; ?>
		</div>
		<?php if (count($travelerPhotos)): ?>
			<div class="hotel-gallery-lightbox guide-review-lightbox" data-guide-review-modal hidden>
				<div class="hotel-gallery-lightbox-backdrop" data-gallery-close="backdrop"></div>
				<div class="hotel-gallery-lightbox-dialog">
					<button class="hotel-gallery-close" type="button" data-gallery-close="button" aria-label="Закрыть галерею">×</button>
				<button class="hotel-gallery-nav hotel-gallery-nav--prev" type="button" data-gallery-nav="prev" aria-label="Предыдущее фото"></button>
				<button class="hotel-gallery-nav hotel-gallery-nav--next" type="button" data-gallery-nav="next" aria-label="Следующее фото"></button>
				<figure class="hotel-gallery-stage">
					<img data-gallery-image alt="" />
				</figure>
				<div class="hotel-gallery-counter" data-gallery-counter></div>
			</div>
		</div>
		<?php endif; ?>
	</section>

	<section class="section section--guide-articles">
		<div class="container">
			<h2 class="section-title guide-section-title">Статьи с участием гида</h2>
			<?php if (count($articleCards)): ?>
				<div class="guide-articles-grid">
					<?php foreach ($articleCards as $articleCard): ?>
						<article class="guide-article-card">
							<div class="guide-article-image" style="background-image: url('<?php echo htmlspecialchars((string) ($articleCard['image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>');"></div>
							<h3 class="guide-article-title"><?php echo $sanitizer->entities((string) ($articleCard['title'] ?? '')); ?></h3>
							<?php if (!empty($articleCard['summary'])): ?>
								<p class="guide-article-summary"><?php echo $sanitizer->entities((string) $articleCard['summary']); ?></p>
							<?php endif; ?>
							<a class="guide-article-link" href="<?php echo $sanitizer->entities((string) ($articleCard['url'] ?? '/articles/')); ?>">Читать статью</a>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<div class="guide-empty">Статей с упоминанием этого гида пока не найдено.</div>
			<?php endif; ?>
		</div>
	</section>
</div>
