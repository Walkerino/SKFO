<?php namespace ProcessWire;

/**
 * ProcessWire ProMailer Email
 * 
 * Handles sending of emails to subscribers
 *
 * Copyright 2023 by Ryan Cramer
 * This is a commercial module, do not distribute.
 * 
 * HOOKABLE METHODS
 * ================
 * @method WireMail getMailer($name = '') Get WireMail instance
 *
 * // these are called for any email sent by ProMailer (including distributions and confirmations)
 * @method bool sendReady($email, WireMail $mailer) Send ready
 * @method sent($email, WireMail $mailer) Message sent to subscriber
 * @method sendFail($email, WireMail $mailer, $error) Message failed to send
 * @method string markupToText($markup) Convert given HTML markup to plain text
 *
 * // these are called only during distributions
 * @method bool subscriberMessageReady(array $data) Message ready to send to subscriber 
 * @method void subscriberMessageSent(array $data) Message sent to subscriber
 * @method void subscriberMessageFail(array $data) Message failed to send to subscriber
 * @method string renderMessageBodyFromPage(Page $page, ProMailerMessage $message, ProMailerSubscriber $subscriber, $type = 'html')
 * 
 *
 */

class ProMailerEmail extends ProMailerTypeManager {
	
	/**
	 * Get WireMail module
	 *
	 * @param string $name Specify which WireMail module or omit for to auto-detect
	 * @return WireMail
	 *
	 */
	public function ___getMailer($name = '') {
		if($this->promailer->forceMailer) $name = $this->promailer->forceMailer;
		$mailer = null;
		$lname = strtolower($name);
		if($lname === 'wiremail' || $lname === 'php') {
			// PHP native WireMail specified
			$mailer = $this->wire()->mail->new(array('module' => 'WireMail'));
		} else if($lname === 'pretend' || $lname === 'pretender') {
			// pretend mailer (does not send anything)
			$mailer = $this->wire(new WireMailPretender());
		} else if(strlen($name)) {
			// custom WireMail module specified
			$mailer = $this->wire()->modules->get($name);
		}	
		if(!$mailer instanceof WireMailInterface) {
			$mailer = $this->wire()->mail->new(); // system default
			if(!$mailer instanceof WireMailInterface) $mailer = wireMail(); // not likely
			if($name) $this->warning("Unable to get mailer '$name', using '$mailer' instead");
		}
		return $mailer;
	}

	/**
	 * Send a populated WireMail object
	 *
	 * @param WireMail $mailer
	 * @return int
	 *
	 */
	public function send(WireMail $mailer) {

		$email = implode(',', $mailer->to);
		$result = 0;
		$error = '';

		try {
			if($this->sendReady($email, $mailer)) {
				$result = $mailer->send();
				if(!$result) $error = "send() returned 0";
			} else {
				$error = "sendReady() cancelled send";
			}
		} catch(\Exception $e) {
			$error = $e->getMessage();
		}

		if($result) {
			$this->sent($email, $mailer);
		} else {
			$this->sendFail($email, $mailer, $error);
		}

		return (int) $result;
	}
	
	/**
	 * Send given message to given subscriber
	 *
	 * @param ProMailerMessage|int $message Message or message ID
	 * @param ProMailerSubscriber|int|Page $subscriber Subscriber, subscriber ID or subscriber Page
	 * @param array $options
	 *  - `verbose` (bool): Return verbose array of info rather than bool?
	 *  - `pretend` (bool): Pretend to send rather than actually sending?
	 * @return bool|array True if message sent, false if not. Returns verbose array if verbose option specified. 
	 *
	 */
	public function sendMessage($message, $subscriber, array $options = array()) {
		
		$defaults = array(
			'verbose' => false,
			'pretend' => false,
		);

		$options = array_merge($defaults, $options);
		$message = $this->messages->_message($message);
		$subscriber = $this->subscribers->_subscriber($subscriber);
		$list = $subscriber->list;
	
		// data for hooks or verbose return value
		$data = array(
			'message' => $message,
			'subscriber' => $subscriber,
			'list' => $list, 
			'mailer' => null,
			'options' => $options,
			'success' => false,
			'bodyText' => '',
			'bodyHtml' => '',
			'subject' => '',
			'error' => '',
		);
		
		if(empty($subscriber['email']) || !$this->promailer->ok()) {
			return $options['verbose'] ? $data : false;
		}
		
		if($message['body_type'] == ProMailer::bodyTypeHtml) {
			$bodyHtml = $this->renderMessageBody($message, $subscriber, 'html');
			$bodyText = '';
		} else if($message['body_type'] == ProMailer::bodyTypeText) {
			// text only
			$bodyText = $this->renderMessageBody($message, $subscriber, 'text');
			$bodyHtml = '';
		} else {
			// both html and text
			$bodyHtml = $this->renderMessageBody($message, $subscriber, 'html');
			$bodyText = $this->renderMessageBody($message, $subscriber, 'text');
		}

		if(empty($bodyHtml) && empty($bodyText)) {
			throw new WireException("Message body is empty");
		}

		if($bodyHtml === $bodyText || empty($bodyText)) {
			$bodyText = $this->markupToText($bodyHtml);
		}

		if(ProMailer::sendDebug) {
			// debug mode tests send without sending, along with 10% chance of error
			$n = mt_rand(0, 100);
			if($n < 90) $data['success'] = true;
			return $options['verbose'] ? $data : $data['success'];
		}
		
		$subject = $message['subject'];
		if(strpos($subject, '}')) {
			$subject = $this->populateMessagePlaceholders($message, $subscriber, $subject, 'subject');
		}

		if($options['pretend']) $message['mailer'] = 'pretend';
		$mailer = $this->getMailer($message['mailer']); 
		
		if(strlen($bodyText)) $mailer->body($bodyText);
		if(strlen($bodyHtml)) $mailer->bodyHTML($bodyHtml);

		$data['mailer'] = $mailer;
		$data['bodyText'] = $bodyText;
		$data['bodyHtml'] = $bodyHtml;
		$data['subject'] = $subject;
	
		try {
			$mailer->to($subscriber['email']);
			$mailer->from($message['from_email'], $message['from_name']);
			$mailer->subject($subject);
			
			if($message->reply_email) $mailer->replyTo($message->reply_email);
			if(!$this->subscriberMessageReady($data)) return $options['verbose'] ? $data : false;

			$result = $this->send($mailer);
			
		} catch(\Exception $e) {
			$this->error($e->getMessage());
			$result = false;
		}
		
		$data['success'] = (bool) $result;
		
		if($result) {
			$this->subscriberMessageSent($data);
		} else {
			$data['error'] = $this->errors('last string');
			$this->subscriberMessageFail($data);
		}
		
		if($options['verbose']) return $data;

		return (bool) $result;
	}

	/**
	 * Render message body for subscriber
	 *
	 * @param ProMailerMessage $message
	 * @param ProMailerSubscriber $subscriber
	 * @param string $type Either "text" or "html" (default=html)
	 * @return string
	 * @throws WireException
	 *
	 */
	public function renderMessageBody(ProMailerMessage $message, ProMailerSubscriber $subscriber, $type = 'html') {

		$input = $this->wire()->input;
		$type = strtolower($type);
		$body = '';

		if($type !== 'html') $type = 'text';

		// variables available to rendered pages or URLs
		$getVars = array(
			'type' => $type,
			'subscriber_id' => $subscriber['id'],
			'message_id' => $message['id'],
			'list_id' => $message['list_id']
		);
		
		if($message['body_source'] == ProMailer::bodySourceHref) {
			// external URL
			if($type == 'html') {
				$url = $message['body_href_html'];
			} else {
				$url = empty($message['body_href_text']) ? $message['body_href_html'] : $message['body_href_text'];
			}
			$url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($getVars);
			$http = new WireHttp();
			$body = $http->get($url);
			
			if($type === 'text' && stripos($body, '<html') !== false) {
				$body = $this->markupToText($body);
			} else if($type === 'html') {
				$info = parse_url($url);
				$rootHttpUrl = "$info[scheme]://$info[host]";
				if(!empty($info['port'])) $rootHttpUrl .= ":$info[port]";
				$body = preg_replace('!( (?:href|src)=["\']?)/!i', '$1' . $rootHttpUrl . '/', $body);
			}

		} else if($message['body_source'] == ProMailer::bodySourcePage && !empty($message['body_page'])) {
			// render a page
			$pages = $this->wire()->pages;
			$of = $pages->of();
			if(!$of) $pages->of(true);
			$page = $pages->get((int) $message['body_page']);
			
			if(!$page->id) throw new WireException("Page $message[body_page] does not exist");
			if(!$page->viewable()) throw new WireException('Invalid page (not viewable)');
		
			// when bodyTypeHtml and type=text that means we are doing auto-generated text
			if($message['body_type'] == ProMailer::bodyTypeHtml && $type === 'text') {
				$htmlToText = true;
				$type = 'html';
				$getVars['type'] = $type;
			} else {
				$htmlToText = false;
			}
			
			foreach($getVars as $k => $v) {
				$input->get->__set($k, $v);
				$_GET[$k] = $v;
			}
			
			$body = $this->renderMessageBodyFromPage($page, $message, $subscriber, $type); 
			
			if($type === 'html' || $htmlToText) {
				// replace relative references with absolute
				$rootPage = $pages->get('/');
				$rootUrl = $rootPage->url();
				$rootHttpUrl = $rootPage->httpUrl();
				$body = preg_replace('!(\s(?:href|src)=["\']?)' . $rootUrl . '!i', '$1' . $rootHttpUrl, $body);
				if($htmlToText) $body = $this->markupToText($body); 
			}
			
			if(!$of) $pages->of(false);
			
		} else if($message['body_source'] == ProMailer::bodySourceInput) {
			// direct input
			if($type === 'html') {
				$body = $message['body_html'];
			} else {
				$body = $message['body_text'];
				if(empty($body)) $body = $this->markupToText($message['body_html']); 
			}
		}


		$body = $this->populateMessagePlaceholders($message, $subscriber, $body, $type);

		return $body;
	}

	/**
	 * Render message body from a Page object
	 * 
	 * @param Page $page
	 * @param ProMailerMessage $message
	 * @param ProMailerSubscriber $subscriber
	 * @param string $type Type of 'html' or 'text'
	 * @return string
	 * 
	 */
	protected function ___renderMessageBodyFromPage(Page $page, ProMailerMessage $message, ProMailerSubscriber $subscriber, $type = 'html') {
		
		$page->setQuietly('mailType', $type);
		$page->setQuietly('mailMessage', $message);
		$page->setQuietly('mailSubscriber', $subscriber);

		$pages = $this->wire()->pages;
		$of = $pages->of();
		if(!$of) $pages->of(true); 
		
		$pagePrevious = $this->wire()->page;
		$this->wire('page', $page);
		$body = $page->render();
		$this->wire('page', $pagePrevious);
		
		if(!$of) $pages->of(false);
		
		return $body;
	}

	/**
	 * Convert markup to text using best available method
	 * 
	 * @param string $markup
	 * @return string
	 * 
	 */
	public function ___markupToText($markup) {
		$sanitizer = $this->wire()->sanitizer;
		if(method_exists($sanitizer, 'getTextTools')) {
			$text = $sanitizer->getTextTools()->markupToText($markup, array(
				'underlineHeadlines' => true,
				'uppercaseHeadlines' => true, 
				'collapseSpaces' => true, 
				'linkToUrls' => true, 
			));
		} else if(method_exists($sanitizer, 'markupToText')) {
			$text = $sanitizer->markupToText($markup); 
		} else {
			$markup = $sanitizer->unentities($markup);
			$text = strip_tags($markup); 
		}
		return $text;
	}

	/**
	 * Populate placeholders for the message
	 * 
	 * @param ProMailerMessage $message
	 * @param ProMailerSubscriber $subscriber
	 * @param string $body
	 * @param string $type Either 'html', 'text' or 'subject'
	 * @return string
	 * 
	 */
	protected function populateMessagePlaceholders(ProMailerMessage $message, ProMailerSubscriber $subscriber, $body, $type) {
		
		$list = $this->lists->get($message['list_id']); 
		
		// populate placeholders: {name} style variables
		// subscriber properties
		$data = is_array($subscriber['custom']) ? $subscriber['custom'] : array();
		$data['email'] = $subscriber['email'];

		// placeholders message properties
		$properties = array(
			'title',
			'subject',
			'from_email',
			'from_name'
		);
		
		foreach($properties as $property) {
			if(empty($data[$property])) $data[$property] = $message[$property];
		}
		
		$fieldsArray = $list->fieldsArray();
		foreach($fieldsArray as $fieldName => $fieldInfo) {
			if(!isset($data[$fieldName])) $data[$fieldName] = '';
			if(!empty($fieldInfo['options'])) {
				foreach($fieldInfo['options'] as $value => $label) {
					if($value != $data[$fieldName]) continue;
					$data["$fieldName.label"] = $label;
				}
				if(!isset($data["$fieldName.label"])) $data["$fieldName.label"] = '';
			}
		}
		
		// unsubscribe and subscribe URLs
		if(strpos($body, 'subscribe_url')) {
			$data['unsubscribe_url'] = $this->forms->getUnsubscribeUrl($subscriber);
			$data['subscribe_url'] = $this->forms->getSubscribeUrl($message['list_id']);
		}
	
		$body = $this->populatePlaceholders($body, $data);

		// populate page variables
		if($list['type'] === 'pages' && $subscriber['page'] && strpos($body, '}')) {
			// i.e. {page.title} => {title} or similar to differentiate from other already-replaced properties
			$body = str_replace('{page.', '{', $body); 
			/** @var Page $page */
			$page = $subscriber['page'];
			$page->of(true);
			if($type === 'html') {
				$body = $page->getMarkup($body);
			} else {
				$body = $page->getText($body, false, false);
			}
		}
		
		return $body;
	}

	/**
	 * Populate placeholders from $data in $body 
	 * 
	 * @param string $body
	 * @param array $data
	 * @return string
	 * 
	 */
	public function populatePlaceholders($body, array $data) {
		
		$body = $this->populateConditionals($body, $data);
		$a = array(); // replacements
		
		// translate to {name} style variables
		foreach($data as $key => $value) {
			$key = '{' . $key . '}';
			if(strpos($body, $key) === false) continue;
			if(is_array($value)) {
				$value = implode(', ', $value);
			} else if(is_object($value)) {
				$value = $value instanceof PageArray ? $value->implode(', ', 'title') : (string) $value;
			}
			if(strpos($value, '}') !== false) {
				// do not allow values to populate more placeholders
				$value = str_replace('{page.', '{', $value);
				$value = preg_replace('/\{([-_.a-zA-Z0-9]+)\}/', ' ', $value);
			}
			$a[$key] = $value;
		}

		$body = str_replace(array_keys($a), array_values($a), $body);
		
		return $body;
	}
	
	/**
	 * Populate conditional {tag} statements in $body according to $data
	 * 
	 * ~~~~~
	 * // basic example
	 * {if:first_name}{first_name}{endif}
	 *
	 * // example with else condition
	 * {if:first_name}
	 *   Hello {first_name},
	 * {else}
	 *   Hello friend,
	 * {endif}
	 * 
	 * // when nesting statements, append the {endif} with the field name, 
	 * // for example: i.e {endif:last_name}
	 * // may occasionally be necessary with {else} as well
	 * {if:first_name}
	 *   {if:last_name}
	 *     Hello {first_name} {last_name}, 
	 *   {else}
	 *     Hello {first_name}, 
	 *   {endif:last_name}
	 * {else}
	 *   Hello friend,
	 * {endif:first_name}
	 * 
	 * // example of ifnot condition (opposite of if condition)
	 * {ifnot:first_name}
	 *   Hello friend,
	 * {else}
	 *   Hello {first_name},
	 * {endif}
	 * ~~~~~
	 * 
	 * @param string $body
	 * @param array $data
	 * @return string
	 * 
	 */
	public function populateConditionals($body, array $data) {
		
		static $level = 0;
		
		if(strpos($body, '{if') === false) return $body;
		
		if(!$level && strpos($body, '{/if}') || strpos($body, '{/ifnot}') || strpos($body, '{endif}')) {
			// allow for {/if} or {endif} without field name, only if no other if statements within it
			// convert them to {endif:field_name} statements
			$body = str_replace(array('{/if}', '{/ifnot}'), '{endif}', $body);
			$a = array();
			if(preg_match_all('!\{(if|ifnot):([-_.a-zA-Z0-9]+)\}(.*?)\{endif\}!s', $body, $matches)) {
				foreach($matches[0] as $key => $fullMatch) {
					$ifType = $matches[1][$key];
					$fieldName = $matches[2][$key];
					$content = $matches[3][$key];	
					$a[$fullMatch] = '{' . $ifType . ':' . $fieldName . '}' . $content . '{endif:'. $fieldName . '}';
				}
				if(count($a)) $body = str_replace(array_keys($a), array_values($a), $body);
			}
		}
		
		$a = explode("\n", $body);
		foreach($a as $key => $line) {
			$line = trim($line);
			if(substr($line, 0, 1) === '{' && substr($line, -1) === '}' && strpos($line, '}') < strlen($line)) {
				// line with just a statement on it, suppress newline
				$a[$key] = trim($line);
			} else {
				$a[$key] = "\n$line";
			}
		}
		$body = implode('', $a);
		
		if(!preg_match_all('!\{(if|ifnot):([-_.a-zA-Z0-9]+)\}(.*?)\{(?:endif|/\\1):\\2\}!s', $body, $matches)) {
			return $body;
		}
		
		$a = array();
		$level++;
		
		foreach($matches[0] as $key => $fullMatch) {
			
			$ifType = $matches[1][$key];
			$fieldName = $matches[2][$key];
			$trueContent = $matches[3][$key];
			
			if(strpos($trueContent, '{if:') !== false || strpos($trueContent, '{ifnot:') !== false) {
				if($level < 5) {
					$trueContent = $this->populatePlaceholders($trueContent, $data);
				}
			}
			
			$falseContent = '';
			$isEmpty = empty($data[$fieldName]);
	
			if(strpos($trueContent, '{else}') !== false) {
				$trueContent = str_replace('{else}', "{else:$fieldName}", $trueContent);
			}
			if(strpos($trueContent, "{else:$fieldName") !== false) {
				list($trueContent, $falseContent) = explode("{else:$fieldName}", $trueContent, 2);
			}
			
			if(($ifType === 'if' && !$isEmpty) || ($ifType === 'ifnot' && $isEmpty)) {
				// show the content matched by the if statement
				$a[$fullMatch] = $trueContent;
			} else {
				// remove the content matched by the if statement
				$a[$fullMatch] = $falseContent;
			}
		}
		
		if(count($a)) {
			$body = trim(str_replace(array_keys($a), array_values($a), $body));
		}
		
		$level--;
		
		return $body;
	}
	
	/**
	 * Get the default email template page
	 * 
	 * @param bool $allowCreate
	 * @return Page|NullPage
	 * 
	 */
	public function getDefaultEmailPage($allowCreate = true) { 
		
		$config = $this->wire()->config;
		$pages = $this->wire()->pages;
		
		$page = $pages->get("include=hidden, sort=-created, template=" . ProMailer::emailTemplateName);
		if($page->id) return $page;
		
		$page = $pages->get("include=all, template=" . ProMailer::emailTemplateName);
		if($page->id) return $page;
		
		if(!$allowCreate) return new NullPage();

		/** @var Template $template */
		$template = $this->wire()->templates->get(ProMailer::emailTemplateName);

		if(!$template) {
			$fieldgroup = $this->wire()->fieldgroups->get(ProMailer::emailTemplateName);
			if(!$fieldgroup) {
				$fieldgroup = new Fieldgroup();
				$fieldgroup->name = ProMailer::emailTemplateName;
				$fieldgroup->add($this->wire()->fields->get('title'));
				$body = $this->wire()->fields->get('body');
				if($body) $fieldgroup->add($body);
				$fieldgroup->save();
			}
			$template = new Template();
			$template->name = ProMailer::emailTemplateName;
			$template->label = 'ProMailer: Email';
			$template->fieldgroup = $fieldgroup;
			$template->slashUrls = 1;
			$template->noGlobal = 1;
			$template->noPrependTemplateFile = 1;
			$template->noAppendTemplateFile = 1;
			$template->save();
			$this->message("Created Template: $template->name");
		}

		$page = new Page();
		$page->template = $template;
		$page->name = 'email-example';
		$page->parent = $this->forms->getSubscribePage();
		$page->addStatus(Page::statusHidden);
		$page->title = 'Email example';
		$page->set('body', 
			"<h3>This is an example email page</h3>" . 
			"<p>You do not have to use this page or template unless you want to, but it is here as an example to get you started.</p>" . 
			"<p>Be sure to also check out the template file '{$template->name}.php' for details on how to develop email templates.</p>"
		);
		$page->save();
		$this->message("Created page $page->path");

		$src = dirname(__FILE__) . '/' . ProMailer::emailTemplateName . '.php';
		$dst = $config->paths->templates . ProMailer::emailTemplateName . '.php';
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

	public function install() {
		$this->getDefaultEmailPage();
		return parent::install();
	}
	
	public function uninstall() {
		
		$pages = $this->wire()->pages;
		
		$qty = $pages->count("include=all, template=" . ProMailer::emailTemplateName); 
		if(!$qty) return parent::uninstall();
		
		$page = $pages->get("include=all, name=email-example, template=" . ProMailer::emailTemplateName);
		if(!$page->id) return parent::uninstall();
		
		$this->message("Removing page: $page->path");
		$page->delete();

		// we do not want to remove their own custom pages
		if($qty > 1) return parent::uninstall();

		$template = $this->wire()->templates->get(ProMailer::subscribeTemplateName);
		if($template) {
			$template->flags = Template::flagSystemOverride;
			$template->flags = 0;
			$this->message("Removing template: $template->name");
			$this->wire()->templates->delete($template);
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
		
		return parent::uninstall();
	}
	
	public function table() {
		return '';
	}
	
	public function typeName() {
		return '';
	}

	/**
	 * Save entry to sent email log
	 *
	 * @param string $str
	 *
	 */
	public function saveLog($str) {
		$useLogs = $this->promailer->useLogs;
		if(!in_array(ProMailer::useLogEmail, $useLogs)) return;
		$this->wire()->log->save('promailer-email', $str);
	}

	/**
	 * Get all the parts of an email address
	 *
	 * @param string $email
	 * @return array
	 *
	 */
	public function parseEmail($email) {
		if(strpos($email, '@') !== false) {
			list($name, $host) = explode('@', $email);
			if(strpos($name, '+') !== false) {
				list($name, $subname) = explode('|', $name);
			} else {
				$subname = '';
			}
			if(strpos($host, '.') !== strrpos($host, '.')) {
				$parts = explode('.', $host);
				$ext = array_pop($parts);
				$domain = array_pop($parts) . '.' . $ext;
			} else {
				$domain = $host;
			}
		} else {
			list($name, $subname, $host, $domain) = array('','','','');
		}
		return array(
			'name' => $name,
			'subname' => $subname,
			'host' => $host,
			'domain' => $domain
		);
	}

	/**
	 * Is the given email in the given domain (or host)?
	 * 
	 * @param string $email
	 * @param string|array $domain
	 * @return bool
	 * 
	 */
	public function emailInDomain($email, $domain) {
		$in = false;
		
		if(is_array($domain)) {
			foreach($domain as $d) {
				if(!$this->emailInDomain($email, $d)) continue;
				$in = true;
				break;
			}
		} else {
			$email = $this->parseEmail(strtolower($email));
			$domain = strtolower(trim($domain));
			$in = strlen($domain) > 0 && ($email['domain'] === $domain || $email['host'] === $domain);
		}
	
		return $in;
	}

	/**
	 * Prepare mailer with RFC 8058 List-Unsubscribe headers
	 * 
	 * @param WireMail $mailer
	 * @param ProMailerSubscriber $subscriber
	 * @param ProMailerList $list
	 * 
	 */
	protected function mailerListUnsubscribeHeaders(WireMail $mailer, ProMailerSubscriber $subscriber, ProMailerList $list) {
		
		$unsub = $this->promailer->useListUnsub;
		$domains = trim($this->promailer->listUnsubDomains);
		
		if(strlen($domains) && !$this->emailInDomain($subscriber->email, explode("\n", $domains))) {
			return;
		}
		
		$unsubUrl = $this->forms->getUnsubscribeUrl($subscriber, array('requireHttps' => true));
		$unsubSubject = urlencode("Unsubscribe: $list->title");
		$unsubEmail = $this->promailer->listUnsubEmail;
		$unsubMailto = "mailto:$unsubEmail?subject=$unsubSubject";
		$unsubOneClick = 'List-Unsubscribe=One-Click';

		if($unsub == ProMailer::useListUnsubUrl) {
			$mailer->header('List-Unsubscribe', "<$unsubUrl>");
			$mailer->header('List-Unsubscribe-Post', $unsubOneClick);
		} else if($unsub == ProMailer::useListUnsubEmail && $unsubEmail) {
			$mailer->header('List-Unsubscribe', "<$unsubMailto>");
		} else if($unsub == ProMailer::useListUnsubBoth) {
			$mailer->header('List-Unsubscribe', "<$unsubUrl>, <$unsubMailto>");
			$mailer->header('List-Unsubscribe-Post', $unsubOneClick);
		}
	}
	
	/*** HOOKS ***********************************************************************************************/

	/**
	 * Hook called when message ready to send to email
	 *
	 * If method returns false ($event->return==false) the send of message to subscriber will be aborted
	 *
	 * @param string $email
	 * @param WireMail $mailer
	 * @return bool
	 *
	 */
	public function ___sendReady($email, WireMail $mailer) {
		return true;
	}

	/**
	 * Hook called after message has been sent to subscriber
	 *
	 * @param string $email
	 * @param WireMail $mailer
	 *
	 */
	public function ___sent($email, WireMail $mailer) {
		$this->saveLog($mailer->className() . " SENT $email ($mailer->subject)");
	}

	/**
	 * Hook called when message fails to send to subscriber
	 *
	 * @param string $email
	 * @param WireMail $mailer
	 * @param string $error
	 *
	 */
	public function ___sendFail($email, WireMail $mailer, $error) {
		$this->saveLog($mailer->className() . " FAIL $email ($mailer->subject) - $error");
		if(strlen($error) && $this->wire()->page->template->name === 'admin') $this->error($error);
	}

	/**
	 * Message ready to send to subscriber
	 * 
	 * @param array $data Receives a copy of data for the send:
	 *  - `subscriber` (ProMailerSubscriber)
	 *  - `message` (ProMailerMessage)
	 *  - `list` (ProMailerList)
	 *  - `mailer` (WireMail)
	 *  - `bodyText` (string)
	 *  - `bodyHtml` (string)
	 *  - `subject` (string)
	 * @return bool
	 * 
	 */
	public function ___subscriberMessageReady(array $data) {
		
		/** @var ProMailerList $list */
		$list = $data['list'];
		/** @var ProMailerSubscriber $subscriber */
		$subscriber = $data['subscriber'];
		/** @var WireMail $mailer */
		$mailer = $data['mailer'];
		
		// remove X-Mailer header
		$mailer->header('X-Mailer', null);

		if($this->promailer->useListUnsub) {
			$this->mailerListUnsubscribeHeaders($mailer, $subscriber, $list);
		}
		
		if($this->promailer->useXMailer) {
			$info = ProMailer::getModuleInfo();
			$class = $mailer->className();
			$mailer->header('X-Mailer', "ProcessWire/ProMailer v$info[version] ($class)");
		}
		
		return true;
	}

	/**
	 * Message sent to subscriber
	 *
	 * @param array $data Receives a copy of data for the send:
	 *  - `subscriber` (ProMailerSubscriber)
	 *  - `message` (ProMailerMessage)
	 *  - `mailer` (WireMail)
	 *  - `bodyText` (string)
	 *  - `bodyHtml` (string)
	 *  - `subject` (string)
	 *  - `success` (bool)
	 *  - `error` (string)
	 *
	 */
	public function ___subscriberMessageSent(array $data) {
	}

	/**
	 * Message failed to send to subscriber
	 *
	 * @param array $data Receives a copy of data for the send:
	 *  - `subscriber` (ProMailerSubscriber)
	 *  - `message` (ProMailerMessage)
	 *  - `mailer` (WireMail)
	 *  - `bodyText` (string)
	 *  - `bodyHtml` (string)
	 *  - `subject` (string)
	 *  - `success` (bool)
	 *  - `error` (string)
	 *
	 */
	public function ___subscriberMessageFail(array $data) {
	}

}

class WireMailPretender extends WireMail {
	public function ___send() { 
		return count($this->to); 
	}
	public function __toString() {
		return 'Pretender';
	}
}