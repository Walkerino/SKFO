<?php namespace ProcessWire;

class ProcessProMailerSubscribers extends ProcessProMailerTypeManager {
	
	/**
	 * View subscriber list
	 *
	 * Render subscribers in a list specified by list_id URL param
	 *
	 * @return string
	 * @throws WireException
	 *
	 */
	public function execute() {

		$input = $this->wire()->input;
		$listId = (int) $input->get('list_id');
		if(!$listId) $this->wire()->session->location('../lists/');
		$list = $this->promailer->lists->get($listId);
		if(!$list) throw new WireException('Unknown list');

		$this->process->breadcrumb('../', $this->label('promailer'));
		$this->process->breadcrumb('../lists/', $this->label('lists'));
		$this->process->breadcrumb("./?list_id=$listId", "List ID $listId");
		$this->process->headline("$list[title]");
		
		if($list->type === ProMailer::listTypePages) {
			return $this->executePages($list);
		} else {
			return $this->executeSubscribers($list);
		}
	}

	/**
	 * Execute when list type is ProMailer subscribers
	 * 
	 * @param ProMailerList $list
	 * @return string
	 * 
	 */
	protected function executeSubscribers(ProMailerList $list) {
	
		$input = $this->wire()->input;
		$sanitizer = $this->wire()->sanitizer;
		$modules = $this->wire()->modules;
		$session = $this->wire()->session;
		$config = $this->wire()->config;
		
		$ajax = $config->ajax;
		$limit = 100;
		
		if($input->get('export')) {
			$this->promailer->subscribers->exportCSV($list->id);
		}

		$find = $input->get('find_str');
		if($find === null) $find = $input->post('find_str');
	
		$find = (string) $find;
		$hasFind = strlen($find) > 0;
		
		if($hasFind) {
			$find = $sanitizer->text($find);
			$hasFind = strlen($find) > 0;
			if($hasFind) $input->whitelist('find_str', $find);
		}

		$form = $this->buildSubscribersForm($list);
		if($hasFind) $form->action .= "&find_str=" . urlencode($find);

		$sorts = $this->promailer->subscribers->getAllowedSorts();
		$sortDefault = $this->promailer->subscribers->getDefaultSort();
		$sort = (string) $input->get->name('sort');
		$sort = strlen($sort) && isset($sorts[$sort]) ? $sorts[$sort]['name'] : '';

		if($sort) {
			$input->whitelist('sort', $sort);
			$form->action .= "&sort=$sort";
		}

		// delete found subscribers?
		$deleteFound = !$ajax && $hasFind && $input->post('remove_subscribers') === 'REMOVE-FOUND';
		
		$findOptions = array(
			'list' => $list,
			'limit' => $limit,
			'partial' => true,
			'sort' => $sort ? $sort : $sortDefault,
			'delete' => $deleteFound,
		);
		
		if(strpos($find, '=') !== false) {
			$findOptions['custom'] = $find;
		} else {
			$findOptions['email'] = $find;
		}

		// pull in subscribers for list
		$subscribers = $this->promailer->subscribers->find($findOptions);

		if($deleteFound && is_int($subscribers)) {
			$this->message(sprintf($this->_('Deleted %d found subscriber(s)'), $subscribers));
			$session->redirect("./?list_id=$list->id");
		} else if($hasFind && !count($subscribers)) {
			if($ajax) {
				// ok
			} else {
				if(!$deleteFound) $this->warning(sprintf($this->_('No subscribers found matching “%s”'), $find));
				$session->location("./?list_id=$list->id");
			}
		}

		if(count($subscribers) || $ajax)  {
			$input->whitelist('list_id', $list->id);
			$form->add($this->renderSubscribers($subscribers));
		}

		if(!$ajax) {
			/** @var InputfieldSubmit $f */
			$f = $modules->get('InputfieldSubmit');
			$f->attr('name', 'submit_subscribers');
			$f->showInHeader(true);
			$form->add($f);

			if($input->post('submit_subscribers')) {
				$this->processSubscribers($form, $list->id);
			}
		}

		return $form->render();
	}

	/**
	 * Render subscribers for executeSubscribers() method
	 *
	 * @param ProMailerSubscribersArray $subscribers
	 * @return InputfieldMarkup
	 *
	 */
	protected function renderSubscribers(ProMailerSubscribersArray $subscribers) {

		$input = $this->wire()->input;
		$modules = $this->wire()->modules;
		$sanitizer = $this->wire()->sanitizer;

		$options = $subscribers->findOptions();
		/** @var ProMailerList $list */
		$list = $options['list'];
		$sorts = $this->promailer->subscribers->getAllowedSorts();
		$sort = $options['sort'];
		$dateFormat = 'Y/m/d H:i';

		/** @var InputfieldMarkup $markup */
		$markup = $modules->get('InputfieldMarkup');
		$markup->icon = 'user-circle-o';
		$markup->attr('id', 'promailer-subscribers-markup');

		/** @var InputfieldCheckbox $checkbox */
		/*
		$checkbox = $modules->get('InputfieldCheckbox');
		$checkbox->attr('name', 'removes[]');
		$checkbox->attr('value', 'all');
		$checkbox->label = ' ';
		$checkbox->entityEncodeLabel = false;
		$checkbox->label = '&nbsp;' . wireIconMarkup('trash-o');
		*/

		$icons = array(
			'confirmed' => '✓',
			'pending' => '',
			'resend' => '↻',
			'delete' => '✗',
		);
		/** @var InputfieldRadios $statusSelect */
		$statusSelect = $modules->get('InputfieldSelect');
		$statusSelect->attr('name', 'status__id');
		$statusSelect->required = true;
		$statusSelect->addOption(0, $this->_('Pending') . ' ' . $icons['pending']);
		$statusSelect->addOption(-1, $this->_('Resend') . ' ' . $icons['resend']);
		$statusSelect->addOption(1, $this->_('Confirmed') . ' ' . $icons['confirmed']);
		$statusSelect->addOption(-2, $this->_('DELETE') . ' ' . $icons['delete']);

		/** @var InputfieldSelect $sortSelect */
		$sortSelect = $modules->get('InputfieldSelect');
		$sortSelect->attr('name', 'sort');
		$sortSelect->attr('id', 'promailer-subscribers-sort');
		$sortSelect->label = $this->_('Sort');
		$sortSelect->required = true;
		$sortSelect->addClass('uk-form-small');
		foreach($sorts as $sortName => $sortInfo) {
			$sortSelect->addOption($sortName, sprintf($this->_('Sort: %s'), $sortInfo['label']));
		}
		$sortSelect->attr('value', $sort);

		/** @var MarkupAdminDataTable $table */
		$table = $modules->get('MarkupAdminDataTable');
		$table->setEncodeEntities(false);
		$table->setSortable(false);
		$table->setID('promailer-subscribers-table');

		$headerRow = array(
			'id' => $this->_x('ID', 'th-number'),
			'email' => $this->_x('Email', 'th-email'),
			'created' => $this->_x('Added', 'th-date'),
			'status' => $this->_x('Status', 'th-select'),
		);

		if($sort === '-confirmed') $headerRow['created'] = $this->_x('Confirmed', 'th-date');
		if($sort === '-num_bounce') $headerRow['num_bounce'] = $this->_x('Bounce', 'th-quantity');
		if($sort === '-num_sent') $headerRow['num_sent'] = $this->_x('Sent', 'th-quantity');

		foreach($list->fields as $fieldInfo) {
			$label = $sanitizer->entities(empty($fieldInfo['label']) ? $fieldInfo['name'] : $fieldInfo['label']);
			$headerRow[] = $label;
		}

		$table->headerRow(array_values($headerRow));

		$useGravatar = $this->promailer->useGravatar;

		foreach($subscribers as $subscriber) {
			/** @var ProMailerSubscriber $subscriber */

			$id = $subscriber['id'];
			$email = $sanitizer->entities($subscriber['email']);
			$gravatar = $useGravatar ? $subscriber->gravatar(40) . ' ' : '';
			// $checkbox->attr('value', $subscriber['email']);

			$status = clone $statusSelect;
			$status->attr('name', "status__$id");
			if(!$subscriber->allowResend()) $status->removeOption(-1);
			if($subscriber->confirmed > 1 && method_exists($status, 'optionLabel')) {
				// additional check icon indicates user confirmed rather than manual confirmed
				$status->optionLabel(1, $status->optionLabel(1) . "$icons[confirmed]"); 
			}
		
			$status->attr('value', $subscriber->confirmed ? 1 : 0); 
			$dateAdded = date($dateFormat, $subscriber->created); 
			$date = wireRelativeTimeStr($subscriber['created']);
			
			if($subscriber->confirmed > 1) {
				$dateConfirmed = date($dateFormat, $subscriber->confirmed);
				$dateTip = sprintf($this->_('Added on %1$s and double opt-in via confirmation email on %2$s'), $dateAdded, $dateConfirmed);
				if($sort === '-confirmed') $date = $this->_('Double opt-in by user') . "<br />" . date($dateFormat, $subscriber->confirmed);
			} else if($subscriber->confirmed) {
				$dateTip = sprintf($this->_('Added on %s and confirmed manually in admin'), $dateAdded);
				if($sort === '-confirmed') $date = $this->_('Manually confirmed') . "<br />$dateAdded";
			} else {
				$dateTip = sprintf($this->_('Added on %s and waiting for opt-on confirmation by email'), $dateAdded);
				if($sort === '-confirmed') $date = $this->_('Pending since') . "<br />$dateAdded"; 
			}

			$row = array(
				"$gravatar<span class='promailer-id'>$id</span>",
				"<input class='promailer-field' type='email' name='email__$id' value='$email' required='required'>",
				"<span class='pw-tooltip' title='$dateTip'>$date</span>",
				$status->render(),
			);

			if($sort === '-num_bounce') $row[] = $subscriber->num_bounce;
			if($sort === '-num_sent') $row[] = $subscriber->num_sent;

			foreach($list->fields as $fieldInfo) {
				// $row[] = $this->renderSubscriberCustomFieldInput($subscriber, $fieldInfo);
				$inputValue = $subscriber->getCustom($fieldInfo['name']);
				if($inputValue) $fieldInfo['value'] = $inputValue;
				$inputName = $fieldInfo['name'] . "__$subscriber->id";
				$row[] = ProMailerLists::listFields()->fieldArrayToInput($fieldInfo, $inputName, true);
			}

			// $row[] = $checkbox->render();
			$table->row($row);
		}

		$headline = $subscribers->getPaginationString($this->label('subscribers'));
		$pager = $subscribers->renderPager();
		$meta = '';
		if($input->pageNum() > 1) {
			$meta .= sprintf($this->_('page %d'), $input->pageNum()) . ' ';
		}
		if($options['email']) {
			$meta .= sprintf($this->_('email matching “%s”'), $sanitizer->entities($options['email'])) . ' ';
		} else if($options['custom']) {
			$meta .= sprintf($this->_('custom field containing “%s”'), $sanitizer->entities(ltrim($options['custom'], '=!%*'))) . ' ';
		} else if(!empty($options['filters'])) {
			$meta .= $this->_('custom field matching selector');
		}
		$meta .= "<span id='promailer-spinner'>" . wireIconMarkup('spinner', 'fa-spin')  . "</span>";

		$markup->attr('value',
			"<div id='promailer-subscribers-list' data-list='$list->id'>" .
				($pager ? '' : $sortSelect->render()) .
				"<h2>$headline <small class='ui-priority-secondary'>$meta</small></h2>" .
				($pager ? $sortSelect->render() : '') .
				$pager . $table->render() . $pager .
				"<input type='hidden' name='render_time' value='" . time() . "' />" . 
			"</div>"
		);

		return $markup;
	}

	/** 
	 * Build start of form for subscribers or pages lists
	 * 
	 * @param ProMailerList $list
	 * @return InputfieldForm
	 * 
	 */
	protected function buildForm(ProMailerList $list) {
		
		$modules = $this->wire()->modules;
		$input = $this->wire()->input;

		/** @var InputfieldForm $form */
		$form = $modules->get('InputfieldForm');
		$form->attr('id', 'promailer-subscribers-form');
		$form->action = './';
		$form->addClass('InputfieldFormConfirm');
		if($input->pageNum() > 1) $form->action .= 'page' . $input->pageNum();
		$form->action .= "?list_id=$list->id";

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->attr('title', $this->_('Settings'));
		$fieldset->attr('name', 'fieldset_settings');
		$fieldset->label = $this->_('Subscriber list settings');
		$fieldset->icon = 'gear';
		$form->add($fieldset);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'rename_list');
		$f->label = $this->_('List title');
		$f->attr('value', $list['title']);
		$f->icon = 'info-circle';
		$f->collapsed = Inputfield::collapsedYes;
		$fieldset->add($f);

		return $form;
	}

	/**
	 * Build subscribers form
	 *
	 * @param ProMailerList $list
	 * @return InputfieldForm
	 *
	 */
	protected function buildSubscribersForm(ProMailerList $list) {

		$modules = $this->wire()->modules;
		$input = $this->wire()->input;

		$form = $this->buildForm($list);
		if($this->wire()->config->ajax) return $form;

		$modules->get('JqueryWireTabs');
		$hasSubscribers = $list->numSubscribers() > 0;
		
		$fieldNames = array();
		foreach($list->fieldsArray() as $fieldInfo) $fieldNames[] = $fieldInfo['name'];
		$hasCustomFields = count($fieldNames) > 0;

		if($hasSubscribers) {
			/** @var InputfieldText $f */
			$findLabel = $this->_('Find');
			$wrap = new InputfieldWrapper();
			$wrap->addClass('ProMailerTab');
			$wrap->attr('title', $findLabel);
			$form->prepend($wrap);
			$find = (string) $input->whitelist('find_str');
			$f = $modules->get('InputfieldText');
			$f->attr('name', 'find_str');
			$f->label = $findLabel;
			$f->description = 
				$this->_('Enter partial or full email address to find matching subscribers. The subscriber list will update as you type.');
			if($hasCustomFields) $f->description .= ' ' . 
				$this->_('To find values in custom fields, enter “field=value” for exact match or “field*=value” for partial match.') . ' ' . 
				$this->_('To search all custom fields specify just “=value”.'); 
			$f->attr('value', $find);
			$f->skipLabel = Inputfield::skipLabelHeader;
			$f->icon = 'search';
			$wrap->add($f);
		}

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $form->getChildByName('fieldset_settings');
		$fieldset->description = $this->_('Control list title, whether it is open for new subscribers, and define any custom subscriber fields.');
		$fieldset->addClass('ProMailerTab', 'wrapClass');

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'list_closed');
		$f->label = $this->_('List status');
		if($list->closed) {
			$statusLabel = $this->_('closed');
		} else {
			$statusLabel = $this->_('open');
		}
		$f->label .= " ($statusLabel)";
		$f->addOption(0, $this->_('Open for new subscribers'));
		$f->addOption(1, $this->_('Closed (subscribe disabled)'));
		$f->optionColumns = 1;
		$f->attr('value', $list['closed'] ? 1 : 0);
		$f->icon = 'eye-slash';
		$f->collapsed = Inputfield::collapsedYes;
		$fieldset->add($f);

		/** @var InputfieldTextarea $f */
		$f = $modules->get('InputfieldTextarea');
		$f->attr('name', 'custom_fields');
		$f->label = $this->_('Define custom fields');
		if(count($fieldNames)) $f->label .= ' (' . implode(', ', $fieldNames) . ')';
		$f->description =
			$this->_('Enter one custom field per line in the format `field_name:type` where “type” can be any sanitizer method name (i.e. text, textarea, int, etc.). For example: `first_name:text` or `age:int`, and so on.') . ' ' .
			$this->_('To make a field required in your subscribe form, prepend an asterisk to the “name, i.e. `*first_name:text`, otherwise the field is considered optional.') . ' ' . 
			sprintf($this->_('See the [ProMailer custom fields reference](%s) for more details on how to use custom fields.'), 'https://processwire.com/store/pro-mailer/manual/#custom-fields-reference');
		$f->notes =
			$this->_('After defining them here, you can add these fields to your subscribe form or import them from a subscribers CSV file.') . ' ' . 
			$this->_('Any fields you add also display in the subscribers list. These fields can also be used for output in your emails by using placeholders like `{field_name}`.');
		$f->attr('value', $list->fieldsString());
		$f->icon = 'table';
		$f->collapsed = Inputfield::collapsedYes;
		$fieldset->add($f);

		$this->addUnsubEmailField($fieldset, $list);
			
		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = $this->_('Subscriber management tools');
		$fieldset->description = $this->_('Add, remove, import or export subscribers.');
		$fieldset->attr('title', $this->_('Tools'));
		$fieldset->icon = 'wrench';
		$fieldset->addClass('ProMailerTab');
		if(!$hasSubscribers) $fieldset->set('themeOffset', 1);
		$form->add($fieldset);

		/** @var InputfieldTextarea $f */
		$f = $modules->get('InputfieldTextarea');
		$f->attr('name', 'add_subscribers');
		$f->label = $this->_('Add new subscribers by email');
		$f->description = $this->_('Enter email address of new subscribers (one per line)');
		if($hasSubscribers) $f->collapsed = Inputfield::collapsedYes;
		$f->icon = 'plus-circle';
		$fieldset->add($f);

		if($hasSubscribers) {
			/** @var InputfieldTextarea $f */
			$f = $modules->get('InputfieldTextarea');
			$f->attr('name', 'remove_subscribers');
			$f->label = $this->_('Remove subscribers by email or in bulk');
			$f->description =
				$this->_('Enter email address of subscribers to remove (one per line) or type in a bulk action (see below).');
			$f->notes = '**' . $this->_('BULK ACTIONS:') . '** ' .
				$this->_('To remove all subscribers from this list type `REMOVE-ALL`.') . ' ' .
				$this->_('To remove filtered subscribers matching the current “find” selection, type `REMOVE-FOUND`.') . ' ' . 
				$this->_('We want you to type these actions in uppercase as confirmation, since they are destructive actions.');
				
			
			$f->collapsed = Inputfield::collapsedYes;
			$f->icon = 'minus-circle';
			$fieldset->add($f);
		}

		/** @var InputfieldFile $f */
		$f = $modules->get("InputfieldFile");
		$f->name = 'csv_file';
		$f->label = $this->_('Import subscribers from CSV file');
		$f->extensions = 'csv txt';
		$f->maxFiles = 1;
		$f->overwrite = true;
		$f->description =
			'• ' . $this->_('File must contain one email address per line, as well as any custom fields.') . "\n" .
			'• ' . $this->_('The first line (header) in the CSV should define the columns.') . "\n" .
			'• ' . $this->_('Columns having headers that match your custom field names will be imported.') . "\n" . 
			'• ' . $this->_('The email column must have the header named “email”, unless it is the only column.') . "\n" .
			'• ' . $this->_('If there is only one column per row, it is assumed to be email address (no header needed).') . "\n" . 
			'• ' . $this->_('The CSV file is expected to have UTF-8 encoding.');
		$f->collapsed = Inputfield::collapsedYes;
		$f->icon = 'file-excel-o';
		$delims = array(
			'auto' => $this->_('Auto-detect'),
			',' => $this->_('Comma'),
			';' => $this->_('Semicolon'),
			'tab' => $this->_('Tab'),
		);
		$f->appendMarkup = "<p>" . $this->_('CSV delimiter:') . " &nbsp; <span class='description'>";
		foreach($delims as $delim => $label) {
			$checked = $delim === 'auto' ? 'checked' : '';
			$f->appendMarkup .= "<label><input type='radio' name='delimiter' value='$delim' $checked /> $label</label> &nbsp; ";
		}
		$f->appendMarkup .= "</span></p>" . 
			"<p class='notes'>" .
			$this->_('If you will be using custom fields, please define them (on the Settings tab) before doing this import.') . 
			"</p>";
		$fieldset->add($f);

		/** @var InputfieldCheckboxes $f */
		$f = $modules->get('InputfieldCheckboxes');
		$f->attr('name', 'import_list');
		$f->label = $this->_('Import subscribers from other ProMailer list(s)');
		$f->description = $this->_('This will import all subscribers from the selected list(s) into this one (except for duplicates).');
		$f->collapsed = Inputfield::collapsedYes;
		$f->icon = 'users';
		foreach($this->promailer->lists->getAll() as $importList) {
			if($importList->id === $list->id) continue;
			$f->addOption($importList->id, $importList->title . ' ' . 
				'[span.detail] ' . $this->process->subscribersLabel($importList) . ' [/span]'
			);
		}
		$fieldset->add($f);

		/** @var InputfieldFile $f */
		if($hasSubscribers) {
			$f = $modules->get("InputfieldMarkup");
			$f->name = 'export_csv';
			$f->label = $this->_('Export subscribers to CSV file');
			$f->icon = 'file-excel-o';
			$f->collapsed = Inputfield::collapsedYes;
			$f->description = $this->_('This button starts a download of all subscribers in this list in CSV file.');
			/** @var InputfieldButton $btn */
			$btn = $modules->get('InputfieldButton');
			$btn->value = $this->_('Download CSV');
			$btn->icon = 'file-excel-o';
			$btn->href = "./?list_id=$list->id&export=1";
			$f->value = "<p>" . $btn->render() . "</p>";
			$fieldset->add($f);
		}

		return $form;
	}

	/**
	 * @param InputfieldWrapper $fieldset
	 * @param ProMailerList $list
	 * 
	 */
	protected function addUnsubEmailField(InputfieldWrapper $fieldset, ProMailerList $list) {
		if(version_compare($this->wire()->config->version, '3.0.132', '<')) return;
		if(!in_array($this->promailer->useListUnsub, array(ProMailer::useListUnsubEmail, ProMailer::useListUnsubBoth))) {
			return;
		}
		/** @var InputfieldEmail $f */
		$f = $this->wire()->modules->get('InputfieldEmail');
		$f->attr('name', 'unsub_email');
		$f->label = $this->_('Email address for 1-click unsubscribe requests');
		if($list->unsub_email) $f->label .= " ($list->unsub_email)";
		$f->description = sprintf($this->_('When present, it will override the default: %s'), $this->promailer->listUnsubEmail); 
		$f->attr('value', (string) $list->unsub_email);
		$f->icon = 'eraser';
		$f->collapsed = Inputfield::collapsedYes;
		$fieldset->add($f);
	}

	/**
	 * Process actions for subscribers
	 *
	 * @param InputfieldForm $form
	 * @param int $listId
	 * @throws WireException
	 *
	 */
	protected function processSubscribers(InputfieldForm $form, $listId) {

		$input = $this->wire()->input;
		$sanitizer = $this->wire()->sanitizer;

		$list = $this->promailer->lists->get($listId);
		if(!$list) throw new WireException($this->_('Unknown list'));
		$listChanged = false;

		// Process all the subscriber rows as well as the form
		$this->processSubscribersRows($list);
		$form->processInput($input->post);

		// Remove checked subscribers
		/*
		$removes = $input->post('removes');
		if(is_array($removes) && count($removes)) {
			$this->promailer->subscribers->saveLogDisabled(true);
			foreach($removes as $email) {
				if($this->promailer->subscribers->unsubscribe($email, $listId)) {
					$this->message("Removed subscriber: $email");
				}
			}
			$this->promailer->subscribers->saveLogDisabled(false);
		}
		*/

		// Add subscribers by email
		$emails = $form->getChildByName('add_subscribers');
		$emails = $emails ? $emails->val() : '';
		if(strlen($emails)) {
			$this->promailer->subscribers->saveLogDisabled(true);
			foreach(explode("\n", $emails) as $email) {
				$email = $sanitizer->email($email);
				if($email) {
					$confirmed = true;
					$result = $this->promailer->subscribers->add($email, $list, $confirmed);
					if($result && is_int($result)) {
						$this->warning(sprintf($this->_('Subcriber already in list: %s'), $email));
					} else if($result) {
						$this->message(sprintf($this->_('Added subscriber: %s'), $email));
					}
				}
			}
			$this->promailer->subscribers->saveLogDisabled(false);
		}

		// Remove subscribers by email
		$emails = $form->getChildByName('remove_subscribers');
		$emails = $emails ? $emails->val() : '';
		if($emails === 'REMOVE-ALL') {
			$qty = $this->promailer->subscribers->unsubscribeAllFromList($list);
			$this->message(sprintf($this->_('Removed %d subscribers from list: %s'), $qty, $list->title));
		} else if($emails === 'REMOVE-FOUND') {
			// this is handled in the executeSubscribers() method to the subscribers.find() call
		} else if(strlen($emails)) {
			$this->promailer->subscribers->saveLogDisabled(true);
			foreach(explode("\n", $emails) as $email) {
				$email = $sanitizer->email($email);
				if($email && $this->promailer->subscribers->unsubscribe($email, $list)) {
					$this->message(sprintf($this->_('Removed subscriber: %s'), $email));
				}
			}
			$this->promailer->subscribers->saveLogDisabled(false);
		}

		// Rename list
		$title = $form->getChildByName('rename_list')->val();
		if(strlen($title) && $title != $list['title']) {
			$list['title'] = $title;
			$listChanged = true;
			$this->message(sprintf($this->_('Renamed list to: %s'), $title));
		}

		// List status (open/closed)
		$closed = ((int) $form->getChildByName('list_closed')->val()) ? true : false;
		if($closed !== $list['closed']) {
			$list['closed'] = $closed;
			$listChanged = true;
			if($closed) {
				$this->message($this->_('Changed list to closed'));
			} else {
				$this->message($this->_('Changed list to open'));
			}
		}

		// Custom field definitions
		$customFields = $form->getChildByName('custom_fields')->val();
		if($customFields !== $list->fieldsString()) {
			$list->set('fields', $customFields);
			$listChanged = true;
			$this->message($this->_('Updated list custom fields'));
		}

		/*
		// Unsubscribe email for “list-unsubscribe” headers (3.0.132+)
		$unsubEmail = $form->getChildByName('unsub_email')->val();
		if($unsubEmail && $unsubEmail !== $list->unsub_email) {
			$list->set('unsub_email', $unsubEmail);
			$listChanged = true;
			$this->message("Updated use of “list-unsubscribe” email headers");
		}
		*/
		
		if($listChanged) $this->promailer->lists->save($list);

		// Import subscribers from CSV file
		$pagefiles = $form->getChildByName('csv_file')->value;
		/** @var Pagefiles $pagefiles */
		if(count($pagefiles)) {
			/** @var Pagefile $pagefile */
			$pagefile = $pagefiles->first();
			$delimiter = substr($input->post('delimiter'), 0, 1);
			$result = $this->promailer->subscribers->importCSV($pagefile->filename(), $listId, array('delimiter' => $delimiter));
			if($result) $this->message($result);
			$this->wire()->files->unlink($pagefile->filename());
		}

		// Import subscribers from another list
		$f = $form->getChildByName('import_list');
		$importLists = $f->attr('value');
		if(count($importLists)) {
			$messages = array();
			foreach($importLists as $importListId) {
				$importList = $this->promailer->lists->get((int) $importListId);
				if(!$importList) continue;
				$result = $this->promailer->subscribers->copyAll($importList, $list);
				$message = sprintf($this->_('Imported %d subscriber(s) from list: %s'), (int) $result[0], $importList->title);
				if($result[1] > 0) $message .= ' ' . sprintf($this->_('(%d duplicate emails not imported)'), (int) $result[1]);
				$messages[] = $message;
			}
			if(count($messages)) {
				$this->message(
					'<strong>' . $this->_('Import results:') . '</strong><br />' . 
					implode('<br />', $messages), 
					Notice::allowMarkup);
			}
		}

		// Build redirect URL
		$redirect = "./";
		if($input->pageNum() > 1) $redirect .= 'page' . $input->pageNum();
		$redirect .= "?list_id=$listId";
		
		$find = (string) $input->whitelist('find_str');
		if(strlen($find)) $redirect .= "&find_str=" . urlencode($find);
		$sort = (string) $input->whitelist('sort');
		if($sort) $redirect .= "&sort=$sort";
	
		// Redirect after process
		$this->wire()->session->location($redirect);
	}

	/**
	 * Process the custom field inputs for each subscriber row
	 *
	 * @param ProMailerList $list
	 *
	 */
	protected function processSubscribersRows(ProMailerList $list) {

		$sanitizer = $this->wire()->sanitizer;
		$input = $this->wire()->input;
		
		$subscribers = array();
		$saveSubscribers = array(); // save these subscribers
		$sendSubscribers = array(); // re-send confirmation emails to these subscribers
		$deleteSubscribers = array(); // delete these subscribers
		$confirmSubscribers = array();
		$renderTime = (int) $input->post('render_time');
		
		if(!$renderTime) $renderTime = time();

		// get all posted subscribers and look for changes to email or custom fields
		foreach($input->post() as $key => $value) {

			if(!strpos($key, '__')) continue;
			list($inputName, $subscriberId) = explode('__', $key, 2);

			$subscriberId = (int) $subscriberId;

			/** @var ProMailerSubscriber $subscriber */
			if(isset($subscribers[$subscriberId])) {
				$subscriber = $subscribers[$subscriberId];
			} else {
				$subscriber = $this->promailer->subscribers->get($subscriberId, $list);
			}
		
			if(!$subscriber) continue;
			$changed = false;

			if(strpos($key, 'email__') === 0) {
				$oldVal = $subscriber['email'];
				$newVal = $sanitizer->email($value);
				if($oldVal != $newVal) {
					$subscriber['email'] = $newVal;
					$subscriber->trackChange('email');
					$changed = true;
				}
			} else {
				foreach($list->fields as $fieldName => $fieldInfo) {
					if($fieldName !== $inputName) continue;
					if($fieldInfo['internal']) continue; // internal
					if($subscriber->setCustom($fieldName, $value)) $changed = true;
				}
			}

			if($changed) {
				$saveSubscribers[$subscriberId] = $subscriber;
			}

			$subscribers[$subscriberId] = $subscriber;
		}

		// identify subscriber status
		foreach($subscribers as $id => $subscriber) {
			/** @var ProMailerSubscriber $subscriber */
			$status = $input->post("status__$id");
			if($status === null) continue;
			$status = (int) $status;
			if($status === -1) {
				// re-send
				$sendSubscribers[] = $subscriber;
			} else if($status === -2) {
				// delete
				$deleteSubscribers[$id] = $subscriber;
				if(isset($saveSubscribers[$id])) unset($saveSubscribers[$id]); 
			} else if(($status === 1 && !$subscriber['confirmed']) || ($subscriber['confirmed'] && $status === 0)) {
				// update confirmed status
				if($subscriber->confirmed && $subscriber->confirmed >= $renderTime) {
					$this->warning(sprintf($this->_('Skipped %s status change because they just now completed opt-in themselves.'), $subscriber->email)); 
				} else {
					$subscriber['confirmed'] = ($status ? 1 : 0);
					$subscriber->trackChange('confirmed');
					$saveSubscribers[$id] = $subscriber;
					if($status) $confirmSubscribers[$id] = $subscriber;
				}
			}
		}

		foreach($saveSubscribers as $subscriber) {
			/** @var ProMailerSubscriber $subscriber */
			$this->message(
				sprintf($this->_('Updated subscriber: %s'), $subscriber->email) . ' - ' . 
				implode(', ', $subscriber->getChanges())
			);
			if(isset($confirmSubscribers[$subscriber->id])) {
				// remove confirmation email data since no longer needed
				$subscriber->setConfData(array());
			}
			$this->promailer->subscribers->save($subscriber);
		}
		
		foreach($sendSubscribers as $subscriber) {
			/** @var ProMailerSubscriber $subscriber */
			$sent = $this->promailer->subscribers->resendConfirmEmail($subscriber);
			if($sent) {
				$this->message(sprintf($this->_('Re-sent confirmation email to: %s'), $subscriber->email));
			} else {
				$this->warning(sprintf($this->_('Failed to resend confirmation email to: %s'), $subscriber->email));
			}
		}
	
		foreach($deleteSubscribers as $subscriber) {
			/** @var ProMailerSubscriber $subscriber */
			if($this->promailer->subscribers->delete($subscriber)) {
				$this->message(sprintf($this->_('Deleted subscriber: %s'), $subscriber->email));
			} else {
				$this->warning(sprintf($this->_('Error deleting subscriber: %s'), $subscriber->email)); 
			}
		}
	
		/*
		foreach($confirmSubscribers as $subscriber) {
			// subscribers where confirmation status changed
		}
		*/
	}

	/**
	 * Render and process form for pages/users-type lists using InputfieldSelector
	 *
	 * @param ProMailerList $list
	 * @return string
	 *
	 */
	protected function executePages(ProMailerList $list) {

		$input = $this->wire()->input;
		$modules = $this->wire()->modules;
		$templates = $this->wire()->templates;

		$form = $this->buildForm($list);
		$selector = empty($list['selector']) ? 'template=user, email!=""' : $list['selector'];

		// determine what columns will be shown in InputfieldSelector preview
		$previewColumns = array();
		$previewTemplate = '';

		foreach(new Selectors($selector) as $s) {
			foreach($s->fields() as $fieldName) {
				if($fieldName == 'template') {
					$previewTemplate = $templates->get($s->value());
					// no need to show template column if only one possible template
					if(count($s->values()) == 1) continue;
				}
				$previewColumns[$fieldName] = $fieldName;
			}
		}

		if($previewTemplate && $previewTemplate->fieldgroup->hasField('title')) {
			if(!isset($previewColumns['title'])) array_unshift($previewColumns, 'title');
			if($previewTemplate == 'user' && !isset($previewColumns['name'])) array_unshift($previewColumns, 'name');
		} else {
			if(!isset($previewColumns['name'])) array_unshift($previewColumns, 'name');
		}

		/** @var InputfieldSelector $f */
		$f = $modules->get('InputfieldSelector');
		$f->attr('name', 'selector');
		$f->label = $this->_('Match users or pages that should be part of this subscriber list');
		$f->icon = 'user-circle-o';
		$f->description =
			$this->_('At least one of your selections below should be for an email field that “is not empty.”') . ' ' .
			$this->_('This field will be used as the email address to send to for each matched page/user.');
		$f->set('initValue', $this->promailer->subscribers->pageSelector($list, array('getInitValue' => true)));
		$f->set('allowSystemTemplates', true);
		$f->set('allowSystemCustomFields', true);
		$f->set('showFieldLabels', 1);
		$f->set('previewColumns', $previewColumns);
		$f->attr('value', $selector);
		$form->prepend($f);

		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect');
		$f->attr('name', 'unsub_field');
		$f->label = $this->_('Field that indicates user has unsubscribed from this list');
		$f->required = true;
		$f->description =
			$this->_('By default, users are considered subscribed to this list based on your selection above.') . ' ' .
			$this->_('Select a checkbox field that will be used to indicate the user has unsubscribed from this list.') . ' ' .
			$this->_('ProMailer will update this field if a user clicks the unsubscribe link in an email.');
		if(empty($list['unsub_field'])) $list['unsub_field'] = ProMailer::unsubscribeFieldName;
		$this->promailer->forms->getUnsubscribeField($list, true, false);
		foreach($this->wire()->fields as $field) {
			if(!$field->type instanceof FieldtypeCheckbox) continue;
			$f->addOption($field->name, "$field->name ($field->label)");
		}
		$f->attr('value', $list['unsub_field']);

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $form->getChildByName('fieldset_settings');
		$fieldset->description = '';
		$fieldset->collapsed = Inputfield::collapsedYes;
		$fieldset->add($f);
		$this->addUnsubEmailField($fieldset, $list);

		/** @var InputfieldSubmit $f */
		$f = $modules->get('InputfieldSubmit');
		$f->attr('name', 'submit_subscribers_pages');
		$f->showInHeader(true);
		$form->add($f);

		if($input->post('submit_subscribers_pages')) {
			$form->processInput($input->post);
			$_list = clone $list;
			$changes = array();
			$fieldNames = array(
				'selector' => 'selector',
				'title' => 'rename_list',
				'unsub_field' => 'unsub_field',
				'unsub_email' => 'unsub_email'
			);
			foreach($fieldNames as $listFieldName => $formFieldName) {
				$f = $form->getChildByName($formFieldName);
				if(!$f) continue;
				$list[$listFieldName] = $f->attr('value');
				if($list[$listFieldName] !== $_list[$listFieldName]) $changes[] = $listFieldName;
			}
			/* the following commented code was replaced with the above code
			$list['selector'] = $form->getChildByName('selector')->attr('value');
			$list['title'] = $form->getChildByName('rename_list')->attr('value');
			$list['unsub_field'] = $form->getChildByName('unsub_field')->attr('value');
			$list['unsub_email'] = $form->getChildByName('unsub_email')->attr('value');
			if($list['selector'] !== $_list['selector']) $changes[] = 'selector';
			if($list['title'] !== $_list['title'] && !empty($list['title'])) $changes[] = 'title';
			if($list['unsub_field'] !== $_list['unsub_field']) $changes[] = 'unsub_field';
			if($list['unsub_email'] !== $_list['unsub_email']) $changes[] = 'unsub_email';
			*/
			$this->promailer->forms->getUnsubscribeField($list, true, true);
			if(count($changes)) {
				$this->message(sprintf($this->_('Updated list: %s'), implode(', ', $changes)));
				$this->promailer->lists->save($list);
			}
			$this->wire()->session->location("./?list_id=$list[id]");
		}

		return $form->render();
	}

}