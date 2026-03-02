<?php namespace ProcessWire;

/**
 * ProFields: Query/selector support for Custom Fieldtype
 * 
 * THIS IS PART OF A COMMERCIAL MODULE: DO NOT DISTRIBUTE.
 * This file should NOT be uploaded to GitHub or available for download on any public site.
 *
 * Copyright 2024 by Ryan Cramer Design, LLC
 * ryan@processwire.com
 * 
 * This would typically be in the FieldtypeCustom class but is here
 * instead to isolate and focus on this particular need since it 
 * requires more code than most Fieldtypes. 
 * 
 * 
 */
class FieldtypeCustomQuery extends Wire {
	
	/**
	 * @var CustomField|Field
	 *
	 */
	protected $field;

	/**
	 * @var FieldtypeCustom
	 * 
	 */
	protected $fieldtype;

	/**
	 * Query populated by getMatchQuery
	 * 
	 * @var PageFinderDatabaseQuerySelect 
	 * 
	 */
	protected $query = null;

	/**
	 * Table populated by getMatchQuery
	 * 
	 * @var string 
	 * 
	 */
	protected $table = '';

	/**
	 * Operator populated by getMatchQuery
	 * 
	 * @var string
	 * 
	 */
	protected $operator = '=';

	/**
	 * Value populated by getMatchQuery
	 * 
	 * @var string
	 * 
	 */
	protected $value = '';

	/**
	 * @param CustomField|Field $field
	 * 
	 */
	public function __construct(Field $field) {
		$this->field = $field;
		$this->fieldtype = $field->type;
		parent::__construct();
		$this->field->wire($this);
	}

	/**
	 * Get selector info
	 * 
	 * @param array $info
	 * @return array
	 * 
	 */
	public function getSelectorInfo( array $info): array {

		$inputfield = $this->fieldtype->getInputfield(new Page(), $this->field);
		$flatInputfields = $inputfield->defs()->getFlatInputfields();

		// extra operators to add when indicated one is present
		$addOperators = [
			'%=' => [ '*=', '~=', '~|=' ]
		];

		// operators to replace for select types
		$replaceSelectOperators = [
			'=' => '@=',
			'!=' => '@!='
		];

		$labels = [];

		foreach($info['subfields'] as $name => $subfield) {

			if(empty($subfield['operators'])) $subfield['operators'] = [];

			if(isset($flatInputfields[$name])) {
				$f = $flatInputfields[$name];
				$label = $f->label;
				//$parent = $f->getSetting(CustomFieldDefs::_parent_inputfield); 
				//if($parent) $label = "$label ($parent->label)";
				$subfield['label'] = $label;
				if(!isset($labels[$label])) $labels[$label] = [];
				$labels[$label][$name] = $f;
			} else {
				$f = null;
			}

			$operators = $subfield['operators'];

			foreach($addOperators as $testOp => $addOps) {
				if(!in_array($testOp, $operators)) continue;
				foreach($addOps as $op) {
					if(!in_array($op, $operators)) $operators[] = $op;
				}
				$subfield['operators'] = $operators;
			}

			if($subfield['input'] === 'select') { 
				if($f instanceof InputfieldSelect) {
					$subfield['options'] = $f->getOptions();
					foreach($subfield['operators'] as $key => $op) {
						if(isset($replaceSelectOperators[$op])) {
							$subfield['operators'][$key] = $replaceSelectOperators[$op];
						}
					}
				} else if($f instanceof InputfieldPage) {
					$subfield = $this->getSelectorInfoPage($f, $subfield);
				}
			}

			$info['subfields'][$name] = $subfield;
		}

		// find any duplicate labels and expand them to include parent label or subfield name
		foreach($labels as $label => $subfields) {
			if(count($subfields) < 2) continue;
			foreach($subfields as $name => $f) {
				$parent = $f->getSetting(CustomFieldDefs::_parent_inputfield);
				if($parent) {
					$newLabel = "$parent->label > $label";
				} else {
					$newLabel = "$label ($name)";
				}
				$info['subfields'][$name]['label'] = $newLabel;
			}
		}

		return $info;
	}

	/**
	 * Get selector info for Page reference subfield
	 * 
	 * @param Inputfield $f
	 * @param array $subfield
	 * @return array
	 * 
	 */
	protected function getSelectorInfoPage(Inputfield $f, array $subfield): array {
		
		$labelFieldName = $f->labelFieldName;
		$settings = [ 'parent_id', 'template_id', 'findPagesSelector', 'findPagesSelect' ];
		$selector = [];
		
		if(empty($labelFieldName)) $labelFieldName = 'title|name';
		
		foreach($settings as $k) {
			$v = (string) $f->get($k);
			if(!strlen($v) || $v === '0') continue;
			if($k === 'template_id') $k = 'templates_id';
			$selector[] = strpos($k, '_id') ? "$k=$v" : $v;
		}
		
		$subfield['input'] = 'autocomplete';
		$subfield['selector'] = implode(', ', $selector);
		$subfield['operators'] = [ '@=', '@!=', '=""', '!=""' ];
		$subfield['labelFieldName'] = $labelFieldName;
		
		$qty = $this->wire()->pages->count($subfield['selector']); 
		
		if($qty > 50) {
			$subfield['input'] = 'autocomplete';
		} else {
			$subfield['input'] = 'select';
			$items = $f->getSelectablePages(new NullPage());
			$options = [];
			foreach($items as $item) {
				/** @var Page $item */
				$options[$item->id] = $f->getPageLabel($item);
			}
			if(count($options)) {
				$subfield['options'] = $options;
			} else {
				$subfield['input'] = 'text';
			}
		}

		return $subfield;
	}

	/**
	 * Get match query
	 *
	 * @param DatabaseQuerySelect|PageFinderDatabaseQuerySelect $query
	 * @param string $table
	 * @param string $subfield Name of the field (typically 'data', unless selector explicitly specified another)
	 * @param string $operator The comparison operator
	 * @param mixed $value The value to find
	 *
	 */
	public function getMatchQuery(DatabaseQuerySelect $query, string $table, string $subfield, string $operator, $value) {
		
		$this->table = $table; // aliased table name
		$this->query = $query;
		$this->operator = $operator;
		$this->value = $value;
		
		$database = $this->wire()->database;
		$selector = $query->selector;
		$not = $selector->not;
		$field = $query->field;
		$fieldTable = $field->getTable(); // non-aliased table name
		$subfield = $this->wire()->sanitizer->fieldName($subfield);
		$inputfield = $this->fieldtype->tools()->getPropertyInputfield(new Page(), $this->field, $subfield);
		$isPageRef = $inputfield instanceof InputfieldPage;
		$isSelect = $inputfield instanceof InputfieldSelect;
		$hasArrayValue = $inputfield instanceof InputfieldHasArrayValue;

		$col = "JSON_EXTRACT($table.data, \"$.$subfield\")";
		// $col2 = "$table.data->\"$.$subfield\""; // syntax requires MySQL 5.7.9 or newer
		$mb = function_exists('mb_strtolower');
		$not = $not || $operator === '!=';

		/*
		if(!$this->mySqlVersion('5.7.0')) {
			$this->error("MySQL version 5.7.0 or newer required to search field: $field");
			return; // JSON_EXTRACT not supported
		}
		*/
		
		if($operator === '=' || $operator === '!=') {
			if(($isPageRef || $isSelect || $hasArrayValue) && !empty($value)) {
				if(empty($value)) $value = $isPageRef ? 0 : '';
				if($isPageRef && !ctype_digit("$value") && $value) $value = $this->wire()->pages->get($value)->id;
				if(ctype_digit("$value")) $value = (int) $value;
				$json = json_encode([ $subfield => $value ]);
				$bindKey = $query->bindValueGetKey($value);
				$jsonBindKey = $query->bindValueGetKey($json);
				// $where = "JSON_CONTAINS($table.data, '{\"$subfield\": $value}')=1 OR $col=$bindKey";
				$leftjoin =
					"$fieldTable AS $table ON $table.pages_id=pages.id " . 
					"AND JSON_CONTAINS($table.data, $jsonBindKey)=1 OR $col=$bindKey";
				$query->parentQuery->leftjoin($leftjoin);
				$where = $operator === '!=' ? "$table.pages_id IS NULL" : "$table.pages_id IS NOT NULL";
				$query->parentQuery->where($where);
				return; // since we are manipulating parent query and need nothing further
				
			} else if(!strlen("$value") || (empty($value) && ($isSelect || $hasArrayValue))) {
				// find empty or not-empty
				$leftjoin = 
					"$fieldTable AS $table ON $table.pages_id=pages.id " . 
					"AND JSON_LENGTH($table.data, '$.$subfield')>0";
				$null = $operator === '=' ? 'NULL' : 'NOT NULL';
				$query->parentQuery->leftjoin($leftjoin);
				$query->parentQuery->where("$table.pages_id IS $null");
				return;

				/*
			} else if($inputfield instanceof InputfieldHasArrayValue) {
				if($inputfield instanceof InputfieldPage) $value = (int) $value;
				$where = "JSON_CONTAINS($table.data, ?, '$.$subfield') $operator 1";
				$this->where($where, json_encode([ $value ]));
				return;
				*/
				
			} else {
				// will be handled further down
			}
		}

		if($database->isOperator($operator)) {
			// standard database operator: =, !=, <, <=, >, >=
			if(ctype_digit("$value")) {
				// integer value
				$value = (int) $value;
				
				if($inputfield instanceof InputfieldInteger) {
					$bindKey = $query->bindValueGetKey($value, \PDO::PARAM_INT);
					$where = "$col$operator$bindKey";
				} else {
					$bindKeyInt = $query->bindValueGetKey($value, \PDO::PARAM_INT);
					$bindKeyStr = $query->bindValueGetKey($value, \PDO::PARAM_STR);
					$or = $operator === '!=' ? 'AND' : 'OR';
					$where = "$col$operator$bindKeyInt $or $col$operator$bindKeyStr";
				}
				
			} else {
				// string value
				$value = $mb ? mb_strtolower($value) : strtolower($value);
				$bindKey = $query->bindValueGetKey($value);
				// $query->where("$col$operator$bindKey");
				$where = "JSON_UNQUOTE(LOWER($col))$operator$bindKey";
			}
			
			if($where) $this->where($where);
			return;
		}

		if($operator === '*=') {
			// start by using fulltext index and confirm within encoded field using %=
			$this->buildFindQueryData($operator, $value);
			$operator = '%=';
		}

		if(in_array($operator, array('%=', '^=', '$=', '%^=', '%$='))) {
			$value = $mb ? mb_strtolower($value) : strtolower($value);
			$value = addcslashes($value, '%_');
			switch($operator) {
				case '%=':
					$value = "%$value%";
					break;
				case '^=':
				case '%^=':
					$value = "$value%";
					break;
				case '$=':
				case '%$=':
					$value = "%$value";
					break;
			}
			$like = $not ? 'NOT LIKE' : 'LIKE';
			$value = str_replace('"', '', $value);
			$value = '"' . $value . '"';
			$this->where("LOWER($col) $like ?", $value);
			return;
		}

		if($operator === '~=' || $operator === '~|=') {
			// find any or all words
			$rlike = $not ? 'NOT RLIKE' : 'RLIKE';
			$this->buildFindQueryData($operator, $value, $not); // fulltext pre-filter
			$wheres = array();
			foreach(explode(' ', $value) as $word) {
				$word = $mb ? mb_strtolower($word) : strtolower($word);
				$word = preg_quote($word);
				$word = "([[:blank:]]|[[:punct:]]|[[:space:]]|>|^)$word([[:blank:]]|[[:punct:]]|[[:space:]]|<|$)";
				$bindKey = $query->bindValueGetKey($word);
				$wheres[] = "(LOWER($col) $rlike $bindKey)";
			}
			$whereType = $operator === '~=' ? ' AND ' : ' OR ';
			$this->where(implode($whereType, $wheres));
			return;
		}

		throw new PageFinderException("Unimplemented operator in: $selector"); 
	}
	
	protected function where($sql, $value = null) {
		$op = $this->operator;
		$isEmpty = empty($this->value);
		if(($op === '!=' && !$isEmpty) || ($op === '=' && $isEmpty)) {
			$sql = "(($sql) OR $this->table.data IS NULL)";
		} else {
			$sql = "($sql)";	
		}
		if($value !== null) {
			$this->query->where($sql, $value);
		} else {
			$this->query->where($sql);
		}
	}
	
	/**
	 * Find within fulltext-indexed 'data' column
	 *
	 * @param $operator
	 * @param $value
	 * @param bool $not
	 *
	 */
	protected function buildFindQueryData($operator, $value, bool $not = false) {
		if($not) $operator = "!$operator";
		$ft = new DatabaseQuerySelectFulltext($this->query);
		$this->wire($ft);
		$ft->allowOrder(false);
		$ft->match($this->table, 'data', $operator, $value);
	}
	
}