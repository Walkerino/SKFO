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
	'wifi' => ['title' => 'Бесплатный Wi-Fi', 'icon' => 'site/assets/wifi.44597012.svg'],
	'parking' => ['title' => 'Паркинг', 'icon' => 'hotel-amenity-parking.svg'],
	'elevator' => ['title' => 'Лифт', 'icon' => 'hotel-amenity-elevator.svg'],
	'soundproof_rooms' => ['title' => 'Звукоизолированные номера'],
	'air_conditioning' => ['title' => 'Кондиционер', 'short' => 'A/C', 'icon' => 'site/assets/conditioner.ce1a9abe.svg'],
	'kids' => ['title' => 'Подходит для детей', 'icon' => 'site/assets/children.45775593.svg'],
	'tv' => ['title' => 'Телевизор', 'short' => 'TV', 'icon' => 'site/assets/tv.3c977110.svg'],
	'spa' => ['title' => 'Spa-центр', 'icon' => 'hotel-amenity-spa.svg'],
	'minibar' => ['title' => 'Мини-бар', 'icon' => 'site/assets/mini-bar.742c1dbb.svg'],
	'breakfast' => ['title' => 'Завтрак', 'icon' => 'site/assets/meal.27c89335.svg'],
	'transfer' => ['title' => 'Трансфер', 'icon' => 'hotel-amenity-transfer.svg'],
	'accessible' => ['title' => 'Удобства для людей с ограниченными возможностями', 'icon' => 'site/assets/disabled-support.cc65e41d.svg'],
	'gym' => ['title' => 'Спортивный зал', 'icon' => 'site/assets/fitness.cd535f93.svg'],
	'pool' => ['title' => 'Бассейн', 'icon' => 'hotel-amenity-pool.svg'],
	'restaurant' => ['title' => 'Ресторан', 'icon' => 'site/assets/meal.27c89335.svg'],
	'pets' => ['title' => 'Можно с питомцами', 'icon' => 'site/assets/pets.383546b8.svg'],
	'safe' => ['title' => 'Сейф', 'icon' => 'site/assets/safe.9639935f.svg'],
	'bath' => ['title' => 'Ванна', 'icon' => 'site/assets/bath.c1d4458d.svg'],
	'toiletries' => ['title' => 'Туалетные принадлежности', 'icon' => 'site/assets/toilet.1df72879.svg'],
	'shower' => ['title' => 'Душ', 'icon' => 'site/assets/shower.11ab2844.svg'],
	'iron' => ['title' => 'Утюг', 'icon' => 'site/assets/iron.aa58ef9c.svg'],
	'hair_dryer' => ['title' => 'Фен', 'icon' => 'site/assets/hair-dryer.332233dc.svg'],
	'linens' => ['title' => 'Постельное белье', 'icon' => 'site/assets/exclusuve-bed-linen.acfc1543.svg'],
	'wardrobe' => ['title' => 'Шкаф', 'icon' => 'site/assets/wardrobe.f5a8fd98.svg'],
	'mirror' => ['title' => 'Зеркало', 'icon' => 'site/assets/mirror.3b60a325.svg'],
	'tea' => ['title' => 'Чай', 'icon' => 'site/assets/tea.fde63893.svg'],
	'kettle' => ['title' => 'Чайник или кофеварка', 'icon' => 'site/assets/tea.fde63893.svg'],
	'telephone' => ['title' => 'Телефон', 'icon' => 'site/assets/telephone.b100e40c.svg'],
	'blackout_blinds' => ['title' => 'Плотные шторы', 'icon' => 'site/assets/blackout-blinds.dd56c4ee.svg'],
	'heating' => ['title' => 'Отопление', 'icon' => 'site/assets/heating.6c67a823.svg'],
	'slippers' => ['title' => 'Тапочки', 'icon' => 'site/assets/slippers.24762c1e.svg'],
	'desk' => ['title' => 'Рабочий стол', 'icon' => 'site/assets/desk.96294349.svg'],
	'water' => ['title' => 'Бутилированная вода', 'icon' => 'site/assets/water.3c9b9eab.svg'],
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
	if (strpos($key, 'сейф') !== false || strpos($key, 'safe') !== false) return 'safe';
	if (strpos($key, 'ванн') !== false || strpos($key, 'bath') !== false || strpos($key, 'bathroom') !== false) return 'bath';
	if (strpos($key, 'туалет') !== false || strpos($key, 'toilet') !== false || strpos($key, 'toiletr') !== false || strpos($key, 'косметич') !== false) return 'toiletries';
	if (strpos($key, 'душ') !== false || strpos($key, 'shower') !== false) return 'shower';
	if (strpos($key, 'утю') !== false || strpos($key, 'глад') !== false || strpos($key, 'iron') !== false) return 'iron';
	if (strpos($key, 'фен') !== false || strpos($key, 'hair') !== false) return 'hair_dryer';
	if (strpos($key, 'бель') !== false || strpos($key, 'простын') !== false || strpos($key, 'linen') !== false || strpos($key, 'bedsheet') !== false) return 'linens';
	if (strpos($key, 'шкаф') !== false || strpos($key, 'wardrobe') !== false) return 'wardrobe';
	if (strpos($key, 'зеркал') !== false || strpos($key, 'mirror') !== false) return 'mirror';
	if (strpos($key, 'чайник') !== false || strpos($key, 'kettle') !== false || strpos($key, 'кофевар') !== false || strpos($key, 'coffee') !== false) return 'kettle';
	if (strpos($key, 'чай') !== false || strpos($key, 'tea') !== false) return 'tea';
	if (strpos($key, 'телефон') !== false || strpos($key, 'telephone') !== false) return 'telephone';
	if (strpos($key, 'штор') !== false || strpos($key, 'жалюз') !== false || strpos($key, 'blackout') !== false) return 'blackout_blinds';
	if (strpos($key, 'отоплен') !== false || strpos($key, 'heating') !== false) return 'heating';
	if (strpos($key, 'тапоч') !== false || strpos($key, 'slippers') !== false) return 'slippers';
	if (strpos($key, 'стол') !== false || strpos($key, 'desk') !== false || strpos($key, 'рабоч') !== false) return 'desk';
	if ((strpos($key, 'бутыл') !== false && strpos($key, 'вод') !== false) || strpos($key, 'water') !== false) return 'water';

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
$hotelAdultsDefault = min(2, $hotelMaxGuests);
if ($hotelAdultsDefault < 1) $hotelAdultsDefault = 1;
$hotelChildrenDefault = 0;
$hotelGuestsDefault = min($hotelMaxGuests, $hotelAdultsDefault + $hotelChildrenDefault);
$formatGuestCountByForms = static function(int $count, string $singular, string $few, string $many): string {
	$count = max(0, $count);
	$mod10 = $count % 10;
	$mod100 = $count % 100;
	if ($mod10 === 1 && $mod100 !== 11) return $count . ' ' . $singular;
	if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) return $count . ' ' . $few;
	return $count . ' ' . $many;
};
$hotelAdultsLabel = $formatGuestCountByForms($hotelAdultsDefault, 'взрослый', 'взрослых', 'взрослых');
$hotelChildrenLabel = $formatGuestCountByForms($hotelChildrenDefault, 'ребенок', 'ребенка', 'детей');
$hotelGuestsBreakdownLabel = $hotelAdultsLabel . ($hotelChildrenDefault > 0 ? ', ' . $hotelChildrenLabel : '');

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

$resolveAddressRegionLabel = static function($regionRaw) use ($pages, $normalizeLine): string {
	if ($regionRaw instanceof Page) return $normalizeLine((string) $regionRaw->title);
	if (is_numeric((string) $regionRaw) && (int) $regionRaw > 0) {
		$regionPage = $pages->get((int) $regionRaw);
		if ($regionPage && $regionPage->id) return $normalizeLine((string) $regionPage->title);
		return '';
	}
	return $normalizeLine((string) $regionRaw);
};
$pushAddressPart = static function(array &$parts, string $part) use ($normalizeLine, $toLower): void {
	$part = trim($normalizeLine($part), " ,;\t\n\r\0\x0B");
	if ($part === '') return;
	$partKey = $toLower($part);
	foreach ($parts as $existingPart) {
		if ($toLower((string) $existingPart) === $partKey) return;
	}
	$parts[] = $part;
};

$hotelAddressLabel = '';
if ($page->hasField('address')) {
	$hotelAddressRaw = $page->getUnformatted('address');
	$addressStreet = $normalizeLine((string) $getComboItem($hotelAddressRaw, 'i1'));
	$addressStreet2 = $normalizeLine((string) $getComboItem($hotelAddressRaw, 'i6'));
	$addressCity = $normalizeLine((string) $getComboItem($hotelAddressRaw, 'i2'));
	$addressRegion = $resolveAddressRegionLabel($getComboItem($hotelAddressRaw, 'i3'));
	$addressPostcodeRaw = $normalizeLine((string) $getComboItem($hotelAddressRaw, 'i5'));
	$countryCode = $toLower((string) $getComboItem($hotelAddressRaw, 'i4'));
	$countryLabel = $countryCodeLabels[$countryCode] ?? $normalizeLine((string) $countryCode);

	if (($addressStreet === '' || $addressCity === '' || $addressRegion === '') && isset($database) && $database instanceof WireDatabasePDO) {
		try {
			$stmtAddress = $database->prepare("SELECT data FROM field_address WHERE pages_id = :page_id LIMIT 1");
			if ($stmtAddress) {
				$stmtAddress->bindValue(':page_id', (int) $page->id, \PDO::PARAM_INT);
				$stmtAddress->execute();
				$addressRawFromDb = trim((string) $stmtAddress->fetchColumn());
				$addressDecoded = json_decode($addressRawFromDb, true);
				if (is_array($addressDecoded)) {
					if ($addressStreet === '') $addressStreet = $normalizeLine((string) ($addressDecoded['i1'] ?? ''));
					if ($addressStreet2 === '') $addressStreet2 = $normalizeLine((string) ($addressDecoded['i6'] ?? ''));
					if ($addressCity === '') $addressCity = $normalizeLine((string) ($addressDecoded['i2'] ?? ''));
					if ($addressRegion === '') $addressRegion = $resolveAddressRegionLabel($addressDecoded['i3'] ?? '');
					if ($addressPostcodeRaw === '') $addressPostcodeRaw = $normalizeLine((string) ($addressDecoded['i5'] ?? ''));
					if ($countryCode === '') $countryCode = $toLower((string) ($addressDecoded['i4'] ?? ''));
					if ($countryLabel === '' && $countryCode !== '') {
						$countryLabel = $countryCodeLabels[$countryCode] ?? $normalizeLine((string) $countryCode);
					}
				}
			}
		} catch (\Throwable $e) {
			// Ignore DB fallback errors; address has a safe fallback below.
		}
	}

	$addressPostcode = '';
	$addressExtra = '';
	if ($addressPostcodeRaw !== '') {
		$postcodeDigits = preg_replace('/[^\d]+/u', '', $addressPostcodeRaw) ?? '';
		if (preg_match('/^\d{5,7}$/', $postcodeDigits) === 1) {
			$addressPostcode = $postcodeDigits;
		} elseif ($addressStreet === '') {
			$addressStreet = $addressPostcodeRaw;
		} elseif (strpos($addressPostcodeRaw, ',') === false) {
			$addressExtra = $addressPostcodeRaw;
		}
	}

	$addressParts = [];
	$pushAddressPart($addressParts, $addressStreet);
	$pushAddressPart($addressParts, $addressStreet2);
	$pushAddressPart($addressParts, $addressExtra);
	$pushAddressPart($addressParts, $addressCity !== '' ? $addressCity : $hotelCity);
	$pushAddressPart($addressParts, $addressRegion !== '' ? $addressRegion : $hotelRegion);
	$pushAddressPart($addressParts, $addressPostcode);
	$pushAddressPart($addressParts, $countryLabel);

	$hotelAddressLabel = trim(implode(', ', $addressParts));
}
if ($hotelAddressLabel === '') $hotelAddressLabel = $hotelLocation;

$hotelMapQuery = trim(implode(', ', array_filter([$hotelAddressLabel, $hotelTitle], static fn(string $item): bool => $item !== '')));
if ($hotelMapQuery === '') $hotelMapQuery = $hotelLocation;
if ($hotelMapQuery === '') $hotelMapQuery = 'Северо-Кавказский федеральный округ';

$hotelMapCache = isset($cache) && $cache instanceof WireCache ? $cache : null;
$fetchRemoteJson = static function(string $url): string {
	if (function_exists('curl_init')) {
		$ch = curl_init($url);
		if ($ch !== false) {
			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_CONNECTTIMEOUT => 2,
				CURLOPT_TIMEOUT => 2,
				CURLOPT_HTTPHEADER => [
					'User-Agent: SKFO/1.0 (+https://skfo.ru)',
					'Accept: application/json',
					'Accept-Language: ru',
				],
			]);
			$response = curl_exec($ch);
			curl_close($ch);
			if (is_string($response)) return $response;
		}
	}

	if (!ini_get('allow_url_fopen')) return '';
	$context = stream_context_create([
		'http' => [
			'method' => 'GET',
			'timeout' => 2,
			'header' => implode("\r\n", [
				'User-Agent: SKFO/1.0 (+https://skfo.ru)',
				'Accept: application/json',
				'Accept-Language: ru',
			]),
		],
	]);
	$response = @file_get_contents($url, false, $context);
	return is_string($response) ? $response : '';
};
$resolveGeoCoords = static function(string $query) use ($normalizeLine, $hotelMapCache, $fetchRemoteJson): array {
	$query = $normalizeLine($query);
	if ($query === '') return [];

	$cacheKey = 'hotel_map_geocode_' . sha1($query);
	if ($hotelMapCache instanceof WireCache) {
		$cached = $hotelMapCache->get($cacheKey);
		if (is_array($cached) && isset($cached['lat'], $cached['lon'])) return $cached;
		if ($cached === 'miss') return [];
	}

	$url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&accept-language=ru&q=' . rawurlencode($query);
	$response = $fetchRemoteJson($url);
	if ($response === '') {
		if ($hotelMapCache instanceof WireCache) $hotelMapCache->save($cacheKey, 'miss', 60 * 60 * 6);
		return [];
	}

	$decoded = json_decode($response, true);
	$item = is_array($decoded) && isset($decoded[0]) && is_array($decoded[0]) ? $decoded[0] : null;
	$lat = is_array($item) ? (float) ($item['lat'] ?? 0.0) : 0.0;
	$lon = is_array($item) ? (float) ($item['lon'] ?? 0.0) : 0.0;
	$isValid = abs($lat) > 0.001 && abs($lon) > 0.001 && abs($lat) <= 90.0 && abs($lon) <= 180.0;
	if (!$isValid) {
		if ($hotelMapCache instanceof WireCache) $hotelMapCache->save($cacheKey, 'miss', 60 * 60 * 6);
		return [];
	}

	$coords = ['lat' => $lat, 'lon' => $lon];
	if ($hotelMapCache instanceof WireCache) $hotelMapCache->save($cacheKey, $coords, 60 * 60 * 24 * 30);
	return $coords;
};
$formatCoord = static function(float $value): string {
	$formatted = number_format($value, 6, '.', '');
	$formatted = rtrim(rtrim($formatted, '0'), '.');
	if ($formatted === '-0') $formatted = '0';
	return $formatted;
};

$hotelMapCoords = $resolveGeoCoords($hotelMapQuery);
if (!count($hotelMapCoords) && $hotelAddressLabel !== '') {
	$hotelMapCoords = $resolveGeoCoords($hotelAddressLabel);
}
if (!count($hotelMapCoords)) {
	$hotelMapCoords = $resolveGeoCoords(trim(implode(', ', array_filter([$hotelTitle, $hotelLocation], static fn(string $item): bool => $item !== ''))));
}

if (count($hotelMapCoords) && isset($hotelMapCoords['lat'], $hotelMapCoords['lon'])) {
	$lonLabel = $formatCoord((float) $hotelMapCoords['lon']);
	$latLabel = $formatCoord((float) $hotelMapCoords['lat']);
	$pointLabel = $lonLabel . ',' . $latLabel;
	$hotelMapWidgetUrl = 'https://yandex.ru/map-widget/v1/?z=16&ll=' . rawurlencode($pointLabel) . '&pt=' . rawurlencode($pointLabel . ',pm2rdl1');
} else {
	$hotelMapWidgetUrl = 'https://yandex.ru/map-widget/v1/?z=16&text=' . rawurlencode($hotelMapQuery);
}

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

$hotelBasePriceValue = (int) preg_replace('/[^\d]+/', '', $hotelPriceLabel);
if ($hotelBasePriceValue <= 0) $hotelBasePriceValue = (int) preg_replace('/[^\d]+/', '', $hotelPriceRaw);
if ($hotelBasePriceValue <= 0) $hotelBasePriceValue = 10000;
$formatHotelRoomPrice = static function(int $value): string {
	$value = max(1000, $value);
	return "\u{20BD}\u{202F}" . number_format($value, 0, '.', ',');
};

$resolveAmenityIconUrl = static function(string $iconPath) use ($config): string {
	$iconPath = ltrim(trim($iconPath), '/');
	if ($iconPath === '') return '';
	if (is_file($config->paths->root . $iconPath)) return $config->urls->root . $iconPath;
	if (is_file($config->paths->templates . 'assets/icons/' . $iconPath)) return $config->urls->templates . 'assets/icons/' . $iconPath;
	return '';
};
$amenityGroupIconPaths = [
	'i2' => 'site/assets/star.0c35dc2a.svg',
	'i3' => 'site/assets/common-info.5208dc13.svg',
	'i4' => 'site/assets/extraBed.24fe62c6.svg',
	'i5' => 'site/assets/disabled-support.cc65e41d.svg',
	'i6' => 'site/assets/meal.27c89335.svg',
	'i7' => 'site/assets/internet.b8e3abca.svg',
	'i8' => 'site/assets/train.e58c579c.svg',
	'i9' => 'site/assets/languages.14395ccf.svg',
	'i10' => 'site/assets/entertainment.9f88df47.svg',
	'i11' => 'hotel-amenity-parking.svg',
	'i12' => 'site/assets/extra-services.7bf34de9.svg',
	'i13' => 'site/assets/busyness.96294349.svg',
	'i14' => 'site/assets/fitness.cd535f93.svg',
	'i15' => 'site/assets/barber-shop.ed6a092e.svg',
	'i16' => 'site/assets/kids.d5d85382.svg',
	'i17' => 'site/assets/safe.9639935f.svg',
	'other' => 'site/assets/extra-service.1faf2bfd.svg',
];
$amenityGroupIconUrls = [];
foreach ($amenityGroupIconPaths as $groupKey => $iconPath) {
	$iconUrl = $resolveAmenityIconUrl((string) $iconPath);
	if ($iconUrl === '') continue;
	$amenityGroupIconUrls[(string) $groupKey] = $iconUrl;
}

$hotelRoomAmenityPool = [];
$hotelRoomAmenityPoolKeys = [];
$addRoomAmenity = static function(string $amenityCode, string $amenityTitle = '') use (&$hotelRoomAmenityPool, &$hotelRoomAmenityPoolKeys, $amenityMap, $resolveAmenityIconUrl): void {
	$amenityCode = trim($amenityCode);
	if ($amenityCode === '' || isset($hotelRoomAmenityPoolKeys[$amenityCode])) return;
	if (!isset($amenityMap[$amenityCode])) return;

	$amenityConfig = (array) $amenityMap[$amenityCode];
	$iconUrl = $resolveAmenityIconUrl((string) ($amenityConfig['icon'] ?? ''));
	if ($iconUrl === '') return;

	$title = trim($amenityTitle);
	if ($title === '') $title = trim((string) ($amenityConfig['title'] ?? ''));
	if ($title === '') return;

	$hotelRoomAmenityPoolKeys[$amenityCode] = count($hotelRoomAmenityPool);
	$hotelRoomAmenityPool[] = [
		'code' => $amenityCode,
		'title' => $title,
		'icon' => $iconUrl,
	];
};

foreach ($orderedAmenitySections as $amenitySection) {
	$amenityItems = (array) ($amenitySection['items'] ?? []);
	foreach ($amenityItems as $amenityItem) {
		$amenityLabel = trim((string) $amenityItem);
		if ($amenityLabel === '') continue;
		$amenityCode = $mapAmenityItemToCode($amenityLabel);
		if ($amenityCode === '') continue;
		$addRoomAmenity($amenityCode, $amenityLabel);
	}
}

$defaultRoomAmenityCodes = [
	'minibar',
	'hair_dryer',
	'slippers',
	'bath',
	'toiletries',
	'shower',
	'iron',
	'wardrobe',
	'mirror',
	'water',
	'safe',
	'air_conditioning',
	'tv',
	'wifi',
];
foreach ($defaultRoomAmenityCodes as $amenityCode) {
	$addRoomAmenity((string) $amenityCode);
}

$hotelOfferPointIconMap = [
	'beds' => 'site/assets/extraBed.24fe62c6.svg',
	'meals' => 'site/assets/meal.27c89335.svg',
	'cancellation' => 'site/assets/checkin.8d4e37c0.svg',
	'payment' => 'site/assets/payment.2db08cdd.svg',
];
$getHotelOfferPointIcon = static function(string $pointKey) use ($hotelOfferPointIconMap, $resolveAmenityIconUrl): string {
	$iconPath = trim((string) ($hotelOfferPointIconMap[$pointKey] ?? ''));
	if ($iconPath === '') return '';
	return $resolveAmenityIconUrl($iconPath);
};
$normalizeIsoDateValue = static function($value) use ($normalizeLine): string {
	$value = $normalizeLine((string) $value);
	if ($value === '') return '';

	if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
		$year = (int) ($matches[1] ?? 0);
		$month = (int) ($matches[2] ?? 0);
		$day = (int) ($matches[3] ?? 0);
		if (checkdate($month, $day, $year)) {
			return sprintf('%04d-%02d-%02d', $year, $month, $day);
		}
	}

	if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $matches)) {
		$day = (int) ($matches[1] ?? 0);
		$month = (int) ($matches[2] ?? 0);
		$year = (int) ($matches[3] ?? 0);
		if (checkdate($month, $day, $year)) {
			return sprintf('%04d-%02d-%02d', $year, $month, $day);
		}
	}

	return '';
};
$toPositiveInt = static function($value): int {
	$number = (int) preg_replace('/[^\d]+/', '', (string) $value);
	return max(0, $number);
};
$toBool = static function($value): bool {
	$text = trim((string) $value);
	if ($text === '') return false;
	$lower = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
	return in_array($lower, ['1', 'true', 'yes', 'y', 'on', 'да', 'закрыто', 'closed'], true);
};
$tariffMetaDefaults = [
	'label' => '',
	'date_from' => '',
	'date_to' => '',
	'min_nights' => 0,
	'max_nights' => 0,
	'min_guests' => 0,
	'max_guests' => 0,
	'is_closed' => false,
];
$applyTariffMetaPair = static function(array &$meta, string $rawKey, $rawValue) use ($toLower, $normalizeIsoDateValue, $toPositiveInt, $toBool): void {
	$key = $toLower($rawKey);
	$value = trim((string) $rawValue);
	if ($value === '' && $key !== 'closed') return;

	if (in_array($key, ['label', 'name', 'title', 'period'], true)) {
		$meta['label'] = $value;
		return;
	}
	if (in_array($key, ['from', 'date_from', 'start', 'available_from', 'checkin_from'], true)) {
		$iso = $normalizeIsoDateValue($value);
		if ($iso !== '') $meta['date_from'] = $iso;
		return;
	}
	if (in_array($key, ['to', 'date_to', 'end', 'available_to', 'checkout_to'], true)) {
		$iso = $normalizeIsoDateValue($value);
		if ($iso !== '') $meta['date_to'] = $iso;
		return;
	}
	if (in_array($key, ['min_nights', 'min_night', 'minstay', 'min_stay', 'min_days', 'min_day'], true)) {
		$meta['min_nights'] = $toPositiveInt($value);
		return;
	}
	if (in_array($key, ['max_nights', 'max_night', 'maxstay', 'max_stay', 'max_days', 'max_day'], true)) {
		$meta['max_nights'] = $toPositiveInt($value);
		return;
	}
	if (in_array($key, ['min_guests', 'min_guest'], true)) {
		$meta['min_guests'] = $toPositiveInt($value);
		return;
	}
	if (in_array($key, ['max_guests', 'max_guest'], true)) {
		$meta['max_guests'] = $toPositiveInt($value);
		return;
	}
	if (in_array($key, ['closed', 'is_closed', 'stop_sale', 'stop'], true)) {
		$meta['is_closed'] = $toBool($value);
	}
};
$extractTariffMetaFromPeriod = static function(string $periodRaw) use ($tariffMetaDefaults, $normalizeLine, $applyTariffMetaPair, $normalizeIsoDateValue): array {
	$meta = $tariffMetaDefaults;
	$periodRaw = trim((string) $periodRaw);
	if ($periodRaw === '') return $meta;

	$segments = preg_split('/\s*\|\s*/u', $periodRaw) ?: [];
	$labelSegment = trim((string) ($segments[0] ?? ''));
	$meta['label'] = $normalizeLine($labelSegment);

	for ($i = 1, $count = count($segments); $i < $count; $i++) {
		$segment = trim((string) $segments[$i]);
		if ($segment === '') continue;

		if (preg_match('/^([^=]+)=(.+)$/u', $segment, $matches)) {
			$applyTariffMetaPair($meta, (string) ($matches[1] ?? ''), (string) ($matches[2] ?? ''));
			continue;
		}

		if (preg_match('/(\d{1,2}\.\d{1,2}\.\d{4}|\d{4}-\d{2}-\d{2})\s*(?:\.\.|-|—|–)\s*(\d{1,2}\.\d{1,2}\.\d{4}|\d{4}-\d{2}-\d{2})/u', $segment, $matches)) {
			$dateFrom = $normalizeIsoDateValue((string) ($matches[1] ?? ''));
			$dateTo = $normalizeIsoDateValue((string) ($matches[2] ?? ''));
			if ($dateFrom !== '') $meta['date_from'] = $dateFrom;
			if ($dateTo !== '') $meta['date_to'] = $dateTo;
		}
	}

	return $meta;
};
$extractTariffMetaFromData = static function($dataRaw) use ($tariffMetaDefaults, $applyTariffMetaPair): array {
	$meta = $tariffMetaDefaults;
	if (!is_array($dataRaw)) return $meta;

	$candidate = $dataRaw;
	foreach (['meta', 'availability', 'constraints'] as $nestedKey) {
		if (isset($dataRaw[$nestedKey]) && is_array($dataRaw[$nestedKey])) {
			$candidate = array_merge($candidate, (array) $dataRaw[$nestedKey]);
		}
	}

	foreach ($candidate as $key => $value) {
		if (is_array($value) || is_object($value)) continue;
		$applyTariffMetaPair($meta, (string) $key, (string) $value);
	}

	return $meta;
};

$hotelRoomTariffsByRoomId = [];
$hotelRoomCommonTariffs = [];
if (isset($database) && $database instanceof WireDatabasePDO) {
	try {
		$stmtRoomPrice = $database->prepare("SELECT room, period, price, sort, data FROM field_room_price WHERE pages_id = :page_id ORDER BY sort ASC, data ASC");
		if ($stmtRoomPrice) {
			$stmtRoomPrice->bindValue(':page_id', (int) $page->id, \PDO::PARAM_INT);
			$stmtRoomPrice->execute();
			$tariffRows = $stmtRoomPrice->fetchAll(\PDO::FETCH_ASSOC);
			foreach ((array) $tariffRows as $tariffRow) {
				if (!is_array($tariffRow)) continue;
				$periodRaw = trim((string) ($tariffRow['period'] ?? ''));
				$priceLabel = $normalizeLine((string) ($tariffRow['price'] ?? ''));
				if ($periodRaw === '' && $priceLabel === '') continue;

				$periodMeta = $extractTariffMetaFromPeriod($periodRaw);
				$dataRaw = trim((string) ($tariffRow['data'] ?? ''));
				$dataMeta = $tariffMetaDefaults;
				if ($dataRaw !== '') {
					$dataDecoded = json_decode($dataRaw, true);
					if (is_array($dataDecoded)) {
						$dataMeta = $extractTariffMetaFromData($dataDecoded);
					}
				}

				$periodLabel = trim((string) ($periodMeta['label'] ?? ''));
				if (trim((string) ($dataMeta['label'] ?? '')) !== '') {
					$periodLabel = trim((string) $dataMeta['label']);
				}
				if ($periodLabel === '') $periodLabel = $normalizeLine($periodRaw);

				$dateFrom = trim((string) ($dataMeta['date_from'] ?? ''));
				if ($dateFrom === '') $dateFrom = trim((string) ($periodMeta['date_from'] ?? ''));
				$dateTo = trim((string) ($dataMeta['date_to'] ?? ''));
				if ($dateTo === '') $dateTo = trim((string) ($periodMeta['date_to'] ?? ''));
				$minNights = (int) ($dataMeta['min_nights'] ?? 0);
				if ($minNights <= 0) $minNights = (int) ($periodMeta['min_nights'] ?? 0);
				$maxNights = (int) ($dataMeta['max_nights'] ?? 0);
				if ($maxNights <= 0) $maxNights = (int) ($periodMeta['max_nights'] ?? 0);
				$minGuests = (int) ($dataMeta['min_guests'] ?? 0);
				if ($minGuests <= 0) $minGuests = (int) ($periodMeta['min_guests'] ?? 0);
				$maxGuests = (int) ($dataMeta['max_guests'] ?? 0);
				if ($maxGuests <= 0) $maxGuests = (int) ($periodMeta['max_guests'] ?? 0);
				$isClosed = !empty($dataMeta['is_closed']) || !empty($periodMeta['is_closed']);

				$tariff = [
					'period' => $periodLabel,
					'price' => $priceLabel,
					'date_from' => $dateFrom,
					'date_to' => $dateTo,
					'min_nights' => max(0, $minNights),
					'max_nights' => max(0, $maxNights),
					'min_guests' => max(0, $minGuests),
					'max_guests' => max(0, $maxGuests),
					'is_closed' => $isClosed ? 1 : 0,
				];
				$roomRefId = (int) ($tariffRow['room'] ?? 0);
				if ($roomRefId > 0) {
					if (!isset($hotelRoomTariffsByRoomId[$roomRefId])) $hotelRoomTariffsByRoomId[$roomRefId] = [];
					$hotelRoomTariffsByRoomId[$roomRefId][] = $tariff;
				} else {
					$hotelRoomCommonTariffs[] = $tariff;
				}
			}
		}
	} catch (\Throwable $e) {
		// Ignore optional room tariff table errors and use a single fallback tariff.
	}
}

$roomTariffToOffer = static function(array $tariffRow, int $fallbackPrice, string $bedsLabel, string $mealsLabel) use ($formatHotelRoomPrice, $normalizeLine, $toLower): array {
	$periodLabel = $normalizeLine((string) ($tariffRow['period'] ?? ''));
	$priceRaw = $normalizeLine((string) ($tariffRow['price'] ?? ''));
	$priceValue = (int) preg_replace('/[^\d]+/u', '', $priceRaw);
	if ($priceValue <= 0) $priceValue = max(1000, $fallbackPrice);

	$pricePrefix = preg_match('/^\s*от\b/iu', $priceRaw) === 1 ? 'от ' : '';
	$priceLabel = $pricePrefix . $formatHotelRoomPrice($priceValue);

	$periodKey = $toLower($periodLabel);
	$cancellationLabel = strpos($periodKey, 'невозврат') !== false
		? 'Невозвратный тариф'
		: 'Условия отмены по тарифу';
	$paymentLabel = (strpos($periodKey, 'при заезд') !== false || strpos($periodKey, 'на месте') !== false)
		? 'Оплата при заезде'
		: 'Оплата по условиям тарифа';

	return [
		'label' => $periodLabel,
		'price' => $priceValue,
		'price_label' => $priceLabel,
		'price_prefix' => $pricePrefix,
		'rate_per_guest' => $priceValue,
		'date_from' => trim((string) ($tariffRow['date_from'] ?? '')),
		'date_to' => trim((string) ($tariffRow['date_to'] ?? '')),
		'min_nights' => max(0, (int) ($tariffRow['min_nights'] ?? 0)),
		'max_nights' => max(0, (int) ($tariffRow['max_nights'] ?? 0)),
		'min_guests' => max(0, (int) ($tariffRow['min_guests'] ?? 0)),
		'max_guests' => max(0, (int) ($tariffRow['max_guests'] ?? 0)),
		'is_closed' => !empty($tariffRow['is_closed']) ? 1 : 0,
		'beds' => $bedsLabel,
		'meals' => $mealsLabel,
		'meals_positive' => strpos($toLower($mealsLabel), 'без питан') === false,
		'cancellation' => $cancellationLabel,
		'payment' => $paymentLabel,
	];
};
$addRoomTag = static function(array &$tags, string $label) use ($mapAmenityItemToCode, $amenityMap, $resolveAmenityIconUrl, $toLower, $normalizeLine): void {
	$title = $normalizeLine($label);
	if ($title === '') return;
	$key = $toLower($title);
	foreach ($tags as $existingTag) {
		if ($toLower((string) ($existingTag['title'] ?? '')) === $key) return;
	}
	$icon = '';
	$amenityCode = $mapAmenityItemToCode($title);
	if ($amenityCode !== '' && isset($amenityMap[$amenityCode])) {
		$amenityConfig = (array) $amenityMap[$amenityCode];
		$icon = $resolveAmenityIconUrl((string) ($amenityConfig['icon'] ?? ''));
	}
	$tags[] = [
		'title' => $title,
		'icon' => $icon,
	];
};

$hotelRoomOptions = [];
$roomPagesRaw = $page->hasField('rooms') ? $page->getUnformatted('rooms') : null;
$roomPages = new PageArray();
$roomPageIds = [];
$pushRoomPage = static function($candidate) use (&$roomPages, &$roomPageIds): void {
	if (!$candidate instanceof Page || !$candidate->id) return;
	$roomId = (int) $candidate->id;
	if ($roomId <= 0 || isset($roomPageIds[$roomId])) return;
	$roomPages->add($candidate);
	$roomPageIds[$roomId] = true;
};
if ($roomPagesRaw instanceof PageArray && $roomPagesRaw->count()) {
	foreach ($roomPagesRaw as $roomPageRef) {
		$pushRoomPage($roomPageRef);
	}
} elseif ($roomPagesRaw instanceof Page) {
	$pushRoomPage($roomPagesRaw);
}
if (!$roomPages->count() && $page instanceof Page) {
	foreach ($page->children('include=all') as $roomChildPage) {
		if (!$roomChildPage instanceof Page || !$roomChildPage->id) continue;
		if (!$roomChildPage->hasField('room_info')) continue;
		$pushRoomPage($roomChildPage);
	}
}
if (!$roomPages->count() && isset($pages) && $pages instanceof Pages && count($hotelRoomTariffsByRoomId)) {
	foreach (array_keys($hotelRoomTariffsByRoomId) as $roomRefId) {
		$roomRefId = (int) $roomRefId;
		if ($roomRefId <= 0) continue;
		$pushRoomPage($pages->get($roomRefId));
	}
}
$roomInfoDbById = [];
if ($roomPages instanceof PageArray && $roomPages->count() && isset($database) && $database instanceof WireDatabasePDO) {
	$roomIds = [];
	foreach ($roomPages as $roomPageRef) {
		if (!$roomPageRef instanceof Page || !$roomPageRef->id) continue;
		$roomIds[] = (int) $roomPageRef->id;
	}
	$roomIds = array_values(array_unique($roomIds));
	if (count($roomIds)) {
		try {
			$roomIdsSql = implode(',', array_map('intval', $roomIds));
			$stmtRoomInfo = $database->query("SELECT pages_id, i1, i2, i3, i4, i5, i7, i8, i9, i10 FROM field_room_info WHERE pages_id IN (" . $roomIdsSql . ")");
			if ($stmtRoomInfo) {
				$rows = $stmtRoomInfo->fetchAll(\PDO::FETCH_ASSOC);
				foreach ((array) $rows as $row) {
					if (!is_array($row)) continue;
					$rowId = (int) ($row['pages_id'] ?? 0);
					if ($rowId <= 0) continue;
					$roomInfoDbById[$rowId] = $row;
				}
			}
		} catch (\Throwable $e) {
			// Ignore room_info DB fallback errors and rely on standard field values.
		}
	}
}
$isEmptyRoomInfoValue = static function($value): bool {
	if ($value === null) return true;
	if (is_string($value)) return trim($value) === '';
	if (is_array($value)) {
		foreach ($value as $item) {
			if (!is_scalar($item)) continue;
			if (trim((string) $item) !== '') return false;
		}
		return true;
	}
	if (is_numeric($value)) return ((float) $value) === 0.0;
	return false;
};
$getRoomInfoValue = static function($roomInfoRaw, array $roomInfoDbRow, string $key) use ($getComboItem, $isEmptyRoomInfoValue) {
	$value = $getComboItem($roomInfoRaw, $key);
	if (!$isEmptyRoomInfoValue($value)) return $value;
	return $roomInfoDbRow[$key] ?? $value;
};
if ($roomPages instanceof PageArray && $roomPages->count()) {
	foreach ($roomPages as $roomPage) {
		if (!$roomPage instanceof Page || !$roomPage->id) continue;
		if (method_exists($roomPage, 'isUnpublished') && $roomPage->isUnpublished()) continue;

		$roomTitle = $sanitizeHeadingText((string) $roomPage->title);
		if ($roomTitle === '') $roomTitle = trim((string) $roomPage->title);
		if ($roomTitle === '') $roomTitle = 'Номер';

		$roomInfoRaw = $roomPage->hasField('room_info') ? $roomPage->getUnformatted('room_info') : null;
		$roomInfoDbRow = (array) ($roomInfoDbById[(int) $roomPage->id] ?? []);
		$roomAreaRaw = $normalizeLine((string) $getRoomInfoValue($roomInfoRaw, $roomInfoDbRow, 'i1'));
		$roomArea = (int) preg_replace('/[^\d]+/u', '', $roomAreaRaw);
		$roomGuests = (int) $getRoomInfoValue($roomInfoRaw, $roomInfoDbRow, 'i8');
		if ($roomGuests <= 0) $roomGuests = 2;

		$roomDescription = $normalizeLine((string) $getRoomInfoValue($roomInfoRaw, $roomInfoDbRow, 'i2'));
		if ($roomDescription === '' && $roomPage->hasField('summary')) {
			$roomDescription = $normalizeLine((string) $roomPage->summary);
		}

		$roomViewTags = $extractAmenityItems($getRoomInfoValue($roomInfoRaw, $roomInfoDbRow, 'i3'));
		$roomAmenityTags = $extractAmenityItems($getRoomInfoValue($roomInfoRaw, $roomInfoDbRow, 'i4'));
		$roomMealTags = $extractAmenityItems($getRoomInfoValue($roomInfoRaw, $roomInfoDbRow, 'i5'));
		$roomBedTags = $extractAmenityItems($getRoomInfoValue($roomInfoRaw, $roomInfoDbRow, 'i10'));

		$roomTagItems = [];
		foreach ($roomAmenityTags as $roomAmenityTag) {
			$addRoomTag($roomTagItems, (string) $roomAmenityTag);
			if (count($roomTagItems) >= 8) break;
		}
		if (count($roomTagItems) < 8) {
			foreach ($roomViewTags as $roomViewTag) {
				$addRoomTag($roomTagItems, (string) $roomViewTag);
				if (count($roomTagItems) >= 8) break;
			}
		}
		if (count($roomTagItems) < 8) {
			foreach ($roomBedTags as $roomBedTag) {
				$addRoomTag($roomTagItems, (string) $roomBedTag);
				if (count($roomTagItems) >= 8) break;
			}
		}
		if (!count($roomTagItems)) {
			$roomTagItems = array_slice($hotelRoomAmenityPool, 0, 4);
		}

		$roomImages = [];
		if ($roomPage->hasField('images')) {
			$roomImagesValue = $roomPage->getUnformatted('images');
			if ($roomImagesValue instanceof Pageimage) {
				$roomImages[] = $roomImagesValue->url;
			} elseif ($roomImagesValue instanceof Pageimages && $roomImagesValue->count()) {
				foreach ($roomImagesValue as $roomImageItem) {
					if (!$roomImageItem instanceof Pageimage) continue;
					$roomImages[] = $roomImageItem->url;
				}
			}
		}
		$roomImage = $roomImages[0] ?? ($hotelMedia[0] ?? $hotelImageUrl);
		$roomPhotoCount = count($roomImages);
		if ($roomPhotoCount <= 0) $roomPhotoCount = max(1, count($hotelMedia));
		$roomGalleryImages = count($roomImages) ? $roomImages : [$roomImage];
		$roomGalleryImages = array_values(array_unique(array_filter($roomGalleryImages, static function($value): bool {
			return trim((string) $value) !== '';
		})));
		if (!count($roomGalleryImages) && $roomImage !== '') $roomGalleryImages[] = $roomImage;

		$roomBedsLabel = count($roomBedTags)
			? implode(', ', array_slice($roomBedTags, 0, 3))
			: ($roomDescription !== '' ? $roomDescription : 'Разные варианты кроватей');
		if (function_exists('mb_strlen') && function_exists('mb_substr')) {
			if (mb_strlen($roomBedsLabel, 'UTF-8') > 120) $roomBedsLabel = mb_substr($roomBedsLabel, 0, 117, 'UTF-8') . '…';
		} elseif (strlen($roomBedsLabel) > 120) {
			$roomBedsLabel = substr($roomBedsLabel, 0, 117) . '...';
		}
		$roomMealsLabel = count($roomMealTags)
			? implode(', ', array_slice($roomMealTags, 0, 2))
			: 'Без питания';

		$roomTariffRows = (array) ($hotelRoomTariffsByRoomId[(int) $roomPage->id] ?? []);
		if (!count($roomTariffRows)) $roomTariffRows = $hotelRoomCommonTariffs;
		if (!count($roomTariffRows)) {
			$roomTariffRows = [
				[
					'period' => '',
					'price' => (string) $hotelBasePriceValue,
				],
			];
		}

		$roomOffers = [];
		foreach ($roomTariffRows as $roomTariffRow) {
			if (!is_array($roomTariffRow)) continue;
			$roomOffers[] = $roomTariffToOffer($roomTariffRow, $hotelBasePriceValue, $roomBedsLabel, $roomMealsLabel);
		}
		if (!count($roomOffers)) {
			$roomOffers[] = $roomTariffToOffer(['period' => '', 'price' => (string) $hotelBasePriceValue], $hotelBasePriceValue, $roomBedsLabel, $roomMealsLabel);
		}
		if (count($roomOffers) === 1) {
			$baseOffer = (array) $roomOffers[0];
			$basePrice = (int) ($baseOffer['price'] ?? 0);
			if ($basePrice <= 0) $basePrice = max(1000, $hotelBasePriceValue);
			$breakfastPrice = (int) max($basePrice + 1200, round($basePrice * 1.15));

			$roomOffers[0]['meals'] = 'Без питания';
			$roomOffers[0]['meals_positive'] = false;
			if (trim((string) ($roomOffers[0]['label'] ?? '')) === '') {
				$roomOffers[0]['label'] = 'Базовый тариф';
			}

			$breakfastOffer = $baseOffer;
			$breakfastOffer['label'] = 'Тариф с завтраком';
			$breakfastOffer['price'] = $breakfastPrice;
			$breakfastOffer['price_label'] = $formatHotelRoomPrice($breakfastPrice);
			$breakfastOffer['meals'] = 'Завтрак включён';
			$breakfastOffer['meals_positive'] = true;
			$roomOffers[] = $breakfastOffer;
		}

		$hotelRoomOptions[] = [
			'title' => $roomTitle,
			'area' => max(0, $roomArea),
			'guests' => max(1, $roomGuests),
			'default_guests' => max(1, $hotelGuestsDefault),
			'image' => $roomImage,
			'photo_count' => $roomPhotoCount,
			'gallery_images' => $roomGalleryImages,
			'amenities' => $roomTagItems,
			'offers' => $roomOffers,
		];
	}
}

$hotelHasRealRooms = count($hotelRoomOptions) > 0;
$hotelRoomsEmptyIllustrationUrl = $resolveAmenityIconUrl('site/assets/calendar.bafba94a.svg');
if ($hotelRoomsEmptyIllustrationUrl === '') {
	$hotelRoomsEmptyIllustrationUrl = $config->urls->templates . 'assets/icons/when.svg';
}
$hotelRoomsEmptyTitleHotel = 'Нет свободных номеров на данный момент, приносим свои извинения';
$hotelRoomsEmptyTitleSearch = 'Нет свободных номеров на ваши даты';
$hotelRoomsEmptySubtitleSearch = 'Попробуйте сменить параметры или выбрать другой отель.';

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
		<div class="container tour-overview-layout tour-overview-layout--single">
			<div class="tour-included-card hotel-amenities-card">
				<h2 class="tour-section-title">Сервис и удобства</h2>
				<div class="hotel-amenity-groups">
					<?php foreach ($orderedAmenitySections as $amenitySection): ?>
						<?php $sectionItems = (array) ($amenitySection['items'] ?? []); ?>
						<?php if (!count($sectionItems)) continue; ?>
						<?php
						$amenitySectionKey = (string) ($amenitySection['key'] ?? 'other');
						$amenitySectionTitle = (string) ($amenitySection['title'] ?? '');
						$amenitySectionIconUrl = (string) ($amenityGroupIconUrls[$amenitySectionKey] ?? '');
						$amenitySectionIconClass = $amenitySectionKey === 'i2'
							? 'hotel-amenity-group-title-icon is-highlight'
							: 'hotel-amenity-group-title-icon';
						?>
						<section class="hotel-amenity-group">
							<h3 class="hotel-amenity-group-title">
								<?php if ($amenitySectionIconUrl !== ''): ?>
									<img
										class="<?php echo $amenitySectionIconClass; ?>"
										src="<?php echo htmlspecialchars($amenitySectionIconUrl, ENT_QUOTES, 'UTF-8'); ?>"
										alt=""
										aria-hidden="true"
									/>
								<?php endif; ?>
								<span><?php echo $sanitizer->entities($amenitySectionTitle); ?></span>
							</h3>
							<ul class="tour-included-list hotel-amenity-group-list">
								<?php foreach ($sectionItems as $sectionItem): ?>
									<li><?php echo $sanitizer->entities((string) $sectionItem); ?></li>
								<?php endforeach; ?>
							</ul>
					</section>
				<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>

	<section class="hotel-rooms-section" id="hotel-rooms">
			<div class="container">
				<div class="hotel-rooms-card">
					<header class="hotel-rooms-head">
						<h2 class="tour-section-title">Бронирование номера</h2>
						<div class="hotel-booking-fields hotel-booking-fields--rooms">
							<div class="hotel-booking-row hotel-booking-row--rooms">
								<label class="hotel-booking-input hotel-booking-input--date">
									<span>Заезд</span>
									<span class="hotel-booking-date-field">
										<input
											type="text"
											name="check_in"
											data-hotel-date
											data-hotel-booking-check-in
											data-hotel-date-role="start"
											data-hotel-date-pair="check_out"
											placeholder="дд.мм.гггг"
											readonly
										/>
										<img src="<?php echo $config->urls->templates; ?>assets/icons/when.svg" alt="" aria-hidden="true" />
									</span>
								</label>
								<label class="hotel-booking-input hotel-booking-input--date">
									<span>Выезд</span>
									<span class="hotel-booking-date-field">
										<input
											type="text"
											name="check_out"
											data-hotel-date
											data-hotel-booking-check-out
											data-hotel-date-role="end"
											data-hotel-date-pair="check_in"
											placeholder="дд.мм.гггг"
											readonly
										/>
										<img src="<?php echo $config->urls->templates; ?>assets/icons/when.svg" alt="" aria-hidden="true" />
									</span>
								</label>
								<div class="hotel-booking-input hotel-booking-input--guests">
									<span>Гости</span>
									<label
										class="hero-field hero-field-people hotel-guests-picker"
										data-people-min="1"
										data-people-max="<?php echo (int) $hotelMaxGuests; ?>"
										data-people-unit-singular="гость"
										data-people-unit-few="гостя"
										data-people-unit-many="гостей"
									>
										<input type="text" value="<?php echo $sanitizer->entities($hotelGuestsBreakdownLabel); ?>" readonly />
										<input type="hidden" name="guests_total" value="<?php echo (int) $hotelGuestsDefault; ?>" data-people-hidden-total />
										<input type="hidden" name="guests_adults" value="<?php echo (int) $hotelAdultsDefault; ?>" data-people-hidden="adults" />
										<input type="hidden" name="guests_children" value="<?php echo (int) $hotelChildrenDefault; ?>" data-people-hidden="children" />
										<img src="<?php echo $config->urls->templates; ?>assets/icons/human.svg" alt="" aria-hidden="true" />
										<div class="people-popover" aria-hidden="true">
											<div
												class="people-category-row"
												data-people-category="adults"
												data-min="1"
												data-max="<?php echo (int) $hotelMaxGuests; ?>"
												data-unit-singular="взрослый"
												data-unit-few="взрослых"
												data-unit-many="взрослых"
											>
												<div class="people-category-label">
													<span>Взрослые</span>
													<small>от 14 лет</small>
												</div>
												<div class="people-row">
													<button class="people-btn" type="button" data-action="minus" data-people-target="adults" aria-label="Уменьшить количество взрослых">−</button>
													<span class="people-count" data-people-count="adults" aria-live="polite"><?php echo (int) $hotelAdultsDefault; ?></span>
													<button class="people-btn" type="button" data-action="plus" data-people-target="adults" aria-label="Увеличить количество взрослых">+</button>
												</div>
											</div>
											<div
												class="people-category-row"
												data-people-category="children"
												data-min="0"
												data-max="<?php echo (int) $hotelMaxGuests; ?>"
												data-unit-singular="ребенок"
												data-unit-few="ребенка"
												data-unit-many="детей"
											>
												<div class="people-category-label">
													<span>Дети</span>
													<small>0-13 лет</small>
												</div>
												<div class="people-row">
													<button class="people-btn" type="button" data-action="minus" data-people-target="children" aria-label="Уменьшить количество детей">−</button>
													<span class="people-count" data-people-count="children" aria-live="polite"><?php echo (int) $hotelChildrenDefault; ?></span>
													<button class="people-btn" type="button" data-action="plus" data-people-target="children" aria-label="Увеличить количество детей">+</button>
												</div>
											</div>
										</div>
									</label>
								</div>
								<div class="hotel-booking-action">
									<button class="hotel-booking-action-btn" type="button" data-hotel-booking-action>Найти</button>
								</div>
							</div>
						</div>
						<p class="hotel-rooms-subtitle" data-hotel-booking-summary hidden>На 1 ночь, 2 гостя</p>
				</header>
				<div class="hotel-room-list" data-hotel-room-list<?php echo !$hotelHasRealRooms ? ' hidden' : ''; ?>>
					<?php foreach ($hotelRoomOptions as $roomIndex => $roomOption): ?>
						<?php
						$roomGalleryGroup = 'room-' . ((int) $roomIndex + 1);
						$roomGalleryImages = array_values(array_filter((array) ($roomOption['gallery_images'] ?? []), static function($value): bool {
							return trim((string) $value) !== '';
						}));
						$roomPrimaryImage = trim((string) ($roomOption['image'] ?? ''));
						if (!count($roomGalleryImages) && $roomPrimaryImage !== '') $roomGalleryImages[] = $roomPrimaryImage;
						$roomDisplayImage = $roomGalleryImages[0] ?? $roomPrimaryImage;
						$roomDisplayPhotoCount = max((int) ($roomOption['photo_count'] ?? 0), count($roomGalleryImages));
						if ($roomDisplayPhotoCount <= 0) $roomDisplayPhotoCount = 1;
						$roomTitleText = (string) ($roomOption['title'] ?? 'Номер');
						?>
						<article
							class="hotel-room-row"
							data-hotel-room-row
							data-room-max-guests="<?php echo (int) ($roomOption['guests'] ?? 2); ?>"
						>
							<div class="hotel-room-summary">
								<div class="hotel-room-photo-wrap">
									<button
										class="hotel-room-photo-trigger"
										type="button"
										data-room-gallery-open
										data-room-gallery-group="<?php echo $sanitizer->entities($roomGalleryGroup); ?>"
										aria-label="<?php echo $sanitizer->entities('Открыть фотографии номера: ' . $roomTitleText); ?>"
									>
										<img
											class="hotel-room-photo"
											src="<?php echo htmlspecialchars($roomDisplayImage, ENT_QUOTES, 'UTF-8'); ?>"
											alt="<?php echo $sanitizer->entities($roomTitleText); ?>"
											loading="lazy"
										/>
										<span class="hotel-room-photo-count"><?php echo (int) $roomDisplayPhotoCount; ?> фото</span>
									</button>
								</div>
								<?php if (count($roomGalleryImages)): ?>
									<div class="hotel-room-gallery-items" hidden aria-hidden="true">
										<?php foreach ($roomGalleryImages as $roomGalleryImageIndex => $roomGalleryImage): ?>
											<button
												type="button"
												data-room-gallery-item
												data-room-gallery-group="<?php echo $sanitizer->entities($roomGalleryGroup); ?>"
												data-gallery-index="<?php echo (int) $roomGalleryImageIndex; ?>"
												data-gallery-src="<?php echo htmlspecialchars((string) $roomGalleryImage, ENT_QUOTES, 'UTF-8'); ?>"
												data-gallery-alt="<?php echo $sanitizer->entities($roomTitleText . ' — фото ' . ((int) $roomGalleryImageIndex + 1)); ?>"
												tabindex="-1"
											></button>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
									<h3 class="hotel-room-title"><?php echo $sanitizer->entities($roomTitleText); ?></h3>
									<div class="hotel-room-meta">
										<?php if ((int) ($roomOption['area'] ?? 0) > 0): ?>
											<span><?php echo (int) ($roomOption['area'] ?? 0); ?> м²</span>
										<?php endif; ?>
									</div>
									<div class="hotel-room-tags">
										<?php foreach ((array) ($roomOption['amenities'] ?? []) as $roomAmenity): ?>
											<?php
											$roomAmenityTitle = is_array($roomAmenity) ? trim((string) ($roomAmenity['title'] ?? '')) : trim((string) $roomAmenity);
											if ($roomAmenityTitle === '') continue;
											$roomAmenityIcon = is_array($roomAmenity) ? trim((string) ($roomAmenity['icon'] ?? '')) : '';
											?>
											<span class="hotel-room-tag">
												<?php if ($roomAmenityIcon !== ''): ?>
													<img class="hotel-room-tag-icon" src="<?php echo htmlspecialchars($roomAmenityIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="" aria-hidden="true" />
												<?php endif; ?>
												<span class="hotel-room-tag-text"><?php echo $sanitizer->entities($roomAmenityTitle); ?></span>
											</span>
										<?php endforeach; ?>
								</div>
							</div>
							<div class="hotel-room-offers" data-room-offers>
								<button
									class="hotel-room-offers-nav hotel-room-offers-nav--prev"
									type="button"
									data-room-offers-nav="prev"
									aria-label="Предыдущие тарифы"
								>‹</button>
								<div class="hotel-room-offers-track" data-room-offers-track>
									<?php foreach ((array) ($roomOption['offers'] ?? []) as $roomOffer): ?>
										<?php
										$offerRatePerGuest = max(0, (int) ($roomOffer['rate_per_guest'] ?? ($roomOffer['price'] ?? 0)));
										$offerPricePrefix = (string) ($roomOffer['price_prefix'] ?? '');
										$offerDefaultGuests = max(1, (int) ($roomOption['default_guests'] ?? $hotelGuestsDefault));
										$offerDefaultTotal = max(0, $offerRatePerGuest * $offerDefaultGuests);
										$offerPriceLabel = $offerRatePerGuest > 0
											? $offerPricePrefix . $formatHotelRoomPrice($offerDefaultTotal)
											: (string) ($roomOffer['price_label'] ?? $formatHotelRoomPrice((int) ($roomOffer['price'] ?? 0)));
										$offerCaptionLabel = 'за 1 ночь, для ' . $formatGuestCountByForms($offerDefaultGuests, 'гость', 'гостя', 'гостей');
										?>
											<article
												class="hotel-offer-card"
												data-room-offer-card
												data-offer-rate-per-guest="<?php echo (int) $offerRatePerGuest; ?>"
												data-offer-default-guests="<?php echo (int) $offerDefaultGuests; ?>"
												data-offer-price-prefix="<?php echo htmlspecialchars($offerPricePrefix, ENT_QUOTES, 'UTF-8'); ?>"
												data-offer-date-from="<?php echo htmlspecialchars((string) ($roomOffer['date_from'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
												data-offer-date-to="<?php echo htmlspecialchars((string) ($roomOffer['date_to'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
												data-offer-min-nights="<?php echo (int) ($roomOffer['min_nights'] ?? 0); ?>"
												data-offer-max-nights="<?php echo (int) ($roomOffer['max_nights'] ?? 0); ?>"
												data-offer-min-guests="<?php echo (int) ($roomOffer['min_guests'] ?? 0); ?>"
												data-offer-max-guests="<?php echo (int) ($roomOffer['max_guests'] ?? 0); ?>"
												data-offer-closed="<?php echo !empty($roomOffer['is_closed']) ? '1' : '0'; ?>"
											>
												<ul class="hotel-offer-points">
													<li class="hotel-offer-point">
														<?php $bedsIcon = $getHotelOfferPointIcon('beds'); ?>
														<?php if ($bedsIcon !== ''): ?>
															<img class="hotel-offer-point-icon" src="<?php echo htmlspecialchars($bedsIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="" aria-hidden="true" />
														<?php endif; ?>
														<span class="hotel-offer-point-text"><?php echo $sanitizer->entities((string) ($roomOffer['beds'] ?? '')); ?></span>
													</li>
													<li class="hotel-offer-point<?php echo !empty($roomOffer['meals_positive']) ? ' is-positive' : ''; ?>">
														<?php $mealsIcon = $getHotelOfferPointIcon('meals'); ?>
														<?php if ($mealsIcon !== ''): ?>
															<img class="hotel-offer-point-icon" src="<?php echo htmlspecialchars($mealsIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="" aria-hidden="true" />
														<?php endif; ?>
														<span class="hotel-offer-point-text"><?php echo $sanitizer->entities((string) ($roomOffer['meals'] ?? '')); ?></span>
													</li>
													<li class="hotel-offer-point is-positive">
														<?php $cancellationIcon = $getHotelOfferPointIcon('cancellation'); ?>
														<?php if ($cancellationIcon !== ''): ?>
															<img class="hotel-offer-point-icon" src="<?php echo htmlspecialchars($cancellationIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="" aria-hidden="true" />
														<?php endif; ?>
														<span class="hotel-offer-point-text"><?php echo $sanitizer->entities((string) ($roomOffer['cancellation'] ?? '')); ?></span>
													</li>
													<li class="hotel-offer-point">
														<?php $paymentIcon = $getHotelOfferPointIcon('payment'); ?>
														<?php if ($paymentIcon !== ''): ?>
															<img class="hotel-offer-point-icon" src="<?php echo htmlspecialchars($paymentIcon, ENT_QUOTES, 'UTF-8'); ?>" alt="" aria-hidden="true" />
														<?php endif; ?>
														<span class="hotel-offer-point-text"><?php echo $sanitizer->entities((string) ($roomOffer['payment'] ?? '')); ?></span>
													</li>
												</ul>
												<div class="hotel-offer-footer">
													<?php if (trim((string) ($roomOffer['label'] ?? '')) !== ''): ?>
														<div class="hotel-offer-badge"><?php echo $sanitizer->entities((string) $roomOffer['label']); ?></div>
													<?php endif; ?>
													<div class="hotel-offer-price" data-room-offer-price><?php echo $sanitizer->entities($offerPriceLabel); ?></div>
													<div class="hotel-offer-caption" data-room-offer-caption><?php echo $sanitizer->entities($offerCaptionLabel); ?></div>
													<button class="hotel-offer-book-btn" type="button">Забронировать</button>
												</div>
										</article>
									<?php endforeach; ?>
								</div>
								<button
									class="hotel-room-offers-nav hotel-room-offers-nav--next"
									type="button"
									data-room-offers-nav="next"
									aria-label="Следующие тарифы"
								>›</button>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
				<div
					class="hotel-rooms-empty"
					data-hotel-rooms-empty
					data-empty-title-hotel="<?php echo htmlspecialchars($hotelRoomsEmptyTitleHotel, ENT_QUOTES, 'UTF-8'); ?>"
					data-empty-title-search="<?php echo htmlspecialchars($hotelRoomsEmptyTitleSearch, ENT_QUOTES, 'UTF-8'); ?>"
					data-empty-subtitle-search="<?php echo htmlspecialchars($hotelRoomsEmptySubtitleSearch, ENT_QUOTES, 'UTF-8'); ?>"
					<?php echo $hotelHasRealRooms ? 'hidden' : ''; ?>
				>
					<?php if ($hotelRoomsEmptyIllustrationUrl !== ''): ?>
						<img class="hotel-rooms-empty-image" src="<?php echo htmlspecialchars($hotelRoomsEmptyIllustrationUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" aria-hidden="true" />
					<?php endif; ?>
					<p class="hotel-rooms-empty-title" data-hotel-rooms-empty-title><?php echo $sanitizer->entities($hotelRoomsEmptyTitleHotel); ?></p>
					<p class="hotel-rooms-empty-subtitle" data-hotel-rooms-empty-subtitle hidden><?php echo $sanitizer->entities($hotelRoomsEmptySubtitleSearch); ?></p>
				</div>
			</div>
		</div>
	</section>

	<section class="hotel-location-section" id="hotel-location">
		<div class="container">
			<div class="hotel-location-card">
				<h2 class="tour-section-title">Расположение</h2>
				<p class="hotel-location-address"><?php echo $sanitizer->entities($hotelAddressLabel); ?></p>
				<div class="hotel-location-map-wrap">
					<iframe
						class="hotel-location-map"
						src="<?php echo htmlspecialchars($hotelMapWidgetUrl, ENT_QUOTES, 'UTF-8'); ?>"
						title="<?php echo $sanitizer->entities('Расположение: ' . $hotelTitle); ?>"
						loading="lazy"
						referrerpolicy="no-referrer-when-downgrade"
						allowfullscreen
					></iframe>
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
	<div class="hotel-gallery-lightbox" data-hotel-room-gallery-modal hidden>
		<div class="hotel-gallery-lightbox-backdrop" data-room-gallery-close="backdrop"></div>
		<div class="hotel-gallery-lightbox-dialog" role="dialog" aria-modal="true" aria-label="Фотографии номера">
			<button class="hotel-gallery-close" type="button" data-room-gallery-close="button" aria-label="Закрыть">×</button>
			<button class="hotel-gallery-nav hotel-gallery-nav--prev" type="button" data-room-gallery-nav="prev" aria-label="Предыдущее фото"></button>
			<figure class="hotel-gallery-stage">
				<img src="" alt="" data-room-gallery-image />
			</figure>
			<button class="hotel-gallery-nav hotel-gallery-nav--next" type="button" data-room-gallery-nav="next" aria-label="Следующее фото"></button>
			<div class="hotel-gallery-counter" data-room-gallery-counter></div>
		</div>
	</div>
</div>
