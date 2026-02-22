<?php namespace ProcessWire;

$authUser = isset($skfoAuthUser) && is_array($skfoAuthUser) ? $skfoAuthUser : null;
?>

<div id="content" class="profile-page">
	<section class="profile-section">
		<div class="container">
			<?php if (!$authUser): ?>
				<div class="profile-card">
					<h1 class="profile-title">Профиль</h1>
					<p class="profile-subtitle">Чтобы открыть профиль, войдите через email и временный код.</p>
					<button class="profile-auth-btn" type="button" data-auth-open>Войти в профиль</button>
				</div>
			<?php else: ?>
				<div class="profile-card">
					<h1 class="profile-title">Профиль</h1>
					<p class="profile-subtitle">Вы успешно авторизованы.</p>
					<div class="profile-row">
						<span class="profile-label">Имя</span>
						<span class="profile-value"><?php echo $sanitizer->entities((string) ($authUser['name'] ?? '')); ?></span>
					</div>
					<div class="profile-row">
						<span class="profile-label">Email</span>
						<span class="profile-value"><?php echo $sanitizer->entities((string) ($authUser['email'] ?? '')); ?></span>
					</div>
					<button
						class="profile-logout-btn"
						type="button"
						data-auth-logout
						data-auth-api-url="<?php echo $sanitizer->entities((string) $page->url); ?>"
						data-csrf-name="<?php echo $sanitizer->entities($session->CSRF->getTokenName()); ?>"
						data-csrf-value="<?php echo $sanitizer->entities($session->CSRF->getTokenValue()); ?>"
					>Выйти из аккаунта</button>
				</div>
			<?php endif; ?>
		</div>
	</section>
</div>
