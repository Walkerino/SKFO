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

/** @var Page|null $currentPage */
$currentPage = wire('page');
$isAdminRequest = $currentPage && $currentPage->template && $currentPage->template->name === 'admin';
if(!$isAdminRequest) return;

/** @var WireInput $input */
$input = wire('input');
if(strtoupper($input->requestMethod()) === 'POST') return;

/** @var Config $config */
$config = wire('config');
$config->useLazyLoading = false;

/** @var Fields $fields */
$fields = wire('fields');
/** @var Modules $modules */
$modules = wire('modules');
/** @var Templates $templates */
$templates = wire('templates');
/** @var Fieldgroups $fieldgroups */
$fieldgroups = wire('fieldgroups');
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

$regionAdventureTitleField = $ensureField('region_adventure_title', 'FieldtypeText', 'Регион: приключение (заголовок)');
$regionAdventurePriceField = $ensureField('region_adventure_price', 'FieldtypeText', 'Регион: приключение (цена)');
$regionAdventureImageField = $ensureField('region_adventure_image', 'FieldtypeImage', 'Регион: приключение (фото)', [
	'maxFiles' => 1,
	'extensions' => 'jpg jpeg png gif webp',
]);
$regionAdventuresCardsField = $ensureField('region_adventures_cards', 'FieldtypeRepeater', 'Регион: блок "На встречу к приключениям"');

$regionPlaceTitleField = $ensureField('region_place_title', 'FieldtypeText', 'Регион: место (заголовок)');
$regionPlaceTextField = $ensureField('region_place_text', 'FieldtypeTextarea', 'Регион: место (описание)');
$regionPlaceImageField = $ensureField('region_place_image', 'FieldtypeImage', 'Регион: место (фото)', [
	'maxFiles' => 1,
	'extensions' => 'jpg jpeg png gif webp',
]);
$regionPlacesCardsField = $ensureField('region_places_cards', 'FieldtypeRepeater', 'Регион: блок интересных мест');

$regionArticlesHeadingField = $ensureField('region_articles_heading', 'FieldtypeText', 'Регион: заголовок блока статей');
$regionArticleTitleField = $ensureField('region_article_title', 'FieldtypeText', 'Регион: статья (заголовок)');
$regionArticleDateField = $ensureField('region_article_date', 'FieldtypeDatetime', 'Регион: статья (дата публикации)');
$regionArticleTopicField = $ensureField('region_article_topic', 'FieldtypeText', 'Регион: статья (тематика)');
$regionArticleUrlField = $ensureField('region_article_url', 'FieldtypeText', 'Регион: статья (ссылка)');
$regionArticleImageField = $ensureField('region_article_image', 'FieldtypeImage', 'Регион: статья (фото)', [
	'maxFiles' => 1,
	'extensions' => 'jpg jpeg png gif webp',
]);
$regionArticleFreshField = $ensureField('region_article_is_fresh', 'FieldtypeCheckbox', 'Регион: статья (плашка "Свежая статья")');
$regionArticlesCardsField = $ensureField('region_articles_cards', 'FieldtypeRepeater', 'Регион: блок "Интересное о регионе"');

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

$articleTopicField = $ensureField('article_topic', 'FieldtypeText', 'Статья: тематика');
$articlePublishDateField = $ensureField('article_publish_date', 'FieldtypeDatetime', 'Статья: дата публикации');
$articleCoverImageField = $ensureField('article_cover_image', 'FieldtypeImage', 'Статья: обложка', [
	'maxFiles' => 1,
	'extensions' => 'jpg jpeg png gif webp',
]);
$articleExcerptField = $ensureField('article_excerpt', 'FieldtypeTextarea', 'Статья: краткое описание');
$articleContentField = $ensureField('article_content', 'FieldtypeTextarea', 'Статья: текст');

$placeRegionField = $ensureField('place_region', 'FieldtypeText', 'Место: регион');
$placeSummaryField = $ensureField('place_summary', 'FieldtypeTextarea', 'Место: описание');
$placeImageField = $ensureField('place_image', 'FieldtypeImage', 'Место: фото', [
	'maxFiles' => 1,
	'extensions' => 'jpg jpeg png gif webp',
]);

$hotelCityField = $ensureField('hotel_city', 'FieldtypeText', 'Отель: город');
$hotelRegionField = $ensureField('hotel_region', 'FieldtypeText', 'Отель: регион');
$hotelRatingField = $ensureField('hotel_rating', 'FieldtypeFloat', 'Отель: рейтинг');
$hotelPriceField = $ensureField('hotel_price', 'FieldtypeInteger', 'Отель: цена');
$hotelMaxGuestsField = $ensureField('hotel_max_guests', 'FieldtypeInteger', 'Отель: макс. гостей');
$hotelAmenitiesField = $ensureField('hotel_amenities', 'FieldtypeTextarea', 'Отель: удобства (по строкам)');
$hotelImageField = $ensureField('hotel_image', 'FieldtypeImage', 'Отель: фото', [
	'maxFiles' => 1,
	'extensions' => 'jpg jpeg png gif webp',
]);

$homeFeaturedToursField = $ensureField('home_featured_tours', 'FieldtypePage', 'Главная: выбранные туры');
$homeFeaturedPlacesField = $ensureField('home_featured_places', 'FieldtypePage', 'Главная: выбранные места');
$homeActualPlacesField = $ensureField('home_actual_places', 'FieldtypePage', 'Главная: актуальные места');
$homeFeaturedArticlesField = $ensureField('home_featured_articles', 'FieldtypePage', 'Главная: выбранные статьи');

$regionFeaturedToursField = $ensureField('region_featured_tours', 'FieldtypePage', 'Регион: выбранные туры');
$regionFeaturedPlacesField = $ensureField('region_featured_places', 'FieldtypePage', 'Регион: выбранные места');
$regionFeaturedArticlesField = $ensureField('region_featured_articles', 'FieldtypePage', 'Регион: выбранные статьи');

$articlesTodayRefsField = $ensureField('articles_today_refs', 'FieldtypePage', 'Статьи: блок "Читают сегодня"');
$articlesFirstTimeRefsField = $ensureField('articles_first_time_refs', 'FieldtypePage', 'Статьи: блок "Впервые на Кавказе?"');
$hotelsFeaturedRefsField = $ensureField('hotels_featured_refs', 'FieldtypePage', 'Отели: приоритетные карточки');

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

if($regionAdventureImageField && $regionAdventureImageField->id) {
	$regionAdventureImageChanged = false;

	if((int) $regionAdventureImageField->get('maxFiles') !== 1) {
		$regionAdventureImageField->set('maxFiles', 1);
		$regionAdventureImageChanged = true;
	}

	$regionAdventureExtensions = trim((string) $regionAdventureImageField->get('extensions'));
	if($regionAdventureExtensions === '') {
		$regionAdventureImageField->set('extensions', 'jpg jpeg png gif webp');
		$regionAdventureImageChanged = true;
	}

	if($regionAdventureImageChanged) {
		$fields->save($regionAdventureImageField);
		$log->save('actual-cards-setup', "Updated field 'region_adventure_image' settings.");
	}
}

if($regionPlaceImageField && $regionPlaceImageField->id) {
	$regionPlaceImageChanged = false;

	if((int) $regionPlaceImageField->get('maxFiles') !== 1) {
		$regionPlaceImageField->set('maxFiles', 1);
		$regionPlaceImageChanged = true;
	}

	$regionPlaceExtensions = trim((string) $regionPlaceImageField->get('extensions'));
	if($regionPlaceExtensions === '') {
		$regionPlaceImageField->set('extensions', 'jpg jpeg png gif webp');
		$regionPlaceImageChanged = true;
	}

	if($regionPlaceImageChanged) {
		$fields->save($regionPlaceImageField);
		$log->save('actual-cards-setup', "Updated field 'region_place_image' settings.");
	}
}

if($regionArticleImageField && $regionArticleImageField->id) {
	$regionArticleImageChanged = false;

	if((int) $regionArticleImageField->get('maxFiles') !== 1) {
		$regionArticleImageField->set('maxFiles', 1);
		$regionArticleImageChanged = true;
	}

	$regionArticleExtensions = trim((string) $regionArticleImageField->get('extensions'));
	if($regionArticleExtensions === '') {
		$regionArticleImageField->set('extensions', 'jpg jpeg png gif webp');
		$regionArticleImageChanged = true;
	}

	if($regionArticleImageChanged) {
		$fields->save($regionArticleImageField);
		$log->save('actual-cards-setup', "Updated field 'region_article_image' settings.");
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

$catalogImageFields = [
	'article_cover_image' => $articleCoverImageField,
	'place_image' => $placeImageField,
	'hotel_image' => $hotelImageField,
];
foreach($catalogImageFields as $fieldName => $field) {
	if(!$field || !$field->id) continue;

	$imageChanged = false;
	if((int) $field->get('maxFiles') !== 1) {
		$field->set('maxFiles', 1);
		$imageChanged = true;
	}

	$extensions = trim((string) $field->get('extensions'));
	if($extensions === '') {
		$field->set('extensions', 'jpg jpeg png gif webp');
		$imageChanged = true;
	}

	if($imageChanged) {
		$fields->save($field);
		$log->save('actual-cards-setup', "Updated field '{$fieldName}' settings.");
	}
}

$syncPageReferenceField = static function(?Field $field, string $selector) use ($fields, $log): void {
	if(!$field || !$field->id) return;
	$changed = false;

	if((string) $field->get('inputfieldClass') !== 'InputfieldAsmSelect') {
		$field->set('inputfieldClass', 'InputfieldAsmSelect');
		$changed = true;
	}

	if((string) $field->get('findPagesSelector') !== $selector) {
		$field->set('findPagesSelector', $selector);
		$changed = true;
	}

	if((string) $field->get('labelFieldName') !== 'title') {
		$field->set('labelFieldName', 'title');
		$changed = true;
	}

	if($changed) {
		$fields->save($field);
		$log->save('actual-cards-setup', "Updated field '{$field->name}' settings.");
	}
};

$syncPageReferenceField($homeFeaturedToursField, 'template=tour, include=all, sort=title');
$syncPageReferenceField($homeFeaturedPlacesField, 'template=place, include=all, sort=title');
$syncPageReferenceField($homeActualPlacesField, 'template=place, include=all, sort=title');
$syncPageReferenceField($homeFeaturedArticlesField, 'template=article, include=all, sort=-article_publish_date');

$syncPageReferenceField($regionFeaturedToursField, 'template=tour, include=all, sort=title');
$syncPageReferenceField($regionFeaturedPlacesField, 'template=place, include=all, sort=title');
$syncPageReferenceField($regionFeaturedArticlesField, 'template=article, include=all, sort=-article_publish_date');

$syncPageReferenceField($articlesTodayRefsField, 'template=article, include=all, sort=-article_publish_date');
$syncPageReferenceField($articlesFirstTimeRefsField, 'template=article, include=all, sort=-article_publish_date');
$syncPageReferenceField($hotelsFeaturedRefsField, 'template=hotel, include=all, sort=title');

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
	(!$regionAdventuresCardsField || !$regionAdventuresCardsField->id) &&
	(!$regionPlacesCardsField || !$regionPlacesCardsField->id) &&
	(!$regionArticlesCardsField || !$regionArticlesCardsField->id) &&
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

if($regionAdventuresCardsField && $regionAdventuresCardsField->id) {
	$regionAdventuresRepeaterTemplate = $repeaterType->_getRepeaterTemplate($regionAdventuresCardsField);
	if($regionAdventuresRepeaterTemplate && $regionAdventuresRepeaterTemplate->id) {
		$regionAdventuresFieldgroup = $regionAdventuresRepeaterTemplate->fieldgroup;
		$regionAdventuresRepeaterFields = [$regionAdventureTitleField, $regionAdventurePriceField, $regionAdventureImageField];
		$regionAdventuresChanged = false;

		foreach($regionAdventuresRepeaterFields as $field) {
			if(!$field || !$field->id) continue;
			if(!$regionAdventuresFieldgroup->has($field)) {
				$regionAdventuresFieldgroup->add($field);
				$regionAdventuresChanged = true;
			}
		}

		if($regionAdventuresChanged) {
			$regionAdventuresFieldgroup->save();
			$log->save('actual-cards-setup', "Updated repeater fieldgroup '{$regionAdventuresFieldgroup->name}'.");
		}
	}
}

if($regionPlacesCardsField && $regionPlacesCardsField->id) {
	$regionPlacesRepeaterTemplate = $repeaterType->_getRepeaterTemplate($regionPlacesCardsField);
	if($regionPlacesRepeaterTemplate && $regionPlacesRepeaterTemplate->id) {
		$regionPlacesFieldgroup = $regionPlacesRepeaterTemplate->fieldgroup;
		$regionPlacesRepeaterFields = [$regionPlaceTitleField, $regionPlaceTextField, $regionPlaceImageField];
		$regionPlacesChanged = false;

		foreach($regionPlacesRepeaterFields as $field) {
			if(!$field || !$field->id) continue;
			if(!$regionPlacesFieldgroup->has($field)) {
				$regionPlacesFieldgroup->add($field);
				$regionPlacesChanged = true;
			}
		}

		if($regionPlacesChanged) {
			$regionPlacesFieldgroup->save();
			$log->save('actual-cards-setup', "Updated repeater fieldgroup '{$regionPlacesFieldgroup->name}'.");
		}
	}
}

if($regionArticlesCardsField && $regionArticlesCardsField->id) {
	$regionArticlesRepeaterTemplate = $repeaterType->_getRepeaterTemplate($regionArticlesCardsField);
	if($regionArticlesRepeaterTemplate && $regionArticlesRepeaterTemplate->id) {
		$regionArticlesFieldgroup = $regionArticlesRepeaterTemplate->fieldgroup;
		$regionArticlesRepeaterFields = [
			$regionArticleTitleField,
			$regionArticleDateField,
			$regionArticleTopicField,
			$regionArticleUrlField,
			$regionArticleImageField,
			$regionArticleFreshField,
		];
		$regionArticlesChanged = false;

		foreach($regionArticlesRepeaterFields as $field) {
			if(!$field || !$field->id) continue;
			if(!$regionArticlesFieldgroup->has($field)) {
				$regionArticlesFieldgroup->add($field);
				$regionArticlesChanged = true;
			}
		}

		if($regionArticlesChanged) {
			$regionArticlesFieldgroup->save();
			$log->save('actual-cards-setup', "Updated repeater fieldgroup '{$regionArticlesFieldgroup->name}'.");
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

	$homeCatalogFields = [
		$homeFeaturedToursField,
		$homeFeaturedPlacesField,
		$homeActualPlacesField,
		$homeFeaturedArticlesField,
	];
	foreach($homeCatalogFields as $field) {
		if(!$field || !$field->id) continue;
		if(!$homeFieldgroup->has($field)) {
			$homeFieldgroup->add($field);
			$homeChanged = true;
		}
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

$ensureTemplateWithFieldgroup = function(string $templateName, string $label, string $fieldgroupName) use ($templates, $fieldgroups, $log): ?Template {
	$template = $templates->get($templateName);
	if($template && $template->id) return $template;

	$fieldgroup = $fieldgroups->get($fieldgroupName);
	if(!$fieldgroup || !$fieldgroup->id) {
		$fieldgroup = new Fieldgroup();
		$fieldgroup->name = $fieldgroupName;
		$fieldgroups->save($fieldgroup);
	}

	$template = new Template();
	$template->name = $templateName;
	$template->label = $label;
	$template->fieldgroup = $fieldgroup;
	$templates->save($template);
	$log->save('actual-cards-setup', "Created template '{$templateName}'.");
	return $templates->get($templateName);
};

$articleTemplate = $ensureTemplateWithFieldgroup('article', 'Статья (каталог)', 'catalog_article');
$placeTemplate = $ensureTemplateWithFieldgroup('place', 'Место (каталог)', 'catalog_place');
$hotelTemplate = $ensureTemplateWithFieldgroup('hotel', 'Отель (каталог)', 'catalog_hotel');
$systemTitleField = $fields->get('title');

$ensureTemplateTitleSupport = static function(?Template $template, ?Field $titleField) use ($templates, $log): void {
	if(!$template || !$template->id || !$template->fieldgroup || !$titleField || !$titleField->id) return;

	$fieldgroupChanged = false;
	if(!$template->fieldgroup->has($titleField)) {
		$template->fieldgroup->add($titleField);
		$fieldgroupChanged = true;
	}
	if($fieldgroupChanged) {
		$template->fieldgroup->save();
		$log->save('actual-cards-setup', "Added system field 'title' to template '{$template->name}'.");
	}

	if((int) $template->get('noGlobal') !== 0) {
		$template->set('noGlobal', 0);
		$templates->save($template);
		$log->save('actual-cards-setup', "Enabled global fields on template '{$template->name}'.");
	}
};

$ensureTemplateTitleSupport($articleTemplate, $systemTitleField);
$ensureTemplateTitleSupport($placeTemplate, $systemTitleField);
$ensureTemplateTitleSupport($hotelTemplate, $systemTitleField);

$syncTemplateFields = static function(?Template $template, array $templateFields, string $logName) use ($log): void {
	if(!$template || !$template->id || !$template->fieldgroup) return;
	$fieldgroup = $template->fieldgroup;
	$changed = false;

	foreach($templateFields as $field) {
		if(!$field || !$field->id) continue;
		if(!$fieldgroup->has($field)) {
			$fieldgroup->add($field);
			$changed = true;
		}
	}

	if($changed) {
		$fieldgroup->save();
		$log->save('actual-cards-setup', $logName);
	}
};

$syncTemplateFields($articleTemplate, [
	$articleTopicField,
	$articlePublishDateField,
	$articleCoverImageField,
	$articleExcerptField,
	$articleContentField,
], "Updated fields on template 'article'.");

$syncTemplateFields($placeTemplate, [
	$placeRegionField,
	$placeSummaryField,
	$placeImageField,
], "Updated fields on template 'place'.");

$syncTemplateFields($hotelTemplate, [
	$hotelCityField,
	$hotelRegionField,
	$hotelRatingField,
	$hotelPriceField,
	$hotelMaxGuestsField,
	$hotelAmenitiesField,
	$hotelImageField,
], "Updated fields on template 'hotel'.");

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

if($regionDetailTemplate && $regionDetailTemplate->id) {
	$regionFieldgroup = $regionDetailTemplate->fieldgroup;
	$regionTemplateFields = [
		$regionAdventuresCardsField,
		$regionPlacesCardsField,
		$regionArticlesHeadingField,
		$regionArticlesCardsField,
		$regionFeaturedToursField,
		$regionFeaturedPlacesField,
		$regionFeaturedArticlesField,
	];
	$regionTemplateChanged = false;

	foreach($regionTemplateFields as $field) {
		if(!$field || !$field->id) continue;
		if(!$regionFieldgroup->has($field)) {
			$regionFieldgroup->add($field);
			$regionTemplateChanged = true;
		}
	}

	$regionFieldsToRemove = [
		'region_forum_title',
		'region_forum_subtitle',
		'region_forum_button_text',
		'region_forum_image',
	];
	foreach($regionFieldsToRemove as $fieldName) {
		$field = $fields->get($fieldName);
		if($field && $field->id && $regionFieldgroup->has($field)) {
			$regionFieldgroup->remove($field);
			$regionTemplateChanged = true;
		}
	}

	if($regionTemplateChanged) {
		$regionFieldgroup->save();
		$log->save('actual-cards-setup', "Updated fields on template 'region'.");
	}
}

$basicPageTemplate = $templates->get('basic-page');
if($basicPageTemplate && $basicPageTemplate->id && $basicPageTemplate->fieldgroup) {
	$basicFieldgroup = $basicPageTemplate->fieldgroup;
	$basicChanged = false;
	$basicCatalogFields = [$articlesTodayRefsField, $articlesFirstTimeRefsField, $hotelsFeaturedRefsField];
	foreach($basicCatalogFields as $field) {
		if(!$field || !$field->id) continue;
		if(!$basicFieldgroup->has($field)) {
			$basicFieldgroup->add($field);
			$basicChanged = true;
		}
	}

	if($basicChanged) {
		$basicFieldgroup->save();
		$log->save('actual-cards-setup', "Updated fields on template 'basic-page'.");
	}
}

$regionsPage = $pages->get('/regions/');
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

			if($isAdminRequest && $regionPage && $regionPage->id) {
				$regionPage = $pages->get((int) $regionPage->id);
				if(!$regionPage || !$regionPage->id) continue;

				$regionPage->of(false);

				if($regionPage->hasField('region_articles_heading')) {
					$currentHeading = trim((string) $regionPage->getUnformatted('region_articles_heading'));
					if($currentHeading === '') {
						$regionPage->setAndSave('region_articles_heading', 'Интересное о ' . $title);
					}
				}

				if($regionPage->hasField('region_adventures_cards')) {
					$adventureItems = $regionPage->getUnformatted('region_adventures_cards');
					$adventureCount = $adventureItems instanceof PageArray ? $adventureItems->count() : 0;

					if($adventureCount === 0) {
						$adventureDefaults = [
							['title' => "Открыть лучшие маршруты {$title}", 'price' => 'от 15 000₽'],
							['title' => "Увидеть знаковые места {$title}", 'price' => 'от 12 500₽'],
							['title' => "Запланировать активный уикенд в {$title}", 'price' => 'от 13 000₽'],
							['title' => "Выбрать семейный тур по {$title}", 'price' => 'от 10 000₽'],
							['title' => "Собрать насыщенный маршрут по {$title}", 'price' => 'от 11 500₽'],
						];

						foreach($adventureDefaults as $adventureDefault) {
							$card = $regionPage->region_adventures_cards->getNew();
							$card->of(false);
							$card->set('region_adventure_title', (string) ($adventureDefault['title'] ?? ''));
							$card->set('region_adventure_price', (string) ($adventureDefault['price'] ?? ''));
							$regionPage->region_adventures_cards->add($card);
						}

						$regionPage->save('region_adventures_cards');
						$log->save('actual-cards-setup', "Seeded field 'region_adventures_cards' on page '{$regionPath}'.");
					}
				}

				if($regionPage->hasField('region_places_cards')) {
					$placeItems = $regionPage->getUnformatted('region_places_cards');
					$placeCount = $placeItems instanceof PageArray ? $placeItems->count() : 0;

					if($placeCount === 0) {
						$placeDefaults = [
							[
								'title' => "Главная достопримечательность {$title}",
								'text' => 'Одна из самых узнаваемых локаций региона, которую стоит увидеть в первую очередь.',
							],
							[
								'title' => "Панорамный маршрут {$title}",
								'text' => 'Живописный маршрут с красивыми видами и удобным доступом для путешествий.',
							],
						];

						foreach($placeDefaults as $placeDefault) {
							$card = $regionPage->region_places_cards->getNew();
							$card->of(false);
							$card->set('region_place_title', (string) ($placeDefault['title'] ?? ''));
							$card->set('region_place_text', (string) ($placeDefault['text'] ?? ''));
							$regionPage->region_places_cards->add($card);
						}

						$regionPage->save('region_places_cards');
						$log->save('actual-cards-setup', "Seeded field 'region_places_cards' on page '{$regionPath}'.");
					}
				}

				if($regionPage->hasField('region_articles_cards')) {
					$articleItems = $regionPage->getUnformatted('region_articles_cards');
					$articleCount = $articleItems instanceof PageArray ? $articleItems->count() : 0;

					if($articleCount === 0) {
						$articleDefaults = [
							[
								'title' => "Как подготовиться к путешествию в {$title}",
								'topic' => 'Советы туристам',
								'date' => strtotime('2026-02-01'),
								'is_fresh' => 1,
							],
							[
								'title' => 'Душа Кавказа в поэзии и традициях',
								'topic' => 'Культура и традиции',
								'date' => strtotime('2025-12-22'),
								'is_fresh' => 0,
							],
							[
								'title' => 'Горнолыжный сезон: советы и лайфхаки',
								'topic' => 'Советы туристам',
								'date' => strtotime('2025-12-16'),
								'is_fresh' => 0,
							],
							[
								'title' => "Что взять с собой в поездку по {$title}",
								'topic' => 'Полезные подборки',
								'date' => strtotime('2025-12-08'),
								'is_fresh' => 0,
							],
						];

						foreach($articleDefaults as $articleDefault) {
							$card = $regionPage->region_articles_cards->getNew();
							$card->of(false);
							$card->set('region_article_title', (string) ($articleDefault['title'] ?? ''));
							$card->set('region_article_topic', (string) ($articleDefault['topic'] ?? ''));
							$card->set('region_article_date', (int) ($articleDefault['date'] ?? 0));
							$card->set('region_article_is_fresh', (int) ($articleDefault['is_fresh'] ?? 0));
							$regionPage->region_articles_cards->add($card);
						}

						$regionPage->save('region_articles_cards');
						$log->save('actual-cards-setup', "Seeded field 'region_articles_cards' on page '{$regionPath}'.");
					}
				}
			}
		}
	}

if($isAdminRequest && $regionsPage && $regionsPage->id && $regionCardsField && $regionCardsField->id) {
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

$homePage = $pages->get('/');
$contentRoot = $pages->get('/content/');
if((!$contentRoot || !$contentRoot->id) && $homePage && $homePage->id) {
	$rootTemplate = $templates->get('basic-page');
	if($rootTemplate && $rootTemplate->id) {
		$contentRoot = new Page();
		$contentRoot->template = $rootTemplate;
		$contentRoot->parent = $homePage;
		$contentRoot->name = 'content';
		$contentRoot->title = 'Каталог контента';
		$pages->save($contentRoot);
		$log->save('actual-cards-setup', "Created page '/content/'.");
	}
}

$ensureCatalogSection = static function(?Page $parent, string $name, string $title, ?Template $template) use ($pages, $log): ?Page {
	if(!$parent || !$parent->id || !$template || !$template->id) return null;
	$path = $parent->path . $name . '/';
	$page = $pages->get($path);
	if($page && $page->id) {
		if($page->template && $page->template->id !== $template->id) {
			$page->of(false);
			$page->template = $template;
			$pages->save($page);
			$log->save('actual-cards-setup', "Updated template for page '{$path}'.");
		}
		return $page;
	}

	$page = new Page();
	$page->template = $template;
	$page->parent = $parent;
	$page->name = $name;
	$page->title = $title;
	$pages->save($page);
	$log->save('actual-cards-setup', "Created page '{$path}'.");
	return $page;
};

if($contentRoot && $contentRoot->id) {
	$basicPageTemplate = $templates->get('basic-page');
	$ensureCatalogSection($contentRoot, 'tours', 'Каталог туров', $basicPageTemplate);
	$ensureCatalogSection($contentRoot, 'articles', 'Каталог статей', $basicPageTemplate);
	$ensureCatalogSection($contentRoot, 'places', 'Каталог мест', $basicPageTemplate);
	$ensureCatalogSection($contentRoot, 'hotels', 'Каталог отелей', $basicPageTemplate);
}

$contentAdminPage = $pages->get('/content-admin/');
if((!$contentAdminPage || !$contentAdminPage->id) && $homePage && $homePage->id) {
	$basicTemplate = $templates->get('basic-page');
	if($basicTemplate && $basicTemplate->id) {
		$contentAdminPage = new Page();
		$contentAdminPage->template = $basicTemplate;
		$contentAdminPage->parent = $homePage;
		$contentAdminPage->name = 'content-admin';
		$contentAdminPage->title = 'Контент-центр';
		$pages->save($contentAdminPage);
		$log->save('actual-cards-setup', "Created page '/content-admin/'.");
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
