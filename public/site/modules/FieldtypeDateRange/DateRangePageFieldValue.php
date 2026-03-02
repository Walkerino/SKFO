<?php namespace ProcessWire;

require_once(__DIR__ . '/DateRangeValue.php');

/**
 * Date Range Page Field Value
 * 
 * This file is part of the ProFields package
 * Please do not distribute.
 * 
 * Same as DateRangeValue except connected to a Page and Field
 * for context specific formatting of dates and ranges.
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */
class DateRangePageFieldValue extends DateRangeValue {

	/**
	 * @var Page
	 *
	 */
	protected $page;

	/**
	 * @var DateRangeField
	 *
	 */
	protected $field;

	/**
	 * @var string
	 *
	 */
	protected $dateFormat = '';

	/**
	 * Construct
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @param string $dateFrom
	 * @param string $dateTo
	 * @param string $inFormat
	 *
	 */
	public function __construct(Page $page, Field $field, $dateFrom = '', $dateTo = '', $inFormat = '') {
		$this->page = $page;
		$this->field = $field;
		if(empty($inFormat)) $inFormat = (string) $field->get('format');
		parent::__construct($dateFrom, $dateTo, $inFormat);
		$page->wire($this);
	}

	/**
	 * Set page
	 * 
	 * @param Page $page
	 * @return $this
	 * 
	 */
	public function setPage(Page $page) {
		$this->page = $page;
		return $this;
	}

	/**
	 * Get page
	 * 
	 * @return Page
	 * 
	 */
	public function getPage() {
		return $this->page;
	}

	/**
	 * Set field
	 * 
	 * @param Field $field
	 * @return $this
	 * 
	 */
	public function setField(Field $field) {
		$this->field = $field;
		return $this;
	}

	/**
	 * Get field
	 * 
	 * @return DateRangeField
	 * 
	 */
	public function getField() {
		return $this->field;
	}

	/**
	 * Get output formatting status of page
	 * 
	 * @return bool
	 * 
	 */
	protected function of() {
		return $this->page->of();
	}

	/**
	 * Get 'date_from' or 'date_to'
	 * 
	 * @param sring $key One of 'date_from' or 'date_to'
	 * @param string $dateFormat Optionally use this date format
	 * @return string
	 * 
	 */
	protected function getDate($key, $dateFormat = '') {
		$value = ($key === 'date_from' ? parent::getDateFrom() : parent::getDateTo());
		if(!$this->of()) return $value;
		if(empty($dateFormat)) $dateFormat = $this->getPhpDateFormat();
		return $this->format($value, $dateFormat);
	}

	/**
	 * Get date from
	 * 
	 * @return string
	 * 
	 */
	public function getDateFrom() {
		return $this->getDate('date_from');
	}

	/**
	 * Get date to
	 * 
	 * @return string
	 * 
	 */
	public function getDateTo() {
		return $this->getDate('date_to');
	}

	/**
	 * Get php date format
	 * 
	 * @return string
	 * 
	 */
	protected function getPhpDateFormat() {
		if(empty($this->dateFormat)) {
			$fieldtype = $this->field->type; /** @var FieldtypeDateRange $fieldtype */
			$this->dateFormat = $fieldtype->getPhpDateOutputFormat($this->field);
		}
		return $this->dateFormat;
	}

	/**
	 * Get date range
	 * 
	 * @param string $dateFormat Optionally use this date format
	 * @return string
	 * 
	 */
	public function getRange($dateFormat = '') {
		if(!$this->of()) return parent::getRange($dateFormat);
		if($this->date_from === $this->date_to && $this->field->get('collapseRange')) {
			// collapse to just one date if from/to are the same
			$value = $this->getDate('date_from', $dateFormat);
		} else {
			if(empty($dateFormat)) $dateFormat = $this->getPhpDateFormat();
			$value = parent::getRange($dateFormat);
			$fieldtype = $this->field->type; /** @var FieldtypeDateRange $fieldtype */
			$separator = $fieldtype->getDateRangeOutputSeparator($this->field);
			$value = str_replace(self::separator, $separator, $value);
		}
		return $value;
	}
}