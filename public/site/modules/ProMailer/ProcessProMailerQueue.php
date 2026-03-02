<?php namespace ProcessWire;

class ProcessProMailerQueue extends ProcessProMailerTypeManager {
	
	/**
	 * Execute the sending queue
	 *
	 * Since queue is already executing in background (if active) this method primarily
	 * just reports on the status of the queue.
	 *
	 * @return string Returns HTML with output of current status in the queue
	 * @throws WireException
	 *
	 */
	public function execute() {

		$input = $this->wire()->input;
		$session = $this->wire()->session;
		$modules = $this->wire()->modules;
		
		$message = $this->promailer->messages->get((int) $input->get('message_id'));

		if(!$message) throw new WireException('Unknown message');

		$list = $message->getList();
		$redirectUrl = "./?message_id=$message[id]";

		$queues = $this->promailer->queues;
		$queues->install();

		if($input->get('start')) {
			if($queues->add($message)) {
				$this->message($this->_('Added message to queue') . " - $message[title]");
				$queues->process(true);
			}
		} else if($input->get('pause')) {
			if($queues->pause($message)) {
				$this->message($this->_('Paused background sending of message') . " - $message[title]");
				$redirectUrl = "../message/?message_id=$message[id]&pause=1";
			}
		} else if($input->get('resume')) {
			if($queues->resume($message)) {
				$this->message($this->_('Resumed background sending of message') . " - $message[title]");
				$queues->process(true);
			}
		} else if($input->get('stop')) {
			if($queues->remove($message)) {
				$subscriberId = $message['subscriber_id'];
				$message['subscriber_id'] = 0;
				$this->promailer->messages->save($message);
				$session->message(
					$this->_('Stopped sending of message') . " - $message[title] " . 
					sprintf($this->_('(last sent to subscriber ID %d)'), $subscriberId)
				);
				$redirectUrl = "../message/?message_id=$message[id]&stop=1";
			}
		} else {
			$redirectUrl = '';
		}

		if($redirectUrl) $session->location($redirectUrl);

		$this->process->breadcrumb('../', $this->label('promailer'));
		$this->process->breadcrumb('../messages/', $this->label('messages'));
		$this->process->breadcrumb('../message/?message_id=' . $message['id'], $message['title']);

		$queueItem = $queues->get($message['id']);

		if($queueItem) {
			$qstat = $this->statusStr($message, $queueItem, $list);
			$out = "<p id='promailer-qstat'>$qstat</p>";
		} else {
			$session->message($this->_('Message is not currently in the queue or has already finished sending.'));
			$out = '';
		}

		if($queueItem && $queueItem->paused) {
			$this->process->headline("$message[title] " . $this->_('(paused)'));
			/** @var InputfieldButton $resume */
			$resume = $modules->get('InputfieldButton');
			$resume->value = $this->_('Resume sending');
			$resume->href = "./?message_id=$message[id]&resume=1";
			$resume->icon = 'play-circle';

			$out .= "<p>" . $resume->render() . "</p>";

		} else {
			$this->process->headline("$message[title]");

			/** @var InputfieldButton $pause */
			$pause = $modules->get('InputfieldButton');
			$pause->attr('id', 'promailer-button-pause');
			$pause->value = $this->_('Pause');
			$pause->href = "./?message_id=$message[id]&pause=1";
			$pause->icon = 'pause-circle';

			/** @var InputfieldButton $stop */
			$stop = $modules->get('InputfieldButton');
			$stop->attr('id', 'promailer-button-stop');
			$stop->attr('data-confirm-label', $this->_('Are you sure you want to abort this send?')); 
			$stop->value = $this->_('Stop');
			$stop->href = "./?message_id=$message[id]&stop=1";
			$stop->icon = 'stop-circle';
			$stop->setSecondary(true);

			$out .= "<p>" . $pause->render() . $stop->render() . "</p>";
		}

		return "<div id='promailer-sending-queue' data-message='$message[id]'>$out</div>";
	}

	
	/**
	 * Get status string for current sending queue
	 *
	 * Note: returns redirect URL rather than status string when ajax mode and sending is finished
	 *
	 * @param ProMailerMessage|null $message
	 * @param ProMailerQueue|null $queueItem
	 * @param ProMailerList|null $list
	 * @return string
	 *
	 */
	public function statusStr(ProMailerMessage $message = null, ProMailerQueue $queueItem = null, ProMailerList $list = null) {

		if(!$message) {
			$input = $this->wire()->input;
			$message = $this->promailer->messages->get((int) $input->get('message_id'));
			if(!$message) return '';
		}

		if(!$list) {
			$list = $message->getList();
			if(!$list) return '';
		}

		if(!$queueItem) {
			$queueItem = $this->promailer->queues->get($message['id']);
			if(!$queueItem) {
				if($this->wire()->config->ajax) {
					return "../message/?message_id=$message[id]&finished=1";
				} else {
					return "Finished sending to list “" . $list['title'] . "”";
				}
			}
		}

		$numTotal = $this->promailer->subscribers->count([
			'list' => $list,
			'confirmed' => true
		]);

		return "Sent $queueItem[num] of $numTotal to list “" . $list['title'] . "”";
	}

}