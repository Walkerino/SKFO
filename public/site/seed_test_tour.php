<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

/**
 * Idempotent content seed for the public test tour page.
 *
 * Uploaded page files are shared runtime data and are ignored by git, so the
 * source photos live in tracked template assets and are copied into the
 * ProcessWire image fields when the seed version changes.
 */
$skfoSeedTestTour = static function(ProcessWire $wire): void {
	$seedVersion = 'SKFO_TEST_TOUR_SEED_V4';
	$pages = $wire->pages;
	$users = $wire->users;
	$log = $wire->log;

	$pageSlug = 'chetyrekhdnevnyi-tur-po-dagestanu';
	$page = $pages->get('include=all, path=/tour/' . $pageSlug . '/');
	if((!$page instanceof Page || !$page->id) && $wire->config->debug) {
		$page = $pages->get('include=all, template=tour, id=1586');
	}

	$tourTemplate = $wire->templates->get('tour');
	$tourParent = $pages->get('include=all, path=/tour/');
	if((!$page instanceof Page || !$page->id) && $tourTemplate instanceof Template && $tourTemplate->id && $tourParent instanceof Page && $tourParent->id) {
		$page = new Page();
		$page->template = $tourTemplate;
		$page->parent = $tourParent;
		$page->name = $pageSlug;
	}
	if(!$page instanceof Page || (!$page->id && !($page->parent instanceof Page)) || !$page->template || $page->template->name !== 'tour') return;

	$currentDisclaimer = $page->hasField('tour_disclaimer') ? (string) $page->getUnformatted('tour_disclaimer') : '';
	if(strpos($currentDisclaimer, $seedVersion) !== false) return;

	$originalUser = $wire->user;
	$superuser = $users->get('roles=superuser, limit=1');
	if($superuser instanceof User && $superuser->id) {
		$users->setCurrentUser($superuser);
	}

	$assetDir = __DIR__ . '/templates/assets/seed-test-tour';
	$coverImages = [
		'01-sulak-canyon.jpg',
		'02-sulak-viewpoint.jpg',
		'03-canyon-cliffs.jpg',
		'04-derbent-fortress.jpg',
		'05-caspian-coast.jpg',
		'06-mountain-road.jpg',
		'07-gunib-village.jpg',
		'08-mountain-panorama.jpg',
		'09-horse-ride.jpg',
		'10-waterfall.jpg',
		'11-guide-story.jpg',
		'12-evening-canyon.jpg',
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

	$replaceImages = static function(Page $target, string $fieldName, array $fileNames) use ($assetDir, $log): void {
		if(!$target->hasField($fieldName)) return;
		$images = $target->getUnformatted($fieldName);
		if(!$images instanceof Pageimages) return;

		$images->removeAll();
		foreach($fileNames as $fileName) {
			$path = $assetDir . '/' . $fileName;
			if(!is_file($path)) {
				$log->save('actual-cards-setup', "Test tour seed image missing: {$path}");
				continue;
			}
			$images->add($path);
		}
		$target->save($fieldName);
	};

	try {
		$page->of(false);
		if((int) $page->status & Page::statusUnpublished) $page->removeStatus(Page::statusUnpublished);
		if((int) $page->status & Page::statusHidden) $page->removeStatus(Page::statusHidden);

		$page->title = 'Четырехдневный тур по Дагестану';
		$setText($page, 'tour_region', 'Республика Дагестан');
		$setText($page, 'tour_description', "Насыщенный тестовый маршрут по Дагестану на 4 дня: Сулакский каньон, бархан Сарыкум, Дербент, экраноплан Лунь, Гуниб, аулы и горные панорамы.\n\nПрограмма собрана так, чтобы на странице тура были заполнены все основные блоки: галерея, расписание по дням, условия, питание, встреча, гид, скидка и юридическая информация.");
		$setText($page, 'tour_duration', '4 дня / 3 ночи');
		$setText($page, 'tour_group', '4-12 человек');
		$setText($page, 'tour_season', 'Май - Октябрь');
		$setText($page, 'tour_age', '12+');
		$setText($page, 'tour_included', implode("\n", [
			'Трансфер по маршруту на комфортном минивэне или внедорожнике',
			'Проживание 3 ночи в гостевых домах и проверенных отелях',
			'Трехразовое питание с блюдами дагестанской кухни',
			'Прогулка на катере по Сулакскому каньону',
			'Конная прогулка в горной локации',
			'Входные билеты в основные объекты программы',
			'Сопровождение аттестованного гида',
			'Помощь с подбором авиабилетов и подготовкой к туру',
			'Фотопаузы на видовых точках',
			'Аптечка и базовое туристическое снаряжение группы',
		]));
		$setOptionByValue($page, 'tour_difficulty_level', 'medium');
		$setOptionByValue($page, 'tour_emotion_level', 'unforgettable');
		$guidePage = $pages->get('include=all, path=/guides/seed26-guide-04/');
		if($guidePage instanceof Page && $guidePage->id) {
			$setText($page, 'guide', $guidePage);
		}
		$setText($page, 'tour_type', 'Экскурсионный тур, джиппинг, легкий треккинг');
		$setText($page, 'tour_format', 'Сборная группа или индивидуально');
		$setText($page, 'tour_language', 'Русский');
		$setText($page, 'tour_dates', '16-19 мая, 6-9 июня, 20-23 июня, далее по запросу');
		$setText($page, 'tour_meeting_point', 'Махачкала, аэропорт Уйташ или центр города, 08:00');
		$setText($page, 'tour_meals', 'Завтраки, обеды и ужины включены; доступны вегетарианские опции по запросу');
		$setText($page, 'tour_what_to_take', 'Паспорт, удобную обувь, ветровку, солнцезащитные очки, пауэрбанк и личные лекарства');
		$setText($page, 'tour_seats_left', 5);
		$setText($page, 'tour_is_hot', 1);
		$setText($page, 'tour_discount_percent', 12);
		$setText($page, 'tour_discount_deadline', strtotime('2026-06-15 23:59:00'));
		$setText($page, 'tour_guide_name', 'Марина Кочкарова');
		$setText($page, 'tour_guide_experience_years', 9);
		$setText($page, 'tour_guide_tourists_count', 1240);
		$setText($page, 'tour_guide_attestation_number', '05-ГИД-2026-1842');
		$setText($page, 'tour_guide_registry_url', 'https://tourism.gov.ru/');
		$setText($page, 'tour_disclaimer', $seedVersion . "\nТестовая карточка тура заполнена демонстрационными данными для проверки интерфейса. Информация не является публичной офертой.");
		$pages->save($page);

		if($page->hasField('tour_price')) {
			$priceValue = $page->getUnformatted('tour_price');
			if($priceValue instanceof TableRows) {
				$priceValue->removeAll();
				foreach([
					['period' => 'за 1 человека в группе 8-12 человек', 'price' => '42 900 ₽', 'discount' => '37 752 ₽ до 15 июня'],
					['period' => 'за 1 человека в мини-группе 4-7 человек', 'price' => '54 900 ₽', 'discount' => '48 312 ₽ до 15 июня'],
					['period' => 'индивидуальный тур для 2-3 человек', 'price' => '78 000 ₽', 'discount' => 'по запросу'],
				] as $priceRow) {
					$row = $priceValue->makeBlankItem();
					$row->period = $priceRow['period'];
					$row->price = $priceRow['price'];
					$row->discount = $priceRow['discount'];
					$priceValue->add($row);
				}
				$page->set('tour_price', $priceValue);
			} else {
				$page->set('tour_price', '42 900 ₽');
			}
			$pages->save($page, ['quiet' => true]);
		}

		$replaceImages($page, 'tour_cover_image', $coverImages);
		$replaceImages($page, 'tour_guide_photo', ['guide-marina-kochkarova.jpg']);

		if($page->hasField('tour_days')) {
			$days = $page->get('tour_days');
			if($days instanceof RepeaterPageArray) {
				foreach($days as $existingDay) {
					$days->remove($existingDay);
				}
				$page->save('tour_days');

				$daySeeds = [
					[
						'title' => 'День 1. Махачкала, бархан Сарыкум и Сулакский каньон',
						'description' => 'Встречаемся утром, знакомимся с группой и едем к бархану Сарыкум. После обеда смотрим каньон с главных площадок, выходим на катере и ужинаем в гостевом доме.',
						'images' => ['01-sulak-canyon.jpg', '02-sulak-viewpoint.jpg', '03-canyon-cliffs.jpg'],
					],
					[
						'title' => 'День 2. Дербент, крепость Нарын-Кала и Каспий',
						'description' => 'Гуляем по старому Дербенту, поднимаемся к крепости, заезжаем к экраноплану Лунь и встречаем вечер у моря. В программе дегустация локальной кухни.',
						'images' => ['04-derbent-fortress.jpg', '05-caspian-coast.jpg'],
					],
					[
						'title' => 'День 3. Гуниб, аулы и горные дороги',
						'description' => 'Переезжаем в горный Дагестан: видовые серпантины, старые аулы, мастерская ремесленников и неспешная конная прогулка. Ночуем в горах.',
						'images' => ['06-mountain-road.jpg', '07-gunib-village.jpg', '09-horse-ride.jpg'],
					],
					[
						'title' => 'День 4. Водопады, панорамы и возвращение',
						'description' => 'Финальный день оставляем для водопадов, короткого трека и панорамных остановок. Возвращаемся в Махачкалу к вечерним рейсам.',
						'images' => ['08-mountain-panorama.jpg', '10-waterfall.jpg', '12-evening-canyon.jpg'],
					],
				];

				foreach($daySeeds as $daySeed) {
					$day = $days->getNew();
					$day->of(false);
					$day->tour_day_title = $daySeed['title'];
					$day->tour_day_description = $daySeed['description'];
					$day->save();
					$dayImages = $day->getUnformatted('tour_day_images');
					if($dayImages instanceof Pageimages) {
						foreach($daySeed['images'] as $fileName) {
							$path = $assetDir . '/' . $fileName;
							if(is_file($path)) $dayImages->add($path);
						}
						$day->save('tour_day_images');
					}
					$days->add($day);
				}
				$page->set('tour_days', $days);
				$page->save('tour_days');
			}
		}

		$log->save('actual-cards-setup', "Seeded test tour page {$page->id} with full demo content.");
	} catch(\Throwable $e) {
		$log->save('errors', 'Test tour seed failed: ' . $e->getMessage());
	} finally {
		if($originalUser instanceof User && $originalUser->id) {
			$users->setCurrentUser($originalUser);
		}
	}
};

$skfoSeedTestTour($wire);
