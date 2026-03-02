<?php namespace ProcessWire;

/**
 * ProFields: Custom Field Tools
 * 
 * THIS IS PART OF A COMMERCIAL MODULE: DO NOT DISTRIBUTE.
 * This file should NOT be uploaded to GitHub or available for download on any public site.
 *
 * Copyright 2024 by Ryan Cramer Design, LLC
 * ryan@processwire.com
 *
 */
class CustomFieldTools extends Wire {
	
	const objTypePage = 2; 
	const objTypePageArray = 3;
	const objTypeLangValue = 4;
	
	/**
	 * Column types by Inputfield class name
	 * 
	 * Prefix 'Inputfield' to array keys is implied.
	 *
	 * @var string[]
	 *
	 */
	protected $colTypesByInputfield = [
		'Datetime' => 'DATETIME',
		'Float' => 'DOUBLE',
		'Integer' => 'INT',
		'Page' => 'ENUM',
		'Select' => 'ENUM',
		'Text' => 'TEXT',
		'Textarea' => 'TEXT',
	];

	/**
	 * Types allowed for 'data' column
	 * 
	 * @var string[] 
	 * 
	 */
	protected $schemaDataColTypes = [
		'json', 
		'text', 
		'mediumtext', 
		'longtext' 
	];
	
	/**
	 * Get database column types for each property in the field
	 *
	 * @param Page $page
	 * @param Field|CustomField $field
	 * @return array
	 *
	 */
	public function getDatabaseColTypes(Page $page, Field $field): array {
		if($page === null) $page = new Page();
		$inputfields = $field->type->getInputfield($page, $field)->getInputfields();
		$colTypes = [];
		foreach($inputfields->getAll() as $f) {
			/** @var Inputfield $f */
			list(,$name) = explode($field->name . '_', $f->name);
			$colTypes[$name] = $this->getDatabaseColTypeByInputfield($f);
		}
		return $colTypes;
	}

	/**
	 * Identify the likely column type for the given Inputfield
	 *
	 * @param Inputfield $f
	 * @return string
	 *
	 */
	public function getDatabaseColTypeByInputfield(Inputfield $f): string {
		$colType = '';
		foreach($this->colTypesByInputfield as $className => $columnType) {
			if(!wireInstanceOf($f, "Inputfield$className")) continue;
			$colType = $columnType;
			break;
		}
		return $colType ? $colType : 'TEXT';
	}

	/**
	 * Get the Inputfield for a specific property/subfield
	 *
	 * @param Page|NullPage $page
	 * @param Field|CustomField $field
	 * @param string $property
	 * @return Inputfield|null
	 *
	 */
	public function getPropertyInputfield(Page $page, Field $field, string $property) {
		/** @var InputfieldCustom $inputfield */
		$inputfield = $field->type->getInputfield($page, $field);
		$inputfield->set('name', $field->name); // required
		return $inputfield->defs()->getPropertyInputfield($property);
	}

	/**
	 * Sleep a value
	 * 
	 * @param array|Wire|int|mixed|string $value
	 * @return array|int|mixed|string
	 * 
	 */
	public function sleepValue($value) {

		static $level = 0;
		
		$types = [];
		$level++;
		$root = $level === 1;
		
		$languages = $this->wire()->languages;
		$languageValues = [];
		$defaultLanguageId = $languages ? $languages->getDefault()->id : 0;

		if($root && $value instanceof WireData) {
			$value = $value->getArray();
			if($languages) {
				foreach($value as $key => $v) {
					if(!$v instanceof Wire) continue;
					if(!wireInstanceOf($v, 'LanguagesPageFieldValue')) continue;
					foreach($v->getArray() as $langId => $langValue) {
						$langKey = $langId == $defaultLanguageId ? $key : $key . $langId;
						$languageValues[$langKey] = (string) $langValue;
					}
					unset($value[$key]);
					$types[$key] = self::objTypeLangValue;
				}
			}
		}

		if(is_array($value)) {
			$a = [];
			foreach($value as $key => $val) {
				if(is_array($val)) {
					$val = $this->sleepValue($val);
				} else if($val instanceof Wire) {
					if($val instanceof Page) {
						$types[$key] = self::objTypePage;
					} else if($val instanceof PageArray) {
						$types[$key] = self::objTypePageArray;
					} else if($val instanceof LanguagesValueInterface) {
						$types[$key] = self::objTypeLangValue;
					} else {
						$types[$key] = $val->className();
					}
					$val = $this->sleepValue($val);
				}
				$a[$key] = $val;
			}
			$value = $a;

		} else if(is_object($value)) {
			if($value instanceof Page) {
				$value = $value->id;
			} else if($value instanceof PageArray) {
				$value = $value->explode('id');
			} else if($languages && wireInstanceOf($value, 'LanguagesPageFieldValue')) {
				$value = $value->getArray();
			} else if($value instanceof WireData) {
				$value = $this->sleepValue($value);
			} else {
				// unknown, unrecognized or unsupported object value
				$value = (string) $value;
			}
		}

		if($root && is_array($value)) { 
			if(count($languageValues)) $value = array_merge($value, $languageValues);
			ksort($value); // alpha sort required when language values present
			if(count($types)) $value['_t'] = $types;
		}

		$level--;
		
		// if($root) bd($value, 'sleepValue');

		return $value;
	}

	/**
	 * Wakeup a value
	 * 
	 * @param Page $page
	 * @param array $value
	 * @return array
	 * 
	 */
	public function wakeupValue(Page $page,  array $value): array {

		$types = $value['_t'] ?? [];
		unset($value['_t']);
		
		$languages = $this->wire()->languages;
		$langValues = [];
		$defaultLanguage = $languages ? $languages->getDefault() : null;
		$langValueClassName = $languages ? wireClassName('LanguagesPageFieldValue', true) : '';

		foreach($value as $name => $val) {
			$type = $types[$name] ?? '';
	
			if($type == self::objTypePage) {
				$val = $val ? $this->wire()->pages->get((int) $val) : new NullPage();
				
			} else if($type == self::objTypePageArray) {
				$val = empty($value) ? new PageArray() : $this->wire()->pages->getByIDs($val);
			
			} else if($type == self::objTypeLangValue && $languages) {
				$langProperties[$name] = $name;
				/** @var LanguagesPageFieldValue $langValue */
				$langValue = new $langValueClassName($page);
				$langValue->setLanguageValue($defaultLanguage, $val);
				$this->wire($langValue);
				$val = $langValue;
				$langValues[$name] = $langValue;
				
			} else if($type && !ctype_digit("$type") && is_array($val)) {
				// some other object type
				$cls = wireClassName($type, true);
				if(wireClassExists($cls)) {
					$obj = new $cls();
					if(method_exists($obj, '__set')) {
						foreach($val as $k => $v) $obj->$k = $v;
					} else {
						// unsupported object type
					}
				} else {
					// unsupported object type
				}
			} else if($languages && ctype_digit(substr($name, -4))) {
				// i.e. 'desc1234' where 'desc' is name and '1234' is language ID
				foreach($languages as $language) {
					if($language->isDefault()) continue;
					$langId = (string) $language->id; 
					if(!strpos($name, $langId)) continue;
					list($_name, $rest) = explode($langId, $name); // explode('1234', 'desc1234') => 'desc'
					if(!isset($langValues[$_name])) continue;
					if(strlen($rest)) continue;
					/** @var LanguagesPageFieldValue $langValue */
					$langValue = $langValues[$_name];
					$langValue->setLanguageValue($language, $val);
					unset($value[$name]);
					break;
				}
				continue;
			}
		
			$value[$name] = $val;
		}

		return $value;
	}

	/**
	 * Update database schema for 'data' column
	 * 
	 * @param Field $field
	 * @param string $dataType
	 * @return bool
	 * 
	 */
	public function updateDatabaseSchema(Field $field, string $dataType): bool {
		
		$database = $this->wire()->database;
		$table = $field->getTable();
		$dataTypes = $this->getSchemaDataColTypes();
		
		if(!$database->tableExists($table)) return false;
		if(!in_array($dataType, $dataTypes, true)) return false;
		
		$col = $database->getColumns($table, 'data');
		$dataTypeNow = strtolower($col['type']); 
		if($dataTypeNow === $dataType) return true;
	
		try {
			$database->exec("DROP INDEX `data` ON $table");
		} catch(\Exception $e) {
			// there may not be a fulltext daata index if previous type was json
		}
	
		try {
			$database->exec("ALTER TABLE $table MODIFY `data` $dataType NOT NULL");
			$this->message("Modified $table.data from $col[type] to $dataType", "debug nogroup");
		} catch(\Exception $e) {
			$this->error($e->getMessage());
			$dataType = '';
		}
	
		if(empty($dataType)) return false;
		
		if($dataType == 'json') {
			// @todo options to manage multi-value index (MySQL 8.0.17 or higher)
		} else {
			// create fulltext index
			$database->exec("CREATE FULLTEXT INDEX `data` on $table(`data`)");
			$this->message("Created fulltext index for data column", "debug nogroup");
		}

		$field->dataType = $dataType;
		$field->dataTypeNow = $dataType;
		$field->save();
		
		return true;
	}
	
	public function getSchemaDataColTypes(): array {
		return $this->schemaDataColTypes; 
	}
}