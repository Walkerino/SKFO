<?php namespace ProcessWire;

/**
 * Class FieldtypeRepeaterMatrixConfigHelper
 *
 * ProcessWire ProFields Repeater Matrix config helper.
 * Provides methods for configuring a Repeater Matrix field.
 *
 * THIS IS PART OF A COMMERCIAL MODULE: DO NOT DISTRIBUTE.
 * This file should NOT be uploaded to GitHub or available for download on any public site.
 *
 * Copyright 2023 by Ryan Cramer Design, LLC
 * ryan@processwire.com
 *
 * PWPFRMPA
 * 
 *
 */

class FieldtypeRepeaterMatrixConfigHelper extends Wire {

	/**
	 * @var RepeaterMatrixField
	 * 
	 */
	protected $field;

	/**
	 * @var Template
	 * 
	 */
	protected $template;

	/**
	 * All optional render files for summary field
	 * 
	 * @var array
	 * 
	 */
	protected $renderFiles = array();

	/**
	 * Cached array of all matrix types for all fields
	 * 
	 * @var array
	 * 
	 */
	protected $matrixTypesInfo = array();

	/**
	 * Construct
	 * 
	 * @param Field|RepeaterMatrixField $field
	 * @param Template $template
	 * 
	 */
	public function __construct(Field $field, Template $template) {
		parent::__construct();
		$field->wire($this);
		$this->field = $field;
		$this->template = $template; 
	}

	/**
	 * @return Field|RepeaterMatrixField
	 * 
	 */
	public function getField() {
		return $this->field;
	}
	
	/**
	 * Get prefix for given repeater matrix item index (for matrix configuration screen)
	 *
	 * @param int|string $n
	 * @return string
	 *
	 */
	public function getMatrixItemPrefix($n) {
		return "matrix{$n}_";
	}

	/**
	 * Populate given $inputfields with fields to configure repeater matrix field
	 * 
	 * Helper for FieldtypeRepeaterMatrix::___getConfigInputfields()
	 * 
	 * @param InputfieldWrapper $inputfields
	 * @return InputfieldWrapper
	 * 
	 */
	public function getConfigInputfields(InputfieldWrapper $inputfields) {
		
		$config = $this->wire()->config;
		$modules = $this->wire()->modules;
	
		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = $this->_('Repeater matrix types');
		$fieldset->description = $this->_('Define one or more types that will be available in your repeater matrix.');
		$fieldset->attr('id', 'RepeaterMatrixItems');
		$inputfields->prepend($fieldset);
	
		// setup order of types
		$order = array();
		$maxN = 0;
		for($n = 0; $n <= $this->field->getMaxMatrixTypes(); $n++) {
			$prefix = $this->getMatrixItemPrefix($n);
			$name = $this->field->get($prefix . "name");
			if(!$name) continue;
			$sort = (int) $this->field->get($prefix . "sort");
			while(isset($order[$sort])) $sort++;
			$this->field->set($prefix . "sort", $sort);
			$order[$sort] = $n;
			if($n > $maxN) $maxN = $n;
		}
		
		// add existing items in order
		ksort($order);
		foreach($order as $n) {
			$fieldset->add($this->getConfigInputfieldsMatrixItem($n));
		}
		
		// add new item template
		$newItem = $this->getConfigInputfieldsMatrixItem('N');
		$newItem->addClass('MatrixItemTemplate');
		$fieldset->add($newItem);
		
		$importItem = $this->getConfigInputfieldsMatrixImportItem(); 
		$importItem->addClass('MatrixItemImportTemplate');
		$fieldset->add($importItem);

		// add needed css and js
		$url = $config->urls('FieldtypeRepeaterMatrix'); 
		$config->styles->add($url . 'config.css');
		$config->scripts->add($url . 'config.js');
		$config->js('RepeaterMatrix', array(
			'maxN' => $maxN
		));

		// add type link
		$addLabel = $this->_('Add new matrix type');
		$importLabel = $this->_('Import matrix type');

		/** @var InputfieldButton $addButton */
		$addButton = $modules->get('InputfieldButton');
		$addButton->attr('id', 'MatrixItemAdd');
		$addButton->href = '#';
		$addButton->icon = 'plus-circle';
		$addButton->value = $addLabel;
		
		/** @var InputfieldButton $importButton */
		$importButton = $modules->get('InputfieldButton');
		$importButton->attr('id', 'MatrixItemImport');
		$importButton->href = '#';
		$importButton->icon = 'plus-circle';
		$importButton->value = $importLabel;
		$importButton->setSecondary();
		
		$fieldset->appendMarkup = "<p>" . $addButton->render() . $importButton->render() . "</p>";
		
		//$fieldset->appendMarkup = "<p><a id='MatrixItemAdd' href='#'><i class='fa fa-plus-circle'></i> $addLabel</a></p>";
	
		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup');
		$f->attr('name', '_matrix_note');
		$f->label = $this->_('Repeater matrix editor settings');
		$f->icon = 'sliders';
		$f->collapsed = Inputfield::collapsedYes;
		$f->themeOffset = 1;
		$f->value = "<p>" .
			sprintf(
				$this->_('Please see the %s tab for matrix editor/input settings.'),
				"<a href='#' onclick='Inputfields.find(\"Inputfield__matrixInputSettings\");return false'>" . $this->_('Input') . "</a>"
			) .
			"</p>";
		$fs = $inputfields->getChildByName('_repeaterEditorSettings');	
		if($fs) {
			$inputfields->insertAfter($f, $fs); 
		} else {
			$inputfields->add($f);
		}


		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup');
		$f->label = $this->_('Optional render files for this repeater matrix');
		$file = $config->urls->fieldTemplates . $this->field->name . '.php';
		$f->description = 
			$this->_('The main/index file receives a $value variable containing an array of all repeater matrix items in this page field.') .
			' ' . $this->_('The item file(s) receive a $page variable holding the repeater matrix item.');
		$f->notes = $this->_('See the Repeater Matrix documentation for more details.'); 
		$f->icon = 'file-code-o';
		$f->collapsed = Inputfield::collapsedYes;
		
		/** @var MarkupAdminDataTable $table */
		$table = $modules->get('MarkupAdminDataTable');
		$table->setEncodeEntities(false);
		$table->headerRow(array(
			$this->_x('File', 'th'),
			$this->_x('Details', 'th')
		));
		$table->row(array(
			$file,
			$this->_('Main/index file.') . ' ' .
				$this->_('Iterates and outputs all matrix items') 
		));
		foreach($this->renderFiles as $name => $file) {
			$table->row(array(
				$file,
				sprintf($this->_('Generates output for "%s" matrix items'), $name)
			));
		}
		$f->value = $table->render();
		$inputfields->add($f);
	
		/*	
			"<h3>" . $this->_('Example') . "</h3>" . 
			"<p><b>" . $this->_('Main matrix index file:') . "</b><br /><pre class='MatrixCode'>" . 
			$this->wire('sanitizer')->entities(
				"<?php" . 
				"\nforeach(\$value as \$item) {" . 
				"\n  echo \$item->render();" . 
				"\n}"
			) .
			"</pre></p><p><b>" . $this->_('Item file(s):') . "</b> <span class='detail'>" . 
			$this->_('(assuming $title and $body are fields in the matrix item type)') . 
			"</span><br /><pre class='MatrixCode'>" . 
			$this->wire('sanitizer')->entities(
				"<div class='bodycopy'>" . 
				"\n  <h3><?=\$page->title?></h3>" . 
				"\n  <?=\$page->body?>" . 
				"\n</div>"
			) . 
			"</pre></p>";
		
		//$f->appendMarkup .= ' ' . $this->_('Usage: `echo $page->render("%s");`');
		*/

		return $inputfields; 
	}
	
	/**
	 * Get a single configurable matrix item in a Fieldset
	 *
	 * @param int $n Index of matrix item
	 * @return InputfieldFieldset
	 *
	 */
	protected function getConfigInputfieldsMatrixItem($n) {

		$sanitizer = $this->wire()->sanitizer;
		$modules = $this->wire()->modules;
		$config = $this->wire()->config;
		$languages = $this->wire()->languages;
		$field = $this->field;
		$template = $this->template;

		/** @var ProcessTemplate $processTemplate */
		static $processTemplate = null;
		if(is_null($processTemplate)) {
			$processTemplate = $modules->getModule('ProcessTemplate', array('noInit' => true));
		}

		$prefix = $this->getMatrixItemPrefix($n);
		$contextNamespace = rtrim($prefix, '_');
		$matrixName = $field->get($prefix . "name");
		$matrixLabel = $field->get($prefix . "label");
		$matrixSort = $field->get($prefix . "sort");
		$matrixHead = $field->get($prefix . "head");
		$matrixIcon = $field instanceof RepeaterMatrixField ? $field->matrixTypeIcon($matrixHead) : '';

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->addClass('MatrixItem');
		if($matrixName) {
			$contextLabel = $matrixLabel ? $matrixLabel : $matrixName;
			$renderFile = $config->urls->fieldTemplates . "$field->name/$matrixName.php";
			$this->renderFiles[$matrixName] = $renderFile;
			$fieldset->appendMarkup = "<p class='detail'><i class='fa fa-fw fa-file-code-o'></i>" . 
				sprintf($this->_('Optional render file for this matrix type: %s (receives $page as matrix item)'), "<b>$renderFile</b>") . 
				"</p>";
			//$fieldset->label = sprintf('#%d (id=%d): ', ($matrixSort + 1), $n);
		} else {
			$contextLabel = $this->_('New Matrix Type');
			//$fieldset->label = $contextLabel;
		}
		$fieldset->icon = ($matrixIcon ? $matrixIcon : 'arrows');
		$fieldset->collapsed = Inputfield::collapsedYes;
		$fieldset->attr('id', $prefix . "item");
		
		/** @var InputfieldHidden $f */
		$f = $modules->get('InputfieldHidden');
		$f->attr('id+name', $prefix . "sort");
		$f->addClass('MatrixItemSort');
		$f->attr('value', $matrixSort);
		$fieldset->add($f);

		/** @var InputfieldHidden $f */
		$f = $modules->get('InputfieldHidden');
		$f->attr('id+name', "_" . $prefix . "delete");
		$f->addClass('MatrixItemDelete');
		$f->attr('value', '');
		$fieldset->add($f);

		/** @var InputfieldName $f */
		$f = $modules->get('InputfieldName');
		$f->attr('id+name', $prefix . "name");
		$f->label = $this->_('Name');
		$f->attr('value', $matrixName);
		$f->columnWidth = 50;
		$f->notes = $f->description;
		$f->description = '';
		$f->required = false;
		if($matrixName) {
			$f->required = true;
			$f->attr('required', 'required');
		}
		$fieldset->add($f);
		$nameField = $f;

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('id+name', $prefix . "label");
		$f->label = $this->_('Label');
		$f->attr('value', $matrixLabel);
		$notes = 
			$this->_('Use “Label” or “Group > Label” to identify this matrix type to the person editing.');
		if($languages) {
			$f->useLanguages = true;
			$nameField->columnWidth = 100;
			$nameField->description = $nameField->notes;
			$nameField->notes = '';
			$f->description = $notes;
			foreach($languages as $language) {
				if($language->isDefault()) continue;
				$f->set("value$language", $field->get($prefix . "label$language"));
			}
		} else {
			$f->columnWidth = 50;
			$f->notes = $notes;
		}
		if(strlen("$f->value")) {
			$value = (string) $f->value;
			list($groupLabel, $typeLabel) = $field->matrixTypeGroupAndLabel($value);
			if(strlen($groupLabel)) {
				$groupLabel = $sanitizer->entities($groupLabel);
				$typeLabel = $sanitizer->entities($typeLabel);
				$icon = str_replace("<i", "<i style='margin:0'", wireIconMarkup('angle-double-right', 'fw ui-priority-secondary'));
				$value = $groupLabel . $icon . $typeLabel;
				$fieldset->entityEncodeLabel = false;
			}
			$fieldset->label = $value;
		} else if($matrixName) {
			$fieldset->label = $matrixName;
		} else {
			$fieldset->label = $contextLabel;
		}
		$fieldset->add($f);

		$editLink = $config->urls->admin .
			"setup/field/edit" .
			"?id={value}" .
			"&fieldgroup_id={$template->fieldgroup->id}" .
			"&context_namespace=" . $contextNamespace .
			"&context_label=" . urlencode($contextLabel) .
			"&modal=1" .
			"&process_template=1";

		/** @var InputfieldAsmSelect $select */
		$select = $modules->get('InputfieldAsmSelect');
		$select->label = $this->_x('Fields', 'field-label');
		$select->description = $this->_('Select the fields that are shown in this matrix type. You may also drag and drop fields to the desired order.');
		$select->description .= ' ' . $this->_('Once a field has been saved in your matrix type, you may also click its label to apply additional customizations to the field.');
		$select->icon = 'cube';
		$select->attr('id+name', $prefix . "fields");
		$select->attr('title', $this->_('Add Field'));
		$select->setAsmSelectOption('sortable', true);
		$select->setAsmSelectOption('fieldset', true);
		$select->setAsmSelectOption('editLink', $editLink);
		// $select->setAsmSelectOption('editLinkOnlySelected', false); // makes ProcessField throw error when field isn't in fieldgroup
		$select->setAsmSelectOption('hideDeleted', false);

		$value = $field->get($prefix . "fields");
		if(!is_array($value)) $value = array();

		foreach($this->wire()->fields as $f) {
			/** @var Field $f */
			if($f->name == $this->field->name) continue;
			if(($f->flags & Field::flagPermanent) && !$config->advanced) continue;
			$name = $f->name;
			if($f->flags & Field::flagSystem) $name .= "*";
			if(in_array($f->id, $value)) {
				$_f = $template->fieldgroup->getFieldContext($f, $contextNamespace);
				if($_f) $f = $_f;
			}
			$attrs = $processTemplate->getAsmListAttrs($f);
			$select->addOption($f->id, $name, $attrs);
		}

		$select->attr('value', $value);
		$fieldset->add($select);
	
		/** @var InputfieldText $input */
		$input = $modules->get('InputfieldText');
		$input->attr('name', $prefix . "head");
		$value = $field->get($prefix . "head");
		if(!$value) $value = "{matrix_label} [• {matrix_summary}]";
		$input->attr('value', $value);
		$input->label = $this->_('Item headers');
		$input->description = 
			$this->_('Enter the format used for item headers in the page editor. Surround page fields in `{brackets}`.') . ' ' . 
			$this->_('You may enter `{matrix_label}` to show the entire label defined above.') . ' ' . 
			$this->_('Or, if using “Group > Type” style labels, you may refer to them individually with `{matrix_group}` and `{matrix_type}`.') . ' ' . 
			$this->_('You may enter `{matrix_summary}` to auto-summarize the item content.') . ' ' . 
			$this->_('Text surrounded in `[square brackets]` will be de-emphasized.') . ' ';
		$input->notes = 
			sprintf(
				$this->_('To replace the default icon (drag arrows) specify `icon-name` at the beginning of the label, replacing “name” with an [fa-icon name](%s) (without the “fa-” prefix).'),
				'https://fontawesome.com/v4.7/icons/'
			) . ' ' . 
			$this->_('For example, `icon-home`, `icon-heart`, etc.') . ' ' . 
			$this->_('To use a different background color for the item headers, specify a hex color code, i.e. `#FF0000` at the end of the label (use only colors that work with white text).'); 
		$input->collapsed = Inputfield::collapsedYes;
		$fieldset->add($input);

		return $fieldset;
	}

	protected function getConfigInputfieldsMatrixImportItem() {
	
		$modules = $this->wire()->modules;
		$field = $this->field;
		$fieldtype = $field->type; /** @var FieldtypeRepeaterMatrix $fieldtype */
		$prefix = $this->getMatrixItemPrefix('N');
		$matrixSort = $field->get($prefix . "sort");
		
		if(empty($this->matrixTypesInfo)) {
			$this->matrixTypesInfo = $fieldtype->getAllMatrixTypesInfo();
			ksort($this->matrixTypesInfo); 
		}

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->addClass('MatrixItem');
		$fieldset->label = $this->_x('New Matrix Type (Import)', 'import');
		$fieldset->icon = 'arrows';
		$fieldset->collapsed = Inputfield::collapsedYes;
		$fieldset->attr('id', $prefix . "import");

		/** @var InputfieldHidden $f */
		$f = $modules->get('InputfieldHidden');
		$f->attr('id+name', $prefix . "sort");
		$f->addClass('MatrixItemSort');
		$f->attr('value', $matrixSort);
		$fieldset->add($f);
	
		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect'); 
		$f->attr('name', $prefix . 'import'); 
		$f->addClass('MatrixItemImportType');
		$f->label = $this->_('Import matrix type'); 
		$f->skipLabel = Inputfield::skipLabelHeader;
		$f->addOption('', $this->_('Select type to import…')); 
		$language = $this->wire()->languages ? $this->wire()->user->language : null;
		if($language && $language->isDefault()) $language = null;
		foreach($this->matrixTypesInfo as $fieldName => $types) {
			$importField = $this->wire()->fields->get($fieldName);
			foreach($types as /*$typeName =>*/ $info) {
				$importLabel = $importField->getLabel() . ' > ';
				$importLabel .= $language && !empty($info["label$language->id"]) ? $info["label$language->id"] : $info['label'];
				$f->addOption("$importField->id.$info[type]", $importLabel);
			}
		}
		$fieldset->add($f);
		
		return $fieldset;
	}
	
	public function getMatrixProperties($n = 0) {
		$languages = $this->wire()->languages;
		$properties = array(
			// POST name => property name
			'name' => 'name',
			'label' => 'label',
			'fields' => 'fields',
			'sort' => 'sort',
			'head' => 'head',
		);

		if($languages) foreach($languages as $language) {
			if(!$language->isDefault()) $properties["label__$language"] = "label$language";
		}
		
		if($n) {
			$a = array();	
			$prefix = $this->getMatrixItemPrefix($n);
			foreach($properties as $k => $v) {
				$a[$prefix . $k] = $prefix . $v;
			}
			$properties = $a;
		}

		return $properties;
	}

	/**
	 * Save matrix type config
	 * 
	 * Helper for FieldtypeRepeaterMatrix::___saveConfigInputfields()
	 * 
	 */
	public function saveConfigInputfields() {

		$field = $this->field;
		$fields = $this->wire()->fields;
		$input = $this->wire()->input;
		$fieldIDs = array($fields->get('repeater_matrix_type')->id);
		$matrixNames = array();
		$properties = $this->getMatrixProperties();

		// ensure that template item (identified by "matrixN_..." is not saved in the field)
		$values = $field->getArray();
		foreach($properties as $postName => $propertyName) {
			$postName = "matrixN_$postName";
			$propertyName = "matrixN_$propertyName";
			$input->post->offsetUnset($postName);
			if(isset($values[$propertyName])) $field->remove($propertyName);
		}
		
		$input->post->offsetUnset("matrixN_import");
	
		// save the repeater matrix types
		foreach(array_keys($_POST) as $key) {
			
			if(strpos($key, 'matrix') !== 0) continue; 
			
			if(strpos($key, '_import') && preg_match('/^matrix(\d+)_import$/', $key, $matches) && !empty($_POST[$key])) {
				list($importField, $importType) = explode('.', $_POST[$key]); 
				$importField = $fields->get((int) $importField); 
				$n = (int) $matches[1];
				$sort = (int) $input->post($this->getMatrixItemPrefix($n) . 'sort');
				if($importField) {
					$importedFieldIDs = $this->importMatrixType($importField, (int) $importType, $field, $n, $sort);
					$fieldIDs = array_merge($fieldIDs, $importedFieldIDs);
				}
				$input->post->offsetUnset($key);
				continue;
			}
			
			if(!strpos($key, '_name')) continue;
			if(!preg_match('/^matrix(\d+)_name$/', $key, $matches)) continue;
			$n = (int) $matches[1];

			$prefix = $this->getMatrixItemPrefix($n);
			$delete = $input->post('_' . $prefix . 'delete');
			$name = $input->post->fieldName($prefix . 'name');
			
			if($fields->get($name)) {
				$this->error(sprintf(
					$this->_('Please choose a different name for matrix type “%s” as this name is already in use by a field.'),
					$name
				));
				$input->post->$key = $name;
			}
			if(isset($matrixNames[$name])) $this->error(sprintf(
				$this->_('There are multiple matrix types using the name “%s”, please correct this so that the names are unique.'),
				$name
			));
			$matrixNames[$name] = $name;
				
			if($delete || !$name) {
				// delete the item or remove properties not in use
				foreach($properties as $property) {
					$value = $field->get($prefix . $property);
					if(!is_null($value)) $field->remove($prefix . $property);
				}
				if($delete) $this->message($this->_('Deleted matrix type') . " - $name");
				continue;
			}

			foreach($properties as $postName => $propertyName) {
				if($propertyName == 'fields' || $propertyName == 'sort') continue;
				$postKey = $prefix . $postName;
				$value = $input->post->text($postKey);
				$field->set($prefix . $propertyName, $value);
				$input->post->$postKey = $value;
			}

			$value = $input->post($prefix . 'fields');
			if(is_array($value)) {
				$typeFieldIDs = array();
				foreach($value as $fieldID) {
					$fieldID = (int) $fieldID;
					$fieldIDs[$fieldID] = $fieldID;
					$typeFieldIDs[] = $fieldID;
				}
				$field->set($prefix . 'fields', $typeFieldIDs); 
			}
			
			$sort = (int) $input->post($prefix . 'sort');
			$field->set($prefix . 'sort', $sort);
		}
		
		// populate for FieldtypeRepeater to pick up
		$input->post->repeaterFields = array_values($fieldIDs);
	}

	/**
	 * Import matrix type from one field into current field
	 * 
	 * @param Field|RepeaterMatrixField $fromField
	 * @param int $fromType
	 * @param Field|RepeaterMatrixField $toField
	 * @param int $toType
	 * @param int $toSort
	 * @return array Array of field IDs that were added
	 * 
	 */
	protected function importMatrixType(Field $fromField, $fromType, Field $toField, $toType, $toSort) {
	
		$fields = $this->wire()->fields;
		$input = $this->wire()->input;
		$fieldtype = $toField->type; /** @var FieldtypeRepeaterMatrix $fieldtype */
		$properties = $this->getMatrixProperties();
		$fromPrefix = $this->getMatrixItemPrefix($fromType);
		$toPrefix = $this->getMatrixItemPrefix($toType);
		$toField = $this->field;
		$toName = '';
		$fromName = '';
		
		foreach($properties as $postName => $propertyName) {
			if($propertyName === 'sort') {
				$value = $toSort;
			} else {
				$value = $fromField->get($fromPrefix . $propertyName);
			}
			if($propertyName === 'name') {
				$toName = $value;
				$fromName = $value;
				$n = 0; 
				while($toField->getMatrixTypeByName($toName)) {
					$toName = $value . '_' . (++$n);
				}
				$value = $toName;
			}
			$toField->set($toPrefix . $propertyName, $value);
			$toPostName = $toPrefix . $postName;
			$input->post->$toPostName = $value; 
		}
		
		// copy field-specific contexts
		$fromFieldgroup = $fieldtype->getMatrixTemplate($fromField)->fieldgroup;
		$toFieldgroup = $fieldtype->getMatrixTemplate($toField)->fieldgroup;
		$fromInfo = $fromField->getMatrixTypesInfo(array('get' => 'fields', 'index' => 'type'));
		$addedFieldIDs = array();
		$contextQty = 0;
		list($fromNS, $toNS) = array(rtrim($fromPrefix, '_'), rtrim($toPrefix, '_'));
		foreach($fromInfo[$fromType] as /*$fieldName =>*/ $field) {
			if($toFieldgroup->hasField($field)) continue;
			$field = $fields->get($field->name); // without context
			$toFieldgroup->add($field);
			$addedFieldIDs[] = $field->id;
		}
		$toFieldgroup->save();
		foreach($fromInfo[$fromType] as /*$fieldName =>*/ $field) {
			$data = $fromFieldgroup->getFieldContextArray($field->id, $fromNS);
			if(empty($data)) continue;
			$field = $toFieldgroup->getFieldContext($field->name);
			$toFieldgroup->setFieldContextArray($field->id, $data, $toNS);
			$contextQty++;
		}
		if($contextQty) $toFieldgroup->saveContext();

		// report what we did
		if($toName) {
			$fromLabel = "$fromField->name.$fromName";
			$toLabel = "$toField->name.$toName";
			$this->message(sprintf('Imported matrix type “%s” to “%s”', $fromLabel, $toLabel));
		}
		
		return $addedFieldIDs;
	}

	/**
	 * Configuration for InputfieldRepeaterMatrix
	 * 
	 * @param InputfieldRepeaterMatrix $inputfield
	 * @param InputfieldWrapper $inputfields
	 * @return InputfieldWrapper
	 * 
	 */
	public function getInputConfigInputfields(InputfieldRepeaterMatrix $inputfield, InputfieldWrapper $inputfields) {
		
		$modules = $this->wire()->modules;
		$field = $this->field;

		/** @var FieldtypeRepeaterMatrix|null $fieldtype */
		$fieldtype = $inputfield->hasFieldtype;
		if(!$field || !$fieldtype) return $inputfields;

		/** @var InputfieldFieldset $fs */
		$fs = $modules->get('InputfieldFieldset');
		$fs->label = $this->_('Repeater matrix editor and input settings');
		$fs->icon = 'sliders';
		$fs->attr('name', '_matrixInputSettings');
		$fs->themeOffset = 1;
		$inputfields->prepend($fs);

		$addTypeImagesLabel = $this->_('Images');
		$addTypeCustomLabel = $this->_('Custom');
		$imagesUrl = $inputfield->imagesUrl();

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'addType');
		$f->label = $this->_('How should the user add new repeater matrix items?'); 
		$f->description =
			$this->_('Determines whether the user selects from a dropdown, clicks an image, or clicks a link when adding a new matrix item.');
		$f->notes = 
			$this->_('Note the “Select” option is the only one that supports “Group > Label” style labels for your matrix types (if used).');
		$f->addOption(
			InputfieldRepeaterMatrix::addTypeSelect, 
			$this->_('Select') . ' ' .  
			'[span.detail] ' . $this->_('(select from a dropdown list)') . ' [/span]'
		);
		$f->addOption(
			InputfieldRepeaterMatrix::addTypeLinks, 
			$this->_('Links') . ' ' . 
			'[span.detail] ' . $this->_('(click from a list of text links)') . ' [/span]'
		);
		$f->addOption(
			InputfieldRepeaterMatrix::addTypeImages, 
			$addTypeImagesLabel . ' ' . 
			'[span.detail] ' . $this->_('(click preview images that you prepare)') . ' [/span]'
		);
		$f->addOption(
			InputfieldRepeaterMatrix::addTypeCustom,
			$addTypeCustomLabel . ' ' .
			'[span.detail] ' . $this->_('(enables you to provide your own solution)') . ' [/span]'
		);
		$f->val($inputfield->addType);
		$fs->add($f);
		
		/** @var InputfieldFieldset $fs2 */
		$fs2 = $modules->get('InputfieldFieldset');
		$fs2->label = $addTypeCustomLabel;
		$fs2->description = $this->_('Place the following example code in `/site/templates/admin.php` (near the top, after the opening php tag) and then use it as a starting point for your own custom solution.');
		$fs2->showIf = 'addType=' . InputfieldRepeaterMatrix::addTypeCustom;
		$fs->add($fs2);
	
		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup');
		
		$a = array(
			'$wire->addHookAfter("InputfieldRepeaterMatrix::renderAddMatrixCustom", function($event) {', 
			'  if($event->object->getField()->name != "' . $field->name . '") return;',
			'  $style = "display:inline-block;background:#ccc;padding:40px;margin:5px"; // example',
			'  $out = "";', 
			'  foreach($event->arguments(0) as $type) {',
			'    $out .= "<a href=\'$type[href]\' style=\'$style\'>$type[label]</a>";',
			'  }',
			'  $event->return = [ "Select type to add", $out ];',
			'});',
		);

		$f->value = '<pre>' . $this->wire()->sanitizer->entities(implode("\n", $a)) . '</pre>';
		// $out = "<div class='InputfieldRepeaterMatrixAddCustom pw-hidden' data-label='$label'>$out</div>";

		$fs2->add($f);

		/** @var InputfieldFieldset $fs2 */
		$fs2 = $modules->get('InputfieldFieldset');
		$fs2->label = $addTypeImagesLabel;
		$fs2->icon = 'image';
		$fs2->showIf = 'addType=' . InputfieldRepeaterMatrix::addTypeImages;
		$fs2->attr('name', '_addTypeImagesFieldset');
		$fs2->themeColor = 'secondary';
		$fs2->description =
			sprintf($this->_('To use this option create image files having the same name as your matrix types and place them in `%s`.'), $imagesUrl) . ' ' .
			sprintf($this->_('Supported extensions include: `%s`.'), implode('`, `', $inputfield->getImageExtensions())) . ' ' .
			$this->_('If your image files are located elsewhere or you want to use filenames that differ from your matrix type names, use the “Matrix types to images” setting below.');
		$fs->add($fs2);

		/** @var InputfieldTextarea $f */
		$f = $modules->get('InputfieldTextarea');
		$f->attr('name', 'imageDefs');
		$f->label = $this->_('Matrix types to images');
		$f->description =
			sprintf($this->_('Use this setting only if your image files are not located in `%s` or if their filenames differ from your matrix type names.'), $imagesUrl) . ' ' .
			$this->_('Specify one per line of `matrix_type_name:/path/to/image.png` where `matrix_type_name` is the name of your matrix type and `/path/to/image.png` is the path to the image file relative to the root URL.') . ' ' .
			sprintf($this->_('If image files are located in `%s` then you may specify just the image filename like `image.png` and omit the path.'), $imagesUrl);
		$f->val($inputfield->getAddTypeImages(true));
		$f->collapsed = !strlen($inputfield->imageDefs); 
		$rows = substr_count($f->val(), "\n");
		if($rows > 3) $f->attr('rows', $rows+1);
		$fs2->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'imageStyle');
		$f->label = $this->_('Style(s)');
		$f->description =
			$this->_('Enter the style attribute for the element that wraps images.') . ' ' .
			$this->_('For example `margin:10px; width:200px;` would provide a 10 pixel margin between images and display them at 200 pixels wide.');
		$f->val($inputfield->imageStyle);
		$fs2->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldToggle');
		$f->attr('name', 'imageText');
		$f->label = $this->_('Show text labels below images?');
		$f->val($inputfield->imageText);
		$fs2->add($f);

		/** @var InputfieldAsmSelect $f */
		$f = $modules->get('InputfieldAsmSelect');
		$f->attr('name', 'allowMatrixTypes');
		$f->label = $this->_('Matrix types to allow for adding new repeater items');
		$f->description =
			$this->_('This enables you to show some types and not others. This is primarily useful when setting for the context of different templates.');
		$f->notes = $this->_('If none are selected then all are allowed.');
		foreach($fieldtype->getMatrixTypes($field, 'type', 'label') as $type => $label) {
			if(empty($label)) $label = $field->getMatrixTypeName($type);
			$f->addOption($type, $label);
		}
		$f->attr('value', $inputfield->allowMatrixTypes);
		$fs->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'allowChangeType');
		$f->label = $this->_('Allow changing matrix type of existing items?');
		$f->addOption(0, $this->_('No'));
		$f->addOption(1, $this->_('Yes'));
		$f->addOption(2, sprintf($this->_('Yes + “%s”'), $inputfield->undefinedLabel()));
		$f->attr('value', (int) $inputfield->allowChangeType);
		$f->optionColumns = 1;
		$f->description =
			sprintf($this->_('The “%s” option allows a repeater item to also show fields used by ALL types.'), $inputfield->undefinedLabel()) . ' ' .
			$this->_('This can be useful as a temporary selection when content must be copied/pasted among fields before final type change.');
		$fs->add($f);

		return $inputfields;
	}
}

