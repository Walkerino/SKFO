<?php namespace ProcessWire;

/**
 * TinyMCE fields
 * 
 * Because there are a lot of potential settings with a TinyMCE field
 * a simple way to add them is to using the `settingsField` option where
 * you can specify the name of an existing TinyMCE field to inherit all
 * of the settings from. 
 * 
 * Note: this example will only work if you have InputfieldTinyMCE
 * installed.
 *
 */

/** @var Page|NullPage $page Page instance is provided when applicable */
/** @var Field|null $field Field instance is provided when applicable */

// if TinyMCE not installed return an error (you can remove this)
if(!wire()->modules->isInstalled('InputfieldTinyMCE')) {
	return [
		'tinymce' => [
			'type' => 'markup',
			'label' => 'TinyMCE not installed',
			'value' => '<p>InputfieldTinyMCE is needed for this example.</p>'
		]
	];
}

$a = [
	'terms_and_conditions' => [
		'type' => 'TinyMCE',
		'label' => 'Terms and conditions',
		'inlineMode' => 1, // 0=regular, 1=inline
		'columnWidth' => 50, 
		// 'settingsField' => 'body' // makes it inherit settings another TinyMCE field: body
	],
	'privacy_policy' => [
		'type' => 'TinyMCE',
		'label' => 'Privacy policy',
		'inlineMode' => 1, // 0=regular, 1=inline
		'columnWidth' => 50,
	],
];

// if we can find another TinyMCE field, add an example that inherits the
// settings from that TinyMCE field
foreach(wire()->fields->findByType('FieldtypeTextarea') as $field) {
	if($field->get('inputfieldClass') !== 'InputfieldTinyMCE') continue;
	$a['copyright_statement'] = [
		'type' => 'TinyMCE',
		'label' => 'Copyright statement',
		'description' => "Settings inherited from field: $field->name",
		'settingsField' => $field->name
	];
	break;
}

return $a;