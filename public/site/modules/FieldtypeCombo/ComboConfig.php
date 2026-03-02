<?php namespace ProcessWire;

/**
 * ProcessWire ProFields Combo Configuration Tool
 *
 * Part of the ProFields package.
 * Please do not distribute.
 *
 * Copyright (C) 2024 by Ryan Cramer
 *
 * https://processwire.com
 * 
 * @todo add option to clone existing Combo subfield during configuration. 
 * C0MBOPW3O227
 *
 */
class ComboConfig extends Wire {
	
	const debug = false;
	
	/**
	 * @var ComboSettings
	 * 
	 */
	protected $settings;

	/**
	 * @var Field|Inputfield
	 * 
	 */
	protected $field;

	/**
	 * @var int
	 * 
	 */
	protected $defaultQty = 3;

	/**
	 * @var array
	 * 
	 */
	protected $textformatters = array();

	/**
	 * Types that support Textformatter modules
	 * 
	 * @var array
	 * 
	 */
	protected $defaultTextTypes = array(
		'CKEditor',
		'Email',
		'Text',
		'Textarea',
		'TinyMCE',
		'URL',
	);

	/**
	 * Base types that have been added later and should always be available
	 * 
	 * @var string[] 
	 * 
	 */
	protected $addBaseTypes = array(
		'File',
		'Image',
	);

	/**
	 * Construct
	 * 
	 * @param ComboSettings $settings
	 * @param Field|Inputfield $field
	 * 
	 * 
	 */
	public function __construct(ComboSettings $settings, $field) {
		parent::__construct();
		$this->settings = $settings;
		$this->field = $field;
		$field->wire($this);
		if($field instanceof Field) {
			$this->addHookAfter('ProcessField::buildEditFormAdvanced', $this, 'hookProcessFieldBuildEditFormAdvanced');
		}
	}

	/**
	 * @return Field|Inputfield
	 * 
	 */
	public function getField() {
		return $this->field;
	}

	/**
	 * Get valid Inputfield module types
	 *
	 * @return array of [ 'Name' => 'Label' ]
	 *
	 */
	protected function getValidInputfieldTypes() {

		$validTypes = array();
		$languages = $this->wire()->languages;
		$modules = $this->wire()->modules;
		
		if($this->field instanceof InputfieldCombo) {
			$baseTypes = $this->field->get('baseTypes');
		} else {
			$baseTypes = $this->wire()->modules->getConfig('InputfieldCombo', 'baseTypes');
		}
		
		if(!is_array($baseTypes) || empty($baseTypes)) {
			require_once(__DIR__ . '/ComboModuleConfig.php');
			$moduleConfig = new ComboModuleConfig();
			$baseTypes = $moduleConfig->getDefaultBaseTypes();
		}
		
		foreach($this->addBaseTypes as $baseType) {
			if(!in_array($baseType, $baseTypes)) $baseTypes[] = $baseType;
		}

		foreach($baseTypes as $name) {
			$moduleName = "Inputfield$name";
			if(!$modules->isInstalled($moduleName)) continue;
			$title = $modules->getModuleInfoProperty($moduleName, 'title');
			if(empty($title)) $title = $name;
			$validTypes[$name] = array(
				'name' => $name, 
				'moduleName' => $moduleName,
				'title' => $title,
				'useLanguages' => false,
			);
			if($languages && in_array($name, $this->defaultTextTypes)) {
				$name = "{$name}_Language";
				$validTypes[$name] = array(
					'name' => $name,
					'moduleName' => $moduleName, 
					'title' => $title . ' ' . $this->_('(multi-language)'),
					'useLanguages' => true
				);
			}
		}

		ksort($validTypes);

		return $validTypes;
	}


	/**
	 * Get qty setting or default if none defined
	 * 
	 * @return int
	 * 
	 */
	public function qty() {
		$qty = (int) $this->settings->findMaxQty();
		if($qty < 1) $qty = $this->defaultQty;
		return $qty;
	}

	/**
	 * Get array of installed Textformatter modules [ 'moduleName' => 'title' ]
	 * 
	 * @return array
	 * 
	 */
	protected function textformatters() {
		if(!empty($this->textformatters)) return $this->textformatters;
		$textformatters = $this->wire()->modules->findByPrefix('Textformatter', 1);
		foreach($textformatters as $name => $info) {
			$this->textformatters[$name] = isset($info['title']) ? $info['title'] : $name;
		}
		return $this->textformatters;
	}

	/**
	 * Get Inputfield to use for configuration
	 * 
	 * @param string $thisType Inputfield type name i.e. "Select", "Text", "Text_Language", etc.
	 * @param array $validTypes Result from getValidInputfieldTypes() method
	 * @return Inputfield
	 * 
	 */
	protected function getInputfield($thisType, array $validTypes) {
		$modules = $this->wire()->modules;
		$typeInfo = $validTypes[$thisType];
		/** @var Inputfield $inputfield */
		$inputfield = $modules->get($typeInfo['moduleName']);
		if(!$inputfield) $inputfield = $modules->get('InputfieldText');
		if($typeInfo['useLanguages']) $inputfield->useLanguages = true;
		if($this->field instanceof ComboField) {
			$inputfield->hasField = $this->field;
			$inputfield->hasFieldtype = $this->field->type;
		} else {
			$inputfield->hasFieldtype = false;
		}
		return $inputfield;
	}
	
	/**
	 * Update the given $typeInputfield to namespace it for given prefix
	 * 
	 * @param Inputfield $typeInputfield Inputfield being namespaced
	 * @param Inputfield $inputfield Inputfield being configured
	 * @param $prefix
	 * 
	 */
	protected function namespaceSettingInputfield(Inputfield $typeInputfield, Inputfield $inputfield, $prefix) {
		
		// namespace each of the configurable Inputfields for the one we are configuring
		$name = $typeInputfield->name;
		$id = $typeInputfield->id;
		$us = strpos($name, '_') === 0 ? '_' : '';
		$usid = strpos($id, '_') === 0 ? '_' : '';
		$newName = $us . $prefix . $name;
		$typeInputfield->name = $newName;
		
		if($id) {
			$typeInputfield->id = str_replace('_Inputfield_', '_', $usid . $prefix . $id);
		}
	
		if($name === 'showIf' || $name === 'requiredIf') {
			$typeInputfield->notes .= "\n" . 
				sprintf(
					$this->_('To build a condition from a subfield in this Combo field, specify `%s_subfield=value`, replacing `subfield` with the name of the Combo subfield.'),
					$this->field->name
				);
			
		} else if($typeInputfield instanceof InputfieldCheckbox) {
			// ensure setting value=1 automatically makes it checked
			$typeInputfield->autocheck = true;
			
		} else if($inputfield instanceof InputfieldDatetime) { 
			// special case for InputfieldDatetime 
			/** @var InputfieldRadios $typeInputfield */
			if($name === '_dateInputFormat' || $name === '_timeInputFormat') {
				// update the 'onchange' attribute to point to the correct input 
				$find = "Inputfield$name";
				$replace = str_replace('__', '_', ltrim($typeInputfield->name, '_'));
				$typeInputfield->attr('onchange', str_replace($find, $replace, $typeInputfield->attr('onchange')));
			} else if($name === 'timeInputSelect' && $this->wire()->input->requestMethod('POST')) {
				// prevents this unnecessary error message:
				// Warning ('showIf' from 'Time Input Type'): dependency field 'i16__timeInputFormat' is not present in this form.
				$typeInputfield->showIf = '';
			}
		}
			
		$value = $this->settings->getSetting($newName);
		if($value !== null) $typeInputfield->val($value);
	
		// multi-language settings
		if($typeInputfield->useLanguages) {
			$languages = $this->wire()->languages;
			if($languages) foreach($languages as $language) {
				/** @var Language $language */
				if($language->isDefault()) continue;
				$value = $this->settings->getSetting($newName . $language->id);
				if($value !== null) $typeInputfield->set("value$language->id", $value);
			}
		}

		// update any conditionals for the namespace too
		foreach(array('showIf', 'requiredIf') as $propertyIf) {
			$valueIf = $typeInputfield->getSetting($propertyIf);
			if(empty($valueIf)) continue;
			// $valueIf = $prefix . $valueIf;
	
			$selectors = new Selectors($valueIf);
			$this->wire($selectors);
			$valueIf = '';
			foreach($selectors as $selector) {
				$sf = $selector->field();
				$sf = "$prefix$sf";
				$sf = str_replace('|', "|$prefix", $sf);
				if(strlen($valueIf)) $valueIf .= ', ';
				$valueIf .= $sf . $selector->operator() . $selector->value();
			}
			
			$typeInputfield->set($propertyIf, $valueIf);
		}
	}

	/**
	 * @param FieldtypeCombo $fieldtype
	 * @param string $inputType
	 * @param string $prefix
	 * @return InputfieldFieldset
	 * 
	 */
	protected function getSchemaFieldset(FieldtypeCombo $fieldtype, $inputType, $prefix) {
	
		$modules = $this->wire()->modules;
		$comboSchema = $fieldtype->comboSchema();
		$schemaTypeValue = $this->settings->getSetting($prefix . 'schemaType');
		$schemaDataValue = $this->settings->getSetting($prefix . 'schemaData');
		$xidxValue = (int) $this->settings->getSetting($prefix . 'xidx');
		
		$defaultSchemaTypeValue = $comboSchema->getDefaultSchemaType($inputType);
		$defaultSchemaTypeLabel = '';
		
		if(!$schemaTypeValue) $schemaTypeValue = 'auto';

		/** @var InputfieldFieldset $fs */
		$fs = $modules->get('InputfieldFieldset');
		$fs->attr('name', "_{$prefix}combo_schema");
		$fs->label = $this->_('Database schema');
		$fs->collapsed = Inputfield::collapsedYes;
		$fs->icon = 'database';

		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect');
		$f->name = $prefix . 'schemaType';
		$f->label = $this->_('Schema storage type');
		$f->description = $this->_('Warning, changing the storage type can result in data loss so be sure you know what you are doing before changing this.');
		$schemaTypes = $comboSchema->getSchemaTypes();
		$leads = array(
			's' => $this->_('String'),
			'n' => $this->_('Number'),
			'o' => $this->_('Other'),
		);
		$f->addOption('auto', $this->_('Default/auto (recommended)'));
		foreach($schemaTypes as $schemaType => $schemaInfo) {
			$c = substr($schemaType, 0, 1);
			$lead = isset($leads[$c]) ? $leads[$c] : $leads['o'];
			if(isset($schemaInfo['min']) && isset($schemaInfo['max'])) {
				$extra = ' ' . sprintf($this->_('(%1$s to %2$s)'), $schemaInfo['min'], $schemaInfo['max']);
			} else {
				$extra = '';
			}
			$label = "$lead: $schemaInfo[label]$extra";
			// $options[$schemaType] = $label;
			$f->addOption($schemaType, $label); 
			if($schemaType === $defaultSchemaTypeValue) $defaultSchemaTypeLabel = $label;
		}
		$f->notes = sprintf($this->_('The default/auto storage type for %1$s is: %2$s'), "**$inputType**", "**$defaultSchemaTypeLabel**");
		$f->val($schemaTypeValue);
		$fs->add($f);

		/** @var InputfieldText $f2 */
		$f2 = $modules->get('InputfieldText');
		$f2->name = $prefix . 'schemaData';
		$f2->label = $this->_('Schema options');
		$f2->icon = 'database';
		$f2->showIf = "$f->name=n-decimal|n-decimal-u|s-char|s-char-var";
		$f2->description =
			$this->_('For decimal types, specify a value like `10,2` where `10` is precision (total digits) and `2` is scale (decimal digits).') . ' ' .
			$this->_('For char/varchar types, specify a value like `90` which is the maximum length allowed (max 191).');
		$f2->val($schemaDataValue);
		$fs->add($f2);
	
		/** @var InputfieldToggle $f */
		$f = $modules->get('InputfieldToggle'); 
		$f->name = $prefix . 'xidx'; 
		$f->label = $this->_('Index this subfield?'); 
		$f->description = $this->_('If this column does not need to be used in specific search queries, you can disable the index to use less resources.');
		$f->labelType = InputfieldToggle::labelTypeCustom;
		$f->useReverse = true;
		$f->yesLabel = $this->_('No');
		$f->noLabel = $this->_('Yes');
		$f->val($xidxValue);
		$fs->add($f);
		
		$fs->showIf = 'modSchema=1';
		
		return $fs;
	}

	/**
	 * Get settings for given Inputfield module
	 * 
	 * @param Inputfield $inputfield
	 * @param string $prefix
	 * @param InputfieldWrapper $parentFieldset
	 * @return InputfieldWrapper
	 *
	 */
	protected function getInputfieldSettingsFieldset(Inputfield $inputfield, $prefix, InputfieldWrapper $parentFieldset) {
	
		$modules = $this->wire()->modules;
		$useTextformatters = false;
		$usePageReference = false;
		$useLanguages = false;
		$usePagefiles = false;
		$inputType = '';
		$subfieldName = '';

		// first set any config values to the Inputfield
		foreach($this->settings->getSubfieldData($prefix) as $key => $value) {
			if($key === 'type') {
				$inputType = strtolower($value);
				if($value === 'Page') {
					$usePageReference = true;
				} else if(in_array($value, $this->defaultTextTypes)) {
					$useTextformatters = true;
				} else if($value === 'File' || $value === 'Image') {
					$usePagefiles = true;
				} else if(strpos($value, '_Language')) {
					$useTextformatters = true;
					$useLanguages = true;
				} else if(wireInstanceOf("Inputfield$value", "InputfieldText")) {
					$useTextformatters = true;
				}
				continue;
			} else if($key === 'name') {
				$subfieldName = $value;
				continue;
			}
				
			$inputfield->set($key, $value);
		}
	
		$hasFieldtype = $inputfield->hasFieldtype;
		$inputfield->hasFieldtype = false;
		$typeInputfields = $inputfield->getConfigInputfields();
		if($hasFieldtype) $inputfield->hasFieldtype = $hasFieldtype;

		// adding the visibility fieldset generates a recursive error
		// so we add the inputfields within visibility independently
		$visibilityInputfields = [];
		
		// namespace the Inputfield settings
		foreach($typeInputfields->getAll([ 'withWrappers' => true ]) as $typeInputfield) {
			/** @var Inputfield $typeInputfield */
			$name = $typeInputfield->name; // name before namespacing 
			$this->namespaceSettingInputfield($typeInputfield, $inputfield, $prefix);
			
			// identify the settings in visibility fieldset for later use
			$parent = $typeInputfield->getParent();
			if($parent && $parent->name === 'visibility') {
				$visibilityInputfields[$name] = $typeInputfield;
			}
		}

		/** @var InputfieldFieldset $fs */
		$fs = $modules->get('InputfieldFieldset');
		$fs->name = "_{$prefix}_config";
		$fs->label = $this->_('Settings');
		$fs->collapsed = Inputfield::collapsedYes;
		$fs->addClass('InputfieldNoFocus');
		$fs->icon = 'sliders';

		/** @var InputfieldFieldset $fss */
		$fss = $modules->get('InputfieldFieldset');
		$fss->name = "_{$prefix}_labels";
		$fss->label = $this->_('Additional labels');
		$fss->collapsed = Inputfield::collapsedYes;
		$fss->icon = 'paragraph';
		$parentFieldset->add($fss);
		
		/** @var InputfieldTextarea $f1 */
		$f1 = $modules->get('InputfieldTextarea');
		$f1->name = $prefix . 'description';
		$f1->label = $this->_('Description');
		$f1->rows = 3;
		$f1->icon = 'align-left';
		$f1->columnWidth = 50;
		$this->settings->setInputfieldValue($f1);
		$fss->add($f1);

		/** @var InputfieldTextarea $f2 */
		$f2 = $modules->get('InputfieldTextarea');
		$f2->name = $prefix . 'notes';
		$f2->label = $this->_('Notes');
		$f2->rows = 3;
		$f2->icon = 'paperclip';
		$f2->columnWidth = 50;
		$this->settings->setInputfieldValue($f2);
		$fss->add($f2);
		
		if($inputfield->hasFieldtype !== false) {
			/** @var InputfieldName $f3 */
			$f3 = $modules->get('InputfieldText'); 
			$f3->name = $prefix . 'icon';
			$f3->label = $this->_('Icon');
			$this->settings->setInputfieldValue($f3, false);
			$f3->icon = $f3->val() ? $f3->val() : 'question-circle';
			$f3->attr('list', 'combo-icon-names'); 
			$fss->add($f3);
			$f1->columnWidth = 33;
			$f2->columnWidth = 34;
			$f3->columnWidth = 33;
		}
	
		/** @var InputfieldHidden $f */
		$f = $modules->get('InputfieldHidden');
		$f->name = $prefix . 'ok';
		$f->val('1');
		$fs->add($f);
	
		if($useTextformatters && $inputfield->hasFieldtype !== false) {
			/** @var InputfieldAsmSelect $f */
			$f = $modules->get('InputfieldAsmSelect');
			$f->name = $prefix . 'textformatters';
			$f->label = $this->_('Text formatters');
			$f->description = 
				$this->_('Text formatter modules to be applied to page value when output formatting is on.') . ' ' . 
				$this->_('For most text types, you should at least select the HTML entity encoder.');
			$f->icon = 'text-width';
			$f->addOptions($this->textformatters());
			$this->settings->setInputfieldValue($f, false);
			$f->collapsed = Inputfield::collapsedBlank;
			$parentFieldset->add($f);
		} else {
			/** @var InputfieldHidden $f */
			$f = $modules->get('InputfieldHidden');
			$f->name = $prefix . 'textformatters';
			$f->val('');
			$fs->add($f);
		}
		
		if($usePageReference && $inputfield->hasFieldtype !== false) {
			/** @var InputfieldRadios $f */
			$f = $modules->get('InputfieldRadios');
			$f->name = $prefix . 'one';
			$f->label = $this->_('Page reference value type');
			$f->icon = 'files-o';
			$f->addOption(0, $this->_('PageArray (multi-value)'));
			$f->addOption(1, $this->_('Page or NullPage (single-value)'));
			$this->settings->setInputfieldValue($f, false); 
			$f->val((int) $f->val());
			$fs->add($f);
		}
		
		if($usePagefiles) {
			// InputfieldFile or InputfieldImage 
			$this->getInputfieldFileSettings($prefix, $typeInputfields, $fs, $subfieldName);
		}
		
		if($useLanguages) {
			/** @var InputfieldRadios $f */
			$f = $modules->get('InputfieldRadios');
			$f->name = $prefix . 'xlang';
			$f->label = $this->_('Inherit default language value when blank?');
			$f->icon = 'language';
			$f->notes = $this->_('Applies only to non-default formatted language values on the front-end of your site.');
			$f->addOption(0, $this->_('Yes'));
			$f->addOption(1, $this->_('No'));
			$f->optionColumns = 1;
			$this->settings->setInputfieldValue($f, false);
			$f->val((int) $f->val());
			$fs->add($f);
		}
		
		// update collapsed setting for visibility Inputfields
		foreach($visibilityInputfields as $name => $f) {
			$f->collapsed = Inputfield::collapsedBlank;
			if($name === 'collapsed' && "$f->value" === "0") $f->collapsed = Inputfield::collapsedYes;
			$fs->add($f);
		}

		// if visibility fieldset is not skipped it generates a recursive error
		// so we add the visibility Inputfields to our main fieldset instead
		foreach($typeInputfields as $f) {
			$name = $f->name;
			if($name === 'visibility') continue;
			$fs->add($f);
		}
		
		// move user-specified select options to parent fieldset (more prominent)
		if($inputfield instanceof InputfieldSelect) {
			$optionsConfig = $fs->getChildByName($prefix . 'options');
			if($optionsConfig) {
				$optionsConfig->parent->remove($optionsConfig);
				$parentFieldset->add($optionsConfig);
				if(!$optionsConfig->icon) $optionsConfig->icon = 'list-ul';
			}
		}
		
		// add Settings fieldset
		$parentFieldset->add($fs);

		// move user-specified column with to parent fieldset (more prominent)
		$widthConfig = $fs->getChildByName($prefix . 'columnWidth');
		if($widthConfig) {
			$widthConfig->addClass('InputfieldNoFocus', 'wrapClass');
			$widthConfig->parent->remove($widthConfig);
			$parentFieldset->add($widthConfig);
		}
		
		if($inputfield->hasFieldtype !== false) {
			/** @var InputfieldText $f */
			$f = $modules->get('InputfieldText');
			$f->name = $prefix . 'move';
			$f->label = $this->_('Custom form placement');
			$f->description =
				$this->_('You can optionally detach this subfield from this Combo field and make it appear somewhere else in the form.') . ' ' .
				$this->_('To do this, specify the name of the field you want this subfield to appear after, i.e. `body`.') . ' ' .
				$this->_('To make it appear before the field (rather than after), prepend a minus sign to the field name, i.e. `-body`.');
			$f->notes = $this->_('If specified field is not present, no custom placement will occur and subfield will appear in regular order.');
			$this->settings->setInputfieldValue($f, false);
			$f->collapsed = Inputfield::collapsedBlank;
			$f->attr('list', 'combo-move-names');
			$f->icon = 'exchange';
			$f->addClass('ComboDetachInput');
			$parentFieldset->add($f);
		}

		// additional settings present when a PW native Field 
		if($inputfield->hasFieldtype instanceof FieldtypeCombo && $this->settings->modSchema) {
			// database schema configuration
			$dbfs = $this->getSchemaFieldset($inputfield->hasFieldtype, $inputType, $prefix);
			if($dbfs) $parentFieldset->add($dbfs);
		}

		return $fs;
	}

	/**
	 * Update settings for InputfieldFile/InputfieldImage
	 * 
	 * @param string $prefix
	 * @param InputfieldWrapper $typeInputfields
	 * @param InputfieldWrapper $fs
	 * @param string $subfieldName
	 *
	 */
	protected function getInputfieldFileSettings($prefix, InputfieldWrapper $typeInputfields, InputfieldWrapper $fs, $subfieldName) {
		
		$modules = $this->wire()->modules;
		$removes = array('unzip', 'overwrite');
	
		foreach($removes as $name) {
			$f = $typeInputfields->getChildByName($prefix . $name);
			if(!$f) continue;
			$parent = $f->getParent();
			if(!$parent) continue;
			$parent->remove($f);
			if(!$parent->children()->count()) $parent->getParent()->remove($parent);
		}
		
		/** @var InputfieldInteger $f */
		$f = $typeInputfields->getChildByName($prefix . 'maxFiles');
		if($f) {
			$f->notes = trim($f->notes . "\n" .
				$this->_('If you enter `1` the formatted value of this subfield is a Pagefile or Pageimage (single) object.') . ' ' .
				$this->_('Otherwise the formatted value is a Pagefiles or Pageimages (array) object.')
			);
		}
		
		$f = $typeInputfields->getChildByName($prefix . 'extensions'); 
		if($f) {
			/** @var InputfieldFile $if */
			$if = $this->wire()->modules->get('InputfieldFile');
			$if->extensions = $f->val();
			$fieldtype = $this->wire()->fieldtypes->get('FieldtypeFile'); /** @var FieldtypeFile $fieldtype */
			if(method_exists($fieldtype, 'getValidFileExtensions')) {
				$info = $fieldtype->getValidFileExtensions($if);
				if(!empty($info['invalid'])) {
					$error = sprintf(
						$this->_('File extension “%1$s” used by subfield “%2$s” requires an appropriate [FileValidator](%3$s) module'), 
						strtoupper(implode(', ', $info['invalid'])),
						$subfieldName,
						'https://processwire.com/modules/category/file-validator/'
					);
					$this->error($error, Notice::allowMarkdown);
				}
			}
		}
		
		$f = $typeInputfields->getChildByName($prefix . 'useTags');
		if(!$f) {
			$f = $typeInputfields->getChildByName($prefix . 'descriptionRows');
			$fieldset = $f ? $f->getParent() : $fs; 
			/** @var InputfieldRadios $f */
			$f = $modules->get("InputfieldRadios");
			$f->attr('name', $prefix . 'useTags');
			$f->label = $this->_('Use file tags?');
			$f->icon = 'tags';
			$selectsTagsLabel = $this->_('User selects predefined tags');
			$entersTagsLabel = $this->_('User enters their own tags');
			$f->addOption(FieldtypeFile::useTagsOff, $this->_('Disabled'));
			$f->addOption(FieldtypeFile::useTagsNormal, $entersTagsLabel);
			$f->addOption(FieldtypeFile::useTagsPredefined, $selectsTagsLabel);
			$f->addOption(FieldtypeFile::useTagsNormal | FieldtypeFile::useTagsPredefined, $selectsTagsLabel . ' + ' . $entersTagsLabel);
			$f->val((int) $f->val());
			$this->settings->setInputfieldValue($f);
			if(!$f->val()) $f->collapsed = Inputfield::collapsedYes;
			$fieldset->add($f);

			/** @var InputfieldText $f */
			$f = $modules->get('InputfieldText');
			$f->attr('name', $prefix . 'tagsList');
			$f->label = $this->_('Predefined tags');
			$f->description = $this->_('Enter tags separated by a space. Tags may contain letters, digits, underscores or hyphens.');
			$f->icon = 'tags';
			$f->showIf = $prefix . 'useTags>1';
			$this->settings->setInputfieldValue($f);
			$fieldset->add($f);
		}
	}

	/**
	 * Get the Inputfields necessary to configure this field 
	 *
	 * @param InputfieldWrapper $inputfields
	 *
	 */
	public function getConfigInputfields(InputfieldWrapper $inputfields) {
		
		$config = $this->wire()->config;
		$config->scripts->add($config->urls('InputfieldCombo') . 'ComboConfig.js');
		$config->styles->add($config->urls('InputfieldCombo') . 'ComboConfig.css');
		
		$modules = $this->wire()->modules;
		$input = $this->wire()->input;
		$validTypes = $this->getValidInputfieldTypes();
		$order = array();
		$subfieldNames = array();
		$subfieldTypes = array();
		$isPost = $input->requestMethod('POST');
		$qty = $isPost ? (int) $input->post('qty') : 0;
		
		if($qty) {
			if($qty > $this->settings->qty) $this->settings->qty = $qty;
		} else {
			$qty = $this->settings->findMaxQty();
		}
		
		$inputfields->addClass('ComboConfig');

		/** @var InputfieldInteger $f */
		$f = $modules->get(self::debug ? 'InputfieldText' : 'InputfieldHidden');
		$f->attr('name', 'qty');
		$f->attr('id', 'ComboConfigQty');
		$f->label = 'Number of inputs';
		$f->val($qty);
		$f->set('themeOffset', 2);
		$inputfields->add($f);
	
		/** @var InputfieldText $f */
		$f = $modules->get(self::debug ? 'InputfieldText' : 'InputfieldHidden');
		$f->name = 'order';
		$f->attr('id', 'ComboConfigOrder');
		if(self::debug) $f->set('themeOffset', 2);
		$orderInputfield = $f;
		$inputfields->add($f);
		
		/** @var InputfieldWrapper $wrapper */
		//$wrapper = $modules->get('InputfieldMarkup');
		$wrapper = $modules->get('InputfieldFieldset');
		$wrapper->label = $this->_('Combo subfields');
		$wrapper->attr('id', 'ComboConfigFields');
		$wrapper->icon = 'object-group';
		$inputfields->prepend($wrapper);
		
		$items = array();
		$type0 = null; // type <select> used for add-new item
		
		for($n = 0; $n <= $qty; $n++) {
			// n==0 for "new subfield" template, 1+ for existing Combo subfields

			$prefix = $this->settings->prefix($n);
			$thisType = (string) $this->settings->get($prefix . 'type');
			$thisName = (string) $this->settings->get($prefix . 'name');
		
			if($n) {
				if(!$thisType && $isPost) $thisType = $input->post->fieldName($prefix . 'type');
				if(!$thisType || $thisType === 'DELETE') continue; // does not exist
				if(!$thisName && $isPost) $thisName = $input->post->fieldName($prefix . 'name');
				if(!$thisName) $thisName = "i$n";
			}

			$thisIsNew = false;
			$thisLabel = (string) $this->settings->get($prefix . 'label');
			$thisWidth = (int) $this->settings->get($prefix . 'columnWidth');
			$thisOk = (int) $this->settings->get($prefix . 'ok'); // is it already configured?
			$thisIcon = $this->settings->get($prefix . 'icon');
			$thisMove = $this->settings->get($prefix . 'move'); // is it moved elsewhere? (detached)
	
			if(!$thisOk && $isPost) $thisOk = (int) $input->post($prefix . 'ok');		
			if(!$n) $thisLabel = $this->_('New subfield'); 
			if(self::debug) $thisLabel .= " #$n";
			
			if($thisIcon && !$this->iconNames($thisIcon)) {
				$thisIcon = '';
				$this->settings->set($prefix . 'icon', ''); 
			}

			/** @var InputfieldFieldset $fieldset */
			$fieldset = $modules->get('InputfieldFieldset');
			$fieldset->attr('id+name', "_{$prefix}fieldset");
			$fieldset->label = $thisLabel ? $thisLabel : sprintf($this->_('Input #%d'), $n);
			$fieldset->icon = $thisIcon ? $thisIcon : 'arrows';
			$fieldset->addClass('ComboConfigField');
			$fieldset->addClass('InputfieldNoFocus', 'wrapClass');
			$fieldset->wrapAttr('data-combo-n', $n); 
			$fieldset->wrapAttr('data-combo-w', $thisWidth ? $thisWidth : 100);
			if($thisMove) $fieldset->addClass('ComboStateDetached'); 
			
			if($n && $thisOk) {
				$fieldset->collapsed = Inputfield::collapsedYes;
			} else if($n) {
				$thisIsNew = true;
				$fieldset->description = $this->_('Please finish configuring this field and then Save.');
			} else {
				$fieldset->wrapAttr('hidden', 'hidden');
				$fieldset->description = $this->_('Select a type, enter a label, then Save and return here to finish configuring this new subfield.'); 
			}
			
			$items[$n] = $fieldset;
			
			/** @var InputfieldText $f */
			$f = $modules->get('InputfieldText');
			$f->name = $prefix . 'label';
			$f->label = $this->_('Label');
			if($n) $f->val((string) $thisLabel);
			$f->columnWidth = 33;
			$f->addClass('ComboFieldLabel');
			$this->settings->setInputfieldValue($f);
			$fieldset->add($f);
			
			/** @var InputfieldName $f */
			$f = $modules->get('InputfieldName');
			$f->name = $prefix . 'name';
			$f->label = $this->_('Name');
			$f->description = '';
			$f->val((string) $thisName);
			$f->columnWidth = 34;
			$f->required = false;
			$f->maxlength = 58;
			$f->placeholder = 'a-zA-Z0-9_';
			$f->pattern = '^[a-zA-Z][_a-zA-Z0-9]*$';
			$f->addClass('ComboFieldName');
			$fieldset->add($f);
			
			if($n) {
				$subfieldNames[] = "$thisName";
				$subfieldTypes[] = "$thisType";
			}

			/** @var InputfieldSelect $f */
			$f = $modules->get('InputfieldSelect');
			$f->name = $prefix . 'type';
			$f->label = $this->_('Type');
			if(!$thisType) $f->addOption('');
			foreach($validTypes as $name => $info) {
				$attrs = array();
				$f->addOption($name, $info['title'], $attrs); 
			}
			if($n) {
				$f->required = true;
			} else {
				$type0 = $f;
			}
			$f->addOption('DELETE', $this->_('DELETE THIS FIELD'));
			$f->val($thisType);
			$f->columnWidth = 33;
			$f->addClass('ComboFieldType');
			$f->attr('data-prev-type', ''); // for JS
			$fieldset->add($f);
			
			if(!$thisType || !isset($validTypes[$thisType])) continue;
			
			// prevent processing of Inputfields that haven’t been displayed to user yet
			// to avoid unnecessary “missing required field” errors and such
			if($isPost && $thisIsNew) continue;

			// get config settings from selected Inputfield type
			$inputfield = $this->getInputfield($thisType, $validTypes);
			$fs = $this->getInputfieldSettingsFieldset($inputfield, $prefix, $fieldset);
			if($fs && $thisIsNew) $fs->collapsed = Inputfield::collapsedNo;
		}
		
		$numSubfields = count($items) - 1; // -1 to subtract the template item (num=0)

		/*		
		if($numSubfields && $type0 && count($subfieldTypes)) {
			// @todo clone option for subfields
			$optgroupOptions = array();
			foreach($subfieldNames as $n => $subfieldName) {
				$subfieldType = $subfieldTypes[$n];
				$optgroupOptions["clone-$n"] = "$subfieldName ($subfieldType)";
			}
			ksort($optgroupOptions);
			$type0->addOptions(array($this->_('Clone subfield') => $optgroupOptions)); 
		}
		*/

		// add in configured order
		foreach(explode(',', $this->settings->order) as $n) {
			$n = (int) $n;
			if(!isset($items[$n])) continue;
			$fieldset = $items[$n];
			$wrapper->add($fieldset);
			unset($items[$n]);
			if($n) $order[] = $n;
		}

		// add any that are left
		foreach($items as $fieldset) {
			$wrapper->add($fieldset);
			$n = $fieldset->wrapAttr('data-combo-n');
			if($n) $order[] = $n;
		}
			
		if(!$isPost && !$numSubfields) {
			// if no items yet, click the "add field" button for them
			$wrapper->appendMarkup .= 
				"<script>jQuery(document).ready(function() { jQuery('#ComboConfigAdd').click() });</script>";
		}

		$order = implode(',', $order);
		$orderInputfield->val($order);
		$this->settings->order = $order;
		
		/** @var InputfieldButton $button */
		$button = $modules->get('InputfieldButton');
		$button->attr('name', '_combo_add'); 
		$button->attr('id', 'ComboConfigAdd'); 
		$button->val($this->_('Add subfield')); 
		$button->icon = 'plus';
		$button->setSecondary(true);
		$wrapper->add($button);
	
		if($this->field instanceof ComboField) {
			$datalists = $this->renderDatalist('combo-icon-names', $this->iconNames()); // icons datalist
			$moveOptions = array_merge($this->wire()->fields->getAll()->explode('name'), $subfieldNames); // moves datalist
			$datalists .= $this->renderDatalist('combo-move-names', $moveOptions);
			$wrapper->appendMarkup .= $datalists;
			$wrapper->appendMarkup .= "<p class='detail ComboAdvancedNote'>" . 
				$this->_('See the “Advanced” tab for advanced Combo settings.') . "</p>";
		}
		
		if(self::debug) {
			/** @var InputfieldMarkup $f */
			$f = $modules->get('InputfieldMarkup');
			$f->name = '_combo_debug';
			$f->label = 'Combo Debug';
			$f->icon = 'bug';
			$f->value = "<pre>" . htmlentities($this->settings->toJSON()) . "</pre>";
			$f->collapsed = Inputfield::collapsedYes;
			$f->set('themeOffset', 2);
			$inputfields->add($f);
		}
	
		$settings = $this->settings;
		$wrapper->addHookAfter('processInput', function(HookEvent $event) use($settings, $wrapper) {
			$messages = $settings->clean($wrapper);
			foreach($messages as $message) $settings->warning($message);
		}); 

	}

	/**
	 * Render a <datalist> 
	 * 
	 * @param string $id
	 * @param array $options
	 * @return string
	 * 
	 */
	protected function renderDatalist($id, array $options) {
		sort($options);
		$out = "<datalist id='$id'>";
		foreach($options as $option) {
			$out .= "<option value='$option'>";
		}
		$out .= "</datalist>";
		return $out;
	}

	/**
	 * Get all icon names
	 * 
	 * @param string $iconName Specify icon name to return bool true if valid, bool false if not
	 * @return array|bool
	 * 
	 */
	protected function iconNames($iconName = '') {
		static $names = array();
		if(empty($names)) {
			$file = $this->wire()->config->paths('InputfieldIcon') . 'icons.inc';
			if(!is_file($file)) return array();
			foreach(file($file) as $name) {
				$name = trim($name);
				if(strpos($name, 'fa-') === 0) $name = substr($name, 3);
				$name = htmlspecialchars($name);
				$names[$name] = $name;
			}
		}
		if($iconName) return isset($names[$iconName]); 
		return $names;
	}

	/**
	 * Advanced field configuration (appears on "Advanced" tab in field editor)
	 * 
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function getConfigAdvancedInputfields(InputfieldWrapper $inputfields) {
		$modules = $this->wire()->modules;
		
		/** @var InputfieldFieldset $fs */
		$fs = $modules->get('InputfieldFieldset');
		$fs->attr('name', '_combo_advanced_settings');
		$fs->label = $this->_('Combo advanced settings');
		$fs->icon = 'object-group';
		$inputfields->prepend($fs);

		/** @var InputfieldToggle $f */
		$f = $modules->get('InputfieldToggle');
		$f->attr('name', 'useColNames');
		$f->label = $this->_('Use verbose column names in database table?');
		$f->description =
			$this->_('By default Combo uses condensed column names like “i2”. Verbose column names append the subfield name to that, like “i2_first_name”.') . ' ' .
			$this->_('You may want to enable this option if you may work with the Combo database tables separately from the Combo field.');
		$f->val((int) $this->settings->useColNames);
		$fs->add($f);

		/** @var InputfieldToggle $f */
		$f = $modules->get('InputfieldToggle');
		$f->attr('name', 'modSchema');
		$f->label = $this->_('Allow customization of database schema for each subfield?');
		$f->description =
			$this->_('When enabled, each of the Combo fields on the “Details” tab of this field editor will show a “Database schema” fieldset.') . ' ' . 
			$this->_('This enables you to make adjustments to the schema of each field and control whether it is indexed.');
		$f->val((int) $this->settings->modSchema);
		$fs->add($f);
		
		/** @var InputfieldRadios$f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'useDataCol');
		$f->label = $this->_('Load Combo field data from');
		$f->description = 
			$this->_('The Combo field keeps a backup of data in a combined index.') . ' ' . 
			$this->_('You can optionally use this as your page data source, should you need to use the backup.') . ' ' . 
			$this->_('Use this option if ProFields support recommends it, otherwise use the primary data.'); 
		$f->addOption(0, $this->_('Primary data: Individual database columns')); 
		$f->addOption(1, $this->_('Backup data: Combined “data” column')); 
		$f->val((int) $this->settings->useDataCol);
		$fs->add($f);

		if($this->wire()->input->requestMethod('GET')) {
			/** @var InputfieldMarkup $f */
			$f = $modules->get('InputfieldMarkup');
			$f->attr('name', '_comboPhpDoc');
			$f->label = $this->_('Phpdoc class definition');
			$phpdoc = htmlspecialchars(trim($this->settings->toPhpDoc(false)));
			$name = $this->field->name;
			$className = "ComboValue_$name";
			$f->val(
				"<p class='description'>1. Define a phpdoc documentation class for this field:</p>" . 
				"<pre><code>$phpdoc</code></pre>" . 
				"<p class='description'>2. Get the combo field value and tell your editor what kind of value it is:</p>" . 
				"<pre>/" . "** @var $className \${$name} *" . "/\n" . 
				"\$$name = \$page-&gt;get('$name');</pre>" . 
				"<p class='description'>3. Get any subfield value from combo field:</p>" . 
				"<pre>\$value = \${$name}-&gt;any_subfield;</pre>"
			);
			$fs->add($f);
		}
	}
	
	public function hookProcessFieldBuildEditFormAdvanced(HookEvent $event) {
		$fs = $event->return;
		$f = $fs->getChildByName('_combo_advanced_settings');
		if($f) $fs->prepend($f);
	}

}