<?php namespace ProcessWire;

/**
 * ProcessWire ProFields
 * 
 * This is a commercial module, part of the ProcessWire ProFields package
 * DO NOT DISTRIBUTE
 *
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 * 
 */

/**
 * Perform a translation (internal use)
 *
 * @param string $text Original text
 * @param string $name Optionally provide a name for the translation
 * @param string $label Optionally provide a label to identify the text in the page editor
 * @param array $options Array of options.
 * @return string
 *
 */
function __functional($text, $name = '', $label = '', $options = array()) {
	/** @var FieldtypeFunctional|null $fieldtype */
	static $fieldtype = null;
	$files = TemplateFile::getRenderStack();
	$defaults = array(
		'type' => isset($options['type']) ? $options['type'] : '',
		'name' => is_string($name) ? $name : '',
		'label' => is_string($label) ? $label : '',
		'file' => end($files),
	);
	if(is_array($text)) {
		$options = $text;
		$text = isset($options['text']) ? $options['text'] : '';
	} else if(is_array($name)) {
		$options = $name;
	} else if(is_array($label)) {
		$options = $label;
	}
	$options = array_merge($defaults, $options);
	if($fieldtype === null) $fieldtype = wire()->fieldtypes->get('FieldtypeFunctional');
	return ($fieldtype ? $fieldtype->translate($text, $options) : $text);
}

if(!function_exists("\\ProcessWire\\__text")):

	/**
	 * Perform a translation for text phrase or word (single-line)
	 * 
	 * @param string $text Original text
	 * @param string $name Optionally provide a name for the translation
	 * @param string $label Optionally provide a label to identify the text in the page editor
	 * @return string
	 * 
	 */
	function __text($text, $name = '', $label = '') {
		return __functional($text, $name, $label, array('type' => ''));
	}
	
	/*
	function __plural($text, $textPlural, $count, $name = '') {
		$options = array('type' => 'n', 'textPlural' => $textPlural, 'count' => (int) $count);
		if($name) $options['name'] = $name;
		return wire('fieldtypes')->get('FieldtypeFunctional')->translate($text, $options);
	}
	*/
endif;

if(!function_exists("\\ProcessWire\\__textarea")):

	/**
	 * Perform a translation for textarea (multi-line text)
	 *
	 * @param string $text Original text
	 * @param string $name Optionally provide a name for the translation
	 * @param string $label Optionally provide a label to identify the text in the page editor
	 * @return string
	 *
	 */
	function __textarea($text, $name = '', $label = '') {
		return __functional($text, $name, $label, array('type' => 'textarea'));
	}
	
endif;

if(!function_exists("\\ProcessWire\\__richtext")):

	/**
	 * Perform a translation for rich text (CKEditor)
	 *
	 * @param string $text Original text
	 * @param string $name Optionally provide a name for the translation
	 * @param string $label Optionally provide a label to identify the text in the page editor
	 * @return string
	 *
	 */
	function __richtext($text, $name = '', $label = '') {
		return __functional($text, $name, $label, array('type' => 'richtext'));
	}
	
endif;

if(!function_exists("\\ProcessWire\\__fieldset")):

	/**
	 * Define a Fieldset
	 *
	 * @param string $names Names of fields you want in this fieldset
	 * @param string $name Name for the fieldset
	 * @param string $label Label for the fieldset
	 *
	 */
	function __fieldset($names, $name = '', $label = '') {
		// nothing to do, as these calls are for the parser only
	}

endif;	

if(!function_exists("\\ProcessWire\\__value")):
	
	/**
	 * Return a previously declared value with given name
	 *
	 * @param string $name
	 * @return string
	 *
	 */
	function __value($name) {
		return __functional('', $name);
	}
endif;
