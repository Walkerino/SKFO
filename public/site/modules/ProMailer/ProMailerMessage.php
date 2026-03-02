<?php namespace ProcessWire;

/**
 * ProMailer Message
 * 
 * Copyright 2023 by Ryan Cramer
 * 
 * @property int $id
 * @property string $title
 * @property string $subject
 * @property int $flags Either 0 or ProMailer::messageFlagsQueueSend
 * @property string $from_name Optional from name
 * @property string $from_email From email address
 * @property string $reply_email Optional reply-to email address (if different from from_email)
 * @property int $body_source
 * @property int $body_type
 * @property int $body_page
 * @property string $body_html
 * @property string $body_text
 * @property int $list_id
 * @property int $subscriber_id
 * @property int $created
 * @property int $last_sent
 * @property string $mailer
 * @property array $custom
 * @property null|int $throttle_secs
 * @property null|int $send_qty
 *
 * // aliased properties 
 * @property ProMailerList|null $list List this message sends to
 * @property ProMailerSubscriber|null $subscriber Next subscriber this message sends to
 * 
 * @todo reply-to email address setting
 *
 */
class ProMailerMessage extends ProMailerType {

	/**
	 * Allowed values for body_source
	 * 
	 * @var array
	 * 
	 */
	protected $bodySources = array(
		ProMailer::bodySourceInput,
		ProMailer::bodySourcePage,
		ProMailer::bodySourceHref,
	);

	/**
	 * Allowed values for body_type
	 * 
	 * @var array
	 * 
	 */
	protected $bodyTypes = array(
		ProMailer::bodyTypeHtml,
		ProMailer::bodyTypeText,
		ProMailer::bodyTypeBoth,
	);

	/**
	 * @var ProMailerList|null
	 * 
	 */
	protected $list = null;

	/**
	 * @var ProMailerSubscriber|null
	 * 
	 */
	protected $subscriber = null;

	/**
	 * Get default values
	 * 
	 * @return array
	 * 
	 */
	public function getDefaultsArray() {
		return array(
			'id' => 0,
			'title' => '',
			'subject' => '',
			'flags' => 0,
			'from_name' => '',
			'from_email' => '',
			'body_source' => ProMailer::bodySourceInput,
			'body_type' => ProMailer::bodyTypeBoth,
			'body_page' => 0,
			'body_html' => '',
			'body_text' => '',
			'body_href_html' => '',
			'body_href_text' => '',
			'list_id' => 0,
			'subscriber_id' => 0,
			'created' => 0,
			'last_sent' => 0,
			'mailer' => '',
			'custom' => array(),
			'throttle_secs' => null, // null=use default
			'send_qty' => null,
		);
	}
	
	public function get($key) {
		if($key === 'list') return $this->getList();
		if($key === 'subscriber') return $this->getSubscriber();
		return parent::get($key);
	}
	
	public function set($key, $value) {
		if($key === 'body_source' && !in_array((int) $value, $this->bodySources)) {
			throw new WireException("Invalid value for body_source: $value"); 
		} else if($key === 'body_type' && !in_array((int) $value, $this->bodyTypes)) {
			throw new WireException("Invalid value for body_type: $value"); 
		} else if($key === 'list') {
			return $this->setList($value);
		} else if($key === 'list_id' && $this->list) {
			$this->list = null;
		} else if($key === 'subscriber') {
			return $this->setSubscriber($value);
		} else if($key === 'subscriber_id' && $this->subscriber) {
			$this->subscriber = null;
		}
		return parent::set($key, $value);
	}
	
	/**
	 * Get the list this message will send to
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
	 * Set the list this message will send to
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
	 * Get the subscriber this message will send to
	 *
	 * @return ProMailerSubscriber|null
	 *
	 */
	public function getSubscriber() {
		if($this->subscriber) return $this->subscriber;
		$subscriber_id = $this->get('subscriber_id');
		$this->subscriber = $subscriber_id ? $this->manager->subscribers->get($subscriber_id) : null;
		return $this->subscriber;
	}

	/**
	 * Set the subscriber this message will send to
	 *
	 * @param ProMailerSubscriber $subscriber
	 * @return $this
	 * @throws WireException
	 *
	 */
	public function setSubscriber(ProMailerSubscriber $subscriber) {
		parent::set('subscriber_id', (int) $subscriber->id);
		$this->subscriber = $subscriber;
		return $this;
	}


}