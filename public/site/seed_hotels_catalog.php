<?php
declare(strict_types=1);

namespace ProcessWire;

if (PHP_SAPI !== 'cli') {
	echo "This script can be run only from CLI.\n";
	exit(1);
}

$options = getopt('', ['apply', 'count::']);
$apply = isset($options['apply']);
$targetCount = isset($options['count']) ? (int) $options['count'] : 10;
if ($targetCount < 1) $targetCount = 1;
if ($targetCount > 100) $targetCount = 100;

$publicRoot = realpath(__DIR__ . '/../');
if (!$publicRoot || !is_file($publicRoot . '/index.php')) {
	echo "Cannot find ProcessWire bootstrap (public/index.php).\n";
	exit(1);
}

chdir($publicRoot);
require $publicRoot . '/index.php';

$bootWire = (isset($wire) && $wire instanceof ProcessWire) ? $wire : null;
$wire = $bootWire ?: wire();
if (!$wire || !($wire instanceof ProcessWire)) {
	echo "Cannot bootstrap ProcessWire API context.\n";
	exit(1);
}

$users = $wire->wire('users');
$pages = $wire->wire('pages');
$templates = $wire->wire('templates');
$sanitizer = $wire->wire('sanitizer');
$config = $wire->wire('config');

if (!$pages || !$templates || !$sanitizer || !$config) {
	echo "ProcessWire API is not fully initialized (pages/templates/sanitizer/config unavailable).\n";
	exit(1);
}

if ($users) {
	$super = $users->get('name=maximus|admin');
	if ($super && $super->id) {
		$users->setCurrentUser($super);
	}
}

$hotelTemplate = $templates->get('hotel');
if (!$hotelTemplate || !$hotelTemplate->id) {
	echo "Template 'hotel' not found. Run site setup/ready first.\n";
	exit(1);
}

$home = $pages->get('/');
if (!$home || !$home->id) {
	echo "Home page '/' not found.\n";
	exit(1);
}

$basicPageTemplate = $templates->get('basic-page');
$hotelListRoot = $pages->get('/hotel/');
if ($hotelListRoot && $hotelListRoot->id) {
	$hotelsRoot = $hotelListRoot;
} else {
	$hotelsRoot = null;
}

$ensureSection = static function(Page $parent, string $name, string $title, ?Template $template) use ($pages, $apply): ?Page {
	if (!$template || !$template->id) return null;

	$path = $parent->path . $name . '/';
	$page = $pages->get($path);
	if ($page && $page->id) return $page;

	if (!$apply) {
		echo "[dry-run] create section {$path}\n";
		return null;
	}

	$page = new Page();
	$page->template = $template;
	$page->parent = $parent;
	$page->name = $name;
	$page->title = $title;
	$pages->save($page);
	echo "[create] section {$path}\n";
	return $pages->get($path);
};

if (!$hotelsRoot || !$hotelsRoot->id) {
	$contentRoot = $pages->get('/content/');
	if ((!$contentRoot || !$contentRoot->id) && $basicPageTemplate && $basicPageTemplate->id) {
		$contentRoot = $ensureSection($home, 'content', 'Каталог контента', $basicPageTemplate);
	}
	if (!$contentRoot || !$contentRoot->id) {
		$contentRoot = $pages->get('/content/');
	}
	if ($contentRoot && $contentRoot->id) {
		$hotelsRoot = $pages->get('/content/hotels/');
		if ((!$hotelsRoot || !$hotelsRoot->id) && $basicPageTemplate && $basicPageTemplate->id) {
			$hotelsRoot = $ensureSection($contentRoot, 'hotels', 'Каталог отелей', $basicPageTemplate);
		}
		if (!$hotelsRoot || !$hotelsRoot->id) {
			$hotelsRoot = $pages->get('/content/hotels/');
		}
	}
}
if (!$hotelsRoot || !$hotelsRoot->id) {
	echo "Cannot resolve hotels root. Checked '/hotel/' and '/content/hotels/'.\n";
	exit(1);
}

$seedHotels = [
	[
		'title' => 'Вилла Терраса Домбай',
		'city' => 'Домбай',
		'region' => 'Карачаево-Черкесская Республика',
		'rating' => 5,
		'price' => 18500,
		'max_guests' => 4,
		'amenities' => ['wifi', 'parking', 'breakfast', 'transfer'],
		'image_asset' => 'default-hotel-1.png',
		'description' => "Современная вилла с панорамными окнами и видом на горы.\n\nПодходит для семейного отдыха и коротких туров на выходные.",
	],
	[
		'title' => 'Санаторий Нарзан Пятигорск',
		'city' => 'Пятигорск',
		'region' => 'Ставропольский край',
		'rating' => 4,
		'price' => 9600,
		'max_guests' => 2,
		'amenities' => ['wifi', 'breakfast', 'spa', 'gym'],
		'image_asset' => 'default-hotel-2.png',
		'description' => "Санаторий рядом с курортной зоной и терренкурами.\n\nЕсть программы восстановления и удобная локация для прогулок.",
	],
	[
		'title' => 'Апартаменты Горный Вид Кисловодск',
		'city' => 'Кисловодск',
		'region' => 'Ставропольский край',
		'rating' => 4,
		'price' => 8200,
		'max_guests' => 3,
		'amenities' => ['wifi', 'parking', 'air_conditioning'],
		'image_asset' => 'default-hotel-3.png',
		'description' => "Уютные апартаменты рядом с парком и канатной дорогой.\n\nХороший вариант для спокойного отдыха и удаленной работы.",
	],
	[
		'title' => 'Отель Каньон Сулак',
		'city' => 'Дубки',
		'region' => 'Республика Дагестан',
		'rating' => 5,
		'price' => 14200,
		'max_guests' => 3,
		'amenities' => ['wifi', 'parking', 'breakfast', 'transfer'],
		'image_asset' => 'default-hotel-4.png',
		'description' => "Отель с быстрым выездом к смотровым площадкам Сулакского каньона.\n\nИдеален для активных маршрутов по Дагестану.",
	],
	[
		'title' => 'Отель Аул Гуниб',
		'city' => 'Гуниб',
		'region' => 'Республика Дагестан',
		'rating' => 3,
		'price' => 6900,
		'max_guests' => 2,
		'amenities' => ['wifi', 'breakfast', 'kids'],
		'image_asset' => 'default-hotel-5.png',
		'description' => "Небольшой отель в исторической части села.\n\nПодходит для знакомства с горными маршрутами и культурой региона.",
	],
	[
		'title' => 'Вилла Эльбрус Панорама',
		'city' => 'Терскол',
		'region' => 'Кабардино-Балкарская Республика',
		'rating' => 5,
		'price' => 22100,
		'max_guests' => 6,
		'amenities' => ['wifi', 'parking', 'breakfast', 'spa'],
		'image_asset' => 'default-hotel-6.png',
		'description' => "Просторная вилла у подножия Эльбруса.\n\nЕсть отдельная зона отдыха и удобный трансфер к склонам.",
	],
	[
		'title' => 'Апартаменты Чегемский Ущелье',
		'city' => 'Нальчик',
		'region' => 'Кабардино-Балкарская Республика',
		'rating' => 4,
		'price' => 7800,
		'max_guests' => 4,
		'amenities' => ['wifi', 'parking', 'kids'],
		'image_asset' => 'default-hotel-7.png',
		'description' => "Апартаменты с удобным выездом к Чегемским водопадам.\n\nПрактичный вариант для путешествий на автомобиле.",
	],
	[
		'title' => 'Отель Архыз Альпина',
		'city' => 'Архыз',
		'region' => 'Карачаево-Черкесская Республика',
		'rating' => 4,
		'price' => 11800,
		'max_guests' => 3,
		'amenities' => ['wifi', 'breakfast', 'parking', 'transfer'],
		'image_asset' => 'default-hotel-8.png',
		'description' => "Отель вблизи туристических троп и зон катания.\n\nПодойдет для круглогодичного отдыха в горах.",
	],
	[
		'title' => 'Санаторий Асса Ингушетия',
		'city' => 'Джейрах',
		'region' => 'Республика Ингушетия',
		'rating' => 4,
		'price' => 8700,
		'max_guests' => 2,
		'amenities' => ['wifi', 'breakfast', 'spa', 'accessible'],
		'image_asset' => 'default-hotel-9.png',
		'description' => "Санаторный формат в тихой горной долине.\n\nЕсть базовые оздоровительные процедуры и питание.",
	],
	[
		'title' => 'Отель Башни Осетии',
		'city' => 'Владикавказ',
		'region' => 'Республика Северная Осетия',
		'rating' => 4,
		'price' => 9300,
		'max_guests' => 3,
		'amenities' => ['wifi', 'parking', 'breakfast', 'gym'],
		'image_asset' => 'default-hotel-10.png',
		'description' => "Городской отель с удобным доступом к Кармадонскому ущелью.\n\nХороший базовый вариант для комбинированных маршрутов.",
	],
];

$existingHotels = $pages->find('parent_id=' . (int) $hotelsRoot->id . ', template=hotel, include=all, status<8192, limit=2000');
$existingCount = $existingHotels->count();
$missing = max(0, $targetCount - $existingCount);

echo 'Target hotel pages: ' . $targetCount . "\n";
echo 'Hotels root path: ' . $hotelsRoot->path . "\n";
echo 'Current hotel pages under root: ' . $existingCount . "\n";
echo 'Need to create: ' . $missing . "\n";

if ($missing < 1) {
	echo "Nothing to create.\n";
	exit(0);
}

$existingTitleMap = [];
foreach ($existingHotels as $existingHotel) {
	$title = trim((string) $existingHotel->title);
	if ($title === '') continue;
	$key = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
	$existingTitleMap[$key] = true;
}

$created = 0;
$planned = 0;

$buildUniquePageName = static function(string $baseName, Page $parent) use ($pages): string {
	$name = $baseName !== '' ? $baseName : 'hotel';
	$counter = 1;
	$unique = $name;
	while ($pages->get($parent->path . $unique . '/')->id) {
		$counter++;
		$unique = $name . '-' . $counter;
	}
	return $unique;
};

foreach ($seedHotels as $seedHotel) {
	if ($planned >= $missing) break;

	$title = trim((string) ($seedHotel['title'] ?? ''));
	if ($title === '') continue;
	$titleKey = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
	if (isset($existingTitleMap[$titleKey])) continue;

	$planned++;
	$baseName = $sanitizer->pageName($title, true);
	$pageName = $buildUniquePageName($baseName, $hotelsRoot);

	if (!$apply) {
		echo "[dry-run] create hotel '{$title}' at {$hotelsRoot->path}{$pageName}/\n";
		continue;
	}

	$page = new Page();
	$page->template = $hotelTemplate;
	$page->parent = $hotelsRoot;
	$page->name = $pageName;
	$page->title = $title;

	$page->of(false);
	if ($page->hasField('hotel_city')) $page->set('hotel_city', (string) ($seedHotel['city'] ?? ''));
	if ($page->hasField('hotel_region')) $page->set('hotel_region', (string) ($seedHotel['region'] ?? ''));
	if ($page->hasField('hotel_rating')) $page->set('hotel_rating', (float) ($seedHotel['rating'] ?? 0));
	if ($page->hasField('hotel_price')) $page->set('hotel_price', (int) ($seedHotel['price'] ?? 0));
	if ($page->hasField('hotel_max_guests')) $page->set('hotel_max_guests', (int) ($seedHotel['max_guests'] ?? 1));
	if ($page->hasField('hotel_amenities')) $page->set('hotel_amenities', implode("\n", (array) ($seedHotel['amenities'] ?? [])));
	if ($page->hasField('hotel_description')) $page->set('hotel_description', (string) ($seedHotel['description'] ?? ''));

	$pages->save($page);

	if ($page->hasField('hotel_image')) {
		$assetFileName = trim((string) ($seedHotel['image_asset'] ?? ''));
		if ($assetFileName !== '') {
			$assetPath = rtrim((string) $config->paths->templates, '/') . '/assets/hotels/' . ltrim($assetFileName, '/');
			if (is_file($assetPath)) {
				$images = $page->getUnformatted('hotel_image');
				if ($images instanceof Pageimages && !$images->count()) {
					$images->add($assetPath);
					$page->save('hotel_image');
				}
			}
		}
	}

	$created++;
	$existingTitleMap[$titleKey] = true;
	echo "[create] hotel '{$title}' => {$page->path}\n";
}

if (!$apply) {
	echo "Dry run completed. Run with --apply to write changes.\n";
	exit(0);
}

echo "Created hotel pages: {$created}\n";
$finalCount = (int) $pages->count('parent_id=' . (int) $hotelsRoot->id . ', template=hotel, include=all, status<8192');
echo "Final hotel pages under root: {$finalCount}\n";

exit(0);
