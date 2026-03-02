<?php namespace ProcessWire;

/**
 * ProcessWire ProMailer Subscribers Array
 *
 * Copyright 2023 by Ryan Cramer
 * This is a commercial module, do not distribute.
 * 
 */ 

class ProMailerSubscribersArray extends PaginatedArray {

	protected $findOptions = array();

	public function isValidItem($item) {
		return $item instanceof ProMailerSubscriber;
	}

	/**
	 * Get or set options used to find these subscribers
	 *
	 * @param array $options
	 * @return array
	 *
	 */
	public function findOptions(array $options = array()) {
		if(!empty($options)) $this->findOptions = $options;
		return $this->findOptions;
	}
	
	/**
	 * Return debug info for one item 
	 *
	 * @param mixed $item
	 * @return array
	 *
	 */
	public function debugInfoItem($item) {
		/** @var ProMailerSubscriber $item */
		$a = $item->getArray();
		return $a;
	}

}