<?php namespace ProcessWire;

$authUser = isset($skfoAuthUser) && is_array($skfoAuthUser) ? $skfoAuthUser : null;
$normalizeName = static function(string $value): string {
	$value = trim($value);
	$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
	return $value;
};
$displayName = $normalizeName((string) ($authUser['name'] ?? ''));
$displayEmail = trim((string) ($authUser['email'] ?? ''));
if ($displayName === '' && $displayEmail !== '') {
	$emailName = strstr($displayEmail, '@', true);
	if (is_string($emailName) && trim($emailName) !== '') {
		$displayName = $emailName;
	}
}
if ($displayName === '') {
	$displayName = 'Путешественник';
}
$buildInitials = static function(string $value): string {
	$value = trim($value);
	if ($value === '') return 'SK';

	$parts = preg_split('/\s+/u', $value) ?: [];
	$letters = [];
	foreach ($parts as $part) {
		if ($part === '') continue;
		$first = function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
		if ($first === '') continue;
		$letters[] = function_exists('mb_strtoupper') ? mb_strtoupper($first, 'UTF-8') : strtoupper($first);
		if (count($letters) >= 2) break;
	}

	if (!count($letters)) {
		$fallback = function_exists('mb_substr') ? mb_substr($value, 0, 2, 'UTF-8') : substr($value, 0, 2);
		return function_exists('mb_strtoupper') ? mb_strtoupper($fallback, 'UTF-8') : strtoupper($fallback);
	}

	return implode('', $letters);
};
$profileInitials = $buildInitials($displayName !== '' ? $displayName : $displayEmail);
$createdAtRaw = trim((string) ($authUser['created_at'] ?? ''));
$memberSince = '';
if ($createdAtRaw !== '') {
	$createdAtTs = strtotime($createdAtRaw);
	if ($createdAtTs !== false) {
		$memberSince = date('d.m.Y', $createdAtTs);
	}
}
?>

<div id="content" class="profile-page">
	<section class="profile-section">
		<div class="container profile-container">
			<div class="profile-hero">
				<div class="profile-hero-inner">
					<div class="profile-hero-copy">
						<p class="profile-kicker">Личный кабинет</p>
						<h1 class="profile-hero-title">Профиль SKFO.RU</h1>
						<p class="profile-hero-subtitle">
							<?php if (!$authUser): ?>
								Войдите по email и временному коду, чтобы управлять вашим профилем.
							<?php else: ?>
								Здравствуйте, <?php echo $sanitizer->entities($displayName); ?>. Ваш профиль готов к работе.
							<?php endif; ?>
						</p>
					</div>
					<div class="profile-hero-state<?php echo $authUser ? ' is-online' : ''; ?>">
						<?php echo $authUser ? 'Вход выполнен' : 'Гость'; ?>
					</div>
				</div>
			</div>

			<div class="profile-shell">
			<?php if (!$authUser): ?>
					<div class="profile-card profile-card--guest">
						<h2 class="profile-card-title">Вход в профиль</h2>
						<p class="profile-card-subtitle">После входа вы сможете использовать персональные функции на сайте.</p>
						<div class="profile-benefits">
							<div class="profile-benefit">Быстрый вход без пароля: только email и код из письма.</div>
							<div class="profile-benefit">Единый аккаунт для действий на сайте.</div>
							<div class="profile-benefit">Защищенная авторизация по одноразовому коду.</div>
						</div>
						<button class="profile-auth-btn" type="button" data-auth-open>Войти в профиль</button>
					</div>
			<?php else: ?>
					<div class="profile-card profile-card--auth">
						<div class="profile-head">
							<div class="profile-avatar" aria-hidden="true"><?php echo $sanitizer->entities($profileInitials); ?></div>
							<div class="profile-head-copy">
								<h2 class="profile-name"><?php echo $sanitizer->entities($displayName); ?></h2>
								<p class="profile-email"><?php echo $sanitizer->entities($displayEmail); ?></p>
							</div>
							<div class="profile-status">Профиль активен</div>
						</div>

						<div class="profile-info-grid">
							<div class="profile-info-item">
								<span class="profile-label">Имя</span>
								<span class="profile-value"><?php echo $sanitizer->entities($displayName); ?></span>
							</div>
							<div class="profile-info-item">
								<span class="profile-label">Email</span>
								<span class="profile-value"><?php echo $sanitizer->entities($displayEmail); ?></span>
							</div>
							<div class="profile-info-item">
								<span class="profile-label">Статус</span>
								<span class="profile-value">Авторизован</span>
							</div>
							<div class="profile-info-item">
								<span class="profile-label">Дата регистрации</span>
								<span class="profile-value"><?php echo $sanitizer->entities($memberSince !== '' ? $memberSince : '—'); ?></span>
							</div>
						</div>

						<div class="profile-actions">
							<button
								class="profile-logout-btn"
								type="button"
								data-auth-logout
								data-auth-api-url="<?php echo $sanitizer->entities((string) $page->url); ?>"
								data-csrf-name="<?php echo $sanitizer->entities($session->CSRF->getTokenName()); ?>"
								data-csrf-value="<?php echo $sanitizer->entities($session->CSRF->getTokenValue()); ?>"
							>Выйти из аккаунта</button>
						</div>
					</div>
			<?php endif; ?>
			</div>
		</div>
	</section>
</div>
