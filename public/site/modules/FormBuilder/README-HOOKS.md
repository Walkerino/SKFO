# FormBuilder Hooks

This document contains a reference of all hooks in FormBuilderProcessor, which is the 
most useful type to hook in FormBuilder. First we start out with examples of how to 
create hooks to FormBuilder, then we follow with a reference list of all available 
hooks. Hooks should be placed in a /site/ready.php file. 

-----------------------------------------------------------------------------------

## HOW-TO AND EXAMPLES

Here is how to hook a FormBuilder method: 

~~~~~
$forms->addHook('FormBuilderProcessor::methodName', function(HookEvent $e) {
  // hook code
}); 
~~~~~

For example, hooking before the renderReady method would enable you to perform some 
action or modification right before the form is rendered: 

~~~~~
$forms->addHookBefore('FormBuilderProcessor::renderReady', function($event) {
  $processor = $event->object; // FormBuilderProcessor instance
  $form = $event->arguments(0); // retrieve any argument in order (zero based)...
  $form = $event->arguments('form'); // ...OR retrieve any argument by name
  if($processor->formName == 'foo') {
    // do something for form named "foo"
  } else if($processor->formName == 'bar-baz') {
    // do something else for form named "bar-baz"
  }
}); 
~~~~~

As an example, you might use a renderReady method to pre-populate values for a form. 
In this case, if a user is logged in, we'll pre-populate their email address in the
appropriate field named "email": 

~~~~~
$forms->addHookBefore('FormBuilderProcessor::renderReady', function($event) {
  $form = $event->arguments(0);
  $user = $event->wire('user');
  if(!$user->isLoggedIn()) return; // if not logged in, exit now
  // see if there a field in the form named 'email'
  $inputfield = $form->getChildByName('email');
  // if there is an email field and it's empty, populate user's email 
  if($inputfield && $inputfield->isEmpty()) {
    $inputfield->attr('value', $user->email);     
  }
}); 
~~~~~~

Hooking after a method lets you modify its return value:

~~~~~
$forms->addHookAfter('FormBuilderProcessor::render', function($e) {
  $str = $e->return; 
  $str .= "<p>Hello World</p>";
  $e->return = $str; 
}); 
~~~~~

Hooking before a method lets you modify its arguments before the method you are 
hooking gets called:

~~~~~
$forms->addHookBefore('FormBuilderProcessor::saveEntry', function($e) {
  $data = $e->arguments(0); 
  // populate field named "foo" with value "bar", before it gets saved as an entry
  $data['foo'] = 'bar'; 
  // populate the argument back so it reflects the new value
  $e->arguments(0, $data); 
}); 
~~~~~

Another example, adding a file attachment to an auto-responder email. 
In this case, a user subscribes to a list and receives a bonus article PDF file in 
their confirmation email:

~~~~~
$forms->addHook('FormBuilderProcessor::emailFormReady', function($e) {
  $form = $e->arguments(0); // or arguments('form')
  $mailer = $e->arguments(1); // or arguments('email')
  if($form->name == 'subscribe') {
    $mailer->addFileAttachment('/path/to/bonus-article.pdf'); 
  }
}); 
~~~~~

For this last example, lets refuse subscriptions from @hotmail.com since they've all 
been spam accounts lately: 

~~~~~
$forms->addHook('FormBuilderProcessor::processInputDone', function($e) {
  $form = $e->arguments(0);
  if($form->name != 'subscribe') return; // make it apply only to "subscribe" form
  $field = $form->getChildByName('email');
  $email = $field->attr('value');
  if(stripos($email, '@hotmail.com')) {
    $field->error('Please use a non-hotmail email address'); 
  } 
}); 
~~~~~

-----------------------------------------------------------------------------------

## HOOKS REFERENCE

These hooks are in the format: 

- `returnType methodName($arg0, $arg1, $arg2, etc.)`
  Description of hook.
  
All of these hooks are in FormBuilderProcessor.

- `InputfieldForm populate(array $data, $entryID)` 
  Populate `$this->form` with the `[ name => value ]` data from the given associative array.

- `string render($id = 0)`
  Render the form output, or follow-up success message. If $id is populated, it is 
  the id of existing form entry.

- `string renderReady(InputfieldForm $form, $formFile = '', array $vars = array())`
  Called when ready to render, and returns rendered output. Note the $formFile and 
  $vars arguments are only populated in embed method D. 

- `bool processInput($id = 0)`
  Process input for submitted form. If $id is populated, it is the id of existing 
  form entry. 

- `void processInputReady(InputfieldForm $form)`
  Called right before $form->processInput() is called.

- `void processInputDone(InputfieldForm $form)`
  Called after $form->processInput() and spam filtering is completed. 

- `int|bool saveForm(InputfieldForm $form, $id = 0)`
  Save the form to the database entry, page, or email(s) per form action settings. 
  If $id is populated, it is the id of an existing entry being saved.

- `int saveEntry(array $data)`
  Save a form entry where $data is the given entry. Existing entry should have 
  populated id property. Returns id of saved entry.

- `Page|null savePage(array $data, int $status = null, array $onlyFields = null)`
  Save given entry $data to a Page. See method comments for additional details.

- `bool savePageCheckName(Page $page)`
  Hook called before $page->save() to validate that page name is allowed. Returns 
  false if save should be aborted. 

- `bool allowSavePageField(Page $page, $pageFieldName, $formFieldName, $value, array $entry)`
  Allow the given field info to be saved in Page?

- `void savePageReady(Page $page, array $data)`
  Hook called right before Page is about to be saved.

- `array savePageDone(Page $page, array $data, $isNew, array $onlyFields = null)`
  Hook called after a page has been saved. Returns the entry $data that was saved. 

- `bool emailForm(InputfieldForm $form, array $data)`
  Called to email the form result to the administrator(s). Returns true on success, 
  false on fail.

- `void emailFormReady(InputfieldForm $form, FormBuilderEmail $email)`
  Called when $email object is ready, but message not yet sent. 

- `bool emailFormResponder(InputfieldForm $form, array $data)`
  Called to send auto-responder email. Returns true on success, false on fail.

- `void emailFormResponderReady(InputfieldForm $form, FormBuilderEmail $email)`
  Called when $email object ready, but message not yet sent. You might hook this 
  to add a file attachment, for example.

- `void emailFormPopulateSkipFields(FormBuilderEmail $email, InputfieldForm $form)` 
  Called for all emails to specify field names to skip sending in email, 
  i.e. $email->setSkipFieldName($name);

- `bool postAction2(array $data)`
  Called to send $data to external 3rd party URL specified in action2_* form settings. 

- `bool|string postAction2Ready($http, array $data, $method, $url, array $headers)`
  Called when ready to send to external URL. Returns response string or boolean 
  false on fail.
  
- `bool saveGoogleSheets(InputfieldForm $form, array $data)`
   Called to send data into a Google Sheets spreadsheet. 

- `array saveGoogleSheetsReady(GoogleSheets $sheets, array $row, array $data)`
   Called when ready to send data into a Google Sheets spreadsheet. Returns the array
   that will be populated into a spreadsheet row. 

- `formSubmitSuccess(InputfieldForm $form)`
  Called when form has been successfully submitted and saved. 

- `formSubmitError(InputfieldForm $form, array $errors)`
  Called when there were errors that prevented successful submission of form. 

- `string renderSuccess($message)`
  Called to render the given success message or process success action string (which 
  might also instruct it to do a redirect). 

- `string renderSuccessMessage($message, $markupTemplate = '')` 
  Render succcess message string only (called by renderSuccess). 

- `string renderSuccessRedirect($url)`
  Render or execute a redirect to given $url (called by renderSuccess). 

- `string renderErrors()`
  Render error messages. 

- `array renderErrorsReady(array $errors)`
  Called when errors ready to render, hooks can optionally modify $event->return 
  array of errors.

- `string renderError($error, $errorTemplate = '')`
  Render single error message into markup.

- `string wrapOutput($out)`
  Wraps all FormBuilder output in a FormBuilder-specific <div>. 
 
-----------------------------------------------------------------------------------
  
## RECIPES (in progress)

These are more examples taken from real use-cases in the support forum. 

### Conditional auto-responder

In this example, we have configured the auto-responder feature on the form’s actions
tab, but rather than sending an auto-responder to everyone, we want to send it to
just users that have clicked a “Yes” option on a field named “send_me_an_email”, 
which is a radios field having options “Yes” and “No”. In this hook, we check if
the user as NOT clicked the “Yes” option, then we prevent the method we’ve hooked
from being called by using $event->replace = true:

~~~~~ 
$forms->addHookBefore('FormBuilderProcessor::emailFormResponder', function($event) {
  $form = $event->arguments(0);
  $field = $form->getChildByName('send_me_an_email');
  if(!$field) return;
  if($field->attr('value') != 'Yes') {
    // tell PW not to call the method we hooked
    // which will prevent the auto-responder from sending
    $event->replace = true; 
  }
}); 
~~~~~

### Ignore required fields when clicking “Previous” button on a paginated form

Paginated forms process and validate each pagination, ensuring the data meets
all requirements before proceeding with the next required pagination. This might
not be what you want when clicking the "Back" button to visit the previous pagination.
The hook below prevents it from enforcing required fields when the user is clicking
to a previous pagination. 

~~~~~
$wire->addHook('FormBuilderProcessor::processInputReady', function($event) {
  $form = $event->arguments(0);
  $submitType = $event->arguments(1);
  if($submitType === FormBuilderMaker::submitTypePrev) {
    foreach($form->getAll() as $f) {
      if($f->required) $f->required = false;
    } 
  }
});
~~~~~

### Google Sheets: Convert values before storage in spreadsheet

When sending entries to Google Sheets, some types of values won't be recognized
by Google Sheets and may need conversion before they are sent to the spreadsheet.

A. Demonstrates converting a multi-value field (whether Checkboxes, SelectMultiple,
   AsmSelect, etc.) named 'favorite_colors' to a comma-separated string (CSV) for 
   storage in one column.

B. Demonstrates converting a single selection Page field (represented by an ID 
   integer) named 'country' to a Page title for storage in one column. 
   
C. Demonstrates converting a multi-value selection Page field (represented by an 
   array of page IDs) named 'categories' to newline-separated string of Page titles.   

~~~~~
$wire->addHookAfter('FormBuilderProcessor::saveGoogleSheetsReady', function($event) {
  $row = $event->return; // spreadsheet row (array) that will be saved
  $entry = $event->arguments(2); // entry (array) that row is based upon
  foreach($row as $name => $value) {
    if($name === 'favorite_colors' && is_array($value)) {
      // A. convert check box array values to CSV string
      $value = implode(', ', $value); 
    } else if($name === 'country' && ctype_digit("$value")) {
      // B. convert page ID to page title
      $value = $value ? $event->wire()->pages->get((int) $value)->title : ''; 
    } else if($name === 'categories' && is_array($value)) {
      // C. convert array of page IDs to newline-separated page titles
      $value = count($value) ? $event-wire()->pages->getByID($value) : null;
      $value = $value ? $value->implode("\n", "title") : ''; 
    }
    $row[$name] = $value; 
  }
  $event->return = $row;
}); 
~~~~~
 
### Making the next/prev buttons in multi-page form display pagination labels instead 
 
~~~~~ 
$wire->addHookBefore('FormBuilderProcessor::renderReady', function($event) {
  $form = $event->arguments(0);
  $processor = $event->object;

  $nextButton = $form->getChildByName("{$form->name}_submit_next");
  $prevButton = $form->getChildByName("{$form->name}_submit_prev");

  if(!$nextButton && !$prevButton) return;

  $pageNum = $processor->maker->getPageNumToRender();
  $pageBreaks = [];
  $n = 1;
  
  foreach($processor->getFbForm()->getChildrenFlat() as $f) {
    if($f->type != 'FormBuilderPageBreak') continue;
    $pageBreaks[++$n] = $f;
  }

  if($nextButton && isset($pageBreaks[$pageNum+1])) {
    $nextButton->val($pageBreaks[$pageNum+1]->label);
  }

  if($prevButton && isset($pageBreaks[$pageNum-1])) {
    $prevButton->val($pageBreaks[$pageNum-1]->label);
  }
}); 
~~~~~

-----------------------------------------------------------------------------------

Copyright 2023 by Ryan Cramer Design, LLC