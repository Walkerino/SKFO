<?php namespace ProcessWire;

/**
 * Dates
 *
 * Specify the 'type' of 'datetime' to use dates
 * Specify an 'inputType' of either 'text', 'html' or 'select' (examples of each below)
 * Specify a `dateOutputFormat` (PHP date format) to use for dates when $page output formatting is on. 
 * If no dateOutputFormat is specified, it will attempt to use dateInputFormat for formatted values. 
 * 
 * ## inputType: text (enables use of jQuery UI datepicker)
 * 
 * - `inputType` (string): Specify "text" to use jQuery UI datepicker and the following options:
 * - `datepicker` (int): 1 to show datepicker button, 2 to show inline, 3 to show on focus.
 * - `dateInputFormat` (string): PHP date format to use for input (https://www.php.net/manual/en/datetime.format.php).
 * - `timeInputFormat` (string): If also using time, specify time input format (see above link).
 * - `placeholder` (string): Optional placeholder string to show when input has no value. 
 * - `defaultToday` (bool): Specify true to use the current date/time as the default value. 
 * 
 * ## inputType: html (enables use of HTML5 date, time, or datetime inputs)
 * 
 * - `inputType` (string): Specify "html" to use HTML5 date/time input and the following options:
 * - `htmlType` (string): Specify one of "date", "datetime" or "time"
 *
 * ## inputType: select (enables use of separate year, month and day selects)
 * 
 * - `inputType` (string): Specify "select" to use separate year/month/day select boxes.
 * - `dateSelectFormat` (string): Specify order for month day and year inputs. For example:
 *    - `mdy`: Sep 1 2024
 *    - `Mdy`: September 1 2024
 *    - `ymd`: 2024 Sep 1
 *    - `yMd`: 2024 September 1
 *
 */

/** @var Page|NullPage $page Page instance is provided when applicable */
/** @var Field|null $field Field instance is provided when applicable */


return [
	// date field (not multi-language)
	'date_picker' => [
		'type' => 'datetime',
		'label' => __('Date picker'),
		'inputType' => 'text',
		'datepicker' => 3, // show datepicker: 3=focus, 2=inline, 1=button
		'dateInputFormat' => 'Y/m/d',
		'dateOutputFormat' => 'F j Y', 
		'placeholder' => 'YYYY/MM/DD',
		'columnWidth' => 33,
	],
	'datetime_picker' => [
		'type' => 'datetime',
		'label' => __('Date and time pickers'),
		'inputType' => 'text',
		'datepicker' => 3, // show datepicker: 3=focus, 2=inline, 1=button
		'dateInputFormat' => 'Y/m/d',
		'timeInputFormat' => 'H:i',
		'placeholder' => 'YYYY/MM/DD HH:MM',
		'columnWidth' => 33,
	],
	'date_picker_button' => [
		'type' => 'datetime',
		'label' => __('Date picker on button click'),
		'inputType' => 'text',
		'datepicker' => 1, // show datepicker: 3=focus, 2=inline, 1=button
		'dateInputFormat' => 'Y/m/d',
		'placeholder' => 'YYYY/MM/DD',
		'columnWidth' => 34,
	],
	'date_html' => [
		'type' => 'datetime',
		'label' => __('HTML5 date'),
		'inputType' => 'html',
		'htmlType' => 'date', // html input type: date, time, or datetime
		'columnWidth' => 33,
	],
	'date_select' => [
		'type' => 'datetime',
		'label' => __('Date select'),
		'inputType' => 'select',
		'dateSelectFormat' => 'mdy', // 'mdy'=Sep 1 2024, 'Mdy'=September 1 2024, 'ymd'=2024 Sep 1
		'yearFrom' => 2024, 
		'yearTo' => date('Y') + 10,
		'columnWidth' => 66,
	],
	'date_picker_inline' => [
		'type' => 'datetime',
		'label' => __('Date picker inline'),
		'inputType' => 'text',
		'datepicker' => 2, // show datepicker: 3=focus, 2=inline, 1=button
		'dateInputFormat' => 'Y/m/d',
		'placeholder' => 'YYYY/MM/DD',
		'collapsed' => 1, 
	],
];