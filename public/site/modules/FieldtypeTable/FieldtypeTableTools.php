<?php namespace ProcessWire;

class FieldtypeTableTools extends Wire {
	
	protected $fieldtype;
	
	public function __construct(FieldtypeTable $fieldtype) {
		$this->fieldtype = $fieldtype;
		parent::__construct();
		$fieldtype->wire($this);
	}

	/**
	 * Get all file columns for given Table field
	 * 
	 * @param Field $field
	 * @return array
	 * 
	 */
	public function getFileCols(Field $field) {
		$fileCols = array();
		$maxCols = (int) $field->get('maxCols');
		for($n = 1; $n <= $maxCols; $n++) {
			$type = $field->get("col{$n}type");
			if($type === 'file' || $type === 'image') {
				$fileCols[$n] = $this->fieldtype->getColumn($field, $n);
			}
		}
		return $fileCols;
	}

	/**
	 * Get array of full path/file for all files managed by given page and field
	 *
	 * This method requires ProcessWire 3.0.233+
	 *
	 * #pw-internal
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param bool $hasFiles
	 * @return array|bool
	 *
	 */
	public function getFilesFromDatabase(Page $page, Field $field, $hasFiles = false) {

		$getFiles = array();
		$fileCols = $this->getFileCols($field);

		if(!count($fileCols)) return ($hasFiles ? false : $getFiles);

		$database = $this->wire()->database;
		$table = $field->getTable();

		foreach($fileCols as $col) {

			$sql =
				"SELECT $col[name] FROM $table " .
				"WHERE pages_id=:pages_id AND $col[name] IS NOT NULL AND $col[name]!='' " .
				($hasFiles ? "LIMIT 1" : "");

			$query = $database->prepare($sql);
			$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
			$query->execute();

			if(!$query->rowCount()) continue;

			if($hasFiles) {
				// early exit if only checking if any files are present
				$getFiles[] = 'placeholder';
				$query->closeCursor();
				break;
			}

			while($row = $query->fetch(\PDO::FETCH_NUM)) {
				if(empty($row[0])) continue;
				/** @var Pagefile|Pageimage $value */
				$value = $this->fieldtype->wakeupFile($page, $field, $row[0], $col['type']);
				if(!$value instanceof Pagefile) continue;
				$filename = $value->filename();
				if(method_exists($value, 'getFiles')) { // 3.0.233+
					$getFiles = array_merge($getFiles, $value->getFiles());
				} else {
					$getFiles[] = $filename;
				}
			}

			$query->closeCursor();
		}

		return ($hasFiles ? count($getFiles) > 0 : $getFiles);
	}

	/**
	 * Get files when given $page is not a live version of the page
	 *
	 * This gets files from the $page instance rather than the database.
	 *
	 * #pw-internal
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param bool $hasFiles
	 * @return array|bool
	 *
	 */
	public function getFilesFromPage(Page $page, Field $field, $hasFiles = false) {

		$getFiles = array();
		$fileCols = $this->getFileCols($field);

		if(!count($fileCols)) {
			// no file columns, so on files possible
			return ($hasFiles ? false : $getFiles);
		}

		foreach($page->get($field->name) as $row) {
			/** @var TableRow $row */
			foreach($fileCols as $col) {
				$value = $row->get($col['name']);
				if(!$value instanceof Pagefile) continue;
				$filename = $value->filename();
				if(method_exists($value, 'getFiles')) { // 3.0.233+
					$getFiles = array_merge($getFiles, $value->getFiles());
				} else {
					$getFiles[] = $filename;
				}
				if($hasFiles) break; // stop at 1 if only checking hasFiles
			}
		}

		return ($hasFiles ? count($getFiles) > 0 : $getFiles);
	}


}