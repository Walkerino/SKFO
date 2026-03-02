<?php namespace ProcessWire;

class ProcessProMailerMessages extends ProcessProMailerTypeManager {

	/**
	 * Render messages index
	 *
	 * @param bool $showActions
	 *
	 * @return string
	 *
	 */
	public function execute($showActions = true) {

		$url = $this->wire()->page->url;
		$modules = $this->wire()->modules;
		
		$out = '';

		if($showActions) {
			$this->process->breadcrumb($url, $this->label('promailer'));
			$this->process->headline($this->label('messages'));
		} else {
			$out .= "<h2>" . $this->label('messages') . "</h2>";
		}

		$messages = $this->promailer->messages->getAll();

		/** @var InputfieldForm $form */
		$form = $modules->get('InputfieldForm');
		$form->attr('action', $url . 'messages/');

		if(count($messages)) {
			/** @var MarkupAdminDataTable $table */
			$table = $modules->get('MarkupAdminDataTable');
			$table->headerRow(array(
				$this->_('title'),
				$this->_('list'),
				$this->_('sent'),
			));

			foreach($messages as $message) {
				$list = $message['list_id'] ? $this->promailer->lists->get($message['list_id']) : false;
				$table->row(array(
					$message['title'] => $url . "message/?message_id=$message[id]",
					$list ? $list['title'] : ' ',
					wireRelativeTimeStr($message['last_sent']),
				));
			}

			$out .= $table->render();
		}

		if($showActions) {
			/** @var InputfieldText $f */
			$f = $modules->get('InputfieldText');
			$f->attr('id+name', 'add_message');
			$f->label = $this->_('Add new message');
			$f->description = $this->_('Enter title of new message');
			if($this->wire('input')->get('add')) {
				$f->appendMarkup = '<script>$(document).ready(function() { $("#add_message").focus(); });</script>';
			} else if(count($messages)) {
				$f->collapsed = Inputfield::collapsedYes;
			}
			$f->icon = 'plus-circle';
			$form->add($f);

			if(count($messages)) {
				/** @var InputfieldSelect $f */
				$f = $modules->get('InputfieldSelect');
				$f->attr('name', 'remove_message');
				$f->label = $this->_('Delete message');
				$f->collapsed = Inputfield::collapsedYes;
				$f->icon = 'trash-o';
				foreach($messages as $message) {
					$f->addOption($message['id'], $message['title']);
				}
				$form->add($f);

				/** @var InputfieldCheckbox $f */
				$f = $modules->get('InputfieldCheckbox');
				$f->attr('name', 'remove_message_confirm');
				$f->label = $this->_('Are you sure you want to delete the message? (check box to confirm)');
				$f->showIf = 'remove_message>0';
				$form->add($f);
			}

			/** @var InputfieldSubmit $f */
			$f = $modules->get('InputfieldSubmit');
			$f->attr('name', 'submit_messages');
			$f->showInHeader(true);
			$form->add($f);

			if($this->wire()->input->post('submit_messages')) {
				$this->process($form, $messages);
			}
		}

		return $out . $form->render();
	}

	/**
	 * Process actions for messages
	 *
	 * @param InputfieldForm $form
	 * @param array $messages
	 *
	 */
	protected function process(InputfieldForm $form, array $messages) {

		$input = $this->wire()->input;
		$form->processInput($input->post);
		$redirect = '';

		$removeMessage = $form->getChildByName('remove_message');
		if($removeMessage && $removeMessage->val() && $input->post('remove_message_confirm')) {
			$removeMessage = $removeMessage->val();
			$message = isset($messages[$removeMessage]) ? $messages[$removeMessage] : null;
			if($message && $this->promailer->messages->remove($removeMessage)) {
				$this->message(sprintf($this->_('Removed message: %s'), $message['title']));
				$redirect = './';
			}
		}

		$addMessage = $form->getChildByName('add_message')->val();
		if($addMessage) {
			$dup = false;
			foreach($messages as $message) {
				if($message['title'] === $addMessage) $dup = true;
			}
			if($dup) {
				$this->error(sprintf($this->_('Message title already taken: %s'), $addMessage));
			} else {
				$message = $this->promailer->messages->add($addMessage);
				if($message) {
					$this->message(sprintf($this->_('Added message: %s'), $addMessage));
					$redirect = "../message/?message_id=$message[id]";
				} else {
					$this->error($this->_('Failed to add new message'));
				}
			}
		}

		if($redirect) {
			$this->wire()->session->location($redirect);
		}
	}
}