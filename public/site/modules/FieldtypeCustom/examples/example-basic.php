<?php namespace ProcessWire;

/**
 * Defining Inputfields as PHP array
 * 
 * This is essentially the same as defining as JSON except that a PHP array
 * is used instead. Here are a few benefits of using PHP to define your form:
 * 
 * 1. PHP arrays can be a little more forgiving than JSON since
 *    you have your choice of quotes and it doesn't complain if you leave 
 *    a comma after the last item in an array. 
 * 
 * 2. Since we are in PHP, we can optionally use logic, such as loading 
 *    the list of countries below to populate a "Country" field at the 
 *    end of this form. Or you could conditionally add fields depending
 *    on the current $page, for example. 
 * 
 * 3. You can optionally make field labels translatable by surrounding 
 *    them in a `__('Label text')` call. 
 * 
 * See also example-basic2.php which shows an alternate way to create 
 * your Inputfields and gain the benefit of IDE type hinting. 
 * 
 * TYPES
 * =====
 * Every field definition must have a 'type' property containing one 
 * of the following values (A-Z):
 *
 * - AsmSelect
 * - Checkbox
 * - Checkboxes
 * - Datetime
 * - Email
 * - Fieldset
 * - Float
 * - Hidden
 * - Icon
 * - Integer
 * - Markup
 * - Page
 * - Radios
 * - Select
 * - SelectMultiple
 * - Text
 * - Textarea
 * - TextTags
 * - TinyMCE
 * - Toggle
 * - URL
 * 
 * You may also be able to use other Inputfield types by specifying
 * the Inputfield module name directly for the type (with or without the
 * prefix "Inputfield"). Only Inputfield types that do not require a 
 * corresponding Fieldtype may work. 
 * 
 * Configurable properties for any of the types can be found in phpdoc 
 * at the top of each Inputfield module file: 
 * 
 * https://github.com/processwire/processwire/tree/master/wire/modules/Inputfield
 * 
 * Your definition file(s) receive all API variables, but the one most
 * likely to be useful is the $page API variable, which represents the
 * page currently being edited. 
 * 
 * REQUIRED FIELDS
 * ===============
 * To make a field required, specify `"required" => true` in the definition
 * for that field. 
 * 
 * DEFAULT VALUES 
 * ==============
 * To specify a default value, enter `"value" => "your default value"` in
 * the definition for a field. Note that if the field is a multi-selection
 * type or uses an array value, you should specify an array as the default
 * value also, i.e. `"value" => [ "r", "b", "g" ]`
 * 
 */

/** @var Page|null $page Page instance is provided when applicable */
/** @var Field|null $field Field instance is provided when applicable */

return [
	'first_name' => [
		'type' => 'text',
		'label' => 'First name',
		'required' => true,
		'columnWidth' => 50,
	],
	'last_name' => [
		'type' => 'text',
		'label' => 'Last name',
		'required' => true,
		'columnWidth' => 50
	],
	'email' => [
		'type' => 'email',
		'label' => 'Email address',
		'icon' => 'envelope-o',
		'placeholder' => 'person@company.com'
	],
	'bio' => [
		'type' => 'textarea', 
		'label' => 'Biography', 
		'rows' => 5, 
		'value' => 'Enter biography here (this is an example of a default value)', 
	],
	'address' => [
		'type' => 'fieldset',
		'label' => 'Address (fieldset example)',
		'icon' => 'address-card-o',
		'children' => [
			// note: Prefixing names with fieldset name ("address_") is desirable but not required
			'address_street' => [
				'type' => 'text', 
				'label' => 'Street'
			],
			'address_city' => [
				'type' => 'text',
				'label' => 'City',
				'columnWidth' => 50
			],
			'address_state' => [
				'type' => 'text',
				'label' => 'State/province',
				'columnWidth' => 25
			],
			'address_zip' => [
				'type' => 'text',
				'label' => 'Zip/post code',
				'columnWidth' => 25
			],
			'address_country' => [
				'type' => 'select',
				'label' => 'Country',
				'options' => include(
					wire()->config->paths('FieldtypeCustom') . 'examples/countries.php'
				),
				'value' => 'United States', // example of default value
			],	
		],
	],
];