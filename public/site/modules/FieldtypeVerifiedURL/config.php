<?php namespace ProcessWire;

/**
 * Configure Verified URL field
 * 
 * @param Field $field
 * @param InputfieldWrapper $inputfields
 * 
 */
function config_FieldtypeVerifiedURL(Field $field, InputfieldWrapper $inputfields) {
	
	/** @var FieldtypeVerifiedURL $fieldtype */
	$fieldtype = $field->type;
	$modules = $field->wire()->modules;
	$config = $field->wire()->config;
	$input = $field->wire()->input;
	$pages = $field->wire()->pages;
	
	$maxSeconds = (int) $field->get('maxSeconds');
	if(!$maxSeconds) $maxSeconds = FieldtypeVerifiedURL::defaultMaxSeconds;

	$reverifyDays = $field->get('reverifyDays');
	if($reverifyDays === null) $reverifyDays = FieldtypeVerifiedURL::defaultReverifyDays;

	$reverifyErrorDays = $field->get('reverifyErrorDays');
	if($reverifyErrorDays === null) $reverifyErrorDays = FieldtypeVerifiedURL::defaultReverifyErrorDays;

	$maxErrorTries = $field->get('maxErrorTries');
	if($maxErrorTries === null) $maxErrorTries = FieldtypeVerifiedURL::defaultMaxErrorTries;

	$moduleInfo = FieldtypeVerifiedURL::getModuleInfo();
	
	/** @var InputfieldFieldset $fieldset */
	$fieldset = $modules->get('InputfieldFieldset');
	$fieldset->label = $moduleInfo['title'];
	$fieldset->icon = 'link';
	$fieldset->themeOffset = 1;
	$inputfields->add($fieldset);

	$reverifyDesc = __('Enter number of days, or 0 to never re-verify after first time.');

	/** @var InputfieldInteger $f */
	$f = $modules->get('InputfieldInteger');
	$f->attr('name', 'reverifyDays');
	$f->label = __('Re-verify success URLs after how many days?');
	$f->description = $reverifyDesc;
	$f->attr('value', $reverifyDays);
	$f->columnWidth = 50;
	$fieldset->add($f);

	/** @var InputfieldInteger $f */
	$f = $modules->get('InputfieldInteger');
	$f->attr('name', 'reverifyErrorDays');
	$f->label = __('Re-verify error URLs after how many days?');
	$f->description = $reverifyDesc;
	$f->attr('value', $reverifyErrorDays);
	$f->columnWidth = 50;
	$fieldset->add($f);

	/** @var InputfieldInteger $f */
	$f = $modules->get('InputfieldInteger');
	$f->attr('name', 'maxErrorTries');
	$f->label = __('Max attempts to re-verify error URLs');
	$f->description = __('The number of times you want to try again later, in case URL has come back online.');
	$f->notes = sprintf(__('After max attempts, URL will have status of: %s'), $fieldtype->statusStr(997));
	$f->attr('value', $maxErrorTries);
	$f->columnWidth = 50;
	$fieldset->add($f);

	/** @var InputfieldInteger $f */
	$f = $modules->get('InputfieldInteger');
	$f->attr('name', 'maxSeconds');
	$f->label = __('Max seconds allowed for verifying URLs in 1 request');
	$f->description = __('Prevents verify from taking too many resources in any single request.');
	$f->notes = __('Lower numbers distribute verification across more requests.');
	$f->attr('value', $maxSeconds);
	$f->columnWidth = 50;
	$fieldset->add($f);

	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$f->attr('name', 'useLog');
	$f->label = __('Log URL verifications and errors?');
	$f->description = sprintf(
		__('When checked, URL verification activity will be logged to: %s'),
		$config->urls->logs . $fieldtype->logName($field) . '.txt'
	);
	if($field->get('useLog')) $f->attr('checked', 'checked');
	$fieldset->add($f);

	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$f->attr('name', 'useTitle');
	$f->label = __('Pull contents of title tags at verified URLs?');
	$f->description = sprintf(__('When checked, URL <title> tag text will be accessible via `$page->%s->title`'), $field->name);
	if($field->get('useTitle')) $f->attr('checked', 'checked');
	$f->collapsed = Inputfield::collapsedBlank;
	$fieldset->add($f);

	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldInteger');
	$f->attr('name', 'getHTML');
	$f->label = __('Pull contents of entire HTML document at URL?');
	$f->description = sprintf(__('Enter the number of bytes to pull from the beginning of the HTML document. This HTML will be accessible via string `$page->%s->html`'), $field->name);
	$value = (int) $field->get('getHTML');
	$f->attr('value', $value > 0 ? $value : '');
	$f->collapsed = Inputfield::collapsedBlank;
	$fieldset->add($f);

	/** @var InputfieldText $f */
	$f = $modules->get('InputfieldText');
	$f->attr('name', 'getHeaders');
	$f->label = __('Pull specific response headers?');
	$f->description = __('Enter the names of one or more response headers you want to pull and store, separating each with a space. Or specify just an asterisk “*” to get ALL headers.');
	$f->notes = sprintf(__('Response headers will be accessible via array `$page->%s->headers`'), $field->name);
	$f->value = $field->get('getHeaders');
	$f->collapsed = Inputfield::collapsedBlank;
	$fieldset->add($f);

	/** @var InputfieldText $f */
	$f = $modules->get('InputfieldText');
	$f->attr('name', 'matchRegex');
	$f->label = __('Match regular expression in site markup');
	$f->description = __('Enter a full PCRE regular expression to match in site markup. Example: `/(This|That|Other)/i`');
	$f->notes = sprintf(__('Result will be accessible via array `$page->%s->matches`'), $field->name);
	$f->value = $field->get('matchRegex');
	$f->collapsed = Inputfield::collapsedBlank;
	$fieldset->add($f);

	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$f->attr('name', 'useRedirect');
	$f->label = __('Remember URLs for “301 permanent” and “302 temporary” redirects?');
	$f->description = sprintf(__('When checked, redirect URL will be saved in `$page->%s->redirect`'), $field->name);
	$f->notes = __('This option also enables this field to notify editors when entered URL redirects to another.');
	if($field->get('useRedirect')) $f->attr('checked', 'checked');
	$fieldset->add($f);

	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$f->attr('name', 'fixRedirect');
	$f->label = __('Automatically update URL when “301 permanent” redirect resolves to same hostname?');
	$f->description =
		__('This enables you to automatically update URLs that likely do not need manual review.') . ' ' .
		__('Helpful for keeping up-to-date with changes like http to https, trailing slash requirements, changed URLs, etc.') . ' ' .
		__('Redirects that result in host name changes are excluded for safety.');
	if($field->get('fixRedirect')) $f->attr('checked', 'checked');
	$f->showIf = 'useRedirect>0';
	$fieldset->add($f);

	/** @var InputfieldRadios $f */
	$f = $modules->get('InputfieldRadios');
	$f->attr('name', 'useString');
	$f->label = __('Formatted value type');
	$f->addOption(0, __('Object instance with “url”, “status”, “title”, “redirect”, “html”, “headers” and “matches” properties. String value is always “url”.'));
	$f->addOption(1, __('Regular string containing just the URL'));
	$f->notes = __('Regardless of which option you choose, accessing the value as a string will always resolve to the URL.');
	$f->attr('value', (int) $field->get('useString'));
	$fieldset->add($f);

	/** @var InputfieldRadios $f */
	$f = $modules->get('InputfieldRadios');
	$f->attr('name', 'useBlank');
	$f->label = __('When to make formatted URL value blank?');
	$f->description = __('This option can be useful if you want to prevent output of non-verified URLs on the front-end.');
	$f->addOption(0, __('Never'));
	$f->addOption(1, __('When URL produced http error during verification'));
	$f->addOption(2, __('When status not yet determined OR http error'));
	$f->attr('value', (int) $field->get('useBlank'));
	$fieldset->add($f);

	/** @var InputfieldRadios $f */
	$f = $modules->get('InputfieldRadios');
	$f->attr('name', 'wireHttpUse'); 
	$f->label = __('WireHttp method to use'); 
	$f->description = __('Force use of a specific WireHttp method or “Automatic” to use best available and fallback to others as needed.');
	$f->addOption('auto', __('Automatic (recommended)'));
	$f->addOption('curl', __('CURL'));
	$f->addOption('fopen', __('Fopen'));
	$f->addOption('socket', __('Socket')); 
	$v = $field->get('wireHttpUse'); 
	if(empty($v)) $v = 'auto';
	$f->val($v);
	$fieldset->add($f);

	/** @var Inputfieldtext $f */
	$f = $modules->get('InputfieldText');
	$f->attr('name', 'userAgent'); 
	$f->label = __('Browser user-agent string to use when validating URL');
	$f->description = __('Leave blank for default, enter `client` to duplicate client side user-agent or enter user-agent string to use.');
	$userAgent = 'ProcessWire/' . ProcessWire::versionMajor . '.' . ProcessWire::versionMinor . ' (WireHttp; FieldtypeVerifiedURL)';
	$f->notes = __('Default user agent when left blank:') . " `$userAgent`";
	$f->attr('value', (string) $field->get('userAgent'));
	$f->collapsed = Inputfield::collapsedBlank;
	$fieldset->add($f);

	/** @var InputfieldURL $f */
	$f = $modules->get('InputfieldURL');
	$f->attr('name', '_testVerifyURL');
	$f->label = __('Test the URL verification');
	$f->description = __('Enter a URL to test if you would like to make sure this module is working.');
	$f->collapsed = Inputfield::collapsedYes;
	$fieldset->add($f);

	$testURL = $input->post('_testVerifyURL');
	if($testURL) {
		$value = $fieldtype->newVerifiedURL($pages->get(1), $field, array('href' => $testURL));
		$code = $value->verifyNow(false);
		$statusStr = $value->httpUrl() . ' - ' . $fieldtype->statusStr($value, true);
		if($field->get('useTitle')) $statusStr .= " - Title: $value->title";
		$code >= 400 ? $fieldtype->warning($statusStr) : $fieldtype->message($statusStr);
	}

	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$f->attr('name', '_resetVerifiedURL');
	$f->label = __('Reset all verified URL data for this field?');
	$f->description = __('Check this box to reset all URL verification data for this field, forcing it to re-verify everything.');
	$f->collapsed = Inputfield::collapsedYes;
	$f->notes = __('DANGER: this action will delete all URL verification data and cannot be undone.');
	$fieldset->add($f);

	if($input->post('_resetVerifiedURL')) {
		$fieldtype->resetVerifyData($field);
		$fieldtype->message(__('Verified URL data has been reset'));
	}

	/** @var InputfieldHidden $f */
	$f = $modules->get('InputfieldHidden');
	$f->attr('name', 'schemaVersion');
	$f->attr('value', $field->get('schemaVersion'));
	$fieldset->add($f);
}