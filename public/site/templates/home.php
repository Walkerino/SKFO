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
						<img src="<?php echo $config->urls->templates; ?>assets/icons/when.svg" alt="" aria-hidden="true" />
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
</div>
