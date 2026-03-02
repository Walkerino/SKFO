<?php namespace ProcessWire;

class ProcessProMailerSend extends ProcessProMailerTypeManager {

	/**
	 * Send message(s) for this request
	 *
	 * @return string
	 *
	 */
	public function execute() {

		$sanitizer = $this->wire()->sanitizer;
		$input = $this->wire()->input;
		$session = $this->wire()->session;

		$send = (int) $input->get('send'); // current send index (1 for first message)
		$finished = $input->get('finished') ? true : false; // true when sending is detected as finished
		$message = $this->promailer->messages->get((int) $input->get('message_id'));
		$list = $message ? $this->promailer->lists->get($message['list_id']) : null;
		$pause = $input->get('pause') ? true : false; // true when paused
		$nextSubscriberId = 0; // id of next subscriber to send to after current one (populated in foreach)
		$numSent = 0; // count of messages sent in this request
		$info = array(); // info array to display for anyone watching the send
		$preview = '';

		if(!$message || !$list) throw new WireException('Unknown message or list');

		if($message['flags'] & ProMailer::messageFlagsQueueSend) {
			$session->location("../queue/?message_id=$message[id]&start=1");
		}

		// start: return iframe to send
		if(!$send) return $this->initSend($message);

		// determine how long we've been here
		$timer = (time() - $session->getFor($this, "start$message[id]"));
		$secs = sprintf(_n('1 second', '%d seconds', $timer), $timer);

		// finish: return finished message when complete
		if($finished) return $this->finishSend($message, $send-1, $secs);

		// get the first subscriber we will send to in this request
		$subscriber = $this->getSubscriberForSend($message, $list);
		if($subscriber === false) return $this->finishSend($message, $send-1, $secs);
		$lastSubscriber = $subscriber;

		// build information for console output
		$from = $message['from_name'] ? "$message[from_name] <$message[from_email]>" : $message['from_email'];

		$total = $this->promailer->subscribers->count([
			'list' => $list,
			'confirmed' => true
		]);
		$remain = $this->promailer->subscribers->count([
			'list' => $list,
			'id' => $subscriber['id'],
			'confirmed' => true
		]);

		$index = ($total - $remain) + 1;

		// adjust console output according to paused vs sending state
		if($pause) {
			$status = "Paused at $index of $total ($secs)";
		} else {
			$sendQty = $message['send_qty'];
			if($sendQty == 1) {
				$status = "Sent $index of $total ($secs)";
			} else {
				$indexTo = $index + $sendQty > $total ? $total : $index + $sendQty - 1;
				$status = "Sent $index to $indexTo of $total ($secs)";
			}
			$info[] = "**Subject:** $message[subject]";
			$info[] = "**From:** $from";
		}

		// place status at the top of console output
		array_unshift($info, "**" . $this->label('status') . "** $status");

		// iterate once for quantity of messages allowed to send per request
		for($n = 0; $n < $message['send_qty']; $n++) {

			// send the message
			$sendResult = $this->promailer->messages->send($message, $subscriber, array('verbose' => true));

			if($sendResult['success']) {
				// update subscriber 
				$numSent++;
				$subscriber->num_sent = $subscriber->num_sent + 1;
				if($list['type'] != 'pages') $this->promailer->subscribers->save($subscriber, 'num_sent');
				$result = 'Success';
				if(empty($preview)) $preview = $sendResult['bodyText'];
			} else {
				$result = 'Fail';
			}

			// indicate in console who this iteration is sending to and result
			$extraInfo = '';
			if($message['subject'] !== $sendResult['subject']) $extraInfo = '“' . $sendResult['subject'] . '”';

			$info[] = "**To:** $subscriber[email] $extraInfo [span.detail] ($sendResult[mailer]: $result) [/span]";
		
			if(ProcessProMailer::debug) {
				foreach($sendResult['mailer']->header as $k => $v) {
					$info[] = "**$k:** $v";
				}
			}

			// determine the next subscriber
			$nextSubscriber = $this->promailer->subscribers->getNext($list, $subscriber['id'], true);
			$nextSubscriberId = $nextSubscriber ? $nextSubscriber['id'] : 0;

			// stop for loop if no more subscribers
			if(!$nextSubscriberId) break;

			// setup for next iteration
			$subscriber = $nextSubscriber;
			$remain--;
			$index++;
		}

		// update messages table for next subscriber
		$this->promailer->messages->saveSent($message, $nextSubscriberId);
		$this->promailer->lists->saveSent($list);

		// create a button for pause or resume
		/** @var InputfieldButton $btn */
		$btn = $this->wire()->modules->get('InputfieldButton');
		$url = "./?message_id=$message[id]&modal=1&send=" . ($send + $numSent);

		if($pause) {
			// paused: make button a resume button
			$btn->value = 'Resume';
			$btn->href = $url;
			$btn->icon = 'play-circle';
			$this->refreshSend(false);
		} else {
			// sending: make a pause button and setup URL and refresh tag for next request
			if(!$nextSubscriberId) $url .= "&finished=1";
			$btn->value = 'Pause';
			$btn->icon = 'pause-circle';
			$btn->href = "$url&pause=1";
			$this->refreshSend($url, $message['throttle_secs']);
		}

		$btnStr = $btn->render();
		$debugStr = ProcessProMailer::debug ? "<p>" . nl2br($sanitizer->entities($preview)) . "</p>" : "";
		$infoStr = "<p>" . nl2br($sanitizer->entitiesMarkdown(implode("\n", $info), array('allowBrackets' => true))) . "</p>";

		if($this->promailer->useGravatar && $lastSubscriber) {
			$infoStr = $lastSubscriber->gravatar(100, "style='float:right'") . $infoStr;
		}

		// render our console output
		return "<div id='promailer-sending'>$infoStr $btnStr $debugStr</div>";
	}

	/**
	 * Initialize message send by establishing sending iframe
	 *
	 * @param ProMailerMessage $message
	 * @return string
	 *
	 */
	protected function initSend(ProMailerMessage $message) {

		// render sending frame
		$this->process->headline("Sending: $message[title]");
		$this->process->breadcrumb("../", $this->label('promailer'));
		$this->process->breadcrumb("../messages/", $this->label('messages'));
		$this->process->breadcrumb("../message/?message_id=$message[id]", $message['title']);

		$style = "width:100%;height:400px;margin:0;padding:0;";

		$url = "./?message_id=$message[id]&send=1&modal=1";
		$out = "<iframe id='mailer-viewport' name='mailer-viewport' frameborder='0' style='$style' src='$url'></iframe>";

		$session = $this->wire()->session;
		$session->setFor($this, "start$message[id]", time());

		return $out;
	}

	/**
	 * Internal method for testing finishSend without performing a send
	 *
	 * @return string
	 *
	 */
	public function executeTest() {
		$message = $this->promailer->messages->get(1);
		return $this->finishSend($message, 10, '10 secs');
	}

	/**
	 * Render the finished sending markup
	 *
	 * This is used by the live-send (not background send)
	 *
	 * @param ProMailerMessage $message
	 * @param int $numSent
	 * @param string $secs Elapsed time/seconds label
	 * @return string
	 *
	 */
	protected function finishSend(ProMailerMessage $message, $numSent, $secs) {
		$this->refreshSend(false);
		$title = $this->wire()->sanitizer->entities($message['title']);
		return
			"<p>" .
			wireIconMarkup('check-square-o', 'fw') . " Finished sending “" . $title . "”<br />" .
			wireIconMarkup('envelope-o', 'fw') . " $numSent emails sent<br />" .
			wireIconMarkup('clock-o', 'fw') . " Elapsed time $secs" .
			"</p>" .
			"<p><a target='_top' href='../message/?message_id=$message[id]'>" . $this->label('continue') . "</a></p>";
	}

	/**
	 * Set the refresh URL or state and additinal document <head> markup
	 *
	 * This is used by the live-send (not background send)
	 *
	 * @param string $refreshUrl URL to refresh to or false to disable refresh
	 * @param int $throttleSecs
	 *
	 */
	protected function refreshSend($refreshUrl, $throttleSecs = 0) {
		$out = "<style type='text/css'>body.modal #main { padding: 3px !important; margin: 0 !important; }</style>";
		if($refreshUrl) {
			$out .= "<META HTTP-EQUIV='Refresh' CONTENT='$throttleSecs; URL=$refreshUrl' />";
		}
		$this->wire()->adminTheme->addExtraMarkup('head', $out);
	}

	/**
	 * Get current subscriber to send to or false if none found (for executeSend)
	 *
	 * @param ProMailerMessage $message
	 * @param ProMailerList $list
	 * @return ProMailerSubscriber|bool
	 * @throws WireException
	 *
	 */
	protected function getSubscriberForSend(ProMailerMessage $message, ProMailerList $list) {
		$subscriber = false;

		// find next subscriber stored in message DB entry
		if($message['subscriber_id'] > 0) {
			$subscriber = $this->promailer->subscribers->getById($message['subscriber_id'], $list);
		}

		// make sure subscriber still exists and is confirmed
		if(!$subscriber || !$subscriber['confirmed']) {
			// subscriber no longer present or not confirmed, so get next confirmed subscriber
			$subscriber = $this->promailer->subscribers->getNext($list, $message['subscriber_id'], true);
		}

		// ensure we have a valid subscriber
		if($subscriber === null) {
			// database query error, error message is also sent by getNextSubscriber()
			throw new WireException("Unable to load subscriber $message[subscriber_id]");

		} else if($subscriber === false) {
			// end of subscribers list, this should not occur unless last subscriber in list
			// unsubscribed between 2nd to last and last request
		}

		return $subscriber;
	}
	
	/**
	 * Execute message preview
	 *
	 * Requires 'subscriber_id' and 'message_id' in query string.
	 * If 'type' in query string is 'text' it renders text preview, otherwise it renders HTML preview. 
	 *
	 * @throws WireException
	 * @return string
	 *
	 */
	public function preview() {

		$input = $this->wire()->input;
		$type = $input->get('type') === 'text' ? 'text' : 'html';

		$messageId = (int) $input->get('message_id');
		$message = $this->promailer->messages->get($messageId);
		if(!$message) throw new WireException('No message found');

		$subscriberId = (int) $input->get('subscriber_id');
		if(!$subscriberId) $subscriberId = $message->subscriber_id;
		$subscriber = $this->promailer->subscribers->getNext($message->list_id, $subscriberId);
		if(!$subscriber) $subscriber = $this->promailer->subscribers->getNext($message->list_id, 0);

		if(!$subscriber) {
			$a = array(
				'email' => $this->wire()->user->email,
				'list_id' => $message->list_id,
				'custom' => array(),
			);
			$list = $message->getList();
			if($list) {
				foreach($list->fields as $fieldName => $fieldType) {
					$a['custom'][$fieldName] = $fieldName;
				}
			}
			$subscriber = new ProMailerSubscriber($a);
		}

		if($type === 'text') header('Content-type: text/plain; charset=utf-8');

		return $this->promailer->email->renderMessageBody($message, $subscriber, $type);
	}

}