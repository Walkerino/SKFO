<?php namespace ProcessWire;

/**
 * ProMailer subscriber list
 * 
 * Copyright 2023 by Ryan Cramer
 * 
 * @property int $id
 * @property string $type Either ProMailer::listTypeRegular or ProMailer::listTypePages 
 * @property string $title
 * @property array $custom Additional custom data to store with this list in associative array
 * @property array $fields Custom field definitions as ['name' => 'sanitizer'], only used by regular lists
 * @property bool $closed List closed for new subscribers? only used by regular lists
 * @property string $selector Selector string, only used by pages lists
 * @property string $unsub_field Field name that has non-empty value if user is unsubscribed, only used by pages lists
 * @property string $unsub_email Email address for optional list-unsubscribe header
 * @property string $unsub_list_id Move unsubscribers to this list ID rather than deleting them completely.
 * @property ProMailerLists $manager
 *
 */
class ProMailerList extends ProMailerType {
	
	public function getDefaultsArray() {
		return array(
			'id' => 0,
			'type' => '',
			'title' => '',
			'custom' => array(),
			'fields' => array(), // used only by regular lists
			'closed' => false, // used only by regular lists
			'selector' => '', // used only by pages lists
			'unsub_field' => '', // used only by pages lists
			'unsub_list_id' => 0, // used only by regular lists
		);
	}
	
	public function set($key, $value) {
		if($key === 'type') {
			$value = (string) $value;
			if($value !== ProMailer::listTypeRegular && $value !== ProMailer::listTypePages) {
				throw new WireException("Invalid list type: $value"); 
			}
		} else if($key === 'fields') {
			$value = $this->listFields()->fieldsArray($value);
		}
		return parent::set($key, $value);
	}
	
	/**
	 * Get newline separated string of custom field definitions for this list
	 * 
	 * @return string
	 * 
	 */
	public function fieldsString() {
		return $this->listFields()->fieldsArrayToString($this->get('fields'));
	}

	/**
	 * Return array of custom field definitions for this list
	 * 
	 * @return array
	 * 
	 */
	public function fieldsArray() {
		return $this->fields;
	}

	/**
	 * Return number of subscribers in this list
	 * 
	 * @param bool $confirmed Return only opt-in/confirmed subscribers? (default=falses)
	 * @return int
	 * 
	 */
	public function numSubscribers($confirmed = false) {
		return $this->manager->subscribers->count(array('list' => $this, 'confirmed' => $confirmed)); 
	}

	/**
	 * @return ProMailerListFields
	 * 
	 */
	protected function listFields() {
		return ProMailerLists::listFields(); 
	}

}