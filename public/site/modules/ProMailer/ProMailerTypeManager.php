<?php namespace ProcessWire;

/**
 * ProcessWire ProMailer Type Manager
 *
 * Copyright 2023 by Ryan Cramer
 * 
 * @property ProMailer $promailer
 * @property string $table
 * @property string $typeName
 * @property ProMailerForms $forms
 * @property ProMailerEmail $email
 * @property ProMailerLists $lists
 * @property ProMailerSubscribers $subscribers
 * @property ProMailerMessages $messages
 * @property ProMailerQueues $queues
 *
 *
 */
abstract class ProMailerTypeManager extends Wire {

	/**
	 * @var ProMailer
	 *
	 */
	protected $promailer;

	/**
	 * Construct
	 *
	 * @param ProMailer $promailer
	 *
	 */
	public function __construct(ProMailer $promailer) {
		$this->promailer = $promailer;
		$typeName = $this->typeName();
		if($typeName) {
			$typeFile = __DIR__ . '/' . $this->typeName() . '.php';
			if(is_file($typeFile)) require_once(__DIR__ . '/' . $this->typeName() . '.php');
		}
		$promailer->wire($this);
		parent::__construct();
	}

	/**
	 * Get the id property for the given type value
	 * 
	 * @param ProMailerType|int $id
	 * @return int
	 * 
	 */
	public function _id($id) {
		if($id instanceof ProMailerType) {
			$id = $id->id;
		} else if(is_array($id)) {
			$id = $id['id'];
		}	
		return (int) $id;
	}

	/**
	 * Get the class name of the type this class manages
	 * 
	 * @return string
	 * 
	 */
	public function typeName() {
		return rtrim($this->className(), 's');
	}

	/**
	 * Get the DB table name used by this type
	 * 
	 * @return string
	 * 
	 */
	abstract public function table();
	
	public function __get($key) {
		if($key === 'table') return $this->table();
		if($key === 'typeName') return $this->typeName();
		if($key === 'promailer') return $this->promailer;
		$instance = $this->promailer->instance($key);
		return $instance ? $instance : parent::__get($key);
	}

	public function install() {
		return true;
	}
	
	public function uninstall() {
		$table = $this->table();
		if($table) return $this->wire()->database->exec("DROP TABLE `$table`"); 
		return true;
	}
	
	public function upgrade($fromVersion, $toVersion) {
		if($fromVersion && $toVersion) { /* hi */ }
		return true;
	}
}	