<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

/**
 * ProMailer: Hook examples
 * 
 * Copy/paste any hooks you want to use into your /site/ready.php file and modify
 * consistent with your needs. We will be adding more hooks to this file of examples 
 * as they come up. 
 * 
 */

/** @var ProcessWire $wire */

/***************************************************************************************
 * Captures subscribe errors and sends email to $config->adminEmail
 *
 */
$wire->addHookAfter('ProMailerForms::processSubscribe', function(HookEvent $event) {

	$result = $event->return; /** @var array $result */
	$adminEmail = $event->wire()->config->adminEmail;
	$skipErrors = [ 'errorDuplicate', 'errorEmail', 'errorRequired', 'errorHoneypot' ];

	if(empty($result['error']) || empty($adminEmail) || in_array($result['error'], $skipErrors)) return;

	wireMail()
		->to($adminEmail)
		->subject("ProMailer subscribe fail: $result[error]")
		->body("$result[message]\n\nError: $result[error]\nEmail: $result[email]\n")
		->send();
});

/***************************************************************************************
 * Emails new subscribe requests to $config->adminEmail
 *
 */
$wire->addHook('ProMailerSubscribers::added', function(HookEvent $event) {

	$subscriber = $event->arguments(0); // ProMailerSubscriber
	$adminEmail = $event->wire()->config->adminEmail;
	$list = $subscriber->getList(); // ProMailerList

	if($adminEmail) wireMail()
		->to($adminEmail)
		->subject("New subscriber to $list->title")
		->body("$subscriber->email subscribe request to $list->title")
		->send();
});

/***************************************************************************************
 * Emails new confirmed subscribers to $config->adminEmail
 *
 */
$wire->addHook('ProMailerSubscribers::confirmedEmail', function(HookEvent $event) {

	$adminEmail = $event->wire()->config->adminEmail;
	$email = $event->arguments(0); // string
	$list = $event->arguments(1); // ProMailerList

	// get subscriber if you need it (we do not here)
	// $subscribers = $event->object; // ProMailerSubscribers
	// $subscriber = $subscribers->getByEmail($email, $list); // ProMailerSubscriber

	if($adminEmail) wireMail()
		->to($adminEmail)
		->subject('New subscriber confirmed')
		->body("$email confirmed subscribe to $list->title")
		->send();
});

/***************************************************************************************
 * Mailgun analytics hook (via @jens.martsch)
 *
 * To enable Mailgun analytics for different newsletters/mails, you can use tags,
 * in this case using the email subject as a tag for analytics/tracking in Mailgun.
 * This hook requires the WireMailMailgun module to be installed. More about
 * Mailgun tags can be found here:
 *
 * https://documentation.mailgun.com/en/latest/user_manual.html#tagging
 *
 */
$wire->addHookAfter('ProMailerEmail::subscriberMessageReady', function(HookEvent $event) {
	/** @var array $data */
	$data = $event->arguments(0);
	/** @var WireMail $mailer */
	$mailer = $data['mailer'];
	if($mailer->className() == 'WireMailMailgun' 
		&& (method_exists($mailer, 'addTag') || method_exists($mailer, '___addTag'))) {
		$mailer->addTag($data['subject']);
	}
});