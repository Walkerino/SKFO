<?php namespace ProcessWire; 

// Template file for pages using the “basic-page” template
// -------------------------------------------------------
// The #content div in this file will replace the #content div in _main.php
// when the Markup Regions feature is enabled, as it is by default. 
// You can also append to (or prepend to) the #content div, and much more. 
// See the Markup Regions documentation:
// https://processwire.com/docs/front-end/output/markup-regions/

?>

<?php
$legalConfig = isset($skfoLegalConfig) && is_array($skfoLegalConfig) ? $skfoLegalConfig : skfoLegalConfig();
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$isReviewsRequest = $requestPath === '/reviews' || $requestPath === '/reviews/';
$isRegionsRequest = $requestPath === '/regions' || $requestPath === '/regions/';
$isArticlesRequest = $requestPath === '/articles' || $requestPath === '/articles/';
$isHotelsRequest = preg_match('#^/hotels(?:/|$)#', (string) $requestPath) === 1;
$isHotelsSearchRequest = trim((string) $input->get('search_hotels')) === '1';
$isProfileRequest = $requestPath === '/profile' || $requestPath === '/profile/';
if ($page->name === 'reviews' || $page->path === '/reviews/' || $isReviewsRequest) {
	require __DIR__ . '/reviews.php';
	return;
}
if ($page->name === 'regions' || $page->path === '/regions/' || $isRegionsRequest) {
	require __DIR__ . '/regions.php';
	return;
}
if ($page->name === 'articles' || $page->path === '/articles/' || $isArticlesRequest) {
	require __DIR__ . '/articles.php';
	return;
}
if ($page->name === 'hotels' || $page->path === '/hotels/' || $isHotelsRequest || $isHotelsSearchRequest) {
	require __DIR__ . '/hotels.php';
	return;
}
if ($page->name === 'profile' || $page->path === '/profile/' || $isProfileRequest) {
	require __DIR__ . '/profile.php';
	return;
}
?>

<div id="content">
	<?php
	$pageName = trim((string) $page->name);
	$pageTitle = trim((string) $page->title);
	$customLegalSections = [];
	if ($pageName === 'terms') {
		$pageTitle = (string) ($legalConfig['terms_title'] ?? 'Пользовательское соглашение');
		$customLegalSections = (array) ($legalConfig['terms_sections'] ?? []);
	} elseif ($pageName === 'privacy') {
		$pageTitle = (string) ($legalConfig['privacy_title'] ?? 'Политика обработки цифровых данных');
		$customLegalSections = (array) ($legalConfig['privacy_sections'] ?? []);
	}
	$pageBody = '';
	if ($page->hasField('body')) {
		$pageBody = trim((string) $page->getUnformatted('body'));
	}
	?>
	<div class="container basic-page-content">
		<?php if ($pageTitle !== ''): ?>
			<h1><?php echo $sanitizer->entities($pageTitle); ?></h1>
		<?php endif; ?>

		<?php if ($pageBody !== '' && !count($customLegalSections)): ?>
			<div class="basic-page-body"><?php echo $pageBody; ?></div>
		<?php endif; ?>

		<?php if (count($customLegalSections)): ?>
			<div class="basic-page-legal-stack">
				<?php foreach ($customLegalSections as $section): ?>
					<?php
					$sectionTitle = trim((string) ($section['title'] ?? ''));
					$paragraphs = array_values(array_filter((array) ($section['paragraphs'] ?? []), static function($value): bool {
						return trim((string) $value) !== '';
					}));
					if ($sectionTitle === '' && !count($paragraphs)) continue;
					?>
					<section class="basic-page-legal">
						<?php if ($sectionTitle !== ''): ?>
							<h2><?php echo $sanitizer->entities($sectionTitle); ?></h2>
						<?php endif; ?>
						<?php foreach ($paragraphs as $paragraph): ?>
							<p><?php echo $sanitizer->entities((string) $paragraph); ?></p>
						<?php endforeach; ?>
					</section>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ($pageName === 'services'): ?>
			<section class="basic-page-legal">
				<h2>Условия размещения услуг</h2>
				<p>Публикуя предложение на платформе, организатор подтверждает достоверность информации о маршруте, стоимости, составе услуг и условиях участия.</p>
				<p>Организатор обязуется соблюдать действующее законодательство РФ и самостоятельно нести ответственность перед пользователем за оказанные услуги.</p>
			</section>
		<?php endif; ?>
	</div>
</div>	
