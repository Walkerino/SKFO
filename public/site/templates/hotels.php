<?php namespace ProcessWire;

$normalizeRegionOption = static function(string $value): string {
	$value = trim(str_replace(["\r", "\n"], ' ', $value));
	$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
	return $value;
};

$regionOptions = [];
$regionOptionKeys = [];
$addRegionOption = static function(string $value) use (&$regionOptions, &$regionOptionKeys, $normalizeRegionOption): void {
	$normalizedValue = $normalizeRegionOption($value);
	if ($normalizedValue === '') return;

	$key = function_exists('mb_strtolower') ? mb_strtolower($normalizedValue, 'UTF-8') : strtolower($normalizedValue);
	if (isset($regionOptionKeys[$key])) return;

	$regionOptionKeys[$key] = true;
	$regionOptions[] = $normalizedValue;
};

$regionsPage = $pages->get('/regions/');
if ($regionsPage && $regionsPage->id && $regionsPage->hasField('region_cards') && $regionsPage->region_cards->count()) {
	foreach ($regionsPage->region_cards as $card) {
		$title = $card->hasField('region_card_title') ? (string) $card->region_card_title : '';
		$addRegionOption($title);
	}
}

if (!count($regionOptions)) {
	foreach (
		[
			'Кабардино-Балкарская Республика',
			'Карачаево-Черкесская Республика',
			'Республика Дагестан',
			'Республика Ингушетия',
			'Республика Северная Осетия',
			'Ставропольский край',
			'Чеченская Республика',
		] as $regionTitle
	) {
		$addRegionOption($regionTitle);
	}
}

$toLower = static function(string $value): string {
	return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
};
$normalizeAmenityLine = static function(string $value): string {
	$line = trim(str_replace("\xc2\xa0", ' ', $value));
	$line = preg_replace('/\s+/u', ' ', $line) ?? $line;
	return trim($line);
};
$hotelTypeOptions = [
	'villa' => 'Вилла',
	'sanatorium' => 'Санаторий',
	'hotel' => 'Отель',
	'apartments' => 'Апартаменты',
];
$resolveHotelType = static function(string $title) use ($toLower): string {
	$value = $toLower($title);
	if ($value === '') return 'hotel';
	if (strpos($value, 'вилл') !== false || strpos($value, 'villa') !== false) return 'villa';
	if (strpos($value, 'санатор') !== false) return 'sanatorium';
	if (strpos($value, 'апарт') !== false || strpos($value, 'apartment') !== false) return 'apartments';
	return 'hotel';
};

$formatPrice = static function(int $value): string {
	return number_format($value, 0, '', ' ') . '₽';
};

$normalizeRating = static function(float $value): int {
	if ($value <= 0) return 4;
	return max(1, min(5, (int) round($value)));
};

$amenityMap = [
	'wifi' => ['title' => 'Бесплатный Wi-Fi', 'short' => 'Wi', 'icon' => 'hotel-amenity-wifi.svg'],
	'parking' => ['title' => 'Паркинг', 'short' => 'P', 'icon' => 'hotel-amenity-parking.svg'],
	'elevator' => ['title' => 'Лифт', 'short' => 'Lift', 'icon' => 'hotel-amenity-elevator.svg'],
	'soundproof_rooms' => ['title' => 'Звукоизолированные номера', 'short' => 'Quiet'],
	'air_conditioning' => ['title' => 'Кондиционер', 'short' => 'A/C'],
	'kids' => ['title' => 'Подходит для детей', 'short' => 'Kid', 'icon' => 'hotel-amenity-kid.svg'],
	'tv' => ['title' => 'Телевизор', 'short' => 'TV'],
	'spa' => ['title' => 'Spa-центр', 'short' => 'SPA', 'icon' => 'hotel-amenity-spa.svg'],
	'minibar' => ['title' => 'Мини-бар', 'short' => 'Bar', 'icon' => 'hotel-amenity-minibar.svg'],
	'breakfast' => ['title' => 'Завтрак', 'short' => 'BF', 'icon' => 'hotel-amenity-breakfast.svg'],
	'transfer' => ['title' => 'Трансфер', 'short' => 'Tr', 'icon' => 'hotel-amenity-transfer.svg'],
	'accessible' => ['title' => 'Удобства для людей с ограниченными возможностями', 'short' => 'A11y', 'icon' => 'hotel-amenity-accessible.svg'],
	'gym' => ['title' => 'Спортивный зал', 'short' => 'Gym', 'icon' => 'hotel-amenity-gym.svg'],

	// Legacy/fallback codes that may still exist in older hotel entries.
	'pool' => ['title' => 'Бассейн', 'short' => 'Pool', 'icon' => 'hotel-amenity-pool.svg'],
	'restaurant' => ['title' => 'Ресторан', 'short' => 'Rest'],
	'pets' => ['title' => 'Можно с питомцами', 'short' => 'Pet'],
];
$extractAmenityItems = static function($value) use ($normalizeAmenityLine): array {
	$items = [];
	$push = static function(string $line) use (&$items, $normalizeAmenityLine): void {
		$line = $normalizeAmenityLine($line);
		if ($line === '') return;
		$items[] = $line;
	};
	$walk = null;
	$walk = static function($input) use (&$walk, $push): void {
		if ($input instanceof ComboValue) {
			for ($i = 1; $i <= 20; $i++) {
				$walk($input->get('i' . $i));
			}
			return;
		}
		if (is_array($input)) {
			foreach ($input as $entry) $walk($entry);
			return;
		}
		if (!is_scalar($input)) return;

		$text = trim((string) $input);
		if ($text === '') return;

		$decoded = null;
		if (($text[0] ?? '') === '{' || ($text[0] ?? '') === '[') {
			$decoded = json_decode($text, true);
		}
		if (is_array($decoded)) {
			$walk($decoded);
			return;
		}

		$lines = preg_split('/\R+/u', $text) ?: [];
		foreach ($lines as $line) $push((string) $line);
	};
	$walk($value);
	return array_values(array_unique($items));
};
$amenityCodeByLabel = [];
foreach ($amenityMap as $code => $amenityConfig) {
	$title = trim((string) ($amenityConfig['title'] ?? ''));
	if ($title === '') continue;
	$amenityCodeByLabel[$toLower($title)] = $code;
}
$mapAmenityItemToCode = static function(string $item) use ($amenityMap, $amenityCodeByLabel, $toLower): string {
	$key = $toLower($item);
	if ($key === '') return '';
	if (isset($amenityMap[$key])) return $key;
	if (isset($amenityCodeByLabel[$key])) return (string) $amenityCodeByLabel[$key];

	if (strpos($key, 'wi-fi') !== false || strpos($key, 'wifi') !== false || strpos($key, 'интернет') !== false) return 'wifi';
	if (strpos($key, 'парков') !== false) return 'parking';
	if (strpos($key, 'лифт') !== false) return 'elevator';
	if (strpos($key, 'звукоизол') !== false || strpos($key, 'тишин') !== false) return 'soundproof_rooms';
	if (strpos($key, 'кондицион') !== false) return 'air_conditioning';
	if (strpos($key, 'дет') !== false || strpos($key, 'семейн') !== false) return 'kids';
	if (strpos($key, 'телевиз') !== false || strpos($key, 'tv') !== false || strpos($key, 'кабельн') !== false) return 'tv';
	if (strpos($key, 'spa') !== false || strpos($key, 'спа') !== false) return 'spa';
	if (strpos($key, 'мини-бар') !== false || strpos($key, 'мини бар') !== false) return 'minibar';
	if (strpos($key, 'завтрак') !== false || strpos($key, 'питани') !== false || strpos($key, 'шведск') !== false) return 'breakfast';
	if (strpos($key, 'трансфер') !== false || strpos($key, 'аэропорт') !== false) return 'transfer';
	if (strpos($key, 'доступ') !== false || strpos($key, 'ограниченн') !== false || strpos($key, 'инвалид') !== false) return 'accessible';
	if (strpos($key, 'фитнес') !== false || strpos($key, 'тренаж') !== false || strpos($key, 'спортзал') !== false) return 'gym';
	if (strpos($key, 'бассейн') !== false) return 'pool';
	if (strpos($key, 'ресторан') !== false || strpos($key, 'кафе') !== false || strpos($key, 'бар') !== false) return 'restaurant';
	if (strpos($key, 'питом') !== false || strpos($key, 'животн') !== false) return 'pets';

	return '';
};

$getImageUrlFromValue = static function($imageValue): string {
	if ($imageValue instanceof Pageimage) return $imageValue->url;
	if ($imageValue instanceof Pageimages && $imageValue->count()) return $imageValue->first()->url;
	return '';
};
$getHotelPrimaryImage = static function(Page $hotelPage) use ($getImageUrlFromValue): string {
	foreach (['hotel_image', 'images', 'hotel_gallery', 'hotel_images'] as $fieldName) {
		if (!$hotelPage->hasField($fieldName)) continue;
		$imageUrl = $getImageUrlFromValue($hotelPage->getUnformatted($fieldName));
		if ($imageUrl !== '') return $imageUrl;
	}
	return '';
};
$sanitizeHeadingText = static function(string $value): string {
	$value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	$value = str_replace(['&quot;', '"', '«', '»'], '', $value);
	$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
	return trim($value);
};

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
$defaultHotelGallery = [];
$addDefaultHotelImage = static function(string $filePath, string $urlPath) use (&$defaultHotelGallery): void {
	if (!is_file($filePath)) return;
	$defaultHotelGallery[] = $urlPath;
};
foreach ($defaultHotelGalleryAssets as $assetPath) {
	$assetPath = ltrim($assetPath, '/');
	$addDefaultHotelImage($config->paths->templates . $assetPath, $config->urls->templates . $assetPath);
}
if (!count($defaultHotelGallery)) {
	foreach ($fallbackHotelGalleryAssets as $assetPath) {
		$assetPath = ltrim($assetPath, '/');
		$addDefaultHotelImage($config->paths->root . $assetPath, $config->urls->root . $assetPath);
	}
}
$pickDefaultHotelImage = static function(int $index) use ($defaultHotelGallery, $defaultHotelImage): string {
	if (!count($defaultHotelGallery)) return $defaultHotelImage;
	$safeIndex = $index >= 0 ? $index : 0;
	return $defaultHotelGallery[$safeIndex % count($defaultHotelGallery)];
};
$getHotelGalleryImages = static function(Page $hotelPage, int $fallbackIndex = 0) use ($defaultHotelGallery, $pickDefaultHotelImage, $defaultHotelImage): array {
	$gallery = [];
	$galleryMap = [];
	$pushGalleryImage = static function(string $url) use (&$gallery, &$galleryMap): void {
		$url = trim($url);
		if ($url === '') return;
		if (isset($galleryMap[$url])) return;
		$galleryMap[$url] = true;
		$gallery[] = $url;
	};

	foreach (['hotel_gallery', 'hotel_images', 'images', 'hotel_image'] as $imageFieldName) {
		if (!$hotelPage->hasField($imageFieldName)) continue;
		$fieldValue = $hotelPage->getUnformatted($imageFieldName);
		if ($fieldValue instanceof Pageimage) {
			$pushGalleryImage($fieldValue->url);
			continue;
		}
		if ($fieldValue instanceof Pageimages && $fieldValue->count()) {
			foreach ($fieldValue as $imageItem) {
				if (!$imageItem instanceof Pageimage) continue;
				$pushGalleryImage((string) $imageItem->url);
			}
		}
	}

	if (!count($gallery)) {
		$fallbackImage = $pickDefaultHotelImage($fallbackIndex);
		if ($fallbackImage === '') $fallbackImage = $defaultHotelImage;
		$pushGalleryImage($fallbackImage);
	}
	if (!count($gallery) && count($defaultHotelGallery)) {
		foreach ($defaultHotelGallery as $defaultGalleryImage) $pushGalleryImage((string) $defaultGalleryImage);
	}
	if (count($gallery) > 12) $gallery = array_slice($gallery, 0, 12);
	return $gallery;
};
$defaultHotelDetailsUrl = '';
if (isset($pages) && $pages instanceof Pages) {
	$defaultHotelDetailsPage = $pages->get('template=hotel, include=all');
	if ($defaultHotelDetailsPage instanceof Page && $defaultHotelDetailsPage->id) {
		$defaultHotelDetailsUrl = (string) $defaultHotelDetailsPage->url;
	}
}
$hotelAmenitiesRawByPageId = [];
if (isset($database) && $database instanceof WireDatabasePDO) {
	try {
		$stmt = $database->query("SELECT pages_id, data FROM field_hotel_amenities WHERE data IS NOT NULL AND data <> ''");
		if ($stmt) {
			foreach (($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) as $row) {
				$pageId = (int) ($row['pages_id'] ?? 0);
				$rawData = trim((string) ($row['data'] ?? ''));
				if ($pageId < 1 || $rawData === '') continue;
				$hotelAmenitiesRawByPageId[$pageId] = $rawData;
			}
		}
	} catch (\Throwable $e) {
		// Ignore DB fallback errors; regular field parsing will still work.
	}
}
$hotelsCatalog = [];

if (isset($pages) && $pages instanceof Pages) {
	$hotelPages = $pages->find('template=hotel, include=all, sort=title, limit=500');
	foreach ($hotelPages as $hotelPage) {
		$title = trim((string) $hotelPage->title);
		if ($title === '') continue;

		$priceRaw = $hotelPage->hasField('hotel_price') ? (string) $hotelPage->getUnformatted('hotel_price') : '';
		$price = (int) preg_replace('/[^\d]+/', '', $priceRaw);
		$ratingRaw = $hotelPage->hasField('hotel_rating') ? trim((string) $hotelPage->getUnformatted('hotel_rating')) : '';
		$ratingRaw = str_replace(',', '.', $ratingRaw);
		$rating = is_numeric($ratingRaw) ? (float) $ratingRaw : 0.0;
		$maxGuests = $hotelPage->hasField('hotel_max_guests') ? (int) $hotelPage->getUnformatted('hotel_max_guests') : 1;
		if ($maxGuests < 1) $maxGuests = 1;
		$region = $hotelPage->hasField('hotel_region') ? trim((string) $hotelPage->hotel_region) : '';
		if ($region !== '') $addRegionOption($region);

		$amenities = [];
		if ($hotelPage->hasField('hotel_amenities')) {
			$amenityItems = $extractAmenityItems($hotelPage->getUnformatted('hotel_amenities'));
			$pageId = (int) $hotelPage->id;
			if (!count($amenityItems) && isset($hotelAmenitiesRawByPageId[$pageId])) {
				$amenityItems = $extractAmenityItems($hotelAmenitiesRawByPageId[$pageId]);
			}
			foreach ($amenityItems as $amenityItem) {
				$code = $mapAmenityItemToCode((string) $amenityItem);
				if ($code === '' || !isset($amenityMap[$code])) continue;
				if (!in_array($code, $amenities, true)) $amenities[] = $code;
			}
		}

		$gallery = $getHotelGalleryImages($hotelPage, count($hotelsCatalog));
		$image = trim((string) ($gallery[0] ?? ''));
		if ($image === '') $image = $pickDefaultHotelImage(count($hotelsCatalog));

		$hotelsCatalog[] = [
			'title' => $title,
			'city' => $hotelPage->hasField('hotel_city') ? trim((string) $hotelPage->hotel_city) : '',
			'region' => $region,
			'rating' => $normalizeRating($rating),
			'price' => $price > 0 ? $price : 10000,
			'max_guests' => $maxGuests,
			'amenities' => $amenities,
			'type' => $resolveHotelType($title),
			'image' => $image,
			'gallery' => $gallery,
			'url' => (string) $hotelPage->url,
		];
	}
}

if (!count($hotelsCatalog)) {
	$hotelsCatalog = [
	[
		'title' => 'Санаторий "Виктория"',
		'city' => 'Ессентуки',
		'region' => 'Ставропольский край',
		'rating' => 4.5,
		'price' => 23251,
		'max_guests' => 4,
		'amenities' => ['wifi', 'kids', 'parking', 'spa', 'gym', 'pool', 'breakfast'],
		'image' => $defaultHotelImage,
	],
	[
		'title' => 'Отель "Курортный дом"',
		'city' => 'Кисловодск',
		'region' => 'Ставропольский край',
		'rating' => 4.8,
		'price' => 19800,
		'max_guests' => 3,
		'amenities' => ['wifi', 'parking', 'spa', 'pool', 'breakfast'],
		'image' => $defaultHotelImage,
	],
	[
		'title' => 'Горный парк-отель "Архыз"',
		'city' => 'Архыз',
		'region' => 'Карачаево-Черкесская Республика',
		'rating' => 4.6,
		'price' => 21700,
		'max_guests' => 5,
		'amenities' => ['wifi', 'kids', 'parking', 'pool', 'restaurant', 'transfer'],
		'image' => $defaultHotelImage,
	],
	[
		'title' => 'Отель "Домбай Вью"',
		'city' => 'Домбай',
		'region' => 'Карачаево-Черкесская Республика',
		'rating' => 4.4,
		'price' => 18400,
		'max_guests' => 2,
		'amenities' => ['wifi', 'parking', 'restaurant', 'transfer'],
		'image' => $defaultHotelImage,
	],
	[
		'title' => 'Отель "Каспий Плаза"',
		'city' => 'Махачкала',
		'region' => 'Республика Дагестан',
		'rating' => 4.7,
		'price' => 24500,
		'max_guests' => 4,
		'amenities' => ['wifi', 'kids', 'parking', 'gym', 'pool', 'breakfast'],
		'image' => $defaultHotelImage,
	],
	[
		'title' => 'Бутик-отель "Горная Тишина"',
		'city' => 'Нальчик',
		'region' => 'Кабардино-Балкарская Республика',
		'rating' => 4.3,
		'price' => 16300,
		'max_guests' => 2,
		'amenities' => ['wifi', 'spa', 'breakfast', 'pets'],
		'image' => $defaultHotelImage,
	],
	[
		'title' => 'Гранд-отель "Терек"',
		'city' => 'Владикавказ',
		'region' => 'Республика Северная Осетия',
		'rating' => 4.6,
		'price' => 21100,
		'max_guests' => 4,
		'amenities' => ['wifi', 'parking', 'gym', 'restaurant', 'breakfast'],
		'image' => $defaultHotelImage,
	],
	[
		'title' => 'Отель "Грозный Сити"',
		'city' => 'Грозный',
		'region' => 'Чеченская Республика',
		'rating' => 4.9,
		'price' => 27100,
		'max_guests' => 5,
		'amenities' => ['wifi', 'kids', 'parking', 'spa', 'gym', 'pool', 'restaurant'],
		'image' => $defaultHotelImage,
	],
	[
		'title' => 'Спа-отель "Минеральный"',
		'city' => 'Пятигорск',
		'region' => 'Ставропольский край',
		'rating' => 4.2,
		'price' => 15800,
		'max_guests' => 3,
		'amenities' => ['wifi', 'spa', 'pool', 'breakfast'],
		'image' => $defaultHotelImage,
	],
	[
		'title' => 'Парк-отель "Нарзан"',
		'city' => 'Железноводск',
		'region' => 'Ставропольский край',
		'rating' => 4.1,
		'price' => 14900,
		'max_guests' => 2,
		'amenities' => ['wifi', 'parking', 'pets', 'breakfast'],
		'image' => $defaultHotelImage,
	],
	[
		'title' => 'Резиденция "Эльбрус"',
		'city' => 'Терскол',
		'region' => 'Кабардино-Балкарская Республика',
		'rating' => 4.5,
		'price' => 22300,
		'max_guests' => 4,
		'amenities' => ['wifi', 'parking', 'restaurant', 'transfer', 'spa'],
		'image' => $defaultHotelImage,
	],
	[
		'title' => 'Отель "Сунжа Ривер"',
		'city' => 'Магас',
		'region' => 'Республика Ингушетия',
		'rating' => 4.0,
		'price' => 14100,
		'max_guests' => 2,
		'amenities' => ['wifi', 'parking', 'breakfast'],
		'image' => $defaultHotelImage,
	],
];

	foreach ($hotelsCatalog as $index => $hotel) {
		$hotelsCatalog[$index]['image'] = $pickDefaultHotelImage($index);
		$fallbackGallery = [];
		$fallbackGalleryMap = [];
		$pushFallbackGallery = static function(string $url) use (&$fallbackGallery, &$fallbackGalleryMap): void {
			$url = trim($url);
			if ($url === '') return;
			if (isset($fallbackGalleryMap[$url])) return;
			$fallbackGalleryMap[$url] = true;
			$fallbackGallery[] = $url;
		};
		$pushFallbackGallery((string) $hotelsCatalog[$index]['image']);
		foreach ($defaultHotelGallery as $defaultGalleryImage) $pushFallbackGallery((string) $defaultGalleryImage);
		$hotelsCatalog[$index]['gallery'] = array_slice($fallbackGallery, 0, 12);
		if (trim((string) ($hotelsCatalog[$index]['url'] ?? '')) === '') {
			$hotelsCatalog[$index]['url'] = $defaultHotelDetailsUrl !== '' ? $defaultHotelDetailsUrl : $page->url;
		}
	}
}

foreach ($hotelsCatalog as $index => $hotel) {
	$hotelsCatalog[$index]['title'] = trim((string) ($hotel['title'] ?? ''));
	$hotelsCatalog[$index]['region'] = trim((string) ($hotel['region'] ?? ''));
	$hotelsCatalog[$index]['type'] = trim((string) ($hotel['type'] ?? ''));
	if ($hotelsCatalog[$index]['type'] === '' || !isset($hotelTypeOptions[$hotelsCatalog[$index]['type']])) {
		$hotelsCatalog[$index]['type'] = $resolveHotelType($hotelsCatalog[$index]['title']);
	}

	$ratingRaw = (float) ($hotel['rating'] ?? 0);
	$hotelsCatalog[$index]['rating'] = $normalizeRating($ratingRaw);
	if ($hotelsCatalog[$index]['region'] !== '') $addRegionOption($hotelsCatalog[$index]['region']);
	if (trim((string) ($hotelsCatalog[$index]['url'] ?? '')) === '') {
		$hotelsCatalog[$index]['url'] = $defaultHotelDetailsUrl !== '' ? $defaultHotelDetailsUrl : $page->url;
	}

	$galleryImages = [];
	$galleryImagesMap = [];
	$pushGalleryImage = static function(string $url) use (&$galleryImages, &$galleryImagesMap): void {
		$url = trim($url);
		if ($url === '') return;
		if (isset($galleryImagesMap[$url])) return;
		$galleryImagesMap[$url] = true;
		$galleryImages[] = $url;
	};
	foreach ((array) ($hotel['gallery'] ?? []) as $galleryImageUrl) {
		$pushGalleryImage((string) $galleryImageUrl);
	}
	$pushGalleryImage((string) ($hotelsCatalog[$index]['image'] ?? ''));
	if (!count($galleryImages)) $pushGalleryImage($pickDefaultHotelImage($index));
	$hotelsCatalog[$index]['gallery'] = array_slice($galleryImages, 0, 12);
	$hotelsCatalog[$index]['image'] = (string) ($hotelsCatalog[$index]['gallery'][0] ?? $pickDefaultHotelImage($index));
}

if (count($regionOptions)) {
	sort($regionOptions, SORT_NATURAL | SORT_FLAG_CASE);
}

$featuredHotelOrder = [];
if ($page->hasField('hotels_featured_refs') && $page->hotels_featured_refs->count()) {
	$order = 0;
	foreach ($page->hotels_featured_refs as $featuredHotelPage) {
		if (!$featuredHotelPage instanceof Page) continue;
		$featuredTitle = trim((string) $featuredHotelPage->title);
		if ($featuredTitle === '') continue;
		if (isset($featuredHotelOrder[$featuredTitle])) continue;
		$featuredHotelOrder[$featuredTitle] = $order++;
	}
}

if (count($featuredHotelOrder)) {
	usort($hotelsCatalog, static function(array $a, array $b) use ($featuredHotelOrder): int {
		$aTitle = trim((string) ($a['title'] ?? ''));
		$bTitle = trim((string) ($b['title'] ?? ''));
		$aPinned = array_key_exists($aTitle, $featuredHotelOrder);
		$bPinned = array_key_exists($bTitle, $featuredHotelOrder);

		if ($aPinned && $bPinned) {
			return $featuredHotelOrder[$aTitle] <=> $featuredHotelOrder[$bTitle];
		}
		if ($aPinned) return -1;
		if ($bPinned) return 1;

		return strcmp($aTitle, $bTitle);
	});
}

$filterRegion = trim((string) $input->get('region'));
$filterHotelType = trim((string) $input->get('hotel_type'));
$filterStars = (int) $input->get('stars');
if ($filterStars < 2 || $filterStars > 5) $filterStars = 0;
if ($filterHotelType !== '' && !isset($hotelTypeOptions[$filterHotelType])) $filterHotelType = '';
if ($filterRegion !== '' && !in_array($filterRegion, $regionOptions, true)) $filterRegion = '';

$hasActiveFilters = $filterRegion !== '' || $filterHotelType !== '' || $filterStars > 0;
$isFiltersApplied = trim((string) $input->get('apply_filters')) === '1' || $hasActiveFilters;

$regionFieldClass = $filterRegion !== '' ? ' is-filled' : '';
$typeFieldClass = $filterHotelType !== '' ? ' is-filled' : '';
$starsFieldClass = $filterStars > 0 ? ' is-filled' : '';

$filteredHotels = $hotelsCatalog;
if ($isFiltersApplied) {
	$filteredHotels = array_values(array_filter($hotelsCatalog, static function(array $hotel) use ($filterRegion, $filterHotelType, $filterStars): bool {
		$hotelRegion = trim((string) ($hotel['region'] ?? ''));
		if ($filterRegion !== '' && $hotelRegion !== $filterRegion) return false;

		$hotelType = trim((string) ($hotel['type'] ?? ''));
		if ($filterHotelType !== '' && $hotelType !== $filterHotelType) return false;

		$hotelStars = (int) ($hotel['rating'] ?? 0);
		$hotelStars = max(1, min(5, $hotelStars));
		if ($filterStars > 0 && $hotelStars !== $filterStars) return false;

		return true;
	}));
}

$perPage = 8;
$currentPage = max(1, (int) $input->get('page'));
$totalHotels = count($filteredHotels);
$totalPages = $totalHotels > 0 ? (int) ceil($totalHotels / $perPage) : 1;
if ($currentPage > $totalPages) $currentPage = $totalPages;
$offset = ($currentPage - 1) * $perPage;
$visibleHotels = array_slice($filteredHotels, $offset, $perPage);

$buildPageUrl = static function(int $pageNumber) use ($page, $filterRegion, $filterHotelType, $filterStars, $hasActiveFilters): string {
	$params = [];
	if ($hasActiveFilters) $params['apply_filters'] = '1';
	if ($filterRegion !== '') $params['region'] = $filterRegion;
	if ($filterHotelType !== '') $params['hotel_type'] = $filterHotelType;
	if ($filterStars > 0) $params['stars'] = (string) $filterStars;
	if ($pageNumber > 1) $params['page'] = (string) $pageNumber;

	$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
	return $page->url . ($query !== '' ? '?' . $query : '');
};
$forumExternalUrl = 'https://club.skfo.ru';
?>

<div id="content" class="hotels-page">
	<section class="hero hotels-hero">
		<div class="container hero-inner">
			<h1 class="hero-title">
				ОТЕЛИ<br />
				НА ЛЮБОЙ ВКУС
			</h1>
			<div class="hero-tabs" aria-label="Разделы">
				<div class="hero-tabs-group" role="tablist">
					<span class="tab-indicator" aria-hidden="true"></span>
					<span class="tab-hover" aria-hidden="true"></span>
					<a class="hero-tab" href="/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/tour.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Туры</span>
					</a>
					<a class="hero-tab is-active" href="/hotels/" role="tab" aria-selected="true">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/hotel.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Отели</span>
					</a>
					<a class="hero-tab" href="/reviews/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/reviews.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Отзывы</span>
					</a>
					<a class="hero-tab" href="/guides/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/human.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Гиды</span>
					</a>
					<a class="hero-tab" href="/regions/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Регионы</span>
					</a>
					<a class="hero-tab" href="/places/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/location_on_nav.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Места</span>
					</a>
					<a class="hero-tab" href="/articles/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/journal.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Статьи</span>
					</a>
				</div>
				<a class="hero-tab hero-tab-forum" href="<?php echo $forumExternalUrl; ?>" target="_blank" rel="noopener noreferrer" aria-label="Форум">
					<img src="<?php echo $config->urls->templates; ?>assets/icons/forum.svg" alt="" aria-hidden="true" />
					<span>Форум</span>
					<img class="hero-tab-external" src="<?php echo $config->urls->templates; ?>assets/icons/external_site.svg" alt="" aria-hidden="true" />
				</a>
			</div>
			<form class="hero-search hotels-search hero-search--compact" action="<?php echo $sanitizer->entities($page->url); ?>" method="get">
				<input type="hidden" name="apply_filters" value="1" />
				<div class="hero-search-fields hotels-search-fields">
					<label class="hero-field<?php echo $regionFieldClass; ?>">
						<span class="sr-only">Регион</span>
						<select name="region">
							<option value="">Все регионы</option>
							<?php foreach ($regionOptions as $regionOption): ?>
								<option value="<?php echo $sanitizer->entities($regionOption); ?>"<?php echo $regionOption === $filterRegion ? ' selected' : ''; ?>>
									<?php echo $sanitizer->entities($regionOption); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
					</label>
					<label class="hero-field<?php echo $typeFieldClass; ?>">
						<span class="sr-only">Тип отеля</span>
						<select name="hotel_type">
							<option value="">Все типы</option>
							<?php foreach ($hotelTypeOptions as $typeCode => $typeLabel): ?>
								<option value="<?php echo $sanitizer->entities($typeCode); ?>"<?php echo $typeCode === $filterHotelType ? ' selected' : ''; ?>>
									<?php echo $sanitizer->entities($typeLabel); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<img src="<?php echo $config->urls->templates; ?>assets/icons/hotel.svg" alt="" aria-hidden="true" />
					</label>
					<label class="hero-field<?php echo $starsFieldClass; ?>">
						<span class="sr-only">Количество звезд</span>
						<select name="stars">
							<option value="">Любая категория</option>
							<?php for ($stars = 2; $stars <= 5; $stars++): ?>
								<option value="<?php echo $stars; ?>"<?php echo $filterStars === $stars ? ' selected' : ''; ?>>
									<?php echo $stars; ?> звезды
								</option>
							<?php endfor; ?>
						</select>
						<img src="<?php echo $config->urls->templates; ?>assets/icons/reviews.svg" alt="" aria-hidden="true" />
					</label>
				</div>
				<button class="search-btn" type="submit">Применить</button>
			</form>
		</div>
	</section>

	<section class="section section--hotels-results">
		<div class="container">
			<?php if (count($visibleHotels)): ?>
				<div class="hotels-grid">
					<?php foreach ($visibleHotels as $hotel): ?>
						<?php
						$imageUrl = trim((string) ($hotel['image'] ?? ''));
						$hotelUrl = trim((string) ($hotel['url'] ?? ''));
						$city = trim((string) ($hotel['city'] ?? ''));
						$region = trim((string) ($hotel['region'] ?? ''));
						$locationLabel = trim($city . ', ' . $region, ', ');
						$titleLabel = $sanitizeHeadingText((string) ($hotel['title'] ?? ''));
						if ($titleLabel === '') $titleLabel = trim((string) ($hotel['title'] ?? ''));
						$ratingValue = (int) ($hotel['rating'] ?? 0);
						$ratingValue = max(1, min(5, $ratingValue));
						$ratingToneClass = $ratingValue <= 2
							? ' hotel-card-rating--danger'
							: ($ratingValue === 3 ? ' hotel-card-rating--warning' : ' hotel-card-rating--success');
						if ($hotelUrl === '') $hotelUrl = $page->url;
						$galleryImages = array_values(array_filter((array) ($hotel['gallery'] ?? []), static function($url): bool {
							return trim((string) $url) !== '';
						}));
						if (!count($galleryImages) && $imageUrl !== '') $galleryImages[] = $imageUrl;
						$cardGalleryId = 'hotel-card-gallery-' . md5($hotelUrl . '|' . $titleLabel . '|' . ($galleryImages[0] ?? $imageUrl));
						$openGalleryAriaLabel = 'Открыть фото отеля ' . ($titleLabel !== '' ? '«' . $titleLabel . '»' : '');
						?>
						<article class="hotel-card">
							<div class="hotel-card-media"<?php echo $imageUrl !== '' ? " style=\"background-image: url('" . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . "');\"" : ''; ?>>
								<button
									class="hotel-card-media-trigger"
									type="button"
									data-hotel-card-open-gallery
									data-hotel-card-gallery-group="<?php echo $sanitizer->entities($cardGalleryId); ?>"
									aria-label="<?php echo $sanitizer->entities(trim($openGalleryAriaLabel)); ?>"
								></button>
								<?php if (count($galleryImages) > 1): ?>
									<span class="hotel-card-media-count"><?php echo count($galleryImages); ?> фото</span>
								<?php endif; ?>
								<span class="hotel-card-rating<?php echo $ratingToneClass; ?>">
									<span class="hotel-card-rating-star" aria-hidden="true">★</span>
									<span class="hotel-card-rating-value"><?php echo (int) $ratingValue; ?></span>
								</span>
							</div>
							<?php foreach ($galleryImages as $galleryIndex => $galleryImageUrl): ?>
								<?php
								$galleryAlt = trim('Фото отеля ' . $titleLabel . ' #' . ((int) $galleryIndex + 1));
								?>
								<button
									type="button"
									class="hotel-hero-gallery-hidden"
									data-hotel-card-gallery-item
									data-hotel-card-gallery-group="<?php echo $sanitizer->entities($cardGalleryId); ?>"
									data-gallery-type="image"
									data-gallery-src="<?php echo htmlspecialchars((string) $galleryImageUrl, ENT_QUOTES, 'UTF-8'); ?>"
									data-gallery-alt="<?php echo $sanitizer->entities($galleryAlt); ?>"
								></button>
							<?php endforeach; ?>
							<h2 class="hotel-card-title"><?php echo $sanitizer->entities($titleLabel); ?></h2>
							<p class="hotel-card-location"><?php echo $sanitizer->entities($locationLabel); ?></p>
							<ul class="hotel-card-amenities" aria-label="Опции отеля">
									<?php foreach ((array) ($hotel['amenities'] ?? []) as $amenityCode): ?>
										<?php if (in_array($amenityCode, ['soundproof_rooms', 'restaurant'], true)) continue; ?>
										<?php if (!isset($amenityMap[$amenityCode])) continue; ?>
										<?php
										$amenity = $amenityMap[$amenityCode];
										$amenityTitle = (string) ($amenity['title'] ?? '');
										$amenityShort = (string) ($amenity['short'] ?? '');
										$amenityIconFile = trim((string) ($amenity['icon'] ?? ''));
										$amenityIconUrl = $amenityIconFile !== '' ? $config->urls->templates . 'assets/icons/' . ltrim($amenityIconFile, '/') : '';
										?>
										<li class="hotel-card-amenity" title="<?php echo $sanitizer->entities($amenityTitle); ?>">
											<span class="hotel-card-amenity-icon">
												<?php if ($amenityIconUrl !== ''): ?>
													<img src="<?php echo $sanitizer->entities($amenityIconUrl); ?>" alt="" aria-hidden="true" />
												<?php else: ?>
													<?php echo $sanitizer->entities($amenityShort); ?>
												<?php endif; ?>
											</span>
										</li>
									<?php endforeach; ?>
							</ul>
							<div class="hotel-card-footer">
								<div class="hotel-card-price"><?php echo $sanitizer->entities($formatPrice((int) ($hotel['price'] ?? 0))); ?></div>
								<a class="hotel-card-btn" href="<?php echo $sanitizer->entities($hotelUrl); ?>">Выбрать номер</a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
				<div class="hotel-gallery-lightbox" data-hotel-cards-gallery-modal hidden>
					<div class="hotel-gallery-lightbox-backdrop" data-gallery-close="backdrop"></div>
					<div class="hotel-gallery-lightbox-dialog" role="dialog" aria-modal="true" aria-label="Медиатека отеля">
						<button class="hotel-gallery-close" type="button" data-gallery-close="button" aria-label="Закрыть галерею">×</button>
						<button class="hotel-gallery-nav hotel-gallery-nav--prev" type="button" data-gallery-nav="prev" aria-label="Предыдущее фото"></button>
						<button class="hotel-gallery-nav hotel-gallery-nav--next" type="button" data-gallery-nav="next" aria-label="Следующее фото"></button>
						<figure class="hotel-gallery-stage">
							<img data-gallery-image alt="" />
						</figure>
						<div class="hotel-gallery-counter" data-gallery-counter></div>
					</div>
				</div>

				<?php if ($totalPages > 1): ?>
					<nav class="hotels-pagination" aria-label="Страницы отелей">
						<?php
						$paginationWindowStart = max(1, min($currentPage, $totalPages - 2));
						$paginationWindowEnd = min($totalPages, $paginationWindowStart + 2);
						?>
						<?php for ($i = $paginationWindowStart; $i <= $paginationWindowEnd; $i++): ?>
							<a class="hotels-pagination-link<?php echo $i === $currentPage ? ' is-active' : ''; ?>" href="<?php echo $sanitizer->entities($buildPageUrl($i)); ?>"<?php echo $i === $currentPage ? ' aria-current="page"' : ''; ?>>
								<?php echo $i; ?>
							</a>
						<?php endfor; ?>
						<?php if ($paginationWindowEnd < $totalPages - 1): ?>
							<span class="hotels-pagination-ellipsis" aria-hidden="true">...</span>
						<?php endif; ?>
						<?php if ($paginationWindowEnd < $totalPages): ?>
							<a class="hotels-pagination-link" href="<?php echo $sanitizer->entities($buildPageUrl($totalPages)); ?>">
								<?php echo $totalPages; ?>
							</a>
						<?php endif; ?>
						<?php if ($currentPage < $totalPages): ?>
							<a class="hotels-pagination-link hotels-pagination-next" href="<?php echo $sanitizer->entities($buildPageUrl($currentPage + 1)); ?>" aria-label="Следующая страница">
								Далее
							</a>
						<?php endif; ?>
					</nav>
				<?php endif; ?>
			<?php else: ?>
				<div class="hotels-empty">
					По выбранным фильтрам отели не найдены. Измените фильтры и попробуйте снова.
				</div>
			<?php endif; ?>
		</div>
	</section>
</div>
