<?php namespace ProcessWire;

$reviewTable = 'tour_reviews';
$reviewError = '';
$reviewSuccess = '';
$reviewAuthorValue = '';
$reviewTextValue = '';
$reviewRatingValue = 5;
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

$createReviewsTable = static function($database, string $table): void {
	$database->exec(
		"CREATE TABLE IF NOT EXISTS `$table` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`page_id` INT UNSIGNED NOT NULL,
			`author` VARCHAR(120) NOT NULL,
			`review_text` TEXT NOT NULL,
			`rating` TINYINT UNSIGNED NOT NULL,
			`avatar_color` VARCHAR(16) NOT NULL DEFAULT 'blue',
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			KEY `page_created` (`page_id`, `created_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
	);

	try {
		$database->exec("ALTER TABLE `$table` ADD COLUMN `avatar_color` VARCHAR(16) NOT NULL DEFAULT 'blue'");
	} catch (\Throwable $e) {
		// Column already exists on upgraded installations.
	}
};

$reviewError = (string) ($pullFlash($session, $flashPrefix . 'error') ?? '');
$reviewSuccess = (string) ($pullFlash($session, $flashPrefix . 'success') ?? '');
$reviewAuthorValue = (string) ($pullFlash($session, $flashPrefix . 'author') ?? '');
$reviewTextValue = (string) ($pullFlash($session, $flashPrefix . 'text') ?? '');
$reviewRatingFlash = (int) ($pullFlash($session, $flashPrefix . 'rating') ?? 0);
if ($reviewRatingFlash >= 1 && $reviewRatingFlash <= 5) {
	$reviewRatingValue = $reviewRatingFlash;
}

if ($input->requestMethod() === 'POST' && $input->post('review_form') === 'reviews_page') {
	$reviewAuthorValue = trim((string) $input->post('review_author'));
	$reviewTextValue = trim((string) $input->post('review_text'));
	$reviewRatingValue = (int) $input->post('review_rating');

	try {
		$csrfValid = $session->CSRF->validate();
	} catch (\Throwable $e) {
		$csrfValid = false;
	}

	if (!$csrfValid) {
		$reviewError = 'Ошибка безопасности формы. Обновите страницу и попробуйте снова.';
	} elseif ($reviewAuthorValue === '' || $textLength($reviewAuthorValue) < 2) {
		$reviewError = 'Укажите имя (минимум 2 символа).';
	} elseif ($reviewTextValue === '' || $textLength($reviewTextValue) < 8) {
		$reviewError = 'Добавьте текст отзыва (минимум 8 символов).';
	} elseif ($reviewRatingValue < 1 || $reviewRatingValue > 5) {
		$reviewError = 'Выберите оценку от 1 до 5.';
	} else {
		try {
			$createReviewsTable($database, $reviewTable);
			$avatarColorKey = $avatarColorKeys[array_rand($avatarColorKeys)];
			$insertReview = $database->prepare(
				"INSERT INTO `$reviewTable` (`page_id`, `author`, `review_text`, `rating`, `avatar_color`) VALUES (:page_id, :author, :review_text, :rating, :avatar_color)"
			);
			$insertReview->bindValue(':page_id', (int) $page->id, \PDO::PARAM_INT);
			$insertReview->bindValue(':author', $reviewAuthorValue, \PDO::PARAM_STR);
			$insertReview->bindValue(':review_text', $reviewTextValue, \PDO::PARAM_STR);
			$insertReview->bindValue(':rating', $reviewRatingValue, \PDO::PARAM_INT);
			$insertReview->bindValue(':avatar_color', $avatarColorKey, \PDO::PARAM_STR);
			$insertReview->execute();

			$reviewSuccess = 'Спасибо! Ваш отзыв отправлен.';
			$reviewAuthorValue = '';
			$reviewTextValue = '';
			$reviewRatingValue = 5;
		} catch (\Throwable $e) {
			$reviewError = 'Не удалось сохранить отзыв. Попробуйте позже.';
			$log->save('errors', "reviews page save error: " . $e->getMessage());
		}
	}

	if ($reviewError !== '') {
		$setFlash($session, $flashPrefix . 'error', $reviewError);
		$setFlash($session, $flashPrefix . 'author', $reviewAuthorValue);
		$setFlash($session, $flashPrefix . 'text', $reviewTextValue);
		$setFlash($session, $flashPrefix . 'rating', $reviewRatingValue);
	} else {
		$setFlash($session, $flashPrefix . 'success', $reviewSuccess);
		$session->remove($flashPrefix . 'author');
		$session->remove($flashPrefix . 'text');
		$session->remove($flashPrefix . 'rating');
	}

	$session->redirect($page->url . '#reviews-form');
}

$reviews = [];
try {
	$createReviewsTable($database, $reviewTable);
	$selectReviews = $database->prepare(
		"SELECT `author`, `review_text`, `rating`, `avatar_color`
		FROM `$reviewTable`
		WHERE `page_id`=:page_id
		ORDER BY `created_at` DESC, `id` DESC"
	);
	$selectReviews->bindValue(':page_id', (int) $page->id, \PDO::PARAM_INT);
	$selectReviews->execute();
	$reviews = $selectReviews->fetchAll(\PDO::FETCH_ASSOC) ?: [];
} catch (\Throwable $e) {
	$log->save('errors', "reviews page read error: " . $e->getMessage());
}

$csrfTokenName = $session->CSRF->getTokenName();
$csrfTokenValue = $session->CSRF->getTokenValue();
?>

<div id="content" class="reviews-page">
	<section class="reviews-hero">
		<div class="container">
			<h1 class="reviews-title">Ваши честные<br />Отзывы</h1>
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

				<form class="reviews-form" method="post" action="#reviews-form">
					<input type="hidden" name="review_form" value="reviews_page" />
					<input type="hidden" name="<?php echo $sanitizer->entities($csrfTokenName); ?>" value="<?php echo $sanitizer->entities($csrfTokenValue); ?>" />

					<label class="reviews-field">
						<input type="text" name="review_author" maxlength="120" required placeholder="Как вас зовут?" aria-label="Как вас зовут?" value="<?php echo $sanitizer->entities($reviewAuthorValue); ?>" />
					</label>

					<label class="reviews-field">
						<textarea name="review_text" rows="7" maxlength="3000" required placeholder="Ваш честный отзыв..." aria-label="Ваш честный отзыв"><?php echo $sanitizer->entities($reviewTextValue); ?></textarea>
					</label>

					<div class="reviews-field">
						<!-- <span>Оценка</span> -->
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
			</div>
		</div>
	</section>
</div>
