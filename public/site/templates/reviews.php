<?php namespace ProcessWire;

$reviewTable = 'tour_reviews';
require_once __DIR__ . '/_reviews_moderation.php';

$reviewError = '';
$reviewSuccess = '';
$reviewAuthorValue = '';
$reviewTextValue = '';
$reviewRatingValue = 5;
$reviewTourIdValue = 0;
$flashPrefix = 'reviews_form_';
$pullFlash = static function($session, string $key) {
	$value = $session->get($key);
	$session->remove($key);
	return $value;
};
$setFlash = static function($session, string $key, $value): void {
	if ($value === '' || $value === null) {
		$session->remove($key);
		return;
	}
	$session->set($key, $value);
};
$textLength = static function(string $value): int {
	return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
};
$firstLetter = static function(string $value): string {
	$value = trim($value);
	if ($value === '') return '?';
	return function_exists('mb_substr') ? mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($value, 0, 1));
};
$avatarColorKeys = ['blue', 'yellow', 'gray'];
$avatarClassMap = [
	'blue' => 'is-blue',
	'yellow' => 'is-yellow',
	'gray' => 'is-gray',
];
$authUser = isset($skfoAuthUser) && is_array($skfoAuthUser) ? $skfoAuthUser : null;
$isAuthLoggedIn = $authUser !== null;
$resolveReviewAuthor = static function(?array $user): string {
	if (!$user) return '';

	$name = trim((string) ($user['name'] ?? ''));
	if ($name !== '') return $name;

	$email = trim((string) ($user['email'] ?? ''));
	if ($email !== '') {
		$emailName = strstr($email, '@', true);
		if (is_string($emailName) && trim($emailName) !== '') {
			return trim($emailName);
		}
		return $email;
	}

	return 'Пользователь';
};
$resolveTourTitle = static function(Page $tourPage): string {
	$title = $tourPage->hasField('tour_title') ? trim((string) $tourPage->getUnformatted('tour_title')) : '';
	if ($title === '') $title = trim((string) $tourPage->title);
	if ($title === '') $title = trim((string) $tourPage->name);
	return $title;
};

$tourOptions = [];
$tourOptionMap = [];
try {
	$tourPages = $pages->find('template=tour, include=all, check_access=0, status<1024, sort=title, limit=500');
	foreach ($tourPages as $tourPage) {
		if (!$tourPage instanceof Page || !$tourPage->id) continue;

		$tourTitle = $resolveTourTitle($tourPage);
		if ($tourTitle === '') continue;

		$tourOption = [
			'id' => (int) $tourPage->id,
			'title' => $tourTitle,
			'url' => (string) $tourPage->url,
		];
		$tourOptions[] = $tourOption;
		$tourOptionMap[(int) $tourPage->id] = $tourOption;
	}
} catch (\Throwable $e) {
	$log->save('errors', "reviews page tours list error: " . $e->getMessage());
}

$reviewError = (string) ($pullFlash($session, $flashPrefix . 'error') ?? '');
$reviewSuccess = (string) ($pullFlash($session, $flashPrefix . 'success') ?? '');
$reviewTextValue = (string) ($pullFlash($session, $flashPrefix . 'text') ?? '');
$reviewRatingFlash = (int) ($pullFlash($session, $flashPrefix . 'rating') ?? 0);
$reviewTourFlash = (int) ($pullFlash($session, $flashPrefix . 'tour_id') ?? 0);
if ($reviewRatingFlash >= 1 && $reviewRatingFlash <= 5) {
	$reviewRatingValue = $reviewRatingFlash;
}
if ($reviewTourFlash > 0) {
	$reviewTourIdValue = $reviewTourFlash;
}
$reviewAuthorValue = $resolveReviewAuthor($authUser);

if ($input->requestMethod() === 'POST' && $input->post('review_form') === 'reviews_page') {
	$reviewAuthorValue = $resolveReviewAuthor($authUser);
	$reviewTextValue = trim((string) $input->post('review_text'));
	$reviewRatingValue = (int) $input->post('review_rating');
	$reviewTourIdValue = (int) $input->post('review_tour_id');

	try {
		$csrfValid = $session->CSRF->validate();
	} catch (\Throwable $e) {
		$csrfValid = false;
	}

	if (!$isAuthLoggedIn) {
		$reviewError = 'Чтобы оставить отзыв, войдите в свой аккаунт.';
	} elseif (!$csrfValid) {
		$reviewError = 'Ошибка безопасности формы. Обновите страницу и попробуйте снова.';
	} elseif (!count($tourOptions)) {
		$reviewError = 'Сейчас нет доступных туров для отзывов.';
	} elseif (!isset($tourOptionMap[$reviewTourIdValue])) {
		$reviewError = 'Выберите тур из списка.';
	} elseif ($reviewTextValue === '' || $textLength($reviewTextValue) < 8) {
		$reviewError = 'Добавьте текст отзыва (минимум 8 символов).';
	} elseif ($reviewRatingValue < 1 || $reviewRatingValue > 5) {
		$reviewError = 'Выберите оценку от 1 до 5.';
	} else {
		try {
			skfoReviewsEnsureTable($database, $reviewTable);
			skfoReviewsBackfillHashes($database, $reviewTable, 50);

			$moderation = skfoReviewsBuildModerationDecision($database, $reviewTable, $reviewTourIdValue, $reviewTextValue);
			$moderationStatus = (string) ($moderation['status'] ?? 'approved');
			$contentHash = (string) ($moderation['content_hash'] ?? '');
			$moderationFlags = skfoReviewsEncodeFlags((array) ($moderation['flags'] ?? []));

			$avatarColorKey = $avatarColorKeys[array_rand($avatarColorKeys)];
			$insertReview = $database->prepare(
				"INSERT INTO `$reviewTable` (`page_id`, `author`, `review_text`, `rating`, `avatar_color`, `content_hash`, `moderation_status`, `moderation_flags`)
				VALUES (:page_id, :author, :review_text, :rating, :avatar_color, :content_hash, :moderation_status, :moderation_flags)"
			);
			$insertReview->bindValue(':page_id', $reviewTourIdValue, \PDO::PARAM_INT);
			$insertReview->bindValue(':author', $reviewAuthorValue, \PDO::PARAM_STR);
			$insertReview->bindValue(':review_text', $reviewTextValue, \PDO::PARAM_STR);
			$insertReview->bindValue(':rating', $reviewRatingValue, \PDO::PARAM_INT);
			$insertReview->bindValue(':avatar_color', $avatarColorKey, \PDO::PARAM_STR);
			$insertReview->bindValue(':content_hash', $contentHash, \PDO::PARAM_STR);
			$insertReview->bindValue(':moderation_status', $moderationStatus, \PDO::PARAM_STR);
			$insertReview->bindValue(':moderation_flags', $moderationFlags, \PDO::PARAM_STR);
			$insertReview->execute();

			if ($moderationStatus === 'blocked') {
				$banLabels = skfoReviewsBanwordLabels((array) ($moderation['ban_categories'] ?? []));
				$banSuffix = count($banLabels) ? (' (' . implode(', ', $banLabels) . ')') : '';
				$reviewError = 'Отзыв не опубликован: обнаружены запрещенные темы' . $banSuffix . '.';
			} elseif ($moderationStatus === 'duplicate') {
				$reviewSuccess = 'Похожий отзыв уже есть. Ваш отзыв отправлен на модерацию.';
				$reviewAuthorValue = '';
				$reviewTextValue = '';
				$reviewRatingValue = 5;
				$reviewTourIdValue = 0;
			} else {
				$reviewSuccess = 'Спасибо! Ваш отзыв отправлен.';
				$reviewAuthorValue = '';
				$reviewTextValue = '';
				$reviewRatingValue = 5;
				$reviewTourIdValue = 0;
			}
		} catch (\Throwable $e) {
			$reviewError = 'Не удалось сохранить отзыв. Попробуйте позже.';
			$log->save('errors', "reviews page save error: " . $e->getMessage());
		}
	}

	if ($reviewError !== '') {
		$setFlash($session, $flashPrefix . 'error', $reviewError);
		$setFlash($session, $flashPrefix . 'text', $reviewTextValue);
		$setFlash($session, $flashPrefix . 'rating', $reviewRatingValue);
		$setFlash($session, $flashPrefix . 'tour_id', $reviewTourIdValue);
	} else {
		$setFlash($session, $flashPrefix . 'success', $reviewSuccess);
		$session->remove($flashPrefix . 'text');
		$session->remove($flashPrefix . 'rating');
		$session->remove($flashPrefix . 'tour_id');
	}

	$session->redirect($page->url . '#reviews-form');
}

$reviews = [];
try {
	skfoReviewsEnsureTable($database, $reviewTable);
	skfoReviewsBackfillHashes($database, $reviewTable, 50);
	if (count($tourOptionMap)) {
		$tourIds = array_keys($tourOptionMap);
		$placeholders = [];
		foreach ($tourIds as $index => $tourId) {
			$placeholders[] = ':tour_id_' . $index;
		}

		$selectReviewsSql = "SELECT `page_id`, `author`, `review_text`, `rating`, `avatar_color`
			FROM `$reviewTable`
			WHERE `moderation_status`='approved'
			AND `page_id` IN (" . implode(', ', $placeholders) . ")
			ORDER BY `created_at` DESC, `id` DESC
			LIMIT 500";
		$selectReviews = $database->prepare($selectReviewsSql);
		foreach ($tourIds as $index => $tourId) {
			$selectReviews->bindValue(':tour_id_' . $index, (int) $tourId, \PDO::PARAM_INT);
		}
		$selectReviews->execute();
		$rawReviews = $selectReviews->fetchAll(\PDO::FETCH_ASSOC) ?: [];

		foreach ($rawReviews as $rawReview) {
			$tourId = (int) ($rawReview['page_id'] ?? 0);
			if (!isset($tourOptionMap[$tourId])) continue;

			$rawReview['tour'] = $tourOptionMap[$tourId];
			$reviews[] = $rawReview;
		}
	}
} catch (\Throwable $e) {
	$log->save('errors', "reviews page read error: " . $e->getMessage());
}

$csrfTokenName = $session->CSRF->getTokenName();
$csrfTokenValue = $session->CSRF->getTokenValue();
?>

<div id="content" class="reviews-page">
	<section class="hero reviews-hero">
		<div class="container hero-inner reviews-hero-inner">
			<h1 class="reviews-title">Ваши честные<br />Отзывы</h1>
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
					<a class="hero-tab is-active" href="/reviews/" role="tab" aria-selected="true">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/reviews.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Отзывы</span>
					</a>
					<a class="hero-tab" href="/regions/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Регионы</span>
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

	<section class="reviews-content" id="reviews-form">
		<div class="container reviews-layout">
			<div class="reviews-list-card">
				<div class="reviews-list">
					<?php foreach ($reviews as $review): ?>
						<?php
						$rating = max(1, min(5, (int) ($review['rating'] ?? 5)));
						$stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
						$author = (string) ($review['author'] ?? 'Гость');
						$avatarColorKey = (string) ($review['avatar_color'] ?? '');
						$reviewTour = isset($review['tour']) && is_array($review['tour']) ? $review['tour'] : null;
						$reviewTourTitle = $reviewTour ? (string) ($reviewTour['title'] ?? '') : '';
						$reviewTourUrl = $reviewTour ? (string) ($reviewTour['url'] ?? '') : '';
						if (!isset($avatarClassMap[$avatarColorKey])) {
							$index = abs(crc32($author)) % count($avatarColorKeys);
							$avatarColorKey = $avatarColorKeys[$index];
						}
						$avatarClass = $avatarClassMap[$avatarColorKey];
						?>
						<article class="review-item">
							<div class="review-top">
								<span class="review-avatar <?php echo $avatarClass; ?>" aria-hidden="true"><?php echo $sanitizer->entities($firstLetter($author)); ?></span>
								<div class="review-meta">
									<h2 class="review-author"><?php echo $sanitizer->entities($author); ?></h2>
									<span class="review-stars" aria-label="Оценка <?php echo $rating; ?> из 5"><?php echo $stars; ?></span>
									<?php if ($reviewTourTitle !== '' && $reviewTourUrl !== ''): ?>
										<div class="review-tour-label">
											Тур:
											<a class="review-tour-link" href="<?php echo $sanitizer->entities($reviewTourUrl); ?>">
												<?php echo $sanitizer->entities($reviewTourTitle); ?>
											</a>
										</div>
									<?php endif; ?>
								</div>
							</div>
							<p class="review-text"><?php echo nl2br($sanitizer->entities((string) ($review['review_text'] ?? ''))); ?></p>
						</article>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="reviews-form-card">
				<h2 class="reviews-form-title">Оставить отзыв</h2>
				<?php if ($reviewSuccess !== ''): ?>
					<div class="review-message is-success"><?php echo $sanitizer->entities($reviewSuccess); ?></div>
				<?php endif; ?>
				<?php if ($reviewError !== ''): ?>
					<div class="review-message is-error"><?php echo $sanitizer->entities($reviewError); ?></div>
				<?php endif; ?>
				<?php if (!$isAuthLoggedIn): ?>
					<div class="review-message">Чтобы оставить отзыв, войдите в свой профиль.</div>
					<button class="reviews-submit reviews-auth-submit" type="button" data-auth-open data-auth-mode="login">Войти в профиль</button>
				<?php else: ?>
					<?php if (!count($tourOptions)): ?>
						<div class="review-message is-error">Сейчас нет доступных туров для отзывов.</div>
					<?php else: ?>
						<form class="reviews-form" method="post" action="#reviews-form">
							<input type="hidden" name="review_form" value="reviews_page" />
							<input type="hidden" name="<?php echo $sanitizer->entities($csrfTokenName); ?>" value="<?php echo $sanitizer->entities($csrfTokenValue); ?>" />

							<label class="reviews-field">
								<span>Тур</span>
								<select name="review_tour_id" required aria-label="Выберите тур">
									<option value="" disabled<?php echo $reviewTourIdValue < 1 ? ' selected' : ''; ?>>Выберите тур</option>
									<?php foreach ($tourOptions as $tourOption): ?>
										<?php
										$tourId = (int) ($tourOption['id'] ?? 0);
										$tourTitle = (string) ($tourOption['title'] ?? '');
										$isSelected = $reviewTourIdValue === $tourId;
										?>
										<option value="<?php echo $tourId; ?>"<?php echo $isSelected ? ' selected' : ''; ?>>
											<?php echo $sanitizer->entities($tourTitle); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</label>

							<label class="reviews-field">
								<textarea name="review_text" rows="7" maxlength="3000" required placeholder="Ваш честный отзыв..." aria-label="Ваш честный отзыв"><?php echo $sanitizer->entities($reviewTextValue); ?></textarea>
							</label>

							<div class="reviews-field">
								<div class="reviews-stars-input" role="radiogroup" aria-label="Оценка от 1 до 5">
									<?php for ($i = 5; $i >= 1; $i--): ?>
										<?php $isChecked = $reviewRatingValue === $i; ?>
										<input id="review-rating-<?php echo $i; ?>" type="radio" name="review_rating" value="<?php echo $i; ?>"<?php echo $isChecked ? ' checked' : ''; ?> required />
										<label for="review-rating-<?php echo $i; ?>" aria-label="<?php echo $i; ?>"></label>
									<?php endfor; ?>
								</div>
							</div>

							<button class="reviews-submit" type="submit">Отправить</button>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>
</div>
