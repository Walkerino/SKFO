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
		<div class="container">
			<div class="places-banner">
				<div class="places-banner-header">
					<h2 class="section-title section-title--places">Что насчёт Дагестана ?</h2>
					<div class="places-banner-actions">
						<button class="circle-btn circle-btn--prev" type="button" aria-label="Предыдущие места"></button>
						<button class="circle-btn circle-btn--next" type="button" aria-label="Следующие места"></button>
					</div>
				</div>
				<div class="places-grid">
					<article class="place-card">
						<div class="place-card-image place-card-image--1"></div>
						<h3 class="place-card-title">Сулакский каньон</h3>
					</article>
					<article class="place-card">
						<div class="place-card-image place-card-image--2"></div>
						<h3 class="place-card-title">Гамсутль</h3>
					</article>
					<article class="place-card">
						<div class="place-card-image place-card-image--3"></div>
						<h3 class="place-card-title">Экраноплан “Лунь”</h3>
					</article>
					<article class="place-card">
						<div class="place-card-image place-card-image--4"></div>
						<h3 class="place-card-title">Гуллинский мост</h3>
					</article>
					<article class="place-card">
						<div class="place-card-image place-card-image--5"></div>
						<h3 class="place-card-title">Беседка Имама Шамиля</h3>
					</article>
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
		<div class="container actual-grid">
			<article class="actual-card">
				<div class="actual-card-image actual-card-image--1"></div>
				<div class="actual-card-body">
					<h3 class="actual-card-title">Джейрахское ущелье</h3>
					<p class="actual-card-text">
						Гордость Ингушетии! Территория ущелья входит в состав
						Джейрахско-Ассинского заповедника.
					</p>
					<div class="actual-card-footer">
						<span class="tag-location">Ингушетия</span>
					</div>
				</div>
			</article>
			<article class="actual-card">
				<div class="actual-card-image actual-card-image--2"></div>
				<div class="actual-card-body">
					<h3 class="actual-card-title">Озеро Кезеной-Ам</h3>
					<p class="actual-card-text">
						Самое большое высокогорное и невероятной красоты озеро на Северном
						Кавказе.
					</p>
					<div class="actual-card-footer">
						<span class="tag-location">Чеченская Республика</span>
					</div>
				</div>
			</article>
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
				</div>
			</div>
		</div>
	</section>

	<section class="section section--hot-tours">
		<div class="container">
			<div class="hot-tours-header">
				<h2 class="section-title">Чем заняться этим летом</h2>
				<div class="hot-tours-actions">
					<button class="circle-btn" type="button"></button>
					<button class="circle-btn circle-btn--accent" type="button"></button>
				</div>
			</div>
			<div class="hot-tours-grid">
				<article class="hot-tour-card">
					<div class="hot-tour-image hot-tour-image--1"></div>
					<div class="hot-tour-body">
						<h3 class="hot-tour-title">
							Посетить<br />
							Аргунское ущелье
						</h3>
						<div class="hot-tour-region">Чеченская Республика</div>
						<div class="hot-tour-footer">
							<span class="hot-tour-price">от 15 000₽</span>
						</div>
					</div>
				</article>
				<article class="hot-tour-card">
					<div class="hot-tour-image hot-tour-image--2"></div>
					<div class="hot-tour-body">
						<h3 class="hot-tour-title">
							Взобраться<br />
							на гору Эльбрус
						</h3>
						<div class="hot-tour-region">Кабардино-Балкарская Республика</div>
						<div class="hot-tour-footer">
							<span class="hot-tour-price">от 15 000₽</span>
						</div>
					</div>
				</article>
				<article class="hot-tour-card">
					<div class="hot-tour-image hot-tour-image--3"></div>
					<div class="hot-tour-body">
						<h3 class="hot-tour-title">
							Расслабиться<br />
							в Суворовских термах
						</h3>
						<div class="hot-tour-region">Ставропольский край</div>
						<div class="hot-tour-footer">
							<span class="hot-tour-price">от 15 000₽</span>
						</div>
					</div>
				</article>
				<article class="hot-tour-card">
					<div class="hot-tour-image hot-tour-image--4"></div>
					<div class="hot-tour-body">
						<h3 class="hot-tour-title">Умчать в Старый Кахиб</h3>
						<div class="hot-tour-region">Республика Дагестан</div>
						<div class="hot-tour-footer">
							<span class="hot-tour-price">от 15 000₽</span>
						</div>
					</div>
				</article>
				<article class="hot-tour-card">
					<div class="hot-tour-image hot-tour-image--5"></div>
					<div class="hot-tour-body">
						<h3 class="hot-tour-title">
							Заглянуть в Замок<br />
							на воде Шато Эркен
						</h3>
						<div class="hot-tour-region">Кабардино-Балкарская Республика</div>
						<div class="hot-tour-footer">
							<span class="hot-tour-price">от 15 000₽</span>
						</div>
					</div>
				</article>
				<button class="hot-tour-card hot-tour-card--more" type="button">
					<span class="hot-tour-more-text">Показать всё</span>
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
