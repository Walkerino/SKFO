<?php namespace ProcessWire;

/**
 * ProFields: Custom Field WireData
 * 
 * THIS IS PART OF A COMMERCIAL MODULE: DO NOT DISTRIBUTE.
 * This file should NOT be uploaded to GitHub or available for download on any public site.
 *
 * Copyright 2024 by Ryan Cramer Design, LLC
 * ryan@processwire.com
 *
 * 
 */
class CustomWireData extends WireData {

	protected $page = null;
	protected $field = null;
	protected $labels = [];
	protected $htmlProperties = [];
	
	public function get($key) {
		$value = parent::get($key);
		if($value !== '' && $this->useFormatted()) {
			$value = $this->formatValue($value, $key, true);
		}
		return $value;
	}

	public function getIterator(): \ArrayObject {
		$data = [];
		foreach(array_keys($this->data) as $key) {
			$data[$key] = $this->get($key);
		}
		return new \ArrayObject($data);
	}

	/**
	 * Format a value
	 * 
	 * @param mixed $value
	 * @param string $property
	 * @param bool $always
	 * @return array|mixed|string
	 * 
	 */
	protected function formatValue($value, string $property = '', bool $always = false) {
		
		if(!$always && !$this->useFormatted()) return $value;
		if($value === null) return '';
		
		if(is_string($value)) {
			$value = $this->formatValueString($value, $property);
		} else if(is_object($value) && WireArray::iterable($value)) {
			$value = clone $value;
			foreach($value as $k => $v) {
				$value[$k] = $this->formatValue($v, $property, true);
			}
		} else if(is_array($value)) {
			foreach($value as $k => $v) {
				$value[$k] = $this->formatValue($v, $property, true);
			}
		}
		
		return $value;
	}

	/**
	 * Format a string value 
	 * 
	 * @param string $value
	 * @param string $property
	 * @return string
	 * 
	 */
	protected function formatValueString(string $value, string $property): string {
		
		$length = strlen($value);
		if($length === 0) return $value;
		
		if($property && strpos($value, '<') !== false) {
			if(isset($this->htmlProperties[$property])) {
				$isHtml = $this->htmlProperties[$property];
				if($isHtml) return $value;
			} else {
				$f = $this->getField()->defs()->getPropertyInputfield($property);
				$this->htmlProperties[$property] = true;
				if($f && (int) $f->get('contentType') >= FieldtypeTextarea::contentTypeHTML) {
					return $value;
				} else if(wireInstanceOf($f, ['InputfieldTinyMCE', 'InputfieldCKEditor'])) {
					return $value;
				}
			}
			$this->htmlProperties[$property] = false;
		}

		if(($length === 10 || $length === 19) && ctype_digit(substr($value, 0, 4))) {
			// potentially '2024-01-01' or '2024-01-01 11:11:11'
			$f = $this->getField()->defs()->getPropertyInputfield($property);
			$format = $f->get('dateOutputFormat|dateInputFormat');
			if($format) return wireDate($format, $value);
		}

		return $this->wire()->sanitizer->entities($value);
	}

	/**
	 * Should we be returning a formatted value?
	 * 
	 * @return bool
	 * 
	 */
	protected function useFormatted(): bool {
		$field = $this->getField();
		if(!$field || !$field->useEntityEncode) return false;
		$page = $this->getPage();
		return $page && $page->id && $page->of(); 
	}

	/**
	 * @param Page $page
	 * 
	 */
	public function setPage(Page $page) {
		$this->page = $page;
	}

	/**
	 * @return Page|null
	 * 
	 */
	public function getPage() {
		return $this->page;
	}
	
	/**
	 * @param CustomField $field
	 * 
	 */
	public function setField(Field $field) {
		$this->field = $field;
	}

	/**
	 * @return CustomField|null
	 * 
	 */
	public function getField() {
		return $this->field;
	}

	/**
	 * Get labels for all properties/subfields indexed by name
	 * 
	 * @return array
	 * 
	 */
	protected function labels(): array {
		if(!empty($this->labels)) return $this->labels;
		$field = $this->getField();
		if(!$field) return $this->labels;
		$fieldName = $field->name;
		$inputfields = $field->defs()->getFlatInputfields();
		foreach($inputfields as $f) {
			/** @var Inputfield $f */
			list(,$property) = explode($fieldName . '_', $f->name);
			$this->labels[$property] = $f->label;
		}
		return $this->labels;
	}
	
	/**
	 * Get label for given property/subfield by name
	 * 
	 * @param string $property
	 * @param string|null $optionValue Specify only if getting label for a select option
	 * @return string
	 * 
	 */
	public function label(string $property, string $optionValue = null): string {
		if(!empty($optionValue)) return $this->optionLabel($property, $optionValue);
		if(empty($this->labels)) $this->labels();
		if(empty($this->labels[$property])) return '';
		$label = $this->labels[$property];
		if(empty($label)) return '';
		return $this->formatValue($label);
	}

	/**
	 * Get option labels for any select field
	 * 
	 * @param string $property
	 * @return array
	 * 
	 */
	public function optionLabels(string $property): array {
		$field = $this->getField();
		if(!$field) return [];
		$f = $field->defs()->getPropertyInputfield($property);
		$options = [];
		if($f instanceof InputfieldSelect) {
			$options = $f->getOptions();
		} else if($f instanceof InputfieldHasSelectableOptions) {
			$options = $f->get('tagsList|options');
		}
		if(!is_array($options)) return [];
		if(!$this->useFormatted()) return $options;
		foreach($options as $key => $value) {
			$options[$key] = $this->formatValue($value, true);
		}
		return $options;
	}

	/**
	 * Get option label for given select field and value
	 * 
	 * @param string $property
	 * @param string|int $value
	 * @return string
	 * 
	 */
	public function optionLabel(string $property, $value): string {
		$options = $this->optionLabels($property); 
		if(empty($options)) return '';
		return $options[$value] ?? '';
	}
	
}