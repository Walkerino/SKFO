<?php namespace ProcessWire;

/**
 * Single and multi-selection fields
 *
 */

/** @var Page|NullPage $page Page instance is provided when applicable */
/** @var Field|null $field Field instance is provided when applicable */

return [

	// single-select color using radios
	'color' => [
		'type' => 'radios',
		'label' => 'Color', 
		'description' => 'Select your favorite color',
		'columnWidth' => 50,
		'options' => [
			'r' => 'Red',
			'g' => 'Green',
			'b' => 'Blue'
		],
		"value" => "g",
		// 'optionColumns' => 1, // makes them display side-by-side
		// 'optionWidth' => '200px', // display in columns of this width
	],
	
	// multi-select colors using checkboxes
	'colors' => [
		'type' => 'checkboxes',
		'label' => 'Colors',
		'description' => 'Select your favorite colors',
		'columnWidth' => 50, 
		'options' => [
			'r' => 'Red',
			'g' => 'Green',
			'b' => 'Blue'
		],
		'value' => [ 'r', 'b' ], 
		// 'optionColumns' => 1, // makes them display side-by-side
		// 'optionWidth' => '200px', // display in columns of this width
	],
	
	// single select for country
	'country' => [
		'type' => 'select',
		'label' => 'Select a country',
		'options' => include(
			wire()->config->paths('FieldtypeCustom') . 'examples/countries.php'
		),
		'value' => 'United States', 
	],

	// multiple select of countries using asmSelect
	'countries' => [
		'type' => 'asmSelect',
		'label' => 'Select several countries',
		'options' => include(
			wire()->config->paths('FieldtypeCustom') . 'examples/countries.php'
		)
	],

	// yes/no toggle	
	// https://processwire.com/api/ref/inputfield-toggle/
	'good_day' => [
		'type' => 'toggle',
		'label' => 'Is today a good day?',
		'columnWidth' => 50,
	],

	// simple checkbox input
	'agree' => [
		'type' => 'checkbox',
		'label' => 'Do you agree with these terms?',
		'label2' => 'Yes, I agree',
		'columnWidth' => 50, 
	],
	
	// text tags input with predefined and user-entered options 
	// https://processwire.com/api/ref/inputfield-text-tags/
	'dev_tools' => [
		'type' => 'textTags',
		'label' => 'Developer tools (text-tags example)',
		'description' => 'Select tags or enter your own.', 
		'allowUserTags' => true, 
		'tagsList' => [
			'php' => 'PHP',
			'py' => 'Python',
			'java' => 'Java',
			'js' => 'Javascript',
			'css' => 'CSS', 
			'scss' => 'SCSS/SASS',
			'less' => 'LESS',
			'md' => 'Markdown',
		],
	],
];