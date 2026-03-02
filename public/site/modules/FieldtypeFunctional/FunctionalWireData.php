<?php namespace ProcessWire;

/**
 * ProcessWire ProFields: WireData for Functional Fields 
 *
 * This is a commercial module, part of the ProcessWire ProFields package.
 * DO NOT DISTRIBUTE
 *
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 *
 */

class FunctionalWireData extends WireData {

	/**
	 * @var Page
	 *
	 */
	protected $page;

	/**
	 * @var Field
	 *
	 */
	protected $field;

	/**
	 * Wired to API
	 * 
	 */
	public function wired() {
		parent::wired();
		$this->page = $this->wire()->page;
	}

	/**
	 * Set page that this data belongs to
	 * 
	 * @param Page $page
	 * 
	 */
	public function setPage(Page $page) {
		$this->page = $page;
	}

	/**
	 * Set field that this data belongs to
	 *
	 * @param Field $field
	 *
	 */
	public function setField(Field $field) {
		$this->field = $field;
	}

	/**
	 * Get a property
	 *
	 * @param string $key
	 * @return string
	 *
	 */
	public function get($key) {
		if($this->wire()->languages && $this->__isset('.of') && strpos($key, '.') !== 0) {
			// formatted value
			$language = $this->wire()->user->language;
			if(!$language->isDefault()) {
				// getting in a non-default language, first try to get in user's language
				if($this->__isset("$key.$language->id")) return parent::get("$key.$language->id");
				// otherwise try to get original default static value
				if($this->__isset(".$key")) return parent::get(".$key");
			}
		}
		return parent::get($key);
	}

	/**
	 * Get a language value
	 * 
	 * @param int|string|Language $language
	 * @param string $key Textdomain
	 * @return string|null
	 * @throws WireException
	 * 
	 */
	public function getLanguageValue($language, $key) {
		if(!is_object($language)) $language = $this->wire()->languages->get($language);
		if(!$language || !$language->id) throw new WireException("Unknown langauge");
		$value = $language->isDefault() ? parent::get($key) : parent::get("$key.$language");
		if(empty($value)) $value = parent::get(".$key");
		if(empty($value)) $value = parent::get($key);
		return $value;
	}

	/**
	 * Set a language value
	 *
	 * @param int|string|Language $language
	 * @param string $key Textdomain
	 * @param string $value
	 * @return WireData|FunctionalWireData
	 * @throws WireException
	 *
	 */
	public function setLanguageValue($language, $key, $value) {
		if(!is_object($language)) $language = $this->wire()->languages->get($language);
		if(!$language || !$language->id) throw new WireException("Unknown langauge");
		if(!$language->isDefault()) $key = "$key.$language";
		return parent::set($key, $value);
	}

	/**
	 * Default the default/static value for given textdomain
	 * 
	 * @param string $textdomain
	 * @return string|null
	 * 
	 */
	public function getDefaultValue($textdomain) {
		$value = parent::get(".$textdomain");
		if(empty($value)) $value = parent::get($textdomain);
		return $value; 
	}

	/**
	 * Set the default/static value for given textdomain
	 * 
	 * @param string $textdomain
	 * @param string $value
	 * @return WireData|FunctionalWireData
	 * 
	 */
	public function setDefaultValue($textdomain, $value) {
		return parent::set(".$textdomain", $value); 
	}
}