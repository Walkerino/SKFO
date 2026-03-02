<?php namespace ProcessWire;

/**
 * ProcessWire ProFields Combo Languages Value
 *
 * Part of the ProFields package.
 * Please do not distribute.
 *
 * Copyright (C) 2023 by Ryan Cramer
 *
 * https://processwire.com
 *
 */
class ComboLanguagesValue extends WireData implements LanguagesValueInterface {
	
	const delim = '§·';
	const delimX = '§ ·';

	/**
	 * Get language ID
	 * 
	 * @param string|Language|int $value
	 * @return int
	 * 
	 */
	protected function languageID($value) {
		if(is_int($value)) return $value;
		if(is_string($value) && !ctype_digit("$value")) {
			$value = $this->wire()->languages->get($value);
		}
		return (int) "$value";
	}

	/**
	 * Sets the value for a given language
	 *
	 * @param int|Language $languageID
	 * @param mixed $value
	 *
	 */
	public function setLanguageValue($languageID, $value) {
		$languageID = $this->languageID($languageID);
		$this->data[(int) "$languageID"] = (string) $value;
	}

	/**
	 * Given a language, returns the value in that language
	 *
	 * @param Language|int
	 * @return int
	 *
	 */
	public function getLanguageValue($languageID) {
		$id = $this->languageID($languageID);
		return isset($this->data[$id]) ? $this->data[$id] : '';
	}

	/**
	 * Grab language values from Inputfield and populate to this object
	 *
	 * @param Inputfield $inputfield
	 *
	 */
	public function setFromInputfield(Inputfield $inputfield) {
		foreach($this->wire()->languages as $language) {
			$key = $language->isDefault ? "value" : "value$language->id";
			$this->setLanguageValue($language->id, $inputfield->get($key));
		}
	}

	/**
	 * Populate language values from this object to given Inputfield
	 * 
	 * @param Inputfield $inputfield
	 * 
	 */
	public function setToInputfield(Inputfield $inputfield) {
		foreach($this->wire()->languages as $language) {
			$key = $language->isDefault ? "value" : "value$language->id";
			$inputfield->set($key, $this->getLanguageValue($language->id));
		}
	}

	/**
	 * Export all language values in here to a string that can be later imported
	 * 
	 * @return string
	 * 
	 */
	public function exportToString() {
		
		$languages = $this->wire()->languages;
		$exportValue = '';
		$defaultText = '';
		
		foreach($languages as $language) {
			$text = trim($this->getLanguageValue($language->id));
			if(!strlen($text)) continue;
			if(strpos($text, self::delim) !== false) $text = str_replace(self::delim, self::delimX, $text);
			if($language->isDefault()) {
				$defaultText = $text;
			} else {
				$exportValue .= self::delim . "$language->id:$text";
			}
		}

		return $defaultText . $exportValue;
	}

	/**
	 * Import from given string value to populate this object
	 * 
	 * @param string $value
	 * 
	 */
	public function importFromString($value) {
		if($value === null) return;
		$texts = explode(self::delim, "$value");
		if(!count($texts)) return;
		$languages = $this->wire()->languages;
		$this->setLanguageValue($languages->getDefault()->id, array_shift($texts));
		foreach($texts as $text) {
			list($langId, $text) = explode(':', $text, 2);
			$this->setLanguageValue((int) $langId, $text); 
		}
	}

	/**
	 * @return string
	 * 
	 */
	public function __toString() {
		$language = $this->wire()->user->language;
		$value = $this->getLanguageValue($language->id);
		if(!strlen($value) && !$language->isDefault()) {
			$value = $this->getLanguageValue($this->wire()->languages->getDefault());
		}
		return $value;
	}
}