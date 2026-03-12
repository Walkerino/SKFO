<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

/** @var ProcessWire $wire */

/**
 * ProcessWire Bootstrap Initialization
 * ====================================
 * This init.php file is called during ProcessWire bootstrap initialization process.
 * This occurs after all autoload modules have been initialized, but before the current page
 * has been determined. This is a good place to attach hooks. You may place whatever you'd
 * like in this file. For example:
 *
 * $wire->addHookAfter('Page::render', function($event) {
 *   $event->return = str_replace("</body>", "<p>Hello World</p></body>", $event->return);
 * });
 *
 */

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '';

if ($requestPath === '/region' || $requestPath === '/region/') {
	$query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . (string) $_SERVER['QUERY_STRING'] : '';
	$wire->session->redirect('/regions/' . $query, true);
}

if (preg_match('#^/region/([^/]+)/?$#', $requestPath, $matches) === 1) {
	$legacySlug = trim((string) ($matches[1] ?? ''));
	$slugMap = [
		'dagestan' => 'respublika-dagestan',
		'ingushetya' => 'respublika-ingushetiya',
		'kbr' => 'kabardino-balkarskaya-respublika',
		'kchr' => 'karachaevo-cherkesskaya-respublika',
		'ossetia' => 'respublika-severnaya-osetiya',
		'chechnya' => 'chechenskaya-respublika',
		'stavropolye' => 'stavropolskiy-kray',
	];
	$targetSlug = $slugMap[$legacySlug] ?? '';

	if ($targetSlug !== '') {
		$query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . (string) $_SERVER['QUERY_STRING'] : '';
		$wire->session->redirect('/regions/' . $targetSlug . '/' . $query, true);
	}
}

$isProfileRequest = $requestPath === '/profile' || $requestPath === '/profile/';
if ($isProfileRequest) {
	$profilePage = $wire->pages->get('include=all, path=/profile/');
	if (!$profilePage instanceof Page || !$profilePage->id) {
		$profileTemplate = $wire->templates->get('basic-page');
		$homePage = $wire->pages->get('/');

		if ($profileTemplate instanceof Template && $profileTemplate->id && $homePage instanceof Page && $homePage->id) {
			try {
				$newProfilePage = new Page();
				$newProfilePage->template = $profileTemplate;
				$newProfilePage->parent = $homePage;
				$newProfilePage->name = 'profile';
				$newProfilePage->title = 'Профиль';
				$newProfilePage->save();
			} catch (\Throwable $e) {
				// Keep request flow untouched. Existing auth modal still allows sign-in as fallback.
			}
		}
	}
}
