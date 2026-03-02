<?php namespace ProcessWire;

/**
 * @method ComboSettings getComboSettings()
 * 
 */
trait ComboWireTrait {

	public function isChanged($what = '') {
		return parent::isChanged($what) || $this->getComboSettings()->isChanged($what);
	}

	public function ___changed($what, $old = null, $new = null) {
		parent::___changed($what, $old, $new);
		if($this->getComboSettings()->isSetting($what)) $this->getComboSettings()->changed($what, $old, $new);
	}

	public function trackChange($what, $old = null, $new = null) {
		if($this->getComboSettings()->isSetting($what)) $this->getComboSettings()->trackChange($what, $old, $new);
		return parent::trackChange($what, $old, $new);
	}

	public function untrackChange($what) {
		if($this->getComboSettings()->isSetting($what)) $this->getComboSettings()->untrackChange($what);
		return parent::untrackChange($what);
	}

	public function setTrackChanges($trackChanges = true) {
		$this->getComboSettings()->setTrackChanges($trackChanges);
		return parent::setTrackChanges($trackChanges);
	}

	public function resetTrackChanges($trackChanges = true) {
		$this->getComboSettings()->resetTrackChanges($trackChanges);
		return parent::resetTrackChanges($trackChanges);
	}
}