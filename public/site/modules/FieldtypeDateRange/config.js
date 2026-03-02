/**
 * JS for Date Range Inputfield configuration
 * 
 * This updates the live preview when configuring a date range field. 
 *
 * This file is part of the ProFields package
 * Please do not distribute.
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */

function InputfieldDateRangeConfig(options) {
	
	let $example = $('#date_range_example'); // the date range example <input>
	let $exampleInputfield = $example.closest('.Inputfield'); // the Inputfield that wraps the example
	
	let removeClasses = [ // classes removed on every update
		'Inline', 'StyleAdmin', 'DayLabelMode', '1Day' 
	]; 
	
	let resetNames = [ // setting names that require reset of example date selection value
		'format', 'startDate', 'endDate', 'minNights', 'maxNights', 'disabledDates',
		'disabledDaysOfWeek', 'noCheckInDaysOfWeek', 'noCheckOutDaysOfWeek' 
	];
	
	function settingChange($input) {
		let name = $input.attr('name'); // name of changed setting
		let value; // value of the changed setting
		let $inputfield = $input.closest('.Inputfield'); // the Inputfield that wraps the changed setting
		let $inputs = $inputfield.find(':input'); // all the inputs in the changed setting (if more than one)
		let addClasses = []; // classes that are added when the settings dictate it
		let settings = options;
	
		// if setting name starts with an underscore then ignore it
		if(name.indexOf('_') === 0) return;
		
		// if setting does not update the example preview then exit now
		if($input.hasClass('no-example-preview')) return;
	
		// get the setting value
		if($inputfield.hasClass('InputfieldTextTags') || name === 'disabledDates') {
			// string to array
			value = $input.val().split(' ');
		} else if($inputs.length > 1) {
			// multiple inputs where only 1 can be selected
			$input = $inputfield.find(':input:selected, :input:checked');
			value = $input.length ? $input.val() : '';
		} else {
			// single input
			value = $input.val();
		}
	
		// remove all classes added by settings
		for(let n = 0; n < removeClasses.length; n++) {
			$exampleInputfield.removeClass('InputfieldDateRange' + removeClasses[n]);
		}
	
		// if string value contains only digits then make it an integer 
		if(typeof value === 'string') {
			if(value.match(/^\d+$/)) value = parseInt(value);
		}
		
		settings[name] = value;
		
		// add classes where settings dictate it
		if(settings.inline) addClasses.push('Inline');
		if(settings.outputStyle === 'admin') addClasses.push('StyleAdmin');
		if(settings.dayLabelMode || settings.maxNights < 0) addClasses.push('DayLabelMode');
		if(settings.maxNights < 0) addClasses.push('1Day');
		
		for(let n = 0; n < addClasses.length; n++) {
			$exampleInputfield.addClass('InputfieldDateRange' + addClasses[n]);
		}
		
		// reset example value when some settings change (date range selection)
		if(resetNames.includes(name)) $example.val('');
		
		// reset date range example
		InputfieldDateRange.reset('date_range_example', settings);
	}
	
	let timer = null;

	// setup change event for all inputs in the date range settings
	$('#Inputfield__InputfieldDateRange').on('change', ':input:not(#date_range_example)', function() {
		if(timer) clearTimeout(timer);
		let $this = $(this);
		timer = setTimeout(function() { settingChange($this); }, 250);
	});
}

jQuery(document).ready(function() {
	if(typeof InputfieldDateRangeSettings !== 'undefined') {
		InputfieldDateRangeConfig(InputfieldDateRangeSettings);
	}
});