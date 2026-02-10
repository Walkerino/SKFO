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

?><!DOCTYPE html>
<html lang="ru">
	<head id="html-head">
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title><?php echo $page->title; ?> | SKFO.RU</title>
		<link rel="stylesheet" type="text/css" href="<?php echo $config->urls->templates; ?>styles/main.css" />
	</head>
	<body id="html-body">
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

		<main id="content" class="site-main">
			Default content
		</main>

		<footer class="site-footer" id="site-footer">
			<div class="container footer-grid">
				<div class="footer-brand">
					<div class="footer-logo">SKFO.RU</div>
					<div class="footer-copy">© 2026</div>
					<div class="footer-social">
						<a class="social-btn" href="#" aria-label="VK">VK</a>
						<a class="social-btn" href="#" aria-label="Telegram">TG</a>
						<a class="social-btn" href="#" aria-label="YouTube">YT</a>
					</div>
				</div>
				<div class="footer-links">
					<div class="footer-title">О нас</div>
					<ul>
						<li><a href="/support/">Поддержка</a></li>
						<li><a href="/services/">Размещение услуг</a></li>
						<li><a href="/privacy/">Политика конфиденциальности</a></li>
						<li><a href="/terms/">Правила использования</a></li>
					</ul>
				</div>
				<div class="footer-links">
					<div class="footer-title">Разделы</div>
					<ul>
						<?php foreach($mainNav as $item): ?>
							<li><a href="<?php echo $item['url']; ?>"><?php echo $item['label']; ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</footer>

		<script src="<?php echo $config->urls->templates; ?>scripts/main.js"></script>
	</body>
</html>
