<?php namespace ProcessWire;

require_once(__DIR__ . '/CustomWireData.php');
require_once(__DIR__ . '/CustomFieldDefs.php');

/**
 * ProFields: Inputfield Custom
 * 
 * THIS IS PART OF A COMMERCIAL MODULE: DO NOT DISTRIBUTE.
 * This file should NOT be uploaded to GitHub or available for download on any public site.
 *
 * Copyright 2024 by Ryan Cramer Design, LLC
 * ryan@processwire.com
 * 
 * @property array $value
 * @property int $hideWrap
 * 
 */
class InputfieldCustom extends Inputfield implements InputfieldHasArrayValue {
	
	public static function getModuleInfo(): array {
		return array(
			'title' => 'ProFields: Custom', 
			'summary' => 'Custom fields let you define the subfields programmatically in PHP or JSON.', 
			'version' => 1,
			'icon' => 'diamond',
			'requires' => 'FieldtypeCustom',
		);
	}

	/**
	 * @var CustomFieldDefs|null 
	 * 
	 */
	protected $defs = null;

	/**
	 * Value to set once name attribute is present
	 * 
	 * @var array|null 
	 * 
	 */
	protected $setValue = null;

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		// $this->set('defsJson', '');
		$this->set('hideWrap', 0);
		parent::__construct();
	}

	/**
	 * Get custom field definitions manager
	 * 
	 * @return CustomFieldDefs
	 * 
	 */
	public function defs() {
		
		if($this->defs === null) {
			$this->defs = new CustomFieldDefs();
			$this->wire($this->defs);
		}
		
		if(!$this->defs->getName()) {
			$name = $this->attr('name');
			if($name) $this->defs->setName($name);
		}

		if(!$this->defs->getPage()) {
			$page = $this->hasPage;
			if($page && $page->id) $this->defs->setPage($page);
		}
	
		if(!$this->defs->getField()) {
			$field = $this->hasField;
			if($field) $this->defs->setField($field);
		}
		
		return $this->defs;
	}

	/**
	 * Get custom Inputfields
	 * 
	 * @return InputfieldWrapper
	 * 
	 */
	public function getInputfields(): InputfieldWrapper {
		$name = $this->attr('name'); // name is required
		if(empty($name)) return new InputfieldWrapper();
		return $this->defs()->getInputfields();
	}

	/**
	 * Get Inputfield for given property/subfield
	 * 
	 * @param string $name
	 * @return Inputfield|null
	 * 
	 */
	public function getPropertyInputfield(string $name) {
		return $this->defs()->getPropertyInputfield($name);
	}

	/**
	 * Method called right before Inputfield markup is rendered, so that any dependencies can be loaded as well.
	 *
	 * @param Inputfield|InputfieldWrapper|null The parent InputfieldWrapper that is rendering it, or null if no parent.
	 * @param bool $renderValueMode Specify true only if this is for `Inputfield::renderValue()` rather than `Inputfield::render()`.
	 * @return bool True if assets were just added, false if already added.
	 *
	 */
	public function renderReady(Inputfield $parent = null, $renderValueMode = false): bool {
		
		static $cssUrl = '';
		$result = false;
		
		if($this->hideWrap) {
			$this->addClass('InputfieldCustomHideWrap', 'wrapClass');
			$this->description = '';
			$this->notes = '';
			$this->detail = '';
			if(empty($cssUrl)) {
				// custom.css only used when hideWrap option is enabled
				$config = $this->wire()->config;
				$cssUrl = $config->urls($this) . 'custom.css';
				$config->styles->add($cssUrl);
				$result = true;
			}	
		}
		
		$inputfields = $this->getInputfields();
		if($inputfields->renderReady($parent, $renderValueMode)) $result = true;
		
		if(parent::renderReady($parent, $renderValueMode)) $result = true;
		
		return $result;
	}
	
	/**
	 * Render the HTML input element(s) markup, ready for insertion in an HTML form.
	 *
	 * @return string
	 *
	 */
	public function ___render(): string {
		$inputfields = $this->getInputfields();
		return $inputfields->render();
	}
	
	public function ___renderValue(): string {
		$inputfields = $this->getInputfields();
		// return $inputfields->renderValue();
		
		$out = "<ul>";
		foreach($inputfields->getAll() as $f) {
			$out .= "<li><strong>" . htmlspecialchars($f->label) . ":</strong> " . $f->renderValue() . "</li>";
		}
		$out .= "</ul>";
		return $out;
	}

	/**
	 * Process input
	 * 
	 * @param WireInputData $input
	 * @return self
	 * 
	 */
	public function ___processInput(WireInputData $input): self {
		
		$changes = [];
		
		$inputfields = $this->getInputfields();
		$inputfields->useDependencies = false;
		$inputfields->processInput($input);
		
		foreach($inputfields->getAll() as $f) {
			/** @var Inputfield $f */
			if($f->isChanged()) $changes[] = $f->attr('name');
		}
		
		if(count($changes)) $this->trackChange('value');
		
		return $this;
	}
	
	/**
	 * Get attribute 
	 *
	 * @param string $key
	 * @return mixed|null
	 *
	 */
	public function getAttribute($key) {
		if($key === 'value') return $this->getValue();
		return parent::getAttribute($key);
	}

	/**
	 * Get value attribute
	 * 
	 * @return array
	 * 
	 */
	public function getValue(): array {
		$languages = $this->wire()->languages;
		$value = [];
		foreach($this->getInputfields()->getAll() as $f) {
			/** @var Inputfield $f */
			$name = $f->getSetting(CustomFieldDefs::_property_name);
			if($f instanceof InputfieldDatetime) {
				// always use ISO-8601 for date/time storage
				$v = $f->val();
				$useTime = $f->get('timeInputFormat|timeSelectFormat|timeInputSelect') || $f->htmlType === 'time';
				if($v && $useTime) {
					$value[$name] = wireDate('Y-m-d H:i:s', $v);
				} else {
					$value[$name] = $v ? wireDate('Y-m-d', $v) : '';
				}
			} else if($f->useLanguages && $languages) {
				// multi-language fields
				$className = wireClassName('LanguagesPageFieldValue', true);
				$langValue = new $className($this->hasPage, $this->hasField); /** @var LanguagesPageFieldValue $langValue */
				$langValue->setFromInputfield($f);
				$value[$name] = $langValue;
			} else {
				$value[$name] = $f->val();
			}

		}
		return $value;
	}

	/**
	 * Set attribute
	 * 
	 * @param string $key
	 * @param string|array|CustomWireData $value
	 * @return self
	 * 
	 */
	public function setAttribute($key, $value): self {
		if($key === 'value') return $this->setValue($value);
		parent::setAttribute($key, $value);
		if($key === 'name' && !empty($value) && $this->setValue) {
			$this->setValue($this->setValue);
			$this->setValue = null;
		}
		return $this;
	}

	/**
	 * Set value attribute
	 * 
	 * @param array|WireData $value
	 * @return $this
	 * 
	 */
	public function setValue($value): self {
		
		if($value instanceof WireData) {
			$value = $value->getArray();
		} else if(is_string($value)) {
			$value = json_decode($value, true);
		}
		
		if(!is_array($value)) {
			// invalid value
			return $this;
		}
		
		$name = $this->attr('name');
		
		if(empty($name)) {
			// name is required to set a value so queue for later
			$this->setValue = $value; 
			return $this;
		}
		
		$inputfields = $this->getInputfields()->getAll();
		
		foreach($inputfields as $f) {
			/** @var Inputfield $f */
			$name = $f->getSetting(CustomFieldDefs::_property_name);
			if(isset($value[$name])) {
				$v = $value[$name];
				if($v instanceof Wire && wireInstanceOf($v, 'LanguagesPageFieldValue')) {
					/** @var LanguagesPageFieldValue $v */
					$v->setToInputfield($f);
				} else {
					$f->val($v);
				}
			}
		}
		
		return $this;
	}

	/**
	 * Set property/setting or attribute
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return self
	 * 
	 */
	public function set($key, $value): self {
		/*
		if($key === 'defsJson' && !empty($value)) {
			$this->defs = null;
		}
		*/
		return parent::set($key, $value);
	}

	/**
	 * Inputfield configuration
	 * 
	 * @return InputfieldWrapper
	 * 
	 */
	public function getConfigInputfields(): InputfieldWrapper {
		$config = $this->wire()->config;
		$fs = parent::getConfigInputfields();
		require_once(__DIR__ . '/config.php');
		InputfieldCustom_getConfigInputfields($fs, $this);
		return $fs;
	}
	
	/**
	 * Return array of strings containing errors that occurred during input processing
	 *
	 * @param bool $clear Optionally clear the errors after getting them (Default=false).
	 * @return array
	 *
	public function getErrors($clear = false) {
	$inputfields = $this->getInputfields();
	return $inputfields->getErrors($clear);
	}
	 */

}