<?php namespace ProcessWire;

$toLower = static function(string $value): string {
	$value = trim($value);
	return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
};

$normalizeLine = static function(string $value): string {
	$line = trim(str_replace("\xc2\xa0", ' ', $value));
	$line = preg_replace('/\s+/u', ' ', $line) ?? $line;
	return trim($line);
};

$formatRating = static function(float $value): string {
	$value = max(0.0, min(5.0, $value));
	return str_replace('.', ',', number_format($value, 1, '.', ''));
};

$formatGuestLabel = static function(int $count): string {
	$count = max(1, $count);
	$mod10 = $count % 10;
	$mod100 = $count % 100;
	if ($mod10 === 1 && $mod100 !== 11) return $count . ' гость';
	if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) return $count . ' гостя';
	return $count . ' гостей';
};

$formatPrice = static function(string $value): string {
	$digits = (int) preg_replace('/[^\d]+/', '', $value);
	if ($digits > 0) return number_format($digits, 0, '', ' ') . '₽';

	$value = trim($value);
	if ($value !== '') return $value;
	return '10 000₽';
};

$getImageUrlFromValue = static function($imageValue): string {
	if ($imageValue instanceof Pageimage) return $imageValue->url;
	if ($imageValue instanceof Pageimages && $imageValue->count()) return $imageValue->first()->url;
	return '';
};

$amenityMap = [
	'wifi' => ['title' => 'Бесплатный Wi-Fi', 'icon' => 'hotel-amenity-wifi.svg'],
	'parking' => ['title' => 'Паркинг', 'icon' => 'hotel-amenity-parking.svg'],
	'elevator' => ['title' => 'Лифт', 'icon' => 'hotel-amenity-elevator.svg'],
	'soundproof_rooms' => ['title' => 'Звукоизолированные номера'],
	'air_conditioning' => ['title' => 'Кондиционер', 'short' => 'A/C'],
	'kids' => ['title' => 'Подходит для детей', 'icon' => 'hotel-amenity-kid.svg'],
	'tv' => ['title' => 'Телевизор', 'short' => 'TV'],
	'spa' => ['title' => 'Spa-центр', 'icon' => 'hotel-amenity-spa.svg'],
	'minibar' => ['title' => 'Мини-бар', 'icon' => 'hotel-amenity-minibar.svg'],
	'breakfast' => ['title' => 'Завтрак', 'icon' => 'hotel-amenity-breakfast.svg'],
	'transfer' => ['title' => 'Трансфер', 'icon' => 'hotel-amenity-transfer.svg'],
	'accessible' => ['title' => 'Удобства для людей с ограниченными возможностями', 'icon' => 'hotel-amenity-accessible.svg'],
	'gym' => ['title' => 'Спортивный зал', 'icon' => 'hotel-amenity-gym.svg'],
	'pool' => ['title' => 'Бассейн', 'icon' => 'hotel-amenity-pool.svg'],
	'restaurant' => ['title' => 'Ресторан'],
	'pets' => ['title' => 'Можно с питомцами'],
];

$defaultHotelImage = $config->urls->templates . 'assets/image1.png';
$defaultHotelGalleryAssets = [
	'assets/hotels/default-hotel-1.jpg',
	'assets/hotels/default-hotel-2.jpg',
	'assets/hotels/default-hotel-3.jpg',
	'assets/hotels/default-hotel-4.jpg',
	'assets/hotels/default-hotel-5.jpg',
	'assets/hotels/default-hotel-6.jpg',
	'assets/hotels/default-hotel-7.jpg',
];
$fallbackHotelGalleryAssets = [
	'site/assets/files/1123/a3b0fbb001a56c7f212673120328947d9d32b65d.png',
	'site/assets/files/1127/21f93814b89fb9b949fcd2ca84eb6f5d6d03a218.png',
	'site/assets/files/1061/rectangle_7-6.png',
	'site/assets/files/1057/rectangle_7-1.png',
	'site/assets/files/1058/rectangle_7.png',
	'site/assets/files/1125/c68a00bbd5889a870651731526a0dabc912dd64e.png',
	'site/assets/files/1126/df72bbf9803f91dae6ccae1ffda49c5b7ada217d.png',
];

$hotelTitle = trim((string) $page->title);
$hotelCity = $page->hasField('hotel_city') ? trim((string) $page->hotel_city) : '';
$hotelRegion = $page->hasField('hotel_region') ? trim((string) $page->hotel_region) : '';
$hotelLocation = trim($hotelCity . ', ' . $hotelRegion, ', ');

$hotelRatingRaw = $page->hasField('hotel_rating') ? trim((string) $page->getUnformatted('hotel_rating')) : '';
$hotelRating = is_numeric($hotelRatingRaw) ? (float) $hotelRatingRaw : 4.5;

$hotelPriceRaw = $page->hasField('hotel_price') ? (string) $page->getUnformatted('hotel_price') : '';
$hotelPriceLabel = $formatPrice($hotelPriceRaw);

$hotelMaxGuests = $page->hasField('hotel_max_guests') ? (int) $page->getUnformatted('hotel_max_guests') : 2;
if ($hotelMaxGuests < 1) $hotelMaxGuests = 1;
$hotelGuestsDefault = min(2, $hotelMaxGuests);
$hotelGuestsLabel = $formatGuestLabel($hotelGuestsDefault);

$hotelDescription = '';
if ($page->hasField('hotel_description')) $hotelDescription = trim((string) $page->hotel_description);
if ($hotelDescription === '' && $page->hasField('summary')) $hotelDescription = trim((string) $page->summary);
if ($hotelDescription === '' && $page->hasField('body')) $hotelDescription = trim((string) $page->body);

$hotelImageUrl = '';
if ($page->hasField('hotel_image')) {
	$hotelImageUrl = $getImageUrlFromValue($page->getUnformatted('hotel_image'));
}
if ($hotelImageUrl === '' && $page->hasField('images')) {
	$hotelImageUrl = $getImageUrlFromValue($page->getUnformatted('images'));
}
if ($hotelImageUrl === '') $hotelImageUrl = $defaultHotelImage;

if ($hotelTitle === '') $hotelTitle = 'Отель в СКФО';
if ($hotelLocation === '') $hotelLocation = 'Северо-Кавказский федеральный округ';
if ($hotelDescription === '') {
	$hotelDescription = "Комфортный отель для отдыха и поездок по региону.\nУдобное расположение, сервис и продуманная инфраструктура для гостей.";
}

$hotelAmenities = [];
$hotelAmenityKeys = [];
$addAmenity = static function(string $title, string $iconFile = '', string $iconText = '') use (&$hotelAmenities, &$hotelAmenityKeys, $toLower, $config): void {
	$title = trim($title);
	if ($title === '') return;
	$key = $toLower($title);
	if (isset($hotelAmenityKeys[$key])) return;
	$hotelAmenityKeys[$key] = true;
	$iconText = trim($iconText);
	$hotelAmenities[] = [
		'title' => $title,
		'icon_url' => $iconFile !== '' ? $config->urls->templates . 'assets/icons/' . ltrim($iconFile, '/') : '',
		'icon_text' => $iconText,
	];
};

if ($page->hasField('hotel_amenities')) {
	$amenitiesRaw = trim((string) $page->hotel_amenities);
	$lines = preg_split('/\R+/u', $amenitiesRaw) ?: [];
	foreach ($lines as $line) {
		$item = $normalizeLine((string) $line);
		if ($item === '') continue;

		$amenityCode = $toLower($item);
		if (isset($amenityMap[$amenityCode])) {
			$amenityConfig = $amenityMap[$amenityCode];
			$addAmenity(
				(string) ($amenityConfig['title'] ?? ''),
				(string) ($amenityConfig['icon'] ?? ''),
				(string) ($amenityConfig['short'] ?? '')
			);
			continue;
		}

		$addAmenity($item);
	}
}

if (!count($hotelAmenities)) {
	foreach (['wifi', 'breakfast', 'parking', 'transfer'] as $fallbackAmenityCode) {
		if (!isset($amenityMap[$fallbackAmenityCode])) continue;
		$amenityConfig = $amenityMap[$fallbackAmenityCode];
		$addAmenity(
			(string) ($amenityConfig['title'] ?? ''),
			(string) ($amenityConfig['icon'] ?? ''),
			(string) ($amenityConfig['short'] ?? '')
		);
	}
}

$hotelMedia = [];
$hotelMediaKeys = [];
$addMediaImage = static function(string $url) use (&$hotelMedia, &$hotelMediaKeys): void {
	$url = trim($url);
	if ($url === '') return;
	if (isset($hotelMediaKeys[$url])) return;
	$hotelMediaKeys[$url] = true;
	$hotelMedia[] = $url;
};

$addMediaImage($hotelImageUrl);

foreach (['hotel_gallery', 'hotel_images', 'images', 'hotel_image'] as $imageFieldName) {
	if (!$page->hasField($imageFieldName)) continue;
	$fieldValue = $page->getUnformatted($imageFieldName);
	if ($fieldValue instanceof Pageimage) {
		$addMediaImage($fieldValue->url);
		continue;
	}
	if ($fieldValue instanceof Pageimages && $fieldValue->count()) {
		foreach ($fieldValue as $image) {
			if (!$image instanceof Pageimage) continue;
			$addMediaImage($image->url);
		}
	}
}

if (count($hotelMedia) < 4) {
	foreach ($defaultHotelGalleryAssets as $assetPath) {
		$assetPath = ltrim($assetPath, '/');
		if (!is_file($config->paths->templates . $assetPath)) continue;
		$addMediaImage($config->urls->templates . $assetPath);
		if (count($hotelMedia) >= 8) break;
	}
}
if (!count($hotelMedia)) {
	foreach ($fallbackHotelGalleryAssets as $assetPath) {
		$assetPath = ltrim($assetPath, '/');
		if (!is_file($config->paths->root . $assetPath)) continue;
		$addMediaImage($config->urls->root . $assetPath);
	}
}
if (!count($hotelMedia)) $addMediaImage($defaultHotelImage);
if (count($hotelMedia) > 12) $hotelMedia = array_slice($hotelMedia, 0, 12);
?>

<div id="content" class="tour-page hotel-page">
	<section class="tour-hero hotel-hero">
		<div class="container">
			<div class="tour-hero-shape">
				<div class="tour-hero-layout">
					<div class="tour-hero-main">
						<h1 class="tour-title"><?php echo $sanitizer->entities($hotelTitle); ?></h1>
						<p class="tour-description"><?php echo nl2br($sanitizer->entities($hotelDescription)); ?></p>
					</div>
					<div class="tour-hero-media">
						<div class="tour-badge">
							<img src="<?php echo $config->urls->templates; ?>assets/icons/location_on.svg" alt="" aria-hidden="true" />
							<span><?php echo $sanitizer->entities($hotelLocation); ?></span>
						</div>
						<div class="tour-cover"<?php echo $hotelImageUrl !== '' ? " style=\"background-image: url('" . htmlspecialchars($hotelImageUrl, ENT_QUOTES, 'UTF-8') . "');\"" : ''; ?>></div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="tour-overview hotel-overview">
		<div class="container tour-overview-layout">
			<div class="tour-included-card hotel-amenities-card">
				<h2 class="tour-section-title">Сервис и удобства</h2>
				<ul class="tour-included-list hotel-included-list">
					<?php foreach ($hotelAmenities as $amenity): ?>
						<li>
							<?php if ((string) ($amenity['icon_url'] ?? '') !== ''): ?>
								<img class="hotel-included-icon" src="<?php echo $sanitizer->entities((string) ($amenity['icon_url'] ?? '')); ?>" alt="" aria-hidden="true" />
							<?php elseif ((string) ($amenity['icon_text'] ?? '') !== ''): ?>
								<span class="hotel-included-icon-badge"><?php echo $sanitizer->entities((string) ($amenity['icon_text'] ?? '')); ?></span>
							<?php endif; ?>
							<span><?php echo $sanitizer->entities((string) ($amenity['title'] ?? '')); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="tour-details-card hotel-details-card">
				<h2 class="tour-section-title">Детали Отеля</h2>
				<dl class="tour-meta hotel-meta">
					<div>
						<dt>Рейтинг</dt>
						<dd><?php echo $sanitizer->entities($formatRating($hotelRating)); ?></dd>
					</div>
					<div>
						<dt>Кол-во гостей</dt>
						<dd>
							<label
								class="hero-field hero-field-people hotel-guests-picker"
								data-people-min="1"
								data-people-max="<?php echo (int) $hotelMaxGuests; ?>"
								data-people-unit-singular="гость"
								data-people-unit-few="гостя"
								data-people-unit-many="гостей"
							>
								<input type="text" value="<?php echo $sanitizer->entities($hotelGuestsLabel); ?>" readonly />
								<input type="hidden" value="<?php echo (int) $hotelGuestsDefault; ?>" />
								<img src="<?php echo $config->urls->templates; ?>assets/icons/human.svg" alt="" aria-hidden="true" />
								<div class="people-popover" aria-hidden="true">
									<div class="people-row">
										<button class="people-btn" type="button" data-action="minus" aria-label="Уменьшить количество">−</button>
										<span class="people-count" aria-live="polite"><?php echo (int) $hotelGuestsDefault; ?></span>
										<button class="people-btn" type="button" data-action="plus" aria-label="Увеличить количество">+</button>
									</div>
								</div>
							</label>
						</dd>
					</div>
					<div>
						<dt>Регион</dt>
						<dd><?php echo $sanitizer->entities($hotelLocation); ?></dd>
					</div>
				</dl>
				<div class="tour-details-footer">
					<div class="tour-price-wrap">
						<div class="tour-price"><?php echo $sanitizer->entities($hotelPriceLabel); ?></div>
						<div class="tour-price-caption">за номер</div>
					</div>
					<button class="tour-book-btn" type="button">Забронировать</button>
				</div>
			</div>
		</div>
	</section>

	<section class="hotel-media-section">
		<div class="container">
			<div class="hotel-media-card">
				<h2 class="tour-section-title">Медиатека отеля</h2>
				<div class="hotel-media-grid" data-hotel-gallery>
					<?php foreach ($hotelMedia as $index => $hotelMediaImage): ?>
						<figure class="hotel-media-item">
							<button
								class="hotel-media-trigger"
								type="button"
								data-hotel-gallery-item
								data-gallery-index="<?php echo (int) $index; ?>"
								data-gallery-src="<?php echo htmlspecialchars($hotelMediaImage, ENT_QUOTES, 'UTF-8'); ?>"
								data-gallery-alt="<?php echo $sanitizer->entities('Фото отеля ' . ((int) $index + 1)); ?>"
								aria-label="<?php echo $sanitizer->entities('Открыть фото ' . ((int) $index + 1)); ?>"
							>
								<img
									src="<?php echo htmlspecialchars($hotelMediaImage, ENT_QUOTES, 'UTF-8'); ?>"
									alt="<?php echo $sanitizer->entities('Фото отеля ' . ((int) $index + 1)); ?>"
									loading="lazy"
								/>
							</button>
						</figure>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>

	<div class="hotel-gallery-lightbox" data-hotel-gallery-modal hidden>
		<div class="hotel-gallery-lightbox-backdrop" data-gallery-close="backdrop"></div>
		<div class="hotel-gallery-lightbox-dialog" role="dialog" aria-modal="true" aria-label="Медиатека отеля">
			<button class="hotel-gallery-close" type="button" data-gallery-close="button" aria-label="Закрыть">×</button>
			<button class="hotel-gallery-nav hotel-gallery-nav--prev" type="button" data-gallery-nav="prev" aria-label="Предыдущее фото"></button>
			<figure class="hotel-gallery-stage">
				<img src="" alt="" data-gallery-image />
			</figure>
			<button class="hotel-gallery-nav hotel-gallery-nav--next" type="button" data-gallery-nav="next" aria-label="Следующее фото"></button>
			<div class="hotel-gallery-counter" data-gallery-counter></div>
		</div>
	</div>
</div>
