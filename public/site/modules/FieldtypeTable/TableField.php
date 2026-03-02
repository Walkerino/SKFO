<?php namespace ProcessWire;

/**
 * ProcessWire Table Field
 * 
 * Field that lets you define a database table of custom fields.
 *
 * Part of the ProFields package
 * Please do not distribute.
 *
 * Copyright 2024 by Ryan Cramer
 *
 * https://processwire.com/store/pro-fields/
 * 
 * @since 0.3.0 for this TableField class
 * 
 * @property FieldtypeTable $type
 *
 */
class TableField extends Field {

	/**
	 * Cache for getTableClass() method
	 * 
	 * @var array 
	 * 
	 */
	protected $tableClasses = [];

	/**
	 * Get class to use for TableRow instances
	 * 
	 * @return string Returned class includes namespace
	 * 
	 */
	public function getRowClass() {
		return $this->getTableClass('TableRow');
	}

	/**
	 * Get class to use for TableRows WireArray instances
	 * 
	 * @return string Returned clas includes namespace
	 * 
	 */
	public function getArrayClass() {
		return $this->getTableClass('TableRows');
	}

	/**
	 * Get optional field-specific substitute class for given class
	 * 
	 * @param string $class
	 * @return string
	 * 
	 */
	protected function getTableClass($class) {
		if(!isset($this->tableClasses[$class])) {
			$name = ucfirst($this->name);
			$a = "ProcessWire\\$class";
			$b = "ProcessWire\\$name$class";
			$c = wireClassExists($b) && wireInstanceOf($b, $a) ? $b : $a;
			$this->tableClasses[$class] = $c;
		}
		return $this->tableClasses[$class];
	}
}