<?php namespace ProcessWire;

class ProcessProMailerMessage extends ProcessProMailerTypeManager {

	/**
	 * Execute editing of message
	 *
	 * @return string
	 * @throws WireException
	 *
	 */
	public function execute() {

		/** @var ProMailerList|null $list */
		
		$input = $this->wire()->input;
		$session = $this->wire()->session;

		$messageId = (int) $input->get('message_id');
		$message = $messageId > 0 ? $this->promailer->messages->get($messageId) : null;
		if(!$message) $session->location('../messages/');

		if($message) {
			$queueItem = $this->promailer->queues->get($messageId);
			$headline = sprintf($this->_('Message: %s'), $message['title']);
			if($queueItem) {
				if($queueItem->paused) {
					$headline .= " (paused)";
				} else {
					// currently sending in background
					$session->location("../queue/?message_id=$message[id]");
				}
			}
			$this->process->headline($headline);
			$list = $message['list_id'] ? $this->promailer->lists->get($message['list_id']) : null;
			if($input->get('finished')) {
				$this->message("Finished sending to list “" . $list['title'] . "”");
			}
		} else {
			$this->process->headline($this->_('Add new message'));
			$list = null;
		}


		$this->process->breadcrumb('../', $this->label('promailer'));
		$this->process->breadcrumb('../messages/', $this->label('messages'));

		$form = $this->buildForm($message, $list);

		if($input->post('submit_save_message') || $input->post('submit_send_message')) {
			$this->process($form, $message);
		}

		return $form->render();
	}

	/**
	 * Process save from message editor
	 *
	 * @param InputfieldForm $form
	 * @param ProMailerMessage $message
	 *
	 */
	protected function process(InputfieldForm $form, ProMailerMessage $message) {

		$input = $this->wire()->input;
		$form->processInput($input->post);
		$_message = $message->getArray();
		$list = $this->promailer->lists->get($message['list_id']);
		$errors = array();
		$unableToFindLabel = $this->_('Unable to find subscriber in this list:') . ' ';

		foreach($form->getAll() as $f) {
			/** @var Inputfield $f */
			$name = $f->attr('name');
			$value = $f->attr('value');
			if($name === 'subscriber_id') continue;
			if(!array_key_exists($name, $_message)) continue;
			if($value instanceof Page) $value = $value->id;
			if(substr($name, -3) === '_id' || $name == 'body_page') {
				$value = (int) $value;
			}
			$message[$f->name] = $value;
		}

		// allow for either ID or email in subscriber_id field
		$f = $form->getChildByName('subscriber_id');
		if($f) {
			$value = $f->attr('value');
			if(ctype_digit("$value") || empty($value)) {
				$value = (int) $value;
				if($value) {
					$subscriber = $this->promailer->subscribers->getById($value, $list);
					if(!$subscriber) $errors[] = $unableToFindLabel .  "ID $value";
				}
			} else if(strpos($value, '@')) {
				$_value = $value;
				$value = $this->wire()->sanitizer->email($value);
				if($value) $value = $this->promailer->subscribers->getByEmail($value, $list);
				$value = $value ? (int) $value['id'] : 0;
				if(!$value) $errors[] = $unableToFindLabel . $_value;
			}
			$message['subscriber_id'] = $value;
		}

		if($_message['list_id'] != $message['list_id'] && $_message['subscriber_id'] == $message['subscriber_id']) {
			// ensure that a change to the list also removes the subscriber_id connected with the list
			$message['subscriber_id'] = 0;
		}

		$message['throttle_secs'] = (int) $form->getChildByName('throttleSecs')->attr('value');
		$message['send_qty'] = (int) $form->getChildByName('sendQty')->attr('value');
		if($message['send_qty'] < 1) $message['send_qty'] = 1;

		// flags
		$flags = 0;
		$sendType = $form->getChildByName('sendType')->attr('value');
		if($sendType === 'queue') $flags = $flags | ProMailer::messageFlagsQueueSend;
		// $sendNewest = (int) $form->getChildByName('sendNewest')->attr('value');
		// if($sendNewest) $flags = $flags | ProMailer::messageFlagsNewestFirst;
		$message['flags'] = $flags;

		// save
		$result = $this->promailer->messages->save($message);

		if($result && $input->post('submit_send_message') && !count($errors)) {
			if($flags & ProMailer::messageFlagsQueueSend) {
				$queueItem = $this->promailer->queues->get($message['id']);
				$property = $queueItem ? 'resume' : 'start';
				$redirect = "../queue/?message_id=$message[id]&$property=1";
			} else {
				$redirect = "../send/?message_id=$message[id]";
			}
		} else {
			$this->message(sprintf($this->_('Saved message: %s'), $message['title']));
			if(count($errors)) foreach($errors as $error) $this->error($error);
			$redirect = "./?message_id=$message[id]";
		}

		$this->wire()->session->location($redirect);
	}

	/**
	 * Build the message edit form
	 *
	 * @param ProMailerMessage $message
	 * @param ProMailerList|null $list
	 * @return InputfieldForm
	 *
	 */
	protected function buildForm(ProMailerMessage $message, $list) {
		$modules = $this->wire()->modules;

		/** @var InputfieldForm $form */
		$form = $modules->get('InputfieldForm');
		$form->attr('action', "./?message_id=" . $message->id);
		$form->attr('name', 'message');

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = $this->_('Message content');
		$fieldset->icon = 'envelope';
		$fieldset->set('themeOffset', 1);
		$form->add($fieldset);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'subject');
		$f->label = $this->_('Subject');
		$f->description = $this->_('The email subject line that appears in the user’s inbox. Use of custom field placeholders is also supported.');
		$f->attr('value', $message['subject'] ? $message['subject'] : $message['title']);
		$f->required = true;
		$fieldset->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'body_source');
		$f->label = $this->_('Body source');
		$f->addOption(ProMailer::bodySourceInput, $this->_('Content that you enter here'));
		$f->addOption(ProMailer::bodySourceHref, $this->_('URL that outputs the body'));
		$f->addOption(ProMailer::bodySourcePage, $this->_('Page that renders the body'));
		$f->attr('value', (int) $message['body_source']);
		$f->columnWidth = 50;
		$fieldset->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'body_type');
		$f->label = $this->_('Body content type');
		$f->addOption(ProMailer::bodyTypeHtml, $this->_('HTML with text version auto-generated'));
		$f->addOption(ProMailer::bodyTypeBoth, $this->_('Separate HTML and text versions'));
		$f->addOption(ProMailer::bodyTypeText, $this->_('Plain text only'));
		$f->attr('value', (int) $message['body_type']);
		$f->columnWidth = 50;
		$fieldset->add($f);

		/** @var InputfieldTextarea $f */
		$f = $modules->get('InputfieldTextarea');
		$f->attr('name', 'body_html');
		$f->label = $this->_('Paste in HTML version of message body');
		$f->attr('value', $message['body_html']);
		$f->showIf = 'body_source=' . ProMailer::bodySourceInput . ', body_type!=' . ProMailer::bodyTypeText;
		$fieldset->add($f);

		/** @var InputfieldTextarea $f */
		$f = $modules->get('InputfieldTextarea');
		$f->attr('name', 'body_text');
		$f->label = $this->_('Paste in PLAIN TEXT version of message body');
		$f->attr('value', $message['body_text']);
		$f->showIf = 'body_source=' . ProMailer::bodySourceInput . ', body_type!=' . ProMailer::bodyTypeHtml;
		$fieldset->add($f);

		/** @var InputfieldURL $f */
		$f = $modules->get('InputfieldURL');
		$f->attr('name', 'body_href_html');
		$f->label = $this->_('Enter the full http/https URL that outputs the message body in HTML');
		$f->attr('value', $message['body_href_html']);
		$f->showIf = 'body_source=' . ProMailer::bodySourceHref . ', body_type!=' . ProMailer::bodyTypeText;
		$fieldset->add($f);

		/** @var InputfieldURL $f */
		$f = $modules->get('InputfieldURL');
		$f->attr('name', 'body_href_text');
		$f->label = $this->_('Enter the full http/https URL that outputs the message body in PLAIN TEXT');
		$f->attr('value', $message['body_href_text']);
		$f->showIf = 'body_source=' . ProMailer::bodySourceHref . ', body_type!=' . ProMailer::bodyTypeHtml;
		$fieldset->add($f);

		if(count($this->promailer->useTemplates)) {
			/** @var InputfieldSelect $f */	
			$f = $modules->get('InputfieldSelect');
			$templateIDs = implode('|', $this->promailer->useTemplates);
			$items = $this->wire()->pages->find("template=$templateIDs, include=hidden, sort=title, sort=name");
			foreach($items as $p) {
				$f->addOption($p->id, $p->get('title|name') . ' (' . wireRelativeTimeStr($p->modified) . ')');
			}
			if($items->count() && empty($message['body_page'])) {
				$message['body_page'] = $items->sort('-modified')->first()->id;
			}
		} else {
			/** @var InputfieldPageListSelect $f */
			$f = $modules->get('InputfieldPageListSelect');
		}
		$f->name = 'body_page';
		$f->label = $this->_('Select the page that renders the email body');
		$f->description = $this->_('This should be a page that uses a template prepared for email output and has the content that you want to send.');
		$f->showIf = 'body_source=' . ProMailer::bodySourcePage;
		if($message['body_page']) {
			$f->attr('value', $message['body_page']);
			$bodyPage = $this->wire()->pages->get((int) $message['body_page']); 
			if($bodyPage->id) {
				$f->notes = $bodyPage->get('path') . ' — ' . 
					'[' . $this->_('Edit') . '](' . $bodyPage->editUrl() . ') | ' . 
					'[' . $this->_('View') . '](' . $bodyPage->url() . ')';
			}
		}
		$fieldset->add($f);
		
		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup');
		$f->label = $this->_('Email content and template file notes');
		$f->description = $this->_('Please note the following when developing email templates or content:');
		$f->value =
			"<p>" .
			"<u>" . $this->_('Linking to URLs, images or files') . "</u><br />" .
			$this->_('Links and file/image references should be absolute rather than relative.') . ' ' . 
			sprintf(
				$this->_('This means you should use %s rather than %s in any URLs in the email content and template file.'), 
				'<code>' . $this->_('https://domain.com/path/') . '</code>',
				'<code>' . $this->_('/path/') . '</code>'
			). ' ' . 
			$this->_('If the email contains relative URLs, ProMailer will attempt to convert them to absolute when possible.') . ' ' . 
			"</p>" . 
			"<p>" . 
			"<u>" . $this->_('HTML and text output') . "</u><br />" .
			sprintf(
				$this->_('For web developers: When using a “body source” of a Page or URL, an %s variable will be populated with the value of either “text” or “html”.'),
				"<code>\$input->get('type');</code>"
			) . ' ' .  
			$this->_('Your template file can adjust the output consistent with that.') . ' ' .
			$this->_('Otherwise, the text-only version (if enabled) will be based on the HTML version with the markup stripped out of it.') . 
			"</p>";
		$f->notes = sprintf(
			$this->_('Read more in the [ProMailer manual](%s).'), 
			'https://processwire.com/store/pro-mailer/manual/'
		);
		$f->collapsed = Inputfield::collapsedYes;
		$fieldset->add($f);

		/** @var MarkupAdminDataTable $table */
		$table = $modules->get('MarkupAdminDataTable');
		$table->setEncodeEntities(false);
		$table->setSortable(false);
		$table->headerRow(array(
			$this->_('Placeholder'), 
			$this->_('Description')
		));

		$placeholders = array(
			'email' => $this->_('Email address of the recipient'),
			'unsubscribe_url' => $this->_('URL that unsubscribes the recipient email address'),
			'subscribe_url' => $this->_('URL where users can subscribe to this list'),
			'from_email' => $this->_('Email address of the sender (as defined in the section below)'),
			'from_name' => $this->_('Name of the sender (as defined in the section below)'),
			'subject' => $this->_('Subject of the message'),
			'title' => $this->_('Admin title of the message'),
		);
		if($list) {
			foreach($list->fields as $fieldName => $fieldInfo) {
				if($fieldInfo['internal']) continue;
				if(!empty($fieldInfo['options'])) {
					$placeholders["$fieldName"] = sprintf(
						$this->_('Selected value for “%s” custom field'), 
						$fieldName
					);
					$placeholders["$fieldName.label"] = sprintf(
						$this->_('Selected item(s) label text for “%s” custom field'), 
						$fieldName
					);
				} else {
					$placeholders[$fieldName] = sprintf(
						$this->_('Value of custom “%s” field defined in list'),
						$fieldInfo['type']
					);
				}
			}
			if($list['type'] === 'pages') {
				$placeholders['page.field_name'] = 
					$this->_('Replace “field_name” with the name of any field on your subscriber pages');
			}
		}

		foreach($placeholders as $name => $description) {
			$table->row(array('<code>{' . $name . '}</code>', $description));
		}

		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup');
		$f->label = $this->_('Optional placeholder tags you can use in email content');
		$f->description = 
			$this->_('The following placeholders are automatically replaced in your message body or subject for each email recipient.');
		$f->value = $table->render();
		$f->notes =
			$this->_('Note that the available placeholders may change depending on the subscriber list you are sending to and what custom fields are defined.') . ' ' .
			$this->_('If you change the sending subscriber list (below), save and come back here to see what placeholders are available.') . ' ' . 
			sprintf(
				$this->_('Placeholders can also be used with conditional “if” and “else” statements, [read more](%s).'),
				'https://processwire.com/store/pro-mailer/manual/#custom-fields-placeholders-and-conditionals'
			);
		$f->collapsed = Inputfield::collapsedYes;
		$fieldset->add($f);

		if($message->list_id) {
			/** @var JqueryUI $jQueryUI */	
			$jQueryUI = $modules->get('JqueryUI');
			$jQueryUI->use('modal');

			/** @var InputfieldButton $preview */
			$preview = $modules->get('InputfieldButton');
			$previewUrl = "../preview/?message_id={$message->id}&type";
			$preview->href = "$previewUrl=text";
			$preview->value = $this->_('Preview TEXT email');
			$preview->setSmall(true);
			$preview->setSecondary(true);
			$preview->aclass = trim("$preview->aclass pw-modal pw-modal-small");
			$preview->icon = 'align-left';
			$buttonTEXT = $preview->render();
			$buttonTEXT .= 
				"<span class='detail'>" . 
				$this->_('Please also send to a “test” list to preview from your email.') . 
				"</span>";
			if($message->body_type != ProMailer::bodyTypeText) {
				$preview->href = "$previewUrl=html";
				$preview->value = $this->_('Preview HTML email');
				$preview->icon = 'code';
				$buttonHTML = $preview->render();
			} else {
				$buttonHTML = '';
			}
			$fieldset->appendMarkup = "<p class='promailer-preview'>$buttonHTML$buttonTEXT</p>";
		}

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = $this->_('Sending details');
		$fieldset->icon = 'sliders';
		// $fieldset->set('themeOffset', 1);
		$form->add($fieldset);

		if(empty($message['from_email'])) $message['from_email'] = $this->promailer->defaultFromEmail;
		if(empty($message['from_name'])) $message['from_name'] = $this->promailer->defaultFromName;

		/** @var InputfieldEmail $f */
		$f = $modules->get('InputfieldEmail');
		$f->attr('name', 'from_email');
		$f->label = $this->_('From email');
		$f->description = $this->_('The email address that the message appears from.');
		$f->attr('value', $message['from_email']);
		$f->columnWidth = 50;
		$f->required = true;
		$fieldset->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'from_name');
		$f->label = $this->_('From name');
		$f->description = $this->_('Person or company name.'); 
		$f->attr('value', $message['from_name']);
		$f->columnWidth = 25;
		$fieldset->add($f);
		
		/** @var InputfieldEmail $f */
		$f = $modules->get('InputfieldEmail');
		$f->attr('name', 'reply_email');
		$f->label = $this->_('Reply-to email');
		$f->description = $this->_('If different from “From email”.'); 
		$f->attr('value', $message['reply_email']);
		$f->columnWidth = 25;
		$fieldset->add($f);

		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect');
		$f->attr('name', 'list_id');
		$f->label = $this->_('Send to subscriber list');
		$f->description = $this->_('Select the list of subscribers you would like to send this message to.');
		$f->notes = $this->_('Tip: send to a test list with one or two subscribers before sending to your live list.');
		$f->required = true;
		foreach($this->promailer->lists->getAll() as $_list) {
			$f->addOption($_list['id'], "$_list[id]: $_list[title] " . $this->process->subscribersLabel($_list));
		}
		if($message['list_id']) $f->attr('value', $message['list_id']);
		$f->columnWidth = 50;
		$fieldset->add($f);

		/** @var InputfieldInteger $f */
		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'throttleSecs');
		$f->label = $this->_('Throttle seconds');
		$f->description = $this->_('Wait time between sending requests.');
		$f->attr('value', $message['throttle_secs']);
		$f->columnWidth = 25;
		$fieldset->add($f);

		/** @var InputfieldInteger $f */
		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'sendQty');
		$f->label = $this->_('Send quantity');
		$f->description = $this->_('Messages to send per request.');
		$f->attr('value', $message['send_qty']);
		$f->columnWidth = 25;
		$fieldset->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'mailer');
		$f->label = $this->_('Send method');
		$mailers = array('WireMail' => $this->_('Regular PHP mail'));
		foreach($modules->findByPrefix('WireMail') as $moduleName) {
			$moduleInfo = $modules->getModuleInfo($moduleName);
			$mailers[$moduleName] = $moduleInfo['title'];
		}
		$mailers['pretend'] = 
			$this->_('Pretender') . ' ' . 
			'[span.detail] ' . $this->_('(for testing, only pretends to send)') . '[/span]';
		foreach($mailers as $name => $label) {
			if($name && $name === $this->promailer->defaultMailer) {
				$label .= " [span.detail] " . $this->_('(recommended)') . " [/span]";
			}
			if($this->promailer->forceMailer) {
				if($name != $this->promailer->forceMailer) continue;
				$f->addOption('', $label);
			} else {
				$f->addOption($name, $label);
			}
		}
		if($this->promailer->forceMailer) {
			$f->description = $this->_('The sending method has been limited to the one shown below in the ProMailer module settings.');
			$message['mailer'] = '';
		} else if($this->promailer->defaultMailer && isset($mailers[$this->promailer->defaultMailer])) {
			if(empty($message['mailer'])) {
				$message['mailer'] = $this->promailer->defaultMailer;
			}
		} else if(empty($message['mailer'])) {
			$message['mailer'] = wireMail()->className();
		}
		if(!$this->promailer->forceMailer && $this->wire('user')->isSuperuser()) {
			$f->notes = sprintf(
				$this->_('For more sending options, you can install [WireMail modules](%s).'), 
				'https://modules.processwire.com/categories/email/'
			);
		}
		$f->attr('value', $message['mailer']);
		$f->columnWidth = 50;
		$mailerSelect = $f;
		$fieldset->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'sendType');
		$f->label = $this->_('Send type');
		$f->description = $this->_('Watch the live sending from your browser or have it send in the background.');
		$f->addOption('live', $this->_('Live [span.detail] (observe in your browser while it triggers sending) [/span]'));
		$f->addOption('queue', $this->_('Background [span.detail] (send in background, observing optional) [/span]'));
		if(ProcessProMailer::demo) {
			$f->addOption('all', $this->_('All at once [span.detail] (recommended only if your mailer suggests it) [/span]'));
		}
		$f->attr('value', $message['flags'] & ProMailer::messageFlagsQueueSend ? 'queue' : 'live');
		$f->columnWidth = 50;
		if($this->promailer->forceMailer) {
			$fieldset->insertBefore($f, $mailerSelect); 
		} else {
			$fieldset->add($f);
		}
	
		/** @var InputfieldRadios $f */
		/* COMING SOON 
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'sendFilter');
		$f->label = $this->_('Filtered send');
		$f->description = $this->_('Send only to subscribers matching your filter(s).'); 
		$f->collapsed = Inputfield::collapsedBlank;
		$fieldset->add($f);
		*/

		/** @var InputfieldEmail $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'subscriber_id');
		$f->label = $this->_('Start with subscriber');
		$f->description =
			$this->_('This is the ID or email address of the next subscriber that will receive this message.') . ' ' .
			$this->_('Lists are sent to in order of subscriber ID (lowest to highest).') . ' ' .
			$this->_('You may optionally adjust this to change the position of the send, or set as 0 (or blank) to start from the beginning of the list.');
		if($message['subscriber_id'] > 0 && $list) {
			$f->attr('value', $message['subscriber_id']);
			$subscriber = $this->promailer->subscribers->getById($message['subscriber_id'], $list);
			if($subscriber && $subscriber['id']) {
				$f->notes = sprintf($this->_('Subscriber ID %d is %s'), $subscriber['id'], $subscriber['email']);
				$f->appendMarkup = $subscriber->gravatar();
			}
		} else {
			$f->collapsed = Inputfield::collapsedYes;
		}
		$fieldset->add($f);

		/** @var InputfieldRadios $f */
		/*
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'sendNewest');
		$f->label = $this->_('Send order');
		$f->addOption(0, $this->_('Oldest subscribers first'));
		$f->addOption(1, $this->_('Newest subscribers first'));
		$f->columnWidth = 50;
		$f->attr('value', $message['flags'] & ProMailer::messageFlagsNewestFirst ? 1 : 0);
		$fieldset->add($f);
		*/

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'title');
		$f->label = $this->_('Message title (for admin)');
		$f->description = $this->_('This is only used to identify the message here in the admin.');
		$f->attr('value', $message['title']);
		$f->required = true;
		$f->collapsed = Inputfield::collapsedYes;
		$fieldset->add($f);

		/** @var InputfieldSubmit $f */
		$f = $modules->get('InputfieldSubmit');
		$f->attr('name', 'submit_save_message');
		$f->showInHeader(true);
		$f->value = $this->_('Save');
		$f->icon = 'save';
		$form->add($f);

		if($message['list_id'] && $message['from_email'] && $message['subject']) {
			/** @var InputfieldSubmit $f */
			$f = $modules->get('InputfieldSubmit');
			$f->attr('name', 'submit_send_message');
			$f->showInHeader(true);
			$f->setSecondary(true);
			if($message['subscriber_id']) {
				$f->value = $this->_('Continue sending');
			} else {
				$f->value = $this->_('Start sending');
			}
			$f->icon = 'send';
			$form->add($f);
		}

		return $form;
	}
}