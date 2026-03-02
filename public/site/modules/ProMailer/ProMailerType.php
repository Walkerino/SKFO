<?php namespace ProcessWire;

/**
 * @property int $id
 * 
 */
abstract class ProMailerType extends WireData {
	
	/**
	 * @var ProMailerTypeManager
	 *
	 */
	protected $manager;

	/**
	 * @var array
	 * 
	 */
	protected $defaults = array();

	/**
	 * Construct
	 *
	 * @param array $data Array of data to optionally populate to this instance
	 * @throws WireException
	 * 
	 */
	public function __construct(array $data = array()) {
		$this->set('id', 0);
		$this->defaults = $this->getDefaultsArray();
		$data = array_merge($this->defaults, $data);
		$this->setArray($data);
		parent::__construct();
		$this->resetTrackChanges();
	}

	/**
	 * Set the manager for this type
	 * 
	 * @param ProMailerTypeManager $manager
	 * 
	 */
	public function setManager(ProMailerTypeManager $manager) {
		$this->manager = $manager;
	}

	/**
	 * Get property value
	 * 
	 * @param string $key
	 * @return string|int|mixed|null|ProMailerTypeManager
	 * 
	 */
	public function get($key) {
		if($key === 'manager') return $this->manager;
		return parent::get($key);
	}

	/**
	 * Set value 
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return WireData|ProMailerType
	 * @throws WireException If given an invalid value type
	 * 
	 */
	public function set($key, $value) {
		if(isset($this->defaults[$key])) {
			$default = $this->defaults[$key];
			if(is_int($default)) {
				$value = (int) "$value";
			} else if(is_array($default)) {
				if(!is_array($value)) throw new WireException("Value for '$key' must be an array");
			} else if(is_string($default)) {
				$value = (string) $value;
			} else if(is_bool($default)) {
				$value = (bool) $value;
			} 
		}
		return parent::set($key, $value);
	}
	
	/**
	 * Return array of default values
	 * 
	 * @return array
	 * 
	 */
	abstract public function getDefaultsArray();

	/*
	public function getPropertyAlias($property) {
		$aliases = $this->getPropertyAliases();
		if(isset($aliases[$property])) {
			// property in camelCase format
			return $aliases[$property]; // messageId => message_id
		}
		if(strtolower($property) === $property) {
			// property in underscore_format
			$key = array_search($property, $aliases); 
			if($key !== false) return $key; // message_id => messageId
		}
		return ''; // no alias	
	}
	
	public function getPropertyAliases() {
		if($this->propertyAliases !== null) return $this->propertyAliases;
		$aliases = array();
		foreach($this->getDefaultsArray() as $key => $default) {
			if(strpos($key, '_') === false) continue;
			$parts = explode('_', $key);
			$alias = array_shift($parts);
			foreach($parts as $part) $alias .= ucfirst($part);
			$aliases[$alias] = $key;
		}
		$this->propertyAliases = $aliases;
		return $aliases;
	}
	*/

	/**
	 * String by default resolves to the item’s id property
	 * 
	 * @return string
	 * 
	 */
	public function __toString() {
		return (string) $this->id;
	}
}