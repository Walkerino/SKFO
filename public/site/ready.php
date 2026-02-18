<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

/** @var ProcessWire $wire */

/**
 * ProcessWire Bootstrap API Ready
 * ===============================
 * This ready.php file is called during ProcessWire bootstrap initialization process.
 * This occurs after the current page has been determined and the API is fully ready
 * to use, but before the current page has started rendering. This file receives a
 * copy of all ProcessWire API variables.
 *
 */

/** @var User $user */
$user = wire('user');
if(!$user || !$user->isSuperuser()) return;

/** @var Fields $fields */
$fields = wire('fields');
/** @var Modules $modules */
$modules = wire('modules');
/** @var Templates $templates */
$templates = wire('templates');
/** @var Log $log */
$log = wire('log');
$toLower = static function(string $value): string {
	$value = trim($value);
	return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
};

/**
 * Create missing field with given type/settings.
 *
 * @param string $name
 * @param string $type
 * @param string $label
 * @param array $settings
 * @return Field|null
 */
$ensureField = function(string $name, string $type, string $label, array $settings = []) use ($fields, $modules, $log) {
	$existing = $fields->get($name);
	if($existing && $existing->id) {
		return $existing;
	}

	$fieldtype = $modules->get($type);
	if(!$fieldtype) {
		$log->save('actual-cards-setup', "Fieldtype '$type' not found. Cannot create field '$name'.");
		return null;
	}

	$field = new Field();
	$field->name = $name;
	$field->label = $label;
	$field->type = $fieldtype;

	foreach($settings as $key => $value) {
		$field->set($key, $value);
	}

	$fields->save($field);
	$log->save('actual-cards-setup', "Created field '$name'.");
	return $field;
};

$titleField = $ensureField('card_title', 'FieldtypeText', 'Заголовок карточки');
$textField = $ensureField('card_text', 'FieldtypeTextarea', 'Текст карточки');
$regionField = $ensureField('card_region', 'FieldtypeText', 'Регион');
$imageField = $ensureField('card_image', 'FieldtypeImage', 'Фото карточки', [
	'maxFiles' => 1,
	'extensions' => 'jpg jpeg png gif webp',
]);
$actualCardsField = $ensureField('actual_cards', 'FieldtypeRepeater', 'Актуальные карточки');

$hotTourTitleField = $ensureField('hot_tour_title', 'FieldtypeText', 'Заголовок тура');
$hotTourRegionField = $ensureField('hot_tour_region', 'FieldtypeText', 'Регион тура');
$hotTourPriceField = $ensureField('hot_tour_price', 'FieldtypeText', 'Цена тура');
$hotTourImageField = $ensureField('hot_tour_image', 'FieldtypeImage', 'Фото тура', [
	'maxFiles' => 1,
	'extensions' => 'jpg jpeg png gif webp',
]);
$hotToursCardsField = $ensureField('hot_tours_cards', 'FieldtypeRepeater', 'Карточки блока "Чем заняться этим летом?"');

$dagestanPlaceTitleField = $ensureField('dagestan_place_title', 'FieldtypeText', 'Заголовок места');
$dagestanPlaceImageField = $ensureField('dagestan_place_image', 'FieldtypeImage', 'Фото места', [
	'maxFiles' => 1,
	'extensions' => 'jpg jpeg png gif webp',
]);
$dagestanPlacesCardsField = $ensureField('dagestan_places_cards', 'FieldtypeRepeater', 'Карточки блока "Что насчёт Дагестана?"');

$regionCardTitleField = $ensureField('region_card_title', 'FieldtypeText', 'Заголовок карточки региона');
$regionCardDescriptionField = $ensureField('region_card_description', 'FieldtypeTextarea', 'Описание карточки региона');
$regionCardImageField = $ensureField('region_card_image', 'FieldtypeImage', 'Фото карточки региона', [
	'maxFiles' => 1,
	'extensions' => 'jpg jpeg png gif webp',
]);
$regionCardsField = $ensureField('region_cards', 'FieldtypeRepeater', 'Карточки страницы "Регионы"');

$tourRegionField = $ensureField('tour_region', 'FieldtypeText', 'Регион тура');
$tourDescriptionField = $ensureField('tour_description', 'FieldtypeTextarea', 'Описание тура');
$tourPriceField = $ensureField('tour_price', 'FieldtypeText', 'Цена тура');
$tourDurationField = $ensureField('tour_duration', 'FieldtypeText', 'Длительность тура');
$tourGroupField = $ensureField('tour_group', 'FieldtypeText', 'Группа тура');
$tourSeasonField = $ensureField('tour_season', 'FieldtypeText', 'Сезон тура');
$tourDifficultyField = $ensureField('tour_difficulty', 'FieldtypeText', 'Сложность тура');
$tourDifficultyLevelField = $ensureField('tour_difficulty_level', 'FieldtypeOptions', 'Сложность тура (список)', [
	'inputfieldClass' => 'InputfieldSelect',
]);
$tourAgeField = $ensureField('tour_age', 'FieldtypeText', 'Возраст тура');
$tourIncludedField = $ensureField('tour_included', 'FieldtypeTextarea', 'Что включено (по строкам)');
$tourIncludedItemTextField = $ensureField('tour_included_item_text', 'FieldtypeText', 'Что включено: пункт');
$tourIncludedItemsField = $ensureField('tour_included_items', 'FieldtypeRepeater', 'Что включено: тезисы');
$tourCoverImageField = $ensureField('tour_cover_image', 'FieldtypeImage', 'Обложка тура', [
	'maxFiles' => 1,
	'extensions' => 'jpg jpeg png gif webp',
]);
$tourDayTitleField = $ensureField('tour_day_title', 'FieldtypeText', 'День тура: заголовок');
$tourDayDescriptionField = $ensureField('tour_day_description', 'FieldtypeTextarea', 'День тура: описание');
$tourDayImagesField = $ensureField('tour_day_images', 'FieldtypeImage', 'День тура: изображения', [
	'extensions' => 'jpg jpeg png gif webp',
]);
$tourDaysField = $ensureField('tour_days', 'FieldtypeRepeater', 'Информация по дням');

if($imageField && $imageField->id) {
	$imageChanged = false;

	if((int) $imageField->get('maxFiles') !== 1) {
		$imageField->set('maxFiles', 1);
		$imageChanged = true;
	}

	$extensions = trim((string) $imageField->get('extensions'));
	if($extensions === '') {
		$imageField->set('extensions', 'jpg jpeg png gif webp');
		$imageChanged = true;
	}

	if($imageChanged) {
		$fields->save($imageField);
		$log->save('actual-cards-setup', "Updated field 'card_image' settings.");
	}
}

if($hotTourImageField && $hotTourImageField->id) {
	$hotTourImageChanged = false;

	if((int) $hotTourImageField->get('maxFiles') !== 1) {
		$hotTourImageField->set('maxFiles', 1);
		$hotTourImageChanged = true;
	}

	$hotTourExtensions = trim((string) $hotTourImageField->get('extensions'));
	if($hotTourExtensions === '') {
		$hotTourImageField->set('extensions', 'jpg jpeg png gif webp');
		$hotTourImageChanged = true;
	}

	if($hotTourImageChanged) {
		$fields->save($hotTourImageField);
		$log->save('actual-cards-setup', "Updated field 'hot_tour_image' settings.");
	}
}

if($dagestanPlaceImageField && $dagestanPlaceImageField->id) {
	$dagestanPlaceImageChanged = false;

	if((int) $dagestanPlaceImageField->get('maxFiles') !== 1) {
		$dagestanPlaceImageField->set('maxFiles', 1);
		$dagestanPlaceImageChanged = true;
	}

	$dagestanExtensions = trim((string) $dagestanPlaceImageField->get('extensions'));
	if($dagestanExtensions === '') {
		$dagestanPlaceImageField->set('extensions', 'jpg jpeg png gif webp');
		$dagestanPlaceImageChanged = true;
	}

	if($dagestanPlaceImageChanged) {
		$fields->save($dagestanPlaceImageField);
		$log->save('actual-cards-setup', "Updated field 'dagestan_place_image' settings.");
	}
}

if($regionCardImageField && $regionCardImageField->id) {
	$regionCardImageChanged = false;

	if((int) $regionCardImageField->get('maxFiles') !== 1) {
		$regionCardImageField->set('maxFiles', 1);
		$regionCardImageChanged = true;
	}

	$regionCardExtensions = trim((string) $regionCardImageField->get('extensions'));
	if($regionCardExtensions === '') {
		$regionCardImageField->set('extensions', 'jpg jpeg png gif webp');
		$regionCardImageChanged = true;
	}

	if($regionCardImageChanged) {
		$fields->save($regionCardImageField);
		$log->save('actual-cards-setup', "Updated field 'region_card_image' settings.");
	}
}

if($tourCoverImageField && $tourCoverImageField->id) {
	$tourCoverChanged = false;

	if((int) $tourCoverImageField->get('maxFiles') !== 1) {
		$tourCoverImageField->set('maxFiles', 1);
		$tourCoverChanged = true;
	}

	$tourCoverExtensions = trim((string) $tourCoverImageField->get('extensions'));
	if($tourCoverExtensions === '') {
		$tourCoverImageField->set('extensions', 'jpg jpeg png gif webp');
		$tourCoverChanged = true;
	}

	if($tourCoverChanged) {
		$fields->save($tourCoverImageField);
		$log->save('actual-cards-setup', "Updated field 'tour_cover_image' settings.");
	}
}

if($tourDayImagesField && $tourDayImagesField->id) {
	$tourDayImagesChanged = false;
	$tourDayImagesExtensions = trim((string) $tourDayImagesField->get('extensions'));
	if($tourDayImagesExtensions === '') {
		$tourDayImagesField->set('extensions', 'jpg jpeg png gif webp');
		$tourDayImagesChanged = true;
	}

	if($tourDayImagesChanged) {
		$fields->save($tourDayImagesField);
		$log->save('actual-cards-setup', "Updated field 'tour_day_images' settings.");
	}
}

if(
	$tourDifficultyLevelField &&
	$tourDifficultyLevelField->id &&
	$tourDifficultyLevelField->type instanceof FieldtypeOptions
) {
	$difficultyOptions = $tourDifficultyLevelField->type->getOptions($tourDifficultyLevelField);
	$normalizedCurrentTitles = [];
	foreach($difficultyOptions as $option) {
		$normalizedCurrentTitles[] = $toLower((string) $option->title);
	}

	$normalizedExpectedTitles = ['базовая', 'средняя', 'высокая'];
	if($normalizedCurrentTitles !== $normalizedExpectedTitles) {
		$newOptions = wire(new SelectableOptionArray());
		$newOptions->setField($tourDifficultyLevelField);

		$items = [
			['value' => 'basic', 'title' => 'Базовая'],
			['value' => 'medium', 'title' => 'Средняя'],
			['value' => 'high', 'title' => 'Высокая'],
		];

		foreach($items as $sort => $item) {
			$option = wire(new SelectableOption());
			$option->set('sort', $sort);
			$option->set('value', $item['value']);
			$option->set('title', $item['title']);
			$newOptions->add($option);
		}

		$tourDifficultyLevelField->type->setOptions($tourDifficultyLevelField, $newOptions);
		$log->save('actual-cards-setup', "Updated options for field 'tour_difficulty_level'.");
	}

	if($tourDifficultyField && $tourDifficultyField->id) {
		$difficultyOptions = $tourDifficultyLevelField->type->getOptions($tourDifficultyLevelField);
		$difficultyOptionIds = [];
		foreach($difficultyOptions as $option) {
			$key = $toLower((string) $option->title);
			if($key !== '') {
				$difficultyOptionIds[$key] = (int) $option->id;
			}
		}

		$tourPages = wire('pages')->find('template=tour, include=all');
		foreach($tourPages as $tourPage) {
			$currentValue = $tourPage->getUnformatted('tour_difficulty_level');
			$hasSelectedDifficulty = $currentValue instanceof SelectableOptionArray && $currentValue->count();
			if($hasSelectedDifficulty) continue;

			$legacyValue = trim((string) $tourPage->getUnformatted('tour_difficulty'));
			if($legacyValue === '') continue;

			$legacyKey = $toLower($legacyValue);
			if(!isset($difficultyOptionIds[$legacyKey])) continue;

			$tourPage->setAndSave('tour_difficulty_level', $difficultyOptionIds[$legacyKey]);
		}
	}
}

if(
	(!$actualCardsField || !$actualCardsField->id) &&
	(!$hotToursCardsField || !$hotToursCardsField->id) &&
	(!$dagestanPlacesCardsField || !$dagestanPlacesCardsField->id) &&
	(!$regionCardsField || !$regionCardsField->id) &&
	(!$tourDaysField || !$tourDaysField->id) &&
	(!$tourIncludedItemsField || !$tourIncludedItemsField->id)
) return;

/** @var FieldtypeRepeater|null $repeaterType */
$repeaterType = $modules->get('FieldtypeRepeater');
if(!$repeaterType) {
	$log->save('actual-cards-setup', "FieldtypeRepeater module is not available.");
	return;
}

if($actualCardsField && $actualCardsField->id) {
	$repeaterTemplate = $repeaterType->_getRepeaterTemplate($actualCardsField);
}
if(isset($repeaterTemplate) && $repeaterTemplate && $repeaterTemplate->id) {
	$fieldgroup = $repeaterTemplate->fieldgroup;
	$repeaterFields = [$titleField, $textField, $regionField, $imageField];
	$changed = false;

	foreach($repeaterFields as $field) {
		if(!$field || !$field->id) continue;
		if(!$fieldgroup->has($field)) {
			$fieldgroup->add($field);
			$changed = true;
		}
	}

	if($changed) {
		$fieldgroup->save();
		$log->save('actual-cards-setup', "Updated repeater fieldgroup '{$fieldgroup->name}'.");
	}
}

if($hotToursCardsField && $hotToursCardsField->id) {
	$hotToursRepeaterTemplate = $repeaterType->_getRepeaterTemplate($hotToursCardsField);
	if($hotToursRepeaterTemplate && $hotToursRepeaterTemplate->id) {
		$hotToursFieldgroup = $hotToursRepeaterTemplate->fieldgroup;
		$hotToursRepeaterFields = [$hotTourTitleField, $hotTourRegionField, $hotTourPriceField, $hotTourImageField];
		$hotToursChanged = false;

		foreach($hotToursRepeaterFields as $field) {
			if(!$field || !$field->id) continue;
			if(!$hotToursFieldgroup->has($field)) {
				$hotToursFieldgroup->add($field);
				$hotToursChanged = true;
			}
		}

		if($hotToursChanged) {
			$hotToursFieldgroup->save();
			$log->save('actual-cards-setup', "Updated repeater fieldgroup '{$hotToursFieldgroup->name}'.");
		}
	}
}

if($dagestanPlacesCardsField && $dagestanPlacesCardsField->id) {
	$dagestanRepeaterTemplate = $repeaterType->_getRepeaterTemplate($dagestanPlacesCardsField);
	if($dagestanRepeaterTemplate && $dagestanRepeaterTemplate->id) {
		$dagestanFieldgroup = $dagestanRepeaterTemplate->fieldgroup;
		$dagestanRepeaterFields = [$dagestanPlaceTitleField, $dagestanPlaceImageField];
		$dagestanChanged = false;

		foreach($dagestanRepeaterFields as $field) {
			if(!$field || !$field->id) continue;
			if(!$dagestanFieldgroup->has($field)) {
				$dagestanFieldgroup->add($field);
				$dagestanChanged = true;
			}
		}

		if($dagestanChanged) {
			$dagestanFieldgroup->save();
			$log->save('actual-cards-setup', "Updated repeater fieldgroup '{$dagestanFieldgroup->name}'.");
		}
	}
}

if($regionCardsField && $regionCardsField->id) {
	$regionsRepeaterTemplate = $repeaterType->_getRepeaterTemplate($regionCardsField);
	if($regionsRepeaterTemplate && $regionsRepeaterTemplate->id) {
		$regionsFieldgroup = $regionsRepeaterTemplate->fieldgroup;
		$regionsRepeaterFields = [$regionCardTitleField, $regionCardDescriptionField, $regionCardImageField];
		$regionsChanged = false;

		foreach($regionsRepeaterFields as $field) {
			if(!$field || !$field->id) continue;
			if(!$regionsFieldgroup->has($field)) {
				$regionsFieldgroup->add($field);
				$regionsChanged = true;
			}
		}

		if($regionsChanged) {
			$regionsFieldgroup->save();
			$log->save('actual-cards-setup', "Updated repeater fieldgroup '{$regionsFieldgroup->name}'.");
		}
	}
}

if($tourDaysField && $tourDaysField->id) {
	$tourDaysRepeaterTemplate = $repeaterType->_getRepeaterTemplate($tourDaysField);
	if($tourDaysRepeaterTemplate && $tourDaysRepeaterTemplate->id) {
		$tourDaysFieldgroup = $tourDaysRepeaterTemplate->fieldgroup;
		$tourDaysRepeaterFields = [$tourDayTitleField, $tourDayDescriptionField, $tourDayImagesField];
		$tourDaysChanged = false;

		foreach($tourDaysRepeaterFields as $field) {
			if(!$field || !$field->id) continue;
			if(!$tourDaysFieldgroup->has($field)) {
				$tourDaysFieldgroup->add($field);
				$tourDaysChanged = true;
			}
		}

		if($tourDaysChanged) {
			$tourDaysFieldgroup->save();
			$log->save('actual-cards-setup', "Updated repeater fieldgroup '{$tourDaysFieldgroup->name}'.");
		}
	}
}

if($tourIncludedItemsField && $tourIncludedItemsField->id) {
	$tourIncludedRepeaterTemplate = $repeaterType->_getRepeaterTemplate($tourIncludedItemsField);
	if($tourIncludedRepeaterTemplate && $tourIncludedRepeaterTemplate->id) {
		$tourIncludedFieldgroup = $tourIncludedRepeaterTemplate->fieldgroup;
		$tourIncludedRepeaterFields = [$tourIncludedItemTextField];
		$tourIncludedChanged = false;

		foreach($tourIncludedRepeaterFields as $field) {
			if(!$field || !$field->id) continue;
			if(!$tourIncludedFieldgroup->has($field)) {
				$tourIncludedFieldgroup->add($field);
				$tourIncludedChanged = true;
			}
		}

		if($tourIncludedChanged) {
			$tourIncludedFieldgroup->save();
			$log->save('actual-cards-setup', "Updated repeater fieldgroup '{$tourIncludedFieldgroup->name}'.");
		}
	}
}

$homeTemplate = $templates->get('home');
if($homeTemplate && $homeTemplate->id) {
	$homeFieldgroup = $homeTemplate->fieldgroup;
	$homeChanged = false;

	if($actualCardsField && $actualCardsField->id && !$homeFieldgroup->has($actualCardsField)) {
		$homeFieldgroup->add($actualCardsField);
		$homeChanged = true;
	}

	if($hotToursCardsField && $hotToursCardsField->id && !$homeFieldgroup->has($hotToursCardsField)) {
		$homeFieldgroup->add($hotToursCardsField);
		$homeChanged = true;
	}

	if($dagestanPlacesCardsField && $dagestanPlacesCardsField->id && !$homeFieldgroup->has($dagestanPlacesCardsField)) {
		$homeFieldgroup->add($dagestanPlacesCardsField);
		$homeChanged = true;
	}

	if($homeChanged) {
		$homeFieldgroup->save();
		$log->save('actual-cards-setup', "Updated repeater fields on template 'home'.");
	}
}

$tourTemplate = $templates->get('tour');
if($tourTemplate && $tourTemplate->id) {
	$tourFieldgroup = $tourTemplate->fieldgroup;
	$tourChanged = false;
	$tourFields = [
		$tourRegionField,
		$tourDescriptionField,
		$tourPriceField,
		$tourDurationField,
		$tourGroupField,
		$tourSeasonField,
		$tourDifficultyLevelField,
		$tourAgeField,
		$tourIncludedField,
		$tourIncludedItemsField,
		$tourCoverImageField,
		$tourDaysField,
	];

	foreach($tourFields as $field) {
		if(!$field || !$field->id) continue;
		if(!$tourFieldgroup->has($field)) {
			$tourFieldgroup->add($field);
			$tourChanged = true;
		}
	}

	$tourFieldsToRemove = ['tour_subtitle', 'tour_transfer', 'tour_difficulty'];
	foreach($tourFieldsToRemove as $fieldName) {
		$field = $fields->get($fieldName);
		if($field && $field->id && $tourFieldgroup->has($field)) {
			$tourFieldgroup->remove($field);
			$tourChanged = true;
		}
	}

	if($tourChanged) {
		$tourFieldgroup->save();
		$log->save('actual-cards-setup', "Updated fields on template 'tour'.");
	}
}

$regionsPageDefaults = [
	[
		'slug' => 'kabardino-balkarskaya-respublika',
		'title' => "Кабардино-Балкарская\nРеспублика",
		'description' => "Эльбрус и ущелья",
	],
	[
		'slug' => 'karachaevo-cherkesskaya-respublika',
		'title' => "Карачаево-Черкесская\nРеспублика",
		'description' => "Домбай и Архыз",
	],
	[
		'slug' => 'respublika-dagestan',
		'title' => 'Республика Дагестан',
		'description' => 'Каньоны, аулы и море впечатлений',
	],
	[
		'slug' => 'respublika-ingushetiya',
		'title' => 'Республика Ингушетия',
		'description' => 'Башни, легенды и горные долины',
	],
	[
		'slug' => 'respublika-severnaya-osetiya',
		'title' => "Республика\nСеверная Осетия",
		'description' => 'Перевалы и древние тропы',
	],
	[
		'slug' => 'stavropolskiy-kray',
		'title' => 'Ставропольский край',
		'description' => 'Курорты, парки и мягкий южный ритм',
	],
	[
		'slug' => 'chechenskaya-respublika',
		'title' => 'Чеченская Республика',
		'description' => 'Горные дороги и мощные виды',
	],
];

$regionDetailTemplate = $templates->get('region');
if(!$regionDetailTemplate || !$regionDetailTemplate->id) {
	$basicTemplate = $templates->get('basic-page');
	if($basicTemplate && $basicTemplate->id && $basicTemplate->fieldgroup) {
		$regionDetailTemplate = new Template();
		$regionDetailTemplate->name = 'region';
		$regionDetailTemplate->label = 'Регион';
		$regionDetailTemplate->fieldgroup = $basicTemplate->fieldgroup;
		$templates->save($regionDetailTemplate);
		$log->save('actual-cards-setup', "Created template 'region'.");
	} else {
		$log->save('actual-cards-setup', "Cannot create template 'region': template 'basic-page' not found.");
	}
}

$regionsPage = $pages->get('/regions/');
$currentPage = wire('page');
$isAdminRequest = $currentPage && $currentPage->template && $currentPage->template->name === 'admin';
$regionsTemplate = null;
if($regionsPage && $regionsPage->id && $regionsPage->template && $regionsPage->template->id) {
	$regionsTemplate = $regionsPage->template;
} else {
	$regionsTemplate = $templates->get('regions');
	if(!$regionsTemplate || !$regionsTemplate->id) {
		$regionsTemplate = $templates->get('basic-page');
	}
}

if($regionsTemplate && $regionsTemplate->id && $regionCardsField && $regionCardsField->id) {
	$regionsPageFieldgroup = $regionsTemplate->fieldgroup;
	if($regionsPageFieldgroup && !$regionsPageFieldgroup->has($regionCardsField)) {
		$regionsPageFieldgroup->add($regionCardsField);
		$regionsPageFieldgroup->save();
		$log->save('actual-cards-setup', "Updated fields on template '{$regionsTemplate->name}'.");
	}
}

if((!$regionsPage || !$regionsPage->id) && $regionsTemplate && $regionsTemplate->id) {
	$homePage = $pages->get('/');
	if($homePage && $homePage->id) {
		$regionsPage = new Page();
		$regionsPage->template = $regionsTemplate;
		$regionsPage->parent = $homePage;
		$regionsPage->name = 'regions';
		$regionsPage->title = 'Регионы';
		$pages->save($regionsPage);
		$log->save('actual-cards-setup', "Created page '/regions/'.");
	}
}

if($regionsPage && $regionsPage->id && $regionDetailTemplate && $regionDetailTemplate->id) {
	foreach($regionsPageDefaults as $item) {
		$slug = trim((string) ($item['slug'] ?? ''));
		$title = trim(str_replace("\n", ' ', (string) ($item['title'] ?? '')));
		if($slug === '' || $title === '') continue;

		$regionPath = $regionsPage->path . $slug . '/';
		$regionPage = $pages->get($regionPath);

		if(!$regionPage || !$regionPage->id) {
			$regionPage = new Page();
			$regionPage->template = $regionDetailTemplate;
			$regionPage->parent = $regionsPage;
			$regionPage->name = $slug;
			$regionPage->title = $title;
			$pages->save($regionPage);
			$log->save('actual-cards-setup', "Created region page '{$regionPath}'.");
		} elseif($regionPage->template && $regionPage->template->name !== 'region') {
			$regionPage->of(false);
			$regionPage->template = $regionDetailTemplate;
			$pages->save($regionPage);
			$log->save('actual-cards-setup', "Updated template for region page '{$regionPath}'.");
		}
	}
}

if(!$isAdminRequest && $regionsPage && $regionsPage->id && $regionCardsField && $regionCardsField->id) {
	$regionsPage = $pages->get((int) $regionsPage->id);
	if($regionsPage && $regionsPage->id && $regionsPage->hasField('region_cards')) {
		$regionsCards = $regionsPage->getUnformatted('region_cards');
		$regionsCardsCount = $regionsCards instanceof PageArray ? $regionsCards->count() : 0;

		if($regionsCardsCount === 0) {
			$regionsPage->of(false);
			foreach($regionsPageDefaults as $item) {
				$card = $regionsPage->region_cards->getNew();
				$card->of(false);
				$card->set('region_card_title', $item['title']);
				$card->set('region_card_description', $item['description']);
				$regionsPage->region_cards->add($card);
			}
			$regionsPage->save('region_cards');
			$log->save('actual-cards-setup', "Seeded field 'region_cards' on page '/regions/'.");
		}
	}
}

$reviewsTemplate = $templates->get('reviews');
if(!$reviewsTemplate || !$reviewsTemplate->id) {
	$basicTemplate = $templates->get('basic-page');
	if($basicTemplate && $basicTemplate->id && $basicTemplate->fieldgroup) {
		$reviewsTemplate = new Template();
		$reviewsTemplate->name = 'reviews';
		$reviewsTemplate->label = 'Отзывы';
		$reviewsTemplate->fieldgroup = $basicTemplate->fieldgroup;
		$templates->save($reviewsTemplate);
		$log->save('actual-cards-setup', "Created template 'reviews'.");
	} else {
		$log->save('actual-cards-setup', "Cannot create template 'reviews': template 'basic-page' not found.");
	}
}

if($reviewsTemplate && $reviewsTemplate->id) {
	$reviewsPage = $pages->get('/reviews/');
	if(!$reviewsPage || !$reviewsPage->id) {
		$homePage = $pages->get('/');
		if($homePage && $homePage->id) {
			$reviewsPage = new Page();
			$reviewsPage->template = $reviewsTemplate;
			$reviewsPage->parent = $homePage;
			$reviewsPage->name = 'reviews';
			$reviewsPage->title = 'Отзывы';
			$pages->save($reviewsPage);
			$log->save('actual-cards-setup', "Created page '/reviews/'.");
		}
	} elseif($reviewsPage->template && $reviewsPage->template->name !== 'reviews') {
		$reviewsPage->of(false);
		$reviewsPage->template = $reviewsTemplate;
		$pages->save($reviewsPage);
		$log->save('actual-cards-setup', "Updated page '/reviews/' template to 'reviews'.");
	}
}
