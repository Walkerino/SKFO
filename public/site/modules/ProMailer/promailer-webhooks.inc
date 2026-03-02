<?php namespace ProcessWire;

/** 
 * Capture Mailgun errors (example)
 * 
 * This example is setup to respond to “Permanent Failure” and/or “Temporary Failure”
 * webhooks from the “Mailgun” service to record a bounce. 
 * 
 * When specifying the webhook URL in Mailgun, use the URL to your page using the 
 * promailer-subscribe template, and append “?webhook=mailgun” to the URL, for example: 
 * 
 * https://domain.com/subscribe/?webhook=mailgun
 * 
 * While this example uses Mailgun, the strategy would be similar for other email
 * delivery providers. Webhooks do require that you are using an email delivery provider
 * that supports them. If you need help, post to the ProMailer board with more
 * information about your email delivery provider and we can get a closer look and assist
 * with suggestions on how to set it up. 
 * 
 * To use, place this file in /site/templates/promailer-webhooks.inc
 * 
 */

/** @var ProcessWire $wire */
/** @var WireInput $input */
/** @var ProMailer $promailer $promailer */
/** @var Modules $modules */

if(!defined("PROCESSWIRE")) die();

$promailer = $modules->get('ProMailer');
$webhook = $input->get->name('webhook');
$isPost = $input->requestMethod('POST');

if($isPost && $webhook === 'mailgun' && $modules->isInstalled('WireMailMailgun')) {
	// Mailgun webhooks

	// decode JSON submitted data in request body
	$data = json_decode(file_get_contents('php://input'), true);

	// if it wasn’t JSON or not in the right format, throw a 404
	if(!is_array($data) || !isset($data['signature']) || !is_array($data['signature'])) {
		// submitted data does not match that of a Mailgun webhook
		throw new Wire404Exception();
	}
		
	// appears to be webhook from Mailgun, validate that it really is from them
	// https://documentation.mailgun.com/en/latest/user_manual.html#webhooks
	$sig = $data['signature'];
	$data = $data['event-data'];
	$apiKey = $modules->getConfig('WireMailMailgun', 'apiKey');
	$sigHash = hash_hmac('sha256', "$sig[timestamp]$sig[token]", $apiKey);
	
	if($sigHash !== $sig['signature']) {
		// submission not secured by valid Mailgun signature (maybe someone hacking around)
		throw new WireException('Invalid webhook request');
	}
	
	// signature matches indicating this is a valid webhook from Mailgun
	// now see what kind of event we are getting and handle it appropriately
	$success = true;
	
	if($data['event'] === 'failed') {
		// Handler for Mailgun “Permament Failure” or “Temporary Failure” webhooks
		// record a bounce if delivery failed for email address
		$success = $promailer->subscribers->bounce($data['recipient']);
		
	} else if($data['event'] === 'complained') {
		// Handler for Mailgun “Spam Complaints” webhook
		// user flagged message as spam, remove them from ALL lists
		$success = $promailer->subscribers->unsubscribe($data['recipient'], true); 
		
	} else if($data['event'] === 'opened') {
		// Handler for Mailgun “Opens” webhook
		// user opened/viewed the message
		
	} else if($data['event'] === 'clicked') {
		// Handler for Mailgun “Clicks” webhook
		// user clicked a link in the message
		
	} else {
		// some other event
	}

	echo $success ? "OK" : "FAIL";
	exit;
}

