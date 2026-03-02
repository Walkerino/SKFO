<?php namespace ProcessWire;

/**
 * Verified URL
 * 
 * Represents an individual URL for the ProcessWire VerifiedURL Fieldtype
 * 
 * Part of the ProFields package
 * Please do not distribute.
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @property string $href
 * @property string $url Alias of href property
 * @property int $status HTTP status/response code
 * @property string $statusStr HTTP status/response code string with description, i.e. "200 OK" or "404 Not Found", etc. 
 * @property string $title
 * @property int $tries Number of consecutive error status codes received for this URL
 * @property string|null $redirect Redirect URL if status is 301 or 302
 * @property int $checked Time that last check was performed (unix timestamp)
 * @property string $content Content JSON
 * @property bool $_formatted True if value is formatted, false if not
 * @property string $_verifyValue For internal use by FieldtypeVerifiedURL::verifyValue() method
 * @property string|null $_hrefPrevious Previous href value, when changed
 * @property array $headers Specific response headers from URL if $field->getHeaders option is in use
 * @property array $matches Matches from preg_match if $field->matchRegex option is in use
 * @property string $html Full HTML contents if $field->useHTML option is in use
 * @property string $errstr Error string returned by PHP, if applicable
 * @property Page|null $page
 * @property Field|null $field
 * 
 */
class VerifiedURL extends WireData {

	/**
	 * @var Page|null
	 * 
	 */
	protected $page;

	/**
	 * @var Field|null
	 * 
	 */
	protected $field;

	/**
	 * Optional properties that may be JSON-encoded in the 'content' DB column
	 * 
	 * @var array
	 * 
	 */
	protected $contentKeys = array(
		'title', 
		'headers', 
		'tries',
		'redirect', 
		'matches',
		'errstr',
	);

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		parent::__construct();
		$this->reset();
		// runtime properties
		$this->set('_formatted', false);
		$this->set('_verifyValue', '');
	}

	/**
	 * Reset properties
	 * 
	 */
	public function reset() {
		$this->set('href', '');
		$this->set('status', 0);
		$this->set('checked', 0);
		// content properties
		$this->set('title', '');
		$this->set('tries', 0);
		$this->set('headers', array());
		$this->set('redirect', '');
		$this->set('matches', array());
		$this->set('html', '');
		$this->set('errstr', '');
	}

	/**
	 * Set page associated with this VerifiedURL
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
	 * Set field associated with this VerifiedURL 
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
	 * String value of a VerifiedURL is always the URL
	 * 
	 * @return string
	 * 
	 */
	public function __toString() {
		return $this->get('href');	
	}

	/**
	 * Is the URL valid and verified?
	 * 
	 * By default returns true if valid, false if URL produced an error,
	 * or integer 1 if status not-yet-determined. The not-yet-determined value can be
	 * overridden with the $fallbackValue argument. 
	 * 
	 * @param bool|int $fallbackValue Value to return if status not yet known
	 * @return bool|int
	 * 
	 */
	public function isValid($fallbackValue = 1) {
		$status = $this->get('status');
		if($status > 0 && $status < 400) {
			// success status
			return true;
		} else if($status >= 400) {
			// error status
			return false;
		} else {
			// not yet known
			return $fallbackValue;
		}
	}

	/**
	 * Set property
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return VerifiedURL|WireData
	 * 
	 */
	public function set($key, $value) {
		if($key === 'href' || $key === 'url') {
			return $this->setHref($value);
		} else if($key === 'title' || $key === 'errstr') {
			$value = $this->wire()->sanitizer->text($value);
		} else if($key === 'redirect') {
			$value = strlen("$value") ? $this->sanitizeHref($value) : '';
		} else if($key === 'content') {
			$this->contentJSON($value);
			return $this;
		} else if($key === 'status' || $key === 'checked' || $key === 'tries') {
			$value = (int) $value;
		} else if($key === 'page') {
			return $this->setPage($value);
		} else if($key === 'field') {
			return $this->setField($value);
		} else if($key === 'headers') {
			if(!is_array($value)) $value = array();
			$cleanValue = array();
			$sanitizer = $this->wire()->sanitizer;
			foreach($value as $k => $v) {
				$k = $sanitizer->text($k);
				$v = $sanitizer->text($v);
				if(strlen($k)) $cleanValue[$k] = $v;
			}
			$value = $cleanValue;
		}
		return parent::set($key, $value);
	}

	/**
	 * Set the href value
	 * 
	 * @param string $value
	 * @return VerifiedURL|WireData
	 * 
	 */
	protected function setHref($value) {
		$valuePrevious = parent::get('href');
		$value = $this->sanitizeHref($value);
		if($value && $valuePrevious && $valuePrevious !== $value) {
			// reset status, tries and redirect if URL has changed
			$this->tries = 0;
			$this->status = 0;
			$this->redirect = null;
			parent::set('_hrefPrevious', $valuePrevious);
		}
		return parent::set('href', $value);
	}

	/**
	 * Sanitize a URL/href value
	 * 
	 * @param string $value
	 * @return string
	 * 
	 */
	protected function sanitizeHref($value) {
		if($this->field) {
			/** @var FieldtypeVerifiedURL $fieldtype */
			$fieldtype = $this->field->type;
			$value = $fieldtype->sanitizeValueURL($this->page, $this->field, $value);
		} else {
			$value = $this->wire()->sanitizer->url($value);
		}
		return $value; 
	}

	/**
	 * Get property
	 * 
	 * @param string $key
	 * @return mixed|null|Field|Page|string
	 * 
	 */
	public function get($key) {
		if($key === 'url') {
			$key = 'href';
		} else if($key === 'content') {
			return $this->contentJSON();
		} else if($key === 'statusStr') {
			return $this->statusStr();
		} else if($key === 'page') {
			return $this->page;
		} else if($key === 'field') {
			return $this->field;
		}
		return parent::get($key);
	}

	/**
	 * Get or set the 'content' JSON 
	 * 
	 * @param string|bool $jsonData Specify JSON string to set, or omit to get
	 * @return string|null
	 * 
	 */
	public function contentJSON($jsonData = false) {
	
		if($jsonData === false) {
			// get content
			$content = array();
			foreach($this->contentKeys as $key) {
				$value = $this->get($key);
				if(!empty($value)) $content[$key] = $value;
			}
			return empty($content) ? null : json_encode($content);
			
		} else if($jsonData !== null && strlen("$jsonData")) {
			// set content
			$content = json_decode($jsonData, true);
			if(!is_array($content)) $content = array();
			foreach($this->contentKeys as $key) {
				if(isset($content[$key])) $this->set($key, $content[$key]);
			}
		}
		
		return null;
	}

	/**
	 * Get the href/url property as a fully http URL including scheme
	 * 
	 * This is primarily useful for local URLs that may be stored as 
	 * relative paths that do not initially include domain or scheme.
	 * 
	 * @return string
	 * 
	 */
	public function httpUrl() {

		$input = $this->wire()->input;
		$config = $this->wire()->config;
		$url = $this->get('href'); 
		
		if(empty($url) || strpos($url, '://')) {
			// if URL is empty or already has a scheme return it as-is
			return $url;
		}
		
		if(strpos($url, '//') === 0) {
			// if URL indicates request scheme, then use it
			return $input->scheme() . ":$url";
		}
		
		if(strpos($url, '/') === 0) {
			// URL relative to root: check if it needs root path added to it 
			if($this->field->get('addRoot') && !$this->field->get('noRelative')) {
				$url = rtrim($config->urls->root, '/') . $url;
			}
		} else if(!$this->field->get('noRelative')) {
			// URL relative to current page
			$url = rtrim($this->page->url(), '/') . '/' . $url;
		}
	
		/** @var WireInput $input */
		$url = ltrim($url, '/');
		if(method_exists($input, 'httpHostUrl')) {
			// httpHostUrl added recently, may not be available
			return $input->httpHostUrl() . "/$url";
		} else {
			return ($config->https ? 'https://' : 'http://') . $config->httpHost . "/$url";
		}
	}
	
	/**
	 * Get a string that represents the HTTP code status for a VerifiedURL value
	 *
	 * Examples:
	 *  - Verified 200 OK (1 day ago)
	 *  - Verified 301 Moved permanently => https://domain.com/path/ (1 day ago)
	 *  - Error 404 Not Found (3 weeks ago)
	 *  - Not yet verified
	 *
	 * @param array|bool $options Options to adjust what is included, see this method in FieldtypeVerifiedURL for all options.
	 * @return string|array
	 * @see FieldtypeVerifiedURL::statusStr()
	 *
	 */
	public function statusStr($options = array()) {
		/** @var FieldtypeVerifiedURL $fieldtype */
		$fieldtype = $this->field->type;
		return $fieldtype->statusStr($this, $options);
	}

	/**
	 * Verify this URL now (primarily for testing)
	 * 
	 * @param bool $save Save result to DB? (default=true)
	 * @return int HTTP status code
	 * 
	 */
	public function verifyNow($save = true) {
		/** @var FieldtypeVerifiedURL $fieldtype */
		$fieldtype = $this->field->type;
		return $fieldtype->verifyValue($this->page, $this->field, $this, $save);
	}
}