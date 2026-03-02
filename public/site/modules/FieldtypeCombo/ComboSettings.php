<?php namespace ProcessWire;

/**
 * ProcessWire ProFields Combo: settings and tools
 *
 * Part of the ProFields package.
 * Please do not distribute.
 *
 * Copyright (C) 2024 by Ryan Cramer
 *
 * https://processwire.com
 *
 * @property int $qty Quantity of inputs
 * @property string $order Order of inputs [n] CSV
 * @property bool|int $useColNames Use verbose column names? (FieldtypeCombo only)
 * @property bool|int $useDataCol Load values from 'data' column rather than individual columns? (FieldtypeCombo only)
 * @property bool|int $modSchema Allow customization of schema in field editor?
 * @property bool|int $hideWrap Hide wrapping fieldset around subfields?
 * @property string $i1_name
 * @property string $i1_type
 * @property string $i1_label
 * @property string $i1_description
 * @property string $i1_notes
 * @property string $i1_icon
 * @property bool|int $i1_xidx Disable index?
 * @property bool|int $i1_xbak Disable backup data?
 * @property array $i1_textformatters Textformatter module names (when applicable)
 * @property int $i1_ok Value 0 when not yet configured, value 1 when configured/ready to use
 * 
 */

class ComboSettings extends WireData {
	
	/**
	 * @var Field|Inputfield
	 * 
	 */
	protected $field;

	/**
	 * @var ComboAPI
	 * 
	 */
	protected $api = null;

	/**
	 * Construct
	 *
	 * @param Field|Inputfield $field
	 * 
	 */
	public function __construct($field) {
		$this->field = $field;
		$this->data('qty', 0);
		$this->data('order', '');
		$this->data('useColNames', 0);
		$this->data('useDataCol', 0);
		$this->data('modSchema', 0); 
		$this->data('hideWrap', 0);
		$field->wire($this);
		parent::__construct();
	}

	/**
	 * @return Field|Inputfield
	 * 
	 */
	public function getField() {
		return $this->field;
	}

	/**
	 * Is given property name a Combo setting?
	 * 
	 * @param string $name
	 * @return bool
	 * 
	 */
	public function isSetting($name) {
		if($this->data($name) !== null) return true;
		return $this->hasPrefix($name) > 0;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return $this|WireData
	 * 
	 */
	public function set($key, $value) {
		if($this->isSetting($key)) {
			$this->setSetting($key, $value);
		} else {
			parent::set($key, $value);
		}
		return $this;
	}

	/**
	 * Set a setting and sanitize where applicable
	 * 
	 * @param string $key
	 * @param mixed $value
	 * 
	 */
	protected function setSetting($key, $value) {
		
		if($key === 'qty') {
			$value = (int) $value;
		} else if(strpos($key, 'label') === 0 || strpos($key, 'description') === 0 || strpos($key, 'notes') === 0) {
			$prop = $this->unlang($this->unprefix($key));
			if($prop === 'label') {
				$value = $this->wire()->sanitizer->text($value);
			} else if($prop === 'description' || $prop === 'notes') {
				$value = $this->wire()->sanitizer->textarea($value);
			}
		} else {
			$prop = $this->unprefix($key);
			if($prop === 'name' || $prop === 'type') {
				$value = $this->wire()->sanitizer->fieldName($value);
				if($prop === 'type' && strpos($value, 'Inputfield') === 0) {
					$value = str_replace('Inputfield', '', $value);
				}
			}
		}
		
		$this->data($key, $value);
	}

	/**
	 * Get a setting, optionally with an index number
	 * 
	 * @param string $name
	 * @param int $n
	 * @return string|int|array|null
	 * 
	 */
	public function getSetting($name, $n = 0) {
		if($n) $name = $this->prefix($n) . $name;
		$value = $this->data($name); /** @var string|int|array|null $value */
		return $value;
	}

	/**
	 * Does given name have a prefix? Returns false if no, number or property if yes
	 * 
	 * @param string $name Property that might have prefix
	 * @return bool|int Returns false if no prefix or prefix number (1+) if prefix is present
	 * 
	 */
	public function hasPrefix($name) {
		if(strpos($name, 'i') !== 0 || !strpos($name, '_')) return false; 
		list($n,) = explode('_', substr($name, 1), 2);
		if(!ctype_digit($n)) return false; 
		return (int) $n;
	}
	
	/**
	 * Return the prefix for the given number, or prefix found in given property name string
	 * 
	 * @param int|string $n
	 * @param string $property Optional property name or omit for just property-ready prefix
	 * @return string
	 *
	 */
	public function prefix($n, $property = '') {
		if(ctype_digit("$n")) return self::_prefix($n, $property); 
		$n = $this->hasPrefix($n);
		return ($n === false ? '' : self::_prefix($n, $property));
	}

	/**
	 * Return the prefix for given number (static)
	 * 
	 * @param int $n
	 * @param string $property Optional property name or omit for just property-ready prefix
	 * @return string
	 * 
	 */
	public static function _prefix($n, $property = '') {
		$n = (int) $n;
		return "i{$n}_$property";
	}

	/**
	 * Return name without prefix (static)
	 *
	 * @param string $name
	 * @return string
	 *
	 */
	public static function _unprefix($name) {
		if(strpos($name, 'i') !== 0 || !strpos($name, '_')) return $name;
		list($num, $property) = explode('_', ltrim($name, 'i'), 2);
		return ctype_digit($num) ? $property : $name;
	}
	
	/**
	 * Return name without prefix
	 *
	 * @param string $name
	 * @return string
	 *
	 */
	public function unprefix($name) {
		return self::_unprefix($name);
	}

	/**
	 * Remove language ID from given property name
	 * 
	 * @param string $name
	 * @param bool $getLangId Get language ID from property rather than property? (default=fales)
	 * @return string|Language
	 * 
	 */
	public function unlang($name, $getLangId = false) {
		if(!ctype_digit(substr($name, -1))) {
			// if no number at end, this is not a language property
			return $getLangId ? 0 : $name;
		}
		/*
		if(strpos($name, '__')) {
			// language property in format property__1234 (is this still in use?)
			list($prop, $langId) = explode('__', $name, 2);
			if(ctype_digit($langId)) return $getLangId ? (int) $langId : $prop;
		}
		*/
		// language property in format property1234
		$languages = $this->wire()->languages;
		if(!$languages) return $getLangId ? 0 : $name;
		$langId = 0;
		foreach($languages as $language) {
			/** @var Language $language */
			if($language->isDefault()) continue;
			if(!strpos($name, "$language->id")) continue;
			list($name,) = explode("$language->id", $name, 2);
			$langId = $language->id;
			break;
		}
		return $getLangId ? $langId : $name;
	}

	/**
	 * Given an indexed property or field name, return the associated number [n] index
	 * 
	 * - Given any numbered item property name (i.e. "i3_type"), return the number (i.e. 3)
	 * - Given a field name, i.e. "first_name", return the number for that field. 
	 * - If just given a number, it returns the number.
	 * 
	 * @param string|int|ComboSubfield $name
	 * @return int
	 * 
	 */
	public function num($name) {
		
		$n = 0;
	
		// ComboSubfield object
		if($name instanceof ComboSubfield) return $name->num;
	
		// name in format: "11" (already a num)
		if(ctype_digit("$name")) return (int) $name;
	
		// starts with an 'i'
		$i0 = strpos($name, 'i') === 0; 
	
		// column name format, i.e. "i7"
		if($i0) {
			$i1 = rtrim(substr($name, 1), '_'); 
			if(ctype_digit($i1)) return (int) $i1;
		}
		
		// name in format: "i[n]_property" 
		if($i0 && strpos($name, '_') && ctype_digit($name[1])) {
			list($n,) = explode('_', substr($name, 1), 2);
			if(ctype_digit($n)) return (int) $n;
		}

		// name in format: "first_name" (name of field to find num for)	
		foreach($this->getArray() as $key => $value) {
			if($value !== $name) continue;
			if($key[0] !== 'i' || !strpos($key, '_name') || !ctype_digit($key[1])) continue;
			list($i,) = explode('_', $key, 2);
			$i = (int) ltrim($i, 'i');
			if(!$i) continue;
			if($key !== "i{$i}_name") continue;
			$n = $i;
			break;
		}
		
		return $n;
	}

	/**
	 * Get subfield name
	 * 
	 * @param string|int|ComboSubfield $num
	 * @return string
	 * 
	 */
	public function name($num) {
		$num = ctype_digit("$num") ? (int) $num : $this->num($num);
		$property = $this->prefix($num, 'name');
		$name = $this->getSetting($property);
		return $name ? $name : '';
	}

	/**
	 * Get subfield DB column name
	 *
	 * @param string|int|ComboSubfield $name
	 * @return string
	 *
	 */
	public function colName($name) {
		$num = ctype_digit("$name") ? (int) $name : $this->num($name);
		if($this->useColNames) {
			$name = $this->name($num);
			$colName = "i{$num}_$name";
		} else {
			$colName = "i$num";
		}
		return $colName;
	}

	/**
	 * Does given subfield name or number exist?
	 * 
	 * @param string|int|ComboSubfield $name
	 * @return bool
	 * 
	 */
	public function subfieldExists($name) {
		
		if($name instanceof ComboSubfield) {
			// ComboSubfield object
			$num = $name->num;
		} else if(ctype_digit("$name")) {
			// integer
			$num = (int) $name;
		} else {
			// name string
			$num = $this->num($name);
		}
		
		if($num < 1) return false;
		
		$prefix = $this->prefix($num);
		
		$value = $this->data($prefix . 'type');
		if(!empty($value)) return true;
		
		$value = $this->data($prefix . 'name');
		if(!empty($value)) return true;
		
		return false;
	}

	/**
	 * Get the maximum qty number
	 * 
	 * @return int
	 * 
	 */
	public function findMaxQty() {
		$qty = $this->qty;
		foreach($this->getArray() as $key => $value) {
			if($key[0] !== 'i' || !strpos($key, '_type')) continue;
			list($n,) = explode('_', substr($key, 1), 2);
			if(((int) $n) > $qty) $qty = $n;
		}
		$this->qty = $qty;
		return $qty;
	}

	/**
	 * Get a ComboSubfield 
	 * 
	 * @param string|int|ComboSubfield $nameOrNum Name or index number
	 * @return ComboSubfield|ComboNullSubfield
	 * 
	 */
	public function getSubfield($nameOrNum) {
		if($nameOrNum instanceof ComboSubfield) return $nameOrNum;
		return $this->getSubfieldData($nameOrNum, array('getObject' => true));
	}

	/**
	 * Get all subfields in order
	 * 
	 * @return ComboSubfield[]
	 * 
	 */
	public function getSubfields() {
		
		$subfields = array();
		$sorted = array();
		
		for($n = 1; $n <= $this->findMaxQty(); $n++) {
			$type = $this->get("i{$n}_type");
			if(empty($type) || strtolower($type) === 'deleted') continue;
			$subfield = $this->getSubfield($n);
			if(!$subfield) continue;
			$subfields[$n] = $subfield;
		}
		
		foreach(explode(',', $this->order) as $n) {
			if(!isset($subfields[$n])) continue;
			$subfield = $subfields[$n];
			$sorted[$subfield->name] = $subfield;
			unset($subfields[$n]); 
		}
	
		// add in any extra that weren't in the order (not likely)
		foreach($subfields as $subfield) {
			$sorted[$subfield->name] = $subfield;
		}
		
		return $sorted;
	}
	
	/**
	 * Get array of all data for a given Combo Subfield name or index number
	 * 
	 * @param string|int $nameOrNum Name, num, or prefix
	 * @param array $options
	 *  - `getObject` (bool): Get a ComboSubfield object rather than array? (default=false)
	 *  - `noPrefix` (bool): Remove prefix from returned keys? Applies only if getObject is false (default=true)
	 * @return array|ComboSubfield|ComboNullSubfield
	 * 
	 */
	public function getSubfieldData($nameOrNum, array $options = array()) {
		$defaults = array(
			'getObject' => false, 
			'noPrefix' => true,
		);
		$options = array_merge($defaults, $options);
		if($options['getObject']) $options['noPrefix'] = true;
		$data = array();
		$n = $this->num($nameOrNum);
		if($n) {
			$prefix = $this->prefix($n);
			foreach($this->getArray() as $key => $value) {
				if(strpos($key, $prefix) !== 0) continue;
				if($options['noPrefix']) list(, $key) = explode($prefix, $key, 2);
				$data[$key] = $value;
			}
		}
		if($options['getObject']) {
			$subfield = $n ? new ComboSubfield($n) : new ComboNullSubfield();
			$this->wire($subfield);
			if($n) {
				$subfield->setArray($data);
				$subfield->resetTrackChanges(true);
			}
			return $subfield;
		}
		return $data;	
	}

	/**
	 * Get a subfield property or null if not found
	 * 
	 * @param string|int|ComboSubfield $nameOrNum
	 * @param string $property
	 * @return string|int|bool|null
	 * 
	 */
	public function getSubfieldProperty($nameOrNum, $property) {
		if(is_object($nameOrNum)) {
			if(!$nameOrNum instanceof ComboSubfield) return null;
			$subfield = $nameOrNum;
			return $subfield->get($property);
		}
		$n = $this->num($nameOrNum);
		$prefix = $this->prefix($n);
		return ($n && $prefix ? $this->get($prefix . $property) : null); 
	}

	/**
	 * Get subfield type
	 * 
	 * @param string|int|ComboSubfield $nameOrNum
	 * @return string
	 * 
	 */
	public function getSubfieldType($nameOrNum) {
		$type = $this->getSubfieldProperty($nameOrNum, 'type');
		return $type ? $type : '';
	}

	/**
	 * Get subfield value type
	 * 
	 * @param string|int|ComboSubfield $nameOrNum
	 * @return string One of 'Page', 'PageArray', 'ComboLanguagesValue', 'array', 'string', 'int', 'float' or null if not known
	 * 
	 */
	public function getSubfieldValueType($nameOrNum) {
		
		$type = ucfirst((string) $this->getSubfieldProperty($nameOrNum, 'type'));
		if($type === 'DELETE') return null;

		if($type === 'Page' || $type === 'PageArray') {
			$one = $this->getSubfieldProperty($nameOrNum, 'one');
			return ($one ? 'Page' : 'PageArray');
		} 
		
		if($type === 'File') return 'Pagefiles';
		if($type === 'Image') return 'Pageimages';

		if($this->isLanguageType($type)) return 'ComboLanguagesValue';
		
		$types = $this->getPhpTypes();
		if(isset($types[$type])) return $types[$type];

		$class = "Inputfield$type";
		if(wireClassExists($class)) {
			if(wireInstanceOf($class, 'InputfieldHasArrayValue')) return 'array';
			if(wireInstanceOf($class, 'InputfieldFloat')) return 'float';
			if(wireInstanceOf($class, 'InputfieldInteger')) return 'int';
		}
		
		return null;
	}

	/**
	 * Get blank value for given subfield
	 * 
	 * @param string|int $nameOrNum
	 * @param Page $page Page argument is required for file/image fields
	 * @return array|float|int|null|NullPage|PageArray|string
	 * 
	 */
	public function getSubfieldBlankValue($nameOrNum, Page $page = null) {
		$type = $this->getSubfieldValueType($nameOrNum);
		$value = null;
		
		switch($type) {
			case 'array': return array();
			case 'string': return '';
			case 'Page': return $this->wire()->pages->newNullPage();
			case 'PageArray': return $this->wire()->pages->newPageArray();
			case 'ComboLanguagesValue': return $this->wire(new ComboLanguagesValue());
			case 'Pagefiles': 
			case 'Pageimages':	
				$type = "\\ProcessWire\\$type";
				$value = $this->wire(new $type($page)); /** @var Pagefiles|Pageimages $value */
				$value->setField($this->field);
				return $value;
			case 'int': // 0, null so InputfieldInteger can use initValue
			case 'float': // 0.0
			case 'null':
			case 'DELETE': return null;
		}
		
		if($type !== null && isset($type[0]) && strtoupper($type[0]) === $type[0] && wireClassExists($type)) {
			try { // class name to object instance
				$type = wireClassName($type, true);
				$value = new $type();
				if($value instanceof Wire) $this->wire($value);
			} catch(\Exception $e) {
				$value = null;
			}
		}
		
		return $value;
	}

	/**
	 * Get Inputfield for given subfield name or num
	 * 
	 * @param string|int|array|ComboSubfield $name Combo subfield name, number, array of data or ComboSubfield instance
	 * @return null|Inputfield
	 * 
	 */
	public function getInputfield($name) {

		if(is_array($name)) {
			$data = $name;
		} else if($name instanceof ComboSubfield) {
			$data = $name->getArray();
		} else {
			$data = $this->getSubfieldData($name);
		}

		if(empty($data['name']) || empty($data['type'])) return null;

		$type = ucfirst($data['type']);
		unset($data['type']);

		$languages = $this->wire()->languages; 
		$languageType = $this->isLanguageType($type, true);
		if($languageType) $type = $languageType;

		/** @var Inputfield $f */
		$f = $this->wire()->modules->get("Inputfield$type");
		if(!$f) return null;
	
		if(wireInstanceOf($f, 'InputfieldCheckbox')) {
			// ensures setting value '1' activates 'checked' attribute
			/** @var InputfieldCheckbox $f */
			$f->autocheck = true;
		} 
	
		// Inputfield does not need these properties that are only for ComboSubfield
		unset($data['one'], $data['ok'], $data['num']);

		if($languageType) $f->useLanguages = true;
	
		// find some properties to set separately
		$data2 = array();
		if($languages && wireInstanceOf($f, 'InputfieldSelect')) {
			foreach($languages as $language) {
				$key = "options$language->id";
				if(isset($data[$key])) {
					$data2[$key] = $data[$key];
					unset($data[$key]);
				}
			}
		}

		// set config properties
		$f->setArray($data);
		
		// set separate config properties that require hasFieldtype===false
		if(count($data2)) {
			$hasFieldtype = $f->hasFieldtype;
			$f->hasFieldtype = false;
			$f->setArray($data2);
			$f->hasFieldtype = $hasFieldtype;
		}
		
		return $f;
	}
	
	/**
	 * Set value attr(s) for configuration Inputfield
	 *
	 * Given Inputfield must already have a "name" attribute that has a settings prefix.
	 *
	 * @param Inputfield $f
	 * @param bool $useLanguages Use multi-language when available?
	 *
	 */
	public function setInputfieldValue(Inputfield $f, $useLanguages = true) {

		$f->val($this->get($f->name));
		if(!$useLanguages || !$f instanceof InputfieldText) return;
		$languages = $this->wire()->languages;
		if(!$languages) return;
		$f->useLanguages = true;

		foreach($languages as $language) {
			/** @var Language $language */
			if($language->isDefault()) continue;
			$value = (string) $this->get("{$f->name}$language->id");
			$f->set("value$language->id", $value);
		}
	}

	/**
	 * Get all values for given property, indexed by column name "i[n]"
	 * 
	 * @param string $property
	 * @return array
	 * 
	 */
	public function getAll($property = 'name') {
		$all = array();
		foreach($this->getArray() as $key => $value) {
			if(strpos($key, 'i') !== 0) continue;
			if(strpos($key, "_$property") === false) continue;
			list($i, $k) = explode('_', $key, 2);
			if($k !== $property) continue;
			$all[$i] = $value;
		}
		return $all;
	}

	/**
	 * Clean/validate settings data
	 * 
	 * 1. Clean out any deleted items or data that is no longer applicable
	 * 2. Detect and correct any field name collisions
	 * 
	 * Note: Deleted items are those that have 'DELETE' as the 'i[n]_type'
	 * 
	 * @param InputfieldWrapper $inputfields
	 * @return array Returns an array of messages about what was cleaned
	 * 
	 */
	public function clean(InputfieldWrapper $inputfields = null) {
		
		$deletePrefixes = array();
		$names = array();
		$renameKeys = array();
		$messages = array();
		$deleteFields = array();
		$field = $this->field instanceof ComboField ? $this->field : null;
		
		foreach($this->getArray() as $key => $value) {
			
			if(strpos($key, 'i') !== 0) continue;
			if(!strpos($key, '_name') && !strpos($key, '_type') && !strpos($key, '_move')) continue;
			
			$n = $this->num($key);
			if(!$n) continue;
			
			$prefix = $this->prefix($n);
			
			if($key === "{$prefix}type" && $value === 'DELETE') {
				// found a field to delete
				$deletePrefixes[] = $prefix;
				
			} else if($key === "{$prefix}name") {
				if(isset($names[$value]) || $this->isReservedName($value)) {
					// colliding name or reserved name
					$renameKeys[$key] = $key;
				} else {
					// non-colliding name
					$names[$value] = $key;
				}
			}
		}
		
		foreach($deletePrefixes as $prefix) {
			foreach($this->getArray() as $key => $value) {
				if(strpos($key, $prefix) !== 0) continue;
				$this->remove($key);
				if($field) {
					// necessary only when removing properties from Field object
					$this->field->offsetUnset($key);
				}
				if($key === "{$prefix}_name") {
					$messages[] = sprintf($this->_('Deleted field: %s'), $this->field->name . ".$value");
					$num = $this->num($key);
					if($num) $deleteFields[$num] = $value;
				}
			}
		}
	
		/*
		foreach($deleteFields as $num => $name) {
			$this->api()->subfieldDeleted($name, $num); 
		}
		*/
		
		foreach($renameKeys as $key) {
			$oldValue = $this->get($key);
			$cnt = 0;
			do {
				$cnt++;
				$newValue = $oldValue . "_$cnt";
			} while(isset($names[$newValue])); 
			$messages[] = sprintf($this->_('Name "%s" is already in use by another field or is a reserved name'), $oldValue);
			$this->set($key, $newValue);
			// if($this->field) $this->field->set($key, $newValue);
			if($inputfields) {
				$f = $inputfields->getChildByName($key);
				if($f) $f->val($newValue);
			}
			// $this->api()->subfieldRenamed($oldValue, $newValue, $this->num($key));
		}
		
		return $messages;
	}
	
	/**
	 * Is given name a reserved name?
	 * 
	 * @param string $name
	 * @return bool
	 * 
	 */
	public function isReservedName($name) {
		static $reservedNames = array();
		if(empty($reservedNames)) {
			$reservedNames = array('data', 'pages_id', 'sort', 'type');
			foreach($this->wire('*') as $key => $value) {
				$reservedNames[$key] = $key;
			}
		}
		if(isset($reservedNames[$name])) return true;
		if($this->hasPrefix($name)) return true;
		return false;
	}

	/**
	 * Is given column type a multi-language column type?
	 * 
	 * @param string $type
	 * @param bool $getBaseType Return base type languages are for rather than true? (i.e. Text, Textarea, etc.)
	 * @return bool|string
	 * 
	 */
	public function isLanguageType($type, $getBaseType = false) {
		if(!stripos($type, '_language')) return false;
		if(!$this->wire()->languages) return false;
		$parts = explode('_', $type);
		$last = array_pop($parts);
		$is = strtolower($last) === 'language';
		if($is && $getBaseType) return implode('_', $parts);
		return $is;
	}

	/**
	 * Is given column type a select type?
	 * 
	 * Returns 0 if no, 1 if single select type, 2 if multi-select type
	 * 
	 * @param string $type
	 * @return int
	 * 
	 */
	public function isSelectType($type) {
		if(empty($type) || stripos($type, '_Language') || $type === 'DELETE') return 0;
		$class = "Inputfield" . ucfirst($type);
		if(wireInstanceOf($class, 'InputfieldSelect')) {
			if(wireInstanceOf($class, 'InputfieldHasArrayValue')) return 2;
			return 1;
		}
		return 0;
	}

	/**
	 * Get array of input type to PHP type, i.e. [ 'Text' => 'string' ]
	 *
	 * @return array
	 *
	 */
	public function getPhpTypes() {
		
		if(!empty($this->phpTypes)) return $this->phpTypes;

		if($this->field instanceof InputfieldCombo) {
			$phpTypes = $this->field->get('phpTypes');
		} else {
			$phpTypes = $this->wire()->modules->getConfig('InputfieldCombo', 'phpTypes');
		}

		if(empty($phpTypes)) {
			require_once(__DIR__ . '/ComboModuleConfig.php');
			$moduleConfig = new ComboModuleConfig();
			$this->wire($moduleConfig);
			$phpTypes = $moduleConfig->getDefaultPhpTypes();
		} else {
			$a = array();
			foreach(explode("\n", $phpTypes) as $line) {
				if(!strpos($line, ':')) continue;
				list($inputType, $phpType) = explode(':', $line, 2);
				$inputType = ucfirst(trim($inputType));
				$a[$inputType] = trim($phpType);
			}
			$phpTypes = $a;
		}
		
		$this->phpTypes = $phpTypes;

		return $phpTypes;
	}
	
	protected $phpTypes = array(); // cache for above method
	
	/**
	 * Return JSON string of all settings
	 * 
	 * @param bool $readable Return newline/tab formatted readable JSON string? (default=true)
	 * @return string
	 *
	 */
	public function toJSON($readable = true) {
		$data = $this->getArray();
		ksort($data);
		$flags = $readable ? JSON_PRETTY_PRINT : 0;
		return json_encode($data, $flags);
	}

	/**
	 * Render phpdoc for this field
	 * 
	 * @param bool $writeFile Write to file rather than return phpdoc? If true, returns filename written to
	 * @param bool $namespace
	 * @return string
	 * 
	 */
	public function toPhpDoc($writeFile = false, $namespace = false) {
		$config = $this->wire()->config;
		$name = $this->field->name;
		$className = "ComboValue_{$name}";
		$path = $config->paths->assets . 'docs/';
		$file = $path . "$className.php";
		$subfields = $this->getSubfields();
		
		$doc = array(
			"{$this->field->label} (\$page->{$name})", 
			"", 
			//"ProcessWire ProFields Combo documentation file",
			//"{$config->urls->assets}docs/$className.php",
			//"",
		);
		
		foreach($subfields as $subfield) {
			$valueType = $this->getSubfieldValueType($subfield);
			$doc[] = "@property $valueType \$$subfield->name $subfield->label";
		}
		
		$doc[] = "";
		$out = '';
		if($namespace) $out .= "<" . "?php namespace ProcessWire;\n\n";
		$out .= "/**\n";
		foreach($doc as $line) {
			$out .= " * $line\n";
		}
		$out .= " */\n";
		$out .= "class $className extends ComboValue {}\n\n";
		
		if($writeFile) {
			if(!is_dir($path)) wireMkdir($path);
			if(is_writable($path)) file_put_contents($file, $out);
		}
	
		return $out;
	}

}