<?php namespace ProcessWire;

/**
 * ProFields
 *
 * Demonstrates any ProFields that can be used as as Inputfields (without Fieldtype). 
 *
 */

/** @var Page|NullPage $page Page instance is provided when applicable */
/** @var Field|null $field Field instance is provided when applicable */


$a = [];

if(wire()->modules->isInstalled('InputfieldMultiplier')) {
	// see the top of the InputfieldMultiplier.module file for settings
	$a['test_multiplier'] = [
		'type' => 'multiplier',
		'label' => 'Test of ProFields Multiplier',
		'description' => 'Add up to 6 items',
		'qtyMin' => 3,
		'qtyMax' => 6,
		'trashable' => true,
		'sortable' => false,
		'inputfieldClass' => 'text'
	];
} else {
	$a['test_multiplier'] = [
		'type' => 'markup', 
		'label' => 'Multiplier (not installed)', 
		'value' => '<p>ProFields Multiplier is not installed.</p>',
		'collapsed' => true, 
	];
}

if(wire()->modules->isInstalled('InputfieldDateRange')) {
	// see the top of the InputfieldDateRange.module.php file for settings
	$a['test_date_range'] = [
		'type' => 'dateRange',
		'label' => 'Test of ProFields Date Range',
		'inline' => true, 
		'format' => 'YYYY-MM-DD', 
		'outputStyle' => 'admin',
	];
} else {
	$a['test_date_range'] = [
		'type' => 'markup',
		'label' => 'Date Range (not installed)',
		'value' => '<p>ProFields DateRange is not installed.</p>',
		'collapsed' => true,
	];
}


return $a;