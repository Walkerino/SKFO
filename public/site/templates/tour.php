<?php namespace ProcessWire;

$tourTitle = trim((string) $page->title);
$tourRegion = $page->hasField('tour_region') ? trim((string) $page->tour_region) : '';
$tourDescription = $page->hasField('tour_description') ? trim((string) $page->tour_description) : '';
$tourPrice = $page->hasField('tour_price') ? trim((string) $page->tour_price) : '';
$tourDuration = $page->hasField('tour_duration') ? trim((string) $page->tour_duration) : '';
$tourGroup = $page->hasField('tour_group') ? trim((string) $page->tour_group) : '';
$tourSeason = $page->hasField('tour_season') ? trim((string) $page->tour_season) : '';
$tourDifficulty = $page->hasField('tour_difficulty') ? trim((string) $page->tour_difficulty) : '';
$tourAge = $page->hasField('tour_age') ? trim((string) $page->tour_age) : '';

$tourImageUrl = '';
if ($page->hasField('tour_cover_image')) {
	$cover = $page->getUnformatted('tour_cover_image');
	if ($cover instanceof Pageimage) {
		$tourImageUrl = $cover->url;
	} elseif ($cover instanceof Pageimages && $cover->count()) {
		$tourImageUrl = $cover->first()->url;
	}
}

if ($tourTitle === '') $tourTitle = 'Четырехдневный тур по Дагестану';
if ($tourRegion === '') $tourRegion = 'Республика Дагестан';
if ($tourDescription === '') {
	$tourDescription = "Четырехдневный тур по самым живописным местам Дагестана: от Сулакского каньона до Гунибского района.\n\nВас ждут горные пейзажи, водопады, древние села и уникальные природные объекты.";
}
if ($tourPrice === '') $tourPrice = '23 251₽';
if ($tourDuration === '') $tourDuration = '4 дня';
if ($tourGroup === '') $tourGroup = '4-12 человек';
if ($tourSeason === '') $tourSeason = 'Май-Октябрь';
if ($tourDifficulty === '') $tourDifficulty = 'Базовая';
if ($tourAge === '') $tourAge = '12+';

$includedRaw = $page->hasField('tour_included') ? trim((string) $page->tour_included) : '';
$includedItems = array_values(array_filter(array_map('trim', preg_split('/\R+/', $includedRaw))));
if (!count($includedItems)) {
	$includedItems = [
		'Проживание в гостевых домах в горах',
		'Трехразовое питание',
		'Входные билеты на локации',
		'Прогулка на катере',
	];
}

$tourDays = [];
if ($page->hasField('tour_days') && $page->tour_days->count()) {
	foreach ($page->tour_days as $day) {
		$dayImages = [];
		if ($day->hasField('tour_day_images')) {
			$images = $day->getUnformatted('tour_day_images');
			if ($images instanceof Pageimages && $images->count()) {
				foreach ($images as $img) {
					$dayImages[] = $img->url;
				}
			} elseif ($images instanceof Pageimage) {
				$dayImages[] = $images->url;
			}
		}

		$tourDays[] = [
			'title' => $day->hasField('tour_day_title') ? trim((string) $day->tour_day_title) : '',
			'description' => $day->hasField('tour_day_description') ? trim((string) $day->tour_day_description) : '',
			'images' => $dayImages,
		];
	}
}

if (!count($tourDays)) {
	$tourDays = [
		[
			'title' => 'День 1. Сулакский каньон',
			'description' => 'Знакомство с Дагестаном и главными видовыми точками маршрута.',
			'images' => [],
		],
	];
}

?>

<div id="content" class="tour-page">
	<section class="tour-info">
		<div class="container tour-info-layout">
			<div class="tour-info-main">
				<div class="tour-badge"><?php echo $sanitizer->entities($tourRegion); ?></div>
				<h1 class="tour-title"><?php echo $sanitizer->entities($tourTitle); ?></h1>
				<p class="tour-description"><?php echo nl2br($sanitizer->entities($tourDescription)); ?></p>
			</div>
			<div class="tour-info-side">
				<div class="tour-price"><?php echo $sanitizer->entities($tourPrice); ?> за человека</div>
				<dl class="tour-meta">
					<div><dt>Длительность</dt><dd><?php echo $sanitizer->entities($tourDuration); ?></dd></div>
					<div><dt>Группа</dt><dd><?php echo $sanitizer->entities($tourGroup); ?></dd></div>
					<div><dt>Сложность</dt><dd><?php echo $sanitizer->entities($tourDifficulty); ?></dd></div>
					<div><dt>Сезонность</dt><dd><?php echo $sanitizer->entities($tourSeason); ?></dd></div>
					<div><dt>Возраст</dt><dd><?php echo $sanitizer->entities($tourAge); ?></dd></div>
				</dl>
			</div>
		</div>
	</section>

	<section class="tour-hero-media">
		<div class="container">
			<div class="tour-cover"<?php echo $tourImageUrl ? " style=\"background-image: url('".htmlspecialchars($tourImageUrl, ENT_QUOTES, 'UTF-8')."');\"" : ''; ?>></div>
		</div>
	</section>

	<section class="tour-included">
		<div class="container">
			<h2 class="tour-section-title">Что включено</h2>
			<ul class="tour-included-list">
				<?php foreach ($includedItems as $item): ?>
					<li><?php echo $sanitizer->entities($item); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<section class="tour-days">
		<div class="container">
			<h2 class="tour-section-title">Информация по дням</h2>
			<div class="tour-days-list">
				<?php foreach ($tourDays as $dayIndex => $day): ?>
					<article class="tour-day-card">
						<h3 class="tour-day-title">
							День <?php echo (int) $dayIndex + 1; ?><?php echo $day['title'] !== '' ? '. ' . $sanitizer->entities($day['title']) : ''; ?>
						</h3>
						<p class="tour-day-description"><?php echo nl2br($sanitizer->entities($day['description'])); ?></p>
						<?php if (count($day['images'])): ?>
							<div class="tour-day-images">
								<?php foreach ($day['images'] as $img): ?>
									<div class="tour-day-image" style="background-image: url('<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>');"></div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
</div>
