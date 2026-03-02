<?php namespace ProcessWire;

/**
 * ProcessWire ProFields Combo: database schema management
 *
 * Part of the ProFields package.
 * Please do not distribute.
 *
 * Copyright (C) 2024 by Ryan Cramer
 *
 * https://processwire.com
 *
 *
 */

class ComboSchema extends Wire {

	/**
	 * Schema types
	 * 
	 * @var array
	 * 
	 */
	protected $schemaTypes = array(
		'n-int-tiny' => array(
			'label' => 'Tiny integer',
			'type' => 'TINYINT',
			'min' => -128,
			'max' => 127,
		),
		'n-int-tiny-0' => array(
			'label' => 'Tiny integer (default 0)',
			'type' => 'TINYINT NOT NULL DEFAULT 0',
			'min' => -128,
			'max' => 127,
		),
		'n-int-tiny-u' => array(
			'label' => 'Tiny integer unsigned',
			'type' => 'TINYINT UNSIGNED',
			'min' => 0,
			'max' => 255,		
		),
		'n-int-small' => array(
			'label' => 'Small integer',
			'type' => 'SMALLINT',
			'min' => -32768,
			'max' => 32767,
		),
		'n-int-small-u' => array(
			'label' => 'Small integer unsigned',
			'type' => 'SMALLINT UNSIGNED',
			'min' => 0,
			'max' => 65535,
		),
		'n-int-medium' => array(
			'label' => 'Medium integer',
			'type' => 'MEDIUMINT',
			'min' => -8388608,
			'max' => 8388607
		),
		'n-int-medium-u' => array(
			'label' => 'Medium integer unsigned',
			'type' => 'MEDIUMINT UNSIGNED',
			'min' => 0,
			'max' => 16777215,
		),
		'n-int' => array(
			'label' => 'Integer',
			'type' => 'INT',
			'min' => '-2147483648',
			'max' => '2147483647',
		),
		'n-int-u' => array(
			'label' => 'Integer unsigned',
			'type' => 'INT UNSIGNED',
			'min' => '0',
			'max' => '4294967295',
		),
		'n-int-big' => array(
			'label' => 'Big integer',
			'type' => 'BIGINT',
			'min' => '-9223372036854775808',
			'max' => '9223372036854775807',
		),
		'n-int-big-u' => array(
			'label' => 'Big integer unsigned',
			'type' => 'BIGINT UNSIGNED',
			'min' => '0',
			'max' => '18446744073709551615',
		),
		'n-decimal' => array( // precision:10, scale:2
			'label' => 'Decimal',
			'type' => 'DECIMAL(precision,scale)',
		),
		'n-decimal-u' => array(
			'label' => 'Decimal unsigned',
			'type' => 'DECIMAL(precision,scale) UNSIGNED',
		),
		'n-float' => array(
			'label' => 'Float',
			'type' => 'FLOAT',
		),
		'n-double' => array(
			'label' => 'Double',
			'type' => 'DOUBLE',
		),
		's-text-tiny' => array(
			'label' => 'Tiny text (up to 255 bytes)',
			'type' => 'TINYTEXT',
		),
		's-text' => array(
			'label' => 'Text (up to 64 KB)',
			'type' => 'TEXT',
		),
		's-text-medium' => array(
			'label' => 'Medium text (up to 16 MB)',
			'type' => 'MEDIUMTEXT',
		),
		's-text-long' => array(
			'label' => 'Long text (up to 4 GB)',
			'type' => 'LONGTEXT',
		),
		's-char' => array(
			'label' => 'Char (fixed length up to 191 characters)',
			'type' => 'CHAR(length)', // to 191 indexable
		),
		's-char-var' => array(
			'label' => 'Varchar (variable length up to 191 characters)',
			'type' => 'VARCHAR(length)', // to 191 indexable
		),
		'd-date' => array(
			'label' => 'Date',
			'type' => 'DATE',
		),
		'd-date-time' => array(
			'label' => 'Date and time',
			'type' => 'DATETIME',
		)
	);

	/**
	 * Input type to schema type
	 * 
	 * @var array
	 * 
	 */
	protected $inputTypes = array(
		'asmselect' => 's-text',
		'checkbox' => 'n-int-tiny-0',
		'checkboxes' => 's-text',
		'ckeditor' => 's-text-medium',
		'datetime' => 'd-date-time',
		'email' => 's-text-tiny',
		'file' => 's-text',
		'float' => 'n-float',
		'image' => 's-text',
		'integer' => 'n-int',
		'page' => 's-text',
		'radios' => 's-char-var',
		'select' => 's-char-var',
		'selectmultiple' => 's-text',
		'text' => 's-text',
		'textarea' => 's-text-medium',
		//'toggle' => 'n-int-tiny', // didn't support non-selected state
		'toggle' => 's-text-tiny',
		'url' => 's-text',
		'*' => 's-text',
	);

	/**
	 * Get all schema types info arrays
	 * 
	 * @return array
	 * 
	 */
	public function getSchemaTypes() {
		return $this->schemaTypes;
	}

	/**
	 * Get default schema type for given input type
	 * 
	 * @param string $inputType
	 * @param bool $getInfo Get info array rather than schema type (string)?
	 * @return string
	 * 
	 */
	public function getDefaultSchemaType($inputType, $getInfo = false) {
		$inputType = strtolower($inputType);
		if(strpos($inputType, '_')) list($inputType,) = explode('_', $inputType, 2);
		if(isset($this->inputTypes[$inputType])) {
			$schemaType = $this->inputTypes[$inputType];
		} else {
			$schemaType = $this->inputTypes['*'];
		}
		if($getInfo) return $this->schemaTypes[$schemaType];
		return $schemaType;
	}
	
	/**
	 * Get database schema used by the Field
	 *
	 * @param ComboField|Field $field
	 * @param array $schema
	 *
	 * @return array
	 *
	 */
	public function getDatabaseSchema(Field $field, array $schema) {

		$schema['data'] = 'mediumtext';
		$schema['keys']['data'] = 'FULLTEXT KEY `data` (`data`)';

		if(!$field instanceof ComboField) {
			$field = $this->wire()->fields->get($field->name);
			if(!$field instanceof ComboField) return $schema;
		}

		/** @var ComboField $field */

		$comboSettings = $field->getComboSettings();
		$qty = $comboSettings->findMaxQty();

		for($n = 1; $n <= $qty; $n++) {

			$prefix = $comboSettings->prefix($n);
			$type = strtolower((string) $comboSettings->get($prefix . 'type'));
			$xidx = (int) $comboSettings->get($prefix . 'xidx');
			$col = $comboSettings->colName($n);
			
			if(empty($type) || $type === 'delete') continue;
		
			/* @todo clone subfield option
			if(strpos($type, 'clone-') === 0) {
				list(,$cloneN) = explode('clone-', $type, 2);
				$cloneN = (int) $cloneN;
				$clonePrefix = $comboSettings->prefix($cloneN);
				$type = strtolower((string) $comboSettings->get($clonePrefix . 'type'));
				$xidx = (int) $comboSettings->get($clonePrefix . 'xidx');
			}
			*/

			$languageType = $comboSettings->isLanguageType($type, true);
			if($languageType) $type = $languageType;

			$typeSchema = $this->getColumnTypeSchema($field, $type, $n);
			$indexType = $this->getColumnIndexSchema($typeSchema, $col);

			$schema[$col] = $typeSchema;
			if($xidx) {
				// index disabled
				unset($schema['keys'][$col]); 
			} else {
				$schema['keys'][$col] = $indexType;
			}
		}

		return $schema;
	}
	
	/**
	 * Get database table schema for given column number
	 *
	 * @param ComboField $field
	 * @param string $inputType
	 * @param int $num
	 * @return array
	 *
	 */
	public function getColumnTypeInfo(ComboField $field, $inputType, $num) {

		$comboSettings = $field->getComboSettings();
		$schemaType = $comboSettings->getSubfieldProperty($num, 'schemaType');
		$schemaData = $comboSettings->getSubfieldProperty($num, 'schemaData');

		if(empty($schemaType) || $schemaType === 'auto' || !isset($this->schemaTypes[$schemaType])) {
			if(isset($this->inputTypes[$inputType])) {
				$schemaType = $this->inputTypes[$inputType];
			} else {
				$schemaType = $this->inputTypes['*'];
			}
		}

		$schemaInfo = $this->schemaTypes[$schemaType];
		$type = $schemaInfo['type'];

		if(strpos($type, '(precision,scale)')) {
			list($precision, $scale) = array(10, 2);
			if(strpos("$schemaData", ',')) {
				list($precision, $scale) = explode(',', $schemaData); 
				$precision = (int) trim($precision);
				$scale = (int) trim($scale);
			}
			if($precision < 1) $precision = 10;
			if($scale < 0) $scale = 2;
			$type = str_replace('(precision,scale)', "($precision,$scale)", $type);

		} else if(strpos($type, '(length)')) {
			$length = (int) $schemaData;
			if($length < 1 || $length > 191) $length = 191;
			$type = str_replace('(length)', "($length)", $type);
		}
		
		$schemaInfo['type'] = $type;

		return $schemaInfo;
	}

	/**
	 * Get database table schema for given column number
	 *
	 * @param ComboField $field
	 * @param string $inputType
	 * @param int $num
	 * @return bool|string
	 *
	 */
	public function getColumnTypeSchema(ComboField $field, $inputType, $num) {
		$saving = $field->get('_comboSaving');
		$info = $this->getColumnTypeInfo($field, $inputType, $num);
		$type = $info['type'];
		if($saving && strpos($type, 'DEFAULT') === false && strpos($type, 'NOT NULL') === false) {
			// int and float fields support null, this is recognized by Fieldtype::savePageField
			$nullTypes = array('INT', 'FLOAT', 'DOUBLE', 'DATETIME', 'DATE'); // @todo also DECIMAL?
			foreach($nullTypes as $nullType) {
				if(strpos($type, $nullType) === false) continue;
				$type .= ' DEFAULT NULL';
				break;
			}
		}
		return $type;
	}

	/**
	 * Get index schema
	 *
	 * @param string|array $typeSchema $type schema or info array
	 * @param string $col
	 * @return string
	 *
	 */
	public function getColumnIndexSchema($typeSchema, $col) {
		if(is_array($typeSchema)) $typeSchema = $typeSchema['type'];
		if(strpos($typeSchema, 'TEXT') !== false) {
			$schema = "FULLTEXT `$col` (`$col`)";
		} else {
			$schema = "INDEX `$col` (`$col`)";
		}
		return $schema;
	}

	/**
	 * Update database schema to be consistent with current settings
	 *
	 * @param ComboField $field
	 * @return array Summary of what was added/modified/dropped
	 * @throws WireException
	 *
	 */
	public function updateDatabaseSchema(ComboField $field) {
		
		$oldNames = array(); // names present in existing database schema
		$newNames = array(); // names present in runtime schema
		$oldCols = array(); // array [ "i3" => "TEXT" ] present in existing database schema
		$newCols = array(); // array [ "i3" => "TEXT" ] present in runtime/configured schema
		$oldKeys = array(); // array [ "colName" => true|false ] whether column has key/index in current DB 
		$sqls = array(); // SQL statements to execute
		$dataColType = ''; // type for the 'data' column

		$database = $this->wire()->database;
		$sanitizer = $this->wire()->sanitizer;
		
		$comboSettings = $field->getComboSettings();
		$table = $field->getTable();
		$alter = "ALTER TABLE `$table`";
		
		$labels = array(
			'renameCol' => $this->_('Rename column'), 
			'addCol' => $this->_('Add new column'), 
			'addIndex' => $this->_('Add index to column'),
			'changeType' => $this->_('Change column type'), 
			'dropIndex' => $this->_('Remove index from column'), 
			'dropCol' => $this->_('Remove column'), 
		);
	
		// get current schema: populate $oldCols, $oldNames, $oldKeys
		$query = $database->prepare("DESCRIBE `$table`");
		$query->execute();

		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			$col = $row['Field'];
			if($col === 'data') $dataColType = strtoupper($row['Type']);
			if($col[0] != 'i') continue; 
			if(!ctype_digit(substr($col, 1))) {
				$colName = $col;
				list($col,) = explode('_', $col, 2);
				$oldNames[$col] = $colName;
			} else {
				$oldNames[$col] = $col;
			}
			$type = $this->reduceColType($row['Type']);
			if(strtoupper($row['Null']) === 'NO') $type .= ' NOT NULL';
			if($row['Default'] !== null) $type .= " DEFAULT $row[Default]";
			$oldCols[$col] = $type;
			$oldKeys[$col] = empty($row['Key']) ? false : true;
		}

		$query->closeCursor();
		
		// get new schema (use of FieldtypeCombo::getDatabaseSchema intended)
		$newSchema = $field->type->getDatabaseSchema($field); 
		
		// check if 'data' column schema needs update (rare)
		if($dataColType != strtoupper($newSchema['data'])) {
			$sqls[] = "$alter DROP INDEX `data`";
			$sqls[] = "$alter MODIFY `data` $newSchema[data]";
			$sqls[] = "$alter ADD " . $newSchema['keys']['data'];
		}
		
		// identify all new column names and schema: populate $newCols and $newNames
		foreach($newSchema as $col => $type) {
			if($col[0] != 'i') continue; // i.e. i3 or i3_email
			if(!ctype_digit(substr($col, 1))) {
				// column is in "i3_email" format
				$colName = $col;
				list($col,) = explode('_', $col, 2);
				$newNames[$col] = $sanitizer->fieldName($colName);
			} else {
				// column is in "i3" format (no field name in column)
				$newNames[$col] = $col;
			}
			$newCols[$col] = strtoupper($type);
		}

		// find columns to rename 
		foreach($newNames as $col => $newName) {

			// column does not exist in actual DB schema
			if(!isset($oldNames[$col])) continue;

			// column has no changes to name
			if($oldNames[$col] === $newName) continue;
		
			$oldName = $oldNames[$col];
			$newType = $newCols[$col];
			$newIndex = isset($newSchema['keys'][$newName]) ? $newSchema['keys'][$newName] : '';
			$name = $comboSettings->name($col);
		
			$sqls[] = "$alter DROP INDEX `$oldName`";
			$sqls[] = "$alter CHANGE `$oldName` `$newName` $newType # $name: $labels[renameCol] '$oldName' => '$newName'";
			if($newIndex) $sqls[] = "$alter ADD $newIndex # $name: $labels[addIndex] '$newName'";
		}

		// find columns to add
		foreach($newCols as $col => $type) {
			$name = $comboSettings->name($col);
			$colName = $sanitizer->fieldName($comboSettings->colName($col));
			$index = isset($newSchema['keys'][$colName]) ? $newSchema['keys'][$colName] : '';
			if(!isset($oldCols[$col])) {
				// add new column
				$sqls[] = "$alter ADD `$colName` $type # $name: $labels[addCol] '$colName' - $type";
				if($index) $sqls[] = "$alter ADD $index # $name: $labels[addIndex] '$colName'";

			} else if($oldCols[$col] !== $this->reduceColType($newCols[$col])) {
				// modify existing column
				$sqls[] = "$alter DROP INDEX `$colName`";
				$sqls[] = "$alter MODIFY `$colName` $type # $name: $labels[changeType] '$colName' - $type";
				if($index) $sqls[] = "$alter ADD $index # $name: $labels[addIndex] '$colName'";
			}
		}
		
		// check for indexes that need to be added or removed
		foreach($oldKeys as $col => $hasIndex) {
			$name = $comboSettings->name($col);
			$colName = $oldNames[$col];
			if($hasIndex) {
				// currently has index
				if(empty($newSchema['keys'][$colName])) {
					// new schema dictates NO index
					$sqls[] = "$alter DROP INDEX `$colName` # $name: $labels[dropIndex] '$colName'";
					$oldKeys[$colName] = false;
				}
			} else {
				// current has NO index
				if(!empty($newSchema['keys'][$colName])) {
					// new schema dictates index
					$index = $newSchema['keys'][$colName];
					$sqls[] = "$alter ADD $index # $name: $labels[addIndex] '$colName'";
				}
			}
		}

		// columns to delete	
		foreach($oldCols as $col => $type) {
			if(!isset($newCols[$col])) {
				$name = $comboSettings->name($col);
				$colName = $oldNames[$col];
				if(empty($name)) $name = $colName;
				// delete existing column	
				$sqls[] = "$alter DROP `$colName` # $name: $labels[dropCol] '$name'";
			}
		}

		$completed = array();
		
		foreach($sqls as $sql) {
			$message = '';
			if(strpos($sql, '#')) list($sql, $message) = explode('#', $sql);
			if($message) $message = "$field->name." . trim($message);
			try {
				$database->exec($sql);
				if($message) {
					$completed[] = $message;
					$this->log($message);
				}
			} catch(\Exception $e) {
				if(strpos($sql, 'DROP INDEX') === false) {
					if($message) $this->error($this->_('Failed') . " - $message"); 
					$message = ($message ? "$message - " : "") . $e->getMessage() . " --- $sql";
					$this->log("Failed - $message"); 
				} else {
					// okay to skip DROP INDEX errors since it is expected
					// that we may be attempting to drop indexes that do not exist
				}
			}
		}

		return $completed;
	}

	/**
	 * Reduce/simplify given SQL column type enabled it to be used for comparisons
	 *
	 * @param string $type
	 * @return string
	 *
	 */
	protected function reduceColType($type) {
		$type = strtoupper($type);
		$nums = array('INT', 'FLOAT', 'DOUBLE');
		foreach($nums as $num) {
			$isNum = strpos($type, $num) !== false;
			if($isNum) break;
		}
		if($isNum) {
			// strip details about some number types
			$type = preg_replace('/\(\d+\)/', '', $type);
		}
		while(strpos($type, '  ')) $type = str_replace('  ', ' ', $type);
		return trim($type);
	}

	public function ___log($str = '', array $options = array()) {
		$options['name'] = 'combo-schema';
		return parent::___log($str, $options);
	}
}