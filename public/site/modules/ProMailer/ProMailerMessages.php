<?php namespace ProcessWire;

require(__DIR__ . '/ProMailerMessage.php');

/**
 * ProcessWire ProMailer Messages
 *
 * Copyright 2023 by Ryan Cramer
 *
 * @method ProMailerMessage add($message)
 *
 */
class ProMailerMessages extends ProMailerTypeManager {

	/**
	 * Properties stored in custom/data column that we move out to behave as regular column
	 * 
	 * @var array 
	 * 
	 */
	protected $customProperties = array(
		'throttle_secs' => array(
			'default' => 3, 
			'sanitizer' => 'intUnsigned'
		),
		'send_qty' => array(
			'default' => 1, 
			'sanitizer' => 'intUnsigned'
		),
		'reply_email' => array(
			'default' => '',
			'sanitizer' => 'email',
		),
	);
	
	/**
	 * Add a new message with given title and return new ProMailerMessage object
	 *
	 * @param string|array String containing just title, or specify array with any of the following:
	 *  - `title` (string): REQUIRED
	 *  - `subject` (string): Subject of email
	 *  - `flags` (int): Flags bitmask
	 *  - `from_name` (string): From name
	 *  - `from_email` (string): From email
	 *  - `body_source` (int): Body type (see ProMailer::bodySource* constants)
	 *  - `body_type` (int): Body mode (see ProMailer::bodyType* constants)
	 *  - `body_page` (int): Body rendered by this page ID (if bodySourcePage)
	 *  - `body_html` (string): HTML body (if bodySourceInput)
	 *  - `body_text` (string): Text body (if bodySourceInput)
	 *  - `list_id` (int): List ID
	 *  - `subscriber_id` (int): Next subscriber ID to send to
	 *  - `last_sent` (int): Date last sent (unix timestamp)
	 *  - `mailer` (string): WireMail module name to use
	 *  - `data` (array): Any additional optional data (associative array)
	 * @return ProMailerMessage 
	 *
	 */
	public function ___add($message) {
		
		$database = $this->wire()->database;

		if(is_array($message)) {
			if(empty($message['title'])) throw new WireException("addMessage() requires a 'title'");
		} else if(!is_string($message)) {
			throw new WireException("add method requires either an array or title (string)"); 
		} else {
			$message = array('title' => $message);
		}

		$emailPage = $this->email->getDefaultEmailPage(false);

		$sql = "INSERT INTO $this->table SET title=:title, created=:created, body_source=:body_source, body_page=:body_page";
		$query = $database->prepare($sql);
		$query->bindValue(':title', $message['title']);
		$query->bindValue(':created', time());
		$query->bindValue(':body_source', isset($message['body_source']) ? (int) $message['body_source'] : ProMailer::bodySourcePage, \PDO::PARAM_INT);
		$query->bindValue(':body_page', isset($message['body_page']) ? $message['body_page'] : $emailPage->id, \PDO::PARAM_INT);
		$query->execute();
		
		$messageId = (int) $database->lastInsertId();
		$messageArray = $message; // original provided message (which might just contain a title)
		$message = $this->get($messageId); // message now loaded from DB

		if(count($messageArray) > 1) {
			// properties other than title were provided so populate and save them
			$changed = false;
			foreach($messageArray as $k => $v) {
				if($k === 'id') continue;
				if(!empty($v) && $v !== $message->$k) {
					$message->$k = $v;
					$changed = true;
				}
			}
			if($changed) {
				$this->save($message);
				$message = $this->get($messageId);
			}
		}

		return $message;
	}

	/**
	 * Save the given message
	 *
	 * @param ProMailerMessage $message
	 * @return bool
	 * @throws WireException
	 *
	 */
	public function save(ProMailerMessage $message) {

		$table = ProMailer::messagesTable;
		$sanitizer = $this->wire()->sanitizer;
		
		// convert $message to array
		$message = $message->getArray();
	
		// move custom properties back into 'custom' array
		foreach($this->customProperties as $name => $info) {
			$method = $info['sanitizer'];
			$message['custom'][$name] = $sanitizer->$method($message[$name]);
			unset($message[$name]);
		}
		
		$data = empty($message['custom']) ? '' : json_encode($message['custom']);
		
		$sql =
			"UPDATE $table SET " .
			"title=:title, subject=:subject, flags=:flags, from_name=:from_name, from_email=:from_email, " .
			"body_source=:body_source, body_type=:body_type, body_page=:body_page, body_html=:body_html, " .
			"body_text=:body_text, body_href_html=:body_href_html, body_href_text=:body_href_text, list_id=:list_id, " .
			"subscriber_id=:subscriber_id, created=:created, last_sent=:last_sent, mailer=:mailer, data=:data " .
			"WHERE id=:id";

		$query = $this->wire('database')->prepare($sql);

		$query->bindValue(':title', $message['title']);
		$query->bindValue(':subject', $message['subject']);
		$query->bindValue(':flags', $message['flags'], \PDO::PARAM_INT);
		$query->bindValue(':from_name', $message['from_name']);
		$query->bindValue(':from_email', $message['from_email']);
		$query->bindValue(':body_source', $message['body_source'], \PDO::PARAM_INT);
		$query->bindValue(':body_type', $message['body_type'], \PDO::PARAM_INT);
		$query->bindValue(':body_page', $message['body_page'], \PDO::PARAM_INT);
		$query->bindValue(':body_html', $message['body_html']);
		$query->bindValue(':body_text', $message['body_text']);
		$query->bindValue(':body_href_html', $message['body_href_html']);
		$query->bindValue(':body_href_text', $message['body_href_text']);
		$query->bindValue(':list_id', $message['list_id'], \PDO::PARAM_INT);
		$query->bindValue(':subscriber_id', $message['subscriber_id'], \PDO::PARAM_INT);
		$query->bindValue(':created', $message['created'], \PDO::PARAM_INT);
		$query->bindValue(':last_sent', $message['last_sent'], \PDO::PARAM_INT);
		$query->bindValue(':mailer', $message['mailer']);
		$query->bindValue(':data', $data);
		$query->bindValue(':id', $message['id'], \PDO::PARAM_INT);

		return $query->execute();
	}


	/**
	 * Remove message
	 *
	 * @param int $messageId
	 * @return bool
	 *
	 */
	public function remove($messageId) {
		
		$messageId = $this->_id($messageId);
		$messages = $this->getAll($messageId);
		
		if(!isset($messages[$messageId])) return false;
		
		$database = $this->wire()->database;
		$sql = "DELETE FROM `$this->table` WHERE id=:id";
		
		$query = $database->prepare($sql);
		$query->bindValue(':id', $messageId);
		$query->execute();
		
		$result = $query->rowCount();
		$query->closeCursor();
		
		return (bool) $result;
	}

	/**
	 * Get all messages in an array
	 *
	 * @param int $messageId Optionally just load given message ID
	 * @return array
	 *
	 */
	public function getAll($messageId = 0) {
		
		$database = $this->wire()->database;

		$messages = array();
		$table = ProMailer::messagesTable;
		$sql = "SELECT * FROM $table " . ($messageId ? "WHERE id=:id" : "ORDER BY created");
		$query = $database->prepare($sql);
		
		if($messageId) $query->bindValue(':id', $messageId, \PDO::PARAM_INT);
		$query->execute();
		
		while($message = $query->fetch(\PDO::FETCH_ASSOC)) {
			if(empty($message['id'])) continue;
			$messageId = $message['id'];
			$message['custom'] = empty($message['data']) ? array() : json_decode($message['data'], true);
			unset($message['data']);
			$message = $this->_message($message); 
			$messages[$messageId] = $message;
		}
		
		$query->closeCursor();
		
		return $messages;
	}

	/**
	 * Get message array for given message ID or boolean false if not found
	 *
	 * @param int $messageId
	 * @return ProMailerMessage|bool
	 *
	 */
	public function get($messageId) {
		$messageId = $this->_id($messageId);
		if($messageId < 1) return false;
		$messages = $this->getAll($messageId);
		return isset($messages[$messageId]) ? $messages[$messageId] : false;
	}

	/**
	 * Update messages and subscribers tables after a message has been sent
	 *
	 * @param ProMailerMessage|int $message Message or message ID
	 * @param ProMailerSubscriber|int $nextSubscriberId Next subscriber or next subscriber ID
	 * @return bool
	 *
	 */
	public function saveSent($message, $nextSubscriberId) {

		$messageId = $this->_id($message);
		$nextSubscriberId = $this->_id($nextSubscriberId);

		$sql = "UPDATE `$this->table` SET last_sent=:last_sent, subscriber_id=:subscriber_id WHERE id=:id";
		$query = $this->wire()->database->prepare($sql);
		
		$query->bindValue(':last_sent', time(), \PDO::PARAM_INT);
		$query->bindValue(':subscriber_id', $nextSubscriberId, \PDO::PARAM_INT);
		$query->bindValue(':id', $messageId, \PDO::PARAM_INT);
		
		$result = $query->execute();
		$query->closeCursor();

		return $result;
	}

	/**
	 * Send given message to given subscriber
	 *
	 * @param ProMailerMessage|int $message Message or message ID
	 * @param ProMailerSubscriber|int $subscriber Subscriber or subscriber ID
	 * @param array $options
	 * @return bool|array True if message sent, false if not or verbose array if requested
	 *
	 */
	public function send($message, $subscriber, array $options = array()) {
		return $this->email->sendMessage($message, $subscriber, $options);
	}

	/**
	 * Ensure message is in correct format or convert ID to message
	 * 
	 * @param ProMailerMessage|array|int $a
	 * @return ProMailerMessage
	 * @throws WireException
	 * 
	 */
	public function _message($a) {
		
		$sanitizer = $this->wire()->sanitizer;
		
		$message = null;

		if($a instanceof ProMailerMessage) {
			$message = $a;
		} else if(is_array($a)) {
			$message = new ProMailerMessage($a);
		} else if(is_int($a) || ctype_digit("$a")) {
			$message = $this->get((int) $a);
		}

		if(!$message instanceof ProMailerMessage) {
			throw new WireException("Invalid message");
		}
		
		$custom = $message->custom;

		foreach($this->customProperties as $name => $info) {
			$method = $info['sanitizer'];
			if($message->$name !== null) {
				$value = $message[$name];
			} else if(isset($custom[$name])) {
				$value = $custom[$name];
			} else {
				$value = $info['default'];
			}
			$message->$name = $sanitizer->$method($value);
			unset($custom[$name]);
		}
		
		$message->custom = $custom;
		$message->setManager($this);

		return $message;
	}
	
	public function table() {
		return ProMailer::messagesTable;
	}
	
	public function install() {
		$database = $this->wire()->database;
		$engine = $this->wire()->config->dbEngine;
		$charset = $this->wire()->config->dbCharset;
		$table = $this->table();
		$database->exec("
			CREATE TABLE `$table` (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				title VARCHAR(191) NOT NULL, 
				subject VARCHAR(191) NOT NULL DEFAULT '', 
				flags INT UNSIGNED NOT NULL DEFAULT 0,
				body_source TINYINT UNSIGNED NOT NULL DEFAULT 0, 
				body_type TINYINT UNSIGNED NOT NULL DEFAULT 0,
				body_page INT UNSIGNED NOT NULL DEFAULT 0, 
				body_href_html VARCHAR(500), 
				body_href_text VARCHAR(500), 
				body_html MEDIUMTEXT, 
				body_text MEDIUMTEXT, 
				from_name VARCHAR(191) NOT NULL DEFAULT '',
				from_email VARCHAR(191) NOT NULL DEFAULT '',
				list_id INT NOT NULL DEFAULT 0,
				subscriber_id INT UNSIGNED NOT NULL DEFAULT 0,
				created INT UNSIGNED NOT NULL,
				last_sent INT UNSIGNED NOT NULL DEFAULT 0,
				mailer VARCHAR(50),
				data MEDIUMTEXT,
				UNIQUE title (title), 
				INDEX flags (flags),
				INDEX created (created),
				INDEX last_sent (last_sent)
			) ENGINE=$engine DEFAULT CHARSET=$charset
		");
		return parent::install();
	}
}