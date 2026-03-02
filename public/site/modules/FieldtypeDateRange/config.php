<?php namespace ProcessWire;

/**
 * Date Range configuration functions
 *
 * This file is part of the ProFields package
 * Please do not distribute.
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */

/**
 * InputfieldDateRange configuration
 * 
 * @param InputfieldWrapper $inputfields
 * @param InputfieldDateRange $module
 *
 */
function InputfieldDateRangeConfig(InputfieldWrapper $inputfields, InputfieldDateRange $module) {

	$config = $module->wire()->config;
	$labels = $module->getLabels();
	$untranslated = $labels->getUntranslatedLabels();
	$translated = $labels->getTranslatedLabels();
	$clickToSelectDays = __('Click to select days');
	$dayOptions = array();

	foreach($untranslated['day-names'] as $key => $label) {
		$dayOptions[$label] = $translated['day-names'][$key];
	}
	
	$f = $inputfields->wire()->modules->get('InputfieldDateRange');
	$f->attr('id+name', 'date_range_example');
	$f->label = __('Date range example preview');
	$f->description = __('This example will update automatically for most setting changes below.');
	$f->val(''); 
	$f->themeColor = 'primary';
	$settings = $module->getSettings(false, true);
	foreach($settings as $key => $value) {
		$f->set($key, $value);
	}
	$s = 'script';
	$f->appendMarkup .= "<$s>var InputfieldDateRangeSettings=" . json_encode($settings) . "</$s>";
	if($module->placeholder) {
		$f->placeholder = $module->placeholder;
	} else {
		$f->placeholder = __('Click to select dates');
	}
	$f->themeOffset = 1;
	$inputfields->add($f);
	
	// ---
	
	$f = $inputfields->InputfieldRadios;
	$f->attr('name', 'inline');
	$f->label = __('Date picker mode');
	$f->addOption(1, __('Inline/visible'));
	$f->addOption(0, __('On focus'));
	$f->val((int) $module->inline);
	$f->optionColumns = 1;
	$f->columnWidth = 33;
	$inputfields->add($f);

	$f = $inputfields->InputfieldRadios;
	$f->attr('name', 'outputStyle');
	$f->label = __('Output style');
	$f->addOption('admin', __('ProcessWire'));
	$f->addOption('', __('Stock'));
	$f->val($module->outputStyle);
	$f->optionColumns = 1;
	$f->columnWidth = 34;
	$inputfields->add($f);
	
	$f = $inputfields->InputfieldSelect;
	$f->attr('name', 'format');
	$f->label = __('Input date format');
	$testDate = strtotime(date('Y') . '-01-02');
	foreach($module->getDateFormats() as $fechaFormat => $phpFormat) {
		$label = str_replace([ 'MMMM', 'MMM' ], [ 'Month', 'Mon' ], $fechaFormat); 
		$f->addOption($fechaFormat, wireDate($phpFormat, $testDate) . " ($label)");
	}
	$f->val($module->format);
	$f->columnWidth = 33;
	$f->required = true;
	$inputfields->add($f);

	// ---
	
	$f = $inputfields->InputfieldToggle;
	$f->attr('name', 'showTopbar');
	$f->label = __('Show the info bar?');
	$f->val($module->showTopbar);
	$f->columnWidth = 33;
	$inputfields->add($f);

	$f = $inputfields->InputfieldRadios;
	$f->attr('name', 'topbarPosition');
	$f->label = __('Info bar position');
	$f->addOption('top', __('Top'));
	$f->addOption('bottom', __('Bottom'));
	$f->val($module->topbarPosition);
	$f->columnWidth = 34;
	$f->optionColumns = 1; 
	$inputfields->add($f);
	
	$f = $inputfields->InputfieldToggle;
	$f->attr('name', 'clearButton');
	$f->label = __('Show a “clear” button?');
	$f->val($module->clearButton);
	$f->columnWidth = 33;
	$inputfields->add($f);
	
	// ---
	
	$f = $inputfields->InputfieldRadios;
	$f->attr('name', 'startOfWeek');
	$f->label = __('Day that starts the week');
	$f->addOption('sunday', __('Sunday'));
	$f->addOption('monday', __('Monday'));
	$f->val(strtolower($module->startOfWeek));
	$f->columnWidth = 33;
	$f->optionColumns = 1;
	$f->required = true;
	$inputfields->add($f);

	$f = $inputfields->InputfieldToggle;
	$f->attr('name', 'selectForward');
	$f->label = __('Start date must be selected first?');
	$f->val($module->selectForward);
	$f->columnWidth = 34;
	$inputfields->add($f);
	
	$f = $inputfields->InputfieldToggle;
	$f->attr('name', 'moveBothMonths');
	$f->label = __('Buttons move both months?');
	$f->val($module->moveBothMonths);
	$f->columnWidth = 33;
	$inputfields->add($f);
	
	// ---
	
	$f = $inputfields->InputfieldDatetime;
	$f->attr('name', 'startDate');
	$f->label = __('Minimum selectable date');
	$f->notes = __('Specify date or omit for only future dates.');
	$f->val($module->startDate);
	$f->columnWidth = 33;
	$f->inputType = 'html';
	$inputfields->add($f);

	$f = $inputfields->InputfieldDatetime;
	$f->attr('name', 'endDate');
	$f->label = __('Maximum selectable date');
	$f->notes = __('Omit for no max end date.');
	$f->val($module->endDate);
	$f->columnWidth = 34;
	$f->inputType = 'html';
	$inputfields->add($f);
	
	$f = $inputfields->InputfieldToggle;
	$f->attr('name', 'dayLabelMode');
	$f->label = __('Preferred tooltip label');
	$f->labelType = InputfieldToggle::labelTypeCustom;
	$f->yesLabel = __('Days');
	$f->noLabel = __('Nights');
	$f->val($module->dayLabelMode);
	$f->columnWidth = 33;
	$f->notes = __('Save required to update preview.');
	$inputfields->add($f);
	
	// ---

	$f = $inputfields->InputfieldInteger;
	$f->attr('name', 'minNights');
	$f->label = __('Minimum nights');
	$f->notes = __('Specify min or `0` for no min.');
	$f->inputType = 'number';
	$f->val($module->minNights);
	$f->min = 0;
	$f->columnWidth = 33;
	$f->themeOffset = 1;
	$inputfields->add($f);

	$f = $inputfields->InputfieldInteger;
	$f->attr('name', 'maxNights');
	$f->label = __('Maximum nights');
	$f->notes = __('Specify max, `0` for no max, or `-1` to limit to 1 day.');
	$f->inputType = 'number';
	$f->val($module->maxNights);
	$f->min = -1;
	$f->columnWidth = 34;
	$inputfields->add($f);

	$f = $inputfields->InputfieldToggle;
	$f->attr('name', 'autoClose');
	$f->label = __('Close date picker after select?');
	$f->notes =__('Applies to “On focus” mode only.');
	$f->val($module->autoClose);
	$f->columnWidth = 33;
	$inputfields->add($f);

	// ---

	$f = $inputfields->InputfieldTextTags;
	$f->attr('name', 'disabledDaysOfWeek');
	$f->label = __('Disabled week days');
	foreach($dayOptions as $value => $label) $f->addTag($value, $label);
	$f->val($module->disabledDaysOfWeek);
	$f->placeholder = $clickToSelectDays;
	$f->columnWidth = 33;
	$inputfields->add($f);
	
	$f = $inputfields->InputfieldTextTags;
	$f->attr('name', 'noCheckInDaysOfWeek');
	$f->label = __('Disable start days');
	foreach($dayOptions as $value => $label) $f->addTag($value, $label);
	$f->val($module->noCheckInDaysOfWeek);
	$f->placeholder = $clickToSelectDays;
	$f->columnWidth = 34;
	$inputfields->add($f);
	
	$f = $inputfields->InputfieldTextTags;
	$f->attr('name', 'noCheckOutDaysOfWeek');
	$f->label = __('Disable end days');
	foreach($dayOptions as $value => $label) $f->addTag($value, $label);
	$f->val($module->noCheckOutDaysOfWeek);
	$f->columnWidth = 33;
	$f->placeholder = $clickToSelectDays;
	$inputfields->add($f);

	// ---
	
	$f = $inputfields->InputfieldText;
	$f->attr('name', 'disabledDates');
	$f->label = __('Dates that are not selectable');
	$f->notes = __('Use `YYYY-MM-DD` and separate each date with a space.');
	$f->val(implode(' ', $module->disabledDates));
	$f->placeholder = __('YYYY-MM-DD');
	$f->collapsed = Inputfield::collapsedBlank;
	$inputfields->add($f);
	
	$inputfields->detail = __('Please note: the “Tooltip label” and “Maximum nights” settings require a Save to properly update the example preview.');

	$config->scripts->add($config->urls('InputfieldDateRange') . 'config.js');
}

/**
 * FieldtypeDateRange configuration
 *
 * @param InputfieldWrapper $inputfields
 * @param DateRangeField $field
 *
 */
function FieldtypeDateRangeConfig(InputfieldWrapper $inputfields, Field $field) {

	$fieldtype = $field->type; /** @var FieldtypeDateRange $fieldtype */
	$f = $inputfields->InputfieldSelect;
	$f->attr('name', 'dateOutputFormat');
	$f->label = __('Date output format');
	$f->description =
		__('This format will be used when the page’s output formatting is on.') . ' ' .
		__('When output formatting is off, the format YYYY-MM-DD is used.');
	$testDate = strtotime(date('Y') . '-01-02');
	foreach($fieldtype->getDateOutputFormats() as $fechaFormat => $phpFormat) {
		$label = str_replace([ 'MMMM', 'MMM' ], [ 'Month', 'Mon' ], $fechaFormat);
		$f->addOption($fechaFormat, wireDate($phpFormat, $testDate) . " ($label)");
	}
	$value = $field->get('dateOutputFormat');
	if(empty($value)) $value = 'YYYY-MM-DD';
	$f->val($value);
	$f->required = true;
	$inputfields->add($f);

	$f = $inputfields->InputfieldSelect;
	$f->attr('name', 'dateRangeSeparator');
	$f->label = __('Date range separator');
	$f->description = __('The character that separates “date from” and “date to” when output formatting is on.');
	$dateFormat = $fieldtype->getPhpDateOutputFormat($field);
	$date1 = wireDate($dateFormat, time());
	$date2 = wireDate($dateFormat, time() + (86400 * 3));
	$options = [
		'Spaced' => [
			'S-S' => "$date1 - $date2 (hyphen)",
			'S–S' => "$date1 – $date2 (en-dash)",
			'S—S' => "$date1 — $date2 (em-dash)",
			'S…S' => "$date1 … $date2 (ellipsis)",
			'StoS' => "$date1 to $date2 (to)",
			'S' => "$date1 $date2 (space)",
		],
		'Collapsed' => [
			'-' => "$date1-$date2 (hyphen)",
			'–' => "{$date1}–{$date2} (en-dash)",
			'—' => "{$date1}—{$date2} (em-dash)",
			'…' => "{$date1}…{$date2} (ellipsis)",
		],
	];
	$f->addOptions($options);
	$f->required = true;
	$value = $field->get('dateRangeSeparator');
	if(empty($value)) $value = 'S-S';
	$f->val($value);
	$inputfields->add($f);
	
	$f = $inputfields->InputfieldToggle;
	$f->attr('name', 'collapseRange'); 
	$f->label = __('Collapse range to 1 date if “date from” and “date to” are the same?');
	$date = date('Y-m-d');
	$f->notes = sprintf(__('i.e. “%s - %s” would be reduced to just “%s”'), $date, $date, $date); 
	$f->val((int) $field->get('collapseRange'));
	$inputfields->add($f);
	
	$inputfields->detail = __('The settings above apply only when the page’s output formatting is on.');
}