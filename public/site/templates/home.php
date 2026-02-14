<?php namespace ProcessWire;

// Template file for “home” template used by the homepage
// ------------------------------------------------------
// The #content div in this file will replace the #content div in _main.php
// when the Markup Regions feature is enabled, as it is by default. 
// You can also append to (or prepend to) the #content div, and much more. 
// See the Markup Regions documentation:
// https://processwire.com/docs/front-end/output/markup-regions/

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
					<button class="hero-tab is-active" type="button" role="tab" aria-selected="true">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/tour.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Туры</span>
					</button>
					<button class="hero-tab" type="button" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/hotel.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Отели</span>
					</button>
					<button class="hero-tab" type="button" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/reviews.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Отзывы</span>
					</button>
					<button class="hero-tab" type="button" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Регионы</span>
					</button>
					<button class="hero-tab" type="button" role="tab" aria-selected="false">
						<img src="<?php echo $config->urls->templates; ?>assets/icons/journal.svg" alt="" aria-hidden="true" />
						<span class="hero-tab-text">Статьи</span>
					</button>
				</div>
				<button class="hero-tab hero-tab-forum" type="button" aria-label="Форум">
					<img src="<?php echo $config->urls->templates; ?>assets/icons/forum.svg" alt="" aria-hidden="true" />
					<span>Форум</span>
					<img class="hero-tab-external" src="<?php echo $config->urls->templates; ?>assets/icons/external_site.svg" alt="" aria-hidden="true" />
				</button>
			</div>
			<form class="hero-search" action="#" method="get">
				<div class="hero-search-fields">
					<label class="hero-field">
						<span class="sr-only">Куда</span>
						<input type="text" name="where" placeholder="Куда" list="city-list" />
						<img src="<?php echo $config->urls->templates; ?>assets/icons/where.svg" alt="" aria-hidden="true" />
					</label>
					<label class="hero-field">
						<span class="sr-only">Когда</span>
						<input type="date" name="when" placeholder="Когда" />
						<!-- <img src="<?php echo $config->urls->templates; ?>assets/icons/when.svg" alt="" aria-hidden="true" /> -->
					</label>
					<label class="hero-field hero-field-people">
						<span class="sr-only">С кем</span>
						<input type="text" name="with" placeholder="С кем" readonly />
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
					<option value="Москва"></option>
					<option value="Санкт-Петербург"></option>
					<option value="Сочи"></option>
					<option value="Кисловодск"></option>
					<option value="Пятигорск"></option>
					<option value="Владикавказ"></option>
					<option value="Грозный"></option>
					<option value="Махачкала"></option>
					<option value="Нальчик"></option>
					<option value="Ессентуки"></option>
				</datalist>
				<button class="search-btn" type="submit">Найти туры</button>
			</form>
		</div>
	</section>

		<section class="section section--places">
			<?php
			$dagestanPlacesCards = [];

			if ($page->hasField('dagestan_places_cards') && $page->dagestan_places_cards->count()) {
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

			if ($page->hasField('actual_cards') && $page->actual_cards->count()) {
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
		<div class="container">
			<div class="journal-card">
				<div class="journal-card-header">
					<h2 class="journal-title">Статьи СКФО</h2>
					<p class="journal-subtitle">
						Читайте и планируйте поездки </br> по гайдам, маршрутам, советам
					</p>
					<button class="journal-button" type="button">Выбрать статью</button>
				</div>
				<div class="journal-articles" aria-live="polite">
					<article class="journal-article">
						<div class="journal-article-image journal-article-image--1"></div>
						<div class="journal-article-content">
							<div class="journal-article-meta">
								<span class="journal-article-date">22 Дек 2025</span>
							</div>
							<h3 class="journal-article-title">
								Как подготовиться к первому путешествию в Дагестан
							</h3>
							<span class="journal-article-tag">Советы туристам</span>
						</div>
					</article>

					<article class="journal-article-second"></article>
				</div>
			</div>
		</div>
	</section>

	<section class="section section--hot-tours">
		<?php
		$hotToursCards = [];

		if ($page->hasField('hot_tours_cards') && $page->hot_tours_cards->count()) {
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
				];
			}
		}

		if (!count($hotToursCards)) {
			$hotToursCards = [
				[
					'title' => 'Посетить Аргунское ущелье',
					'region' => 'Чеченская Республика',
					'price' => 'от 15 000₽',
					'image' => '',
				],
				[
					'title' => 'Взобраться на гору Эльбрус',
					'region' => 'Кабардино-Балкарская Республика',
					'price' => 'от 15 000₽',
					'image' => '',
				],
				[
					'title' => 'Расслабиться в Суворовских термах',
					'region' => 'Ставропольский край',
					'price' => 'от 15 000₽',
					'image' => '',
				],
				[
					'title' => 'Умчать в Старый Кахиб',
					'region' => 'Республика Дагестан',
					'price' => 'от 15 000₽',
					'image' => '',
				],
				[
					'title' => 'Заглянуть в Замок на воде Шато Эркен',
					'region' => 'Кабардино-Балкарская Республика',
					'price' => 'от 15 000₽',
					'image' => '',
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
							if (!empty($card['image'])) {
								$image = htmlspecialchars($card['image'], ENT_QUOTES, 'UTF-8');
								$backgroundStyle = " style=\"background-image: linear-gradient(135deg, rgba(17, 24, 39, 0.2), rgba(17, 24, 39, 0.1)), url('{$image}');\"";
							}
							?>
							<article class="hot-tour-card">
								<div class="hot-tour-image"<?php echo $backgroundStyle; ?>></div>
								<div class="hot-tour-body">
									<h3 class="hot-tour-title"><?php echo $sanitizer->entities($card['title']); ?></h3>
									<div class="hot-tour-region"><?php echo $sanitizer->entities($card['region']); ?></div>
									<div class="hot-tour-footer">
										<span class="hot-tour-price"><?php echo $sanitizer->entities($card['price']); ?></span>
									</div>
								</div>
							</article>
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
					<button class="forum-button" type="button">Присоединиться</button>
				</div>
				<img class="forum-image" src="<?php echo $config->urls->templates; ?>assets/image1.png" alt="Форум СКФО" />
			</div>
		</div>
	</section>
</div>
