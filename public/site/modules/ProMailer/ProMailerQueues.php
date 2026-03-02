<?php namespace ProcessWire;

/**
 * ProcessWire ProMailer Queue
 *
 * Copyright 2023 by Ryan Cramer
 * This is a commercial module, do not distribute.
 * 
 * @method int|array sendMessage(ProMailerMessage $message, ProMailerList $list, ProMailerSubscriber $subscriber)
 * @method sendFinished(ProMailerMessage $message, ProMailerList $list, ProMailerQueue $item)
 * @method int process($forceRun = false)
 *
 */ 

class ProMailerQueues extends ProMailerTypeManager {
	
	const lockFile = 'ProMailerQueues.lock';
	const timeFile = 'ProMailerQueues.time';

	/**
	 * Get all queued items
	 * 
	 * @param int|ProMailerMessage $messageId Optional message ID to get just one queue item
	 * @return ProMailerQueue[]
	 * @throws WireException
	 * 
	 */
	public function getAll($messageId = 0) {
		$messageId = $this->_id($messageId);
		$items = array();
		$table = $this->table();
		$sql = "SELECT * FROM $table ";
		if($messageId) $sql .= "WHERE message_id=:message_id";
		$query = $this->wire()->database->prepare($sql);
		if($messageId) $query->bindValue(':message_id', $messageId, \PDO::PARAM_INT);
		$query->execute();
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			$id = $row['message_id'];
			$items[$id] = new ProMailerQueue($row);
		}
		$query->closeCursor();
		return $items;
	}

	/**
	 * Get queue item for given message
	 * 
	 * @param int|ProMailerMessage $messageId
	 * @return ProMailerQueue|null
	 * @throws WireException
	 * 
	 */
	public function get($messageId) {
		$messageId = $this->_messageId($messageId); 
		$items = $this->getAll($messageId);
		return isset($items[$messageId]) ? $items[$messageId] : null;
	}

	/**
	 * Save a queue item
	 * 
	 * @param ProMailerQueue $queue
	 * @param bool $add Add as new item? (default=false)
	 * @return bool
	 * @throws WireException
	 * 
	 */
	public function save(ProMailerQueue $queue, $add = false) {

		$database = $this->wire()->database;
		$table = $this->table();
		$keys = array_keys($queue->getDefaultsArray());
		$sets = array();
		$binds = array();

		foreach($keys as $key) {
			$sets[] = "$key=:$key";
			$binds[":$key"] = $queue->get($key);
		}
		
		$sets = implode(',', $sets);

		if($add) {
			$sql = "INSERT INTO $table SET $sets";
		} else {
			$sql = "UPDATE $table SET $sets WHERE message_id=:messageId";
			$binds[":messageId"] = $queue->get('message_id');
		}

		$query = $database->prepare($sql);

		foreach($binds as $bindKey => $bindValue) {
			$query->bindValue($bindKey, $bindValue);
		}

		return $query->execute();
	}

	/**
	 * Add item to queue
	 * 
	 * @param ProMailerMessage|ProMailerQueue|int $item Queue item, Message or message ID
	 * @return bool
	 * @throws WireException
	 * 
	 */
	public function add($item) {
		if(is_int($item) || $item instanceof ProMailerMessage) return $this->addMessage($item);
		return $this->save($item, true);
	}
	
	/**
	 * Queue message to send in the background
	 *
	 * @param ProMailerMessage|int $message
	 * @return bool
	 *
	 */
	protected function addMessage($message) {
		
		$message = $this->messages->_message($message);
		$id = $message['id'];
		
		if($this->get($id)) return false; // already in queue
	
		$item = $this->_queue(array(
			'message_id' => $id,
			'next_subscriber_id' => (int) $message['subscriber_id'],
			'created' => time(),
			'next_time' => time(),
			'user_id' => $this->wire('user')->id,
		));
		
		if(!$item->next_subscriber_id) {
			$list = $this->lists->get($message['list_id']);
			$nextSubscriber = $this->subscribers->getNext($list, 0);
			if($nextSubscriber) $item->next_subscriber_id = $nextSubscriber['id'];
		}
		
		return $this->save($item, true);
	}

	/**
	 * Pause the queue item
	 * 
	 * @param ProMailerQueue|ProMailerMessage $item
	 * @param bool $resume Specify true to resume a paused item
	 * @return bool
	 * @throws WireException
	 * 
	 */
	public function pause($item, $resume = false) {
		$messageId = $this->_messageId($item);
		$table = $this->table();
		$sql = "UPDATE $table SET paused=:paused WHERE message_id=:message_id";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':paused', $resume ? 0 : 1);
		$query->bindValue(':message_id', $messageId); 
		if($item instanceof ProMailerQueue) $item->paused = $resume ? 0 : 1;
		return $query->execute();
	}

	/**
	 * Resume a paused queue item
	 * 
	 * @param ProMailerQueue|ProMailerMessage $item
	 * @return bool
	 * @throws WireException
	 * 
	 */
	public function resume($item) {
		return $this->pause($item, true);
	}
	
	/**
	 * Remove a message from the sending queue
	 * 
	 * @param int|ProMailerMessage|ProMailerQueue $item Message ID, message or queue item
	 * @return bool
	 * 
	 */
	public function remove($item) {
		$messageId = $this->_messageId($item);
		$table = $this->table();
		$sql = "DELETE FROM $table WHERE message_id=:message_id";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':message_id', $messageId); 
		return $query->execute();
	}

	/**
	 * Process sending any messages in the queue for this request
	 *
	 * @param bool $forceRun
	 * @return int Quantity of processed items
	 * @todo option to use a DB lock rather than a lock file?
	 * 
	 */
	public function ___process($forceRun = false) {
		
		static $numCalls = 0;
	
		// do not allow more than one process() call per request
		if($numCalls) return false;
	
		$cachePath = $this->wire()->config->paths->cache;
		$lockFile = $cachePath . self::lockFile;
		$timeFile = $cachePath . self::timeFile;
		
		if(is_file($timeFile)) {
			// presence of time file indicates there are items to process
			if(filemtime($timeFile) >= time()) {
				// time file modified within last second, so do not process
				return false;
			}
		} else {
			// presence of the time file indicates that there are items in the queue
			// if there is no time file and we're not forcing it to run, then do not process
			if(!$forceRun) return false;
		}
		
		if(is_file($lockFile) && !$forceRun) {
			// lock file exists
			if(filemtime($lockFile) > (time() - 60)) {
				// lock file modified within last minute so do not process
				return false;
			} else {
				// abandoned lock file, we can remove it
				@unlink($lockFile);
			}
		}
		
		if(!file_put_contents($lockFile, (string) time(), LOCK_EX)) {
			// if we cannot obtain a lock to write to lockfile then another request has it locked
			return false;
		}
	
		$numInQueue = 0;
		$numProcessed = 0;
		
		foreach($this->getAll() as $queue) {
			$numInQueue++;
			if($queue->next_time > time() || $queue->paused) continue;
			if($this->processQueue($queue)) $numProcessed++;
		}
		
		if($numInQueue) {
			// update time file to indicate time of last process
			file_put_contents($timeFile, time(), LOCK_EX);
		} else {
			// the queue is empty
			if(is_file($timeFile)) @unlink($timeFile);
		}

		// release the lock
		@unlink($lockFile);
		
		$numCalls++;
		
		return $numProcessed;
	}

	/**
	 * Process individual item in the queue
	 * 
	 * @param ProMailerQueue $queue
	 * @return bool
	 * 
	 */
	public function processQueue(ProMailerQueue $queue) {

		$message = $this->messages->get($queue->message_id);
		$list = $message ? $this->lists->get($message['list_id']) : false;
		
		if(!$message || !$list) return false;

		$nextSubscriber = false;
		$cnt = 0;

		for($n = 0; $n < $message['send_qty']; $n++) {
			
			$subscriber = false;
			
			if($nextSubscriber) {
				$subscriber = $nextSubscriber;

			} else if($queue->next_subscriber_id) {
				// get the next subscriber to send to 
				$subscriber = $this->subscribers->get($queue->next_subscriber_id, $list);
				if(!$subscriber && !$n && $queue->last_subscriber_id) {
					// fallback to get from last sent subscriber, but only if in first iteration
					$subscriber = $this->subscribers->getNext($list, $queue->last_subscriber_id);
				}
			} else if(!$n) {
				// first in list
				$subscriber = $this->subscribers->getNext($list, 0);
			}
			
			if(!$subscriber) {
				// if still no subscriber found then we are finished with this list
				$queue->next_subscriber_id = 0;
				break;
			}
		
			$result = $this->sendMessage($message, $list, $subscriber);
		
			if($result === 'skip') {
				// message to subscriber skipped by hook
			} else if($result) {
				$queue->num_sent++;
			} else {
				$queue->num_fail++;
			}

			$cnt++;
			$queue->num++;
			$queue->last_subscriber_id = $subscriber['id'];
			
			$nextSubscriber = $this->subscribers->getNext($list, $queue->last_subscriber_id);
			
			if($nextSubscriber) {
				$queue->next_subscriber_id = $nextSubscriber['id'];
			} else {
				$queue->next_subscriber_id = 0; // finished
			}
		}
		
		if($cnt) {
			$this->messages->saveSent($message, $queue->next_subscriber_id);
			$this->lists->saveSent($list);
		}

		if(!$queue->next_subscriber_id) {
			$this->sendFinished($message, $list, $queue);
			$this->remove($queue);
		} else {
			$queue->next_time = time() + $message['throttle_secs'];
			$this->save($queue);
		}

		return true;
	}

	/**
	 * Send message
	 * 
	 * To use a hook to skip sending a message to particular subscriber
	 * ~~~~~
	 * $wire->addHookBefore('ProMailerQueues::sendMessage', function($event) {
	 *   $message = $event->arguments(0);
	 *   $list = $event->arguments(1);
	 *   $subscriber = $event->arguments(2);
	 * 
	 *   if(condition where you want to skip this particular send) {
	 *     $event->return = 'skip';
	 *     $event->replace = true;
	 *   }
	 * }); 
	 * ~~~~~
	 * 
	 * @param ProMailerMessage $message
	 * @param ProMailerList $list
	 * @param ProMailerSubscriber $subscriber
	 * @return array|bool|string
	 * 
	 */
	protected function ___sendMessage($message, $list, $subscriber) {
		/** @var bool|array|string $result String only if populated by hook */
		$result = $this->email->sendMessage($message, $subscriber);
		return $result;
	}

	/**
	 * Send finished (in progress)
	 * 
	 * @param ProMailerMessage $message
	 * @param ProMailerList $list
	 * @param ProMailerQueue $queue
	 *
	 */
	protected function ___sendFinished(ProMailerMessage $message, ProMailerList $list, ProMailerQueue $queue) {

		// @todo finish implementing this method
		return; 
	
		$datetime = $this->wire()->datetime;
		$sanitizer = $this->wire()->sanitizer;
		
		if($datetime && method_exists($datetime, 'elapsedTimeStr')) {
			$elapsed = $datetime->elapsedTimeStr($queue->created, time()); 
		} else if(time() - $queue->created > 60) {
			$elapsed = round((time() - $queue->created) / 60) . ' minutes';
		} else {
			$elapsed = (time() - $queue->created) . ' seconds';
		}
		
		$stats = array(
			'Message' => $message->title, 
			'Subject' => $message->subject, 
			'List' => $list->title, 
			'Started' => wireDate('Y/m/d H:i:s', $queue->created), 
			'Finished' => wireDate('Y/m/d H:i:s', time()), 
			'Elapsed' => $elapsed, 
			'Sent' => $queue->num_sent, 
			'Failed' => $queue->num_fail, 
		);
		$bodyHtml = "<html><body><p>";
		$bodyText = '';
		foreach($stats as $label => $value) {
			$label = $sanitizer->entities($label);
			$value = $sanitizer->entities($value);
			$bodyHtml .= "<strong>$label:</strong> $value<br />\n";
			$bodyText .= "$label: $value\n";
		}
		$bodyHtml .= "</p></body></html>";
		
		$mailer = $this->promailer->email->getMailer();
		$mailer->to($message->from_email);
		$mailer->subject("ProMailer send completed");
		$mailer->bodyHTML($bodyHtml);
		$mailer->body($bodyText);
		$this->promailer->email->send($mailer);
	}
	
	/**
	 * Get a blank queue item or merge with another
	 * 
	 * @param array|ProMailerQueue $item
	 * @return ProMailerQueue
	 * 
	 */
	public function _queue($item) {
		if(is_array($item)) {
			return new ProMailerQueue($item);
		} else if($item instanceof ProMailerQueue) {
			return $item;
		} else {
			return new ProMailerQueue();
		}
	}

	/**
	 * Get message ID
	 * 
	 * @param ProMailerMessage|ProMailerQueue|int|string $item
	 * @return int
	 * 
	 */
	public function _messageId($item) {
		if($item instanceof ProMailerMessage) {
			$messageId = (int) $item->id;
		} else if($item instanceof ProMailerQueue) {
			$messageId = (int) $item->message_id;
		} else if(is_int($item) || (is_string($item) && ctype_digit("$item"))) {
			$messageId = (int) $item;
		} else {
			$messageId = 0;
		}
		return $messageId;
	}

	/**
	 * Get queues table
	 * 
	 * @return string
	 * 
	 */
	public function table() {
		return ProMailer::queuesTable;
	}

	/**
	 * Install
	 * 
	 * @return bool
	 * @throws WireException
	 * 
	 */
	public function install() {
		$database = $this->wire()->database;
		$engine = $this->wire()->config->dbEngine;
		$charset = $this->wire()->config->dbCharset;
		$table = $this->table();
		try {
			$database->exec("
				CREATE TABLE `$table` (
					message_id INT UNSIGNED NOT NULL PRIMARY KEY, 
					next_subscriber_id INT UNSIGNED NOT NULL DEFAULT 0,
					last_subscriber_id INT UNSIGNED NOT NULL DEFAULT 0,
					next_time INT UNSIGNED NOT NULL DEFAULT 0,
					created INT UNSIGNED NOT NULL DEFAULT 0,
					user_id INT UNSIGNED NOT NULL DEFAULT 0,
					num INT UNSIGNED NOT NULL DEFAULT 0,
					num_sent INT UNSIGNED NOT NULL DEFAULT 0,
					num_fail INT UNSIGNED NOT NULL DEFAULT 0,
					paused TINYINT UNSIGNED NOT NULL DEFAULT 0,
					INDEX paused (paused)
				) ENGINE=$engine DEFAULT CHARSET=$charset
			");
			$this->message("Created table: $table"); 
		} catch(\Exception $e) {
			// $this->error($e->getMessage());
		}
		return parent::install();
	}

	/**
	 * Upgrade
	 * 
	 * @param string|int $fromVersion
	 * @param string|int $toVersion
	 * @return bool
	 * 
	 */
	public function upgrade($fromVersion, $toVersion) {
		$this->install();
		return parent::upgrade($fromVersion, $toVersion); 
	}
}