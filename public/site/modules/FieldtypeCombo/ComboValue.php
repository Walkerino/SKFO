<?php namespace ProcessWire;

/**
 * ProcessWire ProFields Combo Value
 *
 * Part of the ProFields package.
 * Please do not distribute.
 *
 * Copyright (C) 2024 by Ryan Cramer
 *
 * https://processwire.com
 *
 * @property ComboSettings $comboSettings 
 * @method string render(array $options = [])
 *
 */

class ComboValue extends WireData {

	/**
	 * @var ComboSettings
	 * 
	 */
	protected $comboSettings;

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
	 * Construct
	 *
	 * @param ComboSettings|ComboField|InputfieldCombo
	 * @param Page $page
	 * @param ComboField $field
	 * @throws WireException
	 * 
	 */
	public function __construct($comboSettings, Page $page = null, Field $field = null) {
		if($page) $this->page = $page;
		if($field) $this->field = $field;
		if($comboSettings instanceof ComboSettings) {
			$this->comboSettings = $comboSettings;
		} else if($comboSettings instanceof ComboField || $comboSettings instanceof InputfieldCombo) {
			$this->comboSettings = $comboSettings->getComboSettings();
		} else {
			throw new WireException("ComboValue construct requires ComboSettings, ComboField or InputfieldCombo");
		}
		$comboSettings->wire($this);
		parent::__construct();
	}
	
	/**
	 * Get property
	 * 
	 * @param object|string $key
	 * @return mixed
	 * 
	 */
	public function get($key) {
		if($key === 'comboSettings') return $this->comboSettings;
		$value = parent::get($key);
		return $value;
	}

	/**
	 * Set property value
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return ComboValue
	 * 
	 */
	public function set($key, $value) {
		$subfield = $this->comboSettings->getSubfield($key);
		if($subfield) {
			if($subfield->type === 'Datetime' && ctype_digit("$value")) {
				$value = date('Y-m-d H:i:s', $value);
			}
		}
		return parent::set($key, $value);
	}

	/**
	 * Return all subfields
	 * 
	 * @return ComboSubfield[]
	 * 
	 */
	public function getSubfields() {
		return $this->comboSettings->getSubfields();
	}

	/**
	 * Get settings for given subfield 
	 * 
	 * @param string $name Name of subfield to get settings for
	 * @param bool $getArray Get array rather than ComboSubfield object?
	 * @return array|ComboSubfield
	 * 
	 */
	public function getSubfieldSettings($name, $getArray = false) {
		return $this->comboSettings->getSubfieldData($name, array('getObject' => !$getArray));
	}
	
	/**
	 * Get localized label for given subfield name
	 * 
	 * @param string $name
	 * @param string $type One of 'label', 'description' or 'notes' (default='label')
	 * @return string
	 * 
	 */
	public function getLabel($name, $type = 'label') {
		$subfield = $this->getSubfieldSettings($name);
		if(!$subfield) return '';
		if(in_array($type, array('label', 'description', 'notes'))) return (string) $subfield->$type();
		return (string) $subfield->get($type);
	}

	/**
	 * Get localized description for given subfield name
	 * 
	 * @param string $name
	 * @return string
	 * 
	 */
	public function getDescription($name) {
		return $this->getLabel($name, 'description'); 
	}

	/**
	 * Get localized notes for given subfield name
	 *
	 * @param string $name
	 * @return string
	 *
	 */
	public function getNotes($name) {
		return $this->getLabel($name, 'notes'); 
	}

	/**
	 * Get field (if applicable)
	 * 
	 * @return ComboField|null
	 * 
	 */
	public function getField() {
		if(!$this->field) $this->field = $this->comboSettings->getField();
		return $this->field instanceof ComboField ? $this->field : null;
	}

	/**
	 * @param ComboField $field
	 * 
	 */
	public function setField(Field $field) {
		$this->field = $field;
	}

	/**
	 * @return Page|null
	 * 
	 */
	public function getPage() {
		return $this->page;
	}

	/**
	 * @param Page $page
	 * 
	 */
	public function setPage(Page $page) {
		$this->page = $page;
	}

	/**
	 * Get any filenames associated with this ComboValue
	 * 
	 * @param bool $has Specify true to only return true or false as to whether there are any files
	 * @return array|bool
	 * @since 0.1.3
	 * 
	 */
	public function getFiles($has = false) {
		$filenames = array();
		$types = array('file', 'image'); 
		$hasFiles = false;
		foreach($this->getSubfields() as $subfield) {
			if(!in_array(strtolower($subfield->type), $types)) continue;
			$value = $this->get($subfield->name);
			if(!$value instanceof Pagefile && !$value instanceof Pagefiles) continue;
			if($has) {
				$hasFiles = $value instanceof Pagefile || $value->count() > 0;
				if($hasFiles) break;
			} else if(method_exists($value, 'getFiles')) {
				// PW 3.0.233+
				$filenames = array_merge($filenames, $value->getFiles());
			} else {
				// not supported prior to PW 3.0.233
			}
		}
		return $has ? $hasFiles : $filenames;	
	}

	/**
	 * Return MarkupAdminDataTable of ComboValue
	 * 
	 * @param array $options
	 *  - `verbose` (bool): Return verbose table for debugging purposes? (default=false)
	 * @return MarkupAdminDataTable
	 * 
	 */
	public function table(array $options = array()) {
		
		$sanitizer = $this->wire()->sanitizer;
		$verbose = isset($options['verbose']) ? $options['verbose'] : false;
		
		/** @var MarkupAdminDataTable $table */
		$table = $this->wire()->modules->get('MarkupAdminDataTable');
		$table->setEncodeEntities(false);
		if($verbose) {
			$table->headerRow(array(
				$this->_('Name'),
				$this->_('Label'),
				$this->_('Input Type'),
				$this->_('Value Type'),
				$this->_('Value'),
			));
		} else {
			$table->headerRow(array(
				$this->_('Label'),
				$this->_('Value'),
			));
		}
		
		$subfields = $this->comboSettings->getSubfields();
		
		foreach($subfields as $name => $subfield) {
			$value = $this->get($name);
			
			if(is_string($value)) {
				if($subfield->type !== 'CKEditor' && $subfield->type !== 'TinyMCE') {
					$value = $sanitizer->entities1($value);
				}
				
			} else if(is_array($value)) {
				$s = '';
				$selectType = $this->comboSettings->isSelectType($subfield->type); 
				foreach($value as $k => $v) {
					$s .= "<li>"; 
					if(!$selectType) $s .= $sanitizer->entities1($k) . ": "; 
					$s .= $sanitizer->entities1("$v"); 
					$s .= "</li>";
				}
				$value = strlen($s) ? "<ul>$s</ul>" : "";
				
			} else if($value instanceof Page) {
				$value = $value->getFormatted('title|path');
				
			} else if($value instanceof PageArray) {
				$s = '';
				foreach($value as /* $k => */ $p) {
					$s .= "<li>" . $p->getFormatted('title|path') . "</li>";
				}
				$value = strlen($s) ? "<ul>$s</ul>" : "";
				
			} else if($value instanceof Pagefiles) {
				$s = '';
				foreach($value as $pagefile) {
					/** @var Pagefile $pagefile */
					$d = $sanitizer->entities1($pagefile->description);
					if($pagefile instanceof Pageimage) {
						$v = "<img src='$pagefile->url' alt='$d' />";
					} else {
						$v = $pagefile->basename;
					}
					$s .= 
						"<li>" . 
							"<a href='$pagefile->url'>$v</a>" . ($d ? "<br />$d" : '') .
						"</li>";
				}
				$value = strlen($s) ? "<ul>$s</ul>" : "";
				
			} else if($value instanceof Pagefile) {
				$d = $sanitizer->entities1($value->description);
				$v = $value instanceof Pageimage ? "<img src='$value->url' alt='$d' />" : $value->basename;
				$value = "<a href='$value->url'>$v</a>" .  ($d ? "<br />$d" : '');
				
			} else {
				$value = $sanitizer->entities1($value);
			}
			
			if($verbose) {
				$table->row(array(
					$name,
					$sanitizer->entities1($subfield->getLabel()),
					$subfield->type,
					$this->comboSettings->getSubfieldValueType($subfield),
					$value
				));
			} else {
				$table->row(array(
					$sanitizer->entities1($subfield->getLabel()),
					$value
				));
			}
		}
		
		return $table;
	}

	/**
	 * Render table of label and value for each Combo subfield
	 * 
	 * @param array $options
	 * @return string
	 * 
	 */
	public function ___render(array $options = array()) {
		$table = $this->table($options);
		return $table->render();
	}

	/**
	 * Render verbose table of label, value and other info for each Combo subfield
	 *
	 * @param array $options
	 * @return string
	 *
	 */
	public function renderVerbose(array $options = array()) {
		$options['verbose'] = true;
		return $this->render($options);
	}

}

/**
 * Represents a formatted ComboValue
 *
 */
class ComboValueFormatted extends ComboValue {
	
	/**
	 * Get formatted label for given subfield name (localized)
	 *
	 * @param string $name
	 * @param string $type One of 'label', 'description' or 'notes' (default='label')
	 * @return string
	 *
	 */
	public function getLabel($name, $type = 'label') {
		return $this->wire()->sanitizer->entities(parent::getLabel($name, $type));
	}
}