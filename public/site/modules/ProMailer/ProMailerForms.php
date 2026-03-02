<?php namespace ProcessWire;

/**
 * ProcessWire ProMailer Forms
 *
 * Copyright 2023 by Ryan Cramer
 * This is a commercial module, do not distribute.
 *
 * HOOKABLE PUBLIC API METHODS
 * ===========================
 * @method string subscribe(array $options = array()) Render, process and email-confirm a subscription.
 * @method string unsubscribe(array $options = array()) Render or process an un-subscribe form at URL clicked from an email. 
 * @method bool sendConfirmEmail(ProMailerSubscriber $subscriber, $options = array()) Send a confirmation email to a subscriber. 
 * @method array|bool|null checkConfirmEmail() Check if current request is for a subscribe confirmation and confirm email when applicable.
 * @method string getUnsubscribeUrl(ProMailerSubscriber $subscriber, $queryStringOnly = false) Return URL to the unsubscribe form for given subscriber.
 * @method string getSubscribeUrl($listId = 0) Return URL to subscribe to given list. 
 * @method Field|null getUnsubscribeField(ProMailerList $list, $allowCreate = true, $allowAddTemplates = true) Get the Field that has a non-zero value if user is unsubscribed from a pages list (only).
 * @method Page|NullPage getSubscribePage($allowCreate = true) Get the ProMailer page that handles subscribe and unsubscribe.
 * @method string wrapOutput($out, $markup = '') Wrap ProMailer output
 * 
 * HOOKABLE INTERNAL API METHODS
 * =============================
 * @method array processSubscribe($listId, array $options) Process subscribe form, called automatically by renderSubscribeForm().
 * @method string renderConfirmEmail($type) Fallback method to render confirm email if content not provided to confirm().
 * 
 * @property-read Page $subscribePage
 * @property-read string $subscribeUrl
 * @property array $subscribeOptions
 * @property array $unsubscribeOptions
 * 
 *
 */
class ProMailerForms extends ProMailerTypeManager {
	
	protected $subscribeOptions = array();
	protected $unsubscribeOptions = array();

	public function __construct(ProMailer $promailer) {
		parent::__construct($promailer);
		
		$this->subscribeOptions = array(
			// subscribe form
			'listId' => 0,
			'useCSRF' => true,
			'useHoneypot' => true,
			'honeypotFieldName' => 'subscribe_whywhere',
			'emailFieldName' => 'subscribe_email',
			'emailFieldLabel' => 'Email address to subscribe',
			'emailFieldPlaceholder' => 'email@domain.com',
			'submitFieldName' => 'subscribe_submit',
			'submitFieldLabel' => 'Subscribe',
			
			// success messages
			'successConfirmed' => 'Thank you, your subscription has been confirmed',
			'successEmailSent' => 'We have sent you a confirmation email, please click the link in the email to complete your subscription.',
			
			// error messages
			'errorListUnknown' => 'Unknown mailing list',
			'errorListClosed' => 'List is closed',
			'errorCSRF' => 'Invalid request, please try again (CSRF)',
			'errorRequired' => 'Missing one or more required fields',
			'errorDuplicate' => 'You are already subscribed to this list',
			'errorEmail' => 'Email address did not validate',
			'errorSend' => 'Error sending confirmation email',
			'errorAdd' => 'Error adding you to this mailing list',
			'errorHoneypot' => 'Thank you for your subscription, welcome to the list.', // pretend success
			
			// form related markup
			'errorMarkup' => "<p class='error'>{message}</p>",
			'successMarkup' => "<p class='success'>{message}</p>",
			'wrapMarkup' => "<div id='promailer'>{out}</div>", 
			'formMarkup' =>
				"<form action='{url}#promailer-form' method='post'>".
					"<label for='{email_name}'>{email_label}</label>".
					"<input type='email' id='{email_name}' name='{email_name}' placeholder='{email_placeholder}' value=''>" . 
					"{honeypot}" .
					"<input type='submit' name='{submit_name}' value='{submit_label}'>" . 
					"{extras}" .
				"</form>",
			
			// confirmation email
			'emailSubject' => '{list} - Please confirm your subscription',
			'emailSubject2' => '', // reminder email subject or blank to use emailSubject
			'emailNote' => 'Please confirm your subscription to the “{list}” list by clicking the URL below:',
			'emailConfirm' => 'Confirm Subscription',
			'emailBodyHTML' => "<html><body><p>{email_note}</p><p><a href='{url}'>{email_confirm}</a></p></body></html>",
			'emailBodyText' => "{email_note}\n\n{url}",
			'emailFrom' => '', // optional email address from
			'emailFromName' => '', // optional email name from
		);
		
		$this->unsubscribeOptions = array(
			'useCSRF' => true,
			'submitFieldName' => 'unsub_submit',
			'submitFieldLabel' => 'Confirm unsubscribe',
			'emailFieldName' => 'unsub_email',
			'emailFieldLabel' => 'Email address to unsubscribe',
			'errorInvalid' => 'Invalid unsubscribe request',
			'successMessage' => 'You have unsubscribed from the “{list}” list.',
			'confirmMessage' => 'Please confirm that you want to unsubscribe from list “{list}”.',
			'errorMarkup' => "<p class='error'>{message}</p>",
			'successMarkup' => "<p class='success'>{message}</p>",
			'wrapMarkup' => "<div id='promailer'>{out}</div>", 
			'formMarkup' =>
				"<form action='{url}#promailer-form' method='post'>" .
					"<label for='{email_name}'>{email_label}</label> " .
					"<input type='email' id='{email_name}' name='{email_name}' value='{email_value}'> " .
					"<input type='submit' id='{submit_name}' name='{submit_name}' value='{submit_label}'>" . 
					"{extras}" .
				"</form>"
		);
	}
	
	/**
	 * Render, process and email-confirm a subscription
	 *
	 * This method is automatically calls the processSubscribe() and checkConfirmEmail()
	 * methods when appropriate. As a result, the entire subscription process can be managed
	 * just by calling this method.
	 *
	 * @param array $options
	 *  - `listId` (int): List that this form is to subscribe for or -1 for @todo multiple/user-selected (default=0)
	 *  - `useCSRF` (bool): Use CSRF protection with subscribe form? (default=true)
	 *     You should turn this off if using the subscribe form on a cached-page.
	 *  - `useHoneypot` (bool): Use a honeypot to reduce spam? (default=true). 
	 *  - `honeypotFieldName` (string): Name of honeypot field (default=subscribe_name).
	 *  - `emailFieldName` (string): Name of email field (default=subscribe_email)
	 *  - `emailFieldPlaceholder` (string): Placeholder attribute for email field (default=email@domain.com)
	 *  - `submitFieldName` (string): Name of submit field (default=subscribe_submit)
	 *  - `submitFieldLabel` (string): Label/value for submit button (default=Subscribe)
	 *  - `formMarkup` (string): Markup to use for form, or omit for default.
	 *  - `wrapMarkup` (string): Markup that wraps all output. 
	 *  - `errorMarkup` (string): Markup to use for error messages, where {message} is replaced with error.
	 *  - `successMarkup` (string): Markup to use for success messages, where {message} is replaced with message.
	 *  - `emailSubject` (string): Subject for confirmation email that gets sent (default={list} - Please confirm...)
	 *  - `emailBodyHTML` (string): HTML email body where {url} is replaced with confirmation URL (default provided).
	 *  - `emailBodyText` (string): Text email body where {url} is replaced with confirmation URL (default provided).
	 *  - `emailFrom` (string): Email "from" address (default provided).
	 *  - `emailFromName` (string): Optional email from name.
	 *  - `errorCSRF` (string): Error to display on CSRF error.
	 *  - `errorRequired` (string): Error to display on missing required field error.
	 *  - `errorDuplicate` (string): Error to display if user is already subscribed to the list.
	 *  - `errorEmail` (string): Error to display if email did not validate.
	 *  - `errorSend` (string): Error to display if email did not send.
	 *  - `errorAdd` (string): Error to display if subscriber cannot be added for unknown error.
	 *  - `errorHoneypot` (string): Message to display if honeypot check failed (should be a fake success message).
	 *  - `successEmailSent` (string): Text to when confirmation email has been sent (default provided)
	 *  - `successConfirmed` (string): Text to show when subscription has been confirmed (default=Thank you...)
	 * @return string
	 *
	 */
	public function ___subscribe(array $options = array()) {
	
		$sanitizer = $this->wire()->sanitizer;
		$input = $this->wire()->input;

		$options = array_merge($this->subscribeOptions, $options);
		$listId = $this->_id($options['listId']);
		$error = false;
		$message = '';
		$showForm = true;
		$extras = '';
		$honeypot = '';
		$out = '';
		
		if(empty($options['honeypotFieldName']) || strpos($options['formMarkup'], '{honeypot}') === false) {
			$options['useHoneypot'] = false;
		}

		// get first non-closed list id
		if(!$listId) $listId = $this->lists->getOpen(1);
		
		// if still cannot find list, return an error
		if(!$listId) {
			$out = str_replace('{message}', $options['errorListUnknown'], $options['errorMarkup']);
			return $this->wrapOutput($out, $options['wrapMarkup']);
		}

		$list = $this->lists->get($listId);
		
		if(!$list) {
			$message = $options['errorListUnknown'];
			$error = true;
			$showForm = false;
		} else if($list['closed'] || $list['type'] === 'pages') {
			$message = $options['errorListClosed'];
			$error = true;
			$showForm = false;
		} else if($input->post($options['submitFieldName']) !== null) {
			// form submitted
			$result = $this->processSubscribe($list, $options);
			if($result['error'] === 'errorHoneypot') $result['error'] = ''; // hide honeypot error
			$message = $result['message'];
			if(empty($result['error'])) {
				// success
				$showForm = false;
			} else {
				// error
				$error = true;
			}
		} else if($this->checkConfirmEmail()) {
			$message = $options['successConfirmed'];
			$showForm = false;
		}

		if($error) {
			$out = str_replace('{message}', $message, $options['errorMarkup']);
		} else if($message) {
			$out = str_replace('{message}', $message, $options['successMarkup']);
		}

		if(!$showForm) {
			return $this->wrapOutput($out, $options['wrapMarkup']); 
		}

		if($options['useCSRF']) {
			$extras .= $this->wire()->session->CSRF()->renderInput(); // render a hidden input for CSRF
		}

		if($options['useHoneypot']) {
			$honeypot = "<input type='text' id='{honeypot_name}' name='{honeypot_name}' value=''>";
			$extras .= "<style type='text/css'>#{honeypot_name}{display:none;}</style>"; // hide the honeypot input
		}

		$replacements = array(
			'{url}' => $this->wire('page')->url . "?list=$listId",
			'{list}' => $sanitizer->entities($list['title']),
			'{list_id}' => $listId,
			'{honeypot}' => $honeypot,
			'{extras}' => $extras,
			'{email_name}' => $options['emailFieldName'],
			'{email_value}' => $input->get('email') ? $sanitizer->entities($sanitizer->email($input->get('email'))) : '',
			'{email_label}' => $sanitizer->entities($options['emailFieldLabel']),
			'{email_placeholder}' => $sanitizer->entities($options['emailFieldPlaceholder']),
			'{submit_name}' => $options['submitFieldName'],
			'{submit_label}' => $sanitizer->entities($options['submitFieldLabel']),
			'{honeypot_name}' => $options['honeypotFieldName']
		);
		
		foreach($list->fields as $fieldInfo) {
			$name = $fieldInfo['name'];
			
			foreach($fieldInfo as $k => $v) {
				if($k === 'name' || $k === 'options') continue;
				$replacements['{' . $name . "_$k" . '}'] = $sanitizer->entities((string) $v);
			}

			/*
			$tag = '{' . $name . '_options}';
			if(!empty($fieldInfo['options']) && strpos($options['formMarkup'], $tag)) {
				$options = array();
				foreach($fieldInfo['options'] as $k => $v) {
					$k = $sanitizer->entities($k);
					$v = $sanitizer->entities($v);
					if($fieldInfo['type'] === 'option') {
						$selected = !empty($fieldInfo['value']) && $fieldInfo['value'] === $v ? " selected='selected'" : "";
						$o = "<option$selected value='$k'>$v</option>";
					} else {
						$class = empty($fieldInfo['class']) ? '' :  " class='$fieldInfo[class]'";
						$o = 
							"<div class='promailer-checkbox'>" . 
							"<label><input type='checkbox' name='{$name}[]' value='$k'$class /> $v</label>" . 
							"</div>";
					}
					$options[] = $o;
				}
				$replacements[$tag] = implode('', $options);
			}
			*/
		
			$tag = '{' . $name . '_input}';
			if(strpos($options['formMarkup'], $tag) !== false) {
				$replacements[$tag] = PromailerLists::listFields()->fieldArrayToInput($fieldInfo);
			}
		}

		$out .= str_replace(array_keys($replacements), array_values($replacements), $options['formMarkup']);

		return $this->wrapOutput($out, $options['wrapMarkup']);
	}

	/**
	 * Process a subscription form
	 * 
	 * This is already called by the renderSubscribeForm() method when the form is submitted
	 * 
	 * #pw-internal
	 *
	 * @param int|ProMailerList $list
	 * @param array $options
	 * @return array Returns array with 'error' and 'message' keys
	 *
	 */
	public function ___processSubscribe($list, array $options) {
		
		$input = $this->wire()->input;
		$sanitizer = $this->wire()->sanitizer;

		$list = $this->lists->_list($list);
		if(!$list) throw new WireException("Unknown list");

		$result = array(
			'error' => '',
			'message' => '',
			'email' => '', 	
			'data' => array(), 
			'list' => null,
			'subscriber' => null, 
		);

		$options = array_merge($this->subscribeOptions, $options);
		$email = $input->post->email($options['emailFieldName']);
		$subscriber = null;
		$error = '';
		$errorNote = '';
		$data = array();
		$listId = $list['id'];

		if($options['useCSRF'] && !$this->wire()->session->CSRF()->hasValidToken()) {
			// CSRF failed
			$error = 'errorCSRF';

		} else if(!strlen($email)) {
			// invalid email address
			$error = 'errorEmail';

		} else if($options['useHoneypot'] && $input->post($options['honeypotFieldName'])) {
			// honeypot populated
			$error = 'errorHoneypot';

		} else {
			// add in any other defined custom fields
			$missingNames = array();
			foreach($list->fields as $name => $fieldInfo) {
				if($fieldInfo['internal']) continue;
				$type = $fieldInfo['type'];
				$value = $input->post($name);
				try {
					if(in_array($fieldInfo['type'], array('option', 'options', 'checkboxes'))) {
						$value = $sanitizer->$type($value, array_keys($fieldInfo['options']));
					} else {
						$value = $sanitizer->$type($value);
					}
				} catch(\Exception $e) {
					$value = $sanitizer->text($value);
				}
				if($fieldInfo['required'] && empty($value)) {
					$error = 'errorRequired';
					$missingNames[] = $name;
				}
				$data[$name] = $value;
			}
			
			if(count($missingNames)) {
				$errorNote = implode(', ', $missingNames);
			}
		}
		
		// @todo move rest into separate public method for better non-confirmed API subscriber additions

		if(!$error) {
			// attempt to add subscriber
			$subscriber = $this->subscribers->add($email, $listId, false, $data);
		}
		
		if($error) {
			// error already recorded

		} else if(is_int($subscriber)) {
			// subscriber ID returned, which indicates user is already subscribed
			$error = 'errorDuplicate';

		} else if($subscriber instanceof ProMailerSubscriber) {
			// subscriber object returned, which indicates new subscriber: send them a confirmation email
			$replacements = array(
				'{email_note}' => $options['emailNote'], 
				'{email_confirm}' => $options['emailConfirm'],
			);
			$emailOptions = array(
				'subject' => $options['emailSubject'],
				'subject2' => (empty($options['emailSubject2']) ? '' : $options['emailSubject2']),
				'bodyHTML' => str_replace(array_keys($replacements), array_values($replacements), $options['emailBodyHTML']),
				'body' => str_replace(array_keys($replacements), array_values($replacements), $options['emailBodyText']), 
			);

			if(!empty($options['emailFrom'])) $emailOptions['fromEmail'] = $options['emailFrom'];
			if(!empty($options['emailFromName'])) $emailOptions['fromName'] = $options['emailFromName'];

			if($this->sendConfirmEmail($subscriber, $emailOptions)) {
				// success sending email
				$result['message'] = $options['successEmailSent'];
			} else {
				// failed to send email
				$error = 'errorSend';
			}

		} else {
			// error adding subscriber
			$error = 'errorAdd';
		}
		
		$result['email'] = $email;
		$result['data'] = $data; 
		$result['subscriber'] = $subscriber; 
		$result['list'] = $list;

		if($error) {
			$result['error'] = $error;
			$result['message'] = isset($options[$error]) ? $options[$error] : $error;
			if($errorNote) $result['message'] .= " ($errorNote)";
			$logError = strtoupper(str_replace('error', '', $error));
			$this->subscribers->saveLog("SUBSCRIBE-ERROR-$logError $subscriber[email] ($list[title])"); 
		}

		return $result;
	}

	/**
	 * Render or process an un-subscribe form at URL clicked from an email
	 *
	 * Requires that the URL contains these valid GET variables below that match the subscribers record.
	 * You can get this URL by calling the getUnsubscribeUrl() method.
	 *
	 *  - `unsub` (int): ID of the list to unsubscribe from.
	 *  - `email` (string): Email address to unsubscribe.
	 *  - `code` (string): Authentication code stored with subscriber.
	 *
	 * If any of the above variables are not present or incorrect, an error message will be returned.
	 *
	 * @param array $options
	 * @return string
	 *
	 */
	public function ___unsubscribe(array $options = array()) {

		$input = $this->wire()->input;
		$session = $this->wire()->session;
		$sanitizer = $this->wire()->sanitizer;

		$options = array_merge($this->unsubscribeOptions, $options);
		$listId = (int) $this->_id($input->get('unsub'));
		$list = $listId > 0 ? $this->lists->get($listId) : false;
		$email = $input->get->email('email');
		$code = $input->get->text('code');
		$out = '';
		$error = false;
		$submit = false;
		$message = '';
		$postEmail = '';
	
		if($input->post($options['submitFieldName']) !== null) {
			// confirmed unsubscribe
			if(!$options['useCSRF'] || $session->CSRF()->hasValidToken()) $submit = true;
			if($submit) $postEmail = $input->post->email($options['emailFieldName']);
		} else if($list && $list->unsub_email && $input->requestMethod('POST')) {
			// list-unsubscribe header support for 1-click unsubscribe (RFC 8058)
			$postEmail = $email;
			$fp = fopen("php://input", "r");
			$submit = trim(strtolower(fread($fp, 48))) === 'list-unsubscribe=one-click';
			fclose($fp);
			unset($inStr);
		}
		
		if(!$list || !$email || !$code) {
			// missing required variable
			$error = $options['errorInvalid'];

		} else if($submit) {
			// process unsubscription
			if($postEmail === $email) {
				$subscriber = $this->subscribers->getByEmail($postEmail, $list);
				if(!$subscriber) {
					$error = $options['errorInvalid'];
				} else if($this->subscribers->unsubscribe($subscriber, $list, $code)) {
					$message = $options['successMessage'];
				} else {
					$error = $options['errorInvalid'];
				}
			} else {
				$error = $options['errorInvalid'];
			}
			
		} else {
			// render confirm unsubscribe form
			$subscriber = $this->subscribers->getByEmail($email, $list);
			if($subscriber && $subscriber['code'] === $code) {
				$error = $options['confirmMessage'];
				$email = $subscriber['email'];
				$url = $this->getUnsubscribeUrl($subscriber);
				$out = str_replace('{url}', $url, $options['formMarkup']);
				$out = str_replace('{extras}', $options['useCSRF'] ? $session->CSRF()->renderInput() : '', $out);
			} else {
				$error = $options['errorInvalid'];
			}
		}

		if($error) {
			$out = $options['errorMarkup'] . $out;
			$message = $error;
		} else if($message) {
			$out = $options['successMarkup'] . $out;
		}

		$replacements = array(
			'{message}' => $message,
			'{list}' => $list['title'],
			'{email_name}' => $options['emailFieldName'],
			'{email_label}' => $options['emailFieldLabel'],
			'{email_value}' => $sanitizer->entities($email),
			'{submit_name}' => $options['submitFieldName'],
			'{submit_label}' => $options['submitFieldLabel'],
		);

		$out = str_replace(array_keys($replacements), array_values($replacements), $out);

		return $this->wrapOutput($out, $options['wrapMarkup']);
	}
	
	/**
	 * Send confirmation email to subscriber
	 *
	 * @param ProMailerSubscriber $subscriber
	 * @param array|string
	 * - `fromEmail` (string): From email address or omit for default
	 * - `fromName` (string): Optional from name to accompany from email
	 * - `subject` (string): Message subject or omit for default
	 * - `subject2` (string): Message subject to use when re-sending confirmation email
	 * - `page` (Page): Page that will process confirmation (default=current page)
	 * - `bodyHTML` (string): Optional message body in HTML (omit for default)
	 * - `body` (string): Optional message body in plain text (omit for default)
	 * - If given a string for $options it is assumed to be the fromEmail option.
	 * @return bool True if message sent, false if not
	 *
	 */
	public function ___sendConfirmEmail(ProMailerSubscriber $subscriber, $options = array()) {

		$sanitizer = $this->wire()->sanitizer;
		
		$subscriber = $this->subscribers->_subscriber($subscriber);
		$list = $this->lists->get($subscriber['list_id']);

		$defaults = array(
			'fromEmail' => is_string($options) ? $options : '',
			'fromName' => '',
			'subject' => $this->_('{list} - Please confirm subscription'),
			'subject2' => '',
			'page' => null,
			'body' => "Please confirm subscription by clicking the URL below:\n\n{url}\n\n",
			'bodyHTML' => 
				'<html><body><p>Please confirm subscription by clicking the link below:</p>' . 
				'<p><a href=\'{url}\'>{url}</a></p></body></html>',
			'tries' => 0, 
		);
		
		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		
		if(empty($options['page']) || !$options['page'] instanceof Page || !$options['page']->id) {
			$options['page'] = $this->getSubscribePage();
		}
		
		$confirmUrl = $this->subscribers->getConfirmUrl($subscriber, $options['page'], $list);
		
		if(empty($options['fromEmail'])) $options['fromEmail'] = $this->promailer->defaultFromEmail;
		if(empty($options['fromName'])) $options['fromName'] = $this->promailer->defaultFromName;
		
		$mail = $this->email->getMailer($this->promailer->transMailer);
		$mail->to($subscriber['email']);
		$mail->subject(str_replace('{list}', $list['title'], $options['subject']));

		if(empty($options['bodyHTML'])) {
			$options['bodyHTML'] = $this->renderConfirmEmail('html');
		}

		if(empty($options['body'])) {
			$options['body'] = $this->renderConfirmEmail('text');
		}

		$mail->bodyHTML(str_replace(
			array('{url}', '{list}'),
			array($sanitizer->entities($confirmUrl), $sanitizer->entities($list['title'])),
			$options['bodyHTML']
		));

		if($options['bodyHTML'] != $options['body']) {
			$mail->body(str_replace(
				array('{url}', '{list}'),
				array($confirmUrl, $list['title']),
				$options['body']
			));
		}

		if($options['fromEmail']) $mail->from($options['fromEmail']);
		if($options['fromName']) $mail->fromName($options['fromName']);

		$result = $this->email->send($mail) ? true : false;

		// remember confirmation email data in case we later need to re-send
		$subscriber->setConfData(array(
			'subject' => ($options['subject2'] ? $options['subject2'] : $options['subject']),
			'pageID' => (int) "$options[page]",
			'langID' => ($this->wire()->languages ? $this->wire()->user->language->id : 0),
			'fromEmail' => $options['fromEmail'],
			'fromName' => $options['fromName'],
			'body' => $options['body'],
			'bodyHTML' => $options['bodyHTML'],
			'sent' => time(),
			'result' => $result,
			'tries' => $options['tries']+1, 
		), true);
	
		return $result;
	}

	/**
	 * Re-send existing confirmation email
	 *
	 * @param ProMailerSubscriber $subscriber
	 * @return bool
	 *
	 */
	public function resendConfirmEmail(ProMailerSubscriber $subscriber) {
		
		if(!$subscriber->allowResend()) return false;
		
		$languages = $this->wire()->languages;
		$options = $subscriber->getConfData();
		$setLanguage = null;
		
		if(!empty($options['pageID'])) {
			// page ID from saved confData 
			$pages = $this->wire()->pages;
			$page = $pages->findOne("include=hidden, id=" . (int) $options['pageID']);
			if($page->id) $options['page'] = $page;
			unset($options['pageID']); 
		}

		if(!empty($options['langID']) && $languages) {
			// language ID from saved confData
			$language = $this->wire()->user->language;
			if($language && $language->id != $options['langID']) {
				$setLanguage = $languages->get((int) $options['langID']);
				if($setLanguage->id && $setLanguage->id != $language->id) {
					// a different language was used for confirmation email
					$languages->setLanguage($setLanguage);
				} else {
					$setLanguage = null;
				}
			}
			unset($options['langID']); 
		}
		
		$result = $this->sendConfirmEmail($subscriber, $options);
		
		if($setLanguage) $languages->unsetLanguage();
		
		return $result;

	}
	
	/**
	 * Fallback to render confirm email body (likely not used)
	 * 
	 * In returned value the tag “{url}” will be replaced with the confirmation URL
	 * and the tag “{list}” will be replaced with the title of the list.
	 * 
	 * #pw-internal
	 *
	 * @param string $type Email type, either "html" or "text"
	 * @return string Rendered email in HTML or TEXT
	 *
	 */
	public function ___renderConfirmEmail($type) {

		$body = 'Please confirm your subscription to the “{list}” list by clicking the URL below:';

		if($type === 'html') {
			// HTML body
			return
				"<p>$body</p>" .
				"<p><a href='{url}'>{url}</a></p>";
		} else {
			// plain text body
			return "$body\n\n{url}\n\n";
		}
	}

	/**
	 * Check if current request is for a subscribe confirmation and confirm email when applicable
	 *
	 * @return ProMailerSubscriber|bool|null Returns ProMailerSubscriber on success, null when not applicable and false on failure
	 *
	 */
	public function ___checkConfirmEmail() {
		$input = $this->wire()->input;
		$sanitizer = $this->wire()->sanitizer;
		
		$listId = (int) $input->get('list');
		if(!$listId) return null;
		
		$list = $this->lists->get($listId);
		if(!$list) return null;
		if($list->closed) return false;
		
		$email = $sanitizer->email($input->get('email'));
		if(!$email) return null;
		
		$code = $sanitizer->alphanumeric($input->get('code'));
		if(!$code) return null;
		
		if($this->subscribers->confirmEmail($email, $list, $code)) {
			$subscriber = $this->subscribers->getByEmail($email, $list);
			return $subscriber;
		}
		
		return false;
	}

	/**
	 * Return URL to an unsubscribe form
	 *
	 * @param ProMailerSubscriber $subscriber
	 * @param array|bool $options
	 *  - `queryStringOnly` (bool): Only return the query string? (default=false)
	 *  - `requireHttps` (bool): Require that returned URL uses https scheme? (default=false)
	 *  - If given boolean for $options, the queryStringOnly option is assumed. 
	 * @return string
	 *
	 */
	public function ___getUnsubscribeUrl(ProMailerSubscriber $subscriber, $options = array()) {
		$defaults = array(
			'queryStringOnly' => is_bool($options) ? $options : false,
			'requireHttps' => false
		);
		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		$url = ($options['queryStringOnly'] ? '' : $this->getSubscribePage()->httpUrl());
		if($options['requireHttps']) $url = str_replace('http://', 'https://', $url);
		$url .= 
			"?unsub=$subscriber[list_id]" .
			"&email=" . urlencode($subscriber['email']) .
			"&code=" . urlencode($subscriber['code']);
		return $url;
	}
	
	/**
	 * Get the Field that has a non-zero value if user is unsubscribed from a pages list
	 * 
	 * Applies only to pages lists!
	 * 
	 * This method also creates a generic promailer_unsubscribe field if none exists, 
	 * and adds the field to any relevant fieldgroups. 
	 * 
	 * @param ProMailerList $list
	 * @param bool $allowCreate Create it if it does not exist? (default=true)
	 * @param bool $allowAddTemplates Allow adding it to templates? (default=true)
	 * @return Field|null
	 * 
	 */
	public function ___getUnsubscribeField(ProMailerList $list, $allowCreate = true, $allowAddTemplates = true) {
		$fields = $this->wire()->fields;
		
		if($list['type'] !== 'pages') return null;
		$field = null;
		if($list['unsub_field']) {
			$fieldName = $list['unsub_field'];
			$field = $fields->get($fieldName);
		}
		if(!$field) {
			$field = $fields->get(ProMailer::unsubscribeFieldName);
			if(!$field) {
				if(!$allowCreate) return null;
				$field = new Field();
				$field->name = ProMailer::unsubscribeFieldName;
				$field->label = 'Unsubscribe from emails';
				$field->type = $this->wire()->modules->get('FieldtypeCheckbox');
				try {
					$field->save();
				} catch(\Exception $e) {
					$this->error($e->getMessage());
				}
			}
		}
		if($allowAddTemplates) {
			$templates = $this->wire()->templates;
			$templateNames = $this->subscribers->getPageTemplates($list);
			foreach($templateNames as $templateName) {
				$template = $templates->get($templateName);
				if(!$template) continue;
				$fieldgroup = $template->fieldgroup;
				if($fieldgroup->hasField($field)) continue;
				$fieldgroup->add($field);
				try {
					$fieldgroup->save();
				} catch(\Exception $e) {
					$this->error($e->getMessage());
				}
			}
		}
		return $field;	
	}
	
	/**
	 * Get URL to subscribe to given list
	 *
	 * @param int|ProMailerList $listId
	 * @return string
	 *
	 */
	public function ___getSubscribeUrl($listId = 0) {
		$listId = $this->_id($listId);
		$page = $this->getSubscribePage();
		return $page->httpUrl() . ($listId ? "?list=$listId" : "");
	}

	/**
	 * Get the ProMailer page that handles subscribe and unsubscribe
	 *
	 * @param bool $allowCreate Create if not exists? (default=true)
	 * @return Page|NullPage
	 *
	 */
	public function ___getSubscribePage($allowCreate = true) {
		
		$pages = $this->wire()->pages;

		$page = $pages->get("include=hidden, template=" . ProMailer::subscribeTemplateName);
		if($page->id) return $page;
		
		if(!$allowCreate) return new NullPage();
		
		$config = $this->wire()->config;
		$templates = $this->wire()->templates;
		$fieldgroups = $this->wire()->fieldgroups;
		
		$template = $templates->get(ProMailer::subscribeTemplateName);
		
		if(!$template) {
			$fieldgroup = $fieldgroups->get(ProMailer::subscribeTemplateName);
			if(!$fieldgroup) {
				$fieldgroup = new Fieldgroup();
				$fieldgroup->name = ProMailer::subscribeTemplateName;
				$fieldgroup->add($this->wire('fields')->get('title'));
				$fieldgroup->save();
			}
			$template = new Template();
			$template->name = ProMailer::subscribeTemplateName;
			$template->label = 'ProMailer: Subscribe';
			$template->fieldgroup = $fieldgroup;
			$template->slashUrls = 1;
			$template->noGlobal = 1;
			$template->noParents = -1;
			$template->save();
			$this->message("Created Template: $template->name");
		} else if($template->flags & Template::flagSystem) {
			$template->flags = Template::flagSystemOverride;
			$template->flags = 0;
			$template->save();
		}
		
		$page = new Page();
		$page->template = $template;
		$page->name = ProMailer::name;
		$page->parent = '/';
		$page->addStatus(Page::statusHidden);
		$page->title = 'Subscribe';
		$page->save();
		$this->message("Created page $page->path");
		
		$src = dirname(__FILE__) . '/' . ProMailer::subscribeTemplateName . '.php';
		$dst = $config->paths->templates . ProMailer::subscribeTemplateName . '.php';
		if(!file_exists($dst)) {
			$srcLabel = str_replace($config->paths('root'), '/', $src);
			$dstLabel = str_replace($config->paths('root'), '/', $dst);
			if($this->wire()->files->copy($src, $dst)) {
				$this->message("Copied $srcLabel to $dstLabel");
			} else {
				$this->warning("Unable to copy $srcLabel to $dstLabel - please copy this file manually");
			}
		}
		
		return $page;
	}

	/**
	 * Wrap given output
	 * 
	 * @param string $out
	 * @param string $markup
	 * @return string
	 * 
	 */
	public function ___wrapOutput($out, $markup = '') {
		if(empty($markup) || strpos($markup, '{out}') === false) {
			$markup = "<div id='promailer'>{out}</div>";
		}
		return str_replace('{out}', $out, $markup); 
	}
	
	public function __get($key) {
		switch($key) {
			case 'subscribePage': $value = $this->getSubscribePage(); break;
			case 'subscribeUrl': $value = $this->getSubscribeUrl(); break;
			case 'subscribeOptions': $value = $this->subscribeOptions; break;
			case 'unsubscribeOptions': $value = $this->unsubscribeOptions; break;
			default: $value = parent::__get($key);
		}
		return $value;
	}
	
	public function __set($key, $value) {
		if($key === 'subscribeOptions' && is_array($value)) {
			$this->subscribeOptions = array_merge($this->subscribeOptions, $value); 
		} else if($key === 'unsubscribeOptions' && is_array($value)) {
			$this->unsubscribeOptions = array_merge($this->unsubscribeOptions, $value); 
		} else {
			throw new WireException("Unknown set"); 
		}
		// note: Wire class has no __set() to fall-back
	}
	
	protected function removeSubscribePage() {
		$page = $this->wire()->pages->get("include=all, template=" . ProMailer::subscribeTemplateName);
		
		if($page->id) {
			$this->message("Removing page: $page->path");
			$page->delete();
		}

		$template = $this->wire()->templates->get(ProMailer::subscribeTemplateName);
		if($template) {
			$template->flags = Template::flagSystemOverride;
			$template->flags = 0;
			$this->message("Removing template: $template->name");
			$this->wire('templates')->delete($template);
		}

		$fieldgroup = $this->wire()->fieldgroups->get(ProMailer::subscribeTemplateName);
		if($fieldgroup) {
			$this->message("Removing fieldgroup: $fieldgroup->name");
			$this->wire()->fieldgroups->delete($fieldgroup);
		}

		$templateFile = $this->wire()->config->paths->templates . ProMailer::subscribeTemplateName . '.php';
		if(is_file($templateFile)) {
			$this->message("If you do not intend to re-install, please delete this template file: /site/templates/" . basename($templateFile));
		}
	}
	
	public function install() {
		$this->getSubscribePage();
		return parent::install();
	}
	
	public function uninstall() {
		$this->removeSubscribePage();
		return parent::uninstall();
	}
	
	public function table() {
		return '';
	}
	
	public function typeName() {
		return '';
	}
	
	/*** DEPRECATED METHODS ********************************************************************************/

	/**
	 * @param array $options
	 * @return string
	 * @deprecated
	 * 
	 */
	public function renderSubscribeForm(array $options = array()) {
		return $this->subscribe($options);
	}

	/**
	 * @param array $options
	 * @return string
	 * @deprecated
	 * 
	 */
	public function renderUnsubscribeForm(array $options = array()) {
		return $this->unsubscribe($options);
	}

}