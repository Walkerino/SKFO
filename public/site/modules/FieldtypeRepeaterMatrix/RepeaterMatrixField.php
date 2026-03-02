<?php namespace ProcessWire;

/**
 * Repeater Matrix Field
 * 
 * Copyright 2023 by Ryan Cramer Design, LLC
 * ryan@processwire.com
 * 
 * @property FieldtypeRepeaterMatrix $type
 * @property int $parent_id Parent page ID for repeater items.
 * @property int $template_id Template ID used by repeater items.
 * @property array $repeaterFields Array of field IDs used in repeater.
 * @property int $repeaterMaxItems Maximum number of items allowed.
 * @property int $repeaterMinItems Minimum number of items allowed.
 * @property int|string $repeaterDepth Max allowed depth for repeater items.
 * @property bool|int $familyFriendly Use family friendly depth? Maintains expected parent/child relationships.
 * @property int $repeaterLoading Dynamic loading (ajax) in editor, see `FieldypeRepeater::loading*` constants.
 * @property int $repeaterCollapse Item collapse state, see `FieldtypeRepeater::collapse*` constants
 * @property string $repeaterAddLabel Label to use for adding new items.
 * @property string $repeaterTitle Field name to use for repeater item labels or format string with {fields}.
 * @property int|bool $rememberOpen Remember which items are open between requests?
 * @property int|bool $accordionMode Use accordion mode where only 1 item open at a time?
 * @property int|bool $lazyParents Avoid creating parents when there are no items to store?
 * @property int $repeaterReadyItems (deprecated)
 * 
 * @property string $matrix1_name
 * @property string $matrix1_label
 * @property string $matrix1_head
 * @property array $matrix1_fields
 * @property int $matrix1_sort
 * 
 * @property string $matrix2_name
 * @property string $matrix2_label
 * @property string $matrix2_head
 * @property array $matrix2_fields
 * @property int $matrix2_sort
 * 
 * @property string $matrix3_name
 * @property string $matrix3_label
 * @property string $matrix32_head
 * @property array $matrix3_fields
 * @property int $matrix3_sort
 * 
 * …and so on…
 * 
 * InputfieldRepeaterMatrix
 * ========================
 * @property int $addType Method user uses to add items, see `InputfieldRepeaterMatrix::addType` constants.
 * @property bool|int $allowChangeType Allow changing types of existing repeater items?
 * @property array $allowMatrixTypes Matrix types to allow when adding new (array of matrix type numbers) or empty to allow all.
 * @property string $imageDefs
 * @property string $imageStyle
 * @property bool|int $imageText Show text labels with images?
 * 
 * 
 * @method array getMatrixTypes($indexType = 'name', $valueType = 'type')
 * 
 */
class RepeaterMatrixField extends Field {

	/**
	 * @var array
	 * 
	 */
	protected $maxMatrixTypes = array();

	/**
	 * Get the max quantity of matrix types (highest 'n' value used by matrix type in $field)
	 *
	 * @return int
	 *
	 */
	public function getMaxMatrixTypes() {
		if(isset($this->maxMatrixTypes[$this->id])) {
			return $this->maxMatrixTypes[$this->id];
		}
		$max = 0;
		foreach(array_keys($this->getArray()) as $property) {
			if(strpos($property, 'matrix') !== 0 || !strpos($property, '_name')) continue;
			list($x, $n) = explode('matrix', "x$property", 2);
			if($x) {}
			list($n, $x) = explode('_', $n);
			if($x) {}
			if(ctype_digit($n) && ((int) $n) > $max) $max = (int) $n;
		}
		if($max) {
			$this->maxMatrixTypes[$this->id] = $max;
		} else {
			$max = 5;
		}
		return $max;
	}
	
	/**
	 * Get all information available for each matrix type in an array of arrays indexed by type name
	 *
	 * Unless only a specific property is requested (via the `get` option), returned array includes the following:
	 *
	 *  - `type` (int): Type number
	 *  - `name` (string): Matrix type name
	 *  - `prefix` (string): Prefix used in identifying this type’s settings in the Field object
	 *  - `label` (string): Matrix type label
	 *  - `label123…` (string): Matrix type label per-language (if multi-language) where “123…” is language ID, for all languages.
	 *  - `sort` (int): Sort value
	 *  - `file` (string): Optional PHP render file (whether it exists or not)
	 *  - `head` (string): Optional definition for item headers
	 *  - `field` (Field): Field that these matrix types are for
	 *  - `fields` (array): Array of Field objects used by this matrix type (in template context), indexed by Field name
	 *  - `fieldIDs` (array): Array of Field IDs used by this matrix type
	 *
	 * If a specific property is requested (via the `get` option) then returned array is of strings or ints, depending on what
	 * was requested. If the `type` option is used then the returned array wil be only info for that type, or if combined with
	 * the `get` option then return value is just the property for that type (int or string).
	 *
	 * A blank array or null is returned if you asked for something that isn’t there.
	 *
	 * @param array $options
	 *  - `get` (string): Optionally specify a single property to get for each item rather than everything (default='')
	 *  - `type` (string|int): Get info only for this type rather than all (default='')
	 *  - `index` (string): What property to use for array index, can be 'type' (number) or 'name' (default='name')
	 * @return array|string|null
	 *
	 */
	public function getMatrixTypesInfo(array $options = array()) {

		$defaults = array(
			'get' => '',
			'type' => '',
			'index' => 'name',
			'language' => null,
		);

		$options = array_merge($defaults, $options);
		$get = $options['get'];

		$config = $this->wire()->config;
		$languages = $this->wire()->languages;
		$matrixTypes = array();

		for($n = 1; $n <= $this->getMaxMatrixTypes(); $n++) {

			$prefix = "matrix{$n}_";
			$name = $this->get($prefix . "name");
			$label = $this->get($prefix . "label");
			list($groupLabel, $typeLabel) = $this->matrixTypeGroupAndLabel($label);
			
			if(empty($name)) continue;
			if($options['type'] && ($name !== $options['type'] && "$n" !== "$options[type]")) continue;
			
			$a = array();
			if(!$get || $get === 'type' || $get === 'n') $a['type'] = $n;
			if(!$get || $get === 'name') $a['name'] = $name;
			if(!$get || $get === 'label') $a['label'] = $label;
			if(!$get || $get === 'prefix') $a['prefix'] = $prefix;
			if(!$get || $get === 'sort') $a['sort'] = (int) $this->get($prefix . "sort");
			if(!$get || $get === 'file') $a['file'] = $config->urls->fieldTemplates . "$this->name/$name.php";
			if(!$get || $get === 'head') $a['head'] = $this->get($prefix . "head");
			if(!$get || $get === 'field') $a['field'] = $this;
			if(!$get || $get === 'fieldIDs') $a['fieldIDs'] = $this->get($prefix . "fields");
			if(!$get || $get === 'groupLabel') $a['groupLabel'] = $groupLabel;
			if(!$get || $get === 'typeLabel') $a['typeLabel'] = $typeLabel;
			

			if(!$get || $get === 'fields') {
				$fieldIDs = $this->get($prefix . 'fields');
				$a['fields'] = array();
				if(!is_array($fieldIDs)) $fieldIDs = array();
				$template = $this->type->getMatrixTemplate($this);
				foreach($fieldIDs as $fieldID) {
					$f = $template->fieldgroup->getFieldContext((int) $fieldID);
					if($f) $a['fields'][$f->name] = $f;
				}
			}

			if($languages && (!$get || ($get !== 'label' && strpos($get, 'label') === 0))) {
				foreach($languages as $language) {
					if($language->isDefault()) continue;
					$key = "label$language->id";
					if($get && $get !== $key) continue;
					$a[$key] = (string) $this->get($prefix . "label$language");
				}
			}

			if($get) $a = $a[$get];
			if($options['index'] === 'name') {
				$matrixTypes[$name] = $a;
			} else {
				$matrixTypes[$n] = $a;
			}
		}

		if(!empty($options['type'])) {
			return count($matrixTypes) === 1 ? reset($matrixTypes) : null;
		}

		return $matrixTypes;
	}
	
	/**
	 * Return an array of just matrix types in format [ typeName => typeNumber ]
	 *
	 * @param string $indexType What matrix property to use for the array indexes (default='name')
	 * @param string $valueType What matrix property to use for the array values (default='type')
	 * @return array
	 * @throws WireException if given invalid field
	 *
	 */
	public function ___getMatrixTypes($indexType = 'name', $valueType = 'type') {

		$matrixTypes = array();
		$language = $valueType === 'label' && $this->wire()->languages ? $this->wire()->user->language : null;
		if($language && $language->isDefault()) $language = null;

		for($n = 1; $n <= $this->getMaxMatrixTypes(); $n++) {

			$name = $this->get("matrix{$n}_name");
			if(empty($name)) continue;
			$prefix = "matrix{$n}_";

			if($indexType === 'name') {
				$index = $name;
			} else if($indexType === 'type') {
				$index = $n;
			} else {
				$index = $this->get($prefix . $indexType);
				if($index === null) continue;
			}

			if($valueType === 'name') {
				$value = $name;
			} else if($valueType === 'type') {
				$value = $n;
			} else if($valueType === 'label' && $language) {
				$value = $this->get($prefix . "label$language->id");
				if(empty($value)) $value = $this->get($prefix . 'label');
			} else {
				$value = $this->get($prefix . $valueType);
			}

			$matrixTypes[$index] = $value;
		}

		return $matrixTypes;
	}

	/**
	 * Return matrix type (integer) for given name, or boolean false if not found
	 *
	 * @param string $name
	 * @return bool|int
	 *
	 */
	public function getMatrixTypeByName($name) {
		$types = $this->getMatrixTypes();
		return isset($types[$name]) ? $types[$name] : false;
	}
	
	/**
	 * Return matrix type name for given matrix type integer, or boolean false if not found
	 *
	 * @param int $type
	 * @return string|bool
	 *
	 */
	public function getMatrixTypeName($type) {
		$types = $this->getMatrixTypes('type', 'name');
		return isset($types[$type]) ? $types[$type] : false;
	}

	/**
	 * Get the label for the given matrix type (name or number)
	 *
	 * @param int|string $type
	 * @param Language|int|string|null $language
	 * @return string
	 *
	 */
	public function getMatrixTypeLabel($type, $language = null) {
		$languages = $this->wire()->languages;
		if($language && $languages) {
			if(!is_object($language)) $language = $languages->get($language);
		} else {
			$language = $this->wire()->user->language;
		}
		if($language) {
			if(!$language->id) throw new WireException("Unknown language");
			$a = $this->getMatrixTypesInfo(array('type' => $type));
			$key = $language->isDefault() ? "label" : "label$language->id";
			$label = empty($a[$key]) ? $a['label'] : $a[$key];
		} else {
			$label = $this->getMatrixTypesInfo(array('get' => 'label', 'type' => $type));
		}
		return $label;
	}
	
	/**
	 * Convert a matrix type label into group and type label
	 *
	 * @param string $label
	 * @return array
	 *
	 */
	public function matrixTypeGroupAndLabel($label) {

		$label = (string) $label;
		if(strpos($label, '>>')) {
			$label = str_replace('>>', ' > ', $label);
		}

		$pos1 = strpos($label, '>');
		$pos2 = strpos($label, '<');

		if($pos1 && ($pos2 === false || $pos2 > $pos1)) {
			list($groupLabel, $typeLabel) = explode('>', $label, 2);
			$groupLabel = trim($groupLabel);
			$typeLabel = trim($typeLabel);
		} else {
			$groupLabel = '';
			$typeLabel = '';
		}

		if(!strlen($typeLabel)) $typeLabel = $label;

		return array($groupLabel, $typeLabel);
	}
	
	/**
	 * Get icon from given header string or blank if none found
	 *
	 * @param string $head
	 * @param bool $remove Also remove it from given string? (default=false)
	 * @return string
	 *
	 */
	public function matrixTypeIcon(&$head, $remove = false) {
		if(!is_string($head)) return '';
		if(strpos($head, 'icon-') === false) return '';
		if(preg_match('/\bicon[-]([a-z][-a-z0-9]+)\b\s*/', $head, $matches)) {
			if($remove) $head = str_replace($matches[0], '', $head);
			return $matches[1];
		}
		return '';
	}

	/*** TODO ****************************************************************************/
	
	/**
	 * Add field to given matrix field for given matrix type and save the field
	 *
	 * This can also be used to move a matrix type from one position to another in a matrix type
	 *
	 * @param Field|int|string $addField Field name, ID or object to add to the matrix type
	 * @param string|int $matrixType Matrix type number OR name
	 * @param Field|int|string $beforeField Insert before this Field (id, name or object) or omit to add field at end
	 * @param bool $save Save the field after updating the settings (default=true). Specify false if you will save on your own.
	 * @throws WireException on all errors
	 * @todo needs testing before being changed to "public"
	 *
	 */
	private function addFieldToMatrixType($addField, $matrixType, $beforeField = null, $save = true) {

		if(!$addField instanceof Field) {
			$addFieldName = $addField;
			$addField = $this->wire()->fields->get($addFieldName);
			if(!$addField) throw new WireException("Unknown field to add: $addFieldName");
		}

		// add field to matrix template if it is not already present
		$template = $this->type->getMatrixTemplate($this);
		$fieldgroup = $template->fieldgroup;
		if(!$fieldgroup->hasField($addField)) {
			$fieldgroup->add($addField);
			$fieldgroup->save();
		}

		if(ctype_digit("$matrixType")) {
			$matrixType = (int) $matrixType;
		} else {
			$matrixTypeName = $matrixType;
			$matrixType = $this->getMatrixTypeByName($matrixTypeName);
			if($matrixType === false) throw new WireException("Unknown matrix type: $matrixTypeName");
		}

		$fieldIDs = $this->get("matrix{$matrixType}_fields");
		if(!is_array($fieldIDs)) throw new WireException("Unknown matrix type: $matrixType");

		if($beforeField && !$beforeField instanceof Field) {
			// convert beforeField to a Field object
			$beforeField = $this->wire()->fields->get($beforeField);
		}

		// if beforeField is not present in the existing type field IDs, then we can disregard it
		if($beforeField && !in_array($beforeField->id, $fieldIDs)) $beforeField = null;

		// updated fieldIDs after adding new field
		$newFieldIDs = array();

		if($beforeField) {
			// inserting new field before an existing field
			// if addField is already present, remove it since it will be changing location
			$key = array_search($addField->id, $fieldIDs);
			if($key !== false) unset($fieldIDs[$key]); // remove if already present

			// beforeField is present in existing matrix fields
			// place addField at requested position before beforeField
			foreach($fieldIDs as $fieldID) {
				if(((int) $fieldID) === ((int) $beforeField->id)) {
					$newFieldIDs[] = $addField->id;
				}
				$newFieldIDs[] = $fieldID;
			}
		} else if(in_array($addField->id, $fieldIDs)) {
			// field is already present, so do nothing
			$newFieldIDs[] = $fieldIDs;
		} else {
			// add at end
			$newFieldIDs = $fieldIDs;
			$newFieldIDs[] = $addField->id;
		}

		if($newFieldIDs !== $fieldIDs) {
			// save updates
			$this->set("matrix{$matrixType}_fields", $newFieldIDs);
			if($save) $this->save();
		}
	}

	/*
	private function removeFieldFromMatrixType($removeField, Field $matrixField, $matrixType, $save = true) {
		// @todo needs implementation 
		// remove from type
		// then check if in use by any other types
		// if not used by any types, then remove from fieldgroup
	}
	*/

}