<?php namespace ProcessWire;

/**
 * @method ComboSettings getComboSettings()
 *
 */
trait ComboWireDataTrait {
	
	public function get($key) {
		if($this->getComboSettings()->isSetting($key)) return $this->getComboSettings()->get($key);
		return parent::get($key);
	}

	public function set($key, $value) {
		if($this->getComboSettings()->isSetting($key)) {
			$this->getComboSettings()->set($key, $value);
		} else {
			parent::set($key, $value);
		}
		return $this;
	}

	public function getArray() {
		return array_merge(parent::getArray(), $this->getComboSettings()->getArray());
	}

	public function remove($key) {
		if($this->getComboSettings()->isSetting($key)) $this->getComboSettings()->remove($key);
		return parent::remove($key);
	}

	public function getIterator() {
		return new \ArrayObject($this->getArray());
	}

	public function has($key) {
		return parent::has($key) || $this->getComboSettings()->has($key);
	}

	public function __isset($key) {
		return parent::__isset($key) || $this->getComboSettings()->__isset($key);
	}

}

