<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

/**
 * Idempotent content seed for the three group tours from etnomirkavkaza.ru.
 */
$skfoSeedGroupTours = static function(ProcessWire $wire): void {
	$seedVersion = 'SKFO_GROUP_TOURS_SEED_V1';
	$pages = $wire->pages;
	$users = $wire->users;
	$log = $wire->log;

	$tourTemplate = $wire->templates->get('tour');
	$tourParent = $pages->get('include=all, path=/tour/');
	if(!$tourTemplate instanceof Template || !$tourTemplate->id || !$tourParent instanceof Page || !$tourParent->id) return;

	$assetDir = __DIR__ . '/templates/assets/seed-group-tours';

	$tours = [
		[
			'slug' => 'semeynyy-tur-po-dagestanu',
			'title' => 'Семейный тур по Дагестану',
			'image' => 'family-dagestan.png',
			'description' => "Семидневное семейное путешествие по Дагестану: бархан Сарыкум, Сулакский каньон, горные водохранилища, старинные аулы, Дербент и отдых у моря.\n\nНа странице источника указаны тарифы: эконом - 56 700 ₽, стандарт - 67 000 ₽, премиум - 87 100 ₽.",
			'price' => '56 700 ₽',
			'duration' => '7 дней / 6 ночей',
			'group' => 'семейная группа',
			'age' => '0+',
			'dates' => '1-7 июня 2026, другие даты по запросу',
			'places' => 'Сулакский каньон, Бархан Сарыкум, Ирганайское водохранилище, Карадахская теснина, Гоор, Экраноплан Лунь, Чиркейское водохранилище, Нарын-Кала, Дербент, Датунский храм, Гоцатлинское водохранилище',
			'days' => [
				['title' => 'День 1. Горные водоёмы и каньоны', 'description' => 'Встреча гостей, переезд к бархану Сарыкум, обед на турбазе, Сулакский каньон, катание на катерах по Чиркейскому водохранилищу, Гимринский тоннель, Ирганайское водохранилище и заселение в гостевой дом в горах.'],
				['title' => 'День 2. Исторические места и природные чудеса', 'description' => 'Гоцатлинское водохранилище, Датунский храм XI века, старинный Гоор со сторожевыми башнями, обед с национальной кухней, Карадахская теснина и возвращение в гостевой дом.'],
				['title' => 'День 3. Дербент', 'description' => 'Переезд в Дербент, экскурсия по древнему городу, крепость Нарын-Кала, прогулка по магалам, южнодагестанская кухня, местный базар, экраноплан Лунь и трансфер в Инчхе.'],
				['title' => 'Дни 4-6. Отдых у моря', 'description' => 'Три ночи отдыха у моря после активной экскурсионной части маршрута.'],
				['title' => 'День 7. Махачкала', 'description' => 'Обзорная экскурсия по столице Дагестана и трансфер в аэропорт.'],
			],
		],
		[
			'slug' => 'znakomstvo-s-dagestanom',
			'title' => 'Знакомство с Дагестаном',
			'image' => 'znakomstvo-dagestan.jpg',
			'description' => 'Короткое насыщенное путешествие по главным локациям Дагестана: бархан Сарыкум, Сулакский каньон, Чиркейское водохранилище, Гамсутль, Салтинский водопад и древний Дербент.',
			'price' => '27 000 ₽',
			'duration' => '3 дня / 2 ночи',
			'group' => '6 человек',
			'age' => '6+',
			'dates' => '4-6 июня 2026, 14-16 июня 2026, 18-20 июня 2026, другие даты по запросу',
			'places' => 'Сулакский каньон, Бархан Сарыкум, Гамсутль, Дербент, Ирганайское водохранилище, Салтинский водопад, Экраноплан Лунь, Чиркейское водохранилище',
			'days' => [
				['title' => 'День 1. Природа и первые впечатления', 'description' => 'Встреча в аэропорту или городе, бархан Сарыкум, обед на турбазе, Сулакский каньон, Чиркейское водохранилище, Гимринский тоннель, Ирганайское водохранилище и вечер в гостевом доме.'],
				['title' => 'День 2. Горы и переезд к морю', 'description' => 'Завтрак, подъём к аулу Гамсутль, Салтинский водопад, обед с национальной кухней, переезд в Дербент, заселение в отель и вечерняя прогулка.'],
				['title' => 'День 3. История и завершение путешествия', 'description' => 'Экскурсия по Дербенту, крепость Нарын-Кала, прогулка по магалам, южнодагестанский обед, базар, экраноплан Лунь и трансфер в Махачкалу.'],
			],
		],
		[
			'slug' => 'po-tropam-dagestana',
			'title' => 'По тропам Дагестана',
			'image' => 'tropami-dagestan.png',
			'description' => 'Пятидневный групповой маршрут по Дагестану: Махачкала, бархан Сарыкум, Сулакский каньон, горные водоёмы, Гоор, Карадахская теснина, Гамсутль, Салтинский водопад и Дербент.',
			'price' => '43 000 ₽',
			'duration' => '5 дней / 4 ночи',
			'group' => '6 человек',
			'age' => '6+',
			'dates' => 'Даты по запросу',
			'places' => 'Сулакский каньон, Бархан Сарыкум, Гамсутль, Дербент, Ирганайское водохранилище, Салтинский водопад, Карадахская теснина, Гоор, Экраноплан Лунь, Чиркейское водохранилище',
			'days' => [
				['title' => 'День 1. Встреча и первые впечатления', 'description' => 'Встреча в Махачкале, обзорная экскурсия по столице Дагестана, заселение в отель, приветственный ужин и знакомство с группой.'],
				['title' => 'День 2. Горные водоёмы и каньоны', 'description' => 'Бархан Сарыкум, обед на турбазе, Сулакский каньон, катание по Чиркейскому водохранилищу, Гимринский тоннель, Ирганайское водохранилище и ночёвка в горах.'],
				['title' => 'День 3. Исторические места и природные чудеса', 'description' => 'Гоцатлинское водохранилище, Датунский храм XI века, Гоор, обед с блюдами национальной кухни, Карадахская теснина и возвращение в гостевой дом.'],
				['title' => 'День 4. Горные аулы и водопады', 'description' => 'Подъём к Гамсутлю, Салтинский водопад, обед, переезд в Дербент, заселение в отель и свободное время для прогулки по городу.'],
				['title' => 'День 5. История и завершение путешествия', 'description' => 'Дербент, крепость Нарын-Кала, магалы, южнодагестанская кухня, местный базар, экраноплан Лунь и трансфер в Махачкалу.'],
			],
		],
	];

	$setText = static function(Page $target, string $fieldName, $value): void {
		if($target->hasField($fieldName)) $target->set($fieldName, $value);
	};

	$setOptionByValue = static function(Page $target, string $fieldName, string $value) use ($wire): void {
		if(!$target->hasField($fieldName)) return;
		$field = $wire->fields->get($fieldName);
		if(!$field instanceof Field || !$field->id || !method_exists($field->type, 'getOptions')) return;
		foreach($field->type->getOptions($field) as $option) {
			if((string) $option->value === $value) {
				$target->set($fieldName, (int) $option->id);
				return;
			}
		}
	};

	$setPrice = static function(Page $target, string $price): void {
		if(!$target->hasField('tour_price')) return;
		$priceValue = $target->getUnformatted('tour_price');
		if($priceValue instanceof TableRows) {
			$priceValue->removeAll();
			$row = $priceValue->makeBlankItem();
			$row->period = 'за 1 человека';
			$row->price = $price;
			$row->discount = '';
			$priceValue->add($row);
			$target->set('tour_price', $priceValue);
			return;
		}
		$target->set('tour_price', $price);
	};

	$replaceImages = static function(Page $target, string $fieldName, array $fileNames) use ($assetDir, $log): void {
		if(!$target->hasField($fieldName)) return;
		$images = $target->getUnformatted($fieldName);
		if(!$images instanceof Pageimages) return;

		$images->removeAll();
		foreach($fileNames as $fileName) {
			$path = $assetDir . '/' . $fileName;
			if(!is_file($path)) {
				$log->save('actual-cards-setup', "Group tour seed image missing: {$path}");
				continue;
			}
			$images->add($path);
		}
		$target->save($fieldName);
	};

	$originalUser = $wire->user;
	$superuser = $users->get('roles=superuser, limit=1');
	if($superuser instanceof User && $superuser->id) {
		$users->setCurrentUser($superuser);
	}

	try {
		foreach($tours as $tour) {
			$page = $pages->get('include=all, path=/tour/' . $tour['slug'] . '/');
			if(!$page instanceof Page || !$page->id) {
				$page = new Page();
				$page->template = $tourTemplate;
				$page->parent = $tourParent;
				$page->name = $tour['slug'];
			}
			if(!$page instanceof Page || (!$page->id && !($page->parent instanceof Page))) continue;

			$currentDisclaimer = $page->hasField('tour_disclaimer') ? (string) $page->getUnformatted('tour_disclaimer') : '';
			if(strpos($currentDisclaimer, $seedVersion) !== false) continue;

			$page->of(false);
			if((int) $page->status & Page::statusUnpublished) $page->removeStatus(Page::statusUnpublished);
			if((int) $page->status & Page::statusHidden) $page->removeStatus(Page::statusHidden);

			$page->title = $tour['title'];
			$setText($page, 'tour_region', 'Республика Дагестан');
			$setText($page, 'tour_description', $tour['description']);
			$setPrice($page, $tour['price']);
			$setText($page, 'tour_duration', $tour['duration']);
			$setText($page, 'tour_group', $tour['group']);
			$setText($page, 'tour_season', 'Май - Октябрь');
			$setText($page, 'tour_age', $tour['age']);
			$setText($page, 'tour_type', 'Групповой многодневный тур');
			$setText($page, 'tour_format', 'Сборная группа');
			$setText($page, 'tour_language', 'Русский');
			$setText($page, 'tour_dates', $tour['dates']);
			$setText($page, 'tour_meeting_point', 'Махачкала, аэропорт или центр города');
			$setText($page, 'tour_meals', 'Питание по программе тура');
			$setText($page, 'tour_what_to_take', 'Паспорт, удобную обувь, одежду по погоде, солнцезащитные очки, пауэрбанк и личные лекарства');
			$setText($page, 'tour_included', implode("\n", [
				'Встреча в аэропорту и трансферы по маршруту',
				'Проживание по программе тура',
				'Питание по программе тура',
				'Комфортный автомобиль с водителем',
				'Сопровождение гида на протяжении маршрута',
				'Экскурсионная программа по ключевым локациям',
				$tour['places'],
			]));
			$setText($page, 'tour_disclaimer', $seedVersion . "\nИнформация перенесена и адаптирована со страницы тура etnomirkavkaza.ru. Актуальную стоимость и наличие мест нужно подтверждать перед бронированием.");
			$setOptionByValue($page, 'tour_difficulty_level', 'easy');
			$setOptionByValue($page, 'tour_emotion_level', 'unforgettable');
			$pages->save($page);

			$replaceImages($page, 'tour_cover_image', [$tour['image']]);

			if($page->hasField('tour_days')) {
				$days = $page->get('tour_days');
				if($days instanceof RepeaterPageArray) {
					foreach($days as $existingDay) {
						$days->remove($existingDay);
					}
					$page->save('tour_days');

					foreach($tour['days'] as $daySeed) {
						$day = $days->getNew();
						$day->of(false);
						$day->tour_day_title = $daySeed['title'];
						$day->tour_day_description = $daySeed['description'];
						$day->save();
						$days->add($day);
					}
					$page->set('tour_days', $days);
					$page->save('tour_days');
				}
			}

			$log->save('actual-cards-setup', "Seeded group tour page {$page->id}: {$tour['title']}.");
		}
	} catch(\Throwable $e) {
		$log->save('errors', 'Group tours seed failed: ' . $e->getMessage());
	} finally {
		if($originalUser instanceof User && $originalUser->id) {
			$users->setCurrentUser($originalUser);
		}
	}
};

$skfoSeedGroupTours($wire);
