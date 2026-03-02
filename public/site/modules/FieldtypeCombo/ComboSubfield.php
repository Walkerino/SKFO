<?php namespace ProcessWire;

/**
 * ProcessWire ProFields Combo: subfield definition
 *
 * Part of the ProFields package.
 * Please do not distribute.
 *
 * Copyright (C) 2023 by Ryan Cramer
 *
 * https://processwire.com
 * 
 * @property int $num
 * @property string $name
 * @property string $type
 * @property string $label
 * @property string $description
 * @property string $icon
 * @property string $notes
 * @property bool|int $ok
 * @property int $columnWidth
 * @property bool|int $required
 * @property string $requiredIf
 * @property int $collapsed
 * @property string $showIf
 * @property array $textformatters Textformatter module names that should be applied. 
 * @property bool|int $xlang Do not inherit default language values when true (default=false)
 * @property bool|int $one WireArray value focus on just first item (i.e. Page rather than PageArray)
 * @property array|string|null $options Selectable option values, if applicable
 * @property string $move Move after inputfield "name" or before "-name" before rendering form
 * 
 * @property string|null $schemaType Custom schema type, or blank to use default. (See ComboSchema::$schemaTypes)
 * @property string|null $schemaData Additional schema data, when applicable, i.e. '123' (length), '9,2' (precision,scale)
 * @property bool|int $xidx Disable a DB index for this column?
 * @property bool|int $xbak Disable backup data for this column?
 * 
 * @property string $_namePrevious
 * @property string $_typePrevious
 * 
 */

class ComboSubfield extends WireData {
	
	protected $ready = false;
	
	public function __construct($num = 0) {
		parent::__construct();
		$this->setArray(array(
			'num' => $num,
			'name' => '',
			'type' => '', 
			'label' => '',
			'description' => '',
			'notes' => '',
			'move' => '',
			'icon' => '', 
			'ok' => 0,
			'columnWidth' => 100,
			'required' => 0,
			'requiredIf' => '',
			'showIf' => '',
			'collapsed' => 0,
			'textformatters' => array(),
			'xlang' => 0,
			'one' => 0,
		));
		$this->ready = true;
	}

	/**
	 * Set subfield property
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return ComboSubfield|WireData
	 * @throws WireException
	 * 
	 */
	public function set($key, $value) {
		if(!$this->ready) {
			return parent::set($key, $value);
		}
		if($key === 'name') {
			return $this->setName($value);
		} else if($key === 'type') {
			return $this->setType($value);
		} else if(strpos($key, 'i') === 0 && ctype_digit(substr($key, 1, 2)) && preg_match('/^i\d+_/', $key)) {
			throw new WireException('Do not set prefixed properties to ComboSubfield');
		}
		return parent::set($key, $value);
	}

	/**
	 * Set subfield name
	 * 
	 * @param string $value
	 * @return self|WireData
	 * 
	 */
	public function setName($value) {
		$namePrevious = parent::get('name');
		if($namePrevious !== '' && $namePrevious !== $value) {
			parent::set('_namePrevious', $namePrevious);
		}
		$test = strpos($value, '_') === false ? $value : str_replace('_', '', $value);
		if(!ctype_alnum($test)) {
			$value = $this->wire()->sanitizer->fieldName($value);
		}
		return parent::set('name', $value);
	}

	/**
	 * Set subfield type
	 *
	 * @param string $value
	 * @return self|WireData
	 *
	 */
	public function setType($value) {
		$typePrevious = parent::get('type');
		if($typePrevious !== '' && $typePrevious !== $value) {
			parent::set('_typePrevious', $typePrevious);
		}
		if(strpos($value, 'Inputfield') === 0) {
			list(,$value) = explode('Inputfield', $value, 2);
		}
		return parent::set('type', $value);
	}

	/**
	 * Get collapsed array of subfield data
	 * 
	 * @return array
	 * 
	 */
	public function getArray() {
		$a = parent::getArray();
		$omitWhenEmpty = array(
			'showIf', 'required', 'requiredIf', 'collapsed', 
			'notes', 'description', 'textformatters',
		);
		foreach($omitWhenEmpty as $prop) {
			if(empty($a[$prop])) unset($a[$prop]);
		}
		if(isset($a['columnWidth']) && $a['columnWidth'] == 100) unset($a['columnWidth']);
		return $a;
	}

	/**
	 * Get subfield prefix, optionally for a property
	 * 
	 * @param string $property
	 * @return string
	 * 
	 */
	public function prefix($property = '') {
		return ComboSettings::_prefix($this->num, $property);
	}

	/**
	 * Get language text value
	 * 
	 * @param Language|int|string|null
	 * @param string $property
	 * @param bool $defaultFallback
	 * @return string
	 * 
	 */
	public function getLanguageValue($language, $property, $defaultFallback = true) {
		$langProperty = $this->getLanguageProperty($language, $property);
		$value = $this->get($langProperty);
		if(empty($value) && $defaultFallback) $value = $this->get($property);
		return $value;
	}

	/**
	 * Set language text value
	 * 
	 * @param Language|int|string|null $language
	 * @param string $property
	 * @param string $value
	 * @return self 
	 * 
	 */
	public function setLanguageValue($language, $property, $value) {
		$langProperty = $this->getLanguageProperty($language, $property);
		$this->set($langProperty, $value);
		return $this;
	}

	/**
	 * Get language-localized property name
	 * 
	 * @param Language|int|string|null $language
	 * @param string $property
	 * @return string
	 * 
	 */
	protected function getLanguageProperty($language, $property) {
		
		$languages = $this->wire()->languages;
		if(!$languages) return $property;
		
		if(empty($language)) {
			$language = $this->wire()->user->language;
		} else if(is_string($language) || is_int($language)) {
			$language = $languages->get($language);
		}
		
		if(!$language || !wireInstanceof($language, 'Language')) return $property;
		
		if($language->isDefault()) return $property;
		
		return "$property$language->id";
	}

	/**
	 * Get localized subfield label
	 * 
	 * @param Language|int|string|null Language or omit for current user language
	 * @return string
	 * 
	 */
	public function getLabel($language = null) {
		return $this->getLanguageValue($language, 'label');
	}
	
	/**
	 * Get localized subfield description
	 *
	 * @param Language|int|string|null Language or omit for current user language
	 * @return string
	 *
	 */
	public function getDescription($language = null) {
		return $this->getLanguageValue($language, 'description');
	}

	/**
	 * Get localized subfield notes
	 *
	 * @param Language|int|string|null Language or omit for current user language
	 * @return string
	 *
	 */
	public function getNotes($language = null) {
		return $this->getLanguageValue($language, 'notes');
	}
	
	public function __toString() {
		return $this->className() . ":$this->num:$this->type:$this->name";
	}
}

/**
 * Null Subfield represents non-existence of a Subfield
 *
 */
class ComboNullSubfield extends ComboSubfield {
	public function __construct($num = 0) {
		if($num) {} // ignore
		parent::__construct(0);
	}
	public function set($key, $value) {
		if($key === 'num') $value = 0;
		return parent::set($key, $value);
	}
}