<?php namespace ProcessWire;

require_once(__DIR__ . '/DateRangeField.php');
require_once(__DIR__ . '/DateRangePageFieldValue.php');

/**
 * Date Range Fieldtype
 * 
 * This file is part of the ProFields package
 * Please do not distribute.
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @method array getDateOutputFormats()
 *
 */
class FieldtypeDateRange extends Fieldtype implements Module {
	
	public static function getModuleInfo() {
		return array(
			'title' => 'Date Range',
			'version' => 3,
			'summary' => 'Field holds a date range with date_from and date_to.',
			'icon' => 'calendar',
			'requires' => 'ProcessWire>=3.0.210'
		);
	}
	
	const defaultDateFormat = 'Y-m-d';

	/**
	 * Fetcha date formats to PHP date formats
	 * 
	 * Note: PHP date formats must be strtotime compatible OR start with a 'D'
	 *
	 * @var string[]
	 *
	 */
	protected $dateInputFormats = [
		'YYYY-MM-DD' => 'Y-m-d',
		'YYYY/MM/DD' => 'Y/m/d',
		'YYYY/M/D' => 'Y/n/j', 
		'MM/DD/YYYY' => 'm/d/Y',
		'MM/DD/YY' => 'm/d/y',
		'M/D/YY' => 'n/j/y',
		'MMM D, YYYY' => 'M j, Y',
		'MMMM D, YYYY' => 'F j, Y',
		'D MMM YYYY' => 'j M Y',
		'D MMMM YYYY' => 'j F Y',
		'D.M.YY' => 'j.n.y',
		'D.M.YYYY' => 'j.n.Y',
		'DD.MM.YYYY' => 'd.m.Y',
		'D-M-YY' => 'j-n-y',
		'D-M-YYYY' => 'j-n-Y',
		'DD-MM-YYYY' => 'd-m-Y',
		'DD/MM/YYYY' => 'd/m/Y',
		'DD/MM/YY' => 'd/m/y',
		'D/M/YY' => 'j/n/y',
		'D/M/YYYY' => 'j/n/Y',
	];

	/**
	 * Additional formats allowed for output (only)
	 * 
	 * Does not need to be strtotime compatible
	 * 
	 * @var string[] 
	 * 
	 */
	protected $dateOutputFormats = [];

	/**
	 * Date date formats as [ fechaFormat => phpDateFormat ]
	 * 
	 * @return string[]
	 * 
	 */
	public function getDateInputFormats() {
		return $this->dateInputFormats;
	}

	/**
	 * Get date output formats as [ fetchaFormat => phpDateFormat ]
	 * 
	 * @return string[]
	 * 
	 */
	public function ___getDateOutputFormats() {
		return array_merge($this->dateInputFormats, $this->dateOutputFormats); 
	}

	/**
	 * Get the PHP date input format used by the given Field
	 * 
	 * @param Field $field
	 * @return string
	 * 
	 */
	public function getPhpDateInputFormat(Field $field) {
		$format = (string) $field->get('format');	
		if(empty($format)) return self::defaultDateFormat;
		if(!isset($this->dateInputFormats[$format])) return self::defaultDateFormat;
		return $this->dateInputFormats[$format];
	}
	
	/**
	 * Get the PHP date output format used by the given Field
	 *
	 * @param Field $field
	 * @return string
	 *
	 */
	public function getPhpDateOutputFormat(Field $field) {
		$format = (string) $field->get('dateOutputFormat');
		if(empty($format)) return self::defaultDateFormat;
		if(isset($this->dateInputFormats[$format])) return $this->dateInputFormats[$format];
		$formats = $this->getDateOutputFormats();
		if(isset($formats[$format])) return $formats[$format];
		return self::defaultDateFormat;
	}

	/**
	 * Get the range separator used when output formatting is on 
	 * 
	 * @param Field $field
	 * @return string
	 * 
	 */
	public function getDateRangeOutputSeparator(Field $field) {
		$value = (string) $field->get('dateRangeSeparator');
		if(!strlen($value)) return ' - ';
		$value = str_replace('S', ' ', $value);
		return $value;
	}

	/**
	 * Get class name to use Field objects of this type
	 *
	 * @param array $a Field data from DB (if needed)
	 * @return string Return class name or blank to use default Field class
	 *
	 */
	public function getFieldClass(array $a = []) {
		return 'DateRangeField';
	}
	
	/**
	 * Sanitize value 
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param int|string|mixed $value
	 * @return DateRangeValue
	 *
	 */
	public function sanitizeValue(Page $page, Field $field, $value) {
		$value = $this->sanitizeDateRangeValue($page, $field, $value);
		return $value;
	}

	/**
	 * Get the rendered markup value for a DateRangeValue
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @param DateRangeValue|null $value
	 * @param string $property
	 * @return string
	 * 
	 */
	public function ___markupValue(Page $page, Field $field, $value = null, $property = '') {
		if($value === null) $value = $page->getFormatted($field->name);
		if(!$value instanceof DateRangeValue) return '';
		if(!$value instanceof DateRangePageFieldValue) $value = $value->getPageFieldValue($page, $field);
		$of = $page->of();
		if(!$of) $page->of(true);
		$markupValue = $property ? $value->$property : (string) $value;
		if(!$of) $page->of(false);
		return $this->wire()->sanitizer->entities($markupValue);
	}

	/**
	 * Get blank value
	 *
	 * @param Page $page
	 * @param Field $field
	 * @return DateRangeValue
	 *
	 */
	public function getBlankValue(Page $page, Field $field) {
		return $this->getBlankDateRangeValue($page, $field);
	}

	/**
	 * @return DateRangeValue
	 * 
	 */
	public function getBlankDateRangeValue(Page $page, Field $field) {
		$blankValue = new DateRangePageFieldValue($page, $field);
		return $blankValue;
	}

	/**
	 * Sanitize date range value
	 * 
	 * @param string|array $value
	 * @return array|string
	 * 
	 */
	public function sanitizeDateRangeValue(Page $page, Field $field, $value) {
		
		if($value instanceof DateRangeValue) {
			if(!$value instanceof DateRangePageFieldValue) {
				$value = $value->getPageFieldValue($page, $field);
			}
		} else if(is_string($value) || is_array($value)) {
			$value = new DateRangePageFieldValue($page, $field, $value);
		} else {
			$value = $this->getBlankDateRangeValue($page, $field);
		}

		return $value;
	}

	/**
	 * Get compatible Fieldtypes
	 *
	 * @param Field $field
	 * @return Fieldtypes
	 *
	 */
	public function ___getCompatibleFieldtypes(Field $field) {
		$fieldtypes = new Fieldtypes();
		return $fieldtypes;
	}
	
	/**
	 * Get Inputfield for this Fieldtype
	 *
	 * @param Page $page
	 * @param Field $field
	 * @return InputfieldDateRange
	 *
	 */
	public function getInputfield(Page $page, Field $field) {
		/** @var InputfieldDateRange $inputfield */
		$inputfield = $this->wire()->modules->get('InputfieldDateRange');
		return $inputfield;
	}

	/**
	 * Get DB schema
	 *
	 * @param Field $field
	 * @return array
	 *
	 */
	public function getDatabaseSchema(Field $field) {
		$schema = parent::getDatabaseSchema($field);
		$schema['data'] = 'VARCHAR(25) NOT NULL';
		$schema['date_from'] = 'DATE NOT NULL';
		$schema['date_to'] = 'DATE NOT NULL';
		$schema['nights'] = 'INT UNSIGNED NOT NULL DEFAULT 0';
		$schema['keys']['primary'] = 'PRIMARY KEY (`pages_id`)';
		$schema['keys']['data'] = 'KEY(`data`)';
		$schema['keys']['dates'] = 'INDEX dates (`date_from`, `date_to`)';
		$schema['keys']['date_to'] = 'INDEX date_to (`date_to`)';
		$schema['keys']['nights'] = 'INDEX nights (`nights`)';
		return $schema;
	}
	
	/**
	 * Wakeup value
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param array|int|string $value
	 * @return DateRangeValue
	 *
	 */
	public function ___wakeupValue(Page $page, Field $field, $value) {
		$wakeupValue = $this->getBlankValue($page, $field);
		if(is_array($value)) {
			$wakeupValue->setDateFrom($value['date_from']); 
			$wakeupValue->setDateTo($value['date_to']); 
		} else if(is_string($value)) {
			$wakeupValue->date_from = $value;
		}
		return $wakeupValue;
	}

	/**
	 * Sleep value
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param DateRangeValue|string $value
	 * @return array
	 *
	 */
	public function ___sleepValue(Page $page, Field $field, $value) {
		if(!$value instanceof DateRangeValue) {
			$value = $this->wakeupValue($page, $field, $value);
		}
		$value->validate();
		$sleepValue = [
			'data' => $value->getRange(),
			'date_from' => $value->date_from,
			'date_to' => $value->date_to,
			'nights' => $value->getNights(),
		];
		return $sleepValue;
	}

	/**
	 * Is given value one that should cause the DB row(s) to be deleted rather than saved?
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param mixed $value
	 * @return bool
	 *
	 */
	public function isDeleteValue(Page $page, Field $field, $value) {
		if($value instanceof DateRangeValue) {
			if(!strlen("$value->date_from$value->date_to")) return true;
		}
		return false;
	}

	/**
	 * Get the query that matches a Fieldtype table's data with a given value
	 *
	 * @param PageFinderDatabaseQuerySelect $query
	 * @param string $table The table name to use
	 * @param string $subfield Name of the field (typically 'data', unless selector explicitly specified another)
	 * @param string $operator The comparison operator
	 * @param mixed $value The value to find
	 * @return PageFinderDatabaseQuerySelect|DatabaseQuerySelect $query
	 *
	 */
	public function getMatchQuery($query, $table, $subfield, $operator, $value) {
	
		$value = (string) $value;
		$subfieldLower = strtolower($subfield);
		$where = '';

		if($subfield === 'from' || $subfieldLower === 'datefrom') {
			$subfield = 'date_from';
			
		} else if($subfield === 'to' || $subfieldLower === 'dateto') {
			$subfield = 'date_to';

		} else if($subfield === 'in' || $subfield === 'has' || $subfieldLower === 'inrange') {
			$subfield = 'in_range';
			
		} else if($subfield === 'nights' || $subfield === 'days') {
			// passthru
			$value = (int) $value;
			if($subfield === 'days' && $value > 0) $value = $value - 1;
			$subfield = 'nights';
			return parent::getMatchQuery($query, $table, $subfield, $operator, $value);
			
		} else {
			$now = date('Y-m-d');
			if($subfield === 'current' || $subfield === 'is_current') {
				$where = "$table.date_from<='$now' AND $table.date_to>='$now'";
			} else if($subfield === 'future' || $subfield === 'is_future') {
				$where = "$table.date_from>'$now' AND $table.date_to>'$now'";
			} else if($subfield === 'past' || $subfield === 'is_past') {
				$where = "$table.date_from<'$now' AND $table.date_to<'$now'";
			} else if(!in_array($subfield, [ 'date_from', 'date_to', 'data', 'in_range' ])) {
				return parent::getMatchQuery($query, $table, $subfield, $operator, $value);
			}
			if($where) {
				$where = $value ? "($where)" : "NOT($where)";
				$query->where($where);
				return $query;
			}
		} 
		
		$tableCol = $this->wire()->database->escapeTableCol("$table.$subfield");
		$likeOperator = in_array($operator, [ '%=', '^=', '$=' ]);
		$digital = ctype_digit($value);
		$hasYear = strlen($value) >= 4 && ctype_digit(substr($value, 0, 4));
			
		if($likeOperator) {
			// date_from, date_to, or data
			if($operator === '%=') {
				$value = "%$value%";
			} else if($operator === '^=') {
				$value = "$value%";
			} else if($operator === '$=') {
				$value = "%$value";
			}
			$bindKey = $query->bindValueGetKey($value);
			$where = "$tableCol LIKE $bindKey";
			
		} else if($subfield === 'data') {
			// match full range value
			if($digital && strlen($value) > 4) $value = date('Y-m-d', $value);
			
			if(strpos($value, DateRangeValue::separator)) {
				// ok leave full range as-is
				
			} else if($digital && $hasYear) {
				// i.e. "2024"
				$bindKey = $query->bindValueGetKey($value);
				$where = "YEAR($table.date_from)$operator$bindKey AND YEAR($table.date_to$operator$bindKey)";
				
			} else if(preg_match('/^(\d{4})[^\d](\d{1,2})$/', $value, $matches)) {
				// i.e. "2024-12"
				$bindYear = $query->bindValueGetKey($matches[1]);
				$bindMonth = $query->bindValueGetKey($matches[2]);
				$where = 
					"YEAR($table.date_from)$operator$bindYear " . 
					"AND MONTH($table.date_from)$operator$bindMonth " .
					"AND YEAR($table.date_to)$operator$bindYear " .
					"AND MONTH($table.date_to)$operator$bindMonth ";
				
			} else if(preg_match('!^(\d{4})[^\d](\d{1,2})[^\d](\d{1,2})$!', $value, $matches)) {
				// i.e. "2024-12-23"
				$year = $matches[1];
				$month = strlen($matches[2]) < 2 ? '0' . $matches[2] : $matches[2];
				$day = strlen($matches[3]) < 2 ? '0' . $matches[3] : $matches[3];
				$bindValue = $query->bindValueGetKey("$year-$month-$day");
				$where = "$table.date_from$operator$bindValue AND $table.date_to$operator$bindValue";
			}
		}
		
		if(!$where && ($subfield === 'date_from' || $subfield === 'date_to')) {
			if($digital && $hasYear && strlen($value) === 4) {
				// year i.e. "2024"
				$bindKey = $query->bindValueGetKey($value);
				$where = "YEAR($tableCol)$operator$bindKey";
			} else if($digital) {
				// unix timestamp
				$value = date('Y-m-d', $value);
			} else if($hasYear && preg_match('!^(\d{4})[^\d](\d{1,2})$!', $value, $matches)) {
				// year-month i.e. "2024-12"
				$bindYear = $query->bindValueGetKey($matches[1]);
				$bindMonth = $query->bindValueGetKey($matches[2]);
				$where = "YEAR($tableCol)$operator$bindYear AND MONTH($tableCol)$operator$bindMonth";
			} else {
				// non digit value i.e. "2024-12-13"
				$value = self::strtotime($value);
				$value = date('Y-m-d', $value);
			}
			
		} else if($subfield === 'in_range' || $subfield === 'in') {
			$value = date('Y-m-d', $digital ? $value : self::strtotime($value)); // full date required
			$date = $query->bindValueGetKey($value);
			if($operator === '!=') {
				$where = "$table.date_from>$date OR $table.date_to<$date";
			} else {
				$where = "$table.date_from<=$date AND $table.date_to>=$date";
			}
		}
		
		if($where) {
			$query->where("($where)");
			return $query;
		} else {
			return parent::getMatchQuery($query, $table, $subfield, $operator, $value);
		}
	}
	
	/**
	 * Get information used for InputfieldSelector interactive selector builder
	 *
	 * @param Field $field
	 * @param array $data
	 * @return array
	 *
	 */
	public function ___getSelectorInfo(Field $field, array $data = array()) {
		$info = parent::___getSelectorInfo($field, $data);
		$operators = [ '=', '!=', '%=', '^=', '=""', '!=""' ];
		$yesNoOptions = [
			1 => $this->_('Yes'), 
			0 => $this->_('No'),
		];
		$subfields = array(
			'date_from' => array(
				'name' => 'date_from',
				'input' => 'date',
				'operators' => $operators, 
				'label' => $this->_('Date from'),
			),
			'date_to' => array(
				'name' => 'date_to',
				'input' => 'date',
				'operators' => $operators, 
				'label' => $this->_('Date to'),
			),
			'in_range' => array(
				'name' => 'in_range',
				'input' => 'date', 
				'operators' => array('=', '!='), 
				'label' => $this->_('Has date in range'), 
			),
			'nights' => array(
				'name' => 'nights',
				'input' => 'integer',
				'operators' => array('=', '!=', '>', '>=' ,'<', '<='),
				'label' => $this->_('Nights'),
			),
			'days' => array(
				'name' => 'days',
				'input' => 'integer',
				'operators' => array('=', '!=', '>', '>=' ,'<', '<='),
				'label' => $this->_('Days'),
			),
			'is_current' => array(
				'name' => 'current', 
				'input' => 'select', 
				'options' => $yesNoOptions,
				'label' => $this->_('Is current'), 
			),
			'is_future' => array(
				'name' => 'future',
				'input' => 'select',
				'options' => $yesNoOptions,
				'label' => $this->_('Is in future'),
			),
			'is_past' => array(
				'name' => 'past',
				'input' => 'select',
				'options' => $yesNoOptions,
				'label' => $this->_('Is in past'),
			),
		);
	
		$info['input'] = 'text';
		$info['operators'] = array('%=', '^=', '$=', '=', '!=', '=""', '!=""');
		$info['subfields'] = array_merge($info['subfields'], $subfields);

		return $info;
	}

	/**
	 * Get predefined setups for newly created fields of this type
	 *
	 * @return array
	 *
	 */
	public function ___getFieldSetups() {
		return [
			'daterange' => [
				'title' => $this->_('Date range with date_from and date_to'),
				'inline' => true, 
				'clearButton' => true, 
			],
			/*
			'datesingle' => [
				'title' => $this->_('Date range in single date mode'), 
				'maxNights' => -1,
				'inline' => true,
				'clearButton' => true,
			],
			*/
		];
	}

	/**
	 * Convert date string to time
	 * 
	 * @param string $str
	 * @param string Format that date is using or omit if not known
	 * @return false|int
	 * 
	 */
	static public function strtotime($str, $format = '') {
		$str = trim("$str");
		$f = substr(strtolower($format), 0, 1); // first char of format
		if(ctype_digit($str)) {
			// unix timestamp, already in required format
			return (int) $str; 
		} else if(ctype_digit(substr($str, 0, 4))) {
			// if leading 4 digit year then iso8601 or strtotime compatible assumed
		} else if(strpos($str, ' ')) {
			// date has spaces in it, which will assume strtotime compatibler
		} else if(strlen($str) && ($f === 'd' || $f === 'j')) {
			// format starts with 'd' or 'j' indicating day-first format
			$str = str_replace(['.', '-'], '/', $str); // normalize separators to '/'
			list($d, $m, $y) = explode('/', $str);
			if(strlen($d) < 2) $d = "0$d";
			if(strlen($m) < 2) $m = "0$m";
			if(strlen($y) === 2) $y = "20$y";
			$str = "$y-$m-$d"; // iso8601
		}
		return strtotime($str);
	}

	/**
	 * Get Inputfields to configure integer field
	 *
	 * @param Field $field
	 * @return InputfieldWrapper
	 *
	 */
	public function ___getConfigInputfields(Field $field) {
		$inputfields = parent::___getConfigInputfields($field);
		require_once(__DIR__ . '/config.php');
		$fieldset = $inputfields->InputfieldFieldset;
		$fieldset->attr('name', '_' . $this->className());
		$fieldset->label = $this->_('Date range output settings');
		$fieldset->icon = 'calendar';
		$inputfields->add($fieldset);
		FieldtypeDateRangeConfig($fieldset, $field);
		return $inputfields;
	}

}