<?php namespace ProcessWire;

/**
 * Kitchen sink: all examples combined into one
 * 
 * Copy this to /site/templates/custom-fields/your_field_name.php 
 * if you want to test out all examples at once. 
 * 
 */

$examples = [
	'basic' => 'Basic text inputs and a fieldset',
	'selects' => 'Single and multi-selection inputs',
	'tinymce' => 'Rich text inputs',
	'pagerefs' => 'Page reference inputs',
	'dates' => 'Date and time inputs',
	'dependencies' => 'Dependencies',
	'languages' => 'Multi-language inputs',
];

$path = wire()->config->paths('FieldtypeCustom') . 'examples/';
$fieldsets = [];

foreach($examples as $name => $label) {
	$fieldsets[$name] = [
		'type' => 'fieldset', 
		'label' => $label,
		'themeOffset' => 1,
		'children' => include($path . "example-$name.php"),
	];
}

return $fieldsets;