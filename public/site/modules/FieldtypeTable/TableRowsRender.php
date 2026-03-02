<?php namespace ProcessWire;

/**
 * Provides the render() function implementation for the TableRows class
 * 
 * Part of the ProFields package
 * Please do not distribute.
 *
 * Copyright 2024 by Ryan Cramer
 * 
 */
class TableRowsRender extends Wire {
	
	protected $rows;
	
	public function __construct(TableRows $rows) {
		parent::__construct();
		$this->rows = $rows;
		$rows->wire($this);
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
	public function render($options = array()) {
		
		if(!$this->rows->count()) return '';

		$defaults = array(
			'tableClass' => 'ft-table',
			'columnClass' => 'ft-table-col',
			'columns' => array(),
			'useWidth' => true,
			'useHead' => true,
			'thead' => '',
			'value' => null,
		);

		$page = $this->rows->getPage();
		$user = $this->wire()->user;
		$languages = $this->wire()->languages;
		$sanitizer = $this->wire()->sanitizer;

		if($this->rows->isFormatted()) {
			$rows = $this->rows;
			$of = null;
		} else {
			$of = $page->of();
			$page->of(true); // ensure output formatting is on
			$rows = $page->get($this->rows->getField()->name); // get formatted value
		}

		$columns = $rows->getColumns();
		$totalWidth = 0;
		foreach($columns as /* $colKey => */ $col) $totalWidth += $col['width'];
		if($totalWidth > 100) $defaults['useWidth'] = false;

		$options = array_merge($defaults, $options);

		$out = "<table class='$options[tableClass]'>";
		$attrs = array();

		if($options['thead']) {
			$out .= $options['thead'] . '<tbody>';
		} else if($options['useHead']) {
			$out .= "<thead><tr>";
			foreach($columns as $colKey => $col) {
				if(empty($col['type'])) {
					unset($columns[$colKey]);
					continue;
				}
				$name = $col['name'];
				if(count($options['columns']) && !in_array($name, $options['columns'])) continue;
				$attr = '';
				if($options['columnClass']) $attr .= " class='$options[columnClass] $options[columnClass]-$name'";
				if($options['useWidth']) $attr .= $col['width'] ? " width='$col[width]%'" : '';
				$attrs[$name] = $attr;
				$label = '';
				if($languages && $user->language && !$user->language->isDefault()) {
					if(isset($col["label$user->language"])) $label = $col["label$user->language"];
				}
				if(!strlen($label)) $label = $col['label'];
				if(!$label) $label = $name;
				$out .= "<th$attr>" . $sanitizer->entities($label) . "</th>";
			}
			$out .= "</tr></thead><tbody>";
		} else {
			$out .= '<tbody>';
		}

		foreach($rows as $row) {
			$out .= "<tr>";
			foreach($columns as $col) {
				if(empty($col['type'])) continue;
				$name = $col['name'];
				if(count($options['columns']) && !in_array($name, $options['columns'])) continue;
				$attr = isset($attrs[$name]) ? $attrs[$name] : '';
				$value = $row->$name;

				if(!empty($col['options'])) {
					// convert options to show label rather than value, when applicable
					foreach($col['options'] as $option) {
						if(strpos($option, '=') === false) continue; // no separate value=label
						list($optionVal, $optionLabel) = explode('=', $option, 2);
						if(is_array($value) && in_array($optionVal, $value)) {
							$valueKey = array_search($optionVal, $value);
							if($valueKey !== false) $value[$valueKey] = $optionLabel;
						} else if($optionVal == $value) {
							$value = $optionLabel;
						}
					}
				}

				if(is_array($value)) {
					foreach($value as $k => $v) {
						$value[$k] = $sanitizer->entities1($v);
					}
					$value = implode('<br />', $value);

				} else if($value instanceof Page) {
					$labelField = empty($col['settings']['labelField']) ? 'title|name' : $col['settings']['labelField'];
					$_value = $value;
					$of = $value->of();
					if(!$of) $value->of(true);
					$value = $value->get($labelField);
					if(!$of) $_value->of(false);

				} else if($value instanceof PageArray) {
					$labelField = empty($col['settings']['labelField']) ? 'title|name' : $col['settings']['labelField'];
					$_value = array();
					foreach($value as $v) {
						$of = $v->of();
						if(!$of) $v->of(true);
						$_value[] = $v->get($labelField);
						if(!$of) $v->of(false);
					}
					$value = implode('<br />', $_value);
					unset($_value);

				} else if($value instanceof Pageimage) {
					$s = $col['settings'];
					if(!empty($s['thumbSize']) && preg_match('/^(\d+)x(\d+)$/', $s['thumbSize'], $matches)) {
						list($w, $h) = array((int) $matches[1], (int) $matches[2]);
						$thumb = $value->size($w, $h);
						$img = "<img src='$thumb->url' alt='$value->basename' ";
						if($w) $img .= "width='$w' ";
						if($h) $img .= "height='$h' ";
						if(!empty($s['thumbSize2x']) && preg_match('/^(\d+)x(\d+)$/', $s['thumbSize2x'], $matches)) {
							$thumb2x = $value->size((int) $matches[1], (int) $matches[2]);
							$img .= "srcset='$thumb2x->url 2x' ";
						}
					} else {
						$img = "<img src='$value->url' ";
					}
					$img .= "alt='$value->basename' />";
					$value = "<a href='$value->url'>$img</a>";

				} else if($value instanceof Pagefile) {
					$value = "<a href='$value->url'>$value->basename</a>";

				} else {
					switch($col['type']) {
						case 'date':
						case 'datetime':
							if(empty($value)) {
								$value = '';
							} else if(ctype_digit("$value")) {
								$format = empty($col['options']['dateOutputFormat']) ? '' : $col['options']['dateOutputFormat'];
								if(empty($format)) $format = $col['type'] === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s';
								$value = wireDate($format, $value);
							}
							$value = $sanitizer->entities1($value);
							break;
						case 'checkbox':
							$value = "<input type='checkbox' " . ($value ? 'checked' : '') . " onclick='return false' />";
							break;
						default:
							$value = $sanitizer->entities1("$value");
					}
				}
				$out .= "<td$attr>$value</td>";
			}
			$out .= "</tr>";
		}

		$out .= "</tbody></table>";

		if($of !== null) $page->of($of); // restore

		return $out;
	}

}