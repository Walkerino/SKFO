<?php namespace ProcessWire;

require_once(__DIR__ . '/DateRangeValue.php');

/**
 * Date Range Inputfield
 * 
 * This file is part of the ProFields package
 * Please do not distribute.
 *
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 * 
 * Inputfield-specific settings
 * ------------------------
 * @property bool $dayLabelMode Refer to 'days' rather than 'nights' in labels?
 * @property string $outputStyle Output style to use, one of 'admin' or blank for stock/default.
 * 
 * Hotel Datepicker settings 
 * -------------------------
 * @property bool $inline Use inline mode where date picker is always visible?
 * @property bool $clearButton Add a button that clears the selected date range
 * @property bool $showTopbar Show the top bar?
 * @property bool $autoClose Close the datepicker after selection?
 * @property bool $selectForward Don't allow selections in both directions (first click = first date, second click = second date)
 * @property bool $enableCheckout Enable checkout on disabled dates?
 * @property bool $moveBothMonths Move both months when clicking on the next/prev month button
 * @property string $topbarPosition Location of top bar, specify 'bottom' or 'top'
 * @property string $format Date format to use in Fecha format, i.e. 'YYYY-MM-DD'
 * @property string $startOfWeek Day that starts the week, i.e. 'sunday'
 * @property int $minNights Minimum number of nights
 * @property int $maxNights Maximum number of nights
 * @property string $startDate Don't allow ranges before custom date i.e. 2023-11-21
 * @property string $endDate Don't allow ranges after custom date i.e. 2023-12-11
 * @property array $disabledDates Dates that are not selectable, i.e. [ '2023-11-23', '2023-11-19' ]
 * @property array $disabledDaysOfWeek Disabled week days, i.e. [ 'Monday', 'Tuesday' ]
 * @property array $noCheckInDaysOfWeek Disable check-in on specific days of week, i.e. [ 'Monday', 'Tuesday' ]
 * @property array $noCheckOutDaysOfWeek Disable check-out on specific days of week, i.e. [ 'Monday', 'Tuesday' ]
 * 
 * @method array getSettings($processing = false, $getAll = false) Get all settings that differ from defaults #pw-hooker
 *
 *
 */

class InputfieldDateRange extends InputfieldText {

	public static function getModuleInfo() {
		return array(
			'title' => 'ProFields: Date Range',
			'summary' => 'Enables selection of a date range in a calendar.',
			'icon' => 'calendar',
			'version' => 3,
			'requires' => 'ProcessWire>=3.0.210'
		);
	}

	/**
	 * Default settings for hotel datepicker library 
	 * 
	 * NOTE: these must reflect the defaults as they appear on hotel-datepicker.js
	 * 
	 * @var array 
	 * 
	 */
	protected $datepickerDefaults = [
	
		// The separator string used between date strings.
		'separator' => '-',
		
		// In inline mode the date picker is always visible
		'inline' => false,
		
		// Add a button that clears the selected date range
		'clearButton' => false,
		
		// Show the top bar?	
		'showTopbar' => true,

		// Location of top bar, specify 'bottom' or 'top'
		'topbarPosition' => 'top',
		
		// Close the datepicker after selection?	
		'autoClose' => true,

		// Date format to use
		'format' => 'YYYY-MM-DD',
		
		// Day that starts the week
		'startOfWeek' => 'sunday', 
		
		// Mininum number of nights
		'minNights' => 1, 
		
		// Maximum number of nights
		'maxNights' => 0, 
		
		// Don't allow ranges before custom date i.e. 2023-11-21
		'startDate' => '',
		
		// Don't allow ranges after custom date i.e. 2023-12-11
		'endDate' => '', 
		
		// Don't allow selections in both directions (first click = first date, second click = second date)
		'selectForward' => false,
	
		// Move both months when clicking between months	
		'moveBothMonths' => false, 
	
		// Dates that are not selectable, i.e. [ '2023-11-23', '2023-11-19' ]
		'disabledDates' => [],
	
		// Enable checkout on disabled dates?	
		'enableCheckout' => false, 
	
		// Disabled week days, i.e. [ 'Monday', 'Tuesday' ]	
		'disabledDaysOfWeek' => [], 
	
		// Disable check-in on specific days of week, i.e. [ 'Monday', 'Tuesday' ]	
		'noCheckInDaysOfWeek' => [], 
	
		// Disable check-out on specific days of week, i.e. [ 'Monday', 'Tuesday' ]		
		'noCheckOutDaysOfWeek' => [], 
	];

	/**
	 * Cache of translated labels 
	 * 
	 * @var array 
	 * 
	 */
	static protected $translatedLabels = [];

	/**
	 * Are we currently processing input?
	 * 
	 * @var bool 
	 * 
	 */
	protected $processing = false;

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		parent::__construct();
		$this->setArray($this->getDefaultSettings());
	}
	
	/**
	 * Render ready
	 * 
	 * @param Inputfield|null $parent
	 * @param bool $renderValueMode
	 * @return bool
	 * 
	 */
	public function renderReady(Inputfield $parent = null, $renderValueMode = false) {
		
		if($renderValueMode) return parent::renderReady($parent, $renderValueMode);
		
		$config = $this->wire()->config;
		$url = $config->urls($this);
		$ext = '.js'; // $config->debug ? '.js' : '.min.js'; // @todo
		$class = $this->className();
		$addClasses = [];
		
		$config->styles->add($url . 'hotel-datepicker/dist/css/hotel-datepicker.css'); 
		$config->scripts->add($url . 'fecha/dist/fecha.min.js');
		$config->scripts->add($url . 'hotel-datepicker/dist/js/hotel-datepicker' . $ext);

		if(empty(self::$translatedLabels)) {
			$i18n = $this->getLabels(false); // non-contextual
			$labels = $i18n->getTranslatedLabels();
			self::$translatedLabels = $labels;
			if($config->admin) {
				$config->js($class . 'Labels', $labels);
			} else {
				$s = 'script';
				$this->appendMarkup .= "<$s>{$class}Labels=" . json_encode($labels) . ";</$s>";
			}
		}
	
		if($this->dayLabelMode || $this->maxNights < 0) $addClasses[] = 'DayLabelMode';
		if($this->maxNights < 0) $addClasses[] = '1Day';
		if($this->inline) $addClasses[] = 'Inline';
		if($this->outputStyle) $addClasses[] = 'Style' . ucfirst($this->outputStyle);
		
		foreach($addClasses as $addClass) {
			$this->wrapClass('InputfieldDateRange' . $addClass);
		}
		
		return parent::renderReady($parent, $renderValueMode);
	}

	/**
	 * Render Inputfield
	 *
	 * @return string
	 *
	 */
	public function ___render() {
		
		$attrs = $this->getAttributes();
		$attrs['type'] = 'text';
	
		$value = (string) $attrs['value'];
		
		if(strpos($value, DateRangeValue::separator)) {
			$value = new DateRangeValue($value, '', $this->format);
			$dateFormats = $this->getDateFormats();
			$value = $value->getRange($dateFormats[$this->format]);
			$attrs['value'] = $value;
		}
		
		$attrStr = $this->getAttributesString($attrs);
		
		$settings = $this->getSettings();
		$settings = json_encode($settings);
		$s = 'script';

		$out = 
			"<input $attrStr />" . 
			"<$s>InputfieldDateRange.add('$attrs[id]', $settings);</$s>";
		
		return $out;
	}

	/**
	 * Process input
	 * 
	 * @param WireInputData $input
	 * @return self
	 * 
	 */
	public function ___processInput(WireInputData $input) {
		$this->processing = true;
		parent::___processInput($input);
		
		// apply these in case a hook modified them
		foreach($this->getSettings(true) as $key => $value) {
			$this->set($key, $value);
		}
	
		$this->processing = false;
		return $this;
	}

	/**
	 * Set setting or attribute
	 * 
	 * @param string $key
	 * @param string|array|bool|null $value
	 * @return self
	 * 
	 */
	public function set($key, $value) {
		if(isset($this->datepickerDefaults[$key])) {
			$default = $this->datepickerDefaults[$key];
			if(is_bool($default)) {
				$value = (bool) $value;
			} else if(is_int($default)) {
				$value = (int) $value;
			} else if(is_array($default) && !is_array($value)) {
				// disabledDates, disabledDaysOfWeek,
				// noCheckInDaysOfWeek, noCheckOutDaysOfWeek
				$value = explode(' ', "$value");
			}
		}
		return parent::set($key, $value);
	}

	/**
	 * Sanitize a set value attribute
	 * 
	 * Method originates in InputfieldText
	 * 
	 * @param string $value
	 * @return string
	 * 
	 */
	public function setAttributeValue($value) {
		return $this->sanitizeValue($value);
	}

	/**
	 * Sanitize value to string 
	 * 
	 * @param string|DateRangeValue $value
	 * @return string
	 * 
	 */
	public function sanitizeValue($value) {
		$separator = DateRangeValue::separator;
		if(!$value instanceof DateRangeValue) {
			$value = (string) $value;
			if(strpos($value, $separator) === false) {
				$value = $value ? ($value . $separator . $value) : '';
			}
			$value = new DateRangeValue($value, '', $this->format);
		}
		if($this->startDate && $value->date_from) { 
			// startDate only allows today and later
			if($value->date_from < date('Y-m-d') && !$this->processing) {
				// if date_from is prior to today, update startDate to 
				// allow for it, unless currently processing input
				$this->startDate = $value->date_from;
			}
		}
		return $value->getRange();
	}
	
	/**
	 * @return string[]
	 *
	 */
	public function getDateFormats() {
		/** @var FieldtypeDateRange $fieldtype */
		$fieldtype = $this->wire()->fieldtypes->get('FieldtypeDateRange');
		return $fieldtype->getDateInputFormats();
	}

	/**
	 * Get all configured settings that differ from defaults
	 *
	 * @param bool $processing Are we processing input?
	 * @param bool $getAll Get all settings rather than just those that differ from defaults?
	 * @return array
	 *
	 */
	public function ___getSettings($processing = false, $getAll = false) {
		
		$settings = [
			'dayLabelMode' => (bool) $this->dayLabelMode,
			'outputStyle' => $this->outputStyle, 
		];


		if(!$processing) {
			$settings['labels'] = $this->getLabels(true)->getOnlyTranslatedLabels(self::$translatedLabels);
		}
		
		foreach($this->datepickerDefaults as $key => $default) {
			$value = $this->getSetting($key);
			if(is_bool($default)) $value = (bool) $value;
			if(!$getAll && $default === $value) continue;
			$settings[$key] = $value;
		}

		$formats = $this->getDateFormats();
		
		foreach($this->datepickerDefaults as $key => $default) {
			$value = $this->getSetting($key);
			if(is_bool($default)) $value = (bool) $value;
			if(!$getAll && $default === $value) continue;
			if(($key === 'startDate' || $key === 'endDate') && !empty($value) && $this->format) {
				// convert to expected format
				$date = wireDate($formats[$this->format], $value);
				if(!empty($date)) $value = $date;
			}
			$settings[$key] = $value;
		}
		
		if(isset($settings['separator'])) $settings['separator'] = DateRangeValue::separator;
		if(isset($settings['startOfWeek'])) $settings['startOfWeek'] = strtolower($settings['startOfWeek']);
		
		return $settings;
	}

	/**
	 * Get default settings
	 *
	 * @return array
	 *
	 */
	public function getDefaultSettings() {
		$settings = $this->datepickerDefaults;
		$settings['dayLabelMode'] = false;
		$settings['outputStyle'] = 'admin';
		return $settings;
	}

	/**
	 * @return InputfieldDateRangeLabels
	 * @param bool $contextual Get contextual to this instance? (default=false)
	 *
	 */
	public function getLabels($contextual = false) {
		require_once(__DIR__ . '/InputfieldDateRangeLabels.php');
		$labels = new InputfieldDateRangeLabels(($contextual ? $this : null));
		$this->wire($labels);
		return $labels;
	}

	/**
	 * Inputfield configuration
	 *
	 * @return InputfieldWrapper
	 *
	 */
	public function getConfigInputfields() {
		$inputfields = parent::getConfigInputfields();
		$x = [ 'minlength', 'maxlength', 'pattern', 'stripTags', 'showCount', 'initValue' ];
		foreach($x as $name) {
			$f = $inputfields->getChildByName($name);
			if($f) $f->getParent()->remove($f);
		}
		$fieldset = $inputfields->InputfieldFieldset;
		$fieldset->attr('name', '_InputfieldDateRange');
		$fieldset->label = $this->_('Date range input settings');
		$fieldset->icon = 'calendar';
		require_once(__DIR__ . '/config.php');
		InputfieldDateRangeConfig($fieldset, $this);
		$inputfields->prepend($fieldset);
		return $inputfields;
	}

}