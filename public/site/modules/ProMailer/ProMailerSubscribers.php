<?php namespace ProcessWire;

/**
 * ProcessWire ProMailer Subscribers
 *
 * Copyright 2023 by Ryan Cramer
 * This is a commercial module, do not distribute.
 * 
 *
 * HOOKS CALLED BEFORE AN EVENT
 * ============================
 * (these hooks can set $event->return to false to cancel an event)
 * @method bool addReady($email, ProMailerList $list, $confirmed, array $data)
 * @method bool saveReady(ProMailerSubscriber $subscriber, array $properties)
 * @method bool confirmEmailReady($email, ProMailerList $list, $code)
 * @method bool confirmSubscriberReady(ProMailerSubscriber $subscriber, ProMailerList $list, $code)
 * @method bool bounceDeleteReady($email, ProMailerList $list, $numBounces)
 *
 * HOOKS CALLED AFTER SUCCESS
 * ==========================
 * @method added(ProMailerSubscriber $subscriber)
 * @method saved(ProMailerSubscriber $subscriber, array $properties)
 * @method confirmedEmail($email, ProMailerList $list, $code)
 * @method confirmedSubscriber(ProMailerSubscriber $subscriber, ProMailerList $list, $code)
 * @method unsubscribed(ProMailerSubscriber $subscriber, ProMailerList $list)
 * @method unsubscribedEmail($email)
 * @method unsubscribedPage(Page $subscriber, ProMailerList $list)
 * 
 * BOUNCE-RELATED HOOKS
 * ====================
 * @method int bounceSubscriber(ProMailerSubscriber $subscriber, ProMailerList $list)
 * @method int bounceEmail($email)
 * @method int bouncePage(Page $page, ProMailerList $list)
 * 
 * CONFIRMATION CODE-RELATED HOOKS
 * ===============================
 * @method string getSubscriberCode(ProMailerSubscriber $subscriber, $create = false)
 * @method string getPageCode(Page $page)
 * @method string getRandomCode($length = 40) 
 * 
 * OTHER METHOD HOOKS
 * ==================
 * @method string confirmUrl(ProMailerSubscriber $subscriber, ProMailerList $list, Page $page)
 * @method int unsubscribeAllFromList(ProMailerList $list)
 * @method array|bool importCSV($csvFile, $list, array $options = array())
 * @method void exportCSV($listId)
 *
 */

class ProMailerSubscribers extends ProMailerTypeManager {

	/**
	 * Is the subscribers log currently disabled? (like during mass import operations)
	 * 
	 * @var bool
	 * 
	 */
	protected $saveLogDisabled = false;

	/**
	 * Allowed sorts info for subscribers
	 * 
	 * @var array
	 * 
	 */
	protected $sorts = array();

	/**
	 * Default sort property for subscribers
	 * 
	 * @var string
	 * 
	 */
	protected $defaultSort = '-created';

	/**
	 * Construct
	 *
	 * @param ProMailer $promailer
	 *
	 */
	public function __construct(ProMailer $promailer) {
		parent::__construct($promailer);
		$this->sorts = array(
			'-created' => array(
				'label' => $this->_('created (new-old)'),
				'orderby' => 'created DESC'
			),
			'created' => array(
				'label' => $this->_('created (old-new)'),
				'orderby' => 'created ASC'
			),
			'id' => array(
				'label' => $this->_('id (low-high)'),
				'orderby' => 'id ASC'
			),
			'-id' => array(
				'label' => $this->_('id (high-low)'),
				'orderby' => 'id DESC'
			),
			'email' => array(
				'label' => $this->_('email (a-z)'),
				'orderby' => 'email ASC'
			),
			'-email' => array(
				'label' => $this->_('email (z-a)'),
				'orderby' => 'email DESC'
			),
			'confirmed' => array(
				'label' => $this->_('pending'),
				'orderby' => 'confirmed ASC, created DESC'
			),
			'-confirmed' => array(
				'label' => $this->_('confirmed'),
				'orderby' => 'confirmed DESC, created DESC',
			),
			/* doesn't seem useful?
			'num_bounce' => array(
				'label' => $this->_('least bounces'),
				'orderby' => 'num_bounce ASC'
			),
			*/
			'-num_bounce' => array(
				'label' => $this->_('most bounces'),
				'orderby' => 'num_bounce DESC'
			),
			'num_sent' => array(
				'label' => $this->_('least sent'),
				'orderby' => 'num_sent ASC, email ASC'
			),
			'-num_sent' => array(
				'label' => $this->_('most sent'),
				'orderby' => 'num_sent DESC, email ASC'
			)
		);
		// add in a name property which simply duplicates the array key 
		foreach(array_keys($this->sorts) as $name) {
			$this->sorts[$name]['name'] = $name;
		}
		require_once(__DIR__ . '/ProMailerSubscribersArray.php');
	}

	/**
	 * Add a subscriber to a subscribers list
	 *
	 * @param string $email Email address 
	 * @param ProMailerList|int $list List
	 * @param bool $confirmed Double opt-in confirmed?
	 * @param array $data Associative array of additional data (optional)
	 * @return ProMailerSubscriber|bool|int 
	 *   Returns Subscriber on success, 
	 *   integer ID if subscriber already exists (and confirmed), 
	 *   or boolean false on fail
	 *
	 */
	public function add($email, $list, $confirmed = true, array $data = array()) {

		$email = strtolower($this->wire()->sanitizer->email($email));
		if(!strlen($email)) return false;
		
		$list = $this->_list($list, false);
		$listId = $list['id'];
		$subscriber = $this->getByEmail($email, $list);
		
		if($subscriber) {
			// subscriber already exists
			if($subscriber->confirmed) {
				// if already confirmed, return just id
				return (int) $subscriber['id'];
			} else {
				// if not yet confirmed, return full subscriber (may need to re-send confirmation)
				return $subscriber;
			}
		}
		
		$result = $this->addReady($email, $list, $confirmed, $data);
		if(!$result) return $result;

		$database = $this->wire()->database;
		$code = $this->getRandomCode();
		$binds = array();
		$sql = "INSERT INTO `$this->table` SET ";
		$id = 0;

		$row = array(
			'list_id' => (int) $listId,
			'email' => $email, 
			'data' => empty($data) ? '' : json_encode($data),
			'code' => $this->wire()->sanitizer->text($code, [ 'maxLength' => 40 ]),
			'created' => time(),
			'confirmed' => $confirmed ? 1 : 0,
		);

		foreach($row as $key => $value) {
			$key = $database->escapeCol($key);
			$sql .= "$key=:$key,";
			$binds[$key] = $value;
		}

		$sql = rtrim($sql, ',') . ' ';

		$query = $database->prepare($sql);

		foreach($binds as $key => $value) {
			$query->bindValue(":$key", $value);
		}

		try {
			if($query->execute()) {
				$id = $database->lastInsertId();
			}
		} catch(\Exception $e) {
			/*
			if($this->wire('user')->isSuperuser()) {
				$this->error('Error adding subscriber: ' . $e->getMessage());
			}
			*/
		}

		if($id) {
			$row['id'] = $id;
			$row['custom'] = $data;
			unset($row['data']);
			$subscriber = $this->_subscriber($row, $list);
			$this->added($subscriber);
			if($confirmed) {
				// @todo decide whether we should call confirmedSubscriber hook for this case
				// $this->confirmedSubscriber($subscriber, $list, ''); // blank code
			} else {
				// only log subscribe requests that are not yet confirmed
				// since any other is likely an import, admin or API addition
				$this->saveLog("SUBSCRIBE-REQUEST $subscriber[email] ($list[title])");
			}
		} else {
			$subscriber = null;
		}

		return $id ? $subscriber : false;
	}

	/**
	 * Save/update the given subscriber
	 *
	 * @param ProMailerSubscriber $subscriber
	 * @param array|string $properties Optionally limit update to given property names (array) or name (string)
	 * @return bool
	 *
	 */
	public function save(ProMailerSubscriber $subscriber, $properties = array()) {

		if(!$subscriber['id']) throw new WireException("Subscriber has no ID");
		
		if(is_string($properties)) {
			$properties = strlen($properties) ? array($properties) : array();
		}

		if(!$this->saveReady($subscriber, $properties)) return false;

		if($subscriber['type'] === 'pages') {
			$page = empty($subscriber['page']) ? null : $subscriber['page'];
			$result = true;
			if($page && $page->id && $page->isChanged()) {
				$result = $page->save();
				$this->saved($subscriber, $properties);
			}
			return $result;
		}

		$sanitizer = $this->wire()->sanitizer;
		$database = $this->wire()->database;
		$binds = array('id' => (int) $subscriber['id']);
		$sets = array();

		$row = array(
			'email' => strtolower($sanitizer->email($subscriber['email'])),
			'list_id' => (int) $subscriber['list_id'],
			'data' => empty($subscriber['custom']) ? '' : json_encode($subscriber['custom']),
			'code' => $subscriber['code'],
			'confirmed' => (int) $subscriber['confirmed'],
			'num_sent' => (int) $subscriber['num_sent'],
			'num_bounce' => (int) $subscriber['num_bounce'],
		);

		foreach($row as $col => $value) {
			if(count($properties) && !in_array($col, $properties)) continue;
			$sets[] = "$col=:$col";
			$binds[$col] = $value;
		}

		$sql = "UPDATE `$this->table` SET " . implode(', ', $sets) . " WHERE id=:id";
		$query = $database->prepare($sql);

		foreach($binds as $col => $value) {
			$type = is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
			$query->bindValue(":$col", $value, $type);
		}

		try {
			$result = $query->execute();
			$query->closeCursor();
			$this->saved($subscriber, $properties);
		} catch(\Exception $e) {
			$this->error($e->getMessage());
			$result = false;
		}

		return $result;
	}

	/**
	 * Save only custom field data for existing subscriber
	 * 
	 * @param ProMailerSubscriber $subscriber
	 * @return bool
	 * 
	 */
	public function saveCustom(ProMailerSubscriber $subscriber) {
		if(!$subscriber->id) return false;
		$sql = "UPDATE `$this->table` SET data=:data WHERE id=:id";
		$data = $subscriber->getCustom();
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':data', empty($data) ? '' : json_encode($data)); 
		$query->bindValue(':id', (int) $subscriber->id, \PDO::PARAM_INT);
		$result = $query->execute();
		if($result) $result = $query->rowCount();
		return (bool) $result;
	}

	/**
	 * Copy all subscribers from list1 into list2
	 * 
	 * @param ProMailerList $list1 Copy from this list (1)
	 * @param ProMailerList $list2 Copy into this list (2)
	 * @return array Regular array of two integers containing [ qtyAdded, qtyFailed ]
	 * 
	 */
	public function copyAll(ProMailerList $list1, ProMailerList $list2) {
		
		$qtyAdded = 0;
		$qtyFailed = 0;
		$database = $this->wire()->database;
		$table = $this->table();
	
		if($list1->type == ProMailer::listTypePages) return $this->copyAllPages($list1, $list2); 
		if($list2->type == ProMailer::listTypePages) throw new WireException("Cannot copy to pages-type lists");
		
		$cols = array('flags', 'email', 'data', 'code', 'created', 'confirmed'); 
		$colsStr = implode(',', $cols);
		$valuesStr = ':' . implode(',:', $cols);
		
		$sql1 = "SELECT $colsStr FROM $table WHERE list_id=:list_id ORDER BY id"; 
		$query1 = $database->prepare($sql1);
		$query1->bindValue(':list_id', $list1->id, \PDO::PARAM_INT); 
		
		$sql2 = "INSERT INTO $table (list_id, $colsStr) VALUES(:list_id, $valuesStr)";
		$query2 = $database->prepare($sql2);
		
		$query1->execute();
		
		while($row = $query1->fetch(\PDO::FETCH_ASSOC)) {
			$query2->bindValue(':list_id', $list2->id, \PDO::PARAM_INT); 
			foreach($cols as $col) {
				$query2->bindValue(":$col", $row[$col]); 
			}
			try {
				$query2->execute();
				$qtyAdded++;
			} catch(\Exception $e) {
				$qtyFailed++;
			}
		}
		
		$query1->closeCursor();
		$query2->closeCursor();
		
		return array($qtyAdded, $qtyFailed);
	}

	/**
	 * Copy all subscribers from $list1 to $list2 when $list1 is a Pages-type list and $list2 is a regular list
	 * 
	 * @param ProMailerList $list1
	 * @param ProMailerList $list2
	 * @return array
	 * 
	 */
	protected function copyAllPages(ProMailerList $list1, ProMailerList $list2) {
		
		$pages = $this->wire()->pages;
		$input = $this->wire()->input;
		
		$limit = 500;
		$pageNum = 0;
		$qtyAdded = 0;
		$qtyFailed = 0;
		
		do {
			$input->setPageNum(++$pageNum);
			if($pageNum > 1) $this->pages->uncacheAll();
			
			$selector = $this->pageSelector($list1) . ", limit=$limit";
			$items = $pages->find($selector, array('allowCustom' => true));
			
			foreach($items as $item) {
				$subscriber1 = $this->pageToSubscriber($item, $list1); 
				if(!$subscriber1) continue;
				$data = array();
				foreach($list2->fieldsArray() as $fieldInfo) {
					$fieldName = $fieldInfo['name'];
					$value = $item->get($fieldName); 	
					if($value !== null && !is_object($value)) $data[$fieldName] = $value;
				}
				$subscriber2 = $this->add($subscriber1->email, $list2, true, $data);
				if($subscriber2 instanceof ProMailerSubscriber) {
					$qtyAdded++;
				} else {
					$qtyFailed++;
				}
			}
		} while($items->count());
		
		$input->setPageNum(1);
		
		return array($qtyAdded, $qtyFailed); 
	}

	/**
	 * Record a bounce for an email address or a subscriber in a list
	 * 
	 * If given just an email address, the bounce is recorded in all ProMailer lists that contain the email
	 * 
	 * @param string|ProMailerSubscriber|int|Page $subscriber Email address, subscriber object or ID
	 * @param null|ProMailerList|int $list List or list ID, or omit to detect from subscriber
	 * @return int Positive number on success, 0 on fail
	 * 
	 */
	public function bounce($subscriber, $list = null) {
		$result = 0;
		try {
			if($subscriber instanceof Page) {
				// Page-based subscriber
				$result = $this->bouncePage($subscriber, $list);
			} else if(!$list && is_string($subscriber) && strpos($subscriber, '@')) {
				// record bounce for email address regardless of which ProMailer list it appears in
				$result = $this->bounceEmail($subscriber);
			} else {
				// record bounce for subscriber in specific list
				$subscriber = $this->_subscriber($subscriber);
				$list = $list ? $this->_list($list) : $this->_list($subscriber['list_id']);
				$result = $this->bounceSubscriber($subscriber, $list);
			}
			if($result) $this->maintenance();
		} catch(\Exception $e) {
			if($this->wire()->config->debug) throw $e;	
		}
		return $result;
	}

	/**
	 * Add a bounce for the subscriber in a specific list
	 *
	 * @param ProMailerSubscriber $subscriber Subscriber object, email or ID
	 * @param int|ProMailerList $list List ID or array
	 * @return int Returns positive integer on success or 0 on fail
	 *
	 */
	protected function ___bounceSubscriber(ProMailerSubscriber $subscriber, ProMailerList $list) {
		if(!$subscriber['id']) return 0;
		if($subscriber['type'] === 'pages') return $this->bouncePage($subscriber->page, $list);
		$sql = "UPDATE `$this->table` SET num_bounce=num_bounce+1 WHERE id=:id";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':id', $subscriber['id'], \PDO::PARAM_INT);
		$query->execute();
		$qty = $query->rowCount();
		$query->closeCursor();
		if($qty) $this->saveLog("BOUNCE $subscriber[email] ($list[title])"); 
		return $qty;
	}

	/**
	 * Record a bounce for email address on any/all ProMailer managed lists that it appears on
	 * 
	 * @param string $email Email address to record bounce for
	 * @return int Number of lists where a bounce was recorded for given email
	 * 
	 */
	protected function ___bounceEmail($email) {
		$email = $this->wire()->sanitizer->email($email);
		if(!strlen($email)) return 0;
		$sql = "UPDATE `$this->table` SET num_bounce=num_bounce+1 WHERE email=:email";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':email', $email, \PDO::PARAM_STR);
		$query->execute();
		$qty = $query->rowCount();
		$query->closeCursor();
		if($qty) $this->saveLog("BOUNCE $email (all lists)"); 
		return $qty;
	}

	/**
	 * Add a bounce for a subscriber that is a Page (@todo needs implementation)
	 * 
	 * @param Page $page
	 * @param ProMailerList $list
	 * @return bool
	 * 
	 */
	protected function ___bouncePage(Page $page, ProMailerList $list) {
		return false; // not currently supported
	}

	/**
	 * Called when bounced email has reached a threshold and will be deleted
	 * 
	 * Hooks may optionally make this return false to prevent deletion of the bounced email. 
	 * 
	 * @param string $email
	 * @param ProMailerList $list
	 * @param int $numBounces
	 * @return bool
	 * 
	 */
	protected function ___bounceDeleteReady($email, ProMailerList $list, $numBounces) {
		return true;
	}
	
	/**
	 * Confirm a subscriber as opt-in for a list
	 * 
	 * Use this only when the confirmation comes directly from the subscribing user, 
	 * as calling this method counts as a double opt-in, which is why the email,
	 * list and code are all required to complete it, rather than a ProMailerSubscriber
	 * object instance. 
	 *
	 * @param string $email Email address to confirm
	 * @param int|ProMailerList $list List that the confirmation is for
	 * @param string $code Unique confirmation code for subscriber
	 * @return bool
	 * @throws WireException
	 *
	 */
	public function confirmEmail($email, $list, $code) {
		$sanitizer = $this->wire()->sanitizer;
		$list = $this->_list($list, false);
		$listId = $list['id'];
		$email = strtolower($sanitizer->email($email));
		$code = $sanitizer->text($code, [ 'maxLength' => 40 ]);
		$subscriber = $this->getByEmail($email, $list); 
		if(!$this->confirmEmailReady($email, $list, $code)) return false;
		if($subscriber && !$this->confirmSubscriberReady($subscriber, $list, $code)) return false;
		$sql = "UPDATE `$this->table` SET confirmed=:confirmed WHERE list_id=:list_id AND email=:email AND code=:code";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':list_id', $listId, \PDO::PARAM_INT);
		$query->bindValue(':confirmed', time(), \PDO::PARAM_INT);
		$query->bindValue(':email', $email);
		$query->bindValue(':code', $code);
		$query->execute();
		$result = $query->rowCount() > 0;
		$query->closeCursor();
		if($result) {
			$this->saveLog("SUBSCRIBE-CONFIRM $email ($list[title])");
			$this->confirmedEmail($email, $list, $code);
			if($subscriber) {
				$this->confirmedSubscriber($subscriber, $list, $code); 
			}
		}
		return $result;
	}

	/**
	 * Send confirmation email to subscriber
	 * 
	 * This method is an alias of (and delegates to): $promailer->forms->sendConfirmEmail()
	 *
	 * @param ProMailerSubscriber $subscriber
	 * @param array|string
	 * - `fromEmail` (string): From email address or omit for default
	 * - `fromName` (string): Optional from name to accompany from email
	 * - `subject` (string): Messsage subject or omit for default
	 * - `page` (Page): Page that will process confirmation (default=current page)
	 * - `bodyHTML` (string): Optional message body in HTML (omit for default)
	 * - `body` (string): Optional message body in plain text (omit for default)
	 * - If given a string for $options it is assumed to be the fromEmail option.
	 * @return bool True if message sent, false if not
	 *
	 */
	public function sendConfirmEmail(ProMailerSubscriber $subscriber, $options = array()) {
		return $this->forms->sendConfirmEmail($subscriber, $options);
	}

	/**
	 * Re-send existing confirmation email
	 * 
	 * This method is an alias of (and delegates to): $promailer->forms->resendConfirmEmail()
	 * 
	 * @param ProMailerSubscriber $subscriber
	 * @return bool
	 * 
	 */
	public function resendConfirmEmail(ProMailerSubscriber $subscriber) {
		return $this->forms->resendConfirmEmail($subscriber);
	}

	/**
	 * Get the URL needed to confirm a subscriber 
	 *
	 * @param ProMailerSubscriber $subscriber
	 * @param Page|null $page Landing page for unsubscribe form or omit for current page
	 * @param ProMailerList|int|null $list List or omit to pull automatically from subscriber
	 * @return string
	 *
	 */
	public function getConfirmUrl(ProMailerSubscriber $subscriber, $page = null, $list = null) {
		$subscriber = $this->_subscriber($subscriber, $list ? $list : 0);
		if(empty($list)) $list = $this->lists->get($subscriber['list_id']);
		$list = $this->_list($list, false);
		if(!$page instanceof Page) $page = $this->wire()->page;
		return $this->confirmUrl($subscriber, $list, $page);
	}
	
	/**
	 * Hook that can modify the confirm URL (in $event->return)
	 *
	 * @param ProMailerSubscriber $subscriber
	 * @param ProMailerList $list
	 * @param Page $page
	 * @return string
	 *
	 */
	protected function ___confirmUrl(ProMailerSubscriber $subscriber, ProMailerList $list, Page $page) {
		return $page->httpUrl() .
			"?list=$list[id]" .
			"&email=" . urlencode($subscriber['email']) .
			"&code=" . $subscriber['code'] . 
			'#promailer';
	}

	/**
	 * Given a ProMailerSubscriber, quietly delete it from the database 
	 * 
	 * @param ProMailerSubscriber $subscriber
	 * @return bool
	 * 
	 */
	public function delete(ProMailerSubscriber $subscriber) {
		$sql = "DELETE FROM `$this->table` WHERE id=:id";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':id', $subscriber->id, \PDO::PARAM_INT);
		$query->execute();
		$result = (int) $query->rowCount();
		$query->closeCursor();
		return (bool) $result;
	}

	/**
	 * Remove a subscriber from a list
	 * 
	 * Optionally unsubscribe an email from ALL lists by specifying an email address (string) for the 
	 * first argument ($subscriber) and boolean true for the second argument ($list), and omit the $code argument. 
	 * This option should not be triggered by user input since it does not require a $code. 
	 * 
	 * @param string|ProMailerSubscriber|Page $subscriber Email address or subscriber object or Page
	 * @param int|ProMailerList $list Specify list, or boolean true for ALL lists ($subscriber must be email address and $code must be omitted)
	 * @param string|bool $code If present, this code will also be required or specify boolen false if not required (default)
	 * @return bool
	 *
	 */
	public function unsubscribe($subscriber, $list, $code = false) {
		
		$sanitizer = $this->wire()->sanitizer;
		$database = $this->wire()->database;
		
		if(is_string($subscriber) && $list === true && $code === false) {
			// unsubscribe email from all lists
			return $this->unsubscribeEmail($subscriber) > 0;
		}
			
		$list = $this->_list($list);
		$listId = $list['id'];
		
		if($subscriber instanceof ProMailerSubscriber) {
			// Subscriber object
			$subscriber = $this->_subscriber($subscriber, $list);
			if($code === false) $code = $subscriber['code']; // code is optional if given Subscriber object
		} else if($subscriber instanceof Page) {
			// Page subscriber
			return $this->unsubscribePage($subscriber, $list, $code); 
		} else if(is_string($subscriber)) {
			// email address
			$subscriber = $this->getByEmail($subscriber, $list);
			if(!$subscriber) return false;
		} else {
			throw new WireException('Invalid $subscriber argument to unsubscribe() method');
		}

		if($list['type'] === 'pages') {
			return $this->unsubscribePage($subscriber['page'], $list, $code);
		}

		$email = strtolower($sanitizer->email($subscriber['email']));
		if($code !== false) $code = $sanitizer->text($code);

		if(empty($email)) return false;
		
		$unsubListId = (int) $list['unsub_list_id'];
		$unsubList = $unsubListId ? $this->_list($unsubListId) : null;

		if($unsubList && $unsubList->id != $listId) {
			// move to unsubscribers list
			$unsubscriber = $this->getByEmail($email, $unsubList);
			$unsubTime = time();
			$unsubEmail = $email;
			// ensure we don't already have the email in the unsubscribers list
			// this is not likely to be a regular case, but still have to account for it
			while($unsubscriber) {
				list($emailName, $emailDomain) = explode('@', $email, 2);
				if(strpos($emailName, '+')) list($emailName,) = explode('+', $emailName, 2); 
				$emailPlus = ++$unsubTime;
				$unsubEmail = "$emailName+$emailPlus@$emailDomain";
				$unsubscriber = $this->getByEmail($unsubEmail, $unsubList);
			}
			$sql = "UPDATE `$this->table` SET list_id=:list_id, email=:email WHERE id=:id";
			if($code !== false) $sql .= ' AND code=:code';
			$query = $database->prepare($sql);
			$query->bindValue(':list_id', $unsubList->id, \PDO::PARAM_INT);
			$query->bindValue(':email', $unsubEmail);
		} else {
			// permanently delete
			$sql = "DELETE FROM `$this->table` WHERE list_id=:list_id AND email=:email";
			if($code !== false) $sql .= ' AND code=:code';
			$query = $database->prepare($sql);
			$query->bindValue(':list_id', $listId, \PDO::PARAM_INT);
			$query->bindValue(':email', $email);
		}

		if($code !== false) $query->bindValue(':code', $code);
		$query->execute();
		$result = $query->rowCount();
		$query->closeCursor();
			
		if($result) {
			$this->saveLog("UNSUBSCRIBE $email ($list[title])");
			$this->unsubscribed($subscriber, $list); 
		}

		return (bool) $result;
	}

	/**
	 * Unsubscribe an email from all lists
	 * 
	 * @param string $email
	 * @return int
	 * 
	 */
	protected function unsubscribeEmail($email) {
		$database = $this->wire()->database;
		$email = $this->wire()->sanitizer->email($email);
		if(!strlen($email)) return 0;
		if($this->promailer->unsubDelete) {
			$query = $database->prepare("DELETE FROM `$this->table` WHERE email=:email");
		} else {
			$query = $database->prepare("UPDATE `$this->table` SET flags=flags|2048 WHERE email=:email");
		}
		$query->bindValue(':email', $email);
		$query->execute();
		$qty = $query->rowCount();
		$query->closeCursor();
		if($qty) {
			$this->saveLog("UNSUBSCRIBE $email (all lists)");
			$this->unsubscribedEmail($email);
		}
		return $qty;
	}
	
	/**
	 * Unsubscribe a page-based subscriber
	 *
	 * @param Page $page
	 * @param ProMailerList|int $list
	 * @param string|bool $code
	 * @return bool
	 *
	 */
	protected function unsubscribePage(Page $page, $list, $code = false) {
		$list = $this->_list($list);
		if($code !== false && $code !== $this->getCode($page)) return false;
		$field = $this->forms->getUnsubscribeField($list);
		if(!$field) return false;
		$page->setAndSave($field->name, 1);
		$this->saveLog("UNSUBSCRIBE user:$page->name ($list[title])"); 
		$this->unsubscribedPage($page, $list);
		return true;
	}
	
	/**
	 * Remove all subscribers from a list
	 *
	 * @param ProMailerList $list
	 * @return int Number of subscribers removed
	 * @throws WireException
	 *
	 */
	public function ___unsubscribeAllFromList(ProMailerList $list) {
		$list = $this->_list($list, false);
		$listId = $list['id'];
		$sql = "DELETE FROM `$this->table` WHERE list_id=:list_id";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':list_id', $listId, \PDO::PARAM_INT);
		$query->execute();
		$result = $query->rowCount();
		$query->closeCursor();
		return $result;
	}

	/**
	 * Remove subscribers that haven’t been confirmed for given number of days
	 *
	 * @param int $days
	 * @return int Number that were removed
	 * @throws WireException
	 *
	 */
	public function removeUnconfirmed($days = 30) {
		$sql = "DELETE FROM `$this->table` WHERE list_id>0 AND confirmed=0 AND created<:created";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':created', strtotime("-$days DAYS"));
		$query->execute();
		$result = $query->rowCount();
		$query->closeCursor();
		if($result) $this->saveLog("DELETED $result unconfirmed subscribers (days=$days)"); 
		return $result;
	}

	/**
	 * Get current pagination (limit=100) of subscribers for given list
	 *
	 * Includes both confirmed and non-confirmed subscribers
	 *
	 * @param array $options
	 *  - `list` (ProMailerList|int): List to limit find to
	 *  - `sort` (string): What to sort by (default='-created')
	 *  - `email` (string): Find subscriber email that contains this text
	 *  - `custom` (string): Find subscriber that matches string in custom data
	 *     Note that string in 'custom' containing selector like 'field=value' get converted to 'filters' option. 
	 *  - `partial` (bool): Perform a partial match rather than exact match (for 'custom' or 'email')? (default=true)
	 *  - `confirmed` (bool): Return only confirmed subscribers? (default=false)
	 *  - `id` (int): Count only subscribers greater than or equal to this subscriber ID (default=0)
	 *  - `limit` (int): Limit to this many subscribers per pagination (default=100)
	 *  - `count` (bool): Return a count rather than subscribers? (default=false)
	 *  - `delete` (bool): Delete all found subscribers rather than return them? (default=false)
	 *  - `filters` (array): Find subscribers that match these [ key=value ] filter(s) in custom data.
	 *     To perform exact match, specify [ 'field' => '=value' ] or [ 'field' => 'value' ] (operator optional). 
	 *     To perform partial match, specify [ 'field' => '%=value' ].
	 *     To perform NOT match, specify [ 'field' => '!=value' ] for exact, or [ 'field' => '!%=value' ] for partial.
	 * @return ProMailerSubscribersArray|int Returns array of subscribers or integer if 'count' or 'delete' option specified
	 *
	 */
	public function find(array $options = array()) { // was getSubscribers

		$defaults = array(
			'list' => null,
			'sort' => $this->getDefaultSort(),
			'email' => '',
			'custom' => '',
			'filters' => array(), 
			'partial' => true, 
			'confirmed' => false,
			'id' => 0,
			'limit' => 100,
			'count' => false, 
			'delete' => false, 
		);

		$sanitizer = $this->wire()->sanitizer;
		$database = $this->wire()->database;
		$input = $this->wire()->input;
		
		$sorts = $this->getAllowedSorts();
		$options = array_merge($defaults, $options);
		$list = $options['list'] ? $this->_list($options['list'], false) : null;
		$listId = $list ? $list->id : 0;
		$sort = $options['sort'];
		if(!isset($sorts[$sort])) $sort = '-created';
		$limit = (int) $options['limit'];
		$start = $limit * ($input->pageNum() - 1);
		$wheres = array();
		$binds = array();
		
		if($listId) {
			$wheres[] = "list_id=:list_id";
			$binds[':list_id'] = $listId;
			$options['list'] = $list;
		}
		
		if($options['confirmed']) {
			$wheres[] = "confirmed>0";
		}
		
		if($options['id']) {
			$wheres[] = 'id>=:id';
			$binds[':id'] = (int) $options['id'];
		}
	
		// convert 'custom' values containing selectors to 'filters' value
		$custom = $options['custom'];
		if(strlen($custom) && strpos($custom, '=') && Selectors::stringHasSelector($custom)) {
			$operators = array('=', '!=', '%=', '!%=', '*=', '!*='); 
			$selectors = new Selectors($custom);
			$this->wire($selectors);
			foreach($selectors as $selector) {
				$fieldName = $sanitizer->fieldName($selector->field());
				if(!in_array($selector->operator(), $operators)) {
					throw new WireException("Unsupported operator: $selector->operator"); 
				}
				$options['filters'][$fieldName] = $selector->operator . $selector->value();
			}
			$options['custom'] = '';
		}
	
		$where = '';
		foreach(array('email', 'custom') as $key) {
			if(!is_string($options[$key]) || !strlen($options[$key])) continue;
			$col = $key === 'custom' ? 'data' : $key;
			$value = ltrim($options[$key], '=%*');
			if(strlen($where)) $where .= ' OR ';
			if($options['partial']) {
				$where .= "$col LIKE :$key";
				$like = addcslashes($value, '%_');
				$binds[":$key"] = '%' . $like . '%';
			} else {
				$where .= "$col=:$key";
				$binds[":$key"] = $value;
			}
		}
		if($where) $wheres[] = "($where)";
		
		if(count($options['filters'])) {
			foreach($options['filters'] as $filterName => $filterValue) {
				$filterName = $database->escapeCol($sanitizer->fieldName($filterName));
				$filterValue = mb_strtolower($filterValue);
				$filterNot = false;
				if(strpos($filterValue, '!') === 0) {
					$filterNot = true;
					$filterValue = substr($filterValue, 1);
					$filterOp = '!=';
				} else {
					$filterOp = '=';
				}
				if(strpos($filterValue, '%=') === 0 || strpos($filterValue, '*=') === 0) {
					$filterValue = addcslashes(substr($filterValue, 2), '%_');
					$filterValue = '%' . $filterValue . '%'; 
					$filterOp = ($filterNot ? ' NOT LIKE ' : ' LIKE ');
				} else if(strpos($filterValue, '=') === 0) {
					$filterValue = substr($filterValue, 1); 
					$filterOp = $filterNot ? '!=' : '=';
				}
				// JSON_UNQUOTE(LOWER($col))$operator$bindKey
				if(strlen($filterValue)) {
					$binds[":$filterName"] = $filterValue;	
					$wheres[] =
						"(data!='' AND data IS NOT NULL AND " .
						"JSON_UNQUOTE(LOWER(JSON_EXTRACT(data, \"$.$filterName\")))$filterOp:$filterName" . 
						//"JSON_CONTAINS(data, '{\"$.$filterName\": \"$filterValue\" }')=1" .
						")";
				} else if($filterNot) {
					$wheres[] =
						"((data IS NOT NULL AND data!='') AND (" .
						"JSON_EXTRACT(data, \"$.$filterName\") IS NOT NULL " .
						"AND JSON_EXTRACT(data, \"$.$filterName\")!='' " .
						"AND JSON_CONTAINS(data, '{\"$filterName\": null}')!=1" .
						"))";
				} else {
					$wheres[] = 
						"((data IS NULL OR data='') OR (" . 
						"JSON_EXTRACT(data, \"$.$filterName\") IS NULL " . 
						"OR JSON_EXTRACT(data, \"$.$filterName\")='' " . 
						"OR JSON_CONTAINS(data, '{\"$filterName\": null}')=1" . 
						"))";
				}
			}
		}
		
	
		if($options['delete'] === true) {
			$sql = "DELETE ";
		} else {
			$sql = ($options['count'] ? "SELECT COUNT(*)" : "SELECT SQL_CALC_FOUND_ROWS $this->table.*") . ' ';
		}
		$sql .= "FROM `$this->table` "; 
		if(count($wheres)) $sql .= 'WHERE ' . implode(' AND ', $wheres) . ' ';
		if(!$options['count'] && !$options['delete']) {
			$sql .= "ORDER BY " . $sorts[$sort]['orderby'] . " LIMIT $start,$limit";
		}
		$query = $database->prepare($sql);
		foreach($binds as $k => $v) $query->bindValue($k, $v); 
		$query->execute();
		
		if($options['delete']) {
			$result = (int) $query->rowCount();

		} else if($options['count']) {
			$result = (int) $query->fetchColumn();
			
		} else {
			$subscribers = new ProMailerSubscribersArray();
			$this->wire($subscribers);
			
			while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
				$row['custom'] = strlen($row['data']) ? json_decode($row['data'], true) : array();
				$subscriber = $this->_subscriber($row, $list);
				$subscribers->add($subscriber);
			}
			
			$subscribers->setStart($start); 
			$subscribers->setLimit($limit);
			$subscribers->setTotal((int) $database->query("SELECT FOUND_ROWS()")->fetchColumn());
			$subscribers->findOptions($options);
			$result = $subscribers;
		}

		$query->closeCursor();
		
		return $result;
	}

	/**
	 * Given an email address return the subscriber or boolean false if not found
	 *
	 * @param string $email
	 * @param int|ProMailerList|null $list
	 * @param int $limit If limit is not 1, an array of ProMailerSubscriber objects is returned (default=1)
	 * @return bool|array|ProMailerSubscriber
	 *
	 */
	public function getByEmail($email, $list = null, $limit = 1) {
		
		$email = strtolower($this->wire()->sanitizer->email($email));
		if(empty($email)) return false;

		if($list) {
			$list = $this->_list($list);
			$listId = $list['id'];
			if($list['type'] === 'pages') return $this->getByPageEmail($email, $list);
		} else {
			$listId = 0;
		}

		$sql = "SELECT * FROM `$this->table` WHERE email=:email ";
		if($listId) $sql .= 'AND list_id=:list_id ';
		if($limit) $sql .= 'LIMIT ' . (int) $limit;
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':email', $email);
		if($listId) $query->bindValue(':list_id', $listId, \PDO::PARAM_INT);
		$query->execute();
		$rows = array();
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			$row['custom'] = strlen($row['data']) ? json_decode($row['data'], true) : array();
			$row = $this->_subscriber($row, $list);
			$rows[] = $row;
		}
		$query->closeCursor();

		if($limit == 1) return count($rows) ? reset($rows) : false;

		return $rows;
	}


	/**
	 * Get a subscriber by ID
	 *
	 * @param int $id
	 * @param int|ProMailerList $list Specify list or omit to auto-detect (argument required for page lists though)
	 * @return bool|ProMailerSubscriber
	 *
	 */
	public function getById($id, $list = 0) {

		$id = (int) $id;
		if($id < 1) return false;
		
		if($list) {
			$list = $this->_list($list);
			if($list['type'] === 'pages') return $this->getByPageId($id, $list);
			$listId = $list['id'];
		} else {
			$listId = 0;
		}

		$sql = "SELECT * FROM `$this->table` WHERE id=:id ";
		if($listId > 0) $sql .= 'AND list_id=:list_id ';
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':id', $id, \PDO::PARAM_INT);
		if($listId > 0) $query->bindValue(':list_id', $listId, \PDO::PARAM_INT);
		$query->execute();
		$row = $query->fetch(\PDO::FETCH_ASSOC);
		$query->closeCursor();

		if($row) {
			$row['custom'] = strlen($row['data']) ? json_decode($row['data'], true) : array();
			$row = $this->_subscriber($row, $list);
			return $row;
		}

		return false;
	}

	/**
	 * Get a subscriber that refers to a Page (for a pages-type list)
	 *
	 * @param int $pageId
	 * @param ProMailerList|int $list
	 * @return ProMailerSubscriber
	 * @throws WireException
	 *
	 */
	protected function getByPageId($pageId, $list) {
		$pageId = (int) "$pageId";
		$list = $this->_list($list);
		if($list['type'] !== 'pages') throw new WireException("Given list does not use pages as subscribers");
		$page = $this->wire()->pages->findOne($this->pageSelector($list) . ", id=$pageId", array('allowCustom' => true));
		return $this->pageToSubscriber($page, $list);
	}

	/**
	 * Get a subscriber represented by a Page by email address
	 *
	 * @param string $email
	 * @param ProMailerList|int $list
	 * @return ProMailerSubscriber|bool
	 *
	 */
	protected function getByPageEmail($email, $list) {

		$email = strtolower($this->wire()->sanitizer->email($email));
		if(empty($email)) return false;

		$list = $this->_list($list);
		if($list['type'] !== 'pages') return $this->getByEmail($email, $list);

		$emailField = $this->getPageEmailField($list);
		$emailValue = $this->wire()->sanitizer->selectorValue($email);
	
		if($emailField && $emailValue) {
			$selector = $this->pageSelector($list) . ", $emailField=$emailValue";
			$page = $this->wire()->pages->findOne($selector, array('allowCustom' => true));
			if($page->id) {
				$subscriber = $this->pageToSubscriber($page, $list);
				return $subscriber;
			}
		}

		return false;
	}

	/**
	 * Get a subscriber by email or ID
	 *
	 * @param string|int $id Email address or ID
	 * @param int|ProMailerList $list List
	 * @return ProMailerSubscriber|bool
	 *
	 */
	public function get($id, $list = 0) {
		if($list) $list = $this->_list($list);
		if(is_int($id) || ctype_digit("$id")) {
			if($list['type'] === 'pages') return $this->getByPageId($id, $list);
			return $this->getById((int) $id, $list);
		} else if(strpos($id, '@')) {
			if($list['type'] === 'pages') return $this->getByPageEmail($id, $list);
			return $this->getByEmail($id, $list);
		} else {
			return false;
		}
	}

	/**
	 * Get the next subscriber for the given list
	 *
	 * @param int|ProMailerList $list
	 * @param int|ProMailerSubscriber $lastSubscriberId Last retrieved subscriber ID (or object) or 0 to start from beginning
	 * @param bool $confirmed Include only confirmed subscribers (default=true)
	 * @param bool $reverse Reverse order? (default=false)
	 * @return ProMailerSubscriber|bool|null Subscriber object or boolean false when there is no next subscriber, or null if other error
	 *
	 */
	public function getNext($list, $lastSubscriberId = 0, $confirmed = true, $reverse = false) {

		$list = $this->_list($list);
		$listId = $list['id'];

		if($lastSubscriberId instanceof ProMailerSubscriber) {
			if(!$lastSubscriberId['id']) return false;
			$lastSubscriberId = (int) $lastSubscriberId['id'];
		} else {
			$lastSubscriberId = (int) $lastSubscriberId;
			// $lastSubscriberId = (int) $this->wire('session')->getFor($this, "lastSubscriber$listId");
		}

		if($list['type'] === 'pages') return $this->getNextPage($list, $lastSubscriberId, $reverse);

		$operator = $reverse ? '<' : '>';
		$sql = "SELECT * FROM `$this->table` WHERE list_id=:list_id AND id" . $operator . ":id ";
		if($confirmed) $sql .= "AND confirmed>0 ";
		$sql .= "ORDER BY id ASC LIMIT 1";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':list_id', $listId, \PDO::PARAM_INT);
		$query->bindValue(':id', $lastSubscriberId, \PDO::PARAM_INT);

		try {
			$query->execute();
		} catch(\Exception $e) {
			$this->error($e->getMessage());
			return null;
		}

		if($query->rowCount()) {
			$row = $query->fetch(\PDO::FETCH_ASSOC);
			if(!$row) return null;
			$row['custom'] = strlen($row['data']) ? json_decode($row['data'], true) : array();
			$subscriber = $this->_subscriber($row, $list);
			// $this->wire('session')->setFor($this, "lastSubscriber$listId", $row['id']);
		} else {
			$subscriber = false;
		}

		$query->closeCursor();

		return $subscriber;
	}

	/**
	 * Get next Page-based subscriber
	 * 
	 * @param ProMailerList $list
	 * @param int|ProMailerSubscriber $lastSubscriberId
	 * @param bool $reverse
	 * @return ProMailerSubscriber|bool Returns subscriber array of boolean false if at the end of the list
	 * 
	 */
	protected function getNextPage($list, $lastSubscriberId, $reverse = false) {
		if(empty($list['selector'])) return false;
		$operator = $reverse ? '<' : '>';
		$selector = $this->pageSelector($list) . ", id$operator" . (int) "$lastSubscriberId";
		// @todo should the below line replace the above one?
		// $selector = $this->pageSelector($list) . ", start=0, id$operator" . (int) "$lastSubscriberId";
		$page = $this->wire()->pages->findOne($selector, array('allowCustom' => true));
		if(!$page->id) return false;
		return $this->pageToSubscriber($page, $list);
	}

	/**
	 * Get the URL to unsubscribe a particular subscriber from their list
	 * 
	 * This is an alias of the $promailer->forms->getUnsubscribeUrl() method
	 * If you want to hook this method, hook ProMailerForms:getUnsubscribeUrl()
	 * 
	 * @param ProMailerSubscriber|int $subscriber
	 * @return string
	 * 
	 */
	public function getUnsubscribeUrl($subscriber) {
		$subscriber = $this->subscribers->_subscriber($subscriber); 
		return $subscriber ? $this->forms->getUnsubscribeUrl($subscriber) : ''; 
	}
	
	/**
	 * Return total count of subscribers for given list
	 *
	 * @param array $options
	 *  - `list` (ProMailerList|int): List to count from 
	 *  - `confirmed` (bool): Include only confirmed subscribers? (default=false)
	 *  - `email` (string): String to match in email (default='')
	 *  - `custom` (string): String to match in custom data (default='')
	 *  - `partial` (bool): Perform partial match for strings? (default=true)
	 *  - `id` (int): Count only subscribers greater than or equal to this subscriber ID (default=0)
	 * @return int
	 *
	 */
	public function count(array $options = array()) {
		
		$defaults = array(
			'list' => null,
			'confirmed' => false,
			'email' => '',
			'custom' => '',
			'id' => 0,
		);

		$options = array_merge($defaults, $options);
		$list = $options['list'] ? $this->_list($options['list']) : null;
	
		if(strlen($options['email']) || strlen($options['custom'])) {
			if($list['type'] == 'pages') {
				throw new WireException("The text-search option is not supported for lists of type 'pages'");
			}
			$options['count'] = true;
			// delegate to find() method if keyword searches involved
			return $this->find($options);
		}
		
		if($list && $list['type'] === 'pages') {
			if(empty($list['selector'])) return 0;
			$selector = $this->pageSelector($list);
			if($options['id']) $selector .= ", id>=" . (int) $options['id'];
			$count = $this->wire()->pages->count($selector, array('allowCustom' => true));
			
		} else {
			$database = $this->wire()->database;
			$wheres = array();
			if($list) $wheres[] = "list_id=:list_id";
			if($options['confirmed']) $wheres[] = "confirmed>0";
			if($options['id']) $wheres[] = "id>=:id ";
			$sql = "SELECT COUNT(*) FROM `$this->table` "; 
			if(count($wheres)) $sql .= "WHERE " . implode(' AND ', $wheres);
			$query = $database->prepare($sql);
			if($list) $query->bindValue(':list_id', $list['id'], \PDO::PARAM_INT);
			if($options['id']) $query->bindValue(':id', $options['id'], \PDO::PARAM_INT);
			$query->execute();
			$count = (int) $query->fetchColumn();
			$query->closeCursor();
		}
		
		return $count;
	}

	/**
	 * Import subscribers from given CSV file
	 *
	 * First line must contain header line with one of them being "email".
	 * Columns other than "email" are placed in the "data" array of each subscriber.
	 * Imported subscribers are considered "confirmed".
	 * To import subscriber removals, prepend email address with a minus, i.e. "-you@domain.com".
	 *
	 * @param string $csvFile
	 * @param int|ProMailerList $list
	 * @param array $options
	 *  - `delimiter' (string): Delimiter for CSV fields, ",", ";", "\t", or "a" for auto-detect (default=',') 
	 *  - `length` (int): Maximum line length/chunk size (default=4096)
	 *  - `confirmed` (bool|string): Are these already opt-in/confirmed subscribers? (default=true)
	 * @return array|bool Returns false on fail or on success returns array with counts of 'added', 'failed', 'skipped', 'removed'
	 *
	 */
	public function ___importCSV($csvFile, $list, array $options = array()) {
		
		$defaults = array(
			'delimiter' => ',',
			'length' => 4096,
			'confirmed' => true, 
		);
		
		$options = array_merge($defaults, $options);

		$list = $this->_list($list, false);
		$listId = $list['id'];

		ini_set("auto_detect_line_endings", true);
		set_time_limit(3600);

		$fp = fopen($csvFile, 'r');
		if(!$fp) return false;
		
		if($options['delimiter'] === 'a' || $options['delimiter'] === 'auto') {
			// auto-detect delimiter
			$line = fgets($fp, $options['length']); 
			$delimiters = array(',', ';', "\t");
			$delimiter = null;
			foreach($delimiters as $d) {
				if(strpos($line, $d) === false) continue;
				$parts = explode($d, $line); // headers
				foreach($parts as $part) {
					// if there is a header named "email" then we've found our delmiter
					if(strtolower(trim(trim($part), '"\'')) !== 'email') continue;
					$delimiter = $d;
					break;
				}
				if($delimiter !== null) break;
			}
			if($delimiter === null) $delimiter = ',';
			$options['delimiter'] = $delimiter;
			rewind($fp);
		} else if($options['delimiter'] === 't' || $options['delimiter'] === 'tab') {
			$options['delimiter'] = "\t";
		}

		$header = fgetcsv($fp, $options['length'], $options['delimiter']);
		if(isset($header['0'])) $header[0] = str_replace("\xEF\xBB\xBF", "", $header[0]); 
		$valid = false;
		
		if(count($header) === 1) {
			// just one column in this CSV, assumed to be an email address
			// if first line is an email address then there is no header, so start from beginning
			if(strpos($header[0], '@')) {
				$valid = true;
				$header[0] = 'email';
				rewind($fp);
			} else if($header[0] === 'email') {
				$valid = true;
			}
		} else {
			foreach($header as $colName) {
				if($colName == 'email') $valid = true;
			}
		}

		if(!$valid) {
			$this->error($this->_('CSV file must contain ONLY emails OR have a header with column names with one named “email”.'));
			return false;
		}

		$result = array(
			'added' => 0,
			'failed' => 0,
			'skipped' => 0,
			'removed' => 0,
		);
		
		$this->saveLogDisabled = true;

		while(false !== ($row = fgetcsv($fp, $options['length'], $options['delimiter']))) {
			if(empty($row)) continue;
			$data = array();
			foreach($header as $key => $colName) {
				$data[$colName] = isset($row[$key]) ? $row[$key] : '';
			}
			if(empty($data['email'])) continue;
			$email = $data['email'];
			if(strpos($email, '-') === 0) {
				// remove subscriber
				$email = ltrim($email, '-');
				if($this->unsubscribe($email, $list)) {
					$result['removed']++;
				} else {
					$result['failed']++;
				}
				continue;
			}
			// remove anything from $data that translates to column names in the DB
			// what's left in $data will be encoded to the data property of the table
			foreach(array('email', 'data', 'id', 'code', 'created', 'confirmed') as $key) {
				unset($data[$key]);
			}
			
			$confirmed = $options['confirmed'];
			$subscriber = $this->add($email, $listId, $confirmed, $data);

			if($subscriber instanceof ProMailerSubscriber) {
				$result['added']++;
			} else if($subscriber === false) {
				$result['failed']++;
			} else if(is_int($subscriber)) {
				$result['skipped']++;
			}
		}
	
		$this->saveLogDisabled = false;
		$this->saveLog("IMPORTED $result[added] subscribers ($list[title])"); 

		return $result;
	}

	/**
	 * Export subscribers to CSV download
	 *
	 * @param int|ProMailerList $listId
	 *
	 */
	public function ___exportCSV($listId) {
		
		$list = $this->lists->_list($listId);
		$name = $this->wire()->sanitizer->pageName($list['title']); 
		
		set_time_limit(7200);
		
		header("Content-type: application/force-download");
		header("Content-Transfer-Encoding: Binary");
		header("Content-disposition: attachment; filename=$name.csv");

		$fp = fopen('php://output', 'w');
		// fwrite($fp, "\xEF\xBB\xBF"); // UTF-8 BOM: needed for some software to recognize UTF-8
		// @todo implementation
		
		$cols = array(
			'email', 
			'confirmed',
			'created',
			'num_sent',
			'num_bounce',
		);

		$sql = "SELECT " . implode(',', $cols) . ", data FROM `$this->table` WHERE list_id=:list_id ORDER BY id";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':list_id', $list['id'], \PDO::PARAM_INT);
		$query->execute();
		
		$customNames = array_keys($list->fields);
		
		foreach($customNames as $fieldName) {
			$cols[] = $fieldName;
		}
	
		// header row
		@fputcsv($fp, $cols);
		
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			if(empty($row['confirmed'])) {
				$row['confirmed'] = $this->_('Pending'); // Pending subscriber type (in CSV file)
			} else if($row['confirmed'] > 1) {
				$row['confirmed'] = wireDate('Y-m-d H:i:s', $row['confirmed']); 
			} else {
				$row['confirmed'] = $this->_('Manual'); // Manually-added subscriber type (in CSV file)
			}
			$row['created'] = empty($row['created']) ? '' : wireDate('Y-m-d H:i:s', $row['created']); 
			$data = empty($row['data']) ? array() : json_decode($row['data'], true);
			unset($row['data']);
			foreach($customNames as $fieldName) {
				if(isset($data[$fieldName]) && is_array($data[$fieldName])) $data[$fieldName] = implode("\n", $data[$fieldName]); 
				$row[$fieldName] = empty($data[$fieldName]) ? '' : $data[$fieldName];
			}
			@fputcsv($fp, $row);
		}
		
		fclose($fp);
		$query->closeCursor();
		exit;
	}

	/**
	 * Get the confirmation code for given subscriber array or Page
	 *
	 * @param ProMailerSubscriber|Page $subscriber
	 * @param bool $create Create and populate code if it doesn’t already exist? (default=false)
	 * @return string
	 *
	 */
	public function getCode($subscriber, $create = false) {
		if($subscriber instanceof Page) {
			$page = $subscriber;
			return $this->getPageCode($page);
		} else if(!$subscriber instanceof ProMailerSubscriber) {
			$subscriber = $this->_subscriber($subscriber);
		}
		return $this->getSubscriberCode($subscriber, $create); 	
	}

	/**
	 * Get code for a ProMailerSubscriber
	 * 
	 * @param ProMailerSubscriber $subscriber
	 * @param bool $create Create the code if not present?
	 * @return string
	 * 
	 */
	protected function ___getSubscriberCode(ProMailerSubscriber $subscriber, $create = false) {
		$code = $subscriber->code;
		if($create && !strlen($code)) {
			$code = $this->getRandomCode();
			$subscriber->code = $code;
			if($subscriber->id) $this->save($subscriber, array('code'));
		}
		return strlen($code) ? $code : '';
	}

	/**
	 * Get the code for a page-based subscriber
	 * 
	 * @param Page $page
	 * @return string
	 * 
	 */
	protected function ___getPageCode(Page $page) {
		// $this->wire('log')->save('promailer-test', "$page->id,$page->created,$page->name," . $page->get('email'));
		return md5("$page->id,$page->created,$page->name," . $page->get('email'));
	}

	/**
	 * Generate a random code of the given length
	 * 
	 * @param int $length
	 * @return string
	 * 
	 */
	protected function ___getRandomCode($length = 40) {
		if(wireClassExists('WireRandom')) {
			$rand = new WireRandom();
			$code = $rand->alphanumeric($length);
		} else {
			$code = '';
		}
		if(!strlen($code)) {
			while(strlen($code) <= $length) {
				$code .= (string) mt_rand();
			}
			$code = substr($code, 0, $length);
		}
		return $code;
	}

	/**
	 * Perform subscribers table maintenance
	 * 
	 * @throws WireException
	 * 
	 */
	protected function maintenance() {
		
		$table = $this->table();
		$database = $this->wire()->database;
		$maxBounce = (int) $this->promailer->get('maxBounce'); 
		
		if($maxBounce > 0) {
			$query = $database->prepare("SELECT id, list_id, email, num_bounce FROM `$table` WHERE num_bounce>=:max");
			$query->bindValue(':max', $maxBounce, \PDO::PARAM_INT); 
			$query->execute();
			$ids = array();
			while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
				$list = $this->lists->get((int) $row['list_id']);
				if(!$list) continue;
				$id = (int) $row['id'];
				if($this->bounceDeleteReady($row['email'], $list, $row['num_bounce'])) {
					$ids[$id] = $id;
					$this->saveLog("DELETED $row[email] ($list[title]) - too many bounces [$maxBounce]"); 
				}
			}
			$query->closeCursor();
			if(count($ids)) {
				$database->exec("DELETE FROM `$table` WHERE id IN(" . implode(',', $ids) . ")"); 
			}
		}
	}

	/**
	 * Ensure given data follows subscriber template
	 * 
	 * @param array|ProMailerSubscriber|Page|int $a
	 * @param int|ProMailerList $list
	 * @return ProMailerSubscriber
	 * @throws WireException
	 * 
	 */
	public function _subscriber($a, $list = 0) {
		
		$listID = $this->_id($list);
		$subscriber = null;
		
		if($a instanceof ProMailerSubscriber) {
			if($listID > 0) $a->list_id = $listID;
			$subscriber = $a;
		} else if($a instanceof Page) {
			if(!$list) throw new WireException('List is required');
			$list = $this->_list($list);
			if($list['type'] != 'pages') throw new WireException('List type must be pages when subscriber is a Page');
			$subscriber = $this->pageToSubscriber($a, $list);
		} else if(is_array($a)) {
			if($listID) $a['list_id'] = $listID;
			$subscriber = new ProMailerSubscriber($a); 
		} else if(is_int($a) || ctype_digit("$a")) {
			$subscriber = $this->get((int) $a, $list);
		}
	
		if(!$subscriber instanceof ProMailerSubscriber) {
			throw new WireException("Invalid subscriber");
		}
		
		$subscriber->setManager($this);
		
		return $subscriber;
	}

	/**
	 * Ensure given data follows list template
	 * 
	 * @param ProMailerList|array|int $a
	 * @param bool $allowPages
	 * @return ProMailerList
	 * @throws WireException
	 * 
	 */
	protected function _list($a, $allowPages = true) {
		return $this->lists->_list($a, $allowPages);
	}
	
	/**
	 * Convert Page to a subscriber array
	 *
	 * @param Page $page
	 * @param ProMailerList|int $list
	 * @return ProMailerSubscriber
	 * @throws WireException
	 *
	 */
	public function pageToSubscriber(Page $page, $list) {

		$list = $this->_list($list);
		
		if(empty($list) || $list['type'] !== 'pages') {
			throw new WireException('List must have type “pages” when subscriber is a Page');
		}

		// determine the email address
		$emailField = $this->pageSelector($list, array('getEmailField' => true));
		$email = $page->get($emailField);
		
		$subscriber = new ProMailerSubscriber(array(
			'type' => 'pages',
			'page' => $page,
			'id' => $page->id,
			'list_id' => $list['id'],
			'email' => $email,
			'code' => $this->getCode($page),
			'confirmed' => 1,
			'created' => $page->created,
		));
		$subscriber->setManager($this);
		
		return $subscriber;
	}


	/**
	 * Return selector for finding subscribers from given list
	 * 
	 * @param ProMailerList|int $list
	 * @param array $options
	 *  - `getTemplates` (bool): Return array of template names matched in the selector? (default=false)
	 *  - `getEmailField` (bool): Return name of matched email field in the selector? (default=false)
	 *  - `getInitValue` (bool): Get base selector without any custom selections added (default=false)
	 * @return string|array
	 * 
	 */
	public function pageSelector($list, array $options = array()) {
		
		$defaults = array(
			'getTemplates' => false,
			'getEmailField' => false,
			'getInitValue' => false, 
		);
		
		$options = array_merge($defaults, $options);
		$list = $this->_list($list);
		$hasInclude = false;
		$templates = array();
		$emailField = '';
		$emailFields = array();
		
		if($list['type'] !== 'pages') {
			throw new WireException('List type must be pages to use pageSelector() method');
		}
	
		foreach(new Selectors($list['selector']) as $s) {
			$name = $s->field();
			$value = $s->value();
			$op = $s->operator();
			if($name === 'status' || $name === 'include') {
				$hasInclude = $value;
			} else if($name === 'template') { 
				if($options['getTemplates'] && $op === '=') {
					$templates = $s->values();
				}
			} else if($options['getEmailField'] && !$emailField) {
				$field = $this->wire()->fields->get($name);
				if($field instanceof Field && $field->type instanceof FieldtypeEmail) {
					$emailFields[] = $name;
					$value = trim($value);
					if(!strlen($value) && ($op === '!=' || ($op === '=' && $s->not))) {
						$emailField = $name;
					}
				}
			}
		}
		
		if($options['getTemplates']) return $templates;
		
		if($options['getEmailField']) {
			if(!$emailField && count($emailFields)) $emailField = reset($emailFields);
			return $emailField;
		}
		
		$selector = $options['getInitValue'] ? "sort=id" : "$list[selector], sort=id";

		if(empty($list['unsub_field']) && $this->wire()->fields->get(ProMailer::unsubscribeFieldName)) {
			$list['unsub_field'] = ProMailer::unsubscribeFieldName;
		}
		if(!empty($list['unsub_field'])) {
			$selector .= ", " . $list['unsub_field'] . '=0';
		}

		if($hasInclude === false) {
			$selector .= ", status<" . Page::statusUnpublished;
		}
		
		// @todo check_access=0 for background send?
		
		return trim($selector, ', ');
	}

	/**
	 * Get array of template names used by subscriber pages
	 * 
	 * @param ProMailerList|int $list
	 * @return array
	 * 
	 */
	public function getPageTemplates($list) {
		return $this->pageSelector($list, array('getTemplates' => true));
	}

	/**
	 * Get name of field used for email address on subscriber pages
	 * 
	 * @param ProMailerList|int $list
	 * @return string
	 * 
	 */
	public function getPageEmailField($list) {
		return $this->pageSelector($list, array('getEmailField' => true));
	}

	/**
	 * Get fields allowed for sorting of subscribers
	 * 
	 * @return array
	 * 
	 */
	public function getAllowedSorts() {
		return $this->sorts;
	}

	/**
	 * Get default sort for subscribers
	 * 
	 * @return string
	 * 
	 */
	public function getDefaultSort() {
		return $this->defaultSort;
	}

	/**
	 * Save entry to subscribers log
	 * 
	 * @param string $str
	 * 
	 */
	public function saveLog($str) {
		$useLogs = $this->promailer->useLogs;
		if($this->saveLogDisabled || !in_array(ProMailer::useLogSubscribers, $useLogs)) return;
		if(in_array(ProMailer::useLogSubscribersIPs, $useLogs)) $str .= ' IP:'. $this->wire()->session->getIP();
		$this->wire()->log->save('promailer-subscribers', $str); 
	}

	/**
	 * Disable or enable logging or return current state if given no argumenets
	 * 
	 * @param bool $disabled
	 * @return bool
	 * 
	 */
	public function saveLogDisabled($disabled = null) {
		if(is_bool($disabled)) $this->saveLogDisabled = $disabled;
		return $this->saveLogDisabled;
	}
	
	public function table() {
		return ProMailer::subscribersTable;
	}
	
	public function install() {
		$database = $this->wire()->database;
		$engine = $this->wire()->config->dbEngine;
		$charset = $this->wire()->config->dbCharset;
		$table = $this->table();
		
		$database->exec("
			CREATE TABLE `$table` (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				list_id INT UNSIGNED NOT NULL, 
				flags INT UNSIGNED NOT NULL DEFAULT 0,
				email VARCHAR(150) NOT NULL,
				data MEDIUMTEXT,
				code VARCHAR(40),
				confirmed INT UNSIGNED NOT NULL DEFAULT 0,
				created INT UNSIGNED NOT NULL DEFAULT 0,
				num_sent INT UNSIGNED NOT NULL DEFAULT 0, 
				num_bounce INT UNSIGNED NOT NULL DEFAULT 0,
				UNIQUE list_email (list_id, email),
				INDEX list_id_id (list_id, id),
				INDEX list_id_created (list_id, created),
				INDEX list_id_confirmed (list_id, confirmed),
				INDEX code (code),
				INDEX num_sent_bounce (num_sent, num_bounce)
			) ENGINE=$engine DEFAULT CHARSET=$charset
		");
	}
	
	public function upgrade($fromVersion, $toVersion) {
		
		$database = $this->wire()->database;
		$table = $this->table();
		
		$query = $database->prepare("SHOW COLUMNS FROM `$table` LIKE 'data'");
		$query->execute();
		if(!$query->rowCount()) {
			$database->exec("ALTER TABLE `$table` ADD data MEDIUMTEXT"); 
		}
		
		return parent::upgrade($fromVersion, $toVersion);
	}
	
	/*** HOOKS ***********************************************************************/

	/**
	 * Ready to add new subscriber
	 * 
	 * @param string $email
	 * @param ProMailerList $list
	 * @param bool $confirmed
	 * @param array $data
	 * @return bool Return false if subscriber should not be added
	 * 
	 */
	public function ___addReady($email, ProMailerList $list, $confirmed, array $data) {
		$mail = $this->wire()->mail;
		if($mail && method_exists($mail, '___isBlacklistEmail')) { // pw 3.0.129+
			if($mail->isBlacklistEmail($email)) {
				$this->error(sprintf($this->_('Email not allowed due to blacklist rules: %s'), $email)); 
				return false;
			}
		}
		return true;
	}

	/**
	 * New subscriber added
	 * 
	 * @param ProMailerSubscriber $subscriber
	 * 
	 */
	public function ___added(ProMailerSubscriber $subscriber) {
	}

	/**
	 * Subscriber ready to save
	 * 
	 * @param ProMailerSubscriber $subscriber
	 * @param array $properties Only save these properties, or blank for all properties
	 * @return bool Return false if subscriber should not be saved
	 * 
	 */
	public function ___saveReady(ProMailerSubscriber $subscriber, array $properties) {
		return true;
	}

	/**
	 * Subscriber saved
	 * 
	 * @param ProMailerSubscriber $subscriber
	 * @param array $properties Only save these properties, or blank for all properties
	 * 
	 */
	public function ___saved(ProMailerSubscriber $subscriber, array $properties) {
	}

	/**
	 * Ready to confirm an email
	 * 
	 * @param string $email
	 * @param ProMailerList $list
	 * @param string $code
	 * @return bool Return false if email should not be confirmed
	 * 
	 */
	public function ___confirmEmailReady($email, ProMailerList $list, $code) {
		return true;
	}

	/**
	 * Ready to confirm subscriber
	 * 
	 * @param ProMailerSubscriber $subscriber
	 * @param ProMailerList $list
	 * @param string $code
	 * @return bool Return false if email should not be confirmed
	 * 
	 * 
	 */
	public function ___confirmSubscriberReady(ProMailerSubscriber $subscriber, ProMailerList $list, $code) {
		$subscriber->setConfData(array()); // clear confirmation email data
		return true;
	}

	/**
	 * Email confirmed
	 * 
	 * Please note this is not called for manually confirmed subscribers (like those from the admin)
	 *
	 * @param string $email
	 * @param ProMailerList $list
	 * @param string $code Unique code used by user to unsubscribe
	 *
	 */
	public function ___confirmedEmail($email, ProMailerList $list, $code) {
	}

	/**
	 * Subscriber confirmed (alternative to confirmedEmail)
	 * 
	 * Please note this is not called for manually confirmed subscribers (like those from the admin)
	 *
	 * @param ProMailerSubscriber $subscriber
	 * @param ProMailerList $list
	 * @param string $code Unique code used by user to unsubscribe
	 *
	 */
	public function ___confirmedSubscriber(ProMailerSubscriber $subscriber, ProMailerList $list, $code) {
	}

	/**
	 * Subscriber removed from list
	 * 
	 * @param ProMailerSubscriber $subscriber
	 * @param ProMailerList $list
	 * 
	 */
	public function ___unsubscribed(ProMailerSubscriber $subscriber, ProMailerList $list) {
	}

	/**
	 * Email address removed from ALL lists
	 * 
	 * @param string $email
	 * 
	 */
	public function ___unsubscribedEmail($email) {
	}

	/**
	 * Page-based subscriber unsubscribed from pages-type list
	 * 
	 * @param Page $subscriber
	 * @param ProMailerList $list
	 * 
	 */
	public function ___unsubscribedPage(Page $subscriber, ProMailerList $list) {
	}
}