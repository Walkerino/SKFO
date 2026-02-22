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
	Basic page content 
</div>	
