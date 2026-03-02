<?php namespace ProcessWire;

class ProcessProMailerLists extends ProcessProMailerTypeManager {

	/**
	 * Render available subscriber lists and allow for adding/removing lists
	 *
	 * @return string
	 * @throws WireException
	 * @throws WirePermissionException
	 *
	 */
	public function execute() {
		
		$page = $this->wire()->page;
		$modules = $this->wire()->modules;
		$datetime = $this->wire()->datetime;

		$url = $page->url;

		$this->process->breadcrumb($url, $this->label('promailer'));
		$this->process->headline($this->label('lists'));

		$lists = $this->promailer->lists->getAll();

		/** @var InputfieldForm $form */
		$form = $modules->get('InputfieldForm');
		$form->attr('action', $url . 'lists/');
		$out = '';

		if(count($lists)) {
			/** @var MarkupAdminDataTable $table */
			$table = $modules->get('MarkupAdminDataTable');
			$table->headerRow(array(
				$this->_('title'),
				$this->_('qty'),
				$this->_('sent'),
				$this->_('type'),
				$this->_('id'),
			));

			foreach($lists as $list) {
				if($list['type'] === 'pages') {
					$status = 'users/pages';
				} else if($list['closed']) {
					$status = $this->_('closed');
				} else {
					$status = $this->_('open');
				}
				$table->row(array(
					$list['title'] => $url . "subscribers/?list_id=$list[id]",
					$this->promailer->subscribers->count([ 'list' => $list ]),
					$list['last_sent'] ? $datetime->relativeTimeStr($list['last_sent']) : 'never',
					$status,
					$list['id'],
				));
			}

			$out = $table->render();
		}

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('id+name', 'add_list');
		$f->label = $this->_('Add new list');
		$f->description = $this->_('Enter title of new list');
		if($this->wire('input')->get('add')) {
			$form->appendMarkup = '<script>$(document).ready(function() { $("#add_list").focus(); });</script>';
		} else if(count($lists)) {
			$f->collapsed = Inputfield::collapsedYes;
		}
		$f->icon = 'plus-circle';
		$listWrap = new InputfieldWrapper();
		
		/** @var InputfieldRadios $listType */
		$listType = $modules->get('InputfieldRadios');
		$listType->attr('name', 'add_list_type');
		$listType->label = $this->_('Type of list to add');
		$listType->addOption('', $this->_('New subscribers list managed by ProMailer'));
		$listType->addOption('pages', $this->_('Users (or pages) matching your query/selection'));
		$listType->attr('value', '');
		$listWrap->add($listType);
		$f->appendMarkup = '<p></p>' . $listWrap->render();
		$form->add($f);

		if(count($lists)) {
			/** @var InputfieldSelect $f */
			$f = $modules->get('InputfieldSelect');
			$f->attr('id+name', 'remove_list');
			$f->label = $this->_('Delete list');
			$f->description = $this->_('This will delete the list and all subscribers within it!');
			$f->collapsed = Inputfield::collapsedYes;
			$f->icon = 'trash-o';
			foreach($lists as $list) {
				$f->addOption($list['id'], $list['title'] . ' ' . $this->process->subscribersLabel($list));
			}
			$form->add($f);

			/** @var InputfieldCheckbox $f */
			$f = $modules->get('InputfieldCheckbox');
			$f->attr('name', 'remove_list_confirm');
			$f->label = $this->_('Are you sure you want to delete the list? (check box to confirm)');
			$f->showIf = 'remove_list>0';
			$form->add($f);
		}

		/** @var InputfieldSubmit $f */
		$f = $modules->get('InputfieldSubmit');
		$f->attr('name', 'submit_lists');
		$f->showInHeader(true);
		$form->add($f);

		if($this->wire()->input->post('submit_lists')) {
			$this->process($form, $lists);
		}

		return $out . $form->render();
	}

	/**
	 * Process actions for subscriber lists (add list, remove list)
	 *
	 * @param InputfieldForm $form
	 * @param array $lists
	 *
	 */
	protected function process(InputfieldForm $form, array $lists) {

		$input = $this->wire()->input;
		$form->processInput($input->post);
		$redirect = '';

		$removeList = $form->getChildByName('remove_list');
		if($removeList && $removeList->val() && $input->post('remove_list_confirm')) {
			$removeList = $removeList->val();
			$list = isset($lists[$removeList]) ? $lists[$removeList] : null;
			if($list && $this->promailer->lists->remove($list)) {
				$this->message("Removed list: $list[title]");
				$redirect = './';
			}
		}

		$addList = $this->wire()->sanitizer->text($form->getChildByName('add_list')->val());
		if($addList) {
			$dup = false;
			foreach($lists as $list) {
				if($list['title'] === $addList) $dup = true;
			}
			if($dup) {
				$this->error("List title '$addList' already taken");
			} else {
				$listType = $input->post('add_list_type') == 'pages' ? 'pages' : '';
				$id = $this->promailer->lists->add($addList, $listType);
				if($id) {
					$this->message("Added list: $addList");
					$redirect = "../subscribers/?list_id=$id";
				}
			}
		}

		if($redirect) {
			$this->wire()->session->location($redirect);
		}
	}

}