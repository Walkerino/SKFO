<?php namespace ProcessWire;

/**
 * Using dependencies 
 * 
 * This can include: 
 * - Using `showIf` (or `requiredIf`) conditions.
 * - Defining different fields depending on runtime conditions
 *   such as the currently edited $page. 
 *
 */

/** @var Page|NullPage $page Page instance is provided when applicable */
/** @var Field|null $field Field instance is provided when applicable */

$a = [
	'feature_list' => [
		'type' => 'checkboxes',
		'label' => 'What special features would you like to show?',
		'options' => [
			'blog' => 'Latest blog post', 
			'events' => 'Upcoming events',
			'alert' => 'Alert headline', 
			'email' => 'Subscribe to email list',
		],
	],
	'num_blog' => [
		'type' => 'integer',
		'label' => 'How many blog posts to show?',
		'showIf' => 'feature_list=blog',
	],
	'num_events' => [
		'type' => 'integer',
		'label' => 'How many upcoming events to show?',
		'showIf' => 'feature_list=events',
	],
	'alert_text' => [
		'type' => 'text',
		'label' => 'Text for the alert headline',
		'showIf' => 'feature_list=alert',
	],
	'email_list' => [
		'type' => 'radios',
		'label' => 'Which email list?',
		'showIf' => 'feature_list=email',
		'options' => [
			'fridays' => 'Five point Fridays',
			'monthly' => 'Monthly top 10',
			'weekly' => 'Weekly newsletter',
		],
	],
];

// add an option and another field when editing page using template 'home'
if($page->template == 'home') {
	$a['feature_list']['options']['hello'] = 'Hello homepage';
	$a['hello_home'] = [
		'type' => 'markup', 
		'label' => 'Hello', 
		'value' => '<p>This is only shown on the homepage.</p>', 
		'showIf' => 'feature_list=hello', 
	];
}

return $a;