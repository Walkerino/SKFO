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

$wire->addHookBefore('ProcessPageView::pageNotFound', function(HookEvent $event) use ($wire): void {
	$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
	$path = is_string($path) ? rtrim($path, '/') : '';
	if ($path === '') $path = '/';

	$legalPages = [
		'/terms' => [
			'title' => 'Правила использования сайта',
			'content' => [
				'SKFO.ru является информационной платформой, на которой организаторы публикуют маршруты, а пользователи знакомятся с условиями и оставляют заявки.',
				'SKFO.ru не выступает туроператором и не формирует туристский продукт. Договор оказания услуг заключается непосредственно между пользователем и организатором.',
				'Ответственность за фактическое оказание услуг, программу, безопасность и изменения условий несет организатор, указанный в карточке маршрута.',
			],
		],
		'/services' => [
			'title' => 'Размещение услуг',
			'content' => [
				'Публикуя предложение на платформе, организатор подтверждает достоверность информации о маршруте, стоимости, составе услуг и условиях участия.',
				'Организатор обязуется соблюдать действующее законодательство РФ и самостоятельно нести ответственность перед пользователем за оказанные услуги.',
			],
		],
	];

	if (!isset($legalPages[$path])) return;

	$pageData = $legalPages[$path];
	$title = (string) ($pageData['title'] ?? 'Информация');
	$content = (array) ($pageData['content'] ?? []);
	$titleSafe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
	$paragraphs = '';
	foreach ($content as $paragraph) {
		$text = trim((string) $paragraph);
		if ($text === '') continue;
		$paragraphs .= '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
	}

	http_response_code(200);
	header('Content-Type: text/html; charset=UTF-8');
	echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
		. $titleSafe
		. ' | SKFO</title><style>body{margin:0;font-family:Arial,sans-serif;background:#f7f9fc;color:#1f2937}main{max-width:900px;margin:48px auto;padding:0 20px}h1{font-size:32px;margin:0 0 20px}p{font-size:18px;line-height:1.6;margin:0 0 14px}.actions{margin-top:28px}a{color:#1f52b2;text-decoration:none;font-weight:600}a:hover{text-decoration:underline}</style></head><body><main><h1>'
		. $titleSafe
		. '</h1>'
		. $paragraphs
		. '<div class="actions"><a href="/">Вернуться на главную</a></div></main></body></html>';
	exit;
});

$ensureBasicPage = static function(string $slug, string $title) use ($wire): void {
	$slug = trim($slug);
	$title = trim($title);
	if ($slug === '' || $title === '') return;

	$pagePath = '/' . trim($slug, '/') . '/';
	$existingPage = $wire->pages->get('include=all, status<8192, path=' . $pagePath);
	$pageTemplate = $wire->templates->get('basic-page');
	if (!$pageTemplate instanceof Template || !$pageTemplate->id) {
		$pageTemplate = $wire->templates->get('page');
	}
	$homePage = $wire->pages->get('/');
	if (!$pageTemplate instanceof Template || !$pageTemplate->id) return;
	if (!$homePage instanceof Page || !$homePage->id) return;

	$publishExistingPage = static function(Page $page) use ($title): void {
		$isChanged = false;
		if ((int) $page->status & Page::statusUnpublished) {
			$page->removeStatus(Page::statusUnpublished);
			$isChanged = true;
		}
		if ((int) $page->status & Page::statusHidden) {
			$page->removeStatus(Page::statusHidden);
			$isChanged = true;
		}
		if (trim((string) $page->title) === '') {
			$page->title = $title;
			$isChanged = true;
		}
		if ($isChanged) {
			$page->save();
		}
	};

	if ($existingPage instanceof Page && $existingPage->id) {
		$publishExistingPage($existingPage);
		return;
	}

	$users = $wire->users;
	$originalUser = $wire->user;
	$superuser = $users->get('roles=superuser, limit=1');
	if ($superuser instanceof User && $superuser->id) {
		$users->setCurrentUser($superuser);
	}

	try {
		$newPage = new Page();
		$newPage->template = $pageTemplate;
		$newPage->parent = $homePage;
		$newPage->name = trim($slug, '/');
		$newPage->title = $title;
		$newPage->save();
		$publishExistingPage($newPage);
	} catch (\Throwable $e) {
		// Keep request flow untouched.
	} finally {
		if ($originalUser instanceof User && $originalUser->id) {
			$users->setCurrentUser($originalUser);
		}
	}
};

$ensureTemplatePage = static function(string $templateName, string $label, string $fieldgroupName, string $slug, string $title) use ($wire): void {
	$templateName = trim($templateName);
	$label = trim($label);
	$fieldgroupName = trim($fieldgroupName);
	$slug = trim($slug, '/');
	$title = trim($title);
	if ($templateName === '' || $fieldgroupName === '' || $slug === '' || $title === '') return;

	$templates = $wire->templates;
	$fieldgroups = $wire->fieldgroups;
	$fields = $wire->fields;
	$pages = $wire->pages;
	$users = $wire->users;
	$homePage = $pages->get('/');
	if (!$homePage instanceof Page || !$homePage->id) return;

	$originalUser = $wire->user;
	$superuser = $users->get('roles=superuser, limit=1');
	if ($superuser instanceof User && $superuser->id) {
		$users->setCurrentUser($superuser);
	}

	try {
		$fieldgroup = $fieldgroups->get($fieldgroupName);
		if (!$fieldgroup instanceof Fieldgroup || !$fieldgroup->id) {
			$fieldgroup = new Fieldgroup();
			$fieldgroup->name = $fieldgroupName;
			$fieldgroups->save($fieldgroup);
		}

		$titleField = $fields->get('title');
		if ($titleField instanceof Field && $titleField->id && !$fieldgroup->has($titleField)) {
			$fieldgroup->add($titleField);
			$fieldgroups->save($fieldgroup);
		}

		$template = $templates->get($templateName);
		if (!$template instanceof Template || !$template->id) {
			$template = new Template();
			$template->name = $templateName;
			$template->label = $label !== '' ? $label : $title;
			$template->fieldgroup = $fieldgroup;
			$template->set('noChildren', 1);
			$template->set('noGlobal', 0);
			$templates->save($template);
			$template = $templates->get($templateName);
		}

		if (!$template instanceof Template || !$template->id) return;
		if ($titleField instanceof Field && $titleField->id && $template->fieldgroup instanceof Fieldgroup && !$template->fieldgroup->has($titleField)) {
			$template->fieldgroup->add($titleField);
			$template->fieldgroup->save();
		}

		$pagePath = '/' . $slug . '/';
		$page = $pages->get('include=all, status<8192, path=' . $pagePath);
		$isNewPage = !$page instanceof Page || !$page->id;
		if ($isNewPage) {
			$page = new Page();
			$page->parent = $homePage;
			$page->name = $slug;
		}

		if ($page instanceof Page) {
			$changed = $isNewPage;
			if (!$page->template instanceof Template || $page->template->name !== $templateName) {
				$page->template = $template;
				$changed = true;
			}
			if (trim((string) $page->title) !== $title) {
				$page->title = $title;
				$changed = true;
			}
			if ((int) $page->status & Page::statusUnpublished) {
				$page->removeStatus(Page::statusUnpublished);
				$changed = true;
			}
			if ((int) $page->status & Page::statusHidden) {
				$page->removeStatus(Page::statusHidden);
				$changed = true;
			}
			if ($changed) {
				$page->save();
			}
		}
	} catch (\Throwable $e) {
		// Keep request flow untouched.
	} finally {
		if ($originalUser instanceof User && $originalUser->id) {
			$users->setCurrentUser($originalUser);
		}
	}
};

$ensureTemplatePage('amirov-tour', 'Амиров Тур', 'partner_amirov_tour', 'amirov-tour', 'Амиров Тур');

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
	$ensureBasicPage('profile', 'Профиль');
}

$isTermsRequest = $requestPath === '/terms' || $requestPath === '/terms/';
if ($isTermsRequest) {
	$ensureBasicPage('terms', 'Правила использования сайта');
}

$isServicesRequest = $requestPath === '/services' || $requestPath === '/services/';
if ($isServicesRequest) {
	$ensureBasicPage('services', 'Размещение услуг');
}
