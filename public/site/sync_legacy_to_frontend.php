<?php namespace ProcessWire;

chdir('/var/www/html/public');
require 'index.php';

$wire = wire();
$users = $wire->users;
$pages = $wire->pages;
$database = $wire->database;

$super = $users->get('name=maximus|admin');
if($super && $super->id) {
	$users->setCurrentUser($super);
}

$queryPairs = static function(WireDatabasePDO $db, string $sql, array $bind = []): array {
	$stmt = $db->prepare($sql);
	foreach($bind as $key => $value) {
		$stmt->bindValue($key, $value);
	}
	$stmt->execute();
	return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
};

$firstImageByPage = [];
foreach($queryPairs($database, "SELECT pages_id, data, sort FROM field_images ORDER BY pages_id ASC, sort ASC, data ASC") as $row) {
	$pageId = (int) ($row['pages_id'] ?? 0);
	$file = trim((string) ($row['data'] ?? ''));
	if($pageId < 1 || $file === '' || isset($firstImageByPage[$pageId])) continue;
	$firstImageByPage[$pageId] = $file;
}

$titleByPage = [];
foreach($queryPairs($database, "SELECT pages_id, data FROM field_title") as $row) {
	$pageId = (int) ($row['pages_id'] ?? 0);
	if($pageId < 1) continue;
	$titleByPage[$pageId] = trim((string) ($row['data'] ?? ''));
}

$regionTitleByPage = [];
foreach($queryPairs($database, "SELECT fr.pages_id, fr.data AS region_id FROM field_region fr") as $row) {
	$pageId = (int) ($row['pages_id'] ?? 0);
	$regionId = (int) ($row['region_id'] ?? 0);
	if($pageId < 1 || $regionId < 1) continue;
	$regionTitleByPage[$pageId] = trim((string) ($titleByPage[$regionId] ?? ''));
}

$cityTitleByPage = [];
foreach($queryPairs($database, "SELECT fc.pages_id, fc.data AS city_id FROM field_city fc") as $row) {
	$pageId = (int) ($row['pages_id'] ?? 0);
	$cityId = (int) ($row['city_id'] ?? 0);
	if($pageId < 1 || $cityId < 1) continue;
	$cityTitleByPage[$pageId] = trim((string) ($titleByPage[$cityId] ?? ''));
}

$summaryByPage = [];
foreach($queryPairs($database, "SELECT pages_id, data FROM field_summary") as $row) {
	$pageId = (int) ($row['pages_id'] ?? 0);
	if($pageId < 1) continue;
	$summaryByPage[$pageId] = trim((string) ($row['data'] ?? ''));
}

$contentByPage = [];
foreach($queryPairs($database, "SELECT pages_id, data FROM field_content") as $row) {
	$pageId = (int) ($row['pages_id'] ?? 0);
	if($pageId < 1) continue;
	$contentByPage[$pageId] = trim((string) ($row['data'] ?? ''));
}

$dateByPage = [];
foreach($queryPairs($database, "SELECT pages_id, data FROM field_date") as $row) {
	$pageId = (int) ($row['pages_id'] ?? 0);
	if($pageId < 1) continue;
	$dateByPage[$pageId] = trim((string) ($row['data'] ?? ''));
}

$sectionTopicByPage = [];
foreach($queryPairs($database, "SELECT pages_id, title, headline FROM field_section ORDER BY pages_id ASC, sort ASC, data ASC") as $row) {
	$pageId = (int) ($row['pages_id'] ?? 0);
	if($pageId < 1 || isset($sectionTopicByPage[$pageId])) continue;
	$title = trim((string) ($row['title'] ?? ''));
	$headline = trim((string) ($row['headline'] ?? ''));
	$sectionTopicByPage[$pageId] = $title !== '' ? $title : $headline;
}

$tourByPage = [];
foreach($queryPairs($database, "SELECT pages_id, i2, i3, i8, i9, i11, i14, i15 FROM field_tour") as $row) {
	$pageId = (int) ($row['pages_id'] ?? 0);
	if($pageId < 1) continue;
	$tourByPage[$pageId] = $row;
}

$tourPriceByPage = [];
foreach($queryPairs($database, "SELECT pages_id, price, period FROM field_tour_price ORDER BY pages_id ASC, sort ASC, data ASC") as $row) {
	$pageId = (int) ($row['pages_id'] ?? 0);
	if($pageId < 1 || isset($tourPriceByPage[$pageId])) continue;
	$tourPriceByPage[$pageId] = [
		'price' => trim((string) ($row['price'] ?? '')),
		'period' => trim((string) ($row['period'] ?? '')),
	];
}

$hotelInfoByPage = [];
foreach($queryPairs($database, "SELECT pages_id, i1, i2, i3, i4 FROM field_hotel_info") as $row) {
	$pageId = (int) ($row['pages_id'] ?? 0);
	if($pageId < 1) continue;
	$hotelInfoByPage[$pageId] = $row;
}

$hotelDescByPage = [];
foreach($queryPairs($database, "SELECT pages_id, i1, i2, i3 FROM field_hotel_description") as $row) {
	$pageId = (int) ($row['pages_id'] ?? 0);
	if($pageId < 1) continue;
	$hotelDescByPage[$pageId] = $row;
}

$hotelAmenitiesByPage = [];
foreach($queryPairs($database, "SELECT pages_id, data FROM field_hotel_amenities") as $row) {
	$pageId = (int) ($row['pages_id'] ?? 0);
	$json = trim((string) ($row['data'] ?? ''));
	if($pageId < 1 || $json === '') continue;
	$decoded = json_decode($json, true);
	if(!is_array($decoded)) continue;
	$hotelAmenitiesByPage[$pageId] = $decoded;
}

$hotelMinPriceByPage = [];
foreach($queryPairs($database, "SELECT pages_id, price FROM field_room_price") as $row) {
	$pageId = (int) ($row['pages_id'] ?? 0);
	$priceRaw = trim((string) ($row['price'] ?? ''));
	if($pageId < 1 || $priceRaw === '') continue;
	$digits = preg_replace('/[^\d]+/u', '', $priceRaw) ?? '';
	if($digits === '') continue;
	$value = (int) $digits;
	if($value < 1) continue;
	if(!isset($hotelMinPriceByPage[$pageId]) || $value < $hotelMinPriceByPage[$pageId]) {
		$hotelMinPriceByPage[$pageId] = $value;
	}
}

$monthCodeMap = [
	'ja' => 'Январь', 'fe' => 'Февраль', 'ma' => 'Март', 'ap' => 'Апрель',
	'my' => 'Май', 'jn' => 'Июнь', 'jl' => 'Июль', 'au' => 'Август',
	'sp' => 'Сентябрь', 'oc' => 'Октябрь', 'nv' => 'Ноябрь', 'dc' => 'Декабрь',
];
$difficultyMap = [
	'1' => 'Базовая',
	'2' => 'Базовая',
	'3' => 'Средняя',
	'4' => 'Высокая',
	'5' => 'Высокая',
];

$normalizeText = static function(string $value): string {
	$value = trim(strip_tags($value));
	$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
	return trim($value);
};

$extractListLines = static function(string $html) use ($normalizeText): array {
	$list = [];
	if(preg_match_all('/<li[^>]*>(.*?)<\/li>/isu', $html, $matches)) {
		foreach($matches[1] as $item) {
			$line = $normalizeText((string) $item);
			if($line !== '') $list[] = $line;
		}
	}
	if(!count($list)) {
		$text = str_replace(["\r"], '', strip_tags($html));
		foreach(explode("\n", $text) as $line) {
			$line = $normalizeText((string) $line);
			if($line !== '') $list[] = $line;
		}
	}
	return array_values(array_unique($list));
};

$addImageIfEmpty = static function(Page $page, string $fieldName, ?string $fileName) use ($wire): bool {
	if(!$page->hasField($fieldName)) return false;
	$images = $page->getUnformatted($fieldName);
	if(!$images instanceof Pageimages) return false;
	if($images->count()) return false;
	$fileName = trim((string) $fileName);
	if($fileName === '') return false;

	$path = $wire->config->paths->files . $page->id . '/' . $fileName;
	if(!is_file($path)) return false;

	$images->add($path);
	$page->save($fieldName);
	return true;
};

$extractAmenityCodes = static function(array $legacy) use ($normalizeText): array {
	$texts = [];
	foreach($legacy as $value) {
		if(is_array($value)) {
			foreach($value as $item) {
				$t = $normalizeText((string) $item);
				if($t !== '') $texts[] = mb_strtolower($t, 'UTF-8');
			}
		} else {
			$t = $normalizeText((string) $value);
			if($t !== '') $texts[] = mb_strtolower($t, 'UTF-8');
		}
	}
	if(!count($texts)) return [];

	$codes = [];
	$add = static function(string $code) use (&$codes): void {
		if(!in_array($code, $codes, true)) $codes[] = $code;
	};

	foreach($texts as $text) {
		if(strpos($text, 'wi-fi') !== false || strpos($text, 'wifi') !== false || strpos($text, 'интернет') !== false) $add('wifi');
		if(strpos($text, 'парков') !== false) $add('parking');
		if(strpos($text, 'трансфер') !== false || strpos($text, 'аэропорт') !== false) $add('transfer');
		if(strpos($text, 'дет') !== false) $add('kids');
		if(strpos($text, 'фитнес') !== false || strpos($text, 'тренаж') !== false || strpos($text, 'спортзал') !== false) $add('gym');
		if(strpos($text, 'бассейн') !== false) $add('pool');
		if(strpos($text, 'spa') !== false || strpos($text, 'спа') !== false) $add('spa');
		if(strpos($text, 'завтрак') !== false || strpos($text, 'питани') !== false || strpos($text, 'ресторан') !== false) $add('breakfast');
		if(strpos($text, 'лифт') !== false) $add('elevator');
		if(strpos($text, 'доступн') !== false || strpos($text, 'ограниченн') !== false) $add('accessible');
		if(strpos($text, 'мини-бар') !== false || strpos($text, 'мини бар') !== false) $add('minibar');
		if(strpos($text, 'животн') !== false || strpos($text, 'питомц') !== false) $add('pets');
	}

	return $codes;
};

$saved = [
	'article' => 0,
	'place' => 0,
	'tour' => 0,
	'hotel' => 0,
	'images' => 0,
];

$savePageIfChanged = static function(Page $page, array $changes) use (&$saved): void {
	if(!count($changes)) return;
	$page->of(false);
	foreach($changes as $field => $value) {
		$page->set($field, $value);
	}
	$page->save();
};

foreach($pages->find('template=article, include=all, limit=10000') as $page) {
	$id = (int) $page->id;
	$changes = [];

	if($page->hasField('article_excerpt') && trim((string) $page->getUnformatted('article_excerpt')) === '') {
		$source = trim((string) ($summaryByPage[$id] ?? ''));
		if($source !== '') $changes['article_excerpt'] = $source;
	}
	if($page->hasField('article_content') && trim((string) $page->getUnformatted('article_content')) === '') {
		$source = trim((string) ($contentByPage[$id] ?? ''));
		if($source !== '') $changes['article_content'] = $source;
	}
	if($page->hasField('article_publish_date') && (int) $page->getUnformatted('article_publish_date') < 1) {
		$rawDate = trim((string) ($dateByPage[$id] ?? ''));
		if($rawDate !== '') {
			$ts = strtotime($rawDate);
			if($ts) $changes['article_publish_date'] = $ts;
		}
	}
	if($page->hasField('article_topic') && trim((string) $page->getUnformatted('article_topic')) === '') {
		$topic = trim((string) ($sectionTopicByPage[$id] ?? ''));
		if($topic !== '') $changes['article_topic'] = $topic;
	}

	if(count($changes)) {
		$page->of(false);
		foreach($changes as $field => $value) {
			$page->set($field, $value);
		}
		$page->save();
		$saved['article']++;
	}

	if($addImageIfEmpty($page, 'article_cover_image', $firstImageByPage[$id] ?? null)) {
		$saved['images']++;
	}
}

foreach($pages->find('template=place, include=all, limit=10000') as $page) {
	$id = (int) $page->id;
	$changes = [];

	if($page->hasField('place_summary') && trim((string) $page->getUnformatted('place_summary')) === '') {
		$summary = trim((string) ($summaryByPage[$id] ?? ''));
		$content = trim((string) ($contentByPage[$id] ?? ''));
		$value = $summary !== '' ? $summary : $normalizeText($content);
		if($value !== '') $changes['place_summary'] = $value;
	}
	if($page->hasField('place_region') && trim((string) $page->getUnformatted('place_region')) === '') {
		$region = trim((string) ($regionTitleByPage[$id] ?? ''));
		if($region !== '') $changes['place_region'] = $region;
	}

	if(count($changes)) {
		$page->of(false);
		foreach($changes as $field => $value) {
			$page->set($field, $value);
		}
		$page->save();
		$saved['place']++;
	}

	if($addImageIfEmpty($page, 'place_image', $firstImageByPage[$id] ?? null)) {
		$saved['images']++;
	}
}

foreach($pages->find('template=tour, include=all, limit=10000') as $page) {
	$id = (int) $page->id;
	$changes = [];
	$legacy = $tourByPage[$id] ?? [];
	$legacySeason = [];
	if(isset($legacy['i2'])) {
		$decodedSeason = json_decode((string) $legacy['i2'], true);
		if(is_array($decodedSeason)) $legacySeason = $decodedSeason;
	}

	if($page->hasField('tour_region') && trim((string) $page->getUnformatted('tour_region')) === '') {
		$region = trim((string) ($regionTitleByPage[$id] ?? ''));
		if($region !== '') $changes['tour_region'] = $region;
	}
	if($page->hasField('tour_duration') && trim((string) $page->getUnformatted('tour_duration')) === '') {
		$duration = trim((string) ($legacy['i8'] ?? ''));
		if($duration !== '') $changes['tour_duration'] = $duration;
	}
	if($page->hasField('tour_group') && trim((string) $page->getUnformatted('tour_group')) === '') {
		$group = trim((string) ($legacy['i11'] ?? ''));
		if($group !== '') $changes['tour_group'] = $group;
	}
	if($page->hasField('tour_age') && trim((string) $page->getUnformatted('tour_age')) === '') {
		$age = trim((string) ($legacy['i9'] ?? ''));
		if($age !== '') $changes['tour_age'] = $age;
	}
	if($page->hasField('tour_description') && trim((string) $page->getUnformatted('tour_description')) === '') {
		$description = trim((string) ($legacy['i14'] ?? ''));
		$fallback = $normalizeText((string) ($legacy['i15'] ?? ''));
		$value = $description !== '' ? $description : $fallback;
		if($value !== '') $changes['tour_description'] = $value;
	}
	if($page->hasField('tour_included') && trim((string) $page->getUnformatted('tour_included')) === '') {
		$includedLines = $extractListLines((string) ($legacy['i15'] ?? ''));
		if(count($includedLines)) $changes['tour_included'] = implode("\n", $includedLines);
	}
	if($page->hasField('tour_difficulty') && trim((string) $page->getUnformatted('tour_difficulty')) === '') {
		$key = trim((string) ($legacy['i3'] ?? ''));
		if($key !== '' && isset($difficultyMap[$key])) {
			$changes['tour_difficulty'] = $difficultyMap[$key];
		}
	}
	if($page->hasField('tour_season') && trim((string) $page->getUnformatted('tour_season')) === '') {
		$monthNames = [];
		foreach($legacySeason as $code) {
			$code = trim((string) $code);
			if($code === '' || !isset($monthCodeMap[$code])) continue;
			$monthNames[] = $monthCodeMap[$code];
		}
		if(count($monthNames)) {
			$changes['tour_season'] = implode(', ', array_values(array_unique($monthNames)));
		} else {
			$period = trim((string) (($tourPriceByPage[$id]['period'] ?? '')));
			if($period !== '') $changes['tour_season'] = $period;
		}
	}
	if($page->hasField('tour_price') && trim((string) $page->getUnformatted('tour_price')) === '') {
		$price = trim((string) (($tourPriceByPage[$id]['price'] ?? '')));
		if($price !== '') $changes['tour_price'] = $price;
	}

	if(count($changes)) {
		$page->of(false);
		foreach($changes as $field => $value) {
			$page->set($field, $value);
		}
		$page->save();
		$saved['tour']++;
	}

	if($addImageIfEmpty($page, 'tour_cover_image', $firstImageByPage[$id] ?? null)) {
		$saved['images']++;
	}
}

foreach($pages->find('template=hotel, include=all, limit=10000') as $page) {
	$id = (int) $page->id;
	$changes = [];
	$legacyInfo = $hotelInfoByPage[$id] ?? [];
	$legacyDesc = $hotelDescByPage[$id] ?? [];
	$legacyAmenities = $hotelAmenitiesByPage[$id] ?? [];

	if($page->hasField('hotel_region') && trim((string) $page->getUnformatted('hotel_region')) === '') {
		$region = trim((string) ($regionTitleByPage[$id] ?? ''));
		if($region !== '') $changes['hotel_region'] = $region;
	}
	if($page->hasField('hotel_city') && trim((string) $page->getUnformatted('hotel_city')) === '') {
		$city = trim((string) ($cityTitleByPage[$id] ?? ''));
		if($city !== '') $changes['hotel_city'] = $city;
	}
	if($page->hasField('hotel_rating') && (float) $page->getUnformatted('hotel_rating') <= 0) {
		$stars = trim((string) ($legacyInfo['i2'] ?? ''));
		if($stars !== '' && is_numeric($stars)) {
			$changes['hotel_rating'] = (float) $stars;
		}
	}
	if($page->hasField('hotel_max_guests') && (int) $page->getUnformatted('hotel_max_guests') < 1) {
		$rooms = trim((string) ($legacyInfo['i3'] ?? ''));
		if($rooms !== '' && is_numeric($rooms)) {
			$changes['hotel_max_guests'] = max(1, (int) $rooms);
		}
	}
	if($page->hasField('hotel_price') && (int) $page->getUnformatted('hotel_price') < 1) {
		$price = (int) ($hotelMinPriceByPage[$id] ?? 0);
		if($price > 0) $changes['hotel_price'] = $price;
	}
	if($page->hasField('hotel_description') && trim((string) $page->getUnformatted('hotel_description')) === '') {
		$parts = [];
		foreach(['i1', 'i2', 'i3'] as $key) {
			$part = trim((string) ($legacyDesc[$key] ?? ''));
			$part = $normalizeText($part);
			if($part !== '') $parts[] = $part;
		}
		if(!count($parts)) {
			$fallback = trim((string) ($summaryByPage[$id] ?? ''));
			if($fallback !== '') $parts[] = $fallback;
		}
		if(count($parts)) $changes['hotel_description'] = implode("\n\n", $parts);
	}
	if($page->hasField('hotel_amenities') && trim((string) $page->getUnformatted('hotel_amenities')) === '') {
		$codes = $extractAmenityCodes($legacyAmenities);
		if(count($codes)) $changes['hotel_amenities'] = implode("\n", $codes);
	}

	if(count($changes)) {
		$page->of(false);
		foreach($changes as $field => $value) {
			$page->set($field, $value);
		}
		$page->save();
		$saved['hotel']++;
	}

	if($addImageIfEmpty($page, 'hotel_image', $firstImageByPage[$id] ?? null)) {
		$saved['images']++;
	}
}

echo "Legacy sync complete:\n";
echo "  articles updated: {$saved['article']}\n";
echo "  places updated: {$saved['place']}\n";
echo "  tours updated: {$saved['tour']}\n";
echo "  hotels updated: {$saved['hotel']}\n";
echo "  cover images added: {$saved['images']}\n";
