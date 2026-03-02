<?php namespace ProcessWire;

/**
 * Date Range Value
 * 
 * This file is part of the ProFields package
 * Please do not distribute.
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 * @property string $date_from
 * @property string $dateFrom Alias
 * @property string $from Alias
 * @property string $date_to
 * @property string $dateTo Alias
 * @property string $to Alias
 * @property-read string $range
 * @property-read int $days
 * @property-read int $nights
 * @property-read bool $current
 * @property-read bool $future
 * @property-read bool $past
 * 
 */
class DateRangeValue extends Wire {
	
	const iso8601 = 'Y-m-d';
	const separator = ' - ';
	
	protected $date_from = '';
	protected $date_to = '';
	protected $data = [];
	protected $inFormat = '';

	/**
	 * Alternate names for date properties
	 * 
	 * @var string[] 
	 * 
	 */
	protected $alternates = [
		'dateFrom' => 'date_from',
		'dateTo' => 'date_to',
		'from' => 'date_from',
		'to' => 'date_to',
	];

	/**
	 * Construct
	 * 
	 * @param string|array $dateFrom
	 * @param string $dateTo
	 * @param string $inFormat Formats that given dates may be in
	 * 
	 */
	public function __construct($dateFrom = '', $dateTo = '', $inFormat = '') {
		$this->inFormat = $inFormat;
		if($dateFrom) $this->setDateFrom($dateFrom);
		if($dateTo) $this->setDateTo($dateTo);
		parent::__construct();
	}

	/**
	 * Set property
	 *
	 * @param string $key
	 * @param mixed $value
	 *
	 */
	public function __set($key, $value) {
		if(isset($this->alternates[$key])) $key = $this->alternates[$key];
		if($key === 'nights' || $key === 'days' || $key === 'range' || $key === 'data') {
			// skip, we do not store these
		} else if($key === 'date_from') {
			$this->setDateFrom($value);
		} else if($key === 'date_to') {
			$this->setDateTo($value);
		} else if(!isset($this->data[$key]) || $this->data[$key] !== $value) {
			$this->trackChange($key);
			$this->data[$key] = $value;
		}
	}

	/**
	 * Get property
	 * 
	 * @param string $name
	 * @return mixed
	 * 
	 */
	public function __get($name) {
		if(isset($this->alternates[$name])) $name = $this->alternates[$name];
		switch($name) {
			case 'nights': return $this->getNights();
			case 'days': return $this->getDays();
			case 'range': return $this->getRange();
			case 'date_from': return $this->getDateFrom();
			case 'date_to': return $this->getDateTo();
			case 'current': return $this->isCurrent();
			case 'future': return $this->isFuture();
			case 'past': return $this->isPast();
			
		}
		return isset($this->data[$name]) ? $this->data[$name] : null;
	}

	/**
	 * Set date from
	 *
	 * @param string|int $value
	 * @return self
	 *
	 */
	public function setDateFrom($value) {
		if(is_array($value)) return $this->setRange($value);
		if(strpos("$value", self::separator)) return $this->setRange($value);
		$value = $this->sanitizeDate($value);
		if($value != $this->date_from) $this->trackChange('date_from');
		$this->date_from = $value;
		return $this;
	}

	/**
	 * Set date to
	 * 
	 * @param string|int $value
	 * @return self
	 * 
	 */
	public function setDateTo($value) {
		$value = $this->sanitizeDate($value);
		if($value !== $this->date_to) $this->trackChange('date_to');
		$this->date_to = $value;
		return $this;
	}

	/**
	 * Get date from
	 *
	 * @return string
	 *
	 */
	public function getDateFrom() {
		return $this->date_from;
	}

	/**
	 * Get date to 
	 * 
	 * @return string
	 * 
	 */
	public function getDateTo() {
		return $this->date_to;
	}

	/**
	 * Get date range, optionally in given format
	 * 
	 * @param string $dateFormat
	 * @return string
	 * 
	 */
	public function getRange($dateFormat = '') {
		$dateFrom = $this->date_from;
		$dateTo = $this->date_to;
		if(empty($dateFrom)) return '';
		if(empty($dateTo)) $dateTo = $dateFrom;
		if($dateFormat) {
			$dateFrom = $this->format($dateFrom, $dateFormat);
			$dateTo = $this->format($dateTo, $dateFormat);
		}
		return $dateFrom . self::separator . $dateTo;
	}

	/**
	 * Set date range
	 * 
	 * @param string|array $dateFrom The date_from or range as "YYYY-MM-DD - YYYY-MM-DD"
	 * @param string $dateTo The date_to value if first argument was date_from (omit otherwise)
	 * @return self
	 * 
	 */
	public function setRange($dateFrom, $dateTo = '') {
		
		if(is_array($dateFrom)) {
			$a = $dateFrom;
			if(isset($a['date_from'])) {
				$dateFrom = $a['date_from'];
				$dateTo = isset($a['date_to']) ? $a['date_to'] : $dateFrom;
			} else if(count($a) === 2) {
				list($dateFrom, $dateTo) = $a;
			} else {
				// unrecognized	
			}

		} else if(!empty($dateTo)) {
			// separate dates specified
			
		} else if(is_string($dateFrom)) {
			// range potentially in dateFrom
			$seps = [ ' - ', '–', '—', '…', ' to ' ];
			$dateFrom = str_replace($seps, self::separator, trim($dateFrom));
			while(strpos($dateFrom, '  ') !== false) {
				$dateFrom = str_replace('  ', ' ', $dateFrom);
			}
			if(strpos($dateFrom, self::separator) !== false) {
				list($dateFrom, $dateTo) = explode(self::separator, $dateFrom, 2);
			}
		}


		if(empty($dateTo)) $dateTo = $dateFrom;

		$this->setDateFrom($dateFrom);
		$this->setDateTo($dateTo);
		return $this;
	}

	/**
	 * Get number of nights in range
	 * 
	 * @return int
	 * 
	 */
	public function getNights() {
		$days = $this->getDays();
		return $days > 0 ? $days-1 : 0;
	}

	/**
	 * Get number of days in range
	 * 
	 * @return int
	 * 
	 */
	public function getDays() {
		list($from, $to) = [ $this->date_from, $this->date_to ];
		if(!$from && !$to) {
			$days = 0;
		} else if($from && $to) {
			if($from === $to) return 1;
			$from = $this->strtotime($from);
			$to = $this->strtotime($to);
			$days = (int) (($to - $from) / 86400) + 1;
		} else if($from || $to) {
			$days = 1;
		} else {
			$days = 0;
		}
		return $days;
	}

	/**
	 * Is given date in this range
	 * 
	 * @param string|int $date Specify date as YYYY-MM-DD or UNIX timestamp, or omit for current date
	 * 
	 */
	public function inRange($date) {
		if(!ctype_digit("$date")) $date = $this->strtotime("$date");
		$date = date(self::iso8601, $date);
		return $date >= $this->date_from && $date <= $this->date_to;
	}

	/**
	 * Is given date in this range (alias of inRange)
	 *
	 * @param string|int $date Specify date as YYYY-MM-DD or UNIX timestamp, or omit for current date
	 *
	 */
	public function has($date) {
		return $this->inRange($date);
	}

	/**
	 * Is the current date in this range?
	 * 
	 * @return bool
	 * 
	 */
	public function isCurrent() {
		return $this->inRange(time());
	}

	/**
	 * Does this range start in the future?
	 * 
	 * @return bool
	 * 
	 */
	public function isFuture() {
		return $this->date_from > date(self::iso8601);
	}

	/**
	 * Has this date range already past?
	 * 
	 * @return bool
	 * 
	 */
	public function isPast() {
		return $this->date_to < date(self::iso8601);
	}

	/**
	 * Sanitize given value to YYYY-MM-DD string for internal storage
	 * 
	 * @param string|int $value
	 * @return false|string
	 * 
	 */
	protected function sanitizeDate($value) {
		if(empty($value) || $value === '0000-00-00') return '';
		if(ctype_digit("$value")) return date(self::iso8601, (int) $value);
		$value = $this->strtotime($value, $this->inFormat);
		$value = $value === false ? '' : date(self::iso8601, $value);
		return $value;
	}
	
	/**
	 * Format given date to YYYY-MM-DD or given date format
	 *
	 * @param string|int $date
	 * @param string $format PHP date format
	 * @return string
	 *
	 */
	protected function format($date, $format = '') {
		if(empty($format)) $format = self::iso8601;
		if(empty($date) || $date === '0000-00-00') return '';
		$date = wireDate($format, $this->strtotime($date));
		if($date === false) $date = '';
		return $date;
	}

	/**
	 * Validate current dates and swap if necessary
	 * 
	 */
	public function validate() {
		if(empty($this->date_from)) return;
		if(empty($this->date_to)) $this->date_to = $this->date_from;
		if($this->date_to < $this->date_from) {
			list($this->date_from, $this->date_to) = [$this->date_to, $this->date_from];
		}
	}

	/**
	 * Get a DateRangePageFieldValue for this DateRangeValue
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @return DateRangePageFieldValue
	 * 
	 */
	public function getPageFieldValue(Page $page, Field $field) {
		return new DateRangePageFieldValue($page, $field, $this->getDateFrom(), $this->getDateTo(), $this->inFormat);
	}

	/**
	 * Convert string to timestamp
	 * 
	 * @param string $str
	 * @param string $format
	 * @return false|int
	 * 
	 */
	protected function strtotime($str, $format = '') {
		return FieldtypeDateRange::strtotime($str, $format);
	}

	/**
	 * Get string value
	 * 
	 * @return string
	 * 
	 */
	public function __toString() {
		return $this->getRange();
	}

	/**
	 * Debug info
	 * 
	 * @return array
	 * 
	 */
	public function __debugInfo() {
		return [
			'range' => $this->getRange(), 
			'date_from' => $this->date_from,
			'date_to' => $this->date_to,
			'nights' => $this->getNights(),
			'days' => $this->getDays(), 
			'current' => $this->isCurrent(),
			'future' => $this->isFuture(),
			'past' => $this->isPast(), 
			'data' => $this->data, 
		];
	}

}