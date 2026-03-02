<?php namespace ProcessWire;

require_once(__DIR__ . '/CustomWireData.php');
require_once(__DIR__ . '/CustomField.php');
require_once(__DIR__ . '/CustomFieldDefs.php');
require_once(__DIR__ . '/CustomFieldTools.php');

/**
 * ProFields: Fieldtype Custom
 * 
 * THIS IS PART OF A COMMERCIAL MODULE: DO NOT DISTRIBUTE.
 * This file should NOT be uploaded to GitHub or available for download on any public site.
 *
 * Copyright 2024 by Ryan Cramer Design, LLC
 * ryan@processwire.com
 *
 * 
 * @method CustomWireData createBlankValue(Page $page, Field $field)
 * 
 */
class FieldtypeCustom extends Fieldtype {

	public static function getModuleInfo(): array {
		return array(
			'title' => 'ProFields: Custom',
			'version' => 1,
			'summary' => 'Custom fields let you define the subfields programmatically in PHP or JSON.',
			'icon' => 'diamond',
			'installs' => 'InputfieldCustom',
		);
	}
	
	const debug = false;

	const defaultDataColType = 'mediumtext';

	/**
	 * @var CustomFieldTools|null 
	 * 
	 */
	protected $tools = null;

	/**
	 * When true getDatabaseSchema() returns columns for each subfield definition
	 * 
	 * @var bool 
	 * 
	 */
	protected $useDatabaseColumnsMode = false;

	/**
	 * @return CustomFieldTools
	 * 
	 */
	public function tools() {
		if($this->tools === null) {
			$this->tools = new CustomFieldTools();
			$this->wire($this->tools);
		}
		return $this->tools;
	}

	/**
	 * @param CustomField|DatabaseQuerySelect $o
	 * @return FieldtypeCustomQuery
	 * 
	 */
	protected function query($o): FieldtypeCustomQuery {
		require_once(__DIR__ . '/FieldtypeCustomQuery.php');
		return new FieldtypeCustomQuery($o);
	}

	/**
	 * Create blank value
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @return CustomWireData
	 * 
	 */
	public function ___createBlankValue(Page $page, Field $field): CustomWireData {
		$value = new CustomWireData();
		$this->wire($value);
		$value->setPage($page);
		$value->setField($field);
		return $value;
	}

	/**
	 * Get a blank value
	 *
	 * @param Page $page
	 * @param Field $field
	 * @return CustomWireData
	 *
	 */
	public function getBlankValue(Page $page, Field $field): CustomWireData {
		return $this->createBlankValue($page, $field);
	}

	/**
	 * Sanitize a Textareas value for placement in a Page
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param WireData|array|string $value
	 * @return CustomWireData
	 *
	 */
	public function sanitizeValue(Page $page, Field $field, $value): CustomWireData {
		
		if(is_string($value)) {
			// potentially decode JSON to array
			$value = json_decode($value, true);
		}
		
		if($value instanceof WireData) {
			if($value instanceof CustomWireData) {
				// already in needed format
			} else {
				// convert anonymous WireData value to CustomWireData
				$v = $this->getBlankValue($page, $field);
				$v->setArray($value->getArray());
				$value = $v;
			}
		} else if(is_array($value)) {
			// array to CustomWireData
			$v = $this->getBlankValue($page, $field);
			$v->setArray($value);
			$value = $v;
		} else {
			// some other kind of value
			$value = $this->getBlankValue($page, $field);
		}
		
		return $value;
	}

	/**
	 * Prepare a value for saving
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param WireData|array $value
	 * @return string
	 *
	 */
	public function ___sleepValue(Page $page, Field $field, $value): string {
		if($value === null) $value = $this->getBlankValue($page, $field);
		if($value instanceof CustomWireData) {
			$value = $this->tools()->sleepValue($value);
		} else {
			throw new WireException("Invalid value for sleepValue in " . $this->className()); 
		}
		if(self::debug) $this->bd($value, 'sleepValue');
		return json_encode($value);
	}

	/**
	 * Prepare value for runtime use
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param string $value
	 * @return CustomWireData
	 *
	 */
	public function ___wakeupValue(Page $page, Field $field, $value): CustomWireData {

		$wakeupValue = $this->getBlankValue($page, $field);
		
		if(is_string($value)) {
			$value = json_decode($value, true);
		} else if($value instanceof WireData) {
			$value = $value->getArray(); 
		}
		
		if(is_array($value)) {
			$value = $this->tools()->wakeupValue($page, $value);
			$wakeupValue->setArray($value);
		}
		
		if(self::debug) $this->bd($value, 'wakeupValue');
		$wakeupValue->resetTrackChanges(true);
		
		return $wakeupValue;
	}

	/**
	 * Format the given value for output and return a string of the formatted value
	 *
	 * @param Page $page Page that the value lives on
	 * @param CustomField $field Field that represents the value
	 * @param CustomWireData $value The value to format
	 * @return CustomWireData
	 *
	 */
	public function ___formatValue(Page $page, Field $field, $value): CustomWireData {
		if(!$value instanceof CustomWireData) $value = $this->getBlankValue($page, $field);
		return $value;
	}
	
	/**
	 * Render a markup string of the value.
	 *
	 * @param Page $page Page that $value comes from
	 * @param Field $field Field that $value comes from
	 * @param mixed $value Optionally specify the value returned by `$page->getFormatted('field')`.
	 *  When specified, value must be a formatted value.
	 * 	If null or not specified (recommended), it will be retrieved automatically.
	 * @param string $property Optionally specify the property or index to render. If omitted, entire value is rendered.
	 * @return string|MarkupFieldtype Returns a string or object that can be output as a string, ready for output.
	 * 	Return a MarkupFieldtype value when suitable so that the caller has potential specify additional
	 * 	config options before typecasting it to a string.
	 *
	 */
	public function ___markupValue(Page $page, Field $field, $value = null, $property = ''):string {
		if($value === null) $value = $page->getUnformatted($field->name);
		$inputfield = $this->getInputfield($page, $field);
		$inputfield->attr('name', $field->name);
		$inputfield->setValue($value);
		if($property) {
			$f = $inputfield->getPropertyInputfield($property);
			$out = $f ? $f->renderValue() : $inputfield->renderValue();
		} else {
			$out = $inputfield->renderValue();
		}
		return $out;
	}

	/**
	 * Get the InputfieldCustom, or optionally the Inputfield for a subfield within it
	 *
	 * @param Page $page
	 * @param Field $field
	 * @return InputfieldCustom
	 *
	 */
	public function getInputfield(Page $page, Field $field): InputfieldCustom {
		$modules = $this->wire()->modules;
		
		/** @var InputfieldCustom $inputfield */
		$inputfield = $modules->get('InputfieldCustom');
		
		// do not set name here
		$inputfield->set('hasField', $field);
		$inputfield->set('hasFieldtype', $this);
		$inputfield->set('hasPage', $page);
		
		if($page->id) {
			$value = $page->get($field->name);
			
		} else {
			// for internal use when given NullPage
			$value = [];
			$inputfield->set('name', $field->name); // required
			foreach($inputfield->defs()->getInputfields($field->name)->getAll() as $f) {
				/** @var Inputfield $f */
				// populate with default/blank values
				// @todo is this necessary, won't they already have blank values?
				$value[$f->name] = $f->val();
			}
		}
		
		if($value instanceof WireData) $value = $value->getArray();
		if(is_array($value)) $inputfield->val($value);
		
		return $inputfield;
	}

	/**
	 * Get database schema used by the Field
	 *
	 * @param CustomField $field
	 * @return array
	 *
	 */
	public function getDatabaseSchema(Field $field): array {
		/** @var CustomField $field */
		
		$dataType = $field->dataType;
		$dataTypes = $this->tools()->getSchemaDataColTypes();
		
		if(!in_array($dataType, $dataTypes, true)) $dataType = self::defaultDataColType;
		
		$schema = parent::getDatabaseSchema($field);
		$schema['data'] = "$dataType NOT NULL";
		
		if($dataType === 'json') {
			unset($schema['keys']['data']); 
		} else {
			$schema['keys']['data'] = 'FULLTEXT KEY data (data)';
		}

		if($this->useDatabaseColumnsMode && $field instanceof CustomField) {
			// database columns mode: return separate DB columns for each property/subfield
			// this is so that getSelectorInfo() can include all properties using core logic
			// and this is not actually used in the database at present
			$page = new Page();
			foreach($this->tools()->getDatabaseColTypes($page, $field) as $name => $colType) {
				$schema[$name] = $colType;
				if(strpos($colType, 'TEXT') !== false) {
					$schema['keys'][$colType] = "FULLTEXT KEY $name ($name)";
				}
			}
		} else if($dataType != $field->dataTypeNow && $dataType && $field->dataTypeNow && $field->id) {
			$this->tools->updateDatabaseSchema($field, $dataType);
		}
		
		return $schema;
	}

	/**
	 * @param PageFinderDatabaseQuerySelect $query
	 * @param string $table The table name to use
	 * @param string $subfield Name of the field (typically 'data', unless selector explicitly specified another)
	 * @param string $operator The comparison operator
	 * @param mixed $value The value to find
	 * @return PageFinderDatabaseQuerySelect|DatabaseQuerySelect $query
	 *
	 */
	public function getMatchQuery($query, $table, $subfield, $operator, $value) {
		if($subfield && $subfield != 'data' && $query->field) {
			$field = $query->field; /** @var CustomField $field */
			$this->query($field)->getMatchQuery($query, $table, $subfield, $operator, $value);
		} else {
			$fieldtype = $this->wire()->fieldtypes->FieldtypeTextarea;
			$fieldtype->getMatchQuery($query, $table, $subfield, $operator, $value);
		}
		return $query;
	}

	/**
	 * Return array with information about what properties and operators can be used with this field
	 *
	 * @param CustomField $field
	 * @param array $data Array of extra data, when/if needed
	 * @return array
	 *
	 */
	public function ___getSelectorInfo(Field $field, array $data = array()): array {
		$this->useDatabaseColumnsMode = true;
		$info = parent::___getSelectorInfo($field, $data);
		$this->useDatabaseColumnsMode = false;
		$info = $this->query($field)->getSelectorInfo($info);
		return $info;
	}

	/**
	 * Get class name to use Field objects of this type
	 * 
	 * @param array $a Field data from DB (if needed)
	 * @return string Return class name or blank to use default Field class
	 * @since 3.0.146
	 *
	 */
	public function getFieldClass(array $a = array()): string {
		return 'CustomField';
	}
	
	public function bd($a, $b = null) {
		if(self::debug && function_exists('bd')) ($b === null ? bd($a) : bd($a, $b));
	}

	/**
	 * Get Inputfields to configure the Field
	 *
	 * @param Field $field
	 * @return InputfieldWrapper
	 *
	 */
	public function ___getConfigInputfields(Field $field): InputfieldWrapper {
		$config = $this->wire()->config;
		$fs = parent::___getConfigInputfields($field);
		require_once(__DIR__ . '/config.php');
		FieldtypeCustom_getConfigInputfields($fs, $field);
		return $fs;
	}

	/**
	 * Install
	 * 
	 */
	public function ___install() {
		parent::___install();
		$files = $this->wire()->files;
		$defs = new CustomFieldDefs();
		$this->wire($defs);
		$customPath = $defs->getDefsPath();
		$templatesPath = $this->wire()->config->paths->templates;
		if(!is_dir($customPath) && is_writable($templatesPath)) {
			if($files->mkdir($customPath)) {
				$this->message("Created directory: $customPath"); 
			}
		}
	}

	/**
	 * Uninstall
	 * 
	 */
	public function ___uninstall() {
		parent::___uninstall();
		$defs = new CustomFieldDefs();
		$path = $defs->getDefsPath();
		if(is_dir($path)) {
			$this->warning(
				"Please note that directory $path has been left as-is. " . 
				"You may remove it now if you do not plan to re-install FieldtypeCustom."
			);
		}
	}

}