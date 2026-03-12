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
		['label' => 'Гиды', 'url' => '/guides/'],
		['label' => 'Регионы', 'url' => '/regions/'],
		['label' => 'Статьи', 'url' => '/articles/'],
		['label' => 'Форум', 'url' => 'https://club.skfo.ru'],
	];
	$forumExternalUrl = 'https://club.skfo.ru';

	$templateName = $page->template ? $page->template->name : '';
		$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
		$isProfileRequest = $requestPath === '/profile' || $requestPath === '/profile/';
		$isContentAdminPage = false;
		$isProfilePage = $page->name === 'profile' || $page->path === '/profile/' || $isProfileRequest;
		$isReviewsRequest = $requestPath === '/reviews' || $requestPath === '/reviews/';
		$isHotelsRequest = preg_match('#^/hotels(?:/|$)#', (string) $requestPath) === 1;
		$isRegionsRequest = preg_match('#^/regions(?:/|$)#', (string) $requestPath) === 1;
		$isGuidesRequest = preg_match('#^/guides(?:/|$)#', (string) $requestPath) === 1;
		$isPlacesRequest = preg_match('#^/places(?:/|$)#', (string) $requestPath) === 1;
		$isArticlesRequest = preg_match('#^/articles(?:/|$)#', (string) $requestPath) === 1;
	$isHotelsPage = $page->name === 'hotels' || $page->path === '/hotels/' || in_array($templateName, ['hotels', 'hotel'], true) || $isHotelsRequest;
	$isHotelsCatalogPage = $templateName === 'hotels' || $page->path === '/hotels/' || $requestPath === '/hotels' || $requestPath === '/hotels/';
	$isReviewsPage = $page->name === 'reviews' || $page->path === '/reviews/' || $isReviewsRequest;
	$isGuidesPage = $page->name === 'guides' || $page->path === '/guides/' || in_array($templateName, ['guides', 'guide'], true) || $isGuidesRequest;
	$isTourMobileHeaderPage = $isHotelsCatalogPage || $isReviewsPage;
	$isRegionsPage = $page->name === 'regions' || $page->path === '/regions/' || in_array($templateName, ['regions', 'region', 'places', 'place'], true) || $isRegionsRequest || $isPlacesRequest;
	$isArticlesPage = $page->name === 'articles' || $page->path === '/articles/' || $isArticlesRequest;
	$isHomePage = $page->path === '/' || $templateName === 'home';
	$isTourTemplate = in_array($templateName, ['tour', 'tours', 'hotel', 'reviews', 'regions', 'region', 'places', 'place', 'guides', 'guide', 'articles', 'article'], true) || $isReviewsPage || $isRegionsPage || $isArticlesPage;
	$isTourNavActive = in_array($templateName, ['tour', 'tours'], true);
	$isHotelsNavActive = $templateName === 'hotel' || $isHotelsPage;
	$isReviewsNavActive = $templateName === 'reviews' || $isReviewsPage;
	$isGuidesNavActive = in_array($templateName, ['guides', 'guide'], true) || $isGuidesPage;
	$isRegionsNavActive = in_array($templateName, ['regions', 'region', 'places', 'place'], true) || $isRegionsPage;
	$isArticlesNavActive = $templateName === 'articles' || $isArticlesPage;
	$isToursMenuActive = $isHomePage || $isTourNavActive;
	$isArticleDetailPage = $templateName === 'article';
	if (!$isArticleDetailPage && $templateName === 'articles') {
		$articleParam = trim((string) $input->get('article'));
		$isArticleDetailPath = preg_match('#^/articles/[^/]+/?$#', (string) $requestPath) === 1;
		$isArticleDetailPage = $articleParam !== '' || $isArticleDetailPath;
	}
	$isSecondaryCompactHeaderPage = in_array($templateName, ['tour', 'hotel', 'region', 'place', 'guide'], true) || $isArticleDetailPage;
	$normalizeHeadTitleKey = static function(string $value): string {
		$value = trim($value);
		$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	};
	$headTitleByTemplate = [
		'home' => 'Главная',
		'tour' => 'Тур',
		'tours' => 'Туры',
		'hotels' => 'Отели',
		'hotel' => 'Отель',
		'reviews' => 'Отзывы',
		'guides' => 'Гиды',
		'guide' => 'Гид',
		'regions' => 'Регионы',
		'region' => 'Регион',
		'places' => 'Места',
		'place' => 'Место',
		'articles' => 'Статьи',
		'article' => 'Статья',
		'profile' => 'Профиль',
	];
	$headTitleOverrides = [
		'home' => 'Главная',
		'hotels' => 'Отели',
		'hotel' => 'Отель',
		'tours' => 'Туры',
		'tour' => 'Тур',
		'reviews' => 'Отзывы',
		'guides' => 'Гиды',
		'guide' => 'Гид',
		'regions' => 'Регионы',
		'region' => 'Регион',
		'places' => 'Места',
		'place' => 'Место',
		'articles' => 'Статьи',
		'article' => 'Статья',
		'profile' => 'Профиль',
	];
	$pageTitleForHead = trim((string) $page->title);
	$pageTitleKey = $normalizeHeadTitleKey($pageTitleForHead);
	if (isset($headTitleOverrides[$pageTitleKey])) {
		$pageTitleForHead = $headTitleOverrides[$pageTitleKey];
	}
	if ($requestPath === '/hotels' || $requestPath === '/hotels/') {
		$pageTitleForHead = 'Отели';
	}
	if ($requestPath === '/regions' || $requestPath === '/regions/') {
		$pageTitleForHead = 'Регионы';
	}
	if ($requestPath === '/guides' || $requestPath === '/guides/') {
		$pageTitleForHead = 'Гиды';
	}
	if ($requestPath === '/places' || $requestPath === '/places/') {
		$pageTitleForHead = 'Места';
	}
	if ($requestPath === '/articles' || $requestPath === '/articles/') {
		$pageTitleForHead = 'Статьи';
	}
	if ($pageTitleForHead === '' && isset($headTitleByTemplate[$templateName])) {
		$pageTitleForHead = $headTitleByTemplate[$templateName];
	}
	if ($pageTitleForHead === '' && $page->path === '/') {
		$pageTitleForHead = 'Главная';
	}
	if ($pageTitleForHead === '') $pageTitleForHead = 'Страница';
	$brandTitleForHead = 'СКФО.РУ';
	$faviconSvgPath = $config->paths->templates . 'assets/favicon.svg';
	$faviconUrl = is_file($faviconSvgPath)
		? $config->urls->templates . 'assets/favicon.svg'
		: $config->urls->templates . 'assets/icons/logo2.svg';
	$mainCssPath = $config->paths->templates . 'styles/main.css';
	$mainCssVersion = is_file($mainCssPath) ? filemtime($mainCssPath) : null;
	$mainJsPath = $config->paths->templates . 'scripts/main.js';
	$mainJsVersion = is_file($mainJsPath) ? filemtime($mainJsPath) : null;
	$authUser = isset($skfoAuthUser) && is_array($skfoAuthUser) ? $skfoAuthUser : null;
	$isAuthLoggedIn = $authUser !== null;
	$isCmsEditor = isset($user) && $user instanceof User && $user->isLoggedin() && ($user->isSuperuser() || $user->hasPermission('page-edit'));
	$profileLinkAttrs = $isAuthLoggedIn ? '' : ' data-auth-open';
	$normalizeAuthName = static function(string $value): string {
		$value = trim($value);
		$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
		return $value;
	};
	$profileButtonLabel = 'Профиль';
	if ($isAuthLoggedIn) {
		$profileButtonLabel = $normalizeAuthName((string) ($authUser['name'] ?? ''));
		if ($profileButtonLabel === '') {
			$profileEmail = trim((string) ($authUser['email'] ?? ''));
			if ($profileEmail !== '') {
				$emailName = strstr($profileEmail, '@', true);
				if (is_string($emailName) && trim($emailName) !== '') {
					$profileButtonLabel = trim($emailName);
				}
			}
		}
		if ($profileButtonLabel === '') {
			$profileButtonLabel = 'Профиль';
		}
	}
	$profileAriaLabel = $isAuthLoggedIn ? ('Профиль: ' . $profileButtonLabel) : 'Профиль';
	$authCsrfTokenName = $session->CSRF->getTokenName();
	$authCsrfTokenValue = $session->CSRF->getTokenValue();
	$envValue = static function(string $name, string $default = ''): string {
		$value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
		if ($value === false || $value === null) return $default;
		return trim((string) $value);
	};
	$reCaptchaSiteKey = $envValue('SKFO_RECAPTCHA_SITE_KEY', '');
	$bodyClassNames = [];
	if ($isProfilePage) $bodyClassNames[] = 'page-profile';
	if ($templateName !== '') {
		$templateClassName = preg_replace('/[^a-z0-9_-]+/i', '', $templateName) ?? '';
		$templateClassName = strtolower($templateClassName);
		if ($templateClassName !== '') $bodyClassNames[] = 'template-' . $templateClassName;
	}
	if ($isHotelsCatalogPage) $bodyClassNames[] = 'template-hotels-catalog';
	if ($isTourMobileHeaderPage) $bodyClassNames[] = 'template-tour-mobile-header';
	if ($isArticleDetailPage) $bodyClassNames[] = 'is-article-detail';
	$bodyClassAttr = count($bodyClassNames) ? (' class="' . implode(' ', $bodyClassNames) . '"') : '';

?><!DOCTYPE html>
<html lang="ru">
	<head id="html-head">
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title><?php echo $sanitizer->entities($pageTitleForHead); ?> | <?php echo $brandTitleForHead; ?></title>
		<link rel="icon" type="image/svg+xml" href="<?php echo $faviconUrl; ?>" />
		<link rel="shortcut icon" href="<?php echo $faviconUrl; ?>" />
		<link rel="stylesheet" type="text/css" href="<?php echo $config->urls->templates; ?>styles/main.css<?php echo $mainCssVersion ? '?v=' . (int) $mainCssVersion : ''; ?>" />
	</head>
			<body id="html-body"<?php echo $bodyClassAttr; ?>>
				<?php if(!$isContentAdminPage): ?>
				<?php if ($isSecondaryCompactHeaderPage): ?>
					<header class="tour-header" id="site-header">
						<div class="container tour-header-row">
							<a class="logo tour-header-logo" href="<?php echo $home->url; ?>" aria-label="SKFO.RU">
								<img class="logo-img" src="<?php echo $config->urls->templates; ?>assets/icons/logo.svg" alt="SKFO.RU" />
							</a>
							<nav class="tour-header-center tour-nav" aria-label="Основная навигация">
								<div class="tour-header-nav tour-nav-group" role="tablist">
									<span class="tour-nav-indicator" aria-hidden="true"></span>
									<span class="tour-nav-hover" aria-hidden="true"></span>
									<a class="tour-header-link tour-nav-link<?php echo $isToursMenuActive ? ' is-active' : ''; ?>" href="<?php echo $home->url; ?>">
										<img src="<?php echo $config->urls->templates; ?>assets/icons/tour.svg" alt="" aria-hidden="true" />
										<span class="tour-nav-text">Туры</span>
									</a>
									<a class="tour-header-link tour-nav-link<?php echo $isHotelsNavActive ? ' is-active' : ''; ?>" href="/hotels/">
										<img src="<?php echo $config->urls->templates; ?>assets/icons/hotel.svg" alt="" aria-hidden="true" />
										<span class="tour-nav-text">Отели</span>
									</a>
									<a class="tour-header-link tour-nav-link<?php echo $isReviewsNavActive ? ' is-active' : ''; ?>" href="/reviews/">
										<img src="<?php echo $config->urls->templates; ?>assets/icons/reviews.svg" alt="" aria-hidden="true" />
										<span class="tour-nav-text">Отзывы</span>
									</a>
									<a class="tour-header-link tour-nav-link<?php echo $isGuidesNavActive ? ' is-active' : ''; ?>" href="/guides/">
										<img src="<?php echo $config->urls->templates; ?>assets/icons/human.svg" alt="" aria-hidden="true" />
										<span class="tour-nav-text">Гиды</span>
									</a>
									<a class="tour-header-link tour-nav-link<?php echo $isRegionsNavActive ? ' is-active' : ''; ?>" href="/regions/">
										<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
										<span class="tour-nav-text">Регионы</span>
									</a>
									<a class="tour-header-link tour-nav-link<?php echo $isArticlesNavActive ? ' is-active' : ''; ?>" href="/articles/">
										<img src="<?php echo $config->urls->templates; ?>assets/icons/journal.svg" alt="" aria-hidden="true" />
										<span class="tour-nav-text">Статьи</span>
									</a>
								</div>
								<a class="tour-header-link tour-nav-link tour-nav-link--forum" href="<?php echo $forumExternalUrl; ?>" target="_blank" rel="noopener noreferrer">
									<img src="<?php echo $config->urls->templates; ?>assets/icons/forum.svg" alt="" aria-hidden="true" />
									<span class="tour-nav-text">Форум</span>
									<img class="tour-header-link-external" src="<?php echo $config->urls->templates; ?>assets/icons/external_site.svg" alt="" aria-hidden="true" />
								</a>
							</nav>
							<div class="tour-header-actions">
								<a class="icon-btn tour-header-action" href="/profile/" aria-label="<?php echo $sanitizer->entities($profileAriaLabel); ?>"<?php echo $profileLinkAttrs; ?>>
									<img class="icon-img" src="<?php echo $config->urls->templates; ?>assets/icons/profile.svg" alt="" aria-hidden="true" />
								</a>
								<a class="icon-btn tour-header-action" href="/contacts/" aria-label="Контакты" data-contacts-open>
									<img class="icon-img" src="<?php echo $config->urls->templates; ?>assets/icons/contacts.svg" alt="" aria-hidden="true" />
								</a>
							</div>
							<button class="icon-btn home-burger-toggle tour-burger-toggle" type="button" aria-label="Открыть меню" aria-expanded="false" aria-controls="tour-mobile-menu" data-home-menu-toggle>
								<span class="home-burger-icon" aria-hidden="true"></span>
							</button>
						</div>
						<div class="container home-mobile-menu-wrap tour-mobile-menu-wrap">
							<div class="home-mobile-menu" id="tour-mobile-menu" hidden data-home-menu>
								<nav class="home-mobile-menu-nav" aria-label="Навигация по сайту">
									<a class="home-mobile-menu-link<?php echo $isToursMenuActive ? ' is-active' : ''; ?>" href="<?php echo $home->url; ?>"<?php echo $isToursMenuActive ? ' aria-current="page"' : ''; ?>>Туры</a>
									<a class="home-mobile-menu-link<?php echo $isHotelsNavActive ? ' is-active' : ''; ?>" href="/hotels/"<?php echo $isHotelsNavActive ? ' aria-current="page"' : ''; ?>>Отели</a>
									<a class="home-mobile-menu-link<?php echo $isReviewsNavActive ? ' is-active' : ''; ?>" href="/reviews/"<?php echo $isReviewsNavActive ? ' aria-current="page"' : ''; ?>>Отзывы</a>
									<a class="home-mobile-menu-link<?php echo $isGuidesNavActive ? ' is-active' : ''; ?>" href="/guides/"<?php echo $isGuidesNavActive ? ' aria-current="page"' : ''; ?>>Гиды</a>
									<a class="home-mobile-menu-link<?php echo $isRegionsNavActive ? ' is-active' : ''; ?>" href="/regions/"<?php echo $isRegionsNavActive ? ' aria-current="page"' : ''; ?>>Регионы</a>
									<a class="home-mobile-menu-link<?php echo $isArticlesNavActive ? ' is-active' : ''; ?>" href="/articles/"<?php echo $isArticlesNavActive ? ' aria-current="page"' : ''; ?>>Статьи</a>
									<a class="home-mobile-menu-link home-mobile-menu-link--external" href="<?php echo $forumExternalUrl; ?>" target="_blank" rel="noopener noreferrer">Форум</a>
								</nav>
								<div class="home-mobile-menu-actions">
									<a class="home-mobile-menu-action" href="/profile/" aria-label="<?php echo $sanitizer->entities($profileAriaLabel); ?>"<?php echo $profileLinkAttrs; ?>>
										<img class="icon-img" src="<?php echo $config->urls->templates; ?>assets/icons/profile.svg" alt="" aria-hidden="true" />
										<span><?php echo $sanitizer->entities($profileButtonLabel); ?></span>
									</a>
									<a class="home-mobile-menu-action" href="/contacts/" aria-label="Контакты" data-contacts-open>
										<img class="icon-img" src="<?php echo $config->urls->templates; ?>assets/icons/contacts.svg" alt="" aria-hidden="true" />
										<span>Контакты</span>
									</a>
								</div>
							</div>
						</div>
					</header>
				<?php else: ?>
					<header class="site-header site-header--overlay site-header--home" id="site-header">
						<div class="container header-row header-row--home">
							<a class="icon-btn home-header-profile" href="/profile/" aria-label="<?php echo $sanitizer->entities($profileAriaLabel); ?>"<?php echo $profileLinkAttrs; ?>>
								<img class="icon-img" src="<?php echo $config->urls->templates; ?>assets/icons/profile.svg" alt="" aria-hidden="true" />
								<span><?php echo $sanitizer->entities($profileButtonLabel); ?></span>
							</a>
							<a class="logo logo--home home-header-logo" href="<?php echo $home->url; ?>" aria-label="SKFO.RU">
								<img class="logo-eagle-img" src="<?php echo $config->urls->templates; ?>assets/icons/logo-eagle.svg" alt="" aria-hidden="true" />
								<img class="logo-img" src="<?php echo $config->urls->templates; ?>assets/icons/logo.svg" alt="SKFO.RU" />
							</a>
							<a class="icon-btn home-header-contacts" href="/contacts/" aria-label="Контакты" data-contacts-open>
								<img class="icon-img" src="<?php echo $config->urls->templates; ?>assets/icons/contacts.svg" alt="" aria-hidden="true" />
								<span>Контакты</span>
							</a>
							<button class="icon-btn home-burger-toggle" type="button" aria-label="Открыть меню" aria-expanded="false" aria-controls="home-mobile-menu" data-home-menu-toggle>
								<span class="home-burger-icon" aria-hidden="true"></span>
							</button>
						</div>
						<div class="container home-mobile-menu-wrap">
							<div class="home-mobile-menu" id="home-mobile-menu" hidden data-home-menu>
								<nav class="home-mobile-menu-nav" aria-label="Навигация по сайту">
									<a class="home-mobile-menu-link<?php echo $isToursMenuActive ? ' is-active' : ''; ?>" href="<?php echo $home->url; ?>"<?php echo $isToursMenuActive ? ' aria-current="page"' : ''; ?>>Туры</a>
									<a class="home-mobile-menu-link<?php echo $isHotelsNavActive ? ' is-active' : ''; ?>" href="/hotels/"<?php echo $isHotelsNavActive ? ' aria-current="page"' : ''; ?>>Отели</a>
									<a class="home-mobile-menu-link<?php echo $isReviewsNavActive ? ' is-active' : ''; ?>" href="/reviews/"<?php echo $isReviewsNavActive ? ' aria-current="page"' : ''; ?>>Отзывы</a>
									<a class="home-mobile-menu-link<?php echo $isGuidesNavActive ? ' is-active' : ''; ?>" href="/guides/"<?php echo $isGuidesNavActive ? ' aria-current="page"' : ''; ?>>Гиды</a>
									<a class="home-mobile-menu-link<?php echo $isRegionsNavActive ? ' is-active' : ''; ?>" href="/regions/"<?php echo $isRegionsNavActive ? ' aria-current="page"' : ''; ?>>Регионы</a>
									<a class="home-mobile-menu-link<?php echo $isArticlesNavActive ? ' is-active' : ''; ?>" href="/articles/"<?php echo $isArticlesNavActive ? ' aria-current="page"' : ''; ?>>Статьи</a>
									<a class="home-mobile-menu-link home-mobile-menu-link--external" href="<?php echo $forumExternalUrl; ?>" target="_blank" rel="noopener noreferrer">Форум</a>
								</nav>
								<div class="home-mobile-menu-actions">
									<a class="home-mobile-menu-action" href="/profile/" aria-label="<?php echo $sanitizer->entities($profileAriaLabel); ?>"<?php echo $profileLinkAttrs; ?>>
										<img class="icon-img" src="<?php echo $config->urls->templates; ?>assets/icons/profile.svg" alt="" aria-hidden="true" />
										<span><?php echo $sanitizer->entities($profileButtonLabel); ?></span>
									</a>
									<a class="home-mobile-menu-action" href="/contacts/" aria-label="Контакты" data-contacts-open>
										<img class="icon-img" src="<?php echo $config->urls->templates; ?>assets/icons/contacts.svg" alt="" aria-hidden="true" />
										<span>Контакты</span>
									</a>
								</div>
							</div>
						</div>
					</header>
				<?php endif; ?>
				<?php endif; ?>

			<main id="content" class="site-main">
				Default content
			</main>

			<?php if(!$isContentAdminPage): ?>
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
					<a class="footer-section-item" href="<?php echo $forumExternalUrl; ?>" target="_blank" rel="noopener noreferrer">
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
					<a class="footer-section-item" href="/guides/">
						<span class="footer-section-icon"><img src="<?php echo $config->urls->templates; ?>assets/icons/human.svg" alt="" aria-hidden="true" /></span>
						<span class="footer-section-text">
							<span class="footer-section-title">Гиды</span>
							<span class="footer-section-subtitle">Эксперты по регионам</span>
						</span>
					</a>
					<a class="footer-section-item" href="/places/">
						<span class="footer-section-icon"><img src="<?php echo $config->urls->templates; ?>assets/icons/location_on.svg" alt="" aria-hidden="true" /></span>
						<span class="footer-section-text">
							<span class="footer-section-title">Места</span>
							<span class="footer-section-subtitle">Локации для путешествий</span>
						</span>
					</a>
				</div>
				</div>
			</footer>
			<?php endif; ?>

			<?php if (!$isContentAdminPage && !$isAuthLoggedIn): ?>
				<div class="auth-modal" id="auth-modal" hidden data-auth-api-url="<?php echo $sanitizer->entities((string) $page->url); ?>" data-csrf-name="<?php echo $sanitizer->entities($authCsrfTokenName); ?>" data-csrf-value="<?php echo $sanitizer->entities($authCsrfTokenValue); ?>" data-recaptcha-enabled="<?php echo $reCaptchaSiteKey !== '' ? '1' : '0'; ?>">
				<div class="auth-modal-backdrop" data-auth-close></div>
				<div class="auth-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="auth-modal-title">
					<button class="auth-modal-close" type="button" aria-label="Закрыть" data-auth-close>×</button>
					<div class="auth-pane is-active" data-auth-pane="login">
						<h2 class="auth-title" id="auth-modal-title">Войдите в профиль</h2>
						<p class="auth-subtitle">Чтобы хранить билеты в одном месте и обращаться в поддержку</p>
						<form class="auth-form" data-auth-form="login">
							<label class="auth-field">
								<input type="email" name="email" placeholder="Email" autocomplete="email" required />
								<button class="auth-code-btn" type="button" data-auth-send-code>получить код</button>
							</label>
							<label class="auth-field">
								<input type="text" name="code" placeholder="Код из письма" autocomplete="one-time-code" inputmode="numeric" maxlength="6" required />
							</label>
							<?php if ($reCaptchaSiteKey !== ''): ?>
								<div class="auth-captcha">
									<div class="g-recaptcha" data-sitekey="<?php echo $sanitizer->entities($reCaptchaSiteKey); ?>"></div>
								</div>
							<?php endif; ?>
							<button class="auth-submit-btn" type="submit">Войти</button>
						</form>
						<p class="auth-switch-row">Еще нет профиля? <button type="button" class="auth-switch-link" data-auth-switch="register">Регистрация</button></p>
					</div>
					<div class="auth-pane" data-auth-pane="register">
						<h2 class="auth-title">Регистрация</h2>
						<p class="auth-subtitle">Создайте профиль за минуту</p>
						<form class="auth-form" data-auth-form="register">
							<label class="auth-field">
								<input type="text" name="name" placeholder="Имя" autocomplete="name" required />
							</label>
							<label class="auth-field">
								<input type="email" name="email" placeholder="Email" autocomplete="email" required />
								<button class="auth-code-btn" type="button" data-auth-send-code>получить код</button>
							</label>
							<label class="auth-field">
								<input type="text" name="code" placeholder="Код из письма" autocomplete="one-time-code" inputmode="numeric" maxlength="6" required />
							</label>
							<?php if ($reCaptchaSiteKey !== ''): ?>
								<div class="auth-captcha">
									<div class="g-recaptcha" data-sitekey="<?php echo $sanitizer->entities($reCaptchaSiteKey); ?>"></div>
								</div>
							<?php endif; ?>
							<button class="auth-submit-btn" type="submit">Регистрация</button>
						</form>
						<p class="auth-switch-row">Уже есть профиль? <button type="button" class="auth-switch-link" data-auth-switch="login">Войти</button></p>
					</div>
					<p class="auth-message" data-auth-message aria-live="polite"></p>
				</div>
				</div>
			<?php endif; ?>

			<?php if(!$isContentAdminPage): ?>
			<div class="auth-modal contacts-modal" id="contacts-modal" hidden>
				<div class="auth-modal-backdrop" data-contacts-close></div>
				<div class="auth-modal-dialog contacts-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="contacts-modal-title">
				<button class="auth-modal-close" type="button" aria-label="Закрыть" data-contacts-close>×</button>
				<h2 class="auth-title" id="contacts-modal-title">Контакты</h2>
				<p class="contacts-subtitle">При наличии вопросов, пожалуйста, обратитесь на почту или по номеру телефона.</p>
				<p class="contacts-hours">Ежедневно 10:00-20:00 (МСК)</p>
				<div class="contacts-actions">
					<a class="contacts-action-btn" href="tel:+79000000000">
						<span class="contacts-action-icon" aria-hidden="true">
							<img src="<?php echo $config->urls->templates; ?>assets/icons/contacts-call.svg" alt="" />
						</span>
						<span>+7 900 000-00-00</span>
					</a>
					<a class="contacts-action-btn" href="mailto:info@skfo.ru">
						<span class="contacts-action-icon" aria-hidden="true">
							<img src="<?php echo $config->urls->templates; ?>assets/icons/contacts-mail.svg" alt="" />
						</span>
						<span>skfo@pochta.ru</span>
					</a>
				</div>
				</div>
			</div>
			<?php endif; ?>

			<?php if(!$isContentAdminPage): ?>
			<?php if($reCaptchaSiteKey !== ''): ?>
			<script src="https://www.google.com/recaptcha/api.js?hl=ru" async defer></script>
			<?php endif; ?>
			<script src="<?php echo $config->urls->templates; ?>scripts/main.js<?php echo $mainJsVersion ? '?v=' . (int) $mainJsVersion : ''; ?>"></script>
			<?php endif; ?>
		</body>
	</html>
