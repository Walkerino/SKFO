<?php namespace ProcessWire;

http_response_code(200);

$partnerSiteUrl = 'https://amirov-tour.ru/';
$partnerToursUrl = 'https://amirov-tour.ru/ekskursii';
$partnerPhone = '+7 (988) 432-21-21';
$partnerPhoneHref = 'tel:+79884322121';
$partnerEmail = 'amirov.tour@yandex.ru';
$partnerEmailHref = 'mailto:' . $partnerEmail;
$partnerAddress = 'Р.Д., г. Махачкала, ул. Магомедтагирова, д. 161 Д';
$partnerLogoUrl = $config->urls->templates . 'assets/partners/amirov-tour-logo.png';
$partnerBannerUrl = $config->urls->templates . 'assets/partners/amirov-tour-banner.png';

$partnerDirections = [
	[
		'title' => 'Однодневные экскурсии',
		'text' => 'Групповые выезды из Махачкалы и Каспийска к популярным местам Дагестана, Чечни, Ингушетии и Осетии.',
	],
	[
		'title' => 'Авторские туры',
		'text' => 'Многодневные маршруты по Дагестану с проживанием, питанием и насыщенной программой в горах.',
	],
	[
		'title' => 'Индивидуальные маршруты',
		'text' => 'Туры, составленные под интересы гостей, темп путешествия и состав группы.',
	],
	[
		'title' => 'Джип-туры',
		'text' => 'VIP-формат для компаний до 4 человек на комфортном внедорожнике.',
	],
	[
		'title' => 'Кавказ, Грузия и Армения',
		'text' => 'Маршруты по соседним республикам и странам: от Домбая и Архыза до Тбилиси, Батуми и Еревана.',
	],
	[
		'title' => 'Школьные туры',
		'text' => 'Организованные туры для школьных и студенческих групп.',
	],
];

$partnerFacts = [
	'Команда проводит туры с 2017 года.',
	'Автобусы рассчитаны до 20 человек и оборудованы для комфортной дороги.',
	'Детей можно брать на экскурсии любого возраста.',
	'Для бронирования обычно требуется предоплата 50% от стоимости тура или экскурсии.',
];
?>

<div id="content" class="amirov-page">
	<section class="amirov-hero">
		<div class="container amirov-hero-grid">
			<div class="amirov-hero-copy">
				<p class="amirov-label">Партнёр СКФО.РУ</p>
				<img class="amirov-hero-logo" src="<?php echo $sanitizer->entities($partnerLogoUrl); ?>" alt="Амиров Тур" />
				<h1 class="amirov-title">Туры и<span class="amirov-mobile-break"><br /></span> экскурсии по<span class="amirov-mobile-break"><br /></span> Дагестану<span class="amirov-mobile-break"><br /></span> и Кавказу</h1>
				<p class="amirov-lead">
					«Амиров-Тур» организует групповые, авторские и индивидуальные туры из Махачкалы и Каспийска, помогает знакомиться с природой, историей и традициями Дагестана и соседних регионов.
				</p>
				<div class="amirov-actions">
					<a class="amirov-primary-btn" href="<?php echo $sanitizer->entities($partnerToursUrl); ?>" target="_blank" rel="noopener noreferrer">Смотреть экскурсии</a>
					<a class="amirov-secondary-btn" href="<?php echo $sanitizer->entities($partnerPhoneHref); ?>"><?php echo $sanitizer->entities($partnerPhone); ?></a>
				</div>
			</div>
			<div class="amirov-hero-media">
				<img src="<?php echo $sanitizer->entities($partnerBannerUrl); ?>" alt="Амиров-Тур. Забронировать отдых" />
			</div>
		</div>
	</section>

	<section class="amirov-section">
		<div class="container">
			<div class="amirov-section-head">
				<p class="amirov-section-kicker">Направления</p>
				<h2 class="amirov-section-title">Форматы туров</h2>
			</div>
			<div class="amirov-directions-grid">
				<?php foreach ($partnerDirections as $direction): ?>
					<article class="amirov-direction-card">
						<h3><?php echo $sanitizer->entities((string) $direction['title']); ?></h3>
						<p><?php echo $sanitizer->entities((string) $direction['text']); ?></p>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="amirov-section amirov-section--details">
		<div class="container amirov-details-grid">
			<div class="amirov-details-card">
				<p class="amirov-section-kicker">Почему удобно</p>
				<h2 class="amirov-section-title">Партнёр для путешествий по региону</h2>
				<p class="amirov-details-text">
					Компания делает акцент на живом знакомстве с местами маршрута: в турах рассказывает об истории, традициях и культуре территорий, через которые проходит программа.
				</p>
				<ul class="amirov-facts-list">
					<?php foreach ($partnerFacts as $fact): ?>
						<li><?php echo $sanitizer->entities($fact); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<aside class="amirov-contact-card" aria-label="Контакты партнёра">
				<img class="amirov-contact-logo" src="<?php echo $sanitizer->entities($partnerLogoUrl); ?>" alt="" aria-hidden="true" />
				<h2>Связаться с «Амиров-Тур»</h2>
				<div class="amirov-contact-list">
					<a href="<?php echo $sanitizer->entities($partnerPhoneHref); ?>"><?php echo $sanitizer->entities($partnerPhone); ?></a>
					<a href="<?php echo $sanitizer->entities($partnerEmailHref); ?>"><?php echo $sanitizer->entities($partnerEmail); ?></a>
					<span><?php echo $sanitizer->entities($partnerAddress); ?></span>
				</div>
				<a class="amirov-site-link" href="<?php echo $sanitizer->entities($partnerSiteUrl); ?>" target="_blank" rel="noopener noreferrer">Перейти на сайт партнёра</a>
			</aside>
		</div>
	</section>
</div>
