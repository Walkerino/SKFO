<?php namespace ProcessWire;

require_once(__DIR__ . '/init.php');

/**
 * ProcessWire ProFields Combo Field
 *
 * Part of the ProFields package.
 * Please do not distribute.
 *
 * Copyright (C) 2023 by Ryan Cramer
 *
 * https://processwire.com
 *
 */

class ComboField extends Field {
	
	use ComboWireTrait, ComboWireDataTrait;
	
	/**
	 * @var ComboSettings|null
	 *
	 */
	protected $comboSettings = null;

	/**
	 * Subfield numbers that are moved in the page editor (both index and value are num)
	 * 
	 * @var array
	 * 
	 */
	protected $moves = array();

	/**
	 * Get database column name for given subfield name
	 * 
	 * @param string $name
	 * @return string
	 * 
	 */
	public function colName($name) {
		return $this->getComboSettings()->colName($name);
	}

	/**
	 * Return a key=value array of the data associated with the database table per Saveable interface
	 *
	 * #pw-internal
	 *
	 * @return array
	 *
	 */
	public function getTableData() {
		$data = $this->data; // save
		$comboSettingsData = $this->getComboSettings()->getArray();
		// data from ComboSettings must overwrite data from Field
		$this->data = array_merge($data, $comboSettingsData);
		$a = parent::getTableData();
		$this->data = $data; // restore
		return $a; 
	}

	/**
	 * @param ComboSettings $settings
	 * 
	 */
	public function setComboSettings(ComboSettings $settings) {
		$this->comboSettings = $settings;
	}

	/**
	 * @return ComboSettings
	 * 
	 */
	public function getComboSettings() {
		if($this->comboSettings === null) $this->comboSettings = $this->wire(new ComboSettings($this));
		return $this->comboSettings;
	}

	/**
	 * Get a ComboSubfield
	 *
	 * @param string|int|ComboSubfield $nameOrNum Name or index number
	 * @return ComboSubfield|ComboNullSubfield
	 * @since 0.0.7
	 *
	 */
	public function getSubfield($nameOrNum) {
		$settings = $this->getComboSettings();
		return $settings->getSubfield($nameOrNum);
	}

	/**
	 * Set property
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return $this|Field|WireData
	 * 
	 */
	public function set($key, $value) {
		if($this->getComboSettings()->isSetting($key)) {
			$this->getComboSettings()->set($key, $value);
			if(strpos($key, '_move')) {
				list($i, $move) = explode('_', $key, 2);
				$i = ltrim($i, 'i');
				if(ctype_digit($i) && $move === 'move') {
					$this->moves[(int) $i] = (int) $i;
					if(!$this->hookedProcessPageEdit) {
						$this->addHookAfter('ProcessPageEdit::buildFormContent', $this, 'hookProcessPageEdit');
						$this->hookedProcessPageEdit = true;
					}
				}
			}
		}
		return parent::set($key, $value);
	}

	/**
	 * Has this field already hooked ProcessPageEdit?
	 * 
	 * @var bool
	 * 
	 */
	protected $hookedProcessPageEdit = false;

	/**
	 * Hook to ProcessPageEdit::buildFormContent to move Combo subfield Inputfields
	 * 
	 * @param HookEvent $event
	 * @throws WireException
	 * 
	 */
	public function hookProcessPageEdit(HookEvent $event) {
		
		$input = $this->wire()->input;
		$config = $this->wire()->config;
		$process = $event->object; /** @var ProcessPageEdit $process */
		$id = (int) $input->post('id');
		$page = $process->getPage();
		$isX = !empty($_SERVER['HTTP_X_FIELDNAME']);
		
		if(empty($this->moves)) return;

		// if we are in a page-save request then do not move anything
		if(($id && $id === $page->id) || ($config->ajax && (count($_POST) || $isX))) return;
		
		/** @var InputfieldWrapper $inputfields */
		$form = $event->return;
		$comboSettings = $this->getComboSettings();
	
		/** @var InputfieldCombo $comboMainInputfield */
		$comboMainInputfield = $form->getChildByName($this->name); 
		if(!$comboMainInputfield) return;
		$comboInputfields = $comboMainInputfield->getInputfields();
		
		// reverse the order
		$a = array();
		foreach($comboInputfields as $comboInputfield) $a[] = $comboInputfield;
		$a = array_reverse($a);
		$numMoves = 0;
		
		foreach($a as $comboInputfield) {
			/** @var Inputfield $comboInputfield */
			$num = (int) $comboInputfield->wrapAttr('data-combo-num');
			if(!isset($this->moves[$num])) continue;
			$move = trim($comboSettings->getSubfieldProperty($num, 'move'));
			$before = strpos($move, '-') === 0;
			$move = trim($move, '-');
			if(empty($move)) continue;
			/** @var Inputfield $moveToInputfield */
			$moveToInputfield = $form->getChildByName($move);
			if(!$moveToInputfield) continue;
			/** @var InputfieldWrapper $parent */
			$parent = $moveToInputfield->getParent();
			if(!$parent) continue;
			if($comboInputfield instanceof InputfieldFile) {
				$comboMainInputfield->readyInputfieldFile($comboInputfield);
				$comboInputfield->addHookAfter('render', function(HookEvent $event) use($comboMainInputfield) {
					$comboInputfield = $event->object; /** @var InputfieldFile $comboInputfield */
					$out = $event->return;
					$comboMainInputfield->renderInputfieldFile($comboInputfield, $out);
					$event->return = $out;
				}); 
			}
			$comboInputfields->remove($comboInputfield);
			if($before) {
				$parent->insertBefore($comboInputfield, $moveToInputfield);
			} else {
				$parent->insertAfter($comboInputfield, $moveToInputfield); 
			}
			$comboInputfield->wrapAttr('data-combo-field', $comboMainInputfield->attr('id'));
			$comboInputfield->addClass('InputfieldComboMove', 'wrapClass');
			$numMoves++;
		}
		
		if($numMoves) {
			// alert the main combo Inputfield of changes to moved subfields
			$script = 'script';
			$form->appendMarkup .= "<$script>" . 
				"jQuery(document).on('changed', '.InputfieldComboMove', function() {" . 
					"jQuery('#wrap_' + jQuery(this).attr('data-combo-field') + ':not(.InputfieldStateChanged)')" . 
						".trigger('change', [ 'InputfieldComboMove' ]);" . 
				"});</$script>";
		}
	}
	
}