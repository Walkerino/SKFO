<?php namespace ProcessWire;

/**
 * ProMailer Subscriber
 * 
 * Copyright 2023 by Ryan Cramer
 * 
 * @property int $id
 * @property int $list_id
 * @property string $email
 * @property string $type Either ProMailer::listTypeRegular or ProMailer::listTypePages
 * @property Page|null $page Pages associated with subscriber if type is ProMailer::listTypePages
 * @property int $flags
 * @property array $custom
 * @property string $code Confirmation code for this subscriber, used for confirming subscribe/unsubscribe
 * @property int $confirmed Is subscriber opt-in confirmed? Contains timestamp of confirmation time when confirmed
 * @property int $created Timestamp of when this subscriber was created
 * @property int $num_sent Number of messages sent to this subscriber
 * @property int $num_bounce Number of bounces for this subscriber
 * @property array $confData Confirmation email data (if not yet confirmed)
 * @property ProMailerSubscribers $manager
 * 
 * // aliased properties
 * @property ProMailerList|null $list List that this subscriber belongs to
 * 
 */
class ProMailerSubscriber extends ProMailerType {

	/**
	 * Flag that indicates unsubscriber
	 * 
	 */
	const flagsUnsub = 2048;
	
	public function getDefaultsArray() {
		return array(
			'type' => '', // blank or 'pages'
			'page' => null,
			'id' => 0,
			'list_id' => 0,
			'flags' => 0,
			'email' => '',
			'custom' => array(),
			'code' => '',
			'confirmed' => 0,
			'created' => 0,
			'num_sent' => 0,
			'num_bounce' => 0
		);
	}
	
	protected $list = null;
	
	public function get($key) {
		if($key === 'list') return $this->getList();
		return parent::get($key);
	}

	public function set($key, $value) {
		if("$key" === '0') return $this; // unnecessary JSON of custom fields
		if($key === 'list') return $this->setList($value);
		if($key === 'list_id' && $this->list) $this->list = null;
		if($key === 'email' && strlen($value)) $value = $this->wire()->sanitizer->email($value);
		return parent::set($key, $value);
	}

	/**
	 * Get gravatar <img> tag for subscriber
	 * 
	 * @param int $size Square pixel size (default=80)
	 * @param string $attr Any additional <img> tag attributes to add
	 * @return string
	 * 
	 */
	public function gravatar($size = 80, $attr = '') {
		$useGravatar = $this->manager->promailer->useGravatar;
		if($useGravatar === ProMailer::useGravatarNone) return '';
		if(strpos($attr, 'alt=') === false) $attr = trim("alt='' $attr");
		$url = "https://www.gravatar.com/avatar/" . md5(strtolower(trim($this->email))) . "?s=" . $size . "&d=$useGravatar";
		$img = "<img src='$url' $attr />";
		return $img;
	}

	/**
	 * Get the list this subscriber belongs to
	 * 
	 * @return ProMailerList|null
	 * 
	 */
	public function getList() {
		if($this->list) return $this->list;
		$list_id = $this->get('list_id');
		$this->list = $list_id ? $this->manager->lists->get($list_id) : null;
		return $this->list;
	}

	/**
	 * Set the list for this subscriber
	 * 
	 * @param ProMailerList $list
	 * @return $this
	 * @throws WireException
	 * 
	 */
	public function setList(ProMailerList $list) {
		parent::set('list_id', (int) $list->id);
		$this->list = $list;
		return $this;
	}

	/**
	 * Get custom field value
	 *
	 * @param string $name Property name or omit to get all
	 * @return string|int|mixed|bool|null
	 *
	 */
	public function getCustom($name = '') {
		$custom = parent::get('custom');
		if(!is_array($custom)) $custom = array();
		if($name === '') return $custom;
		return isset($custom[$name]) ? $custom[$name] : null;
	}

	/**
	 * Set value for custom field while also sanitizing it 
	 * 
	 * @param string $name
	 * @param mixed $value
	 * @param bool $saveNow Save to DB now? (default=false)
	 * @return bool Returns true if value was a custom field and modified, false if not
	 * 
	 */
	public function setCustom($name, $value, $saveNow = false) {
		
		$sanitizer = $this->wire()->sanitizer;
		
		$list = $this->getList();
		$fields = $list->fields;
		
		if(!isset($fields[$name])) {
			$this->set($name, $value);
			return false;
		}
		
		$field = $fields[$name];
		$type = $field['type'];
		$custom = $this->getCustom();
	
		try {
			if($type === 'option' || $type === 'options') {
				$value = $sanitizer->$type($value, array_keys($field['options']));
			} else {
				$value = $sanitizer->$type($value);
			}
		} catch(\Exception $e) {
			$value = $sanitizer->text($value);
		}
		
		if((!isset($custom[$name]) && !empty($value)) || (isset($custom[$name]) && $custom[$name] != $value)) {
			$custom[$name] = $value;
			$this->set('custom', $custom);
			if($saveNow) {
				$this->saveCustom();
			} else {
				$this->trackChange($name);
				$this->trackChange('custom');
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Save custom field data now
	 * 
	 * @return bool
	 * 
	 */
	public function saveCustom() {
		return $this->manager->saveCustom($this);
	}
	
	/**
	 * Get or set custom field values
	 *
	 * @param string $name Name of custom field to get or set, or omit to get all
	 * @param null|mixed $value When setting only, value of field to set
	 * @param bool $saveNow When setting only, save the changes now? (default=false)
	 * @return bool|int|mixed|null|string
	 *
	 */
	public function custom($name = '', $value = null, $saveNow = false) {
		if($value === null || $name === '') {
			return $this->getCustom($name);
		} else {
			return $this->setCustom($name, $value, $saveNow);
		}
	}
	
	/**
	 * Get or set confirmation email data
	 *
	 * @param string|bool Specify name of property to get or set, or boolean true to set all in $value argument
	 * @param array|string|int|null $value Omit to get or specify value to set. Specify array when setting all (and name argument is true).
	 * @param bool $saveNow When setting, save changes now?
	 * @return array|bool|int|string
	 *
	 */
	public function confData($name = '', $value = null, $saveNow = false) {
		if($name !== true && !is_string($name)) throw new WireException('Invalid confData $name argument');
		if($name === true && is_array($value)) { // set
			// set all from array in $value argument
			$result = $this->setConfData($value, $saveNow); 
		} else if($value !== null) {
			// set one
			$confData = $this->getConfData();
			$confData[$name] = $value;
			$result = $this->setConfData($confData, $saveNow);
		} else {
			$result = $this->getConfData($name);
		}
		return $result;
	}

	/**
	 * Get all confirmation email data
	 *
	 * @param string $name Optionally specify name of property to get, rather than all
	 * @return array|int|string
	 *
	 */
	public function getConfData($name = '') {
		
		$confData = array(
			'subject' => '',
			'body' => '',
			'bodyHTML' => '',
			'fromEmail' => '',
			'fromName' => '',
			'pageID' => 0,
			'langID' => 0,
			'sent' => 0,
			'result' => 0,
		);
		
		$custom = $this->getCustom();
		if(!is_array($custom) || !isset($custom['confData'])) return $confData;
		
		$confData = array_merge($confData, $custom['confData']);
		if($name !== '') return (is_string($name) && isset($confData[$name]) ? $confData[$name] : null);
		return $confData;
	}

	/**
	 * Set confirmation email data
	 * 
	 * @param array $confData Confirmation email data or blank array to clear
	 * @param bool $saveNow Save now? (default=false)
	 * @return bool
	 * 
	 */
	public function setConfData(array $confData, $saveNow = false) {
		
		if(!empty($confData)) {
			if(empty($confData['subject'])) {
				throw new WireException('confData[subject] is required in setConfData()');
			}
			$confDataPrev = $this->getConfData();
			$confData = array_merge($confDataPrev, $confData);
			if($confData == $confDataPrev) return false; // exit early because no change
			if(!$saveNow) $this->trackChange('custom');
		}
		
		$custom = $this->getCustom();
		
		if(empty($confData)) {
			unset($custom['confData']); 
		} else {
			$custom['confData'] = $confData;
		}	
		
		parent::set('custom', $custom);
		
		return ($saveNow && $this->id ? $this->saveCustom() : true);
	}

	/**
	 * Allow re-send of confirmation email?
	 * 
	 * @return bool
	 * 
	 */
	public function allowResend() {
		if($this->confirmed) return false; // already confirmed
		$confData = $this->getConfData();
		if(empty($confData) || empty($confData['subject'])) return false; // no data to do resend
		return true;
	}

	/**
	 * Save log entry for subscriber
	 * 
	 * @param string $str
	 * @param bool $saveNow
	 * @return bool
	 * 
	public function saveLog($str, $saveNow = true) {
		$str = $this->wire('sanitizer')->text($str, array('maxLength' => 512)); 
		if(empty($str)) return false;
		$str = date('Y-m-d H:i:s') . ' ' . $str;
		$custom = $this->getCustom();
		$log = isset($custom['slog']) ? $custom['slog'] : array();
		if(!is_array($log)) $log = array();
		$log[] = $str;
		$custom['slog'] = $log;
		parent::set('custom', $custom);
		if($saveNow && $this->id) $this->manager->saveCustom($this);
	}
	 */

	/**
	 * Get all log entries for subscriber
	 * 
	 * @param bool $getString
	 * @return array|string
	 * 
	public function getLog($getString = false) {
		$custom = $this->getCustom();
		$log = isset($custom['slog']) ? $custom['slog'] : array();
		if(!is_array($log)) $log = array();
		if($getString) $log = implode("\n", $log);
		return $log;
	}
	 */
	

}