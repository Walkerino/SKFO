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
$extractComboTextParts = static function($value) use ($normalizeLine): array {
	$parts = [];

	if ($value instanceof ComboValue) {
		for ($i = 1; $i <= 20; $i++) {
			$key = 'i' . $i;
			$subValue = $value->get($key);

			if (is_array($subValue)) {
				foreach ($subValue as $item) {
					$line = $normalizeLine((string) $item);
					if ($line !== '') $parts[] = $line;
				}
				continue;
			}

			$line = $normalizeLine((string) $subValue);
			if ($line !== '') $parts[] = $line;
		}

		return $parts;
	}

	if (is_array($value)) {
		foreach ($value as $item) {
			$line = $normalizeLine((string) $item);
			if ($line !== '') $parts[] = $line;
		}
		return $parts;
	}

	if (is_scalar($value)) {
		$text = (string) $value;
		$lines = preg_split('/\R+/u', $text) ?: [];
		foreach ($lines as $line) {
			$normalized = $normalizeLine((string) $line);
			if ($normalized !== '') $parts[] = $normalized;
		}
	}

	return $parts;
};
$extractAmenityItems = static function($value) use ($normalizeLine): array {
	$items = [];
	$push = static function(string $line) use (&$items, $normalizeLine): void {
		$line = $normalizeLine($line);
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
$extractComboGroups = static function($value) use ($normalizeLine): array {
	$groups = [];
	$push = static function(string $groupKey, string $line) use (&$groups, $normalizeLine): void {
		$groupKey = trim($groupKey);
		if ($groupKey === '') $groupKey = 'other';
		$line = $normalizeLine($line);
		if ($line === '') return;
		if (!isset($groups[$groupKey])) $groups[$groupKey] = [];
		$groups[$groupKey][] = $line;
	};
	$walk = null;
	$walk = static function(string $groupKey, $input) use (&$walk, $push): void {
		if ($input instanceof ComboValue) {
			for ($i = 1; $i <= 20; $i++) {
				$walk('i' . $i, $input->get('i' . $i));
			}
			return;
		}
		if (is_array($input)) {
			$isAssoc = array_keys($input) !== range(0, count($input) - 1);
			if ($isAssoc) {
				foreach ($input as $key => $entry) {
					$resolvedKey = is_string($key) && preg_match('/^i\d+$/', $key) ? $key : $groupKey;
					$walk($resolvedKey, $entry);
				}
				return;
			}
			foreach ($input as $entry) $walk($groupKey, $entry);
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
			$walk($groupKey, $decoded);
			return;
		}

		$lines = preg_split('/\R+/u', $text) ?: [];
		foreach ($lines as $line) $push($groupKey, (string) $line);
	};
	$walk('other', $value);

	foreach ($groups as $groupKey => $items) {
		$groups[$groupKey] = array_values(array_unique($items));
	}
	return $groups;
};
$getComboItem = static function($value, string $itemKey) {
	if ($itemKey === '') return null;
	if ($value instanceof ComboValue) return $value->get($itemKey);
	if (is_array($value)) return $value[$itemKey] ?? null;
	if (is_scalar($value)) {
		$text = trim((string) $value);
		if ($text === '') return null;
		$decoded = json_decode($text, true);
		if (is_array($decoded)) return $decoded[$itemKey] ?? null;
	}
	return null;
};
$parseOptionMap = static function(string $rawOptions) use ($normalizeLine): array {
	$map = [];
	$lines = preg_split('/\R+/u', $rawOptions) ?: [];
	foreach ($lines as $line) {
		$line = trim((string) $line);
		if ($line === '' || strpos($line, '=') === false) continue;
		[$key, $label] = explode('=', $line, 2);
		$key = trim($key);
		$label = $normalizeLine((string) $label);
		if ($key === '' || $label === '') continue;
		$map[$key] = $label;
	}
	return $map;
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
$sanitizeHeadingText = static function(string $value): string {
	$value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	$value = str_replace(['&quot;', '"', '«', '»'], '', $value);
	$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
	return trim($value);
};
$getComboFieldConfig = static function(string $fieldName) use ($fields): array {
	if (!isset($fields) || !($fields instanceof Fields)) return [];
	$field = $fields->get($fieldName);
	if (!$field || !$field->id) return [];
	$data = $field->get('data');
	if (is_array($data)) return $data;
	if (is_string($data)) {
		$decoded = json_decode($data, true);
		return is_array($decoded) ? $decoded : [];
	}
	if (is_object($data) && method_exists($data, 'getArray')) {
		$decoded = $data->getArray();
		return is_array($decoded) ? $decoded : [];
	}
	return [];
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
$amenityCodeByLabel = [];
foreach ($amenityMap as $amenityCode => $amenityConfig) {
	$title = trim((string) ($amenityConfig['title'] ?? ''));
	if ($title === '') continue;
	$amenityCodeByLabel[$toLower($title)] = $amenityCode;
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
$amenityGroupLabels = [
	'i2' => 'Основные особенности',
	'i3' => 'Основное',
	'i4' => 'Номера',
	'i5' => 'Доступность',
	'i6' => 'Питание',
	'i7' => 'Интернет',
	'i8' => 'Трансфер',
	'i9' => 'Языки общения',
	'i10' => 'Развлечения / Досуг',
	'i11' => 'Парковка',
	'i12' => 'Туристические услуги',
	'i13' => 'Бизнес',
	'i14' => 'Спорт',
	'i15' => 'Красота и здоровье',
	'i16' => 'Дети',
	'i17' => 'Меры здравоохранения и безопасности',
	'other' => 'Прочее',
];
$amenityGroupOrder = ['i2', 'i3', 'i4', 'i5', 'i6', 'i7', 'i8', 'i9', 'i10', 'i11', 'i12', 'i13', 'i14', 'i15', 'i16', 'i17', 'other'];
$amenityGroupByCode = [
	'wifi' => 'i7',
	'parking' => 'i11',
	'elevator' => 'i3',
	'soundproof_rooms' => 'i4',
	'air_conditioning' => 'i3',
	'kids' => 'i16',
	'tv' => 'i4',
	'spa' => 'i15',
	'minibar' => 'i4',
	'breakfast' => 'i6',
	'transfer' => 'i8',
	'accessible' => 'i5',
	'gym' => 'i14',
	'pool' => 'i14',
	'restaurant' => 'i6',
	'pets' => 'other',
];
$amenitiesFieldConfig = $getComboFieldConfig('hotel_amenities');
foreach ($amenitiesFieldConfig as $configKey => $configValue) {
	if (!preg_match('/^(i\d+)_label$/', (string) $configKey, $matches)) continue;
	$groupKey = (string) ($matches[1] ?? '');
	$groupLabel = $normalizeLine((string) $configValue);
	if ($groupKey === '' || $groupLabel === '') continue;
	$amenityGroupLabels[$groupKey] = $groupLabel;
	if (!in_array($groupKey, $amenityGroupOrder, true)) $amenityGroupOrder[] = $groupKey;
}
$hotelConditionsFieldConfig = $getComboFieldConfig('hotel_conditions');
$conditionParkingOptionMap = $parseOptionMap((string) ($hotelConditionsFieldConfig['i6_options'] ?? ''));
if (!count($conditionParkingOptionMap)) {
	$conditionParkingOptionMap = [
		'free' => 'Бесплатно',
		'paid' => 'Платно',
		'valet' => 'Парковка с услугами парковщика',
	];
}
$conditionPetsOptionMap = $parseOptionMap((string) ($hotelConditionsFieldConfig['i7_options'] ?? ''));
if (!count($conditionPetsOptionMap)) {
	$conditionPetsOptionMap = [
		'allowed' => 'Животные разрешены',
		'disallowed' => 'Животные не разрешены',
		'any' => 'Любые животные',
		'cats' => 'Кошки разрешены',
		'dogs' => 'Собаки разрешены',
		'dogs_small' => 'Маленькие собаки',
		'reptile' => 'Рептилии',
	];
}
$countryCodeLabels = [
	'ru' => 'Россия',
	'by' => 'Беларусь',
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

$hotelTitleRaw = trim((string) $page->title);
$hotelTitle = $sanitizeHeadingText($hotelTitleRaw);
$hotelTitle = $hotelTitle !== '' ? $hotelTitle : $hotelTitleRaw;
$hotelCity = $page->hasField('hotel_city') ? trim((string) $page->hotel_city) : '';
$hotelRegion = $page->hasField('hotel_region') ? trim((string) $page->hotel_region) : '';
$hotelLocation = trim($hotelCity . ', ' . $hotelRegion, ', ');

$hotelRatingRaw = $page->hasField('hotel_rating') ? trim((string) $page->getUnformatted('hotel_rating')) : '';
$hotelRating = is_numeric($hotelRatingRaw) ? (float) $hotelRatingRaw : 4.5;

$hotelPriceRaw = $page->hasField('hotel_price') ? (string) $page->getUnformatted('hotel_price') : '';
$hotelPriceLabel = $formatPrice($hotelPriceRaw);

$hotelMaxGuests = $page->hasField('hotel_max_guests') ? (int) $page->getUnformatted('hotel_max_guests') : 0;
if ($hotelMaxGuests < 2) $hotelMaxGuests = 10;
$hotelGuestsDefault = min(2, $hotelMaxGuests);
$hotelGuestsLabel = $formatGuestLabel($hotelGuestsDefault);

$hotelDescription = '';
if ($page->hasField('hotel_description')) {
	$descriptionParts = $extractComboTextParts($page->getUnformatted('hotel_description'));
	if (count($descriptionParts)) $hotelDescription = implode("\n\n", $descriptionParts);
}
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

$normalizeRichTextToPlain = static function($value) use ($extractAmenityItems): string {
	if (is_array($value)) $value = implode("\n", $extractAmenityItems($value));
	$text = trim((string) $value);
	if ($text === '') return '';
	$textLower = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
	if ($textLower === 'combovalueformatted' || $textLower === 'combovalue') return '';

	$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$text = str_replace("\xc2\xa0", ' ', $text);
	$text = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $text);
	$text = preg_replace('/<\s*br\s*\/?\s*>/iu', "\n", $text) ?? $text;
	$text = preg_replace('/<\s*\/p\s*>/iu', "\n\n", $text) ?? $text;
	$text = strip_tags($text);
	$text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
	$text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
	return trim($text);
};
$hotelDescriptionRawFromDb = '';
$hotelContentRawFromDb = '';
if (isset($database) && $database instanceof WireDatabasePDO) {
	try {
		$stmtDescription = $database->prepare("SELECT data FROM field_hotel_description WHERE pages_id = :page_id LIMIT 1");
		if ($stmtDescription) {
			$stmtDescription->bindValue(':page_id', (int) $page->id, \PDO::PARAM_INT);
			$stmtDescription->execute();
			$hotelDescriptionRawFromDb = trim((string) $stmtDescription->fetchColumn());
		}
		$stmtContent = $database->prepare("SELECT data FROM field_content WHERE pages_id = :page_id LIMIT 1");
		if ($stmtContent) {
			$stmtContent->bindValue(':page_id', (int) $page->id, \PDO::PARAM_INT);
			$stmtContent->execute();
			$hotelContentRawFromDb = trim((string) $stmtContent->fetchColumn());
		}
	} catch (\Throwable $e) {
		// Ignore DB fallback errors; block is optional.
	}
}
$hotelAboutSections = [];
if ($page->hasField('hotel_description')) {
	$hotelDescriptionRaw = $page->getUnformatted('hotel_description');
	$locationText = $normalizeRichTextToPlain($getComboItem($hotelDescriptionRaw, 'i1'));
	if ($locationText === '' && $hotelDescriptionRawFromDb !== '') {
		$descriptionDecoded = json_decode($hotelDescriptionRawFromDb, true);
		if (is_array($descriptionDecoded)) {
			$locationText = $normalizeRichTextToPlain((string) ($descriptionDecoded['i1'] ?? ''));
		}
	}
	if ($locationText !== '') {
		$hotelAboutSections[] = [
			'title' => 'Расположение',
			'text' => $locationText,
		];
	}
}

if ($page->hasField('content')) {
	$contentText = $normalizeRichTextToPlain($page->getUnformatted('content'));
	if ($contentText === '' && $hotelContentRawFromDb !== '') {
		$contentText = $normalizeRichTextToPlain($hotelContentRawFromDb);
	}
	if ($contentText !== '') {
		$hotelAboutSections[] = [
			'title' => 'Описание',
			'text' => $contentText,
		];
	}
}

$hotelAddressLabel = '';
if ($page->hasField('address')) {
	$hotelAddressRaw = $page->getUnformatted('address');
	$addressStreet = $normalizeLine((string) $getComboItem($hotelAddressRaw, 'i1'));
	$addressStreet2 = $normalizeLine((string) $getComboItem($hotelAddressRaw, 'i6'));
	$addressCity = $normalizeLine((string) $getComboItem($hotelAddressRaw, 'i2'));
	$addressPostcode = $normalizeLine((string) $getComboItem($hotelAddressRaw, 'i5'));

	$addressRegion = '';
	$addressRegionRaw = $getComboItem($hotelAddressRaw, 'i3');
	if ($addressRegionRaw instanceof Page) {
		$addressRegion = $normalizeLine((string) $addressRegionRaw->title);
	} elseif (is_numeric((string) $addressRegionRaw) && (int) $addressRegionRaw > 0) {
		$regionPage = $pages->get((int) $addressRegionRaw);
		if ($regionPage && $regionPage->id) $addressRegion = $normalizeLine((string) $regionPage->title);
	} else {
		$addressRegion = $normalizeLine((string) $addressRegionRaw);
	}

	$countryCode = $toLower((string) $getComboItem($hotelAddressRaw, 'i4'));
	$countryLabel = $countryCodeLabels[$countryCode] ?? $normalizeLine((string) $countryCode);

	$streetLine = trim(implode(', ', array_filter([$addressStreet, $addressStreet2], static fn(string $item): bool => $item !== '')));
	$geoLine = trim(implode(', ', array_filter([
		$addressCity !== '' ? $addressCity : $hotelCity,
		$addressRegion !== '' ? $addressRegion : $hotelRegion,
		$addressPostcode,
		$countryLabel,
	], static fn(string $item): bool => $item !== '')));

	$hotelAddressLabel = trim(implode(', ', array_filter([$streetLine, $geoLine], static fn(string $item): bool => $item !== '')));
}
if ($hotelAddressLabel === '') $hotelAddressLabel = $hotelLocation;

$getFirstLine = static function($value) use ($extractAmenityItems): string {
	$items = $extractAmenityItems($value);
	if (!count($items)) return '';
	return trim((string) $items[0]);
};
$hotelRules = [];
$hotelConditionsRaw = $page->hasField('hotel_conditions') ? $page->getUnformatted('hotel_conditions') : null;
if ($hotelConditionsRaw !== null) {
	$checkIn = $getFirstLine($getComboItem($hotelConditionsRaw, 'i2'));
	if ($checkIn !== '') {
		$checkIn = preg_replace('/^\s*регистрация\s+заезда\s*:?\s*/iu', '', $checkIn) ?? $checkIn;
		$hotelRules[] = 'Регистрация заезда: ' . $checkIn;
	}

	$checkOut = $getFirstLine($getComboItem($hotelConditionsRaw, 'i3'));
	if ($checkOut !== '') {
		$checkOut = preg_replace('/^\s*регистрация\s+выезда\s*:?\s*/iu', '', $checkOut) ?? $checkOut;
		$hotelRules[] = 'Регистрация выезда: ' . $checkOut;
	}

	$earlyCheck = $getFirstLine($getComboItem($hotelConditionsRaw, 'i4'));
	if ($earlyCheck !== '') $hotelRules[] = 'Ранний заезд / поздний выезд: ' . $earlyCheck;

	$additionalBed = $getFirstLine($getComboItem($hotelConditionsRaw, 'i5'));
	if ($additionalBed !== '') $hotelRules[] = 'Дополнительная кровать: ' . $additionalBed;

	$parkingRaw = $getFirstLine($getComboItem($hotelConditionsRaw, 'i6'));
	if ($parkingRaw !== '') {
		$parkingLabel = $conditionParkingOptionMap[$parkingRaw] ?? $parkingRaw;
		$hotelRules[] = 'Парковка: ' . $parkingLabel;
	}

	$petsRawItems = $extractAmenityItems($getComboItem($hotelConditionsRaw, 'i7'));
	if (count($petsRawItems)) {
		$petsLabels = [];
		foreach ($petsRawItems as $petsRawItem) {
			$petsKey = trim((string) $petsRawItem);
			if ($petsKey === '') continue;
			$petsLabels[] = $conditionPetsOptionMap[$petsKey] ?? $petsKey;
		}
		if (count($petsLabels)) {
			$hotelRules[] = 'Проживание с животными: ' . implode(', ', array_values(array_unique($petsLabels)));
		}
	}

	$petsFee = $getFirstLine($getComboItem($hotelConditionsRaw, 'i8'));
	if ($petsFee !== '') $hotelRules[] = 'Плата за животных: ' . $petsFee;

	$parkingFee = $getFirstLine($getComboItem($hotelConditionsRaw, 'i9'));
	if ($parkingFee !== '') $hotelRules[] = 'Плата за парковку: ' . $parkingFee;

	$deposit = $getFirstLine($getComboItem($hotelConditionsRaw, 'i10'));
	if ($deposit !== '') $hotelRules[] = 'Депозит: ' . $deposit;
}
if (!count($hotelRules)) $hotelRules[] = 'Уточняются при бронировании.';

$hotelAmenitiesRawFromDb = '';
if (isset($database) && $database instanceof WireDatabasePDO) {
	try {
		$stmt = $database->prepare("SELECT data FROM field_hotel_amenities WHERE pages_id = :page_id LIMIT 1");
		if ($stmt) {
			$stmt->bindValue(':page_id', (int) $page->id, \PDO::PARAM_INT);
			$stmt->execute();
			$rawData = trim((string) $stmt->fetchColumn());
			if ($rawData !== '') $hotelAmenitiesRawFromDb = $rawData;
		}
	} catch (\Throwable $e) {
		// Ignore DB fallback errors; page still has safe defaults.
	}
}

$hotelAmenitySections = [];
$hotelAmenitySectionItemKeys = [];
$addAmenitySectionItem = static function(string $sectionKey, string $sectionTitle, string $itemTitle) use (&$hotelAmenitySections, &$hotelAmenitySectionItemKeys, $toLower): void {
	$sectionKey = trim($sectionKey);
	if ($sectionKey === '') $sectionKey = 'other';
	$sectionTitle = trim($sectionTitle);
	if ($sectionTitle === '') $sectionTitle = 'Прочее';
	$itemTitle = trim($itemTitle);
	if ($itemTitle === '') return;
	$itemKey = $sectionKey . ':' . $toLower($itemTitle);
	if (isset($hotelAmenitySectionItemKeys[$itemKey])) return;
	$hotelAmenitySectionItemKeys[$itemKey] = true;
	if (!isset($hotelAmenitySections[$sectionKey])) {
		$hotelAmenitySections[$sectionKey] = [
			'key' => $sectionKey,
			'title' => $sectionTitle,
			'items' => [],
		];
	}
	$hotelAmenitySections[$sectionKey]['items'][] = $itemTitle;
};

$amenityGroupsRaw = [];
if ($page->hasField('hotel_amenities')) {
	$amenityGroupsRaw = $extractComboGroups($page->getUnformatted('hotel_amenities'));
}
if (!count($amenityGroupsRaw) && $hotelAmenitiesRawFromDb !== '') {
	$amenityGroupsRaw = $extractComboGroups($hotelAmenitiesRawFromDb);
}

$hasDetailedAmenityGroups = false;
foreach ($amenityGroupsRaw as $amenityGroupKey => $amenityGroupItems) {
	if ((string) $amenityGroupKey === 'i2') continue;
	if (!is_array($amenityGroupItems)) continue;
	if (count($amenityGroupItems)) {
		$hasDetailedAmenityGroups = true;
		break;
	}
}

if (count($amenityGroupsRaw)) {
	foreach ($amenityGroupsRaw as $groupKeyRaw => $groupItems) {
		$groupKey = preg_match('/^i\d+$/', (string) $groupKeyRaw) ? (string) $groupKeyRaw : 'other';
		if ($groupKey === 'i2' && $hasDetailedAmenityGroups) continue;
		$groupItems = is_array($groupItems) ? $groupItems : [];
		foreach ($groupItems as $groupItemRaw) {
			$groupItem = $normalizeLine(html_entity_decode((string) $groupItemRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
			if ($groupItem === '') continue;

			$displayItem = $groupItem;
			$detectedCode = $mapAmenityItemToCode($groupItem);
			$isLikelyCode = preg_match('/^[a-z0-9_]+$/', $groupItem) === 1;
			if ($isLikelyCode && isset($amenityMap[$groupItem])) {
				$displayItem = (string) ($amenityMap[$groupItem]['title'] ?? $groupItem);
			}

			$sectionKey = $groupKey;
			if ($sectionKey === 'other' || !isset($amenityGroupLabels[$sectionKey])) {
				if ($detectedCode !== '' && isset($amenityGroupByCode[$detectedCode])) {
					$sectionKey = (string) $amenityGroupByCode[$detectedCode];
				}
			}
			$sectionTitle = (string) ($amenityGroupLabels[$sectionKey] ?? $amenityGroupLabels['other']);
			$addAmenitySectionItem($sectionKey, $sectionTitle, $displayItem);
		}
	}
}

if (!count($hotelAmenitySections)) {
	foreach (['wifi', 'breakfast', 'parking', 'transfer'] as $fallbackAmenityCode) {
		if (!isset($amenityMap[$fallbackAmenityCode])) continue;
		$sectionKey = (string) ($amenityGroupByCode[$fallbackAmenityCode] ?? 'other');
		$sectionTitle = (string) ($amenityGroupLabels[$sectionKey] ?? $amenityGroupLabels['other']);
		$itemTitle = (string) ($amenityMap[$fallbackAmenityCode]['title'] ?? '');
		$addAmenitySectionItem($sectionKey, $sectionTitle, $itemTitle);
	}
}

$orderedAmenitySections = [];
foreach ($amenityGroupOrder as $orderedGroupKey) {
	if (!isset($hotelAmenitySections[$orderedGroupKey])) continue;
	$orderedAmenitySections[] = $hotelAmenitySections[$orderedGroupKey];
	unset($hotelAmenitySections[$orderedGroupKey]);
}
foreach ($hotelAmenitySections as $remainingSection) {
	$orderedAmenitySections[] = $remainingSection;
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

$hotelHeroMainMedia = $hotelMedia[0] ?? '';
$hotelHeroThumbMedia = array_slice($hotelMedia, 1, 4, true);
$hotelHeroVisibleCount = 1 + count($hotelHeroThumbMedia);
$hotelHeroHiddenCount = max(0, count($hotelMedia) - $hotelHeroVisibleCount);
$hotelHeroHiddenMedia = $hotelHeroHiddenCount > 0 ? array_slice($hotelMedia, $hotelHeroVisibleCount, null, true) : [];
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
						<div
							class="hotel-hero-gallery"
							data-hotel-gallery
							data-thumb-count="<?php echo (int) max(0, min(4, count($hotelHeroThumbMedia))); ?>"
						>
							<?php if ($hotelHeroMainMedia !== ''): ?>
								<figure class="hotel-media-item hotel-media-item--hero hotel-media-item--primary">
									<button
										class="hotel-media-trigger"
										type="button"
										data-hotel-gallery-item
										data-gallery-index="0"
										data-gallery-src="<?php echo htmlspecialchars($hotelHeroMainMedia, ENT_QUOTES, 'UTF-8'); ?>"
										data-gallery-alt="<?php echo $sanitizer->entities('Фото отеля 1'); ?>"
										aria-label="<?php echo $sanitizer->entities('Открыть фото 1'); ?>"
									>
										<img
											src="<?php echo htmlspecialchars($hotelHeroMainMedia, ENT_QUOTES, 'UTF-8'); ?>"
											alt="<?php echo $sanitizer->entities('Фото отеля 1'); ?>"
											loading="eager"
											fetchpriority="high"
										/>
									</button>
								</figure>
							<?php endif; ?>
							<?php if (count($hotelHeroThumbMedia)): ?>
								<div class="hotel-hero-gallery-strip">
									<?php $visibleThumbCount = count($hotelHeroThumbMedia); ?>
									<?php $visibleThumbOrder = 0; ?>
									<?php foreach ($hotelHeroThumbMedia as $thumbIndex => $hotelMediaImage): ?>
										<?php
										$visibleThumbOrder++;
										$isMoreTile = $hotelHeroHiddenCount > 0 && $visibleThumbOrder === $visibleThumbCount;
										$thumbPhotoNumber = (int) $thumbIndex + 1;
										$thumbLabel = $isMoreTile
											? ('Открыть галерею, ещё ' . (int) $hotelHeroHiddenCount . ' фото')
											: ('Открыть фото ' . $thumbPhotoNumber);
										?>
										<figure class="hotel-media-item hotel-media-item--hero hotel-media-item--thumb<?php echo $isMoreTile ? ' is-more' : ''; ?>">
											<button
												class="hotel-media-trigger"
												type="button"
												data-hotel-gallery-item
												data-gallery-index="<?php echo (int) $thumbIndex; ?>"
												data-gallery-src="<?php echo htmlspecialchars($hotelMediaImage, ENT_QUOTES, 'UTF-8'); ?>"
												data-gallery-alt="<?php echo $sanitizer->entities('Фото отеля ' . $thumbPhotoNumber); ?>"
												aria-label="<?php echo $sanitizer->entities($thumbLabel); ?>"
											>
												<img
													src="<?php echo htmlspecialchars($hotelMediaImage, ENT_QUOTES, 'UTF-8'); ?>"
													alt="<?php echo $sanitizer->entities('Фото отеля ' . $thumbPhotoNumber); ?>"
													loading="lazy"
												/>
												<?php if ($isMoreTile): ?>
													<span class="hotel-hero-gallery-more">+<?php echo (int) $hotelHeroHiddenCount; ?><small> фото</small></span>
												<?php endif; ?>
											</button>
										</figure>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							<?php if (count($hotelHeroHiddenMedia)): ?>
								<div class="hotel-hero-gallery-hidden" hidden aria-hidden="true">
									<?php foreach ($hotelHeroHiddenMedia as $hiddenIndex => $hotelMediaImage): ?>
										<button
											type="button"
											data-hotel-gallery-item
											data-gallery-index="<?php echo (int) $hiddenIndex; ?>"
											data-gallery-src="<?php echo htmlspecialchars($hotelMediaImage, ENT_QUOTES, 'UTF-8'); ?>"
											data-gallery-alt="<?php echo $sanitizer->entities('Фото отеля ' . ((int) $hiddenIndex + 1)); ?>"
											tabindex="-1"
										></button>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="tour-overview hotel-overview">
		<div class="container tour-overview-layout">
			<div class="tour-included-card hotel-amenities-card">
				<h2 class="tour-section-title">Сервис и удобства</h2>
				<div class="hotel-amenity-groups">
					<?php foreach ($orderedAmenitySections as $amenitySection): ?>
						<?php $sectionItems = (array) ($amenitySection['items'] ?? []); ?>
						<?php if (!count($sectionItems)) continue; ?>
						<section class="hotel-amenity-group">
							<h3 class="hotel-amenity-group-title"><?php echo $sanitizer->entities((string) ($amenitySection['title'] ?? '')); ?></h3>
							<ul class="tour-included-list hotel-amenity-group-list">
								<?php foreach ($sectionItems as $sectionItem): ?>
									<li><?php echo $sanitizer->entities((string) $sectionItem); ?></li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endforeach; ?>
				</div>
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
					<div class="hotel-meta-row hotel-meta-row--address">
						<dt>Адрес</dt>
						<dd><?php echo $sanitizer->entities($hotelAddressLabel); ?></dd>
					</div>
					<div class="hotel-meta-row hotel-meta-row--rules">
						<dt>Правила отеля</dt>
						<dd>
							<ul class="hotel-meta-list">
								<?php foreach ($hotelRules as $hotelRule): ?>
									<li><?php echo $sanitizer->entities((string) $hotelRule); ?></li>
								<?php endforeach; ?>
							</ul>
						</dd>
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

	<?php if (count($hotelAboutSections)): ?>
	<section class="hotel-about-section">
		<div class="container">
			<div class="hotel-about-card">
				<h2 class="tour-section-title">Подробнее об отеле</h2>
				<div class="hotel-about-content">
					<?php foreach ($hotelAboutSections as $aboutSection): ?>
						<article class="hotel-about-item">
							<h3 class="hotel-about-item-title"><?php echo $sanitizer->entities((string) ($aboutSection['title'] ?? '')); ?></h3>
							<p class="hotel-about-item-text"><?php echo nl2br($sanitizer->entities((string) ($aboutSection['text'] ?? ''))); ?></p>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>
	<?php endif; ?>

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
