<?php namespace ProcessWire;

/**
 * Export/Import functions for FieldtypeTable
 * 
 * Part of the ProFields package
 * Please do not distribute.
 *
 * Copyright 2023 by Ryan Cramer
 *
 * https://processwire.com/store/pro-fields/
 *
 */

class FieldtypeTablePorter extends Wire {
	
	/**
	 * Export table rows value for a page to an array
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param TableRows|null $value Value is not actually used here for FieldtypeTable
	 * @param array $options
	 * @return array
	 *
	 */
	public function exportValue(Page $page, Field $field, $value, array $options = array()) {

		$languages = $this->wire()->languages;
		$pages = $this->wire()->pages;

		// note: $value is intentionally not used here		

		$table = $field->getTable();
		$sql = "SELECT * FROM `$table` WHERE pages_id=:pages_id ORDER BY sort";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->execute();
		$exportValue = array();
		$io = $this->getImportValueOptions($field);
		$colTypes = $io['tableColumnTypes'];

		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {

			unset($row['data'], $row['pages_id']);
			$row['sort'] = (int) $row['sort'];

			foreach($row as $k => $v) {
				if(!isset($colTypes[$k])) continue;
				$type = $colTypes[$k];

				if(strpos($type, 'page') === 0 && !empty($v)) {
					// convert page references to paths
					$isMulti = strpos($v, 'pid') !== false || strpos($v, ',') !== false;
					if($isMulti) $v = str_replace('pid', '', $v);
					$ids = $isMulti ? explode(',', $v) : array($v);
					$paths = array();
					foreach($ids as $id) {
						$path = $pages->getPath((int) $id);
						if(!empty($path)) $paths[] = $path;
					}
					$row[$k] = $isMulti ? $paths : reset($paths);

				} else if(strpos($type, 'Language') && $languages) {
					// convert language IDs to language names
					$parts = explode("\r", $v);
					$langVal = array();
					foreach($parts as $part) {
						if(strpos($part, ':')) {
							list($languageID, $languageValue) = explode(':', $part, 2);
							$language = $languages->get((int) $languageID);
						} else {
							$languageValue = $part;
							$language = $languages->getDefault();
						}
						if($language->id) {
							$langVal[$language->name] = $languageValue;
						}
					}
					$row[$k] = $langVal;
				}
			}

			ksort($row);
			$exportValue[] = $row;
		}

		$query->closeCursor();

		return $exportValue;
	}

	/**
	 * Import a previously exported array
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param array $value
	 * @param array $options
	 * @return WireData Note: populates DB directly, does not return a TableRows value
	 *
	 */
	public function importValue(Page $page, Field $field, $value, array $options = array()) {

		$languages = $this->wire()->languages;
		$sanitizer = $this->wire()->sanitizer;
		$database = $this->wire()->database;
		$pages = $this->wire()->pages;

		static $level = 0;

		$numRows1 = wireCount($options['exportValue']);
		$numRows2 = wireCount($value);
		$numAdded = 0;
		$numRemoved = 0;
		$numChanged = 0;
		$notices = new WireData();
		$io = $this->getImportValueOptions($field);
		$colCounts = array();
		$colTypes = $io['tableColumnTypes'];
		$skipCols = array();
		$missingPageRefs = array();
		$missingLanguages = array();

		/** @var Pages $pages */

		// identify schema differences
		$importRow = reset($value);
		if($importRow) {
			foreach($colTypes as $name => $type) {
				if(!array_key_exists($name, $importRow)) {
					$notices->warning("$field: Import value lacks column '$name' (type=$type)");
				}
			}
			foreach($importRow as $name => $v) {
				if($name == 'sort') continue;
				if(!array_key_exists($name, $colTypes)) {
					$notices->warning("$field: Import value has column '$name' not present in field schema (skipping)");
					$skipCols[$name] = $name;
				}
			}
		}

		// identify changed rows and columns
		foreach($value as $key => $importRow) {

			if(isset($options['exportValue'][$key])) {
				$exportRow = $options['exportValue'][$key];
			} else {
				$exportRow = array();
			}

			if($importRow != $exportRow) {
				$numChanged++;
			}

			foreach($importRow as $name => $importVal) {
				if($name == 'sort') continue;
				if(isset($skipCols[$name])) {
					unset($value[$key][$name]);
					continue;
				}
				$exportVal = isset($exportRow[$name]) ? $exportRow[$name] : null;
				if($exportVal != $importVal) {
					if(!isset($colCounts[$name])) $colCounts[$name] = 0;
					$colCounts[$name]++;
				}
				$colType = $colTypes[$name];
				if(strpos($colType, 'page') === 0) {
					// convert page references from paths to IDs
					$pageVal = array();
					if(is_array($importVal)) {
						$isMultiPage = true;
					} else {
						$isMultiPage = false;
						$importVal = empty($importVal) ? array() : array($importVal);
					}
					foreach($importVal as $pagePath) {
						$pagePath = $sanitizer->pagePathNameUTF8($pagePath);
						if(isset($missingPageRefs[$pagePath])) continue;
						$p = $pages->get("path=$pagePath");
						if($p->id) {
							$pageVal[] = $isMultiPage ? "pid$p->id" : $p->id;
						} else {
							$missingPageRefs[$name . $pagePath] = "'$pagePath' for column '$name'";
						}
					}
					$pageVal = $isMultiPage ? implode(',', $pageVal) : (int) reset($pageVal);
					$value[$key][$name] = $pageVal;

				} else if(strpos($colType, 'Language') && is_array($importVal)) {
					// multi-language value, convert named array to id string
					if($languages) {
						$langVals = array();
						foreach($importVal as $langName => $langVal) {
							$language = $languages->get($sanitizer->pageName($langName));
							if($language->id) {
								$langVals[] = "$language->id:$langVal";
							} else {
								$missingLanguages[$langName] = $langName;
							}
						}
						$langVals = implode("\r", $langVals);
					} else {
						$langVals = isset($importVal['default']) ? $importVal['default'] : reset($importVal);
					}
					$value[$key][$name] = $langVals;
				}
			}
		}

		foreach($missingPageRefs as $pagePath) {
			$notices->warning("$field: Unable to find page reference: $pagePath");
		}
		foreach($missingLanguages as $langName) {
			$notices->warning("$field: Unable to find language: $langName");
		}

		// summarize added and deleted rows
		if($numRows1 < $numRows2) {
			$numAdded = $numRows2 - $numRows1;
		} else if($numRows1 > $numRows2) {
			$numRemoved = $numRows1 - $numRows2;
		}

		if($numAdded || $numRemoved) $notices->message("$field: Added $numAdded rows, removed $numRemoved rows");
		if($numChanged && count($colCounts)) {
			$colCounts2 = array();
			foreach($colCounts as $name => $count) {
				$colCounts2[] = "$name ($count)";
			}
			$notices->message("$field: $numChanged rows updated - " . implode(', ', $colCounts2));
		}

		// just testing, return notices only	
		if($options['test']) return $notices;

		// commit to database
		$table = $field->getTable();
		$level++;

		// delete rows
		$sql = "DELETE FROM `$table` WHERE pages_id=:pages_id";
		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->execute();

		// insert rows
		foreach($value as $row) {
			$sql = "INSERT INTO `$table` SET pages_id=:pages_id,";
			$binds = array(":pages_id" => $page->id);
			foreach($row as $name => $val) {
				if(is_null($val)) continue;
				$colName = $database->escapeCol($sanitizer->name($name));
				$sql .= "`$colName`=:$colName,";
				$binds[":$colName"] = $val;
			}
			$sql = rtrim($sql, ",") . ' ';
			$query = $database->prepare($sql);
			foreach($binds as $name => $val) {
				$query->bindValue($name, $val);
			}
			$query->execute();
		}

		return $notices;
	}

	/**
	 * Get associative array of options (name => default) that Fieldtype supports for importValue
	 *
	 * #pw-internal
	 *
	 * @param Field $field
	 * @return array
	 *
	 */
	public function getImportValueOptions(Field $field) {

		$colTypes = array();
		/** @var FieldtypeTable $fieldtype */
		$fieldtype = $field->type;

		foreach($fieldtype->getColumns($field) as /* $n => */ $col) {
			$name = $col['name'];
			$type = $col['type'];
			if(!$name || !$type) continue;
			$colTypes[$name] = $type;
		}

		return array(
			'test' => true,
			'importable' => true,
			'requiresExportValue' => true,
			'returnsPageValue' => false,
			'restoreOnException' => true,
			'tableColumnTypes' => $colTypes,
			'tablePaginationLimit' => (int) $field->get('paginationLimit'),
		);
	}
}