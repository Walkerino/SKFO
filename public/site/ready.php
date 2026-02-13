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

if((!$actualCardsField || !$actualCardsField->id) && (!$hotToursCardsField || !$hotToursCardsField->id)) return;

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

	if($homeChanged) {
		$homeFieldgroup->save();
		$log->save('actual-cards-setup', "Updated repeater fields on template 'home'.");
	}
}
