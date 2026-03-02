<?php namespace ProcessWire;

/**
 * ProFields: Custom Fields Configuration
 * 
 * THIS IS PART OF A COMMERCIAL MODULE: DO NOT DISTRIBUTE.
 * This file should NOT be uploaded to GitHub or available for download on any public site.
 *
 * Copyright 2024 by Ryan Cramer Design, LLC
 * ryan@processwire.com
 * 
 */

/**
 * @param InputfieldWrapper $fs
 * @param CustomField|Field $field
 * 
 */
function FieldtypeCustom_getConfigInputfields(InputfieldWrapper $fs, Field $field) {
	InputfieldCustom_getConfigInputfields($fs, $field);
	$fs = $fs->getByName('_custom_settings');
	$f = $fs->InputfieldSelect; 
	$f->attr('name', 'dataType'); 
	$f->required = true;
	$f->label = __('Database column storage type'); 
	$f->addOption('json', __('JSON (up to 1 GB)'));
	$f->addOption('text', __('TEXT (up to 64 KB)')); 
	$f->addOption('mediumtext', __('MEDIUM TEXT (up to 16 MB)'));
	$f->addOption('longtext', __('LONG TEXT (up to 4 GB)'));
	$val = $field->get($f->name);
	if(empty($val)) $val = FieldtypeCustom::defaultDataColType;
	$f->val($val);
	$fs->add($f);

	$col = $fs->wire()->database->getColumns($field->getTable(), 'data');
	$dataTypeNow = strtolower($col['type']);
		
	$f = $fs->InputfieldHidden;
	$f->attr('name', 'dataTypeNow');
	$f->val($dataTypeNow);
	$field->dataTypeNow = $dataTypeNow;
	$fs->add($f);
}

/**
 * @param InputfieldWrapper $fs
 * @param CustomField|InputfieldCustom $field
 * 
 */
function InputfieldCustom_getConfigInputfields(InputfieldWrapper $fs, $field) {
	
	$config = $fs->wire()->config;
	$input = $fs->wire()->input;

	if($field instanceof Inputfield) {
		$inputfield = $field;
		// if Inputfield has a Fieldtype then it was already configured
		// from a FieldtypeCustom_getConfigInputfields call
		if($inputfield->hasFieldtype) return; 
	} else if($field instanceof Field) {
		$inputfield = $field->getInputfield(new Page());
	} else {
		throw new WireException("Invalid config field: $field"); 
	}

	$fieldsetTest = null;
	$markupTest = null;
	
	$defsFile = $inputfield->defs()->getDefsFile();
	$defsFileExists = is_file($defsFile);
	$defsFileValid = $defsFileExists;
	$defsFileExt = pathinfo($defsFile, PATHINFO_EXTENSION);
	$defsUrl = str_replace($config->paths->root, '/', $defsFile);
	$error = '';
	$defsFromJson = null;
	$ul = [];
	
	$fieldset = $fs->InputfieldFieldset;
	$fieldset->attr('name', '_InputfieldCustom'); 
	$fieldset->label = __('Custom Field');
	$fieldset->icon = 'diamond';
	$fs->prepend($fieldset);

	if($defsFileExists) {
		$ul[] = sprintf(__('Great! Your definitions are in file: %s'), "<code>$defsUrl</code>"); 
		if($defsFileExt === 'json') {
			$defsFromJson = json_decode(file_get_contents($defsFile), true);
			if(!is_array($defsFromJson)) {
				$jsonError = (string) json_last_error_msg();
				$error = __('Oops the definitions file contains invalid JSON:') . " $jsonError";
				$inputfield->error("$error - $defsUrl");
				$defsFileValid = false;
			}
		} else if($defsFileExt === 'php' && $input->requestMethod('GET') && $field instanceof CustomField) {
			$inputfields = $inputfield->defs()->getInputfields($field->name);
			if(wireCount($inputfields)) {
				foreach($inputfields->getAll() as $f) {
					if($f->getSetting('requiredAttr')) $f->requiredAttr = false;
				}
				$markupTest = $inputfields->InputfieldMarkup;
				$markupTest->label = __('Try out your custom field definitions');
				$markupTest->icon = 'cutlery';
				$markupTest->themeOffset = 1;
				$markupTest->description =
					__('Below is an example/test of the fields that we picked up from your definitions file.') . ' ' .
					__('This is how it can look in the editor/form.') . ' ' .
					__('Because this is only a test, no field values will be saved here.') . ' ' .
					__('Try editing a page with this field to give it a more thorough test.');
				$fieldsetTest = new InputfieldWrapper();
				$fieldsetTest->attr('name', '_test_fieldset');
				$fieldsetTest->themeOffset = 1;
				$fieldsetTest->add($inputfields);
			}
			$phpErrors = $inputfield->defs()->getDefsFileErrors();
			if(count($phpErrors)) {
				foreach($phpErrors as $err) $ul[] = "Error: $err";
				$defsFileValid = false;
			}
		}
		
		if($defsFileValid) $ul[] = __('Scroll to the bottom of this screen to try out your field definitions.');
	} else {
		$line = wireIconMarkup('edit fw') . ' ' . __('Please define your fields in file %s or %s');
		$defsName = basename($defsUrl);
		$ext = pathinfo($defsUrl, PATHINFO_EXTENSION);
		$ext2 = $ext === 'json' ? 'php' : 'json';
		$defsUrl2 = basename($defsName, ".$ext") . ".$ext2";
		$line = sprintf($line, "<code>$defsUrl</code>", "<code>$defsUrl2</code>");
		$ul[] = "<li>$line</li>";
		$ul[] = 
			wireIconMarkup('hand-o-down fw') . 
			__('You may want to copy one of the examples (below) to the filename (above) to get started.');
		$fieldset->detail = 
			__('Once you have created a definitions file please click “Save” and more settings will be available.');
	}
	
	if($error) {
		$ul[] = "<span class='ui-state-error-text'>" . wireIconMarkup('warning fw') . " $error</span>";
	} else if(is_array($defsFromJson)) {
		$ul[] = wireIconMarkup('check fw') . ' ' . __('Definitions file is valid JSON'); 
	}

	$f = $fs->InputfieldMarkup;
	$f->attr('name', '_inst');
	$f->label = __('Setup');
	$f->icon = 'lightbulb-o';
	foreach($ul as $li) {
		if(stripos($li, 'err') === 0) {
			$li = "<span class='ui-state-error-text'>" . wireIconMarkup('warning fw') . "Oops! $li</span>";
		} else if(strpos($li, '</i>') === false) {
			$li = wireIconMarkup('check fw') . $li;
		}
		$f->value .= "<li>$li</li>";
	}
	$f->value = "<ul class='uk-list uk-list-divider'>$f->value</ul>";
	$fieldset->add($f);
	
	$examplesPath = $config->paths('FieldtypeCustom') . 'examples/';
	$examplesUrl = str_replace($config->paths->site, '/site/', $examplesPath);
	$examples = [];

	$fieldsetExamples = $fs->InputfieldFieldset;
	$fieldsetExamples->attr('name', '_InputfieldCustomExamples');
	$fieldsetExamples->label = __('Examples');
	$fieldsetExamples->icon = 'code';
	$fieldsetExamples->description = sprintf(
		__('Below are files in the %s directory that serve as examples and/or starting points for your own custom field(s).'),
		"`$examplesUrl`"
	);
	$fieldsetExamples->collapsed = Inputfield::collapsedYes;
	$fieldset->add($fieldsetExamples);

	foreach(new \DirectoryIterator(__DIR__ . '/examples/') as $file) {
		$ext = $file->getExtension();
		if($ext !== 'php' && $ext !== 'json') continue;
		$basename = $file->getBasename();
		if(strpos($basename, 'example') !== 0) continue;
		$examples[$basename] = $file->getPathname();
	}
	
	ksort($examples);
	
	foreach($examples as $basename => $pathname) {
		$data = file_get_contents($pathname);
		$ext = pathinfo($basename, PATHINFO_EXTENSION);
		
		$f = $fs->InputfieldMarkup;
		$f->attr('name', "_$basename");
		$f->label = $basename;
		$f->val("<pre><code class='language-$ext'>" . htmlentities($data) . "</code></pre>");
		$f->collapsed = Inputfield::collapsedYes;
		$fieldsetExamples->add($f);
	}

	$fieldsetSettings = $fs->InputfieldFieldset;
	$fieldsetSettings->attr('name', '_custom_settings');
	$fieldsetSettings->label = __('Settings');
	$fieldsetSettings->icon = 'sliders';
	$fieldsetSettings->collapsed = Inputfield::collapsedYes;
	$fieldset->add($fieldsetSettings);
	
	$f = $fs->InputfieldToggle;
	$f->attr('name', 'hideWrap');
	$f->label = __('Disable wrapping fieldset?');
	$f->icon = 'eye-slash';
	$f->description =
		__('When wrapping fieldset is disabled, the subfield inputs will not appear grouped in a surrounding fieldset.') . ' ' .
		__('Instead, they will appear like regular fields outside of a fieldset.') . ' ' .
		__('Your field label, description and notes will also not appear.');
	$f->val((int) $inputfield->hideWrap);
	$fieldsetSettings->add($f);

	if($field instanceof CustomField) {
		$f = $fs->InputfieldToggle;
		$f->attr('name', 'useEntityEncode');
		$f->label = __('Entity encode text when $page output formatting on? (recommended)');
		$f->notes = __('This includes both values and labels, even those nested in arrays.'); 
		$f->val($field->get($f->name));
		$f->icon = 'html5';
		$fieldsetSettings->add($f);
	}
	
	if($fieldsetTest && $defsFileValid) {
		if($markupTest) $fs->add($markupTest);
		$fs->add($fieldsetTest);
	}
}