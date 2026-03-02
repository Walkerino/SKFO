<?php namespace ProcessWire;

/**
 * ProFields: Custom Field
 * 
 * THIS IS PART OF A COMMERCIAL MODULE: DO NOT DISTRIBUTE.
 * This file should NOT be uploaded to GitHub or available for download on any public site.
 *
 * Copyright 2024 by Ryan Cramer Design, LLC
 * ryan@processwire.com
 * 
 * @property FieldtypeCustom $type
 * @property bool|int $useEntityEncode
 * @property string $dataType Last requested data type
 * @property string $dataTypeNow Last recorded data type from the DB
 * 
 */
class CustomField extends Field {

	/**
	 * @var CustomFieldDefs|null 
	 * 
	 */
	protected $defs = null;

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		$this->set('useEntityEncode', 1);
		$this->set('dataType', FieldtypeCustom::defaultDataColType);
		$this->set('dataTypeNow', FieldtypeCustom::defaultDataColType);
		parent::__construct();
	}

	/**
	 * @return CustomFieldDefs
	 * 
	 */
	public function defs() {
		if($this->defs) return $this->defs;
		$inputfield = $this->type->getInputfield(new Page(), $this);
		$inputfield->attr('name', $this->name);
		$this->defs = $inputfield->defs();
		return $this->defs;
	}
}