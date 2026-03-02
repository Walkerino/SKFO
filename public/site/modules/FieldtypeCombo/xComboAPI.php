<?php namespace ProcessWire;

/**
 * This class is not yet in use (work in progress)
 * 
 * @property array $subfieldsAdded
 * @property array $subfieldsDeleted
 * @property array $subfieldsRenamed
 *
 * @method void subfieldAdded(ComboSubfield $subfield)
 * @method void subfieldDeleted(ComboSubfield $subfield)
 * @method void subfieldRenamed(ComboSubfield $subfield, $oldName, $newName)
 * 
 */
class ComboAPI extends Wire {
	
	protected $comboSettings;
	protected $subfieldsAdded = array();
	protected $subfieldsDeleted = array();
	protected $subfieldsRenamed = array();
	
	public function __construct(ComboSettings $settings) {
		$this->comboSettings = $settings;
		parent::__construct();
	}

	/**
	 * @return ComboSettings
	 * 
	 */
	public function comboSettings() {
		return $this->comboSettings;
	}
	
	public function __get($key) {
		if($key === 'subfieldsAdded') return $this->subfieldsAdded;
		if($key === 'subfieldsDeleted') return $this->subfieldsDeleted;
		if($key === 'subfieldsRenamed') return $this->subfieldsRenamed; 
		if($key === 'comboSettings') return $this->comboSettings;
		return parent::__get($key);
	}

	/**
	 * Get a Combo Subfield
	 * 
	 * @param string|int|ComboSubfield $nameOrNum
	 * @return ComboSubfield|ComboNullSubfield
	 * 
	 */
	public function getSubfield($nameOrNum) {
		return $this->comboSettings->getSubfield($nameOrNum); 
	}
	
	/**
	 * Does given subfield name (or num) exist?
	 *
	 * @param string|int|ComboSubfield $nameOrNum
	 * @return bool
	 *
	 */
	public function subfieldExists($nameOrNum) {
		return $this->comboSettings->subfieldExists($nameOrNum);
	}

	/**
	 * Create a new ComboSubfield instance
	 * 
	 * @param string $type
	 * @param string $name
	 * @param string $label
	 * @return ComboSubfield
	 * 
	 */
	public function newSubfield($type = '', $name = '', $label = '') {
		$type = ucfirst($type);
		if(strpos($type, 'Inputfield') === 0) $type = str_replace('Inputfield', '', $type);
		$subfield = new ComboSubfield();
		$this->wire($subfield);
		if($name) $subfield->name = $name;
		if($type) $subfield->type = $type;
		if($label) $subfield->label = $label;
		return $subfield;
	}

	/**
	 * Add a Combo Subfield
	 * 
	 * ~~~~~
	 * $field = $fields->get('your_combo_field');
	 * $subfield = $field->newSubfield('text', 'first_name', 'First name'); 
	 * $subfield->someSetting = 'some value'; // any other settings…
	 * $subfield->required = true; // …for example
	 * try {
	 *   $field->addSubfield($subfield);
	 *   $field->save();
	 * } catch(WireException $e) {
	 *   // failed to add subfield
	 * }
	 * ~~~~~
	 * 
	 * @param ComboSubfield $subfield
	 * @return bool
	 * @throws WireException
	 * 
	 */
	public function addSubfield(ComboSubfield $subfield) {
		$name = $subfield->name;
		$type = $subfield->type;
		
		if(!strlen($name)) throw new WireException('Combo subfield requires a "name"');
		if(!strlen($type)) throw new WireException('Combo subfield requires a "type"');

		$num = $this->comboSettings->num($name);
		if($num) throw new WireException("There is already a subfield with name: $name");
		
		$subfield->num = $this->comboSettings->findMaxQty();
		$subfield->ok = 1;
		$prefix = $this->comboSettings->prefix($num);
		$data = $subfield->getArray();
		
		foreach($data as $key => $value) {
			$key = $prefix . $key;
			$this->comboSettings->set($key, $value);
		}
		
		$this->subfieldAdded($subfield);
		
		return true;
	}

	/**
	 * Modify a Subfield property 
	 * 
	 * @param string|int|ComboSubfield $subfield
	 * @param string $property
	 * @param mixed $value
	 * @return ComboSubfield
	 * 
	 */
	public function modifySubfield($subfield, $property, $value) {
		$subfield = $this->getSubfield($subfield);
		$subfield->set($property, $value);
		if($subfield->num) {
			$prefix = $this->comboSettings->prefix($subfield->num);
			$this->comboSettings->set($prefix . $property, $value);
		}
		return $subfield;	
	}

	/**
	 * Delete a Combo Subfield
	 * 
	 * ~~~~~
	 * $field = $fields->get('your_combo_field');
	 * $field->deleteSubfield('first_name');
	 * $field->save();
	 * ~~~~~
	 * 
	 * @param ComboSubfield|string|int $subfield
	 * @return bool
	 * 
	 */
	public function deleteSubfield($subfield) {
		$subfield = $this->getSubfield($subfield);
		$num = $subfield->num;
		if(!$subfield->num || !$this->subfieldExists($num)) return false;
		$prefix = $this->comboSettings->prefix($num);
		$this->comboSettings->set($prefix . 'type', 'DELETE');
		$this->comboSettings->clean();
		$this->subfieldDeleted($subfield);
		return true;
	}

	/**
	 * @param ComboSubfield|string|int $subfield
	 * @param string $newName
	 * @return bool|ComboSubfield
	 * @throws WireException
	 * 
	 */
	public function renameSubfield($subfield, $newName) {
	
		$_subfield = $subfield;
		$subfield = $this->comboSettings->getSubfield($subfield);
		$oldName = $subfield->name;
		$num = $subfield->num;
	
		if(!$subfield || !$subfield->num) {
			throw new WireException("Cannot find subfield '$_subfield' to rename to: $newName");
		}
		
		if($subfield->name === $newName) return $subfield;
		
		if($this->subfieldExists($newName)) {
			throw new WireException("Cannot rename to '$newName' because it already exists"); 
		}
		
		if(!$this->comboSettings->isReservedName($newName)) {
			throw new WireException("Cannot rename to '$newName' because it is a reserved word"); 
		}
	
		$subfield->set('name', $newName);
		$subfield->setQuietly('_namePrevious', $oldName);
		$prefix = $this->comboSettings->prefix($num);
		$this->comboSettings->set($prefix . 'name', $newName);
		$this->subfieldRenamed($subfield, $oldName, $newName); 
		
		return $subfield;
	}
	
	public function resetSubfieldQueues() {
		$this->subfieldsAdded = array();
		$this->subfieldsDeleted = array();
		$this->subfieldsRenamed = array();
	}

	/**
	 * Hookable method called when subfield has been added
	 *
	 * @param ComboSubfield $subfield
	 *
	 */
	protected function ___subfieldAdded($subfield) {
		$this->subfieldsAdded[$subfield->name] = $subfield;
	}
	
	/**
	 * Hookable method called when subfield has been deleted
	 *
	 * @param ComboSubfield $subfield
	 *
	 */
	protected function ___subfieldDeleted($subfield) {
		$this->subfieldsDeleted[$subfield->name] = $subfield;
	}

	/**
	 * Hookable method called when subfield has been renamed
	 * 
	 * Note: previous name can be retrieved from $subfield->get('_namePrevious');
	 *
	 * @param ComboSubfield $subfield
	 *
	 */
	protected function ___subfieldRenamed($subfield) {
		$this->subfieldsRenamed[$subfield->name] = $subfield;
	}

}