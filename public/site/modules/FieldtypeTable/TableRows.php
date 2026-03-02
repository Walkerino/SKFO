<?php namespace ProcessWire;

/**
 * ProcessWire Table Fieldtype: Rows
 *
 * Part of the ProFields package
 * Please do not distribute. 
 * 
 * Copyright 2024 by Ryan Cramer
 *
 * https://processwire.com
 *
 * @property array $labels
 * @proeprty array $columns
 * 
 * @method string render(array $options = array())
 * @method TableRow new(array $data = array())
 *
 *
 */

class TableRows extends PaginatedArray {

	/**
	 * @var Field|null
	 * 
	 */
	protected $field = null;

	/**
	 * @var null|Page
	 * 
	 */
	protected $page = null;

	/**
	 * Is this a formatted value?
	 * 
	 * @var bool
	 * 
	 */
	protected $isFormatted = false;

	/**
	 * Construct
	 * 
	 * @param Page $page
	 * @param Field $field
	 * 
	 */
	public function __construct(Page $page, Field $field) {
		parent::__construct();
		$page->wire($this);
		$this->page = $page; 
		$this->field = $field; 
	}

	/**
	 * @return TableRow
	 * 
	 */
	protected function newTableRow() {
		if($this->field instanceof TableField) {
			$class = $this->field->getRowClass();
			$row = new $class($this->field);
		} else {
			$row = new TableRow($this->field);
		}
		return $row;
	}

	/**
	 * Is item valid for this TableRows?
	 * 
	 * @param $item
	 * @return bool
	 * 
	 */
	public function isValidItem($item) {
		return $item instanceof TableRow;
	}

	/**
	 * Make a new/blank TableRow item
	 * 
	 * @return TableRow
	 * 
	 */
	public function makeBlankItem() {
		$row = $this->newTableRow();
		$this->setupBlankRow($row);
		return $row; 
	}

	/**
	 * Create a new TableRow item and add to this TableRows
	 * 
	 * This is different from makeBlankItem() because it automatically adds the new item
	 * to this TableRows object, plus it lets you provide an array of data to populate to 
	 * the new TableRow. Note that you still have to $page->save(); before the data is 
	 * committed to the database. 
	 * 
	 * @param array $data Optional associative array of data to populate to the new row 
	 * @return TableRow New item (already added to this TableRows)
	 * 
	 */
	public function ___new(array $data = array()) {
		$row = $this->newTableRow();
		$this->setupBlankRow($row, $data);
		$this->add($row);
		return $row;
	}

	/**
	 * Make a new TableRows object
	 * 
	 * @return TableRows
	 * 
	 */
	public function makeNew() {
		$class = $this->className(true);
		return new $class($this->page, $this->field); 
	}

	/**
	 * Get a 'labels' or 'columns' property (or other WireArray property)
	 * 
	 * @param string $name
	 * @return array
	 * 
	 */
	public function __get($name) {
		if($name === 'labels') return $this->getLabels();
		if($name === 'columns') return $this->getColumns();
		return parent::__get($name); 
	}

	/**
	 * Return array of information about all columns
	 *
	 * @return array
	 *
	 */
	public function getColumns() {
		/** @var FieldtypeTable $type */
		$type = $this->field->type;
		return $type->getColumns($this->field); 
	}

	/**
	 * Return array of information about the column
	 *
	 * @param int|string Column number or name
	 * @return array
	 *
	 */
	public function getColumn($n) {
		/** @var FieldtypeTable $type */
		$type = $this->field->type;
		return $type->getColumn($this->field, $n); 
	}

	/**
	 * Return array of all column labels (indexed by column name)
	 *
	 * @return array
	 *
	 */
	public function getLabels() {
		$labels = array();
		$columns = $this->getColumns();
		$language = $this->wire()->languages ? $this->wire()->user->language : null;
		if($language && $language->isDefault()) $language = null;
		foreach($columns as $col) {
			$label = $language && !empty($col["label$language->id"]) ? $col["label$language->id"] : $col["label"];
			$labels[$col['name']] = $label;
		}
		return $labels;
	}

	/**
	 * Return the label for this column
	 *
	 * @param int|string Column number or name
	 * @return string
	 *
	 */
	public function getLabel($n) {
		$col = $this->getColumn($n);
		$language = $this->wire()->languages ? $this->wire()->user->language : null;
		$label = $language && !empty($col["label$language->id"]) ? $col["label$language->id"] : $col["label"];
		return $label;
	}

	/**
	 * Render a basic table containing the data from this TableRows
	 *
	 * @param array $options 
	 *	`tableClass` (string): class name for table (default=ft-table)
	 *	`columnClass` (string): class name for each column, col name will be appended: (default=ft-table-col)
	 * 	`columns` (array): column names to render, or omit to render all.
	 *	`useWidth` (bool): whether to use width attributes in columns (default=true)
	 *  `useHead` (bool): use a table header <thead> section? (default=true)
	 *  `thead` (string): contents of the <thead> section or omit to auto-generate.
	 * @return string
	 *
	 */
	public function ___render($options = array()) {
		if(!$this->count()) return '';
		require_once(__DIR__ . '/TableRowsRender.php'); 
		$o = new TableRowsRender($this);
		return $o->render($options);
	}

	/**
	 * Clone this field value
	 *
	 * Ensure that the individual items are cloned and that they have no ID property.
	 * 
	 */
	public function __clone() {
		// skip clone behavior when output formatting is off
		if(!$this->page->of() && !$this->data('preventSaving')) return;
		
		foreach($this->data as $key => $item) {
			/** @var TableRow $copy */
			$copy = clone $item;
			$copy->id = null;
			$this->data[$key] = $copy;
		}
	}

	/**
	 * String value of TableRows object is the rendered output
	 * 
	 * @return string
	 * 
	 */
	public function __toString() {
		if($this->page && $this->page->of()) {
			return (string) $this->render();
		} else {
			return (string) parent::__toString();
		}
	}

	/**
	 * Import CSV file
	 * 
	 * Given CSV file must have the first line as the header containing column names that
	 * map exactly to the column names in this FieldtypeTable field. 
	 * 
	 * @param string $csvFile
	 * @param array $options 
	 * @return int Number of rows imported
	 * @throws WireException
	 * 
	 */
	public function importCSV($csvFile, array $options = array()) {
		
		if(!$this->page || !$this->field) return 0;
		
		$defaults = array(
			'delimiter' => ',',
			'enclosure' => '"', 
			'escape' => "\\", 
			'start' => 0, // row to start from
			'limit' => 0, // max rows to import
		);
		
		/** @var FieldtypeTable $fieldtype */
		$fieldtype = $this->field->type;
		$options = array_merge($defaults, $options);
		$fp = fopen($csvFile, "r");
		if(!$fp) return 0;
		$total = 0;
		$limitCnt = 0;
		$cnt = 0;
		$paginationLimit = $this->field->get('paginationLimit');
		
		if($paginationLimit) {
			$rows = $this->makeNew();
		} else {
			$rows = $this;
		}

		set_time_limit(3600 * 12);
		$header = fgetcsv($fp, 0, $options['delimiter'], $options['enclosure'], $options['escape']); // header line
		
		while(($data = fgetcsv($fp, 0, $options['delimiter'], $options['enclosure'], $options['escape'])) !== false) {
			if($options['limit'] && $limitCnt++ >= $options['limit']) break;
			$total++;
			if($options['start'] && $total < $options['start']) continue;
			$a = array();
			foreach($header as $key => $name) {
				$a[$name] = $data[$key];	
			}
			$item = $this->new($a);
			$rows->add($item);
			if($paginationLimit && ++$cnt >= $paginationLimit) {
				$fieldtype->savePageFieldRows($this->page, $this->field, $rows);
				$rows = $this->makeNew();
				$cnt = 0;
			}
		}
		
		fclose($fp);

		if($paginationLimit) {
			if($cnt) $fieldtype->savePageFieldRows($this->page, $this->field, $rows);
		} else {
			$this->page->set($this->field->name, $rows);
			$this->page->save($this->field->name);
		}
		
		return $total;
	}
	
	/**
	 * Prepare a blank TableRow item with correct value types
	 *
	 * @param TableRow $row
	 * @param array $data Optional associative array of data to populate
	 * @return bool
	 *
	 */
	protected function setupBlankRow(TableRow $row, array $data = array()) {
		if(!$this->page || !$this->field) return false;

		/** @var FieldtypeTable $type */
		$type = $this->field->type;

		foreach($type->getColumns($this->field) as $col) {

			$name = $col['name'];
			$value = null;

			switch($col['valid']) {
				case 'PageArray':
					$value = new PageArray();
					break;
				case 'Page':
					$value = new NullPage();
					break;
				case 'array':
					$value = array();
					break;
				case 'textLanguage':
				case 'textareaLanguage':
				case 'LanguagesPageFieldValue':
					/** @var LanguagesPageFieldValue $value */
					$value = $type->newLanguagesPageFieldValue($this->page, $this->field);
					break;
			}

			if($value !== null) $row->set($name, $value);
		}

		if(count($data)) {
			foreach($data as $name => $value) {
				$row->setSafe($name, $value);
			}
		}

		return true;
	}
	
	public function getPage() {
		return $this->page;
	}
	public function setPage(Page $page) {
		$this->page = $page;
	}
	public function getField() {
		return $this->field;
	}
	public function setField(Field $field) {
		$this->field = $field;
	}

	/**
	 * Get or set whether this is a formatted value
	 * 
	 * @param null|bool $set Omit to get, specify bool to set
	 * @return bool
	 * 
	 */
	public function isFormatted($set = null) {
		if(is_bool($set)) $this->isFormatted = $set;
		return $this->isFormatted;
	}


}