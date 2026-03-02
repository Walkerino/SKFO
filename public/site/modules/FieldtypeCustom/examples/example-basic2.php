<?php namespace ProcessWire;

/**
 * Defining Inputfields directly
 * 
 * This is the same as the "basic" example, but defines the fields directly
 * as Inputfields rather than in an array. 
 * 
 * In this case we create each Inputfield manually which gives us the benefit
 * of our IDE (like PhpStorm) knowing the types and thus being able to provide
 * contextual help and hints as we type. (If supported by your IDE)
 * 
 * Most will likely still prefer to define them as an array, but this 
 * alternate definition option is available for those that find it useful.
 * 
 */

/** @var Page|NullPage|null $page Page instance is provided when applicable */
/** @var Field|null $field Field instance is provided when applicable */

$form = new InputfieldWrapper();

$f = $form->InputfieldText;
$f->name = 'first_name';
$f->label = 'First name';
$f->required = true;
$f->columnWidth = 50;
$form->add($f);

$f = $form->InputfieldText;
$f->name = 'last_name';
$f->label = 'Last name';
$f->required = true;
$f->columnWidth = 50;
$form->add($f);

$f = $form->InputfieldEmail;
$f->name = 'email';
$f->label = 'Email address';
$f->required = true;
$f->placeholder = 'person@company.com';
$form->add($f);

$fieldset = $form->InputfieldFieldset;
$fieldset->name = 'address';
$fieldset->label = 'Mailing address';
$form->add($fieldset);

$f = $form->InputfieldText; 
$f->name = 'address_street';
$f->label = 'Street';
$fieldset->add($f); // note: adding to $fieldset rather than $form

$f = $form->InputfieldText;
$f->name = 'address_city';
$f->label = 'City';
$f->columnWidth = 50;
$fieldset->add($f);

$f = $form->InputfieldText;
$f->name = 'address_state';
$f->label = 'State/province';
$f->columnWidth = 25;
$fieldset->add($f);

$f = $form->InputfieldText;
$f->name = 'address_zip';
$f->label = 'Zip/post code';
$f->columnWidth = 25;
$fieldset->add($f);

$f = $form->InputfieldSelect; 
$f->name = 'address_country';
$f->label = 'Country';
$f->options = include(wire()->config->paths('FieldtypeCustom') . 'examples/countries.php');
$fieldset->add($f);

return $form;