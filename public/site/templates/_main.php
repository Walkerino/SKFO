<?php namespace ProcessWire;

// Optional main output file, called after rendering page’s template file. 
// This is defined by $config->appendTemplateFile in /site/config.php, and
// is typically used to define and output markup common among most pages.
// 	
// When the Markup Regions feature is used, template files can prepend, append,
// replace or delete any element defined here that has an "id" attribute. 
// https://processwire.com/docs/front-end/output/markup-regions/
	
/** @var Page $page */
/** @var Pages $pages */
/** @var Config $config */
	
$home = $pages->get('/'); /** @var HomePage $home */

	$mainNav = [
		['label' => 'Туры', 'url' => '/tours/'],
		['label' => 'Отели', 'url' => '/hotels/'],
		['label' => 'Отзывы', 'url' => '/reviews/'],
		['label' => 'Регионы', 'url' => '/regions/'],
		['label' => 'Статьи', 'url' => '/articles/'],
		['label' => 'Форум', 'url' => '/forum/'],
	];

	$templateName = $page->template ? $page->template->name : '';
	$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
	$isReviewsRequest = $requestPath === '/reviews' || $requestPath === '/reviews/';
	$isRegionsRequest = preg_match('#^/regions(?:/|$)#', (string) $requestPath) === 1;
	$isReviewsPage = $page->name === 'reviews' || $page->path === '/reviews/' || $isReviewsRequest;
	$isRegionsPage = $page->name === 'regions' || $page->path === '/regions/' || $templateName === 'region' || $isRegionsRequest;
	$isTourTemplate = in_array($templateName, ['tour', 'reviews', 'regions', 'region'], true) || $isReviewsPage || $isRegionsPage;
	$isTourNavActive = $templateName === 'tour';
	$isReviewsNavActive = $templateName === 'reviews' || $isReviewsPage;
	$isRegionsNavActive = in_array($templateName, ['regions', 'region'], true) || $isRegionsPage;
	$mainCssPath = $config->paths->templates . 'styles/main.css';
	$mainCssVersion = is_file($mainCssPath) ? filemtime($mainCssPath) : null;

?><!DOCTYPE html>
<html lang="ru">
	<head id="html-head">
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title><?php echo $page->title; ?> | SKFO.RU</title>
		<link rel="stylesheet" type="text/css" href="<?php echo $config->urls->templates; ?>styles/main.css<?php echo $mainCssVersion ? '?v=' . (int) $mainCssVersion : ''; ?>" />
	</head>
		<body id="html-body">
			<?php if($isTourTemplate): ?>
				<header class="tour-header" id="site-header">
					<div class="container tour-header-row">
						<a class="logo tour-header-logo" href="<?php echo $home->url; ?>" aria-label="SKFO.RU">
							<img class="logo-img" src="<?php echo $config->urls->templates; ?>assets/icons/logo.svg" alt="SKFO.RU" />
						</a>
						<nav class="tour-header-center tour-nav" aria-label="Основная навигация">
							<div class="tour-header-nav tour-nav-group" role="tablist">
								<span class="tour-nav-indicator" aria-hidden="true"></span>
								<span class="tour-nav-hover" aria-hidden="true"></span>
								<a class="tour-header-link tour-nav-link<?php echo $isTourNavActive ? ' is-active' : ''; ?>" href="<?php echo $home->url; ?>">
									<img src="<?php echo $config->urls->templates; ?>assets/icons/tour.svg" alt="" aria-hidden="true" />
									<span class="tour-nav-text">Туры</span>
								</a>
								<a class="tour-header-link tour-nav-link" href="/hotels/">
									<img src="<?php echo $config->urls->templates; ?>assets/icons/hotel.svg" alt="" aria-hidden="true" />
									<span class="tour-nav-text">Отели</span>
								</a>
								<a class="tour-header-link tour-nav-link<?php echo $isReviewsNavActive ? ' is-active' : ''; ?>" href="/reviews/">
									<img src="<?php echo $config->urls->templates; ?>assets/icons/reviews.svg" alt="" aria-hidden="true" />
									<span class="tour-nav-text">Отзывы</span>
								</a>
								<a class="tour-header-link tour-nav-link<?php echo $isRegionsNavActive ? ' is-active' : ''; ?>" href="/regions/">
									<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
									<span class="tour-nav-text">Регионы</span>
								</a>
								<a class="tour-header-link tour-nav-link" href="/articles/">
									<img src="<?php echo $config->urls->templates; ?>assets/icons/journal.svg" alt="" aria-hidden="true" />
									<span class="tour-nav-text">Статьи</span>
								</a>
							</div>
							<a class="tour-header-link tour-nav-link tour-header-link--forum" href="/forum/">
								<img src="<?php echo $config->urls->templates; ?>assets/icons/forum.svg" alt="" aria-hidden="true" />
								<span class="tour-nav-text">Форум</span>
							</a>
						</nav>
						<div class="tour-header-actions">
							<a class="icon-btn tour-header-action" href="/profile/" aria-label="Профиль">
								<img class="icon-img" src="<?php echo $config->urls->templates; ?>assets/icons/profile.svg" alt="" aria-hidden="true" />
							</a>
							<a class="icon-btn tour-header-action" href="/contacts/" aria-label="Контакты">
								<img class="icon-img" src="<?php echo $config->urls->templates; ?>assets/icons/contacts.svg" alt="" aria-hidden="true" />
							</a>
						</div>
					</div>
				</header>
			<?php else: ?>
				<header class="site-header site-header--overlay" id="site-header">
					<div class="container header-row">
						<a class="icon-btn" href="/profile/" aria-label="Профиль">
							<img class="icon-img" src="<?php echo $config->urls->templates; ?>assets/icons/profile.svg" alt="" aria-hidden="true" />
							<span>Профиль</span>
						</a>
						<a class="logo" href="<?php echo $home->url; ?>" aria-label="SKFO.RU">
							<img class="logo-img" src="<?php echo $config->urls->templates; ?>assets/icons/logo.svg" alt="SKFO.RU" />
						</a>
						<a class="icon-btn" href="/contacts/" aria-label="Контакты">
							<img class="icon-img" src="<?php echo $config->urls->templates; ?>assets/icons/contacts.svg" alt="" aria-hidden="true" />
							<span>Контакты</span>
						</a>
					</div>
				</header>
			<?php endif; ?>

		<main id="content" class="site-main">
			Default content
		</main>

		<footer class="site-footer" id="site-footer">
			<div class="container footer-layout">
				<div class="footer-brand">
					<div class="footer-brand-head">
						<img class="footer-brand-logo" src="<?php echo $config->urls->templates; ?>assets/icons/logo2.svg" alt="SKFO.RU" />
						<div class="footer-brand-meta">
							<div class="footer-brand-name">SKFO.RU</div>
							<div class="footer-brand-copy">© 2026</div>
						</div>
					</div>
					<div class="footer-social">
						<a class="footer-social-btn" href="#" aria-label="VK">
							<img src="<?php echo $config->urls->templates; ?>assets/icons/vk.svg" alt="" aria-hidden="true" />
						</a>
						<a class="footer-social-btn" href="#" aria-label="Telegram">
							<img src="<?php echo $config->urls->templates; ?>assets/icons/telegram.svg" alt="" aria-hidden="true" />
						</a>
						<a class="footer-social-btn" href="#" aria-label="Dzen">
							<img src="<?php echo $config->urls->templates; ?>assets/icons/dzen.svg" alt="" aria-hidden="true" />
						</a>
					</div>
				</div>
				<nav class="footer-menu" aria-label="Информация">
					<a href="/about/">О нас</a>
					<a href="/support/">Поддержка</a>
					<a href="/services/">Размещение услуг</a>
					<a href="/privacy/">Политика конфиденциальности</a>
					<a href="/terms/">Правила использования сайта</a>
				</nav>
				<div class="footer-sections" aria-label="Разделы">
					<a class="footer-section-item" href="<?php echo $home->url; ?>">
						<span class="footer-section-icon"><img src="<?php echo $config->urls->templates; ?>assets/icons/tour-footer.svg" alt="" aria-hidden="true" /></span>
						<span class="footer-section-text">
							<span class="footer-section-title">Туры</span>
							<span class="footer-section-subtitle">Отдых мечты</span>
						</span>
					</a>
					<a class="footer-section-item" href="/hotels/">
						<span class="footer-section-icon"><img src="<?php echo $config->urls->templates; ?>assets/icons/hotel-footer.svg" alt="" aria-hidden="true" /></span>
						<span class="footer-section-text">
							<span class="footer-section-title">Отели</span>
							<span class="footer-section-subtitle">Места для ночлега</span>
						</span>
					</a>
					<a class="footer-section-item" href="/forum/">
						<span class="footer-section-icon"><img src="<?php echo $config->urls->templates; ?>assets/icons/forum-footer.svg" alt="" aria-hidden="true" /></span>
						<span class="footer-section-text">
							<span class="footer-section-title">Форум СКФО.РУ</span>
							<span class="footer-section-subtitle">Общение и обсуждения</span>
						</span>
					</a>
					<a class="footer-section-item" href="/articles/">
						<span class="footer-section-icon"><img src="<?php echo $config->urls->templates; ?>assets/icons/journal-footer.svg" alt="" aria-hidden="true" /></span>
						<span class="footer-section-text">
							<span class="footer-section-title">Журнал от СКФО.РУ</span>
							<span class="footer-section-subtitle">Статьи о путешествиях</span>
						</span>
					</a>
					<a class="footer-section-item" href="/reviews/">
						<span class="footer-section-icon"><img src="<?php echo $config->urls->templates; ?>assets/icons/reviews-footer.svg" alt="" aria-hidden="true" /></span>
						<span class="footer-section-text">
							<span class="footer-section-title">Отзывы</span>
							<span class="footer-section-subtitle">Оставь свои впечатления</span>
						</span>
					</a>
				</div>
			</div>
		</footer>

		<script src="<?php echo $config->urls->templates; ?>scripts/main.js"></script>
	</body>
</html>
