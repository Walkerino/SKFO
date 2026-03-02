<?php namespace ProcessWire;

/**
 * Page reference fields
 * 
 * All Page reference Inputfields need to have at least the following
 * properties defined: 
 * 
 * - `derefAsPage` (int): Whether it single or multiple mode: 
 *
 *   - `0`: Multiple page (PageArray).
 *   - `1`: Single Page or NULL when empty.
 *   - `2`: Single Page or NullPage when empty.
 * 
 * - `inputfield` (string): Which input class to use, can be any of the following.
 *    You can specify the Inputfield class name or optionally omit the `Inputfield`
 *    prefix, like the options below: 
 * 
 *   - `select`: Select one via standard select.
 *   - `selectMultiple`: Select multiple via multiline select.
 *   - `radios`: Select one via radio buttons.
 *   - `checkboxes`: Select multiple via checkboxes. 
 *   - `asmSelect`: Select multiple/sortable via asmSelect.
 *   - `pageAutocomplete`: Autocomplete for single or multiple selection.
 *   - `pageListSelect`: Select one page from tree. 
 *   - `pageListSelectMultiple`: Select multiple pages from tree. 
 *   - `textTags`: Select one or multiple via text tags input. 
 * 
 * - `findPagesSelector` (string): Selector that matches which pages should be selectable.
 * 
 *    If you want to allow matching any pages you can leave this blank, or specify 
 *    just `has_parent!=2` to exclude admin pages from selection. 
 * 
 * If you need to allow new pages to be created from the Inputfield, you must specify: 
 *
 * - `addable` (bool): Must be true to allow adding new pages.
 * - `parent_id` (int): ID of parent page having children that are selectable or added.
 * - `template_id` (int): ID of template used by selectable pages and newly added pages.
 *
 * 
 */

/** @var Page|null $page Page instance is provided when applicable */
/** @var Field|null $field Field instance is provided when applicable */

return [
	// single page selection using regular select
	'section_page' => [
		'type' => 'page',
		'label' => 'Section page',
		'inputfield' => 'select',
		'findPagesSelector' => 'parent_id=1', // i.e. "template=product, parent=/products/
		'derefAsPage' => 2, // 0=PageArray, 1=Page or false, 2=Page or NullPage
		'labelFieldName' => 'title',
	],
	
	// single page selection using PageListSelect
	'featured_page' => [
		'type' => 'page',
		'label' => 'Featured page',
		'inputfield' => 'pageListSelect',
		'findPagesSelector' => 'has_parent!=2',
		'derefAsPage' => 2,
		'labelFieldName' => 'title',
	],
	
	// multiple page selection using PageListSelectMultiple
	'featured_pages' => [
		'type' => 'page', 
		'label' => 'Related pages',
		'inputfield' => 'pageListSelectMultiple',
		'findPagesSelector' => 'has_parent!=2',
		'derefAsPage' => 0,
		'labelFieldName' => 'title',
	],
	
	// single page selection using PageAutocomplete
	'related_page' => [
		'type' => 'page',
		'label' => 'Related page',
		'derefAsPage' => 2,
		'inputfield' => 'pageAutocomplete',
		'findPagesSelector' => 'has_parent!=2',
		'labelFieldName' => 'title',
	],
	
	// multiple page selection using PageAutocomplete
	'related_pages' => [
		'type' => 'page',
		'label' => 'Related pages',
		'derefAsPage' => 0,
		'inputfield' => 'pageAutocomplete',
		'findPagesSelector' => 'has_parent!=2',
		'labelFieldName' => 'title',
	],

	/**
	 * To have an Page input that allows adding new pages you must specify: 	
	 * addable=true along with a parent_id and template_id.
	 * 
	 * This is commented out since we do not know what parent/template 
	 * would be used in your installation. 
	 * 
	'categories' => [
		'type' => 'page', 
		'label' => 'Categories',
		'addable' => true, // true to allow adding new pages
		'parent_id' => 1234, // id of parent page for newly added pages
		'template_id' => 12, // id of template to use for newly added pages
		'derefAsPage' => 0, 
		'inputfield' => 'InputfieldCheckboxes',
	],
	*/
];