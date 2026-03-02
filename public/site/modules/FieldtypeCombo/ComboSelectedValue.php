<?php namespace ProcessWire;

/**
 * ProcessWire ProFields Combo Selected Value
 *
 * Part of the ProFields package.
 * Please do not distribute.
 *
 * Copyright (C) 2023 by Ryan Cramer
 *
 * https://processwire.com
 *
 * @property string $value
 * @property string $label
 * @property string $title Alias of label
 *
 */

class ComboSelectedValue extends WireData {
	public function __construct() {
		parent::__construct();
		$this->setArray(array(
			'value' => '',
			'label' => '',
		));
	}
	
	public function get($key) {
		if($key === 'title') $key = 'label';
		return parent::get($key);
	}
	
	public function getValue() {
		return $this->value;
	}
	
	public function getLabel() {
		return $this->label;
	}
		
	public function __toString() {
		return (string) $this->getLabel();
	}
}