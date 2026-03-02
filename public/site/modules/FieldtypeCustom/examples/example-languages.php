<?php namespace ProcessWire;

/**
 * Multi-language
 *
 * You can make most text or textarea fields multi-language by 
 * setting the `useLanguages` property to `true`. This requires
 * that you have multi-language fields support installed in PW.
 * 
 * The other aspect of multi-language is making your field and/or
 * option labels translatable by wrapping them in `__('text')` calls.
 *
 */

/** @var Page|NullPage $page Page instance is provided when applicable */
/** @var Field|null $field Field instance is provided when applicable */
/** @var Languages $languages */

if($languages) return [
	// multi-language text field
	'event_title' => [
		'type' => 'text',
		'label' => __('Event title'), // __('text') to make labels translatable
		'useLanguages' => true
	],

	// select field with translatable options	
	'event_type' => [
		'type' => 'select', 
		'label' => __('Event type'), 
		'options' => [
			'awards' => __('Awards ceremony'),
			'board' => __('Board meeting'),
			'convention' => __('Convention'),
			'holiday' => __('Holiday party'), 
			'retreat' => __('Company retreat'),
			'product' => __('Product launch'),
			'team' => __('Team building'),
			'trade' => __('Trade show'), 
		],
		'columnWidth' => 50, 
	],
	
	// date field (not multi-language)
	'event_date' => [
		'type' => 'datetime',
		'label' => __('Date'),
		'datepicker' => 1,
		'inputType' => 'select',
		'dateSelectFormat' => 'yMd',
		'yearFrom' => 2024, 
		'yearTo' => date('Y') + 5, 
		'columnWidth' => 50,
	],

	// another multi-language text field
	'event_location' => [
		'type' => 'text',
		'label' => __('Location'),
		'useLanguages' => true,
	],
	
	// multi-language textarea field
	'event_description' => [
		'type' => 'textarea', // or use 'TinyMCE' for rich text
		'label' => __('Description'),
		'useLanguages' => true, 
		'rows' => 5, 
	],
];

// return a visible error if multi-language support not available (you can remove this)
return [
	'lang_error' => [
		'type' => 'markup',
		'label' => 'Multi language support is not installed',
		'value' => 'Cannot show this example since it requires multi-language',
	]
];