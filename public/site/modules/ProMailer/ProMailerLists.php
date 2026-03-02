<?php namespace ProcessWire;

/**
 * ProcessWire ProMailer Lists
 *
 * Copyright 2023 by Ryan Cramer
 *
 *
 */
class ProMailerLists extends ProMailerTypeManager {
	
	/**
	 * Cache of loaded lists indexed by list ID
	 *
	 * @var array
	 *
	 */
	protected $listsCache = array();

	/**
	 * Allowed list types
	 * 
	 * @var array
	 * 
	 */
	protected $listTypes = array(
		ProMailer::listTypeRegular,
		ProMailer::listTypePages,
	);

	/**
	 * Get ProMailerList for given list ID or boolean false if not found
	 *
	 * @param int $listId
	 * @return ProMailerList|bool
	 *
	 */
	public function get($listId) {
		$listId = $this->_id($listId);
		if(isset($this->listsCache[$listId])) return $this->listsCache[$listId];
		$lists = $this->getAll($listId);
		$list = isset($lists[$listId]) ? $lists[$listId] : false;
		return $list;
	}

	/**
	 * Get all lists in an array
	 *
	 * @param int $listId Optionally limit to this listId
	 * @return ProMailerList[] array of ProMailerList objects indexed by list ID
	 *
	 */
	public function getAll($listId = 0) { 

		$lists = array();
		$database = $this->wire()->database;

		$sql = "SELECT * FROM $this->table " . ($listId ? "WHERE id=:id" : "ORDER BY created");
		$query = $database->prepare($sql);
		if($listId) $query->bindValue(':id', $listId, \PDO::PARAM_INT);

		$query->execute();

		while($list = $query->fetch(\PDO::FETCH_ASSOC)) {
			$listId = $list['id'];
			$data = empty($list['data']) ? array() : json_decode($list['data'], true);
			$fields = empty($data['fields']) ? array() : $data['fields'];
			$list['closed'] = empty($data['closed']) ? false : true;
			$list['type'] = empty($data['type']) ? '' : $data['type'];
			$list['selector'] = empty($data['selector']) ? '' : $data['selector'];
			$list['unsub_field'] = empty($data['unsub_field']) ? '' : $data['unsub_field'];
			$list['unsub_email'] = empty($data['unsub_email']) ? '' : $data['unsub_email'];
			unset($data['closed'], $data['fields'], $data['type'], $data['selector'], $data['unsub_field'], $data['unsub_email']);
			$list['custom'] = $data; // any data that remains is custom data
			
			$list = $this->_list($list); // convert array to ProMailerList
			if(!$list) continue;
			
			$list->set('fields', $fields);
			$lists[$listId] = $list;
			$this->listsCache[$listId] = $list;
			$list->resetTrackChanges(true);
		}

		$query->closeCursor();

		return $lists;
	}
	
	/**
	 * Return array of all lists open for subscription
	 * 
	 * Note that this does not include pages-type lists
	 *
	 * @param bool|int $first Specify true to to only return the first open list or specify 1 to get first open list ID. (default=false)
	 * @return ProMailerList[]|int|null
	 *
	 */
	public function getOpen($first = false) {
		$lists = array();
		foreach($this->getAll() as $list) {
			if($list['closed'] || $list['type'] === ProMailer::listTypePages) continue;
			$lists[] = $list;
		}
		if($first === true) return count($lists) ? $lists[0] : null;
		if($first === 1) return count($lists) ? $lists[0]['id'] : 0;
		return $lists;
	}

	/**
	 * Add a new list with given title and return the ID for it
	 *
	 * @param string $title
	 * @param string $type List type, use ProMailer::listTypeRegular for regular or ProMailer::listTypePages for Users/Pages
	 * @return int
	 *
	 */
	public function add($title, $type = '') {
		
		$database = $this->wire()->database;
		
		if($type && !in_array($type, $this->listTypes)) throw new WireException("Invalid list type: $type");
		
		$data = strlen($type) ? array('type' => $type) : array();
		$sql = "INSERT INTO $this->table SET title=:title, created=:created, data=:data";
		
		$query = $database->prepare($sql);
		$query->bindValue(':title', $title);
		$query->bindValue(':created', time());
		$query->bindValue(':data', json_encode($data));
		$query->execute();
		
		return (int) $database->lastInsertId();
	}

	/**
	 * Remove list and all subscribers in it
	 *
	 * @param ProMailerList $list
	 * @return bool
	 *
	 */
	public function remove(ProMailerList $list) {
		
		if(!$list->id) return false;
		
		$database = $this->wire()->database;
		
		if($list['type'] !== ProMailer::listTypePages) {
			$this->subscribers->unsubscribeAllFromList($list);
		}
		
		$sql = "DELETE FROM $this->table WHERE id=:id";
		
		$query = $database->prepare($sql);
		$query->bindValue(':id', $list['id'], \PDO::PARAM_INT);
		$query->execute();
		
		$result = $query->rowCount();
		$query->closeCursor();
		
		return (bool) $result;
	}

	/**
	 * Save the given list
	 *
	 * @param ProMailerList $list
	 * @return bool
	 * @throws WireException
	 *
	 */
	public function save(ProMailerList $list) {
		
		$database = $this->wire()->database;
		
		if(!strlen($list['title'])) throw new WireException("List must have a title"); 
		if(!$list['id']) throw new WireException("List must have an id"); 
		
		$list = $list->getArray();
		$data = is_array($list['custom']) ? $list['custom'] : array();
		$data['closed'] = empty($list['closed']) ? false : true;
		$data['fields'] = empty($list['fields']) || !is_array($list['fields']) ? array() : $list['fields'];
		$data['type'] = empty($list['type']) ? '' : $list['type'];
		$data['selector'] = empty($list['selector']) ? '' : $list['selector'];
		$data['unsub_field'] = empty($list['unsub_field']) ? '' : $list['unsub_field'];
		$data['unsub_email'] = $list['unsub_email'] ? $list['unsub_email'] : '';
		$data['unsub_list_id'] = (int) $list['unsub_list_id'];
		
		$removeEmpties = array('unsub_field', 'unsub_email', 'unsub_list_id');
		foreach($removeEmpties as $key) {
			if(empty($data[$key])) unset($data[$key]);
		}
		
		$sql = "UPDATE $this->table SET title=:title, last_sent=:last_sent, data=:data WHERE id=:id";
		
		$query = $database->prepare($sql);
		$query->bindValue(':title', $list['title']);
		$query->bindValue(':last_sent', $list['last_sent'], \PDO::PARAM_INT);
		$query->bindValue(':data', json_encode($data));
		$query->bindValue(':id', $list['id'], \PDO::PARAM_INT);
		
		return $query->execute();
	}

	/**
	 * Update last sent date of list
	 * 
	 * This is called automatically after a message is sent to any subscriber in a list. 
	 *
	 * @param int|ProMailerList $list
	 * @param int $lastSent Unix timestamp or omit for current time
	 * @return bool
	 *
	 */
	public function saveSent($list, $lastSent = 0) {
		$listId = $this->_id($list);
		if(!$lastSent) $lastSent = time();
		$sql = "UPDATE $this->table SET last_sent=:last_sent WHERE id=:id";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':last_sent', $lastSent, \PDO::PARAM_INT);
		$query->bindValue(':id', $listId, \PDO::PARAM_INT);
		return $query->execute();
	}

	/**
	 * Return instance of ProMailerListFields
	 * 
	 * This is intended only for calling by ProMailerList instances to serve
	 * as a shared instance for all of them. 
	 * 
	 * #pw-internal
	 * 
	 * @return ProMailerListFields
	 * 
	 */
	public static function listFields() {
		static $listFields = null;
		if($listFields) return $listFields;
		require_once(__DIR__ . '/ProMailerListFields.php');
		$listFields = new ProMailerListFields();
		return $listFields;
	}

	/**
	 * Ensure list is in correct format and/or convert list ID or array to ProMailerList
	 * 
	 * @param ProMailerList|int|array $a
	 * @param bool $allowPages Allow for pages-type lists? 
	 * @return ProMailerList
	 * @throws WireException
	 * 
	 */
	public function _list($a, $allowPages = true) {
	
		if($a instanceof ProMailerList) {
			// good
			$a->setManager($this);
		} else if(is_array($a)) {
			$a = new ProMailerList($a); 
			$a->setManager($this);
		} else if($a) {
			$a = $this->get($a);
		}
		
		if(!$a) throw new WireException("Invalid list");
		
		if(!$allowPages && $a['type'] === 'pages') {
			throw new WireException('Pages-type lists not allowed with this function');
		}
		
		return $a;
	}
	
	public function table() {
		return ProMailer::listsTable;
	}
	
	public function install() {
		$config = $this->wire()->config;
		$engine = $config->dbEngine;
		$charset = $config->dbCharset;
		$this->wire()->database->exec("
			CREATE TABLE `$this->table` (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				title VARCHAR(200) NOT NULL, 
				created INT UNSIGNED NOT NULL,
				data MEDIUMTEXT,
				last_sent INT UNSIGNED NOT NULL DEFAULT 0
			) ENGINE=$engine DEFAULT CHARSET=$charset
		");
		return parent::install();
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

}