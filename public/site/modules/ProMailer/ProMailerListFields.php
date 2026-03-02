<?php namespace ProcessWire;

/**
 * ProcessWire ProMailer List Fields
 * 
 * Custom fields for lists, translates between definition strings and arrays. 
 * 
 * Copyright 2023 by Ryan Cramer
 *
 *
 */
class ProMailerListFields extends Wire {

	/**
	 * Template for list fields
	 * 
	 * @var array
	 * 
	 */
	protected $fieldArrayTemplate = array(
		'name' => null,
		'type' => null,
		'label' => null,
		'input' => null,
		'value' => null,
		'class' => null, 
		'options' => null,
		'required' => null,
		'internal' => null,
		'placeholder' => null,
	);
	
	/**
	 * Given either string or array definition of custom fields, return custom fields definition array
	 *
	 * @param string|array $value
	 * @return array
	 *
	 */
	public function fieldsArray($value) {

		$a = array();

		if(is_string($value)) {
			if(empty($value)) return $a;
			return $this->fieldsStringToArray($value);
		}

		if(!is_array($value)) return $a;

		foreach($value as $k => $f) {
			if(is_array($f)) {
				// already in correct format
				$a = $value;
				break;
			}
			if(is_string($k) && strpos($f, ':') === false) {
				// legacy format where keys were field names and values were strings, convert to 1 line
				$f = "$k:$f";
			}
			$f = $this->fieldLineToArray($f);
			if(empty($f)) continue;
			$a[$f['name']] = $f;
		}

		return $a;
	}

	/**
	 * Convert multi-line fields string to an array of custom field definitions
	 * 
	 * @param string $s
	 * @return array
	 * 
	 */
	public function fieldsStringToArray($s) {

		$a = array();
		if(!strlen($s)) return $a;

		foreach(explode("\n", $s) as $line) {
			if(empty($line)) continue;
			$f = $this->fieldLineToArray($line);
			if(empty($f)) continue;
			$a[$f['name']] = $f;
		}

		return $a;
	}

	/**
	 * Convert line one (string) defining a custom field to an array
	 * 
	 * @param string $line
	 * @return array Returns populated array on success, empty array on error
	 * 
	 */
	public function fieldLineToArray($line) {

		$sanitizer = $this->wire()->sanitizer;

		if(!preg_match('/^\s*([-_a-zA-Z0-9\*!]+)\s*[=:]\s*([_a-zA-Z0-9\*!]+)(.*)$/', $line, $matches)) {
			// invalid field definition
			$this->error("Invalid custom field definition: $line");
			return array();
		}

		$fieldName = $matches[1];
		$fieldType = $matches[2];
		$remainder = trim($matches[3]);
		$fieldOptions = array();
		$required = false;
		$internal = false;

		// check if field is required
		if(strpos($fieldName, '*') !== false || strpos($fieldType, '*') !== false) {
			$required = true;
			$fieldName = trim($fieldName, '*');
			$fieldType = trim($fieldType, '*');
		}

		// check if field is internal
		if(strpos($fieldName, '!') !== false || strpos($fieldType, '!') !== false) {
			$internal = true;
			$fieldName = trim($fieldName, '!');
			$fieldType = trim($fieldType, '!');
		}

		$fieldName = $sanitizer->fieldName($fieldName);
		$fieldType = $sanitizer->fieldName($fieldType);

		// check if sanitizer method exists
		if(!method_exists($sanitizer, $fieldType)) {
			$unknown = true;
			if(method_exists($sanitizer, 'methodExists')) {
				if($sanitizer->methodExists($fieldType)) $unknown = false;
			}
			if($unknown) {
				// try calling it, just in case it is a hook
				try {
					$sanitizer->$fieldType($fieldName);
					$unknown = false;
				} catch(\Exception $e) {
					// truly unknown
				}
			}
			if($unknown) {
				$this->warning("Unknown sanitizer method '$fieldType', changed to 'text'");
				$fieldType = 'text';
			}
		}

		// check for valid options
		if(strpos($remainder, '[') === 0 && strpos($remainder, ']')) {
			// Example 1: fieldName:fieldType[option1|option2|option3]
			// Example 2: fieldName:fieldtype[1=option1|2="option2"|3="option3"] // quotes optional
			$remainder = str_replace('\\]', 'PWPM_CLOSE_BRACKET', $remainder);
			list($options, $remainder) = explode(']', substr($remainder, 1));
			$options = trim(str_replace('PWPM_CLOSE_BRACKET', ']', $options));
			$remainder = trim(str_replace('PWPM_CLOSE_BRACKET', ']', $remainder));
			$options = explode('|', $options);

			foreach($options as $optionLabel) {
				$optionLabel = trim($optionLabel);
				$optionValue = $optionLabel;
				if(strpos($optionLabel, '=')) {
					list($optionValue, $optionLabel) = explode('=', $optionLabel, 2);
				}
				$optionValue = trim($optionValue, "\"'");
				$optionLabel = trim($optionLabel, "\"'");
				$fieldOptions[$optionValue] = $optionLabel;
			}
		}

		$customField = array_merge($this->fieldArrayTemplate, array(
			'name' => $fieldName,
			'type' => $fieldType,
			'options' => $fieldOptions,
			'required' => $required,
			'internal' => $internal,
		));

		$remainder = trim($remainder, "() \t\r\n"); 
		if(strlen($remainder)) {
			$remainder = " $remainder ";
			// find all: property="value", property='value', property=value (commas are optional)
			if(preg_match_all('/[ ,]*([-_a-zA-Z0-9]+)\s*[=:]\s*("[^"]*"|\'[^\']*\'|[^"\'\s]+)[ ,]*/', $remainder, $matches)) {
				foreach($matches[0] as $key => $fullMatch) {
					$propertyName = $matches[1][$key];
					$propertyValue = trim($matches[2][$key], '"\' ');
					$customField[$propertyName] = $propertyValue;
					$remainder = str_replace($fullMatch, '', $remainder);
				}
			}
			$remainder = trim($remainder, '"\' ');
			$remainder = trim($remainder);
			if(strlen($remainder)) {
				$this->warning("Unrecognized meta data in field '$fieldName': $remainder");
			}
		}

		return $customField;
	}

	/**
	 * Convert multiple fields definitions array to newline separated string
	 * 
	 * @param array $a
	 * @return string
	 * 
	 */
	public function fieldsArrayToString(array $a) {
		$lines = array();
		foreach($a as $f) {
			$lines[] = $this->fieldArrayToLine($f);
		}
		return implode("\n", $lines);
	}

	/**
	 * Convert multiple fields definitions array to array of strings
	 * 
	 * @param array $a
	 * @return array
	 * 
	 */
	public function fieldsArrayToStrings(array $a) {
		$lines = array();
		foreach($a as $f) {
			$lines[] = $this->fieldArrayToLine($f);
		}
		return $lines;
	}

	/**
	 * Convert one field array to a line (string)
	 * 
	 * @param array $f
	 * @return string
	 * 
	 */
	public function fieldArrayToLine(array $f) {

		$name = $f['name'];
		$type = $f['type'];
		if($f['required']) $name = "*$name";
		if($f['internal']) $name .= '!';

		$s = "$name:$type";

		if(!empty($f['options'])) {
			$oa = array();
			foreach($f['options'] as $optionValue => $optionLabel) {
				if($optionValue === $optionLabel) {
					$o = $optionValue;
				} else {
					$o = "$optionValue=$optionLabel";
				}
				if(strpos($o, ']') !== false) $o = str_replace(']', '\\]', $o);
				$oa[] = $o;
			}
			$s .= '[' . implode('|', $oa) . ']';
		}

		unset($f['name'], $f['type'], $f['required'], $f['internal'], $f['options']);

		$remainder = '';
		foreach($f as $name => $value) {
			if($value === null) continue;
			$value = trim($value, "'\"");
			if($value === null) continue;
			$quote = '"';
			if(strpos($value, '"')) $quote = "'";
			$remainder .= " $name=$quote$value$quote";
		}
		
		if(strlen($remainder)) {
			$s .= " (" . trim($remainder) . ")";
		}

		return $s;
	}

	/**
	 * Given a field array, return the corresponding HTML input element for it
	 * 
	 * @param array $f
	 * @param string $inputName Optionally use this for name attribute rather than $f[name]
	 * @param bool $admin Is this for rendering in the admin? (default=false)
	 * @return string
	 * 
	 */
	public function fieldArrayToInput(array $f, $inputName = '', $admin = false) {
		
		$adminTheme = $admin ? $this->wire()->adminTheme : false;
		$sanitizer = $this->wire()->sanitizer;

		$inputValue = $f['value'];
		$fieldType = $f['type'];
		$inputType = $f['input'];
		$inputItem = '';
		$extraLabel = empty($f['label']) ? '' : $f['label'];
		$extraAttrs = '';

		if(empty($inputName)) $inputName = $f['name'];
		if(empty($f['id']) && !empty($extraLabel)) $f['id'] = "promailer-input-$inputName";
		if(!empty($extraLabel) && $admin && empty($f['placeholder'])) $f['placeholder'] = $extraLabel;

		switch($fieldType) {
			case 'textarea':
				if(!$inputType) $inputType = 'textarea';
				$adminClass = 'textarea';
				break;
			case 'select':
			case 'option':
				if(!$inputType) $inputType = 'select';
				$adminClass = 'select';
				break;
			case 'checkboxes':
			case 'options':
				if(!$inputType) $inputType = 'checkboxes';
				$adminClass = 'input-checkbox';
				break;
			case 'checkbox':
			case 'bool':
			case 'bit':
				if(!$inputType) $inputType = 'checkbox';
				$adminClass = 'input-checkbox';
				$extraLabel = '';
				break;
			case 'float':
			case 'intUnsigned':
			case 'intSigned':
			case 'int':
				if(!$inputType) $inputType = 'number';
				$adminClass = 'input';
				break;
			default:	
				$adminClass = 'input';
		}

		$inputClass = array('promailer-field');
		if(!empty($f['class'])) $inputClass[] = $f['class'];
		$adminClass = $adminTheme ? $adminTheme->getClass($adminClass) : '';
		if($adminClass) $inputClass[] = $adminClass;
		$inputClass = trim(implode(' ', $inputClass));
		
		foreach($f as $k => $v) {
			if(array_key_exists($k, $this->fieldArrayTemplate) || $k === 'id') continue;
			$extraAttrs .= "$k='" . $sanitizer->entities($v) . "' ";
		}
		if(!empty($f['required']) && !$admin) $extraAttrs .= "required='required' ";
		if(!empty($f['placeholder'])) $extraAttrs .= " placeholder='" . $sanitizer->entities($f['placeholder']) . "' ";
		if($inputType != 'checkboxes') {
			if(!empty($f['id'])) $extraAttrs .= " id='" . $sanitizer->entities($f['id']) . "' ";
			if(empty($f['title']) && !empty($f['label'])) $extraAttrs .= " title='" . $sanitizer->entities($f['label']) . "' ";
		}
		$extraAttrs = trim($extraAttrs);
		
		if($inputType === 'select') {
			if(!$f['required']) $inputItem .= "<option></option>";
			foreach($f['options'] as $optionValue => $optionLabel) {
				$selected = $inputValue == $optionValue ? ' selected' : '';
				$optionValue = $sanitizer->entities($optionValue);
				$optionLabel = $sanitizer->entities($optionLabel);
				$inputItem .= "<option value='$optionValue'$selected>$optionLabel</option>";
			}
			$attrs = trim("class='$inputClass' name='$inputName' $extraAttrs");
			$inputItem = "<select $attrs>$inputItem</select>";

		} else if($inputType === 'checkboxes') {
			$checkboxes = array();
			if(!is_array($inputValue)) $inputValue = array();
			foreach($f['options'] as $value => $label) {
				$checked = in_array($value, $inputValue) ? "checked='checked'" : "";
				$name = $inputName . '[]';
				$label = $sanitizer->entities($label);
				$value = $sanitizer->entities($value);
				$attrs = trim("$checked class='$inputClass' type='checkbox' name='$name' value='$value' $extraAttrs");
				$checkboxes[] = "<label><input $attrs> $label</label>";
			}
			$inputItem = implode('<br />', $checkboxes);

		} else if($inputType === 'checkbox') {
			$checked = $inputValue ? "checked='checked'" : "";
			$attrs = trim("$checked class='$inputClass' type='checkbox' name='$inputName' value='1' $extraAttrs"); 
			$inputItem =
				"<input type='hidden' name='$inputName' value=''>" .
				"<input $attrs>";
		}
		
		if(empty($inputItem)) {
			// fallback to text input
			if(!$inputType) $inputType = 'text';
			$attrs = "type='$inputType' name='$inputName' class='$inputClass'";
			if(!empty($inputValue)) $attrs .= " value='" . $sanitizer->entities($inputValue) . "'";
			if(!empty($extraAttrs)) $attrs .= " $extraAttrs";
			$inputItem = "<input $attrs>";
		}
		
		if(!$admin && $extraLabel && !empty($f['id'])) {
			$inputItem = "<label for='$f[id]'>" . $sanitizer->entities($extraLabel) . " </label>$inputItem";
		}

		return $inputItem;
	}
}