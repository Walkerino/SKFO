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

$normalizeDateInput = static function(string $value): string {
	$value = trim($value);
	if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return '';
	$timestamp = strtotime($value);
	if ($timestamp === false || $timestamp <= 0) return '';
	return date('Y-m-d', $timestamp);
};

$formatPrice = static function(int $value): string {
	return number_format($value, 0, '', ' ') . '₽';
};

$formatRating = static function(float $value): string {
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

$amenityMap = [
	'wifi' => ['title' => 'Wi-Fi', 'short' => 'Wi', 'icon' => 'hotel-amenity-wifi.svg'],
	'kids' => ['title' => 'Для детей', 'short' => 'Kid', 'icon' => 'hotel-amenity-kid.svg'],
	'parking' => ['title' => 'Паркинг', 'short' => 'P', 'icon' => 'hotel-amenity-parking.svg'],
	'spa' => ['title' => 'SPA', 'short' => 'SPA', 'icon' => 'hotel-amenity-spa.svg'],
	'gym' => ['title' => 'Спортзал', 'short' => 'Gym', 'icon' => 'hotel-amenity-gym.svg'],
	'pool' => ['title' => 'Бассейн', 'short' => 'Pool', 'icon' => 'hotel-amenity-pool.svg'],
	'breakfast' => ['title' => 'Завтрак', 'short' => 'BF'],
	'restaurant' => ['title' => 'Ресторан', 'short' => 'Rest'],
	'transfer' => ['title' => 'Трансфер', 'short' => 'Tr'],
	'pets' => ['title' => 'Можно с питомцами', 'short' => 'Pet'],
];

$getImageUrlFromValue = static function($imageValue): string {
	if ($imageValue instanceof Pageimage) return $imageValue->url;
	if ($imageValue instanceof Pageimages && $imageValue->count()) return $imageValue->first()->url;
	return '';
};

$defaultHotelImage = $config->urls->templates . 'assets/image1.png';
$hotelsCatalog = [];

if (isset($pages) && $pages instanceof Pages) {
	$hotelPages = $pages->find('template=hotel, include=all, sort=title, limit=500');
	foreach ($hotelPages as $hotelPage) {
		$title = trim((string) $hotelPage->title);
		if ($title === '') continue;

		$priceRaw = $hotelPage->hasField('hotel_price') ? (string) $hotelPage->getUnformatted('hotel_price') : '';
		$price = (int) preg_replace('/[^\d]+/', '', $priceRaw);
		$ratingRaw = $hotelPage->hasField('hotel_rating') ? trim((string) $hotelPage->getUnformatted('hotel_rating')) : '';
		$rating = is_numeric($ratingRaw) ? (float) $ratingRaw : 0.0;
		$maxGuests = $hotelPage->hasField('hotel_max_guests') ? (int) $hotelPage->getUnformatted('hotel_max_guests') : 1;
		if ($maxGuests < 1) $maxGuests = 1;

		$amenities = [];
		if ($hotelPage->hasField('hotel_amenities')) {
			$amenitiesRaw = trim((string) $hotelPage->hotel_amenities);
			$amenities = array_values(array_filter(array_map('trim', preg_split('/\R+/u', $amenitiesRaw) ?: []), static function(string $code) use ($amenityMap): bool {
				return $code !== '' && isset($amenityMap[$code]);
			}));
		}

		$image = $hotelPage->hasField('hotel_image') ? $getImageUrlFromValue($hotelPage->getUnformatted('hotel_image')) : '';
		if ($image === '') $image = $defaultHotelImage;

		$hotelsCatalog[] = [
			'title' => $title,
			'city' => $hotelPage->hasField('hotel_city') ? trim((string) $hotelPage->hotel_city) : '',
			'region' => $hotelPage->hasField('hotel_region') ? trim((string) $hotelPage->hotel_region) : '',
			'rating' => $rating > 0 ? $rating : 4.0,
			'price' => $price > 0 ? $price : 10000,
			'max_guests' => $maxGuests,
			'amenities' => count($amenities) ? $amenities : ['wifi'],
			'image' => $image,
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

$searchRegion = trim((string) $input->get('where'));
$searchCheckIn = $normalizeDateInput((string) $input->get('checkin'));
$searchCheckOut = $normalizeDateInput((string) $input->get('checkout'));
$searchGuests = (int) $input->get('guests');
if ($searchGuests < 1) $searchGuests = 1;
$searchGuestsLabel = $formatGuestLabel($searchGuests);
$searchCheckInType = $searchCheckIn !== '' ? 'date' : 'text';
$searchCheckOutType = $searchCheckOut !== '' ? 'date' : 'text';

$isSearchSubmitted = trim((string) $input->get('search_hotels')) === '1';
$searchError = '';
if ($isSearchSubmitted && $searchCheckIn !== '' && $searchCheckOut !== '' && $searchCheckIn > $searchCheckOut) {
	$searchError = 'Дата выезда должна быть позже даты заезда.';
}

$regionFieldClass = $searchRegion !== '' ? ' is-filled' : '';
$checkInFieldClass = $searchCheckIn !== '' ? ' is-filled' : '';
$checkOutFieldClass = $searchCheckOut !== '' ? ' is-filled' : '';
$guestsFieldClass = $isSearchSubmitted ? ' is-filled' : '';

$filteredHotels = [];
if ($isSearchSubmitted && $searchError === '') {
	$regionNeedle = $toLower($searchRegion);
	$filteredHotels = array_values(array_filter($hotelsCatalog, static function(array $hotel) use ($regionNeedle, $toLower, $searchGuests): bool {
		if ($regionNeedle !== '') {
			$haystack = $toLower(trim((string) ($hotel['title'] ?? '')) . ' ' . trim((string) ($hotel['city'] ?? '')) . ' ' . trim((string) ($hotel['region'] ?? '')));
			if (strpos($haystack, $regionNeedle) === false) return false;
		}
		$maxGuests = (int) ($hotel['max_guests'] ?? 1);
		if ($maxGuests < $searchGuests) return false;
		return true;
	}));
}

$perPage = 8;
$currentPage = max(1, (int) $input->get('page'));
$totalHotels = count($filteredHotels);
$totalPages = $totalHotels > 0 ? (int) ceil($totalHotels / $perPage) : 1;
if ($currentPage > $totalPages) $currentPage = $totalPages;
$offset = ($currentPage - 1) * $perPage;
$visibleHotels = $isSearchSubmitted ? array_slice($filteredHotels, $offset, $perPage) : [];

$buildPageUrl = static function(int $pageNumber) use ($page, $searchRegion, $searchCheckIn, $searchCheckOut, $searchGuests): string {
	$params = ['search_hotels' => '1'];
	if ($searchRegion !== '') $params['where'] = $searchRegion;
	if ($searchCheckIn !== '') $params['checkin'] = $searchCheckIn;
	if ($searchCheckOut !== '') $params['checkout'] = $searchCheckOut;
	if ($searchGuests > 1) $params['guests'] = (string) $searchGuests;
	if ($pageNumber > 1) $params['page'] = (string) $pageNumber;

	$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
	return $page->url . ($query !== '' ? '?' . $query : '');
};
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
					<a class="hero-tab" href="/regions/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Регионы</span>
					</a>
					<a class="hero-tab" href="/articles/" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/journal.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Статьи</span>
					</a>
				</div>
				<a class="hero-tab hero-tab-forum" href="/forum/" aria-label="Форум">
					<img src="<?php echo $config->urls->templates; ?>assets/icons/forum.svg" alt="" aria-hidden="true" />
					<span>Форум</span>
					<img class="hero-tab-external" src="<?php echo $config->urls->templates; ?>assets/icons/external_site.svg" alt="" aria-hidden="true" />
				</a>
			</div>
			<form class="hero-search hotels-search" action="<?php echo $sanitizer->entities($page->url); ?>" method="get">
				<input type="hidden" name="search_hotels" value="1" />
				<div class="hero-search-fields hotels-search-fields">
					<label class="hero-field hero-field-where<?php echo $regionFieldClass; ?>">
						<span class="sr-only">Регион</span>
						<input type="text" name="where" placeholder="Регион" list="hotel-region-list" value="<?php echo $sanitizer->entities($searchRegion); ?>" />
						<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
					</label>
					<label class="hero-field<?php echo $checkInFieldClass; ?>">
						<span class="sr-only">Дата заезда</span>
						<input type="<?php echo $searchCheckInType; ?>" name="checkin" placeholder="Дата заезда" value="<?php echo $sanitizer->entities($searchCheckIn); ?>" data-hotel-date />
						<img src="<?php echo $config->urls->templates; ?>assets/icons/when.svg" alt="" aria-hidden="true" />
					</label>
					<label class="hero-field<?php echo $checkOutFieldClass; ?>">
						<span class="sr-only">Дата выезда</span>
						<input type="<?php echo $searchCheckOutType; ?>" name="checkout" placeholder="Дата выезда" value="<?php echo $sanitizer->entities($searchCheckOut); ?>" data-hotel-date />
						<img src="<?php echo $config->urls->templates; ?>assets/icons/when.svg" alt="" aria-hidden="true" />
					</label>
					<label
							class="hero-field hero-field-people hotels-field-guests<?php echo $guestsFieldClass; ?>"
						data-people-min="1"
						data-people-max="12"
						data-people-unit-singular="гость"
						data-people-unit-few="гостя"
						data-people-unit-many="гостей"
					>
						<span class="sr-only">Количество гостей</span>
						<input type="text" value="<?php echo $sanitizer->entities($searchGuestsLabel); ?>" readonly />
						<input type="hidden" name="guests" value="<?php echo (int) $searchGuests; ?>" />
						<img src="<?php echo $config->urls->templates; ?>assets/icons/human.svg" alt="" aria-hidden="true" />
						<div class="people-popover" aria-hidden="true">
							<div class="people-row">
								<button class="people-btn" type="button" data-action="minus" aria-label="Уменьшить количество">−</button>
								<span class="people-count" aria-live="polite"><?php echo (int) $searchGuests; ?></span>
								<button class="people-btn" type="button" data-action="plus" aria-label="Увеличить количество">+</button>
							</div>
						</div>
					</label>
				</div>
				<datalist id="hotel-region-list">
					<?php foreach ($regionOptions as $regionOption): ?>
						<option value="<?php echo $sanitizer->entities($regionOption); ?>"></option>
					<?php endforeach; ?>
				</datalist>
				<button class="search-btn" type="submit">Найти отели</button>
			</form>
		</div>
	</section>

	<?php if ($isSearchSubmitted): ?>
		<section class="section section--hotels-results">
			<div class="container">
				<?php if ($searchError !== ''): ?>
					<div class="hotels-empty">
						<?php echo $sanitizer->entities($searchError); ?>
					</div>
				<?php elseif (count($visibleHotels)): ?>
					<div class="hotels-grid">
						<?php foreach ($visibleHotels as $hotel): ?>
							<?php
							$imageUrl = trim((string) ($hotel['image'] ?? ''));
							$city = trim((string) ($hotel['city'] ?? ''));
							$region = trim((string) ($hotel['region'] ?? ''));
							$locationLabel = trim($city . ', ' . $region, ', ');
							?>
							<article class="hotel-card">
								<div class="hotel-card-media"<?php echo $imageUrl !== '' ? " style=\"background-image: url('" . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . "');\"" : ''; ?>>
									<span class="hotel-card-rating"><?php echo $sanitizer->entities($formatRating((float) ($hotel['rating'] ?? 0))); ?></span>
								</div>
								<h2 class="hotel-card-title"><?php echo $sanitizer->entities((string) ($hotel['title'] ?? '')); ?></h2>
								<p class="hotel-card-location"><?php echo $sanitizer->entities($locationLabel); ?></p>
								<ul class="hotel-card-amenities" aria-label="Опции отеля">
										<?php foreach ((array) ($hotel['amenities'] ?? []) as $amenityCode): ?>
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
									<button class="hotel-card-btn" type="button">Выбрать номер</button>
								</div>
							</article>
						<?php endforeach; ?>
					</div>

					<?php if ($totalPages > 1): ?>
						<nav class="hotels-pagination" aria-label="Страницы отелей">
							<?php for ($i = 1; $i <= $totalPages; $i++): ?>
								<a class="hotels-pagination-link<?php echo $i === $currentPage ? ' is-active' : ''; ?>" href="<?php echo $sanitizer->entities($buildPageUrl($i)); ?>">
									<?php echo $i; ?>
								</a>
							<?php endfor; ?>
						</nav>
					<?php endif; ?>
				<?php else: ?>
					<div class="hotels-empty">
						По вашему запросу отели не найдены. Измените фильтры и попробуйте снова.
					</div>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>
</div>
