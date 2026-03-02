<?php namespace ProcessWire;

/**
 * ProcessWire Form Builder Processor: Save Page
 *
 * Copyright (C) 2023 by Ryan Cramer Design, LLC
 *
 * PLEASE DO NOT DISTRIBUTE
 *
 */

class FormBuilderProcessorSavePage extends Wire {

	/**
	 * @var FormBuilderProcessor
	 * 
	 */
	protected $processor;

	/**
	 * Construct
	 * 
	 * @param FormBuilderProcessor $processor
	 * 
	 */
	public function __construct(FormBuilderProcessor $processor) {
		parent::__construct();
		$processor->wire($this);
		$this->processor = $processor;
	}

	/**
	 * Get settings for saving a page
	 *
	 * @param array $data
	 * @param null|int $status
	 * @param null|array $onlyFields
	 * @return array
	 *
	 */
	public function savePageInit(array $data, $status, $onlyFields) {
		
		$processor = $this->processor;
		$pages = $this->wire()->pages;
		$templates = $this->wire()->templates;
		
		$a = array(
			'status' => $status === null ? (int) $processor->savePageStatus : (int) $status,
			'template' => $processor->savePageTemplate ? $templates->get($processor->savePageTemplate) : null,
			'parent' => $processor->savePageParent ? $pages->get((int) $processor->savePageParent) : null,
			'page' => empty($data['_savePage']) ? null : $pages->get((int) $data['_savePage']),
			'onlyFields' => $onlyFields,
		);
		
		if($a['page'] instanceof NullPage) $a['page'] = null;
		
		return $a;
	}

	/**
	 * Save the form entry to a Page
	 *
	 * - If saving an existing page, the ID of that page will be in `$data['_savePage']`
	 * - If `$status` omitted or null, it is determined automatically from form settings (most common call).
	 * - If `$onlyFields` is an array, only the field names specified will be saved.
	 *
	 * @param array $data Form data to send to page
	 * @param int $status Status of created pages
	 * @param array|null $onlyFields Save field names present in this array. If omitted, save all field names. Names are form field names.
	 * @return Page|null Created page or null on failure
	 *
	 */
	public function savePage(array $data, $status = null, $onlyFields = null) {

		$sanitizer = $this->wire()->sanitizer;
		$fields = $this->wire()->fields;
		$processor = $this->processor;

		/** @var Page|null $page */

		$a = $processor->savePageInit($data, $status, $onlyFields);
		$template = $a['template'];
		$parent = $a['parent'];
		$status = $a['status'];
		$page = $a['page']; // populated only if updating existing page or hook provided new page
		$onlyFields = $a['onlyFields'];
		$fileFields = array(); // file fields must be populated after first save
		$of = false;

		if($page && $page->id) {
			$existingPageID = $page->id;
		} else {
			$existingPageID = isset($data['_savePage']) ? (int) $data['_savePage'] : 0;
		}

		// if no template or parent specified in form settings, do not save page
		if(!$template || !$parent || !$parent->id) {
			$processor->saveLog("Cannot save to page because template='$template' or parent='$parent' is empty");
			return null;
		}

		if($page === null) {
			// asked to create a new page: if there is no status setting then do not save it
			if(!$status) {
				$processor->saveLog("Cannot save entry to new page because no page status is defined");
				return null;
			}

		} else if($page instanceof NullPage) {
			// was asked to update a page that does not exist
			$processor->saveLog("Cannot update existing page $existingPageID because it does not exist");
			return null;

		} else if($page) {
			// existing page: if it does not have required template/parent, then do not use
			$pageTemplateId = $page->template->id;
			$pageParentId = $page->parent->id;
			
			if($pageTemplateId !== $template->id) {
				$page = null;
				$processor->saveLog(
					"Warning: existing page $existingPageID uses non-matching template so creating a new page instead " .
					"(page.template=$pageTemplateId, request.template=$template->id)"
				);
			} else if($pageParentId !== $parent->id) {
				$page = null;
				$processor->saveLog(
					"Warning: existing page $existingPageID uses non-matching parent so creating a new page instead " .
					"(page.parent=$pageParentId, request.parent=$parent->id)"
				);
			}
		}

		if($page && $page->id) {
			// use existing page
			$isNew = false;
			$of = $page->of();
			if($of) $page->of(false);
		} else {
			// create a new page	
			if(!$page) {
				if(method_exists($template, 'getPageClass')) {
					$pageClass = $template->getPageClass(true);
					if(class_exists($pageClass)) $page = new $pageClass();
				}
				if(!$page) $page = new Page();
			}
			$this->wire($page);
			$page->parent = $parent;
			$page->template = $template;
			$page->status = $status;
			$isNew = !$page->id;
		}

		// populate field values to the page
		foreach($processor->savePageFields as $field_id => $formFieldName) {

			if(empty($formFieldName)) continue;

			// if onlyFields argument specified, limit saved fields to those present in it
			if(is_array($onlyFields) && !in_array($formFieldName, $onlyFields)) continue;

			// convert a field name like "title" to a field ID (for simpler usage with hooks)
			if(!ctype_digit("$field_id") && $field_id !== 'name' && is_string($field_id) && strlen($field_id)) {
				$field = $fields->get($field_id);
				if($field instanceof Field) $field_id = $field->id;
			}

			// determine what kind of field we are saving based on type of $field_id
			if(ctype_digit("$field_id")) {
				// custom field identified by field ID
				$field = $fields->get((int) $field_id);
				if(!$field) continue;
				$pageFieldName = $field->name;

				// files must be handled after initial save
				if($field->type instanceof FieldtypeFile) {
					if($processor->allowSavePageField($page, $pageFieldName, $formFieldName, $data[$formFieldName], $data)) {
						$fileFields[] = array($formFieldName, $pageFieldName);
					}
					continue;
				}

			} else if($field_id === 'name') {
				// allowed native field
				$pageFieldName = $field_id;

			} else {
				// unknown or invalid field
				continue;
			}

			$value = isset($data[$formFieldName]) ? $data[$formFieldName] : null;

			if($pageFieldName === 'name') {
				$value = $sanitizer->pageName($value, true);
			}
			$allowField = $processor->allowSavePageField($page, $pageFieldName, $formFieldName, $value, $data);
			if($allowField) {
				$oldValue = $page->get($pageFieldName);
				if($oldValue instanceof WireArray) $oldValue->removeAll();
				$page->set($pageFieldName, $value);
			}
		}

		// field definitions in savePageSubfields newline-separated textarea
		foreach(explode("\n", $processor->savePageSubfields) as $line) {

			if(!strpos($line, '=')) continue;
			list($formFieldName, $pageFieldName) = explode('=', trim($line));

			$formSubfieldName = '';
			$formFieldName = trim($formFieldName);
			$pageFieldName = trim($pageFieldName);
			$pageField = $fields->get($pageFieldName);
			
			if(strpos($formFieldName, '.')) {
				list($formFieldName, $formSubfieldName) = explode('.', $formFieldName, 2);
			}
			
			$formFieldValue = isset($data[$formFieldName]) ? $data[$formFieldName] : null;
			if(is_array($formFieldValue) && $formSubfieldName) {
				if(isset($formFieldValue[$formSubfieldName])) {
					$formFieldValue = $formFieldValue[$formSubfieldName];
				} else {
					$formFieldValue = null;
				}
			}

			// if onlyFields argument specified, limit saved fields to those present in it
			if(is_array($onlyFields) && !in_array($formFieldName, $onlyFields)) continue;

			if(!$processor->allowSavePageField($page, $pageFieldName, $formFieldName, $formFieldValue, $data)) continue;

			if($pageField && $pageField->type instanceof FieldtypeFile) {
				if(strpos($pageFieldName, '.')) continue; // subfields for files not currently supported
				$fileFields[] = array($formFieldName, $pageFieldName);
				continue;
			}

			if(!strpos($pageFieldName, '.')) {
				// populating a regular page field
				$page->set($pageFieldName, $formFieldValue);
				continue;
			}

			// populating a subfield
			list($pageFieldName, $pageSubfieldName) = explode('.', $pageFieldName, 2);
			$pageFieldValue = $page->get($pageFieldName);
			if(is_object($pageFieldValue)) {
				/** @var WireData $pageFieldValue */
				$pageSubfieldValue = $pageFieldValue->$pageSubfieldName;
				if($pageSubfieldValue !== $formFieldValue) {
					$pageFieldValue->$pageSubfieldName = $formFieldValue;
					$page->set($pageFieldName, $pageFieldValue);
					$page->trackChange($pageFieldName);
				}
			} else if(is_array($pageFieldValue)) {
				$pageFieldValue[$pageSubfieldName] = $formFieldValue;
				$page->set($pageFieldName, $pageFieldValue);
			} else {
				// no way to set a subfield on this page field value
			}
		}

		// if there is no title, make sure one is populated
		if(!strlen($page->title)) $page->title = date('Y-m-d H:i:s');

		// make sure the page's name is allowed
		if(!$processor->savePageCheckName($page)) return null;

		try {
			$processor->savePageReady($page, $data);
			$page->save();
		} catch(\Exception $e) {
			$processor->adminError($e->getMessage());
		}

		// process any fields that can only be set for a page that exists (like file fields)
		if($page->id && count($fileFields)) {
			$cnt = 0;
			foreach($fileFields as $item) {
				list($formFieldName, $pageFieldName) = $item;
				$filenames = isset($data[$formFieldName]) ? $data[$formFieldName] : null;
				if(!is_array($filenames)) continue; // unexpected value in form field
				$pageField = $fields->get($pageFieldName);
				if(!$pageField) continue; // page files field not present
				$cnt += $processor->savePageFileField($page, $pageField, $filenames, $formFieldName);
			}
			if($cnt) try {
				$page->save();
			} catch(\Exception $e) {
				$processor->adminError($e->getMessage());
			}
		}

		if($page->id) $processor->savePageDone($page, $data, $isNew, $onlyFields);
		if($of) $page->of(true);

		return $page;
	}

	/**
	 * Check and update page name as needed for uniqueness
	 *
	 * @param Page $page
	 * @return bool Return true on success, or false if save should be aborted
	 *
	 */
	public function savePageCheckName(Page $page) {
		
		$processor = $this->processor;

		if(!strlen($page->name)) {
			$page->name = microtime();
		}

		$pageName = $page->name;
		$cnt = 0;

		do {
			$qty = $this->wire()->pages->count("parent_id=$page->parent_id, name=$page->name, id!=$page->id, include=all");
			if(!$qty || !$processor->savePageAdjustName) break;
			$page->name = $pageName . '-' . (++$cnt);
		} while($qty);

		if($qty) {
			$this->error(
				sprintf(
					$this->_('Save page refused because name “%s” is already taken and unique names required.'),
					$pageName
				)
			);
			return false;
		}

		if($page->name != $pageName) {
			$this->warning(
				sprintf(
					$this->_('Incremented page name to “%s” because requested name was already in use'),
					$page->name
				) . " ($pageName)"
			);
		}

		return true;
	}

	/**
	 * Save an individual files field to a Page
	 *
	 * @param Page $page Page that has files field
	 * @param Field $field ProcessWire field instance representing files field
	 * @param array $filenames Names of files to add (full paths included) or [ [ 'file' => 'filename', 'desc' => 'description' ] ]
	 * @param string $formFieldName Name of the form field being saved
	 * @return int Number of files added
	 * @since 0.4.7
	 *
	 */
	public function savePageFileField(Page $page, Field $field, array $filenames, $formFieldName) {
		
		$sanitizer = $this->wire()->sanitizer;
		
		if($formFieldName) {} // for hooks, if needed
		if(empty($filenames)) return 0;
		$cnt = 0;

		/** @var Pagefiles $pageValue */
		$pagefiles = $page->get($field->name);
		if($pagefiles instanceof Pagefile) $pagefiles = $pagefiles->pagefiles;
		if(!$pagefiles instanceof Pagefiles) return 0;

		$maxFiles = (int) $field->get('maxFiles');
		$descRows = (int) $field->get('descriptionRows'); 
		
		if($maxFiles === 1 && count($pagefiles)) $pagefiles->removeAll(); // replace single files

		foreach($filenames as $filename) {
			$desc = '';
			if(is_array($filename)) {
				if(!isset($filename['file'])) continue;
				$desc = isset($filename['desc']) ? $filename['desc'] : '';
				$filename = $filename['file'];
			}
			try {
				$pagefiles->add($filename);
				if($desc !== '' && $descRows > 0) {
					/** @var Pagefile $pagefile */
					$pagefile = $pagefiles->last();
					$desc = $descRows > 1 ? $sanitizer->textarea($desc) : $sanitizer->text($desc);
					$pagefile->description = $desc;
				}
				$cnt++;
			} catch(\Exception $e) {
				$this->processor->adminError($e->getMessage());
			}
		}

		if($cnt) $page->set($field->name, $pagefiles);

		return $cnt;
	}

}