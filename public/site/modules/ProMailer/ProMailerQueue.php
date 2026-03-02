<?php namespace ProcessWire;

/**
 * ProMailer queue item
 * 
 * Copyright 2023 by Ryan Cramer
 *
 * @property int $message_id
 * @property int $last_subscriber_id
 * @property int $next_subscriber_id
 * @property int $next_time
 * @property int $created
 * @property int $user_id
 * @property int $num
 * @property int $num_sent
 * @property int $num_fail
 * @property int $paused
 * 
 * // alias properties
 * @property ProMailerMessage|null $message Message being sent to
 * @property ProMailerSubscriber|null $nextSubscriber Next subscriber to send to
 * @property ProMailerSubscriber|null $lastSubscriber Last subscriber sent to
 *
 */
class ProMailerQueue extends ProMailerType {
	
	protected $message = null;
	protected $lastSubscriber = null;
	protected $nextSubscriber = null;
	
	public function getDefaultsArray() {
		return array(
			'message_id' => 0,
			'last_subscriber_id' => 0,
			'next_subscriber_id' => 0,
			'next_time' => 0, // time + throttleSeconds
			'created' => 0,
			'user_id' => 0,
			'num' => 0,
			'num_sent' => 0,
			'num_fail' => 0,
			'paused' => 0,
		);
	}
	
	public function set($key, $value) {
		if($key === 'message') return $this->setMessage($value);
		if($key === 'message_id' && $this->message) $this->message = null;
		if($key === 'nextSubscriber') return $this->setNextSubscriber($value);
		if($key === 'next_subscriber_id' && $this->nextSubscriber) $this->nextSubscriber = null;
		if($key === 'lastSubscriber') return $this->setLastSubscriber($value);
		if($key === 'last_subscriber_id' && $this->lastSubscriber) $this->lastSubscriber = null;
		return parent::set($key, $value);
	}
	
	public function get($key) {
		if($key === 'message') return $this->getMessage();
		if($key === 'nextSubscriber') return $this->getNextSubscriber();
		if($key === 'lastSubscriber') return $this->getLastSubscriber();
		return parent::get($key);
	}

	/**
	 * @return null|ProMailerMessage
	 * 
	 */
	public function getMessage() {
		if($this->message) return $this->message;
		$id = $this->message_id;
		$this->message = $id ? $this->manager->messages->get($id) : null;
		return $this->message;
	}

	/**
	 * @param ProMailerMessage $message
	 * @return ProMailerType|WireData
	 * 
	 */
	public function setMessage(ProMailerMessage $message) {
		$this->message = $message;
		return parent::set('message_id', $message->id);
	}

	/**
	 * @return null|ProMailerSubscriber
	 * 
	 */
	public function getNextSubscriber() {
		if($this->nextSubscriber) return $this->nextSubscriber;
		$id = $this->next_subscriber_id;
		$this->nextSubscriber = $id ? $this->manager->subscribers->getById($id) : null;
		return $this->nextSubscriber;
	}

	/**
	 * @return null|ProMailerSubscriber
	 *
	 */
	public function getLastSubscriber() {
		if($this->lastSubscriber) return $this->lastSubscriber;
		$id = $this->last_subscriber_id;
		$this->lastSubscriber = $id ? $this->manager->subscribers->getById($id) : null;
		return $this->lastSubscriber;
	}

	/**
	 * @param ProMailerSubscriber $subscriber
	 * @return ProMailerType|WireData
	 * 
	 */
	public function setNextSubscriber(ProMailerSubscriber $subscriber) {
		$this->nextSubscriber = $subscriber;
		return parent::set('next_subscriber_id', $subscriber->id);
	}

	/**
	 * @param ProMailerSubscriber $subscriber
	 * @return ProMailerType|WireData
	 *
	 */
	public function setLastSubscriber(ProMailerSubscriber $subscriber) {
		$this->lastSubscriber = $subscriber;
		return parent::set('last_subscriber_id', $subscriber->id);
	}
}