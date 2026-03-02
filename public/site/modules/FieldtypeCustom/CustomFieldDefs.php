<?php namespace ProcessWire;

/**
 * ProFields: Custom Inputfield definitions manager
 * 
 * THIS IS PART OF A COMMERCIAL MODULE: DO NOT DISTRIBUTE.
 * This file should NOT be uploaded to GitHub or available for download on any public site.
 *
 * Copyright 2024 by Ryan Cramer Design, LLC
 * ryan@processwire.com
 * 
 * @method string getDefsPath()
 * 
 * @todo rename option so if a field gets renamed it can be re-mapped so data is not lost
 * 
 */
class CustomFieldDefs extends Wire {

	/**
	 * Property in Inputfield objects containing its original name (without Field)
	 * 
	 */
	const _property_name = '_custom_property_name';
	const _parent_inputfield = '_custom_parent';

	/**
	 * Dir name where custom field definitions files are stored
	 * 
	 */
	const defsDirName = 'custom-fields';
	
	/**
	 * Cached Inputfields loaded by getInputfields() method
	 *
	 * @var InputfieldWrapper|null
	 *
	 */
	protected $inputfields = null;

	/**
	 * Same as above but in a flat array indexed by property name
	 * 
	 * @var array|null 
	 * 
	 */
	protected $flatInputfields = null;

	/**
	 * @var Page|null 
	 * 
	 */
	protected $page = null;

	/**
	 * @var Field|null 
	 * 
	 */
	protected $field = null;

	/**
	 * @var string 
	 * 
	 */
	protected $name = '';

	/**
	 * All Inputfield names without field name prefix
	 * 
	 * @var array 
	 * 
	 */
	protected $namesAll = [];

	/**
	 * @var array 
	 * 
	 */
	protected $defsFileErrors = [];

	/**
	 * Column schemes indexed by property name
	 * 
	 * @var array|null
	 * 
	protected $schemas = null;
	 */

	/**
	 * Get path where custom field definitions are stored
	 * 
	 */
	public function ___getDefsPath(): string {
		return $this->wire()->config->paths->templates . self::defsDirName . '/';
	}

	/**
	 * Get definitions file name
	 *
	 * @param string $name
	 * @return string
	 *
	 */
	public function getDefsFile(string $name = ''): string {
		if(empty($name)) {
			$type = $this->field ? $this->field->type : false;
			$name = $type instanceof FieldtypeCustom ? $this->field->name : $this->name;
		}
		$path = $this->getDefsPath();
		$defsJsonFile = "$path$name.json";
		$defsPhpFile = "$path$name.php";
		if(is_file($defsJsonFile)) return $defsJsonFile;
		return $defsPhpFile;
	}

	/**
	 * Get display URL for definitions file
	 * 
	 * @param string $name
	 * @return string
	 * 
	 */
	public function getDefsFileUrl(string $name = ''): string {
		$file = $this->getDefsFile($name); 
		return str_replace($this->wire()->config->paths->root, '/', $file);
	}

	/**
	 * Get the custom Inputfield definitions array
	 *
	 * @param string $name
	 * @return array[]|InputfieldWrapper
	 *
	 */
	public function getInputfieldDefs(string $name = '') {

		$defsFile = $this->getDefsFile($name);
		$defsJson = '';
		$defs = [];
		$errors = [];
		$admin = $this->wire()->config->admin;

		if(is_file($defsFile)) {
			if(substr($defsFile, -4) === '.php') {
				$fuel = $this->wire()->fuel->getArray();
				extract($fuel);
				if($this->page) $page = $this->page;
				$field = $this->field;
				try {
					$defs = include($defsFile);
				} catch(\Throwable $t) {
					if(!$admin) throw $t;
					$errors = [ basename($t->getFile()) . ' line ' . $t->getLine() . ': ' . $t->getMessage() ];
				}
				if(!is_array($defs) && !$defs instanceof InputfieldWrapper) {
					$defsFileUrl = $this->getDefsFileUrl($name);
					$errors[] = "Custom field file did not return array or InputfieldWrapper: $defsFileUrl";
				}
			} else {
				$defsJson = file_get_contents($defsFile);
			}
		} else {
			// $defsFileUrl = $this->getDefsFileUrl($name);
			// $errors[] = "Cannot find custom field definitions file: $defsFileUrl";
		}
		
		if(count($errors)) {
			$this->defsFileErrors = $errors;
			if($admin) foreach($errors as $error) $this->error($error, 'nogroup');
			$defsJson = file_get_contents(__DIR__ . '/error.json');
			$errors = json_encode(implode(', ', $errors));
			$defsJson = str_replace('{message}', substr($errors, 1, -1), $defsJson);
		}

		if($defsJson) {
			$defs = json_decode($defsJson, true);
			if($defs === false) {
				$error = json_last_error_msg();
				$this->error("Failed to decode definitions, please check for JSON errors - $error");
				$defs = [];
			}
		}

		return $defs;
	}
	
	/**
	 * Create and setup a new Inputfield from given settings
	 *
	 * @param string $propertyName
	 * @param array $settings
	 * @param Inputfield|null $parentInputfield
	 * @return Inputfield
	 *
	 */
	protected function createInputfield(string $propertyName, array $settings, Inputfield $parentInputfield = null) {

		$modules = $this->wire()->modules;
		$type = $settings['type'] ?? 'InputfieldText';
		$inputName = $this->name . '_' . $propertyName;
		$children = $settings['children'] ?? [];
		
		$this->namesAll[$inputName] = $propertyName;
		
		if(strpos($type, 'Inputfield') !== 0) $type = 'Inputfield' . ucfirst($type);

		$f = $modules->get($type); /** @var Inputfield $f */
		if(!$f) $f = $modules->get('InputfieldText');
		
		unset($settings['type'], $settings['children']);

		if(empty($settings['label'])) $settings['label'] = ucfirst($propertyName);

		// for select types, convert regular PHP $options array to associative array
		// where both the keys and the values are the array values
		$o = $settings['options'] ?? null;
		if(is_array($o) && key($o) === 0) {
			$options = [];
			foreach($o as $k => $v) {
				if(is_int($k)) $k = $v; 
				$options[$k] = $v;
			}
			$settings['options'] = $options;
		}
		
		foreach($settings as $key => $value) {
			$f->set($key, $value);
		}

		$f->attr('name', $inputName);
		$f->set(self::_property_name, $propertyName);
		$this->flatInputfields[$propertyName] = $f;

		if($parentInputfield) {
			$f->set(self::_parent_inputfield, $parentInputfield);
		}
	
		/*
		if(!empty($settings['schema'])) {
			$schema = $settings['schema'];
			if($schema === true) $schema = $this->suggestSchemaFromInputfield($f);
			if($schema && !is_bool($schema)) $this->schemas[$propertyName] = $schema;
		}
		*/
		
		$this->createdInputfield($f);
		
		if($children && $f instanceof InputfieldWrapper) {
			$this->createInputfieldChildren($f, $propertyName, $children);
		}

		$f->resetTrackChanges(true);

		return $f;
	}

	/**
	 * Called after an Inputfield has been created
	 * 
	 * @param Inputfield $f
	 * 
	 */
	protected function createdInputfield(Inputfield $f) {
		
		if($this->field) {
			$f->hasField = $this->field;
			$f->hasFieldtype = $this->field->type;
		}

		if($this->page) {
			$f->hasPage = $this->page;
		}

		if($f instanceof InputfieldCheckbox) {
			// autocheck option required for checkboxes to work properly here
			$f->autocheck = true;
		} else if($f instanceof InputfieldPage) {
			$className = $f->inputfield;
			// convert things like 'asmSelect' to 'InputfieldAsmSelect'
			if(stripos($className, 'Inputfield') !== 0) {
				$f->inputfield = 'Inputfield' . ucfirst($className);
			}
		}
		
		$cls = $f->getSetting('inputfieldClass'); 
		if($cls && strpos($cls, 'Inputfield') !== 0) {
			$f->set('inputfieldClass', 'Inputfield' . ucfirst($cls)); 
		}
	}

	/**
	 * Create and setup children of an InputfieldWrapper
	 * 
	 * This is a helper for the createInputfield() method
	 * 
	 * @param InputfieldWrapper $f
	 * @param string $propertyName Original name of parent fieldset/wrapper
	 * @param string|array $children
	 * 
	 */
	protected function createInputfieldChildren(InputfieldWrapper $f, string $propertyName, $children) {
		$ns = '';
		
		if(is_string($children)) {
			// children indicates the php/json file that should be used
			$file = $this->getDefsPath() . basename($children);
			if(is_file($file)) {
				$ext = pathinfo($children, PATHINFO_EXTENSION);
				// if file is prefixed with a "_" then it will be namespaced for fieldset
				// i.e. fieldset "address" with child "city" will become "address_city"
				$ns = strpos($children, '_') === 0 ? $propertyName . '_' : '';
				if($ext === 'php') {
					$page = $this->page ? $this->page : new NullPage();
					$field = $this->field ? $this->field : null;
					$children = include($file);
				} else if($ext === 'json') {
					$children = json_decode(file_get_contents($file), true);
				}
			}
		}
		
		if(is_array($children)) {
			foreach($children as $childName => $childSettings) {
				if($ns) $childName = $ns . $childName;
				$ff = $this->createInputfield($childName, $childSettings, $f);
				$f->add($ff);
			}
		}
	}

	/**
	 * Setup a single Inputfield, going recursive with any InputfieldWrappers
	 * 
	 * @param Inputfield $f
	 * @param string $propertyName
	 * 
	 */
	protected function setupInputfield(Inputfield $f, string $propertyName) {
		
		$name = $this->name . '_' . $propertyName;
		
		$this->namesAll[$name] = $propertyName;
		
		$f->attr('name', $name);
		$f->set(self::_property_name, $propertyName);
		
		if($this->field) {
			$f->hasField = $this->field;
			$f->hasFieldtype = $this->field->type;
		}
		
		if($this->page) {
			$f->hasPage = $this->page;
		}
	
		if($f instanceof InputfieldWrapper) {
			foreach($f->getAll() as $ff) {
				/** @var Inputfield $ff */
				if($ff->getSetting(self::_property_name)) continue; // already setup
				$propName = $ff->attr('name');
				$this->setupInputfield($ff, $propName);
			}
		}
	}

	/**
	 * Convert defined Inputfields for given field name to InputfieldWrapper
	 *
	 * @return InputfieldWrapper
	 *
	 */
	protected function setupInputfields(): InputfieldWrapper {

		$inputfields = new InputfieldWrapper();
		$this->wire($inputfields);
		$defs = $this->getInputfieldDefs();
		// $this->schemas = [];

		if($defs instanceof InputfieldWrapper) {
			$inputfields = $defs;
			foreach($inputfields->getAll() as $f) {
				$this->setupInputfield($f, $f->attr('name'));
			}
		} else if(is_array($defs)) {
			foreach($this->getInputfieldDefs() as $propertyName => $settings) {
				$f = $this->createInputfield($propertyName, $settings);
				$inputfields->add($f);
			}
		}

		// dependencies
		foreach($inputfields->getAll() as $f) {
			foreach([ 'showIf', 'requiredIf' ] as $ifKey) {
				$if = $f->$ifKey;
				if(empty($if)) continue;
				foreach($this->namesAll as $name => $prop) {
					if(strpos($if, $prop) === false) continue;
					$if = preg_replace('/\b' . $prop . '([!=^$.])/', $name . '$1', $if);
					$f->$ifKey = $if;
				}
			}
		}	

		return $inputfields;
	}

	/*
	protected function suggestSchemaFromInputfield(Inputfield $f): string {
		if($f instanceof InputfieldTextarea) {
			return 'MEDIUMTEXT';
		} else if($f instanceof InputfieldFloat) {
			return 'DOUBLE';
		} else if($f instanceof InputfieldInteger) {
			return $f->min >= 0 ? 'INT UNSIGNED' : 'INT';
		} else if($f instanceof InputfieldText) {
			return 'TEXT';
		} else if($f instanceof InputfieldPage) {
			return $f->derefAsPage ? 'INT UNSIGNED' : 'TEXT';
		}
		return 'TEXT';
	}
	*/

	/**
	 * Get the custom Inputfields
	 *
	 * @param string $name
	 * @return InputfieldWrapper
	 *
	 */
	public function getInputfields(string $name = ''): InputfieldWrapper {
		if($name && $name !== $this->name) $this->setName($name);
		if($this->inputfields === null) {
			// don't allow getting Inputfields until we have a name
			if(empty($this->name)) return new InputfieldWrapper();
			$this->inputfields = $this->setupInputfields();
		}
		return $this->inputfields;
	}

	/**
	 * Get Inputfield for given property/subfield
	 * 
	 * @param string $name
	 * @return Inputfield|null
	 * 
	 */
	public function getPropertyInputfield(string $name) {
		if(!$this->inputfields) $this->getInputfields();
		return $this->flatInputfields[$name] ?? null;
	}

	/**
	 * Get all Inputfields in a flattened array indexed by property name
	 * 
	 * @return array
	 * 
	 */
	public function getFlatInputfields() {
		return $this->flatInputfields;
	}

	/**
	 * @param string $property
	 * @return array|string
	 * 
	public function getSchemas(string $property = '') {
		if($this->inputfields === null) $this->getInputfields();
		if($this->schemas === null) return $property ? '' : [];
		if($property) return $this->schemas[$property] ?? '';
		return $this->schemas;
	}
	 */
	
	public function getDefsFileErrors() {
		return $this->defsFileErrors; 
	}

	public function setName($name) {
		if($name != $this->name) $this->inputfields = null;
		$this->name = (string) $name;
	}
	
	public function getName(): string {
		return $this->name;
	}
	
	public function setPage(Page $page) {
		$this->page = $page;
	}
	
	public function getPage() {
		return $this->page; 
	}
	
	public function setField(Field $field) {
		$this->field = $field;
	}
	
	public function getField() {
		return $this->field;
	}


}