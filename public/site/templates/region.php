<?php namespace ProcessWire;

$regionTitle = trim((string) $page->title);
if ($regionTitle === '') {
	$regionTitle = 'Регион';
}
?>

<div id="content" class="region-page">
	<section class="reviews-hero">
		<div class="container">
			<div class="region-hero-inner">
				<h1 class="reviews-title"><?php echo $sanitizer->entities($regionTitle); ?></h1>
				<a class="region-back-btn" href="/regions/">Вернуться назад</a>
			</div>
		</div>
	</section>

	<section class="regions-content">
		<div class="container">
			<div class="region-placeholder">
				<p class="region-placeholder-text">Страница региона в разработке.</p>
			</div>
		</div>
	</section>
</div>
