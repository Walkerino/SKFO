<?php namespace ProcessWire;

$getImageUrlFromValue = static function($imageValue): string {
	if ($imageValue instanceof Pageimage) return $imageValue->url;
	if ($imageValue instanceof Pageimages && $imageValue->count()) return $imageValue->first()->url;
	return '';
};

$getFirstText = static function(Page $item, array $fieldNames): string {
	foreach ($fieldNames as $fieldName) {
		if (!$item->hasField($fieldName)) continue;
		$value = trim((string) $item->$fieldName);
		if ($value !== '') return $value;
	}
	return '';
};

$placeTitle = trim((string) $page->title);
$placeRegion = $getFirstText($page, ['place_region', 'region']);
$placeSummary = $getFirstText($page, ['place_summary', 'summary']);
$placeContent = $getFirstText($page, ['place_content', 'content']);

$placeImageUrl = '';
foreach (['place_cover_image', 'images'] as $imageField) {
	if (!$page->hasField($imageField)) continue;
	$placeImageUrl = $getImageUrlFromValue($page->getUnformatted($imageField));
	if ($placeImageUrl !== '') break;
}

$backUrl = '/places/';
$placesPage = $pages->get('/places/');
if (!$placesPage || !$placesPage->id) {
	$backUrl = '/regions/';
}

$contentParagraphs = [];
if ($placeContent !== '') {
	$parts = preg_split('/\R{2,}/u', str_replace("\r", '', $placeContent)) ?: [];
	foreach ($parts as $part) {
		$paragraph = trim((string) $part);
		if ($paragraph !== '') $contentParagraphs[] = $paragraph;
	}
}
?>

<div id="content" class="site-main">
	<section class="section">
		<div class="container">
			<p><a href="<?php echo $sanitizer->entities($backUrl); ?>">К списку мест</a></p>
			<h1><?php echo $sanitizer->entities($placeTitle); ?></h1>

			<?php if ($placeImageUrl !== ''): ?>
				<div class="hotel-card-media" style="max-width: 760px; height: 360px; margin: 20px 0; background-image: url('<?php echo htmlspecialchars($placeImageUrl, ENT_QUOTES, 'UTF-8'); ?>');"></div>
			<?php endif; ?>

			<?php if ($placeRegion !== ''): ?>
				<p><strong>Регион:</strong> <?php echo $sanitizer->entities($placeRegion); ?></p>
			<?php endif; ?>
			<?php if ($placeSummary !== ''): ?>
				<p><?php echo $sanitizer->entities($placeSummary); ?></p>
			<?php endif; ?>

			<?php foreach ($contentParagraphs as $paragraph): ?>
				<p><?php echo nl2br($sanitizer->entities($paragraph)); ?></p>
			<?php endforeach; ?>
		</div>
	</section>
</div>
