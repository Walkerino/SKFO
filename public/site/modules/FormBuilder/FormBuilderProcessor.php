<?php namespace ProcessWire;

/**
 * ProcessWire Inputfield Form Processor
 *
 * Handles the rendering and processing of forms for Form Builder.
 *
 * Copyright (C) 2023 by Ryan Cramer Design, LLC
 *
 * PLEASE DO NOT DISTRIBUTE
 *
 * @todo: Add support for nested form fields to be definable into page fields. 
 * @todo: Add a unique "id" attribute to the <form> tag and make the "action" attribute reference it. 
 * <form action='./#my-form'>
 *
 * @property FormBuilder $forms FormBuilder API variable
 * @property int $id Form ID number
 * @property int $saveFlags Flags for saving form submission (default: self::saveFlagsDB)
 * @property bool $skipSessionKey Require a unique session key for each form submission (for security)
 * @property string $formName name of the form
 * @property string $submitText text that appears on the submit button
 * @property string|array $honeypot name of field that, when populated, invalidates the form submission (or array of them)
 * @property array $turingTest array containing list of field names and required answers
 * @property array $spamWords Keywords that flag a form submission as spam.
 * @property int $spamFlags Spam flags
 * @property string $mailer Name of WireMail module to use for sending email, 'WireMail' for native PHP mail(), or blank for auto-detect. 
 * @property string $emailTo email address to send form submissions to, may also be multiple (1 per line) or conditional (1 condition per line)
 * @property string $emailFrom email address (or field name where it resides) to use as the reply-to address for the email. 
 * @property string $emailFrom2 email from address, if different the one specified in module settings and reply-to address.
 * @property string $emailFromName Email from name
 * @property string $emailSubject subject of email that gets sent
 * @property string $responderTo field name (not email address) that WILL contain the submittor's email address (where the responder should be sent). CSV string for multiple.
 * @property string $responderFrom Email address that the responder email should be from
 * @property string $responderFromName Name responder should be from
 * @property string $responderReplyTo Responder reply-to address
 * @property string $responderSubject Subject line for the responder email
 * @property string $responderBody Body for the responder email
 * @property string $successUrl URL to redirect to on successful form submission
 * @property string $successMessage message to display on successful form submission, assuming no successUrl was set
 * @property string $errorMessage message to display when a form error occurred
 * @property string $action2 URL to send duplicate submission to
 * @property array $action2_add array of name=value to add to duplicate submission
 * @property array $action2_remove array of field names to remove from duplicate submission
 * @property array $action2_rename array of field names rename before duplicate submission
 * @property string $akismet CSV string containing field names of: "name,email,content" (in that order)
 * @property bool $allowPreset allow form field values to be pre-set from GET variables?
 * @property bool $useCookies Remember input values?
 * @property-read bool $showHidden Show input[type=hidden] as type=text instead? Option may only be modified in construct $options. (default=false)
 * @property bool $validateNumFields Validate quantity of fields present in form as part of the _submitKey?
 *
 * Settings specific to saving pages from submitted forms:
 * @property string $savePageParent id of parent page
 * @property string $savePageTemplate name of template
 * @property array $savePageFields array of 'processwire_field_id' => 'form_field_name'
 * @property string $savePageSubfields Newline separated string in format: form_field_name=page_field_name.subfield
 * @property int $savePageStatus status of saved page (0 = don't save page now)
 * @property bool|int $savePageAdjustName Adjust page name as needed to ensure it is unique? (default=true)
 * @property string $framework form framework, if in use
 * @property-read FormBuilderForm $fbForm
 * @property-read FormBuilderRender $fbRender
 * @property string $googleSpreadsheetUrl
 * @property array $googleSpreadsheetFields
 * @property string $googleSheetsResponseField
 * @property string $embedVer
 * @property FormBuilderMaker $maker
 * @property FormBuilderEntries $entries
 * @property FormBuilderPartialEntries $partialEntries
 * @property array $pluginActions
 * 
 * HOOKABLE METHODS
 * ================
 * @method InputfieldForm populate(array $data, $entryID) Populate $this->form with the [name=>value] data from the given associative array.
 * @method string render($id = 0) Render the form output, or follow-up success message. If $id is populated, it is the id of existing form entry.
 * @method string renderReady(InputfieldForm $form, $formFile = '', array $vars = array()) Called when ready to render, and returns rendered output. Note the $formFile and $vars arguments are only populated in embed method D. 
 * @method void renderOrProcessReady(InputfieldForm $form, $submitType) Called when ready to render OR process. Added 0.4.7
 * @method bool processInput($id, $submitType) Process input for submitted form. If $id is populated, it is the id of existing form entry. 
 * @method void processInputReady(InputfieldForm $form, $submitType) Called right before $form->processInput() is called.
 * @method void processInputDone(InputfieldForm $form) Called after $form->processInput() and spam filtering is completed. 
 * @method string processInputIsSpam(InputfieldForm $form, $processed) Checks for spam and returns name of spam filter that was triggered, or blank string if not spam.
 * @method void processInputSpammed(InputfieldForm $form, $spamType) Called when form process failed due to being spammed. 
 * @method int|bool saveForm(InputfieldForm $form, $id, $submitType) Save the form to the database entry, page, or email(s) per form action settings. If $id is populated, it is the id of an existing entry being saved.
 * @method void saveFormReady(InputfieldForm $form, array $entry, array $info) Hook called when form is about to be saved, has more info in arguments than saveForm() hook. Added 0.4.5
 * @method array formToEntry(InputfieldForm $form, $id = 0, $merge = false) Given InputfieldForm, convert to $entry array (as called by saveForm method). 
 * @method int saveEntry(array $data) Save a form entry where $data is the given entry. Existing entry should have populated id property. Returns id of saved entry.
 * @method array addEntryReady(array $data, InputfieldForm $form) Called when new entry is about to be added. Return value is entry data that will be saved. 
 * @method array addedEntry(array $data, InputfieldForm $form) Hook called after new entry has been added
 * @method array updateEntryReady(array $data, InputfieldForm $form) Hook called when existing entry is about to be updated
 * @method array updatedEntry(array $data, InputfieldForm $form) Hook called after existing entry has been updated
 * @method Page|null savePage(array $data, int $status = null, array $onlyFields = null) Save given entry $data to a Page. See method comments for additional details.
 * @method array savePageInit(array $data, $status, $onlyFields) Initialize saving of a Page, returns array of settings to use. 
 * @method bool savePageCheckName(Page $page) Hook called before $page->save() to validate that page name is allowed. Returns false if save should be aborted. 
 * @method bool allowSavePageField(Page $page, $pageFieldName, $formFieldName, $value, array $entry) Allow the given field info to be saved in Page?
 * @method bool savePageField(Page $page, $name, $value, $entry) Deprecated, use allowSavePageField() hook above instead.
 * @method int savePageFileField(Page $page, Field $field, array $filenames, $formFieldName) Save a files field for a Page (Added 0.4.7)
 * @method void savePageReady(Page $page, array $data) Hook called right before Page is about to be saved
 * @method array savePageDone(Page $page, array $data, $isNew, array $onlyFields = null) Hook called after a page has been saved. 
 * @method bool|int emailForm(InputfieldForm $form, array $data) Email the form result to the administrator(s). Returns true on success, false on fail, 1 on cancel.
 * @method bool emailFormReady(InputfieldForm $form, FormBuilderEmail $email) Called when $email object is ready, but message not yet sent.  
 * @method bool|int emailFormResponder(InputfieldForm $form, array $data) Send auto-responder email. Returns true on success, false on fail, 1 on cancel.
 * @method bool emailFormResponderReady(InputfieldForm $form, FormBuilderEmail $email) Called when $email object ready, but message not yet sent.
 * @method void emailFormPopulateSkipFields(FormBuilderEmail $email, InputfieldForm $form) Called for all emails to specify field names to skip sending in email, i.e. `$email->setSkipFieldName($name);`
 * @method bool postAction2(array $data) Sends data to external URL specified in action2_* form settings. 
 * @method bool|string postAction2Ready($http, array $data, $method, $url, array $headers) Called when ready to send to external URL. Returns response string or boolean false on fail.
 * @method formSubmitSuccess(InputfieldForm $form) Called when form has been successfully submitted and saved. 
 * @method formSubmitError(InputfieldForm $form, array $errors) Called when there were errors that prevented successful submission of form. 
 * @method formSubmitInvalid(InputfieldForm $form, array $errors) Called when invalid submit key received, like from previously cached form, bot submitted form or CSRF error. 
 * @method string renderSubmittedLandmark() Render a markup landmark that appears after any form submit (success or error), primarily for #anchor or JS scrolling to
 * @method string renderSuccess($message) Render the given success message or success action string. 
 * @method string renderSuccessMessage($message, $markupTemplate = '') Render succcess message string only (called by renderSuccess). 
 * @method string renderSuccessRedirect($url) Render or execute a redirect to given $url (called by renderSuccess). 
 * @method string renderErrors(array $errors = array(), $clear = false) Render error messages. 
 * @method array renderErrorsReady(array $errors) Called when errors ready to render, hooks can optionally modify $event->return array of errors.
 * @method string renderError($error, $errorTemplate = '') Render single error message into markup.
 * @method string wrapOutput($out) Wraps all FormBuilder output
 * @method bool saveGoogleSheets(InputfieldForm $form, array $data)
 * @method array saveGoogleSheetsReady(GoogleSheets $sheets, array $row, array $data)
 *
 */

class FormBuilderProcessor extends WireData {

	/**
	 * These flags control what actions occur when a form is submitted. 
	 *
	 */
	const saveFlagDB = 1;		// save entry to database
	const saveFlagEmail = 2; 	// Send entries to email
	const saveFlagAction2 = 4; 	// Send entries to action2 (3rd party service)
	const saveFlagPage = 8; 	// Send entries to new pages
	const saveFlagExternal = 16; 	// Submit the form somewhere else (rendering all other options invalid)
	const saveFlagFilterSpam = 32; 	// Filter for spam
	const saveFlagResponder = 64; 	// Send an auto-responder email
	const saveFlagGoogleSheets = 128; // Save to Google sheets
	
	/**
	 * Form flags
	 *
	 */
	const formFlagNoModal = 1; // disable modal window for admin/editing
	const formFlagInclude = 2; // form intended as an include for other forms
	const formFlagDisabled = 4; // form is disabled for entries
	const formFlagEmailFiles = 256; // include files as attachments in emails
	const formFlagEmailSubjectId = 512; // include entry id in email subjects
	
	/**
	 * Spam flags
	 *
	 */
	const spamFlagNone = 0; // none defined, use default reporting for spam
	const spamFlagSilent = 2; // silently discard submissions with spam
	const spamFlagVerbose = 4; // notify submitter their submission is spam
	const spamFlagEntry = 8; // save as entry with spam flag

	/**
	 * Pagination partial entry storage types
	 *
	 */
	const partialEntrySession = 0; // store as session values
	const partialEntryDatabase = 1; // storage in database entries table

	/**
	 * Instance of InputfieldForm created by this class
	 * 
	 * @var InputfieldForm
 	 *
	 */
	protected $form; 

	/**
	 * Keeps track of whether or not the form was successfully submitted (see isSubmitted method)
	 *
	 */
	protected $submitted = false;
	

	/**
	 * Cache of our submitKey so we don't ever generate more than one per request
	 *
	 */
	protected $submitKey = '';
	
	/**
	 * Cached result of validSubmitKey method or null when not yet known
	 *
	 * @var null
	 *
	 */
	protected $validSubmitKey = null;

	/**
	 * Fatal error messages from FormBuilderProcessor (these prevent final submission)
	 *
	 */
	protected $errors = array();

	/**
	 * Non-fatal warnings (these do not prevent final submission, but can be shown to user)
	 *
	 */
	protected $warnings = array();

	/**
	 * Errors that may only be shown to admin
	 * 
	 * @var array
	 * 
	 */
	protected $adminErrors = array();

	/**
	 * ID of inserted entry, if entry was saved to entries DB
	 *
	 */
	protected $entryID = 0;

	/**
	 * Last saved entry
	 * 
	 * @var null|array
	 * 
	 */
	protected $entry = null;

	/**
	 * @var FormBuilder
	 * 
	 */
	protected $forms;

	/**
	 * @var FormBuilderPartialEntriesDatabase|FormBuilderPartialEntriesSession
	 * 
	 */
	protected $partialEntries;

	/**
	 * Markup to append to the form render
	 * 
	 * @var string
	 * 
	 */
	protected $appendMarkup = '';

	/**
	 * Initialization error messages
	 * 
	 * @var array
	 * 
	 */
	protected $initErrors = array();

	/**
	 * @var int|bool|null
	 * 
	 */
	protected $submitType = null;

	/**
	 * Non-false when spam was detected (either true or name of spam filter)
	 * 
	 * @var bool|string
	 * 
	 */
	protected $isSpam = false;
	
	/**
	 * @var FormBuilderMaker
	 *
	 */
	protected $maker;

	/**
	 * @var FormBuilderProcessorSavePage|null
	 * 
	 */
	protected $pageSaver = null;

	/**
	 * @var FormBuilderProcessorSpam|null
	 * 
	 */
	protected $spammer = null;

	/**
	 * Page that was created/saved when applicable
	 * 
	 * @var null|Page
	 * 
	 */
	protected $savePage = null;

	/**
	 * Construct the FormBuilderProcessor
	 *
	 * @param FormBuilderForm $fbForm
	 * @param array $options May be any property that is set in the init() method
	 *
	 */
	public function __construct(FormBuilderForm $fbForm, array $options = array()) {
		parent::__construct();

		$wire = $fbForm->forms->wire();
		$wire->wire($this);
		$this->forms = $wire->wire('forms');
		
		$path = __DIR__ . '/';
		require_once($path . 'FormBuilderMaker.php');
		require_once($path . 'FormBuilderPartialEntries.php');
		
		$this->maker = new FormBuilderMaker($this);
	
		if($fbForm->partialEntryType == self::partialEntryDatabase) {
			require_once($path . 'FormBuilderPartialEntriesDatabase.php');
			$this->partialEntries = new FormBuilderPartialEntriesDatabase($this);
		} else {
			require_once($path . 'FormBuilderPartialEntriesSession.php');
			$this->partialEntries = new FormBuilderPartialEntriesSession($this);
		}
		
		$this->init();
		if(!empty($options)) $this->setArray($options);	
		$this->setFbForm($fbForm);
	}

	/**
	 * Initialize the FormBuilderProcessor's configuration variables
	 *
	 */
	protected function init() {
		
		// show input[type=hidden] as type=text instead?
		$this->set('showHidden', false);

		// flags that indicate what actions should occur at form save time
		$this->set('saveFlags', self::saveFlagDB);

		// require a unique session key for each form submission (for security)
		$this->set('skipSessionKey', false); 

		// name of the form, used for auto generated email subject if needed
		$this->set('formName', ''); 

		// text that appears on the submit button
		$this->set('submitText', 'Submit');

		// name of field that, when populated, invalidates the form submission
		$this->set('honeypot', '');

		// array containing list of field names and required answers
		$this->set('turingTest', array());
		
		// keywords (1 per line) that flag a form submission as spam
		$this->set('spamWords', array()); 
	
		// spam mode: 0=none, 2=silent, 4=verbose, 8=spam entry
		$this->set('spamFlags', 0); 
	
		// Name of WireMail module to use for sending, 'WireMail' for native PHP mail() or blank for auto-detect
		$this->set('mailer', $this->forms ? $this->forms->mailer : '');

		// email address to send form submissions to, may also be multiple (1 per line) or conditional (1 condition per line)
		$this->set('emailTo', ''); 		

		// email address (or field name where it resides) to use as the "reply-to" address
		$this->set('emailFrom', '');

		// The email "from" address (if different from $forms->fromEmail)
		$this->set('emailFrom2', ''); 
	
		// Email from "name", typically used only if populated and emailFrom is not a field
		$this->set('emailFromName', '');

		// subject of email that gets sent
		$this->set('emailSubject', 'Form Submission'); 
	
		// field name (not email address) that WILL contain the submittor's email address (where the responder should be sent)
		$this->set('responderTo', '');

		// Email address that the responder email should be from (if different from $forms->fromEmail)
		$this->set('responderFrom', '');
	
		// Responder from "name"
		$this->set('responderFromName', '');
		
		// Responder reply-to address
		$this->set('responderReplyTo', '');

		// Subject line for the responder email
		$this->set('responderSubject', '');

		// Body for the responder email
		$this->set('responderBody', '');

		// URL to redirect to on successful form submission
		$this->set('successUrl', ''); 

		// message to display on successful form submission, assuming no successUrl was set
		$this->set('successMessage', 'Thank you, your form has been submitted.'); 

		// message to display when a form error occurred
		$this->set('errorMessage', 'One or more errors prevented submission of the form. Please correct and try again.'); 

		// URL to send duplicate submission to
		$this->set('action2', '');

		// array of name=value to add to duplicate submission
		$this->set('action2_add', array()); 

		// array of field names to remove from duplicate submission
		$this->set('action2_remove', array()); 

		// array of field names rename before duplicate submission
		$this->set('action2_rename', array()); 

		// CSV string containing field names of: "name,email,content" (in that order)
		$this->set('akismet', '');

		// allow form field values to be pre-set from GET variables?
		$this->set('allowPreset', false); 
	
		// remember input values?
		$this->set('useCookies', false);
		
		// embed key for matching request version to response
		$this->set('embedVer', $this->forms->getEmbedVersion());
		
		// validate quantity of fields as part of submitKey ?	
		$this->set('validateNumFields' , false);

		// settings specific to saving pages from submitted forms
		parent::set('savePageParent', 0); 	// id of parent page
		parent::set('savePageTemplate', ''); 	// name of template
		parent::set('savePageFields', array()); 	// array of 'pw_field_id' => 'form_field_name'
		parent::set('savePageSubfields', '');
		parent::set('savePageStatus', 0); 	// status of saved page (0 = don't save page now)
		parent::set('savePageAdjustName', true); // adjust page name as needed to make it unique?
	
		// settings to specific to saving fields in Google sheets	
		$this->set('googleSpreadsheetFields', array());
		$this->set('googleSpreadsheetUrl', '');
		$this->set('googleSheetsResponseField', '');
	
		// form framework, if in use
		parent::set('framework', ''); 
	
		// FormBuilerForm object, if set
		parent::set('fbForm', null);
		
		// FormBuilderRender object, if set
		parent::set('fbRender', null);
		
		parent::set('pluginActions', array()); 

		// most of the frameworks require this, those that don't (Legacy) set it manually in their constructor
		$config = $this->wire()->config;
		$config->inputfieldColumnWidthSpacing = 0;
	}

	/**
	 * Set form processor setting
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return WireData
	 * 
	 */
	public function set($key, $value) {
		if($key === 'savePageParent' && !is_int($value)) {
			// something other than parent ID integer being set to savePageParent
			if(ctype_digit("$value")) {
				// works for string or Page object
				$value = (int) "$value"; 
			} else if(is_string($value) && strpos($value, '/') !== false) {
				// page path
				$value = $this->wire()->pages->get($value);
			}
		} else if($key === 'savePageTemplate' && (!is_string($value) || ctype_digit("$value"))) {
			// something other than template name being set to savePageTemplate
			if($value instanceof Template) {
				$value = $value->name;
			} else if(ctype_digit("$value")) {
				$value = $this->wire()->templates->get((int) "$value"); 
				$value = $value instanceof Template ? $value->name : '';
			}
		} 
		return parent::set($key, $value); 
	}

	/**
	 * Get property
	 * 
	 * @param string $key
	 * @return mixed
	 * 
	 */
	public function get($key) {
		if($key === 'maker') return $this->maker;
		if($key === 'entries') return $this->entries();
		if($key === 'partialEntries') return $this->partialEntries;
		return parent::get($key);
	}

	/**
	 * Set the FormBuilderForm instance
	 * 
	 * @param FormBuilderForm $fbForm
	 * 
	 */
	public function setFbForm(FormBuilderForm $fbForm) {
		$this->set('fbForm', $fbForm);
		$this->initForm($fbForm);
	}

	/**
	 * Initialize a FormBuilder form and create the associated InputfieldForm
	 * 
	 * @param FormBuilderForm $fbForm
	 * 
	 */
	protected function initForm(FormBuilderForm $fbForm) {

		$this->set('id', $fbForm->id);
		$this->set('formName', $fbForm->name);
		$this->set('skipSessionKey', $fbForm->skipSessionKey);
		$this->errors = array(); // ensure errors are clear
		$this->warnings = array();
	
		try {
			$fbRender = $fbForm->getFbRender();
			if($fbRender) $this->setFbRender($fbRender);

			$this->form = $this->maker->makeInputfieldForm($fbForm);
			
			if($this->maker->hasPagination()) {
				if($this->maker->getPageNumToRender() > $this->maker->getNumPaginations()) {
					throw new FormBuilderException($this->_('Requested pagination is out of bounds'));
				}
				$this->partialEntries->init();
				$this->form->addClass('FormBuilderPagination');
				//$this->partialEntries->submitData($this->maker->submitData(true));
			}
		} catch(FormBuilderException $e) {
			$this->initError($e->getMessage());
		}

		// load plugin actions
		$pluginActions = array();
		foreach($this->pluginActions as $moduleName) {
			/** @var FormBuilderProcessorAction $module */
			$module = $this->wire()->modules->getModule($moduleName);
			if(!$module instanceof FormBuilderProcessorAction) continue;
			$settings = $fbForm->get($moduleName);
			$module->processor($this);
			$module->form($this->form);
			$module->fbForm($fbForm);
			if(is_array($settings)) $module->setArray($settings);
			$pluginActions[$moduleName] = $module;
			if(!$module->isEnabled()) continue;
			if($this->entry) $module->entry($this->entry);
			$module->actionReady();
		}
		$this->pluginActions = $pluginActions;
		$this->executePluginActions('formReady');
	}
	
	/**
	 * Set the FormBuilderRender instance
	 * 
	 * @param FormBuilderRender $renderer
	 * 
	 */
	public function setFbRender(FormBuilderRender $renderer) {
		$this->set('fbRender', $renderer);
	}
	
	/**
	 * Populate the form with the key=value data given in the array
	 * 
	 * @param array $data key=value associative array
	 * @param int $entryID The id of the entry the data is from
	 * @return InputfieldForm Form that was populated
	 *
	 */
	public function ___populate(array $data, $entryID = 0) {
		$entryID = (int) $entryID; 
		$this->setEntry($data);
		$this->sessionSet('entryID', $entryID);
		$this->maker->populateForm($this->form, $data, $entryID);
		return $this->form;
	}

	
	/*****************************************************************************************************
	 * FORM RENDERING
	 *
	 */
	
	/**
	 * Return the rendered form output, whether an actual form or the success message after submitted.
	 *
	 * @param int|null $id Optional ID of entry, if it already exists 
	 * @return string
	 *
	 */
	public function ___render($id = null) {

		if($id === null) $id = $this->getEntryID();
		$entryID = $id;
		unset($id);
	
		$input = $this->wire()->input;
		$config = $this->wire()->config;
		$modules = $this->wire()->modules;
		$user = $this->wire()->user;
		$page = $this->wire()->page;

		$form = $this->form;
		$fbForm = $this->fbForm;
		$fileEmbedD = $config->paths->templates . "FormBuilder/form-$this->formName.php";
		$submitType = $this->getSubmitType();
		$isEditor = $user->hasPermission('form-builder');
		$inAdmin = $page->template->name === 'admin';
		$isPreview = $isEditor && ($input->get('preview') || $input->post('FormBuilderPreview'));
		$isExportD = $input->get('export_d') && $isEditor;
		$isEmbedD = !$isPreview && !$inAdmin && is_file($fileEmbedD);
		$isDisabled = $fbForm->hasFlag(self::formFlagDisabled);
		$framework = null;
		$out = $isEmbedD ? '' : "\n<!-- " . FormBuilderMain::RCD . " -->\n";
		
		$form->protectCSRF = $this->skipSessionKey ? false : true;
		$this->sessionSet('formID', $this->id); // @todo does anything still use this?

		if(!$this->forms->forms()->isValidLicense()) {
			$this->initError($this->_('No valid product key detected'));
		}

		if(!$fbForm->hasPermission('form-submit')) {
			$this->initError($this->_('This form is not available at your access level.'));
		}
		
		if($isDisabled && !$isPreview) {
			$this->initError($this->_('This form is disabled.'));
		}

		if($this->useCookies) {
			$form->addClass('FormBuilderUseCookies');
			/** @var JqueryCore $jQuery */
			$jQuery = $modules->get('JqueryCore');
			$jQuery->use('cookie');
		}
		
		// load the framework used for this form
		if(!$this->isAdmin()) {
			if($this->framework) $fbForm->framework = $this->framework;
			$framework = $this->forms->getFramework($fbForm);
			if($framework) {
				$framework->ready();
				$framework->load();
				$form->addClass($framework->className());
			}
		}
		
		if(count($this->initErrors)) {
			return $this->wrapOutput($this->renderErrors($this->initErrors));
		}
		
		if($submitType === FormBuilderMaker::submitTypeNone) {
			// the form was not submitted in this request
			$this->clearErrors();

		} else if($submitType === FormBuilderMaker::submitTypeInvalid) {
			// attempted but failed submit
			if(!count($this->errors)) $this->addError($this->_('Invalid form submission'));
			$this->formSubmitInvalid($form, $this->adminErrors);

		} else if($submitType) {
			// successful submit: final, next, prev or retry
			$out .= $this->renderSubmittedLandmark();
			// process submitted data
			$this->executePluginActions('processReady');
			$processInputResult = $this->processInput($entryID, $submitType);
			if($processInputResult === true) {
				// true: successful final submit (also sets $this->submitted === true)
				if(!$this->isSpam) $this->formSubmitSuccess($this->form);
				if($this->successUrl) {
					// if there is a success URL, redirect to it (not typically used)
					$this->wire()->session->location($this->successUrl);
				} else if($isEmbedD) {
					// control will be passed to the $formFile
				} else {
					// render success message
					return $this->wrapOutput($out . $this->renderSuccess($this->successMessage));
				}
				// note: processInput() method calls formSubmitError() for all error conditions
			} else if($processInputResult === false) {
				// false: form was submitted but there were errors
			} else if(is_int($processInputResult)) {
				// 0: no errors, but a non-final paginated submit
			} else if($processInputResult === null) {
				// null: errors occurred during form save
			}
			
			// these two can be changed when pagination is active, so get fresh copies
			$form = $this->getInputfieldsForm();
		}
	
		$errors = array_merge($this->getErrors(), $this->getWarnings());

		// give the form a unique & predictable ID attribute
		$form->attr('id', 'FormBuilder_' . $form->name);
		$form->addClass('FormBuilder'); 
		$form->addClass('InputfieldNoFocus');
		$form->appendMarkup .= $this->renderSubmitKey($isEmbedD ? '{submitKey}' : '') . $this->appendMarkup;
		
		if($isExportD) {
			// generate the embed method D file
			$formOut = $this->renderExportEmbedMethodD($form, $framework);
			
		} else if($isPreview) {
			// we are in preview mode with an admin/editor user
			if($isDisabled) $errors[] = $this->_('This form is disabled (shown only for admin preview purposes).');
			$this->executePluginActions('renderReady');
			$formOut = $this->renderPreview($form);

		} else if($isEmbedD) {
			// we are rendering from a custom markup file in /site/templates/FormBuilder/	
			$this->clearErrors();
			$this->executePluginActions('renderReady');
			$formOut = $this->renderEmbedMethodD($form, $fileEmbedD, $framework, $this->submitted, $errors);
			$errors = array();

		} else {
			// normal form render
			$this->executePluginActions('renderReady');
			$formOut = $this->renderReady($form);
		}

		$errors = array_unique(array_merge($errors, $this->getErrors(), $this->getWarnings()));
		if(count($errors)) $out .= $this->renderErrors($errors);
		$this->clearErrors();
		$out .= $formOut;

		if($this->saveFlags & self::saveFlagFilterSpam) $this->spammer()->rendered($out);

		return $this->wrapOutput($out);
	}

	/**
	 * Render the preview as used in the FormBuilder admin
	 * 
	 * @param InputfieldForm $form
	 * @return string
	 * 
	 */
	protected function renderPreview(InputfieldForm $form) {
		$out = $this->renderReady($form);
		
		// add a hidden input for JS detection to add edit links to form fields
		$p = $this->wire()->pages->get("template=admin, name=" . FormBuilderMain::name);
		if(!$p->id) return $out;
		
		$out = str_replace(
			"</form>",
			"<input type='hidden' name='FormBuilderPreview' id='FormBuilderPreview' value='{$p->url}editField/?id={$this->id}&name=' />" .
			"\n</form>",
			$out
		);
		
		return $out;
	}

	/**
	 * Render an embed method D form file
	 * 
	 * @param InputfieldForm $form
	 * @param string $formFile
	 * @param FormBuilderFramework|null $framework
	 * @param bool|int $submitType
	 * @param array $errors
	 * @return string
	 * 
	 */
	protected function renderEmbedMethodD(InputfieldForm $form, $formFile, $framework, $submitType, array $errors) {
		
		$values = array();
		$labels = array();
		$descriptions = array();
		$notes = array();
		$sanitizer = $this->wire()->sanitizer;
		$submitKey = $this->maker()->makeSubmitKey($this->form);
		$submitted = $submitType === true; 

		foreach($errors as $key => $error) {
			$errors[$key] = $sanitizer->entities($error);
		}

		foreach($form->getAll() as $inputfield) {
			$name = $inputfield->attr('name');
			$value = $inputfield->attr('value');
			if(is_object($value)) $value = (string) $value;
			$values[$name] = $value;
			$labels[$name] = $sanitizer->entities($inputfield->label);
			$descriptions[$name] = $sanitizer->entities($inputfield->description);
			$notes[$name] = $sanitizer->entities($inputfield->notes);
			if($inputfield->className() == 'InputfieldSubmit') $labels[$name] = $sanitizer->entities($value);
		}

		$out = $this->renderReady($form, $formFile, array(
			'submitted' => $submitted,
			'errors' => $errors,
			'values' => $values,
			'labels' => $labels,
			'descriptions' => $descriptions,
			'notes' => $notes,
			'form' => $form,
			'fbForm' => $this->fbForm,
			'fbRender' => $this->fbRender,
			'processor' => $this,
			'framework' => $framework,
			'successMessage' => $submitted ? $sanitizer->entities($this->successMessage) : '',
			'submitKey' => $submitKey,
		));

		if(!strpos($out, $submitKey)) { 
			if(strpos($out, '_submitKey')) {
				$re = '!<input\s+type=["\']hidden["\']\s+name=["\']_submitKey["\']\s+value=["\'][^"\']+["\']\s?/?>!';
				$out = preg_replace($re, $this->renderSubmitKey($submitKey), $out);
			} else {
				$out = str_replace('</form>', $this->renderSubmitKey($submitKey) . '</form>', $out); 
			}
		}
		
		return $out;
	}

	/**
	 * Export an embed method D file (this is only used on the admin side)
	 * 
	 * @param InputfieldForm $form
	 * @param FormBuilderFramework|null $framework
	 * @return string
	 * 
	 */
	protected function renderExportEmbedMethodD(InputfieldForm $form, $framework) {
		
		$out = '';
		$formFile = null;
		$hasPagination = null;
		$texts = array(
			'labels' => array(), 
			'descriptions' => array(), 
			'notes' => array()
		);
		
		foreach($form->getAll() as $inputfield) {
			$texts['labels'][$inputfield->name] = $inputfield->label;
			$texts['descriptions'][$inputfield->name] = $inputfield->description;
			$texts['notes'][$inputfield->name] = $inputfield->notes;
			if($inputfield->label) $inputfield->label = "{pwfb:labels:$inputfield->name}";
			if($inputfield->description) $inputfield->description = "{pwfb:descriptions:$inputfield->name}";
			if($inputfield->notes) $inputfield->notes = "{pwfb:notes:$inputfield->name}";
			if($inputfield->className() == 'InputfieldSubmit') {
				$texts['labels'][$inputfield->name] = $inputfield->attr('value');
				$inputfield->attr('value', "{pwfb:labels:$inputfield->name}");
			}
			if($inputfield->className() == 'InputfieldFormBuilderPageBreak') {
				$hasPagination = true;
			}
		}
		
		if($hasPagination) {
			$out = 
				"<h3>" . $this->_('Form export fail') . "</h3>" .
				$this->_('Forms with pagination cannot use embed method D. Please use embed A, B or C.'); 
		} else {
			$out .= $this->renderReady($this->form);
			include_once(__DIR__ . '/FormBuilderMarkup.php');
			$markup = new FormBuilderMarkup($out, $form, $framework, $texts);
			$cachePath = $this->wire()->config->paths->cache . 'FormBuilder/';
			$exportFile = $cachePath . "form-$this->formName.php";
			$markup->saveTo($exportFile);
			$out = 
				"<h3>" . $this->_('Form Markup Exported:') . "</h3>" .
				"<p>$exportFile</p>";
		}
		
		$out =
			"<div style='text-align:center;font-family:sans-serif;'>" .
			$out . 
			"<p><small>" . $this->_('You may close this window.') . "</small></p>" .
			"</div>";
		
		return $out;
	}

	/**
	 * Hook called for render ready, returns the output of $form->render();
	 * 
	 * NOTE: the $formFile and $vars arguments are only populated when using embed method D (custom form file). 
	 * 
	 * @param InputfieldForm $form
	 * @param string $formFile Only present in embed method D
	 * @param array $vars Only present in embed method D
	 * @return string
	 * 
	 */
	protected function ___renderReady($form, $formFile = '', array $vars = array()) {
		
		$this->renderOrProcessReady($form, FormBuilderMaker::submitTypeNone);
		
		if($this->saveFlags & self::saveFlagFilterSpam) $this->spammer()->renderReady($form);

		if($formFile) {
			return wireRenderFile($formFile, $vars);
		} 

		if($form->children()->count() < 2) {
			$btn = $form->child();
			if($btn instanceof InputfieldSubmit) {
				$form->error($this->_('Form has no fields'));
				$form->remove($btn);
			}
		}
		
		$form->columnWidthSpacing = (int) $this->wire()->config->inputfieldColumnWidthSpacing;

		if(!$form->hasClass('InputfieldFormNoWidths')) {
			$classes = InputfieldWrapper::getClasses();
			$classes = explode(' ', $classes['form']);
			if(!in_array('InputfieldFormNoWidths', $classes)) $form->addClass('InputfieldFormWidths');
		}

		// Embed A/B/C: if current request doesn't use a trailing slash, specifically set the form action attribute
		$inputUrl = $this->wire()->input->url();
		if(substr($inputUrl, -1) !== '/') $form->attr('action', $inputUrl);
	
		if($this->partialEntries->active) {
			$this->partialEntries->renderReady($form);
		}
		
		return $form->render();
	}

	/**
	 * Hook called when form is ready to be either rendered or processed
	 * 
	 * #pw-internal
	 * 
	 * @param InputfieldForm $form
	 * @param int $submitType One of the FormBuilderMaker::submitType* constants
	 * @since 0.4.7
	 * 
	 */
	public function ___renderOrProcessReady($form, $submitType) { 
		if($submitType === FormBuilderMaker::submitTypePrev) {
			// required fields are not required if clicking to previous pagination
			foreach($form->getAll() as $f) {
				if($f->required) $f->required = false;
			}
		}
	}
	
	/**
	 * Wraps all FormBuilder output
	 *
	 * @param string $out Output to wrap
	 * @return string
	 *
	 */
	public function ___wrapOutput($out) {
		return "<div class='FormBuilder FormBuilder-$this->formName FormBuilder-$this->id'>\n$out\n</div><!--/.FormBuilder-->";
	}

	/** 
	 * Render the submitKey in a hidden form field, ready to be output
	 *
	 * @param string $submitKey Supply existing submitKey to only render the input for it
	 * @return string
	 *
	 */
	public function renderSubmitKey($submitKey = '') {
		if(empty($submitKey)) $submitKey = $this->maker->makeSubmitKey($this->form);
		return "<input type='hidden' name='_submitKey' value='$submitKey' />";
	}

	
	/*****************************************************************************************************
	 * PROCESS FORM INPUT
	 *
	 */

	/**
	 * Process the input for a submitted form
	 *
	 * @param int $id Optional id of entry, if it already exists
	 * @param int|bool $submitType Type of submission (true: final submit, 1=next pagination, -1=previous pagination)
	 * @return bool|int|null True on final submit success, false on errors preventing save, 0 on paginated submit, null on errors during save
	 *
	 */
	protected function ___processInput($id, $submitType) {

		$entryID = $id; 
		$isFinalSubmit = $submitType === FormBuilderMaker::submitTypeFinal;
		$partialEntries = $this->partialEntries;
		$maker = $this->maker;
		$hasPagination = $maker->hasPagination();
		$input = $this->wire()->input;
		$spamFlags = (int) $this->spamFlags;

		// check for spam before process input (used by honeypot filters)
		$spam = $this->processInputIsSpam($this->form, false);
		if($spam) {
			// check if we can exit early
			if(!($spamFlags & self::spamFlagEntry)) {
				if(!($spamFlags & self::spamFlagVerbose)) return true;
			}
		}	
		
		// let the form process itself
		$this->processInputReady($this->form, $submitType); 
		$this->form->processInput($input->post);

		if($isFinalSubmit) {
			$spam = $this->processInputIsSpam($this->form, true); 
			if($spam) {
				if($spamFlags & self::spamFlagEntry) {
					// save as entry but flagged as spam
				} else if(!count($this->getErrors())) {
					return true; // silent fail
				}
			}
		}

		$this->processInputDone($this->form);
		
		if($hasPagination) { 
			$partialEntries->processPagination($this->form, $submitType);
			if($isFinalSubmit) $isFinalSubmit = $partialEntries->finishPagination($this->form);
		}
	
		if($submitType === FormBuilderMaker::submitTypeRetry) {
			$this->addError($this->_('The session may have expired so we have started new one - please try to submit this form again.'));
		} else if($submitType === FormBuilderMaker::submitTypeDuplicate) {
			$this->addError($this->_('This form appears to have already been submitted.')); 
		}
		
		$errors = $this->getErrors();
		
		// if errors occurred then trigger error hooks and return
		if(count($errors)) {
			// errors occurred
			$this->formSubmitError($this->form, $errors);
			return false;
		} else if($hasPagination && !$isFinalSubmit) {
			// no errors, but a pagination was submitted
			return 0;
		}

		// save the form
		$entryID = $this->saveForm($this->form, $entryID, $submitType);
		if(is_int($entryID)) $this->entryID = $entryID;

		// one more check for errors after saveForm()
		$errors = $this->getErrors(); 
		if(count($errors)) {
			$this->formSubmitError($this->form, $errors);
			return null;
		}

		return true; 
	}

	/**
	 * Hook called right before input is processed
	 * 
	 * @param InputfieldForm $form
	 * @param int $submitType One of the FormBuilderMaker::submitType* constants
	 * 
	 */
	protected function ___processInputReady(InputfieldForm $form, $submitType) { 
		$this->renderOrProcessReady($form, $submitType);
	}

	/**
	 * Hook called immediately after input is processed 
	 * 
	 * @param InputfieldForm $form
	 * 
	 */	
	protected function ___processInputDone(InputfieldForm $form) { }

	/**
	 * Does given processed form contain spam?
	 * 
	 * - Returns name of spam filter that was triggered if yes. 
	 * - Returns blank string if no. 
	 * - This is called twice: once before input processed, once after.
	 * 
	 * Note: form will fail silently if spam is detected, unless spam filter adds $form->error().
	 * 
	 * @param InputfieldForm $form
	 * @param bool $processed Has input been processed yet?
	 * @return string
	 * @since 0.4.7
	 * 
	 */
	protected function ___processInputIsSpam(InputfieldForm $form, $processed) {
		$spam = '';
		if($this->saveFlags & self::saveFlagFilterSpam) {
			$spam = $this->spammer()->isSpam($form, $processed);
			if($spam) $this->processInputSpammed($form, $spam);
		}
		return $spam;
	}
	
	/**
	 * Hook called when processInput fails due to being spammed
	 *
	 * @param InputfieldForm $form
	 * @param string $spamType Name of spam filter that was triggered (i.e. honeypot, akismet, turingTest, keywords)
	 *
	 */
	public function ___processInputSpammed(InputfieldForm $form, $spamType) {
		if($form && $spamType) {} // arguments for hooks
		$this->isSpam = $spamType ? $spamType : true;
		if($this->spamFlags & self::spamFlagVerbose) $this->addError($this->_('Spam filter has been triggered'));
	}

	/**
	 * Was posted submit key valid?
	 *
	 * @param array $options
	 *  - `testOnly` (bool): Only test that the form name is present in submitKey? (default=false)
	 *  - `verbose` (bool): Be verbose with admin error messages (default=true)
	 *  - `getError` (bool): Return error message (string) rather than false when error occurs? (default=false)
	 *  - `getErrorName` (bool): Return error name (string) rather than false when error occurs? (default=false)
	 * @return bool|string
	 *
	 */
	public function validSubmitKey($options = array()) {

		$defaults = array(
			'testOnly' => false,
			'verbose' => true,
			'getError' => false,
			'getErrorName' => false,
		);

		if(!is_array($options)) $options = array('testOnly' => $options);
		$options = array_merge($defaults, $options);

		// return cached value if available
		if($this->validSubmitKey === true) return true;
		if($this->validSubmitKey === false && !$options['getError'] && !$options['getErrorName']) return false;

		// first check if form posted
		$submitKey = $this->wire()->input->post('_submitKey');
		$a = $this->maker->parseSubmitKey($submitKey);
		$sessionKeyLast = $this->sessionGet('sessionKey');
		$error = '';

		$errors = array(
			'key-missing' => 'Missing submit key',
			'key-short' => 'Submit key is too short',
			'wrong-form' => 'Wrong form name',
			'wrong-qty' => 'Number of fields in submit key does not match number in form',
			'missing-0' => 'Skip value "0" expected but not present in submit key',
			'duplicate' => 'One-time-use value in submit key duplicates previously used one',
			'wrong-key' => 'One-time-use value in session does not match one submitted with form',
		);

		if($submitKey === null || !strlen($submitKey)) {
			$error = 'key-missing';

		} else if($a['qty'] < 3) {
			$error = 'key-short';

		} else if($a['formName'] !== $this->formName) {
			$error = 'wrong-form';

		} else if($options['testOnly']) {
			// only testing for valid form name

		} else if($this->validateNumFields && $a['numFields'] != count($this->form->children)) {
			$error = 'wrong-qty';

		} else if($this->skipSessionKey) {
			// session key not in use, so a '0' is substituted
			// if($a['sessionKey'] != "0") $error = 'missing-0';
			if($this->form) $this->form->protectCSRF = false;

		} else if($a['sessionKey'] === $sessionKeyLast) {
			// SUCCESS session key matched
			$this->sessionRemove('sessionKey');
			$this->sessionSet('sessionKeyLast', $sessionKeyLast);

		} else if($a['sessionKey'] === $this->sessionGet('sessionKeyLast')) {
			// check if its a previous submit key, perhaps they just double submitted? 
			$this->addError($this->_('This form was already submitted.'), true);
			$error = 'duplicate';

		} else {
			if($this->wire()->user->isSuperuser()) $this->addError(print_r($a, true) . ' ... ' . $sessionKeyLast);
			$error = 'wrong-key';
		}

		if($error) {
			$this->validSubmitKey = false;
			$message = "$error: " . $errors[$error];
			if($options['verbose']) $this->adminError($message);
			if($options['getErrorName']) return $error;
			if($options['getError']) return $message;
		} else {
			$this->validSubmitKey = true;
		}

		return $this->validSubmitKey;
	}

	
	/*****************************************************************************************************
	 * SAVE FORM
	 *
	 */

	/**
	 * Save the form to the database entry, page, or email(s) per form action settings
	 *
	 * @param InputfieldForm $form
	 * @param int|array $entry Optional id of entry (or entry array), if it already exists, 0 if not
	 * @param int|bool $submitType Submit type (default=null, which makes it ask from getSubmitType() method in this class)
	 * @return int ID of inserted entry (if saving to entries database) or boolean true if not.
	 *
	 */
	public function ___saveForm(InputfieldForm $form, $entry = 0, $submitType = null) {
		
		$entryID = 0;
		$hasPagination = $this->maker->hasPagination();
		$formPaginated = null;
		$saveFlags = $this->saveFlags;
		$hasEntry = false;

		if($submitType === null) $submitType = $this->getSubmitType();

		if(is_array($entry) && !empty($entry)) {
			$id = isset($entry['id']) ? (int) $entry['id'] : 0;
			$data = $entry;
		} else {
			$id = is_array($entry) ? 0 : (int) $entry;
			$data = $this->formToEntry($form, $id, $id > 0);
		}
		unset($entry);
		
		$this->saveFormReady($form, $data, array(
			'hasPagination' => $hasPagination, 
			'saveFlags' => $saveFlags, 
			'entryID' => $id, 
			'submitType' => $submitType,
			'isFinalSubmit' => ($submitType === FormBuilderMaker::submitTypeFinal),
		));
		
		// save the form to a page (when no pagination)
		if(($saveFlags & self::saveFlagPage) && !$hasPagination && !$this->isSpam) {
			$data['_savePage'] = (int) ((string) $this->savePage($data));
			if($data['_savePage']) $data['_savePageTime'] = time();
		}

		// save the form to the DB
		if($this->saveFlags & self::saveFlagDB) {
			if($this->isSpam) {
				$entryFlags = isset($data['entryFlags']) ? $data['entryFlags'] : 0;
				$entryFlags = $entryFlags | FormBuilderEntries::flagSpam;
				$data['entryFlags'] = $entryFlags;
			}
			if($hasPagination) {
				// final pagination save
				$this->entry = $this->partialEntries->save($form, $data, true); 
				if($this->entry) $entryID = $this->entry['id'];
				$saveFlags = $saveFlags & ~self::saveFlagDB;
			} else {
				// regular entry save (saveEntry populates $this->entry)
				$entryID = $this->saveEntry($data);
			}
			$data = $this->entry;
			$hasEntry = true;
		}

		// early exit when spam detected (only save spam entry)
		if($this->isSpam) return $entryID; 
	
		// get entire form for use in all remaining actions
		if($hasPagination && $hasEntry && $saveFlags) {
			$formPaginated = $form;
			$form = $this->maker()->makeInputfieldForm($this->fbForm, -1); // -1=all paginations
			$this->maker()->populateForm($form, $data, $entryID);
			$this->setInputfieldsForm($form);
		}
		
		// save the form to a page (when pagination present)
		if(($saveFlags & self::saveFlagPage) && $hasPagination) {
			$data['_savePage'] = (int) ((string) $this->savePage($data));
			if($data['_savePage']) {
				$data['_savePageTime'] = time();
				if(!empty($data['id'])) $this->saveEntry($data); // update already saved entry
			}
		}

		// entryID is not saved in DB and only used by some hooks (same as $data['id'])
		$data['entryID'] = $entryID; 
		
		// Email the form to recipient(s) if applicable
		if($this->saveFlags & self::saveFlagEmail) {
			$emailResult = $this->emailForm($form, $data);
			if($emailResult === true) {
				// success
			} else if($emailResult === 1) {
				// cancelled (by hook or missing emailTo address)
			} else if($emailResult === 0) {
				// no email address to send to
			} else if($emailResult === false) {
				// failed
				$this->addWarning(
					$this->_('Unable to verify successful email delivery of this form submission.')
				);
			} 
		}			

		// Send an auto-responder if applicable
		if($this->saveFlags & self::saveFlagResponder) {
			$emailResult = $this->emailFormResponder($form, $data);
			if($emailResult === true) {
				// success
			} else if($emailResult === false) {
				// fail
			} else if($emailResult === 0) {
				// no email address to send to
			} else if($emailResult === 1) {
				// cancel
			}
		}
	
		// if there is a secondary action, then initiate a duplicate post
		if(($this->saveFlags & self::saveFlagAction2) && $this->action2) {
			$this->postAction2($data);
		}
		
		// google sheets
		if(($this->saveFlags & self::saveFlagGoogleSheets) && $this->googleSpreadsheetUrl) {
			$this->saveGoogleSheets($form, $data);
		}
	
		// restore partial/paginated form if any errors were present and it will be re-rendered
		if($formPaginated && count($this->getErrors())) {
			$this->setInputfieldsForm($formPaginated);
		}

		return $entryID;
	}

	/**
	 * Hook called when form is about to be saved
	 * 
	 * This hook can be more useful than the saveForm() one because more info is available when this is called.
	 * 
	 * @param InputfieldForm $form
	 * @param array $entry Form converted to entry array
	 * @param array $info Additional information (see call in saveForm method)
	 * @since 0.4.5
	 * 
	 */
	protected function ___saveFormReady(InputfieldForm $form, array $entry, array $info) { }

	
	/*****************************************************************************************************
	 * FORM ENTRIES
	 *
	 */
	
	/**
	 * Get the FormBuilderEntries object for the current form
	 *
	 * @return FormBuilderEntries
	 *
	 */
	public function entries() {
		/** @var FormBuilderForm $fbForm */
		$fbForm = $this->get('fbForm');
		if($fbForm) {
			return $fbForm->entries();
		} else {
			// not likely, but just in case
			return $this->forms->get($this->formName)->entries();
		}
	}

	/**
	 * Get the FormBuilderEntries object for the current form (legacy alias)
	 *
	 * @return FormBuilderEntries
	 * @deprecated
	 *
	 */
	public function getEntries() {
		return $this->entries();
	}

	/**
	 * Converts the submitted InputfieldForm object to an entry data array
	 * 
	 * Called by saveForm()
	 * 
	 * @param InputfieldForm $form
	 * @param int $id
	 * @param bool $merge Merge with any existing entry already loaded?
	 * @return array
	 *
	 */
	public function ___formToEntry(InputfieldForm $form, $id = 0, $merge = false) {
		$data = empty($this->entry) || !$merge ? array() : $this->entry;
		// prepare a $data array that is used by DB or action2 saves
		foreach($form->getAll() as $f) {
			if($f instanceof InputfieldWrapper) continue;
			if($f instanceof InputfieldSubmit) continue;
			if($f->className() === 'InputfieldFormBuilderPageBreak') continue;
			$value = $f->attr('value');
			if(is_object($value)) $value = (string) $value;
			$data[$f->name] = $value;
		}
		if($this->saveFlags & self::saveFlagDB) {
			$data['id'] = $id;
		}
		return $data;
	}

	/**
	 * Load entry by ID or name and populate to form
	 *
	 * @param int|string|array $entryID Populate entry by entry ID, name, or array of data
	 * @return InputfieldForm
	 * @since 0.4.5
	 *
	 */
	public function loadEntry($entryID) {
		if(is_int($entryID) || ctype_digit("$entryID")) {
			$entry = $this->entries()->getById((int) $entryID);
		} else if(is_string($entryID)) {
			$entry = $this->entries()->getByName($entryID);
		} else if(is_array($entryID)) {
			$entry = $entryID;
		} else {
			$entry = array();
		}
		$entryID = isset($entry['id']) ? (int) $entry['id'] : 0;
		if($entryID) {
			$this->setEntry($entry);
			$this->entryID = $entryID;
		}
		return $this->populate($entry, $entryID);
	}

	/**
	 * Save form entry
	 * 
	 * Note: if it is an existing entry, a non-zero "id" property will appear in the given $data.
	 * 
	 * @param array $data Entry data
	 * @return int Entry ID
	 * 
	 */
	public function ___saveEntry(array $data) {
		
		require_once(dirname(__FILE__) . '/FormBuilderEntries.php');
		
		$entries = $this->entries();
		$isNew = empty($data['id']); 
	
		if($isNew) {
			$data = $this->addEntryReady($data, $this->form); 
		} else {
			$data = $this->updateEntryReady($data, $this->form);
		}
		
		$entryID = (int) $entries->save($data); // returns entry ID
		$data['id'] = $entryID;
		$this->entryID = $entryID;
		
		if($isNew) {
			$entry = $this->addedEntry($data, $this->form); 
		} else {
			$entry = $this->updatedEntry($data, $this->form);
		}
		
		if($entry !== $data) {
			// entry changed in added or updated hook, so re-save with changes
			$entries->save($entry);
		}
		
		$this->setEntry($entry);
		
		return $entryID;
	}

	/**
	 * Hook called when new entry is about to be added
	 * 
	 * @param array $data Entry data
	 * @param InputfieldForm $form
	 * @return array Entry data (optionally modified)
	 * @since 0.3.10
	 * 
	 */
	protected function ___addEntryReady(array $data, InputfieldForm $form) {
		if($form) { /* ignore */ }
		return $data;
	}

	/**
	 * Hook called when an existing entry is about to be updated
	 *
	 * @param array $data Entry data
	 * @param InputfieldForm $form
	 * @return array Entry data (optionally modified)
	 * @since 0.3.10
	 *
	 */
	protected function ___updateEntryReady(array $data, InputfieldForm $form) {
		if($form) { /* ignore */ }
		return $data;
	}
	
	/**
	 * Hook called after a new entry has been added
	 * 
	 * If returned $data differs from given $data in any way, it will be re-saved after this function, but this
	 * hook will not be called a second time. 
	 * 
	 * @param array $data
	 * @param InputfieldForm $form
	 * @return array
	 * 
	 */
	protected function ___addedEntry(array $data, InputfieldForm $form) {
		if($form) { /* ignore */ } 
		return $data;
	}

	/**
	 * Hook called when an existing entry has been added
	 *
	 * If returned $data differs from given $data in any way, it will be re-saved after this function, but this
	 * hook will not be called a second time.
	 *
	 * @param array $data
	 * @param InputfieldForm $form
	 * @return array
	 *
	 */
	protected function ___updatedEntry(array $data, InputfieldForm $form) {
		if($form) { /* ignore */ }
		return $data;
	}

	/**
	 * Get the current entry ID, or 0 if not present
	 *
	 * @return int
	 *
	 */
	public function getEntryID() {
		return $this->entryID;
	}

	/**
	 * Set current entry data
	 *
	 * @param array $data
	 *
	 */
	public function setEntry(array $data) {
		$this->entry = $data;
		if(!empty($data['id'])) $this->entryID = $data['id'];
	}

	/**
	 * Get the current form entry, or null if not present
	 *
	 * @param int $id Optional ID of entry to get (default=current entry)
	 * @return array|null
	 *
	 */
	public function getEntry($id = 0) {
		if($this->entry) {
			if(!$id || $id == $this->entry['id']) return $this->entry;
		}
		if(empty($id)) {
			$id = (int) $this->entryID;
		}
		return $id > 0 ? $this->entries()->getById((int) $id) : null;
	}

	/**
	 * Get page that was saved 
	 * 
	 * @return NullPage|Page
	 * @since 0.5.2
	 * 
	 */
	public function getSavePage() {
		$pages = $this->wire()->pages;
		$page = $this->savePage;
		if(!$page) {
			$entry = $this->getEntry();
			if($entry && !empty($entry['_savePage'])) {
				$page = $pages->get((int) $entry['_savePage']); 	
			}
		}
		if(!$page) {
			$page = $this->wire()->pages->newNullPage();
		}
		return $page;
	}
	
	/*****************************************************************************************************
	 * ACTION: SAVE PAGE
	 * 
	 */

	/**
	 * Get settings for saving a page
	 * 
	 * @param array $data
	 * @param null|int $status
	 * @param null|array $onlyFields
	 * @return array
	 * 
	 */
	public function ___savePageInit(array $data, $status, $onlyFields) {
		return $this->pageSaver()->savePageInit($data, $status, $onlyFields);
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
	public function ___savePage(array $data, $status = null, $onlyFields = null) {
		return $this->pageSaver()->savePage($data, $status, $onlyFields);
	}

	/**
	 * Check and update page name as needed for uniqueness
	 * 
	 * @param Page $page
	 * @return bool Return true on success, or false if save should be aborted
	 * 
	 */
	public function ___savePageCheckName(Page $page) {
		return $this->pageSaver()->savePageCheckName($page);
	}
	
	/**
	 * Returns true if given value should be saved, false if not
	 *
	 * @param Page $page Page being saved
	 * @param string $pageFieldName Name of Page field being saved. Can also be "field.subfield"
	 * @param string $formFieldName Name of form field the value came from
	 * @param string $value Value of field
	 * @param array $entry The entire original entry data (in case you need anything from it)
	 * @return bool
	 *
	 */
	public function ___allowSavePageField(Page $page, $pageFieldName, $formFieldName, $value, $entry) {
		// support deprecated call for now
		if($formFieldName) {} // ignore
		if($this->savePageField($page, $pageFieldName, $value, $entry) === false) return false;
		return true;
	}

	/**
	 * Returns true if given value should be saved, false if not (DEPRECATED)
	 * 
	 * @param Page $page
	 * @param string $name
	 * @param string $value
	 * @param array $entry The entire entry (in case you need anything from it)
	 * @return bool
	 * @deprecated Hook into allowSavePageField instead
	 *
	 */
	public function ___savePageField(Page $page, $name, $value, $entry) { 
		if($page && $name && $value && $entry) {} // ignore
		return true; 
	}
	
	/**
	 * Save an individual files field to a Page
	 *
	 * @param Page $page Page that has files field
	 * @param Field $field ProcessWire field instance representing files field
	 * @param array $filenames Names of files to add (full paths included)
	 * @param string $formFieldName Name of the form field being saved
	 * @return int Number of files added
	 * @since 0.4.7
	 *
	 */
	public function ___savePageFileField(Page $page, Field $field, array $filenames, $formFieldName) {
		return $this->pageSaver()->savePageFileField($page, $field, $filenames, $formFieldName);
	}
	
	/**
	 * Hook called right before a Page is about to be saved
	 * 
	 * The given $data array is for convenience, but cannot be modified. You can modify $page though. 
	 * 
	 * @param Page $page
	 * @param array $data Entry data (cannot be modified)
	 * 
	 */
	public function ___savePageReady(Page $page, array $data) { }

	/**
	 * Hook called right after a page is saved
	 * 
	 * @param Page $page Page that was saved
	 * @param array $data Entry data that was populated to page
	 * @param bool $isNew Was it a new page before it was saved?
	 * @param null|array $onlyFields Saved field names present in this array. If omitted, all mapped field names were saved. Names are form field names.
	 * @return array Return entry data (probably not useful)
	 * 
	 */
	public function ___savePageDone(Page $page, array $data, $isNew, $onlyFields) { 
		if($page && $data && $isNew && $onlyFields) {} // ignore
		$this->savePage = $page;
		return $data; 
	}

	
	/*****************************************************************************************************
	 * ACTION: EMAIL
	 *
	 */

	/**
	 * Email the form result to the administrator(s)
	 *
	 * @param InputfieldForm $form 
	 * @param array $data Entry data
	 * @param string $emailTo Alternate "to" email address, if something different than what’s defined in form settings. 
	 * @return bool|int False if there was a failed send, and true if successful or 0.5.3+ integer 1 if cancelled, 0 if no email to send to
	 *
	 */
	public function ___emailForm(InputfieldForm $form, array $data, $emailTo = '') {
		
		$fbForm = $this->getFbForm();
		$subject = $this->emailSubject;
		$fromEmail = $this->emailFrom2; // Email "from" defined with form as 1st choice
		$fromName = $this->emailFromName;
		
		if(!strlen($emailTo)) $emailTo = $this->emailTo;
		if(!strlen($emailTo)) return 0;		

		require_once(dirname(__FILE__) . '/FormBuilderEmail.php');
		
		if(empty($fromEmail) && $this->forms->fromEmail) {
			// try module-defined global default
			$fromEmail = $this->forms->fromEmail; 
		}
		
		if(empty($fromEmail)) {
			// fallback to reply-to if no other option
			$fromEmail = $this->emailFrom; 
		}
		
		// if subject not modified by hooks then allow placeholder variables
		$allowSubjectPlaceholders = $subject === $fbForm->emailSubject;
		
		if(empty($data['entryID'])) {
			$data['entryID'] = isset($data['id']) ? $data['id'] : $this->getEntryID();
		}
		
		if($fbForm->hasFlag(FormBuilderProcessor::formFlagEmailSubjectId)) {
			$entryID = (int) $data['entryID'];
			if(empty($entryID)) $entryID = time();
			$subject = trim($subject) . " (#$entryID)";
		}
	
		// create the email
		$email = new FormBuilderEmail($form);
		$email->to = $emailTo;
		$email->replyTo = $this->emailFrom;
		$email->from = $fromEmail;
		$email->subject = $subject;
		$email->setMailer($this->mailer); 
		$email->setRawFormData($data); 
		$email->setUseFileAttachments($fbForm->hasFlag(FormBuilderProcessor::formFlagEmailFiles));
		$email->setAllowSubjectPlaceholders($allowSubjectPlaceholders);
		if($fromName) $email->fromName = $fromName;

		$this->emailFormPopulateSkipFields($email, $form);
		if(!$this->emailFormReady($form, $email)) return 1;

		return $email->send('email-administrator');
	}
	
	/**
	 * Hook called before email is sent to administrator
	 * 
	 * Change return false to false to skip send (0.5.3+)
	 *
	 * @param InputfieldForm $form
	 * @param FormBuilderEmail $email
	 * @return bool
	 *
	 */
	protected function ___emailFormReady(InputfieldForm $form, FormBuilderEmail $email) { 
		if($form) {}
		$email->setTemplateVar('viewEntryUrl', 
			$this->wire()->config->urls('httpAdmin') . 'setup/form-builder/viewEntry/?id=' . $this->getEntryID()
		);
		return true;
	}

	/**
	 * Email the form result to the sending (auto-responder)
	 *
	 * @param InputfieldForm $form 
	 * @param array $data
	 * @return bool False on fail, true on success, and 0.5.3+ int 1 on cancel, 0 if no email to send to
	 *
	 */
	public function ___emailFormResponder(InputfieldForm $form, array $data) {

		$sanitizer = $this->wire()->sanitizer;
		
		if(!strlen($this->responderTo)) {
			$this->saveLog("Auto-responder enabled but no field defined to get email from");
			return 0;
		}
		
		require_once(dirname(__FILE__) . '/FormBuilderEmail.php');
		
		$fromEmail = $this->responderFrom; 
		if(empty($fromEmail)) $fromEmail = $this->forms->fromEmail;
		
		if(empty($data['entryID'])) {
			$data['entryID'] = isset($data['id']) ? $data['id'] : $this->getEntryID();
		}

		$email = new FormBuilderEmail($form);
		$email->setMailer($this->mailer);
		$email->from = $fromEmail;
		$email->subject = $this->responderSubject;
		$email->body = $this->responderBody;
		$email->setRawFormData($data);
	
		if($this->responderFromName) $email->fromName = $this->responderFromName;
		if($this->responderReplyTo) $email->replyTo = $this->responderReplyTo;

		$this->emailFormPopulateSkipFields($email, $form);
		if(!$this->emailFormResponderReady($form, $email)) {
			$this->saveLog("Auto-responder cancelled by emailFormResponderReady hook");
			return 1;
		}
		
		$numSent = 0;
		$numTotal = 0;
		
		foreach(explode(',', $this->responderTo) as $fieldName) {
			$field = $form->getChildByName(trim($fieldName));
			if(!$field) {
				$this->saveLog("Auto-responder error - cannot find email field: $fieldName"); 
				continue;
			}
			$emailValue = $field->attr('value');
			$responderTo = $sanitizer->email($emailValue);
			if(!strlen($responderTo)) {
				$this->saveLog("Auto-responder error - email empty or failed validation: $emailValue"); 
				continue;
			}
			$email->to = $responderTo;
			$email->setTemplateVar('emailField', $fieldName);
			if($email->send('email-autoresponder')) $numSent++;
			$numTotal++;
		}

		return $numSent == $numTotal;
	}

	/**
	 * Hook called before email is sent to autoresponder
	 *
	 * @param InputfieldForm $form
	 * @param FormBuilderEmail $email
	 * @return bool Set to false to cancel responder (0.5.3+)
	 *
	 */
	protected function ___emailFormResponderReady(InputfieldForm $form, FormBuilderEmail $email) { 
		if($form && $email) {} // ignore
		return true;
	}

	/**
	 * Hookable method for populating fields that should be skipped in an email
	 * 
	 * @param FormBuilderEmail $email
	 * @param InputfieldForm $form
	 * 
	 */
	protected function ___emailFormPopulateSkipFields(FormBuilderEmail $email, InputfieldForm $form) {
		if($form) {} // ignore
		$honeypots = $this->honeypot;
		if(!empty($honeypots)) {
			if(!is_array($honeypots)) $honeypots = array($honeypots); 
			foreach($honeypots as $honeypot) {
				$email->setSkipFieldName(trim($honeypot));
			}
		}
	}

	
	/*****************************************************************************************************
	 * ACTION: POST COPY TO 3RD PARTY URL
	 *
	 */

	/**
	 * Post a duplicate copy of the form to another URL
	 *
	 * @param array $data
	 * @return bool True on success, false on fail
	 * 
	 */
	protected function ___postAction2(array $data) {

		unset($data['id'], $data[$this->formName . '_submit']); 

		// remove fields
		foreach($this->action2_remove as $name) {
			unset($data[$name]); 
		}	
		// add fields
		foreach($this->action2_add as $name => $value) {
			$data[$name] = $value; 
		}
		// rename fields
		foreach($this->action2_rename as $name => $newName) {
			if(!array_key_exists($name, $data)) continue; 
			$value = $data[$name]; 
			unset($data[$name]); 
			$data[$newName] = $value; 
		}

		$url = $this->action2;
		$method = 'post';

		// allow for specifying the method as part of the URL
		// i.e. GET:http://www.domain.com/ (default is POST)
		if(preg_match('/^(GET|POST):(.+)$/i', $url, $matches)) {
			$url = $matches[2]; 
			$method = strtolower($matches[1]);
		}
		
		$headers = array(
			'referer' => $this->wire()->page->httpUrl(), 
			'User-Agent' => 'ProcessWire FormBuilder/5 (+https://processwire.com)'
		);

		// post the data
		$http = new WireHttp();
		$response = $this->postAction2Ready($http, $data, $method, $url, $headers);
		
		if($response === false) {
			$this->forms->formLog($this->form, "Failed HTTP $method to $url - " . $http->getError());
		}

		return $response !== false;
	}

	/**
	 * Called when postAction2 is ready to send
	 * 
	 * - You can hook BEFORE this to modify the arguments before they are used in the HTTP request.
	 * - You can hook AFTER this to analyze the returned $response.
	 * 
	 * @param WireHttp $http 
	 * @param array $data Array of data that is being sent
	 * @param string $method Send method of either "get" or "post"
	 * @param string $url URL that data is being sent to
	 * @param array $headers Additional HTTP headers to include in the request
	 * @return bool|string Returns response string on success or boolean false on fail
	 * 
	 */
	protected function ___postAction2Ready($http, array $data, $method, $url, array $headers) {
		
		foreach($headers as $name => $value) {
			$http->setHeader($name, $value);
		}
		
		if(strtolower($method) == 'get') {
			$response = $http->get($url, $data);
		} else {
			$response = $http->post($url, $data);
		}
		
		return $response;
	}

	
	/*****************************************************************************************************
	 * ACTION: GOOGLE SHEETS
	 *
	 */

	/**
	 * Save to Google Sheets
	 *
	 * @param InputfieldForm $form
	 * @param array $data
	 * @return bool
	 * 
	 */
	protected function ___saveGoogleSheets($form, array $data) {
		
		if(!strlen($this->googleSpreadsheetUrl)) return false;
		if(!count($this->googleSpreadsheetFields)) return false;
		if($form) {} // ignore
		
		$page = $this->wire()->page;
		$user = $this->wire()->user;

		/** @var GoogleClientAPI $google */
		$google = $this->wire()->modules->get('GoogleClientAPI');
		if(!$google) return false;
		
		$sheets = $google->sheets();
		$sheets->setSpreadsheetUrl($this->googleSpreadsheetUrl);
		
		$systemFields = array(
			'created' => date('Y-m-d H:i:s'),
			'entryID' => $this->getEntryID(), 
			'_savePage' => (isset($data['_savePage']) ? $data['_savePage'] : 0), 
			'_formName' => $this->formName,
			'_pageTitle' => $page->title,
			'_pageName' => $page->name,
			'_pageURL' => $page->httpUrl,
			'_pageID' => $page->id, 
			'_userID' => $user->id, 
			'_userName' => $user->name, 
			'_userIP' => $this->wire()->session->getIP(), 
		);
		
		$row = array();
		$blank = ' ';
		$result = false;
		
		foreach($this->googleSpreadsheetFields as $fieldName) {
			if(isset($data[$fieldName])) {
				$value = $data[$fieldName];
			} else if(isset($systemFields[$fieldName])) {
				$value = $systemFields[$fieldName];
			} else {
				$value = $blank;
			}
			$row[$fieldName] = $value; 
		}

		try {
			$row = $this->saveGoogleSheetsReady($sheets, $row, $data); 
			foreach($row as $key => $value) {
				if(is_array($value)) {
					$row[$key] = implode("\n", $value);
				} else if($value === null) {
					$row[$key] = '';
				}
			}
			if(!empty($row)) $result = $sheets->appendRow(array_values($row));
		} catch(\Exception $e) {
			$this->saveLog("Error saving entry for $this->formName to Google sheet: " . $e->getMessage()); 
			$result = false;
		}
		
		if(!$result) return $result;
		
		if($this->googleSheetsResponseField && !empty($data['entryID'])) {
			$this->fbForm->entries()->saveField(
				$data['entryID'], 
				$this->googleSheetsResponseField, 
				$result->getUpdates()->getUpdatedRange()
			); 
		}
		
		return $result;
	}

	/**
	 * Hook called before spreadsheet row saved, can optionally modify $event->return (spreadsheet row)
	 * 
	 * @param GoogleSheets $sheets
	 * @param array $row Spreadsheet row that will be saved
	 * @param array $data FormBuilder entry data
	 * @return array Returns row of data that will be saved to Google sheets or empty if it should not be saved
	 * 
	 */
	protected function ___saveGoogleSheetsReady($sheets, array $row, array $data) {
		if($sheets && $data) {} // ignore
		return $row;
	}

	
	/*****************************************************************************************************
	 * ERRORS, WARNINGS, SUCCESSES
	 *
	 */
	
	/**
	 * Return an array of errors that occurred (strings)
	 *
	 * @param bool $all When true, all errors are included. When false, field-specific errors (displayed inline) are excluded. (default=true)
	 * @param bool $clear Clear out all errors? (default=false) added 0.4.0
	 * @return array Will be blank if no errors.
	 *
	 */
	public function getErrors($all = true, $clear = false) {
		if($all) {
			$errors = $this->form->getErrors($clear);
		} else {
			$errors = array();
		}
		// prepend any self generated errors
		foreach($this->errors as $error) {
			array_unshift($errors, $error);
		}

		if($clear) $this->errors = array();

		return $errors;
	}

	/**
	 * Are there any errors present on form or in this processor?
	 *
	 * @return bool
	 * @since 0.4.5
	 *
	 */
	public function hasErrors() {
		if(count($this->errors)) return true;
		if($this->form && count($this->form->getErrors())) return true;
		return false;
	}

	/**
	 * Add an error message that is shown to the user
	 *
	 * @param string $text
	 * @param bool $prepend
	 * @since 0.3.10
	 *
	 */
	public function addError($text, $prepend = false) {
		if(in_array($text, $this->errors, true)) return;
		if($prepend) {
			array_unshift($this->errors, $text);
		} else {
			$this->errors[] = $text;
		}
	}

	/**
	 * Clear all errors and warnings
	 *
	 * @param bool $all
	 * @since 0.4.5
	 *
	 */
	public function clearErrors($all = true) {
		if($all) $this->form->getErrors(true);
		$this->errors = array();
		$this->warnings = array();
	}

	/**
	 * Return an array of warnings that occurred (strings)
	 *
	 * @param bool $clear Clear out all warnings? (default=false)
	 * @return array Will be blank if no warnings.
	 * @since 0.4.3
	 *
	 */
	public function getWarnings($clear = false) {
		$warnings = $this->warnings;
		if($clear) $this->warnings = array();
		return $warnings;
	}

	/**
	 * Add a warning that can be shown to user but does not prevent form submission
	 *
	 * @param string $text
	 * @param bool $prepend
	 * @since 0.4.3
	 *
	 */
	public function addWarning($text, $prepend = false) {
		if(in_array($text, $this->warnings)) return;
		if($prepend) {
			array_unshift($this->warnings, $text);
		} else {
			$this->warnings[] = $text;
		}
	}

	/**
	 * Add an error message that is ony shown if user has form-builder permission
	 *
	 * @param string $text
	 *
	 */
	public function adminError($text) {
		$user = $this->wire()->user;
		$this->adminErrors[] = $text;
		if(!$user->isLoggedin() || !$user->hasPermission('form-builder')) return;
		$this->errors[] = $text . ' ' . $this->_('(This message appears to admin users only)');
	}

	/**
	 * Add an initialization error, must be called during initialization to be shown
	 *
	 * @param string $text
	 * @since 0.4.5
	 *
	 */
	public function initError($text) {
		$this->initErrors[] = $text;
	}

	
	/**
	 * Called upon successful form submission
	 *
	 * Intended for hooks to listen to. 
	 *
	 * @param InputfieldForm $form
	 *
	 */
	protected function ___formSubmitSuccess(InputfieldForm $form) {
		if($form) {} // ignore
		$this->submitted = true; 
	}

	/**
	 * Called upon a form submission error, for hooks to listen to.
	 *
	 * @param InputfieldForm $form
	 * @param array $errors Array of errors that occurred (strings)
	 *
	 */
	protected function ___formSubmitError(InputfieldForm $form, array $errors) {
		if($form && $errors) {} // ignore
		$this->submitted = false;
	}

	/**
	 * Called when an invalid form submission is received that prevented it from being processed
	 * 
	 * - Invalid submit key received, like from previously cached form or CSRF.
	 * - User receives error message that says "Invalid form submission". 
	 * - Note that the formSubmitError() method is not called since form did not reach processing state.
	 * - Form has not been processed and will not be. Submitted input can only be retrieved
	 *   from POST vars directly at this point. 
	 * 
	 * @param InputfieldForm $form The form that was not processed. 
	 * @param array $errors Note these errors are for admin eyes only, it has details about what was missing from submit key.
	 * @since 0.4.5
	 * 
	 */
	protected function ___formSubmitInvalid(InputfieldForm $form, array $errors) {
		if($form) {}
		$this->saveLog("Invalid form submit - " . implode(', ', $errors)); 
	}

	/**
	 * Render the given success message for output
	 *
	 * @param string $message
	 * @return string
	 *
	 */
	protected function ___renderSuccess($message) {
	
		$message = trim($message);
		$successUrl = '';
		$fallback = $this->_('Thank you, form has been submitted.'); // fallback success message is rules produce empty success message
		$out = $fallback;

		if(ctype_digit("$message")) {
			$page = $this->pages->get((int) $message);
			if($page->id && $page->viewable()) $successUrl = $page->url;
			
		} else if(stripos($message, 'http:' . '//') === 0 || stripos($message, 'https://') === 0) {
			$successUrl = $this->wire()->sanitizer->url($message);

		} else if(strpos($message, 'markdown:') === 0 || strpos($message, 'html:') === 0 || strpos($message, 'text:') === 0) {
			$out = $this->renderSuccessMessage($message);

		} else {
			
			// With the regex below, we are sifting through the success message to determine if it is just text, a URL or a URL:field
			// Variable Positions: 1 ........... 2 . 3 ................. 4 .....
			if(!preg_match('{^(/[-_a-z0-9/]+|\d+)(:?)((?:[_a-zA-Z0-9]+)?)(\?.*)?$}', $message, $matches)) {
				// if not a path then populate a simple text success message
				$out = $this->renderSuccessMessage($message);
				
			} else if(strlen($matches[2]) && strlen($matches[3])) {
				// we have matched a $message is in the format: /path/to/page/ or /path/to/page/:field or 123:field
				// pull the field from /path/to/page
				$page = $this->pages->get($matches[1]); 
				$value = '';
				if($page->viewable(false)) {
					$field = $matches[3];
					$value = $page->get($field);
				}
				if(empty($value)) $value = $fallback;
				$out = "<div class='InputfieldMarkup'><div class='InputfieldContent'>$value</div></div>";
				
			} else {
				// just a redirect URL
				$successUrl = $matches[1]; 
				// page path
				if(strpos($successUrl, '?') === false) {
					// attempt to tie the path to page, in case site is running from subdir, path can start non-subdir
					$page = $this->pages->get($successUrl); 
					if($page->id && $page->viewable()) $successUrl = $page->url; 
				}
				if(isset($matches[4])) $successUrl .= $matches[4]; // opitonal query string
			}
		}

		if($successUrl) {
			// JS redirect required since we will be redirecting the parent window
			$out = $this->renderSuccessRedirect($successUrl);
			
		} else if(count($this->warnings)) {
			$out = $this->renderErrors($this->warnings) . $out;
		}

		return $out;
	}

	/**
	 * Render a success message
	 * 
	 * @param string $message Message to render
	 * @param string $markupTemplate Optional markup template containing {out} placeholder where message is inserted
	 * @return string
	 * 
	 */
	protected function ___renderSuccessMessage($message, $markupTemplate = '') {
		if(empty($markupTemplate)) {
			$markup = InputfieldWrapper::getMarkup();
			$markupTemplate = isset($markup['success']) ? $markup['success'] : '';
			if(empty($markupTemplate)) $markupTemplate = "<div>{out}</div>";
		}
		
		if(strpos($message, 'markdown:') === 0) {
			$format = 'markdown';
		} else if(strpos($message, 'html:') === 0) {
			$format = 'html';
		} else if(strpos($message, 'text:') === 0) {
			$format = 'text';
		} else {
			$format = '';
		}
		
		$isOriginal = $message == $this->successMessage;
	
		if($format) {
			list(,$message) = explode("$format:", $message);
		}
		
		if($isOriginal && $format === 'html') {
			// leave as-is
			$out = str_replace('{out}', $message, $markupTemplate); 
		} else if($isOriginal && $format === 'markdown') {
			$message = $this->wire()->sanitizer->entitiesMarkdown($message, true);
			$out = str_replace('{out}', $message, $markupTemplate); 
		} else {
			$message = htmlentities($message, ENT_QUOTES, "UTF-8");
			$out = nl2br(str_replace('{out}', $message, $markupTemplate)); 
		}
		
		return $out;
	}

	/**
	 * Render a success redirect
	 * 
	 * @param string $url URL to redirect to
	 * @return string By default returns a JS script tag to perform redirect
	 * 
	 */
	protected function ___renderSuccessRedirect($url) {
		return
			"<script type='text/javascript'>window.top.location.href='$url';</script>" .
			"<noscript><a href='$url'>$url</a></noscript>";
	}

	/**
	 * Render the given error messages for output
	 *
	 * @param array|bool $errors Errors to render (default=auto detect), or specify true for $clear argument
	 * @param bool $clear Clear internal errors after rendering? (default=false) Added 0.4.5
	 * @return string
	 * @since 0.4.0 went from protected to public
	 *
	 */
	public function ___renderErrors($errors = array(), $clear = false) {

		$markup = InputfieldWrapper::getMarkup();
		$clear = $errors === true || $clear === true;
		$out = '';
		
		if($errors === true) $errors = array();
		
		// prepend our standard error message to the top
		if(empty($errors)) {
			$errors = $this->getErrors();
			if(count($errors)) array_unshift($errors, $this->errorMessage);
		}
		
		$errors = $this->renderErrorsReady($errors); 
	
		foreach($errors as $error) {
			$errorTemplate = isset($markup['error']) ? $markup['error'] : '';
			$out .= $this->renderError($error, $errorTemplate);
		}
		
		if(strlen($out)) {
			$out = "<div class='FormBuilderErrors'>$out</div>";
		}
		
		if($clear) $this->clearErrors();

		return $out; 
	}

	/**
	 * Called when errors about to be rendered
	 * 
	 * Hooks can optionally modify the $errors by modifying the $event->return value
	 * 
	 * @param array $errors
	 * @return array
	 * 
	 */
	protected function ___renderErrorsReady(array $errors) {
		return $errors;
	}

	/**
	 * Render an error message into markup
	 * 
	 * @param string $error Error message to render
	 * @param string $errorTemplate Markup template for error, has {out} where error message is inserted. Omit to use module setting.
	 * @return string
	 * 
	 */
	protected function ___renderError($error, $errorTemplate = '') {
		if(empty($errorTemplate)) {
			$markup = InputfieldWrapper::getMarkup();
			$errorTemplate = isset($markup['error']) ? $markup['error'] : '';
			if(empty($errorTemplate)) $errorTemplate = "<div>{out}</div>";
		}
		$error = htmlentities($error, ENT_QUOTES, "UTF-8");
		$out = str_replace('{out}', $error, $errorTemplate);
		return $out;
	}

	/**
	 * Render a markup landmark that indicates form was submitted (whether successfully or with errors)
	 * 
	 * @return string
	 * 
	 */
	public function ___renderSubmittedLandmark() {
		// note: JS looks for #FormBuilderSubmitted for in-page scrolling
		return "<div id='FormBuilderSubmitted' data-name='$this->formName'></div>\n";
	}

	
	/*****************************************************************************************************
	 * GENERAL
	 *
	 */

	/**
	 * Get an array of all values from this form
	 *
	 * Should be called only after successful form submission, see isSubmitted() method
	 *
	 * @return array Values indexed by inputfield 'name' attribute
	 *
	 */
	public function getValues() {

		$values = array();
		$skipTypes = array(
			'InputfieldMarkup',
			'InputfieldWrapper',
			'InputfieldSubmit',
		);

		$inputfields = $this->form->getAll();

		foreach($inputfields as $f) {
			$skip = false;
			foreach($skipTypes as $type) {
				if(wireInstanceOf($f, $type)) $skip = true;
			}
			if($skip) continue; 
			$name = $f->attr('name'); 
			$value = $f->attr('value'); 
			$values[$name] = $value; 
		}

		return $values; 
	}

	/**
	 * Was the form successfully submitted? (for public API)
	 *
	 * @return bool
	 *
	 */
	public function isSubmitted() {
		return $this->submitted; 
	}

	/**
	 * Get current FormBuilderForm object
	 * 
	 * @return FormBuilderForm
	 * @since 0.4.0
	 * 
	 */
	public function getFbForm() {
		return $this->fbForm;
	}

	/**
	 * Get the constructed form 
	 *
	 * @return InputfieldForm
	 *
	 */
	public function getInputfieldsForm() {
		return $this->form; 
	}

	/**
	 * Get the constructed form (alias of getInputfieldsForm)
	 * 
	 * @return InputfieldForm
	 * @since 0.5.4
	 * 
	 */
	public function getInputfieldForm() {
		return $this->form;
	}

	/**
	 * Set the InputfieldForm
	 * 
	 * @param InputfieldForm $form
	 * 
	 */
	public function setInputfieldsForm(InputfieldForm $form) {
		$this->form = $form;
	}

	/**
	 * Get the form submit type
	 * 
	 * Returns one of the following:
	 *  - `true` (bool): Final form submission (FormBuilderMaker::submitTypeFinal)
	 *  - `false` (bool): Form was not submitted (FormBuilderMaker::submitTypeNone)
	 *  - `0` (int): Invalid form submission (FormBuilderMaker::submitTypeInvalid)
	 *  - `1` (int): The "next" button was clicked in a paginated form (FormBuilderMaker::submitTypeNext)
	 *  - `-1` (int): The "previous" button was clicked in a paginated form (FormBuilderMaker::submitTypePrev)
	 *  - `2` (int): Another pagination was jumped to (FormBuilderMaker::submitTypeJump)
	 *  - `4` (int): Error occurred and we should retry (FormBuilderMaker::submitTypeRetry)
	 *  - `5` (int): Duplicate (FormBuilderMaker::submitTypeDuplicate)
	 * 
	 * @return bool|int|null
	 * @since 0.4.0
	 * 
	 */
	public function getSubmitType() {
		if($this->submitType !== null) return $this->submitType;
		return $this->maker->getSubmitType();
	}

	/**
	 * Set the form submit type
	 * 
	 * Specify one of the following:
	 *  - `true` (bool): Final form submission (FormBuilderMaker::submitTypeFinal)
	 *  - `false` (bool): Form was not submitted (FormBuilderMaker::submitTypeNone)
	 *  - `0` (int): Invalid form submission (FormBuilderMaker::submitTypeInvalid)
	 *  - `1` (int): The "next" button was clicked in a paginated form (FormBuilderMaker::submitTypeNext)
	 *  - `-1` (int): The "previous" button was clicked in a paginated form (FormBuilderMaker::submitTypePrev)
	 *  - `2` (int): Another pagination was jumped to (FormBuilderMaker::submitTypeJump)
	 *  - `4` (int): Error occurred and we should retry (FormBuilderMaker::submitTypeRetry)
	 *  - `5` (int): Duplicate (FormBuilderMaker::submitTypeDuplicate)
	 * 
	 * @param bool|int $submitType
	 * @since 0.4.0
	 * 
	 */
	public function setSubmitType($submitType) {
		$this->submitType = $submitType; // overrides the one detected by maker
	}

	/**
	 * Get the array upon which this form is based (same as what was passed to constructor)
	 *
	 * @return array
	 * @deprecated
	 *
	 */
	public function getFormArray() {
		return $this->fbForm->getArray();
	}
	
	/**
	 * Get the URL for the given form
	 *
	 * @param array $options
	 * @return string
	 * @since 0.4.0
	 * @todo include #fragment in form URL
	 *
	 */
	public function getFormUrl(array $options = array()) {
		
		$defaults = array(
			'pageNum' => 0,
		);

		$options = array_merge($defaults, $options);
		$inputUrl = $this->wire()->input->url;

		if(substr($inputUrl, -1) !== '/') {
			$url = $inputUrl;
		} else {
			$url = './';
		}

		if($this->maker->hasPagination()) {
			$url = $this->partialEntries->updateFormUrl($url, $options);
		}
		
		// if($this->form) $url .= '#FormBuilder_' . $this->formName;

		return $url;
	}

	/**
	 * Save a log entry to the form-builder log
	 * 
	 * @param string $message
	 * 
	 */
	public function saveLog($message) {
		$this->forms->formLog($this->fbForm ? $this->fbForm : $this->form, $message);
	}

	/**
	 * Set a session value unique for this form
	 * 
	 * @param string $key
	 * @param mixed $value
	 * 
	 */
	public function sessionSet($key, $value) {
		$this->wire()->session->setFor("FormBuilder_$this->formName", $key, $value);
	}

	/**
	 * Get a session value unique for this form
	 * 
	 * @param string $key
	 * @param array|int|string|null $default
	 * @return mixed|null
	 * 
	 */
	public function sessionGet($key, $default = null) {
		$value = $this->wire()->session->getFor("FormBuilder_$this->formName", $key);
		if($value === null && $default !== null) $value = $default;
		return $value;
	}

	/**
	 * Remove a session value unique for this form
	 * 
	 * @param string $key
	 * 
	 */
	public function sessionRemove($key) {
		$this->wire()->session->removeFor("FormBuilder_$this->formName", $key);
	}

	/**
	 * Add markup to append to the form
	 * 
	 * @param string $markup
	 * 
	 */
	public function appendMarkup($markup) {
		$this->appendMarkup .= $markup;
	}

	/**
	 * Returns name of triggered spam filter when entry detected as spam
	 * 
	 * @return bool|string
	 * 
	 */
	public function isSpam() {
		return $this->isSpam;
	}

	/**
	 * Are we currently in the FormBuilder admin?
	 * 
	 * @return bool
	 * 
	 */
	public function isAdmin() {
		return $this->wire()->process == 'ProcessFormBuilder'; 
	}

	/**
	 * Run a method on all FormBuilderProcessorAction (pluginAction) modules
	 * 
	 * @param string $methodName
	 * @param array|null $data Optional associative array of data to send to method
	 * @return array Array of plugin return values (if any) indexed by plugin name
	 * 
	 */
	public function executePluginActions($methodName, $data = null) {
		$returnValues = array();
		foreach($this->pluginActions as $pluginName => $pluginModule) {
			/** @var FormBuilderProcessorAction $pluginModule */
			if(!is_object($pluginModule)) continue;
			if(!$pluginModule->isEnabled()) continue;
			if($this->entry) $pluginModule->entry($this->entry);
			if(is_array($data)) {
				$returnValues[$pluginName] = $pluginModule->$methodName($data);
			} else {
				$returnValues[$pluginName] = $pluginModule->$methodName();
			}
		}
		return $returnValues;
	}

	/**
	 * Get array of labels for given saveFlags
	 * 
	 * @param int|null $saveFlags
	 * @param bool $getString
	 * @return array
	 * 
	 */
	static public function saveFlagsLabels($saveFlags = null, $getString = false) {
		$flags = array(
			self::saveFlagDB => __('Save entries in database'),
			self::saveFlagEmail => __('Send emails to admin'),
			self::saveFlagAction2 => __('Send copy to URL'),
			self::saveFlagPage => __('Send to pages'),
			self::saveFlagExternal => __('Send to external URL only'),
			self::saveFlagFilterSpam => __('Filter for spam'),
			self::saveFlagResponder => __('Send auto-responder emails'),
		);
		if($saveFlags === null) {
			$labels = $flags;
		} else {
			$labels = array();
			foreach($flags as $flag => $label) {
				if($saveFlags & $flag) $labels[$flag] = $label;
			}
		}
		if($getString) $labels = implode(', ', $labels);
		return $labels;	
	}

	
	/*****************************************************************************************************
	 * SUPPORTING INSTANCES
	 *
	 */

	/**
	 * @return FormBuilderMaker
	 *
	 */
	public function maker() {
		return $this->maker;
	}

	/**
	 * @return FormBuilderProcessorSpam
	 * 
	 */
	public function spammer() {
		if($this->spammer) return $this->spammer;
		require_once(__DIR__ . '/FormBuilderProcessorSpam.php');
		$this->spammer = new FormBuilderProcessorSpam($this);
		return $this->spammer;
	}

	/**
	 * @return FormBuilderProcessorSavePage
	 * 
	 */
	public function pageSaver() {
		if($this->pageSaver) return $this->pageSaver;
		require_once(__DIR__ . '/FormBuilderProcessorSavePage.php');
		$this->pageSaver = new FormBuilderProcessorSavePage($this);
		return $this->pageSaver;
	}
}