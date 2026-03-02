<?php namespace ProcessWire;

/**
 * Labels for InputfieldDateRange hotel-datepicker
 * 
 * This class makes them translatable in ProcessWire.
 * This file is part of the ProFields package.
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */ 
class InputfieldDateRangeLabels extends Wire {

	/**
	 * @var null|array 
	 * 
	 */
	static protected $labels = null;

	/**
	 * @var InputfieldDateRange|null 
	 * 
	 */
	protected $module = null;

	/**
	 * Construct
	 * 
	 * @param InputfieldDateRange|null $module
	 * 
	 */
	public function __construct($module = null) {
		if($module instanceof InputfieldDateRange) $this->module = $module;
		parent::__construct();
	}

	/**
	 * Get all labels untranslated
	 * 
	 * @return array
	 * 
	 */
	public function getUntranslatedLabels() {
		return $this->untranslated;
	}

	/**
	 * Get all labels translated
	 * 
	 * @return array
	 * 
	 */
	public function getTranslatedLabels() {
		return $this->translated();
	}

	/**
	 * Get only labels that differ from their source labels
	 * 
	 * @param array|null $sourceLabels Source labels or omit to use those in this class
	 * @return array
	 * 
	 */
	public function getOnlyTranslatedLabels(array $sourceLabels = null) {
		if($sourceLabels === null) {
			if(is_array(self::$labels)) return self::$labels;
			self::$labels = [];
			$translated = $this->translated();
			foreach($this->untranslated as $key => $value1) {
				$value2 = $translated[$key];
				if($value1 !== $value2) self::$labels[$key] = $value2;
			}
			$labels = self::$labels;
		} else {
			$translated = $this->translated();
			$labels = [];
			foreach($sourceLabels as $key => $value1) {
				$value2 = $translated[$key];
				if($value1 !== $value2) $labels[$key] = $value2;
			}
		}
		return $labels;
	}
	
	/**
	 * Untranslated values as they appear in hotel-datepicker.js
	 * 
	 * @var array 
	 * 
	 */
	protected $untranslated = [
		"selected" => 'Your stay:',
		"day" => 'Day',
		"days" => "Days",
		"night" => 'Night',
		"nights" => 'Nights',
		"button" => 'Close',
		"clearButton" => 'Clear',
		"submitButton" => 'Submit',
		"checkin-disabled" => 'Check-in disabled',
		"checkout-disabled" => 'Check-out disabled',
		"day-names-short" => [
			'Sun',
			'Mon',
			'Tue',
			'Wed',
			'Thu',
			'Fri',
			'Sat',
		],
		"day-names" => [
			'Sunday',
			'Monday',
			'Tuesday',
			'Wednesday',
			'Thursday',
			'Friday',
			'Saturday',
		],
		"month-names-short" => [
			'Jan',
			'Feb',
			'Mar',
			'Apr',
			'May',
			'Jun',
			'Jul',
			'Aug',
			'Sep',
			'Oct',
			'Nov',
			'Dec',
		],
		"month-names" => [
			'January',
			'February',
			'March',
			'April',
			'May',
			'June',
			'July',
			'August',
			'September',
			'October',
			'November',
			'December',
		],
		"error-more" => 'Date range should not be more than 1 night',
		"error-more-plural" => 'Date range should not be more than %d nights',
		"error-less" => 'Date range should not be less than 1 night',
		"error-less-plural" => 'Date range should not be less than %d nights',
		"info-more" => 'Please select a date range of at least 1 night',
		"info-more-plural" => 'Please select a date range of at least %d nights',
		"info-range" => 'Please select a date range between %d and %d nights',
		"info-range-equal" => 'Please select a date range of %d nights',
		"info-default" => 'Please select a date range',
		"aria-application" => 'Calendar',
		"aria-selected-checkin" => 'Selected as check-in date, %s',
		"aria-selected-checkout" => 'Selected as check-out date, %s',
		"aria-selected" => 'Selected, %s',
		"aria-disabled" => 'Not available, %s',
		"aria-choose-checkin" => 'Choose %s as your check-in date',
		"aria-choose-checkout" => 'Choose %s as your check-out date',
		"aria-prev-month" => 'Move backward to switch to the previous month',
		"aria-next-month" => 'Move forward to switch to the next month',
		"aria-close-button" => 'Close the datepicker',
		"aria-clear-button" => 'Clear the selected dates',
		"aria-submit-button" => 'Submit the form',
	];

	/**
	 * @return array
	 * 
	 */
	protected function translated() {

		$translated = [
			"selected" => $this->_('Selected dates:'), 
			"day" => $this->_('Day'), 
			"days" => $this->_('Days'), 
			"night" => $this->_('Night'),
			"nights" => $this->_('Nights'),
			"button" => $this->_('Close'),
			"clearButton" => $this->_('Clear'),
			"submitButton" => $this->_('Submit'),
			"checkin-disabled" => $this->_('Check-in disabled'),
			"checkout-disabled" => $this->_('Check-out disabled'),
			"day-names-short" => [
				$this->_('Sun'),
				$this->_('Mon'),
				$this->_('Tue'),
				$this->_('Wed'),
				$this->_('Thu'),
				$this->_('Fri'),
				$this->_('Sat'),
			],
			"day-names" => [
				$this->_('Sunday'),
				$this->_('Monday'),
				$this->_('Tuesday'),
				$this->_('Wednesday'),
				$this->_('Thursday'),
				$this->_('Friday'),
				$this->_('Saturday'),
			],
			"month-names-short" => [
				$this->_('Jan'),
				$this->_('Feb'),
				$this->_('Mar'),
				$this->_('Apr'),
				$this->_('May'),
				$this->_('Jun'),
				$this->_('Jul'),
				$this->_('Aug'),
				$this->_('Sep'),
				$this->_('Oct'),
				$this->_('Nov'),
				$this->_('Dec'),
			],
			"month-names" => [
				$this->_('January'),
				$this->_('February'),
				$this->_('March'),
				$this->_('April'),
				$this->_('May'),
				$this->_('June'),
				$this->_('July'),
				$this->_('August'),
				$this->_('September'),
				$this->_('October'),
				$this->_('November'),
				$this->_('December'),
			],
			"error-more" => $this->_('Date range should not be more than 1 night'),
			"error-more-plural" => $this->_('Date range should not be more than %d nights'),
			"error-less" => $this->_('Date range should not be less than 1 night'),
			"error-less-plural" => $this->_('Date range should not be less than %d nights'),
			"info-more" => $this->_('Please select a date range'), /* previously: Please select a date range of at least 1 night */
			"info-more-plural" => $this->_('Please select a date range of at least %d nights'),
			"info-range" => $this->_('Please select a date range between %d and %d nights'),
			"info-range-equal" => $this->_('Please select a date range of %d nights'),
			"info-default" => $this->_('Please select a date range'),
			"aria-application" => $this->_('Calendar'),
			"aria-selected-checkin" => $this->_('Selected as start date, %s'),
			"aria-selected-checkout" => $this->_('Selected as end date, %s'),
			"aria-selected" => $this->_('Selected, %s'),
			"aria-disabled" => $this->_('Not available, %s'),
			"aria-choose-checkin" => $this->_('Choose %s as your start date'),
			"aria-choose-checkout" => $this->_('Choose %s as your end date'),
			"aria-prev-month" => $this->_('Move backward to switch to the previous month'),
			"aria-next-month" => $this->_('Move forward to switch to the next month'),
			"aria-close-button" => $this->_('Close the datepicker'),
			"aria-clear-button" => $this->_('Clear the selected dates'),
			"aria-submit-button" => $this->_('Submit the form'),
		];
		if($this->module) {
			if($this->module->maxNights < 0) {
				// single-day selection mode
				$translated['selected'] = $this->_('Selected date:');
				$translated['info-default'] = $this->_('Please select a date');
				$translated['info-more'] = $translated['info-default'];
				$translated['info-more-plural'] = $translated['info-default'];
				$translated['info-range-equal'] = $translated['info-default'];
			}
			if($this->module->dayLabelMode) {
			} else {
			}
		}
		return $translated;
	}
}