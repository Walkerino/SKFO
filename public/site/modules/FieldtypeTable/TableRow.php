<?php namespace ProcessWire;

/**
 * ProcessWire Table Fieldtype: Row
 *
 * Part of the ProFields package
 * Please do not distribute. 
 * 
 * Copyright 2024 by Ryan Cramer
 *
 * https://processwire.com/store/pro-fields/
 * 
 * @property int|null $id
 * @property int|null $rowId Same as $id but works whether value is formatted or not
 * 
 * @todo add a save() method for saving individual rows
 *
 */

class TableRow extends WireData {

	/**
	 * @var Field|null
	 * 
	 */
	protected $field = null;

	/**
	 * Table row ID when protected behind id() function
	 * 
	 * @var int 
	 * 
	 */
	protected $rowId = 0;

	/**
	 * Construct a new TableRow
	 * 
	 * @param Field $field
	 * @param array $cols
	 * 
	 */
	public function __construct(Field $field, array $cols = array()) {

		$this->field = $field; 
		$field->wire($this);
		parent::__construct();

		// establish blank placeholder values
		for($n = 1; $n <= $field->get('numCols'); $n++) {
			$name = $field->{"col{$n}name"}; 	
			$this->set($name, '');  
		}

		// populate actual values, when available
		foreach($cols as $key => $value) {
			$this->set($key, $value); 
		}
	}

	/**
	 * Set a value to the TableRow
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return $this|WireData
	 * 
	 */
	public function set($key, $value) {
		if($key == 'data') $key = 'id';
		if($key === 'id') {
			$value = (int) $value;
			$this->rowId = $value;
		}
		return parent::set($key, $value); 
	}
	
	/**
	 * Set a column value with automatic type conversion/checking
	 *
	 * @param string $name Column name
	 * @param mixed $value Column value
	 * @return $this
	 *
	 */
	public function setSafe($name, $value) {
		/** @var FieldtypeTable $type */
		$type = $this->field->type;
		$col = $type->getColumnByName($this->field, $name);
		if(!empty($col)) {
			$value = $type->_sanitizeValue($col, $value);
			$this->set($name, $value);
		}
		return $this;
	}

	/**
	 * Get property
	 * 
	 * @param object|string $key
	 * @return mixed|null
	 * 
	 */
	public function get($key) {
		if($key === 'data') $key = 'id';
		$value = parent::get($key);
		if($key === 'rowId' && !$value) return $this->rowId();
		return $value;
	}

	/**
	 * Get or set id for formatted TableRow
	 * 
	 * This is the same as the $id property except that it is available 
	 * even for formatted rows.
	 * 
	 * @param int $id
	 * @return int
	 * 
	 */
	public function rowId($id = null) {
		if($id) $this->rowId = (int) $id;
		if($this->rowId) return $this->rowId; 
		return (int) parent::get('id');
	}

	/**
	 * @param Field $field
	 * 
	 */
	public function setField(Field $field) {
		$this->field = $field;
	}

	/**
	 * @return Field
	 * 
	 */
	public function getField() {
		return $this->field;
	}


}