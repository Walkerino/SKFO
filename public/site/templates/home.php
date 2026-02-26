<?php namespace ProcessWire;

// Template file for “home” template used by the homepage
// ------------------------------------------------------
// The #content div in this file will replace the #content div in _main.php
// when the Markup Regions feature is enabled, as it is by default. 
// You can also append to (or prepend to) the #content div, and much more. 
// See the Markup Regions documentation:
// https://processwire.com/docs/front-end/output/markup-regions/

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
	$value = trim($value);
	return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
};

$formatDaysLabel = static function(int $days): string {
	$days = max(0, $days);
	$mod10 = $days % 10;
	$mod100 = $days % 100;
	if ($mod10 === 1 && $mod100 !== 11) return $days . ' день';
	if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) return $days . ' дня';
	return $days . ' дней';
};

$normalizeTourDuration = static function(string $value) use ($formatDaysLabel): string {
	$value = trim($value);
	if ($value === '') return '';
	if (preg_match('/\d+/u', $value, $matches) !== 1) return $value;

	$days = (int) ($matches[0] ?? 0);
	if ($days <= 0) return $value;

	return $formatDaysLabel($days);
};

$getImageUrlFromValue = static function($imageValue): string {
	if ($imageValue instanceof Pageimage) return $imageValue->url;
	if ($imageValue instanceof Pageimages && $imageValue->count()) return $imageValue->first()->url;
	return '';
};

$searchRegion = trim((string) $input->get('where'));
$searchTripDate = trim((string) $input->get('when'));
$searchCompanion = trim((string) $input->get('with'));
if ($searchCompanion === '') $searchCompanion = '1 человек';

$isTourSearchSubmitted = trim((string) $input->get('search_tours')) === '1';
$regionFieldClass = $searchRegion !== '' ? ' is-filled' : '';
$tripDateFieldClass = $searchTripDate !== '' ? ' is-filled' : '';
$companionFieldClass = $isTourSearchSubmitted ? ' is-filled' : '';

$defaultTourImage = $config->urls->templates . 'assets/image1.png';
$toursCatalog = [];

if (isset($pages) && $pages instanceof Pages) {
	$tourPages = $pages->find('template=tour, include=all, sort=title, limit=500');
	foreach ($tourPages as $tourPage) {
		if (!$tourPage instanceof Page) continue;

		$title = trim((string) $tourPage->title);
		if ($title === '') continue;

		$imageUrl = $tourPage->hasField('tour_cover_image') ? $getImageUrlFromValue($tourPage->getUnformatted('tour_cover_image')) : '';
		if ($imageUrl === '') $imageUrl = $defaultTourImage;

		$toursCatalog[] = [
			'title' => $title,
			'region' => $tourPage->hasField('tour_region') ? trim((string) $tourPage->tour_region) : '',
			'price' => $tourPage->hasField('tour_price') ? trim((string) $tourPage->tour_price) : '',
			'duration' => $tourPage->hasField('tour_duration') ? $normalizeTourDuration((string) $tourPage->tour_duration) : '',
			'image' => $imageUrl,
			'url' => (string) $tourPage->url,
		];
	}
}

if (!count($toursCatalog) && $page->hasField('home_featured_tours') && $page->home_featured_tours->count()) {
	foreach ($page->home_featured_tours as $tourPage) {
		if (!$tourPage instanceof Page) continue;
		$title = trim((string) $tourPage->title);
		if ($title === '') continue;

		$imageUrl = $tourPage->hasField('tour_cover_image') ? $getImageUrlFromValue($tourPage->getUnformatted('tour_cover_image')) : '';
		if ($imageUrl === '') $imageUrl = $defaultTourImage;

		$toursCatalog[] = [
			'title' => $title,
			'region' => $tourPage->hasField('tour_region') ? trim((string) $tourPage->tour_region) : '',
			'price' => $tourPage->hasField('tour_price') ? trim((string) $tourPage->tour_price) : '',
			'duration' => $tourPage->hasField('tour_duration') ? $normalizeTourDuration((string) $tourPage->tour_duration) : '',
			'image' => $imageUrl,
			'url' => (string) $tourPage->url,
		];
	}
}

$filteredTours = [];
if ($isTourSearchSubmitted) {
	$regionNeedle = $toLower($searchRegion);
	$filteredTours = array_values(array_filter($toursCatalog, static function(array $tour) use ($regionNeedle, $toLower): bool {
		if ($regionNeedle === '') return true;
		$region = $toLower(trim((string) ($tour['region'] ?? '')));
		return strpos($region, $regionNeedle) !== false;
	}));
}

$forumExternalUrl = 'https://club.skfo.ru';

?>

<div id="content">
	<section class="hero">
		<div class="container hero-inner">
			<h1 class="hero-title">
				ТВОЙ КАВКАЗ<br />
				НАЧИНАЕТСЯ ЗДЕСЬ
			</h1>
			<div class="hero-tabs" aria-label="Разделы">
				<div class="hero-tabs-group" role="tablist">
					<span class="tab-indicator" aria-hidden="true"></span>
					<span class="tab-hover" aria-hidden="true"></span>
					<a class="hero-tab is-active" href="/" role="tab" aria-selected="true">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/tour.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Туры</span>
					</a>
					<a class="hero-tab" href="/hotels/" role="tab" aria-selected="false">
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
				<a class="hero-tab hero-tab-forum" href="<?php echo $forumExternalUrl; ?>" target="_blank" rel="noopener noreferrer" aria-label="Форум">
					<img src="<?php echo $config->urls->templates; ?>assets/icons/forum.svg" alt="" aria-hidden="true" />
					<span>Форум</span>
					<img class="hero-tab-external" src="<?php echo $config->urls->templates; ?>assets/icons/external_site.svg" alt="" aria-hidden="true" />
				</a>
			</div>
			<form class="hero-search" action="<?php echo $sanitizer->entities($page->url); ?>" method="get">
				<input type="hidden" name="search_tours" value="1" />
				<div class="hero-search-fields">
					<label class="hero-field hero-field-where<?php echo $regionFieldClass; ?>">
						<span class="sr-only">Куда</span>
						<input type="text" name="where" placeholder="Регион" list="city-list" value="<?php echo $sanitizer->entities($searchRegion); ?>" />
						<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
					</label>
					<label class="hero-field<?php echo $tripDateFieldClass; ?>">
						<span class="sr-only">Дата поездки</span>
						<input type="text" name="when" placeholder="Дата поездки" data-date-input value="<?php echo $sanitizer->entities($searchTripDate); ?>" />
						<img src="<?php echo $config->urls->templates; ?>assets/icons/when.svg" alt="" aria-hidden="true" />
					</label>
					<label class="hero-field hero-field-people<?php echo $companionFieldClass; ?>">
						<span class="sr-only">С кем</span>
						<input type="text" name="with" placeholder="С кем" value="<?php echo $sanitizer->entities($searchCompanion); ?>" readonly />
						<img src="<?php echo $config->urls->templates; ?>assets/icons/human.svg" alt="" aria-hidden="true" />
						<div class="people-popover" aria-hidden="true">
							<div class="people-row">
								<button class="people-btn" type="button" data-action="minus" aria-label="Уменьшить количество">−</button>
								<span class="people-count" aria-live="polite">1</span>
								<button class="people-btn" type="button" data-action="plus" aria-label="Увеличить количество">+</button>
							</div>
						</div>
					</label>
				</div>
				<datalist id="city-list">
					<?php foreach ($regionOptions as $regionOption): ?>
						<option value="<?php echo $sanitizer->entities($regionOption); ?>"></option>
					<?php endforeach; ?>
				</datalist>
				<button class="search-btn" type="submit">Найти туры</button>
			</form>
		</div>
	</section>

	<?php if ($isTourSearchSubmitted): ?>
		<section class="section section--hotels-results section--home-tours-results">
			<div class="container">
				<h2 class="section-title home-tours-results-title">Подходящие туры</h2>
				<?php if (count($filteredTours)): ?>
					<div class="hotels-grid">
						<?php foreach ($filteredTours as $tour): ?>
							<?php
							$tourImage = trim((string) ($tour['image'] ?? ''));
							$tourRegion = trim((string) ($tour['region'] ?? ''));
							$tourPrice = trim((string) ($tour['price'] ?? ''));
							$tourDuration = trim((string) ($tour['duration'] ?? ''));
							$tourUrl = trim((string) ($tour['url'] ?? ''));
							if ($tourPrice === '') $tourPrice = 'Цена уточняется';
							?>
							<article class="hotel-card">
								<div class="hotel-card-media"<?php echo $tourImage !== '' ? " style=\"background-image: url('" . htmlspecialchars($tourImage, ENT_QUOTES, 'UTF-8') . "');\"" : ''; ?>>
									<!-- <?php if ($tourDuration !== ''): ?>
										<span class="hotel-card-rating"><?php echo $sanitizer->entities($tourDuration); ?></span>
									<?php endif; ?> -->
								</div>
								<h2 class="hotel-card-title"><?php echo $sanitizer->entities((string) ($tour['title'] ?? '')); ?></h2>
								<p class="hotel-card-location"><?php echo $sanitizer->entities($tourRegion); ?></p>
								<ul class="hotel-card-amenities" aria-label="Параметры тура">
									<?php if ($tourDuration !== ''): ?>
										<li class="hotel-card-amenity">
											<span class="hotel-card-amenity-icon"><?php echo $sanitizer->entities($tourDuration); ?></span>
										</li>
									<?php endif; ?>
								</ul>
								<div class="hotel-card-footer">
									<div class="hotel-card-price"><?php echo $sanitizer->entities($tourPrice); ?></div>
									<?php if ($tourUrl !== ''): ?>
										<a class="hotel-card-btn" href="<?php echo $sanitizer->entities($tourUrl); ?>">Подробнее</a>
									<?php else: ?>
										<button class="hotel-card-btn" type="button">Подробнее</button>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php else: ?>
					<div class="hotels-empty">
						По вашему запросу туры не найдены. Измените регион и попробуйте снова.
					</div>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

		<section class="section section--places">
			<?php
			$dagestanPlacesCards = [];

			if ($page->hasField('home_featured_places') && $page->home_featured_places->count()) {
				foreach ($page->home_featured_places as $placePage) {
					if (!$placePage instanceof Page) continue;
					$imageUrl = $placePage->hasField('place_image') ? $getImageUrlFromValue($placePage->getUnformatted('place_image')) : '';
					$title = trim((string) $placePage->title);
					if ($title === '' && $imageUrl === '') continue;

					$dagestanPlacesCards[] = [
						'title' => $title,
						'image' => $imageUrl,
					];
				}
			}

			if (!count($dagestanPlacesCards) && $page->hasField('dagestan_places_cards') && $page->dagestan_places_cards->count()) {
				foreach ($page->dagestan_places_cards as $card) {
					$imageUrl = '';
					if ($card->hasField('dagestan_place_image')) {
						$cardImage = $card->getUnformatted('dagestan_place_image');
						if ($cardImage instanceof Pageimage) {
							$imageUrl = $cardImage->url;
						} elseif ($cardImage instanceof Pageimages && $cardImage->count()) {
							$imageUrl = $cardImage->first()->url;
						}
					}

					$dagestanPlacesCards[] = [
						'title' => $card->hasField('dagestan_place_title') ? trim((string) $card->dagestan_place_title) : '',
						'image' => $imageUrl,
					];
				}
			}

			if (!count($dagestanPlacesCards)) {
				$dagestanPlacesCards = [
					[
						'title' => 'Сулакский каньон',
						'image' => '',
					],
					[
						'title' => 'Гамсутль',
						'image' => '',
					],
					[
						'title' => 'Экраноплан “Лунь”',
						'image' => '',
					],
					[
						'title' => 'Гуллинский мост',
						'image' => '',
					],
					[
						'title' => 'Беседка Имама Шамиля',
						'image' => '',
					],
				];
			}

			$dagestanHasSlider = count($dagestanPlacesCards) > 5;
			?>
			<div class="container">
				<div class="places-banner<?php echo $dagestanHasSlider ? ' places-banner--slider' : ''; ?>">
					<div class="places-banner-header">
						<h2 class="section-title section-title--places">Что насчет Дагестана?</h2>
						<div class="places-banner-actions">
							<button class="circle-btn circle-btn--prev places-prev" type="button" aria-label="Предыдущие места"></button>
							<button class="circle-btn circle-btn--next places-next" type="button" aria-label="Следующие места"></button>
						</div>
					</div>
					<div class="places-grid">
						<div class="places-track">
							<?php foreach ($dagestanPlacesCards as $card): ?>
								<?php
								$backgroundStyle = '';
								if (!empty($card['image'])) {
									$image = htmlspecialchars($card['image'], ENT_QUOTES, 'UTF-8');
									$backgroundStyle = " style=\"background-image: linear-gradient(135deg, rgba(17, 24, 39, 0.2), rgba(17, 24, 39, 0.1)), url('{$image}');\"";
								}
								?>
								<article class="place-card">
									<div class="place-card-image"<?php echo $backgroundStyle; ?>></div>
									<h3 class="place-card-title"><?php echo $sanitizer->entities($card['title']); ?></h3>
								</article>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="places-footer">
						<button class="places-more-btn" type="button">
						<span>Показать всё</span>
					</button>
				</div>
			</div>
		</div>
	</section>

	<section class="section section--actual">
		<?php
		$actualCards = [];

			if ($page->hasField('home_actual_places') && $page->home_actual_places->count()) {
				foreach ($page->home_actual_places as $placePage) {
					if (!$placePage instanceof Page) continue;
					$imageUrl = $placePage->hasField('place_image') ? $getImageUrlFromValue($placePage->getUnformatted('place_image')) : '';
					$text = $placePage->hasField('place_summary') ? trim((string) $placePage->place_summary) : '';
					$region = $placePage->hasField('place_region') ? trim((string) $placePage->place_region) : '';
					$title = trim((string) $placePage->title);
					if ($title === '' && $text === '' && $imageUrl === '') continue;

					$actualCards[] = [
						'title' => $title,
						'text' => $text,
						'region' => $region,
						'image' => $imageUrl,
					];
				}
			}

			if (!count($actualCards) && $page->hasField('actual_cards') && $page->actual_cards->count()) {
				foreach ($page->actual_cards as $card) {
					$imageUrl = '';
					if ($card->hasField('card_image')) {
						$cardImage = $card->getUnformatted('card_image');
						if ($cardImage instanceof Pageimage) {
							$imageUrl = $cardImage->url;
						} elseif ($cardImage instanceof Pageimages && $cardImage->count()) {
							$imageUrl = $cardImage->first()->url;
						}
					}

				$actualCards[] = [
					'title' => $card->hasField('card_title') ? trim((string) $card->card_title) : '',
					'text' => $card->hasField('card_text') ? trim((string) $card->card_text) : '',
					'region' => $card->hasField('card_region') ? trim((string) $card->card_region) : '',
					'image' => $imageUrl,
				];
			}
		}

		if (!count($actualCards)) {
			$actualCards = [
				[
					'title' => 'Джейрахское ущелье',
					'text' => 'Гордость Ингушетии! Территория ущелья входит в состав Джейрахско-Ассинского заповедника.',
					'region' => 'Ингушетия',
					'image' => $config->urls->templates . 'assets/image1.png',
				],
				[
					'title' => 'Озеро Кезеной-Ам',
					'text' => 'Самое большое высокогорное и невероятной красоты озеро на Северном Кавказе.',
					'region' => 'Чеченская Республика',
					'image' => $config->urls->templates . 'assets/image1.png',
				],
			];
		}
		?>
		<div class="container actual-grid">
			<?php foreach ($actualCards as $card): ?>
				<?php
				$backgroundStyle = '';
				if (!empty($card['image'])) {
					$image = htmlspecialchars($card['image'], ENT_QUOTES, 'UTF-8');
					$backgroundStyle = " style=\"background-image: linear-gradient(135deg, rgba(17, 24, 39, 0.25), rgba(17, 24, 39, 0.15)), url('{$image}');\"";
				}
				?>
				<article class="actual-card">
					<div class="actual-card-image"<?php echo $backgroundStyle; ?>></div>
					<div class="actual-card-body">
						<h3 class="actual-card-title"><?php echo $sanitizer->entities($card['title']); ?></h3>
						<p class="actual-card-text"><?php echo $sanitizer->entities($card['text']); ?></p>
						<div class="actual-card-footer">
							<span class="tag-location"><?php echo $sanitizer->entities($card['region']); ?></span>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="section section--journal">
		<?php
		$mapHomeJournalArticle = static function(Page $articlePage) use ($getImageUrlFromValue): array {
			$timestamp = $articlePage->hasField('article_publish_date') ? (int) $articlePage->getUnformatted('article_publish_date') : 0;
			return [
				'title' => trim((string) $articlePage->title),
				'topic' => $articlePage->hasField('article_topic') ? trim((string) $articlePage->article_topic) : '',
				'date' => $timestamp > 0 ? date('d.m.Y', $timestamp) : '',
				'image' => $articlePage->hasField('article_cover_image') ? $getImageUrlFromValue($articlePage->getUnformatted('article_cover_image')) : '',
				'url' => '/articles/?' . http_build_query([
					'article' => (string) $articlePage->name,
					'from' => 'home',
					'back' => '/',
				], '', '&', PHP_QUERY_RFC3986),
			];
		};

		$homeJournalArticles = [];
		$homeJournalSlugs = [];
		$addHomeJournalArticle = static function(array $item) use (&$homeJournalArticles, &$homeJournalSlugs): void {
			$title = trim((string) ($item['title'] ?? ''));
			if ($title === '') return;

			$url = trim((string) ($item['url'] ?? ''));
			$slugKey = '';
			if ($url !== '') {
				$urlQuery = parse_url($url, PHP_URL_QUERY);
				if (is_string($urlQuery) && $urlQuery !== '') {
					parse_str($urlQuery, $params);
					$slugKey = trim((string) ($params['article'] ?? ''));
				}
			}
			if ($slugKey === '') {
				$slugKey = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
			}
			if ($slugKey !== '' && isset($homeJournalSlugs[$slugKey])) return;

			if ($slugKey !== '') $homeJournalSlugs[$slugKey] = true;
			$homeJournalArticles[] = $item;
		};

		if ($page->hasField('home_featured_articles') && $page->home_featured_articles->count()) {
			foreach ($page->home_featured_articles as $articlePage) {
				if (!$articlePage instanceof Page || !$articlePage->id) continue;
				$addHomeJournalArticle($mapHomeJournalArticle($articlePage));
			}
		}

		if (count($homeJournalArticles) < 2) {
			$catalogArticlePages = $pages->find('template=article, include=all, sort=-article_publish_date, limit=10');
			foreach ($catalogArticlePages as $articlePage) {
				if (!$articlePage instanceof Page || !$articlePage->id) continue;
				$addHomeJournalArticle($mapHomeJournalArticle($articlePage));
			}
		}

		if (!count($homeJournalArticles)) {
			$homeJournalArticles[] = [
				'title' => 'Как подготовиться к первому путешествию в Дагестан',
				'topic' => 'Советы туристам',
				'date' => '22.12.2025',
				'image' => '',
				'url' => '/articles/?article=kak-podgotovitsya-k-pervomu-puteshestviyu-v-dagestan&from=home&back=%2F',
			];
		}
		?>
		<div class="container">
			<div class="journal-card">
				<div class="journal-card-header">
					<h2 class="journal-title">Статьи СКФО</h2>
					<p class="journal-subtitle">
						Читайте и планируйте поездки </br> по гайдам, маршрутам, советам
					</p>
					<a class="journal-button" href="/articles/">Выбрать статью</a>
				</div>
				<div class="journal-articles" aria-live="polite">
					<?php foreach ($homeJournalArticles as $index => $homeJournalArticle): ?>
						<a class="journal-article<?php echo $index === 0 ? ' is-active' : ''; ?>" href="<?php echo $sanitizer->entities((string) $homeJournalArticle['url']); ?>">
							<?php
							$journalImageStyle = '';
							$journalImageClass = '';
							if (trim((string) $homeJournalArticle['image']) !== '') {
								$journalImage = htmlspecialchars((string) $homeJournalArticle['image'], ENT_QUOTES, 'UTF-8');
								$journalImageStyle = " style=\"background-image: url('{$journalImage}');\"";
								$journalImageClass = ' has-image';
							}
							?>
							<div class="journal-article-image journal-article-image--1<?php echo $journalImageClass; ?>"<?php echo $journalImageStyle; ?>></div>
							<div class="journal-article-content">
								<div class="journal-article-meta">
									<span class="journal-article-date"><?php echo $sanitizer->entities((string) $homeJournalArticle['date']); ?></span>
								</div>
								<h3 class="journal-article-title">
									<?php echo $sanitizer->entities((string) $homeJournalArticle['title']); ?>
								</h3>
								<span class="journal-article-tag"><?php echo $sanitizer->entities((string) $homeJournalArticle['topic']); ?></span>
							</div>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>

	<section class="section section--hot-tours">
		<?php
		$toLower = static function(string $value): string {
			return function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
		};
		$tourUrlByTitle = [];
		$tourPagesForLinks = $pages->find('template=tour, include=all, sort=title, limit=500');
		foreach ($tourPagesForLinks as $tourPageForLink) {
			if (!$tourPageForLink instanceof Page) continue;
			$tourTitleForLink = trim((string) $tourPageForLink->title);
			if ($tourTitleForLink === '') continue;
			$tourUrlByTitle[$toLower($tourTitleForLink)] = (string) $tourPageForLink->url;
		}

		$hotToursCards = [];

		if ($page->hasField('home_featured_tours') && $page->home_featured_tours->count()) {
			foreach ($page->home_featured_tours as $tourPage) {
				if (!$tourPage instanceof Page) continue;
				$imageUrl = $tourPage->hasField('tour_cover_image') ? $getImageUrlFromValue($tourPage->getUnformatted('tour_cover_image')) : '';
				$title = trim((string) $tourPage->title);
				$region = $tourPage->hasField('tour_region') ? trim((string) $tourPage->tour_region) : '';
				$price = $tourPage->hasField('tour_price') ? trim((string) $tourPage->tour_price) : '';
				if ($title === '' && $region === '' && $price === '' && $imageUrl === '') continue;

				$hotToursCards[] = [
					'title' => $title,
					'region' => $region,
					'price' => $price,
					'image' => $imageUrl,
					'url' => (string) $tourPage->url,
				];
			}
		}

		if (!count($hotToursCards) && $page->hasField('hot_tours_cards') && $page->hot_tours_cards->count()) {
			foreach ($page->hot_tours_cards as $card) {
				$imageUrl = '';
				if ($card->hasField('hot_tour_image')) {
					$cardImage = $card->getUnformatted('hot_tour_image');
					if ($cardImage instanceof Pageimage) {
						$imageUrl = $cardImage->url;
					} elseif ($cardImage instanceof Pageimages && $cardImage->count()) {
						$imageUrl = $cardImage->first()->url;
					}
				}

				$hotToursCards[] = [
					'title' => $card->hasField('hot_tour_title') ? trim((string) $card->hot_tour_title) : '',
					'region' => $card->hasField('hot_tour_region') ? trim((string) $card->hot_tour_region) : '',
					'price' => $card->hasField('hot_tour_price') ? trim((string) $card->hot_tour_price) : '',
					'image' => $imageUrl,
					'url' => '',
				];
				$lastIndex = count($hotToursCards) - 1;
				$lastTitleKey = $toLower((string) ($hotToursCards[$lastIndex]['title'] ?? ''));
				if ($lastTitleKey !== '' && isset($tourUrlByTitle[$lastTitleKey])) {
					$hotToursCards[$lastIndex]['url'] = $tourUrlByTitle[$lastTitleKey];
				}
			}
		}

		if (!count($hotToursCards)) {
			$hotToursCards = [
				[
					'title' => 'Посетить Аргунское ущелье',
					'region' => 'Чеченская Республика',
					'price' => 'от 15 000₽',
					'image' => '',
					'url' => isset($tourUrlByTitle[$toLower('Посетить Аргунское ущелье')]) ? $tourUrlByTitle[$toLower('Посетить Аргунское ущелье')] : '',
				],
				[
					'title' => 'Взобраться на гору Эльбрус',
					'region' => 'Кабардино-Балкарская Республика',
					'price' => 'от 15 000₽',
					'image' => '',
					'url' => isset($tourUrlByTitle[$toLower('Взобраться на гору Эльбрус')]) ? $tourUrlByTitle[$toLower('Взобраться на гору Эльбрус')] : '',
				],
				[
					'title' => 'Расслабиться в Суворовских термах',
					'region' => 'Ставропольский край',
					'price' => 'от 15 000₽',
					'image' => '',
					'url' => isset($tourUrlByTitle[$toLower('Расслабиться в Суворовских термах')]) ? $tourUrlByTitle[$toLower('Расслабиться в Суворовских термах')] : '',
				],
				[
					'title' => 'Умчать в Старый Кахиб',
					'region' => 'Республика Дагестан',
					'price' => 'от 15 000₽',
					'image' => '',
					'url' => isset($tourUrlByTitle[$toLower('Умчать в Старый Кахиб')]) ? $tourUrlByTitle[$toLower('Умчать в Старый Кахиб')] : '',
				],
				[
					'title' => 'Заглянуть в Замок на воде Шато Эркен',
					'region' => 'Кабардино-Балкарская Республика',
					'price' => 'от 15 000₽',
					'image' => '',
					'url' => isset($tourUrlByTitle[$toLower('Заглянуть в Замок на воде Шато Эркен')]) ? $tourUrlByTitle[$toLower('Заглянуть в Замок на воде Шато Эркен')] : '',
				],
			];
		}
		?>
		<div class="container-hot-tours">
			<div class="hot-tours-header">
				<h2 class="section-title">Чем заняться этим летом?</h2>
				<div class="hot-tours-actions">
					<button class="circle-btn circle-btn--prev hot-tours-prev" type="button" aria-label="Предыдущие туры"></button>
					<button class="circle-btn circle-btn--next hot-tours-next" type="button" aria-label="Следующие туры"></button>
				</div>
			</div>
				<div class="hot-tours-grid">
					<div class="hot-tours-track">
						<?php foreach ($hotToursCards as $card): ?>
							<?php
							$backgroundStyle = '';
							$cardUrl = trim((string) ($card['url'] ?? ''));
							$isCardLink = $cardUrl !== '';
							if (!empty($card['image'])) {
								$image = htmlspecialchars($card['image'], ENT_QUOTES, 'UTF-8');
								$backgroundStyle = " style=\"background-image: linear-gradient(135deg, rgba(17, 24, 39, 0.2), rgba(17, 24, 39, 0.1)), url('{$image}');\"";
							}
							?>
							<?php if ($isCardLink): ?>
								<a class="hot-tour-card" href="<?php echo $sanitizer->entities($cardUrl); ?>" aria-label="<?php echo $sanitizer->entities((string) $card['title']); ?>">
							<?php else: ?>
								<article class="hot-tour-card">
							<?php endif; ?>
								<div class="hot-tour-image"<?php echo $backgroundStyle; ?>></div>
								<div class="hot-tour-body">
									<h3 class="hot-tour-title"><?php echo $sanitizer->entities($card['title']); ?></h3>
									<div class="hot-tour-region"><?php echo $sanitizer->entities($card['region']); ?></div>
									<div class="hot-tour-footer">
										<span class="hot-tour-price"><?php echo $sanitizer->entities($card['price']); ?></span>
									</div>
								</div>
							<?php if ($isCardLink): ?>
								</a>
							<?php else: ?>
								</article>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
			<div class="hot-tours-footer">
				<button class="hot-tours-more-btn" type="button">
					<span>Показать всё</span>
				</button>
			</div>
		</div>
	</section>

	<section class="section section--forum">
		<div class="container">
			<div class="forum-card">
				<div class="forum-card-inner">
					<h2 class="forum-title">Форум СКФО</h2>
					<p class="forum-subtitle">
						Делимся опытом и помогаем<br />
						друг другу планировать поездки
					</p>
					<a class="forum-button" href="<?php echo $forumExternalUrl; ?>" target="_blank" rel="noopener noreferrer">Присоединиться</a>
				</div>
				<img class="forum-image" src="<?php echo $config->urls->templates; ?>assets/image1.png" alt="Форум СКФО" />
			</div>
		</div>
	</section>
</div>
