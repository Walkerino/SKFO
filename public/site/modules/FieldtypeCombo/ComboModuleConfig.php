<?php namespace ProcessWire;

/**
 * ProcessWire ProFields Combo: Module configuration
 *
 * Part of the ProFields package.
 * Please do not distribute.
 *
 * Copyright (C) 2023 by Ryan Cramer
 *
 * https://processwire.com
 *
 */


class ComboModuleConfig extends WireData {

	/**
	 * Default allowed base Inputfield types
	 * 
	 * @var array
	 * 
	 */
	protected $defaultBaseTypes = array(
		'AsmSelect',
		'Checkbox',
		'Checkboxes',
		'CKEditor',
		'Datetime',
		'Email',
		'File',
		'Float',
		'Icon',
		'Image',
		'Integer',
		'Page',
		'Radios',
		'Select',
		'SelectMultiple',
		'Text',
		'Textarea',
		'TinyMCE',
		'Toggle',
		'URL',
	);

	/**
	 * Excluded Inputfield types
	 * 
	 * @var array
	 * 
	 */
	protected $excludeBaseTypes = array(
		'Button',
		'Combo',
		'CommentsAdmin',
		'Fieldset',
		'Form',
		'FormBuilderFile',
		'FrontendFile',
		'Markup',
		//'Multiplier',
		'PageAutocomplete',
		'PageListSelect',
		'PageListSelectMultiple',
		'PageName',
		'PageTable',
		'PageTitle',
		'Repeater',
		'RepeaterMatrix',
		'Submit',
		'Table',
		'Textareas',
		'Table',
	);

	/**
	 * Inputfield type to phpdoc type
	 * 
	 * @var array
	 * 
	 */
	protected $defaultPhpTypes = array(
		'AsmSelect' => 'array',
		'Checkbox' => 'int',
		'Checkboxes' => 'array',
		'CKEditor' => 'string',
		'Datetime' => 'string',
		'Decimal' => 'float',
		'Email' => 'string',
		'File' => 'Pagefiles',
		'Float' => 'float',
		'Icon' => 'string',
		'Integer' => 'int',
		'Image' => 'Pageimages',
		'Page' => 'PageArray',
		'Password' => 'Password',
		'Radios' => 'string',
		'Select' => 'string',
		'SelectMultiple' => 'array',
		'Selector' => 'string',
		'Text' => 'string',
		'Textarea' => 'string',
		'TinyMCE' => 'string',
		'Toggle' => 'string', // int doesn't allow non-selected state (blank string)
		'URL' => 'string',
	);

	/**
	 * Get default base types
	 * 
	 * @return array
	 * 
	 */
	public function getDefaultBaseTypes() {
		static $ok = false;
		if($ok) return $this->defaultBaseTypes;
		$ok = true;
		$modules = $this->wire()->modules;
		foreach(array('InputfieldTinyMCE', 'InputfieldCKEditor') as $name) {
			if($modules->isInstalled($name)) continue;
			$key = array_search($name, $this->defaultBaseTypes); 
			if($key !== false) unset($this->defaultBaseTypes[$key]); 
		}
		return $this->defaultBaseTypes;
	}

	/**
	 * Get default php types
	 * 
	 * @return array
	 * 
	 */
	public function getDefaultPhpTypes() {
		return $this->defaultPhpTypes;
	}	

	/**
	 * @param InputfieldCombo $module
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function getModuleConfigInputfields(InputfieldCombo $module, InputfieldWrapper $inputfields) {
		$module->wire($this);
		
		/** @var InputfieldCheckboxes $f */
		$f = $this->wire()->modules->get('InputfieldCheckboxes');
		$f->label = $this->_('Allowed field/input types');
		$f->description =
			$this->_('Select the Inputfield types that you want to allow for Combo fields.') . ' ' .
			$this->_('Not all Inputfield types can be used with Combo fields.') . ' ' .
			$this->_('If you add additional types, be sure to test them thoroughly before assuming they work.');
		$f->attr('name', 'baseTypes');
		$f->icon = 'list';
		$f->table = true;
		$f->thead = 
			$this->_('Module') . '|' . 
			$this->_('Description');

		foreach($this->wire()->modules->findByPrefix('Inputfield', 2) as $moduleName => $moduleInfo) {
			$name = str_replace('Inputfield', '', $moduleName);
			if(in_array($name, $this->excludeBaseTypes)) continue;
			// if($name != 'Page' && substr($name, 0, 4) == 'Page') continue;
			$title = str_replace('|', ' ', $moduleInfo['title']); 
			$summary = str_replace('|', ' ', $moduleInfo['summary']); 
			$f->addOption($name, "$title|$summary");
		}

		$value = $module->get('baseTypes');
		if(empty($value)) $value = $this->getDefaultBaseTypes();
		$f->val($value);
		$inputfields->add($f);
	
		/** @var InputfieldTextarea $f */
		$f = $this->wire()->modules->get('InputfieldTextarea');
		$f->attr('name', 'phpTypes'); 
		$f->label = $this->_('Input types to phpdoc types'); 
		$f->description = 
			$this->_('This setting maps Inputfield types to phpdoc types for documentation and default value purposes.') . ' ' . 
			$this->_('There’s no need to modify this setting unless find you need to, or if you want to add something to it.');
		$f->collapsed = Inputfield::collapsedYes;
		$value = $module->get('phpTypes'); 
		if(empty($value)) {
			$value = array();
			foreach($this->defaultPhpTypes as $inputType => $phpType) {
				$value[] = "$inputType:$phpType";
			}
			$value = implode("\n", $value);
		}
		$f->val($value);
		$inputfields->add($f);
	}
}