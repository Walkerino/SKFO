<?php namespace ProcessWire;

/**
 * FieldtypeTable configuration
 * 
 * @param Field $field
 * @param InputfieldWrapper $inputfields
 * @return InputfieldWrapper
 * 
 */
function FieldtypeTable_getConfigInputfields(Field $field, InputfieldWrapper $inputfields) {
	
	$input = $field->wire()->input;
	
	/** @var FieldtypeTable $fieldtype */
	$fieldtype = $field->type;
	$modules = $field->wire()->modules;
	$languages = $field->wire()->languages;
	$numActiveColumns = 0;
	$isPost = $input->requestMethod('POST');
	$colNames = array();
	$uikitLabel = __('Requires a Uikit-based admin theme such as AdminThemeUikit.');

	/** @var InputfieldInteger $f */
	$f = $modules->get('InputfieldInteger');
	$f->attr('name', 'maxCols');
	$f->label = __('Number of Columns');
	$f->attr('value', (int) $field->get('maxCols'));
	$f->inputType = 'number';
	$f->required = true;
	$f->icon = 'tachometer';
	$f->description = __('You may have as many columns as you like. Each group of columns that adds up to 100% width will be placed on separate rows, enabling you to have more input columns than would typically fit in a table.');
	$f->collapsed = Inputfield::collapsedYes;
	$f->notes = __('If you change this value, hit save to see additional column inputs.');
	$inputfields->add($f);

	/** @var InputfieldFieldset $wrap */
	$wrap = $modules->get('InputfieldFieldset');
	$wrap->attr('id', 'table_column_definitions');
	$wrap->label = __('Column Definitions');
	$wrap->icon = 'table';
	$wrap->addClass('InputfieldIsClear');
	$totalWidth = 0;

	for($n = 1; $n <= $field->get('maxCols'); $n++) {

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = sprintf(__('Column %d'), $n);
		$fieldset->addClass('table_column_definition');
		$fieldset->icon = 'arrows';
		$fieldset->addClass('InputfieldIsPrimary InputfieldIsOffset');
		$col = $fieldtype->getColumn($field, $n);

		$name = "col{$n}name";
		$v = $field->get($name);
		if(empty($v)) {
			$columnWidth = 25;
			/** @var InputfieldName $f */
			$f = $modules->get('InputfieldName');
			$f->attr('name', $name);
			$f->label = __('Name');
			$f->notes = __('Use only lowercase: _a-z0-9');
			$f->attr('value', $v);
			$f->required = false;
			$f->columnWidth = $columnWidth;
			$f->description = '';
			$fieldset->add($f);
			$fieldset->collapsed = Inputfield::collapsedYes;
			$isNew = true;
		} else {
			/** @var InputfieldHidden $f */
			$f = $modules->get('InputfieldHidden');
			$f->attr('name', $name);
			$f->attr('value', $v);
			$fieldset->add($f);
			$fieldset->label = $v;
			$columnWidth = 33;
			$numActiveColumns++;
			$isNew = false;
		}
		
		if($isPost) {
			$value = $input->post($name);
			if(!empty($value) && in_array($value, $colNames, true)) {
				$field->wire()->session->error(sprintf(__('Column name "%s" is already in use'), $value));
				$input->post->set($name, '');
			} else if(!empty($value)) {
				$colNames[$name] = $value;
			}
		}

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$name = "col{$n}label";
		$f->attr('name', $name);
		$f->label = __('Label');
		if($isNew) $f->notes = __('Any string value');
		$f->attr('value', $field->$name);
		$f->required = false;
		$f->columnWidth = $columnWidth;
		$f->description = '';
		if($languages) {
			$f->useLanguages = true;
			foreach($languages as $language) {
				/** @var Language $language */
				if($language->isDefault()) continue;
				$_name = $name . $language;
				$_value = $field->get($_name);
				$f->set("value$language", $_value);
			}
		}
		$fieldset->add($f);

		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect');
		$name = "col{$n}type";
		$f->attr('name', $name);
		$f->label = __('Type');
		if($isNew) $f->notes = __('Please select');

		foreach($fieldtype->getColTypes() as $key => $type) {
			$schema = $type['type'];
			$label = $type['label'];
			if(strpos($schema, '[options]')) $label .= ' *';

			$f->addOption($key, $label);
		}

		$value = $field->get($name);
		$f->addOption('delete', __('DELETE THIS COLUMN'));
		$f->attr('value', $value);
		$f->required = $value ? true : false;
		$f->columnWidth = $columnWidth;
		$fieldset->add($f);

		/** @var InputfieldInteger $f */
		$f = $modules->get('InputfieldInteger');
		$name = "col{$n}width";
		$f->attr('name', $name);
		$f->label = __('% Width');
		if($isNew) $f->notes = '10-100 (blank=auto)';
		$f->attr('value', $field->$name > 0 ? $field->$name : '');
		$f->columnWidth = $columnWidth;
		$fieldset->add($f);
		$totalWidth += (int) $field->$name;

		/** @var InputfieldHidden $f */
		$f = $modules->get('InputfieldHidden');
		$name = "col{$n}sort";
		$f->attr('name', $name);
		$f->label = __('Sort');
		$f->attr('value', $n);
		$f->addClass('table_column_sort_value');
		$fieldset->add($f);

		/** @var InputfieldTextarea $f */
		$f = $modules->get('InputfieldTextarea');
		$name = "col{$n}options";
		$f->attr('name', $name);
		$f->attr('value', trim((string) $field->$name));
		$f->label = __('Options (1 per line)');
		$f->addClass('table_column_options');
		// $f->addClass(strpos($schema, '[options]') ? 'has_options' : 'no_options'); 
		$f->notes = __('Optional: To specify separate values and titles, specify **value=title** on the line.');
		$f->showIf = "col{$n}type=select|selectMultiple|asmSelect|radios|checkboxes|textTags";
		$fieldset->add($f);

		/** @var InputfieldSelector $f */
		$f = $modules->get('InputfieldSelector');
		$name = "col{$n}selector";
		$f->attr('name', $name);
		$f->attr('value', trim((string) $field->$name));
		$f->label = __('What pages should be selectable?');
		$f->description = __('Define the selector that will determine which pages are selectable.');
		$f->collapsed = Inputfield::collapsedYes;
		$f->addClass('table_column_selector');
		$f->parseVars = false;
		$f->showIf = "col{$n}type=pageSelect|pageRadios|pageCheckboxes|pageSelectMultiple|pageAsmSelect|pageAutocomplete|pageAutocompleteMultiple";
		$f->allowSystemCustomFields = true;
		$f->allowSystemNativeFields = true;
		$f->allowSystemTemplates = true;
		$fieldset->add($f);

		$name = "col{$n}settings";
		$value = $col['settings'];
		$count = count($value);
		if(!$isNew && $count) {
			/** @var InputfieldTextarea $f */
			$f = $modules->get('InputfieldTextarea');
			$f->attr('rows', max($count, 2));
			$v = '';
			$notes = array();
			foreach($value as $setting => $settingValue) {
				$settingValue = $fieldtype->adjustColumnSetting($setting, $settingValue);
				$v .= "$setting=$settingValue\n";
				$note = FieldtypeTable_getSettingNotes($col, $setting);
				if($note) $notes[$setting] = $note;
			}
			$f->attr('name', $name);
			$f->attr('value', trim("$v"));
			$f->label = __('Settings');
			$f->addClass('table_column_settings');
			$f->collapsed = Inputfield::collapsedYes;
			foreach($notes as $setting => $note) {
				$f->notes .= "**• $setting:** $note\n";
			}
			$note = FieldtypeTable_getSettingNotes($col);
			if($note) $f->notes .= ($f->notes ? "\n" : "") . $note;
			$fieldset->add($f);
		}

		$wrap->add($fieldset);
	}

	$inputfields->add($wrap);

	/** @var InputfieldMarkup $markup */
	$markup = $modules->get('InputfieldMarkup');
	$markup->attr('id', 'table_definition_js');
	$markup->value = "<script" . ">" . file_get_contents(__DIR__ . "/config.js") . "<" . "/script>";
	$inputfields->add($markup);

	$changes = $fieldtype->_exportMode ? array() : $fieldtype->_checkSchema($field, false);
	
	/** @var InputfieldRadios $f */
	$f = $modules->get('InputfieldRadios');
	$f->attr('name', 'commit');
	$f->label = __('Confirm Changes?');
	if(count($changes)) {
		$f->addOption(1, __('Yes'));
		$f->addOption(0, __('No'));
		$f->description = __('Database schema changes that can modify or delete data have been requested. Please confirm these changes by Selecting "Yes" below and saving the field. If you do not want these changes, select "No" and then please return your column(s) to their previous settings/state.');
		$f->notes = implode("\n", $changes);
		$f->icon = 'exclamation-triangle';
		$f->optionColumns = 1;
		$f->attr('value', $field->get('commit'));
		$inputfields->prepend($f);
		$wrap->collapsed = Inputfield::collapsedYes;
	}

	/** @var InputfieldFieldset $fieldset */
	$fieldset = $modules->get('InputfieldFieldset');
	$fieldset->label = __('Settings and Actions');
	$fieldset->icon = 'wrench';
	$fieldset->collapsed = Inputfield::collapsedYes;
	$fieldset->themeOffset = 1;
	$inputfields->add($fieldset);


	if($numActiveColumns) {
		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'renameColumn');
		$f->attr('value', $field->get('renameColumn'));
		$f->label = __('Rename Column');
		$f->icon = 'exchange';
		$f->placeholder = 'old=new';
		$f->collapsed = Inputfield::collapsedBlank;
		$f->description = __('If you want to rename a column, enter **old=new** below, replacing **old** with the current name, and **new** with the new name you want to use.');
		$fieldset->add($f);
	}

	$fieldtype->addHookAfter('ProcessField::fieldSaved', function(HookEvent $event) {
		/** @var Field $field */
		$field = $event->arguments(0);
		if($field->get('commit') && $field->type instanceof FieldtypeTable) {
			/** @var FieldtypeTable $fieldtype */
			$fieldtype = $field->type;
			$fieldtype->_checkSchema($field, true);
			$field->message(__('Database schema changes have been committed.', __FILE__));
			$field->set('commit', null);
			$field->set('renameColumn', null);
			$field->save();
		}
	});

	/** @var InputfieldWrapper $fs */
	$fs = $inputfields->getChildByName('_FieldtypeMultiExtras');
	if($fs) {
		$fs->getParent()->remove($fs);
		$fs->description .=  ' ' . __('Enabling pagination also enables find/filter and sort (by column) features in the page editor.');
		$f = $fs->getChildByName('paginationLimit');
		$f->notes = '';
		$f = $fs->getChildByName('orderByCols');
		if($f) {
			$f->required = true;
			$f->requiredIf = 'paginationLimit>0';
			$f->notes = __('A selection here is required if you enter a pagination limit.');
		}
		
		$fieldset->add($fs);
	}

	$name = 'addRowLabel';
	/** @var InputfieldText $f */
	$f = $modules->get('InputfieldText');
	$f->attr('name', $name);
	$f->label = __('Alternate label for “Add Row” link');
	$f->description = __('If left blank, then the text “Add Row” is used.');
	$f->attr('value', (string) $field->get('addRowLabel'));
	$f->collapsed = Inputfield::collapsedBlank;
	$f->icon = 'plus-circle';
	if($languages) {
		$f->useLanguages = true;
		foreach($languages as $language) {
			if($language->isDefault()) continue;
			$_name = $name . $language;
			$_value = $field->get($_name);
			$f->set("value$language", (string) $_value);
		}
	}
	$fieldset->add($f);
	
	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$f->attr('name', 'useActions');
	$f->label = __('Enable row actions?');
	$f->description = __('Row actions enable you select one or more rows and move or apply actions to them.');
	$f->notes = __('Please note this feature is not available if you are using automatic sorting or pagination.') . ' ' . $uikitLabel;
	$f->icon = 'sliders';
	//$f->showIf = 'orderByCols.count=0, paginationLimit=0';
	if($field->get('useActions')) {
		$f->attr('checked', 'checked');
	} else {
		$f->collapsed = Inputfield::collapsedYes;
	}
	if((int) $field->get('paginationLimit') > 0 || $field->get('orderByCols')) {
		$f->attr('disabled', 'disabled');
		$f->notes = __('This feature is currently disabled because pagination or automatic sorting is active.'); 
	}
	$fieldset->add($f);
	
	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$f->attr('name', 'noDeleteLink');
	$f->label = __('Disable the row delete link?');
	$f->description = __('Since row actions already include a delete action, you can optionally disable the delete link at the end of each row, since it may be redundant for some.');
	$f->icon = 'trash-o';
	$f->showIf = 'useActions=1';
	if($field->get('noDeleteLink')) {
		$f->attr('checked', 'checked');
	} else {
		$f->collapsed = Inputfield::collapsedYes;
	}
	$fieldset->add($f);

	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$f->attr('name', 'useBigDelete');
	$f->label = __('Allow deletion of all rows at once?');
	$f->icon = 'trash';
	$f->description = __('When enabled, double-clicking the trash icon will mark all rows for deletion. The user can optionally delete all rows across all paginations (when in use) by clicking a separate checkbox.');
	$f->showIf = 'noDeleteLink!=1';
	if($field->get('useBigDelete')) {
		$f->attr('checked', 'checked');
	} else {
		$f->collapsed = Inputfield::collapsedYes;
	}
	$fieldset->add($f);

	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$f->attr('name', 'useClone');
	$f->label = __('Allow clone action?');
	$f->icon = 'copy';
	$f->description =
		__('This enables you to clone existing rows by clicking a clone action/icon.') . ' ' .
		__('Please note it does not work with all column types.');
	if($field->get('useClone')) {
		$f->attr('checked', 'checked');
	} else {
		$f->collapsed = Inputfield::collapsedYes;
	}
	$fieldset->add($f);
	
	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$f->attr('name', 'useSmall');
	$f->label = __('Use small inputs?');
	$f->description =
		__('Smaller inputs enable more rows visible at a time.');
	$f->notes = __('Note that not all column types support small inputs so your mileage may vary.') . ' ' . $uikitLabel;
	$f->icon = 'compress';
	if($field->get('useSmall')) {
		$f->attr('checked', 'checked');
	} else {
		$f->collapsed = Inputfield::collapsedYes;
	}
	$fieldset->add($f);

	return $inputfields;
}

/**
 * Get help notes for column settings
 *
 * @param $col
 * @param $setting
 * @return string|null
 *
 */
function FieldtypeTable_getSettingNotes($col, $setting = '') {
	$notes = '';
	$yesNo = ' ' . __('(1=Yes, 0=No)');

	if($setting && $col['type'] === 'toggle') {
		switch($setting) {
			case 'labelType':
				$notes = __('Label type to use: 0=Yes/No, 1=True/False, 2=On/Off, 3=Enabled/Disabled, 100=Custom');
				break;
			case 'useReverse':
				$notes = __('Reverse the order of the Yes/No options?') . $yesNo;
				break;
			case 'useOther':
				$notes = __('Use a 3rd option for “Other”?') . $yesNo;
				break;
			case 'useDeselect':
				$notes = __('Allow toggles to be de-selected, enabling possibility of no selection? Note that value for no selection is blank string.') . $yesNo;
				break;
			case 'defaultOption':
				$notes = __('Default selected value, specify one of “no”, “yes”, “other” or “none”.');
				break;
			case 'yesLabel':
				$notes = __('Custom yes/on label (used only when labelType is 100).');
				break;
			case 'noLabel':
				$notes = __('Custom no/off label (used only when labelType is 100).');
				break;
			case 'otherLabel':
				$notes = __('Label for “Other” option (used only when useOther is 1).');
				break;
		}
	} else if($col['type'] === 'textTags') {
		switch($setting) {
			case 'delimiter':
				$notes = __('Delimiter to use for separating tags. Use one of: "s" for space, "c" for comma or "p" for pipe ("|").');
				break;
			case 'allowUserTags': 	
				$notes = __('Allow user entered tags?') . $yesNo;
				break;
			case 'closeAfterSelect':	
				$notes = __('Close select dropdown box after user selects a tag?') . $yesNo; 
				break;
				/*
			case 'maxItems':	
				$notes = __('Max selectable items: 0 for no limit, 1 for single-select mode, or 2+ to limit selection to that number.'); 
				break;
				*/
			case 'placeholder':	
				$notes = __('Placeholder string to show when no items are selected.');
				break;
		}
	} else if($col['type'] === 'time') {
		switch($setting) {
			case 'timeFormat':
				$notes = 
					__('Time format to use for formatted output.') . ' ' . 
					sprintf(
						__('Uses [PHP datetime format](%s) for hours, minutes, and optionally seconds and am/pm.'), 
						'https://www.php.net/manual/en/datetime.format.php'
					) . ' ' . 
					__('Example: `H:i:s` for 24-hour format like `13:25:00`, `g:ia` for 12 hour format like `1:25pm`');
				break;
			case 'default':	
				$notes = __('Default value for time or blank for none. Use 24-hour format i.e. HH:MM or HH:MM:SS'); 
				break;
			case 'min':	
				$notes = __('Minimum time value in 24-hour format, i.e. HH:MM or HH:MM:SS'); 
				break;
			case 'max':
				$notes = __('Maximum time value in 24-hour format, i.e. HH:MM or HH:MM:SS');
				break;
			case 'step':
				$notes = __('Step value for time in seconds. Use a value of less than 60 to add seconds support into the input.');
				break;
		}
	}
	
	if($notes !== '') return $notes;

	if($setting) switch($setting) {
		case 'settingsField':
			if(strpos($col['type'], 'MCE')) {
				$notes = __('Specify a PW Textarea field name that uses TinyMCE. This field will be for this column’s settings.');
			} else {
				$notes = __('Specify a PW field that uses CKEditor to use for the settings of this column. Note that inline mode is always used, regardless of field settings.');
			}
			break;
		case 'useACF':
			$notes = __('Use Advanced Content Filter (ACF)? Strongly recommended.') . $yesNo;
			break;
		case 'usePurifier':
			$notes = __('Use HTML Purifier? Strongly recommended.') . $yesNo;
			break;
		case 'labelField':
			$notes = __('Page field(s) to use for selectable label. Examples: "title", or "title|path" (uses first non-blank), or "{title}, {path}" (uses all specified fields in your format).');
			break;
		case 'searchField':
			$notes = __('Page field(s) that you want the autocomplete to search. Typically this would be consistent with the labelField.');
			break;
		case 'textformatters':
			$notes = __('Specify Textformatter modules to apply to this text for output formatting. To apply multiple, separate each with a comma. TextformatterEntities is recommended unless you need to allow markup or prefer different Textformatter(s).');
			break;
		case 'formattedValueType':
			$notes = 
				__('How the value should be represented when accessed from a formatted page.') . ' ' . 
				__('One of `value`, `title`, `assoc` or `assoc2`.') . ' ' . 
				__('Use `assoc` to format as a PHP array like `[ "value" => "title" ]`.') . ' ' .
				__('Use `assoc2` to format as a PHP array like `[ "value" => "item value", "title" => "item title" ]`.') . ' ' .
				__('For multi-selection types, either `value` or `title` returns an array of selected values or titles, `assoc` returns associative array, and `assoc2` returns array of associative arrays.'); 
			break;
		case 'placeholder':
			$notes = __('Placeholder text to show when no value is populated.');
			break;
		case 'datepicker':
			$notes = __('What type of date picker to show: 0=None, 1=Show on button click, 2=Show always visible inline date picker, 3=Show date picker on field focus (recommended value).');
			break;
		case 'dateInputFormat':
			$notes = __('Date format to use for input when editing the page.');
			break;
		case 'dateOutputFormat':
			$notes = __('Date format to use for output.');
			break;
		case 'timeInputFormat':
			$notes = __('Time format to use for input when editing the page.');
			break;
		case 'timeOutputFormat':
			$notes = __('Time format to use for output.');
			break;
		case 'defaultToday':
			$notes = __('Specify 1 to make new roles default to the date/time of today, or 0 to leave blank.');
			break;
		case 'yearRange':
			$notes = __('Range of years to show in date picker in format "-10:+10" where "-10" is number of years in the past and "+10" is number of years in the future.');
			break;
		case 'inherit':
			$notes = __('Inherit value from default language when language value is blank?') . $yesNo;
			break;
		case 'digits':
			$notes = __('Total number of digits');
			break;
		case 'precision':
			$notes = __('Number of digits after the decimal');
			break;
		case 'extensions':	
			$notes = __('Space-separated string of allowed file extensions.'); 
			break;
		case 'thumbSize':	
			$notes = __('Dimensions for thumbnail preview image (width x height). Use 0 for proportional width or height.');
			break;
		case 'thumbSize2x':
			$notes = __('Dimensions for thumbnail preview image used on hidpi devices, should be double that of thumbSize. Blank to disable.'); 
			break;
		case 'maxBytes':	
			$notes = __('Maximum bytes for uploaded file or 0 for no max (5000000 is 5 megabytes).'); 
			break;
		case 'min': 	
			$notes= __('Minimum allowed value');
			break;
		case 'max':
			$notes = __('Maximum allowed value');
			break;
		case 'inline':	
			$notes = __('Specify inline=1 to attempt display of inputs on single line (rather than on separate lines)');
			break;
		case 'noblank':	
			$notes = __('Specify noblank=1 to not show a blank/deselected option.');
			break;
		default:
			if(strpos($col['valid'], 'Page') === 0) {
				if($setting == 'validate') {
					$notes = __('Validates that Pages in value matches your selector on page save.');
				} else if($setting == 'default') {
					$notes = __('Optionally specify default selected page ID (number). For multiple, separate each with a comma.');
				}
			}
	} else {
		switch($col['type']) {
			case 'textareaCKE':
				$notes = __('You may also override any other CKEditor field settings by specifying "property=value" in here.');
				break;
			case 'textareaMCE':
				$notes = __('You may also override any other TinyMCE field settings by specifying `property=value` in here.');
				break;
			case 'toggle':
				$notes = __('The value for a toggle field is `0` for “No”, `1` for “Yes”, `2` for “Other” or a blank string for no selection.');
				break;
		}
	}
	if($notes === '') $notes = null;
	return $notes;
}