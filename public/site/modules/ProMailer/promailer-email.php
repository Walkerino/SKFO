<?php namespace ProcessWire;

/**
 * This is an example of what you might use for a ProcessWire template file for an email
 * 
 * Please see this URL for these instructions in HTML:
 * https://processwire.com/store/pro-mailer/manual/#working-with-email-page-template-files
 *
 * PLACEHOLDERS
 * ============
 * There are several {placeholders} that you can use which will be automatically populated
 * in the email:
 *
 * - {email} Email address of recipient (TO email)
 * - {from_email} Email address of the sender (FROM email)
 * - {from_name} Name of the sender
 * - {subject} Subject of the message
 * - {title} Title of the message (as identified in ProMailer admin)
 * - {unsubscribe_url} URL to unsubscribe user from this list
 * - {subscribe_url} URL to subscribe to this list
 * - PLUS any custom fields that you defined
 *
 * Note: if you are viewing a page using this template on its own (without ProMailer)
 * then none of these placeholders will be populated. That’s to be expected.
 *
 * URL VARIABLES
 * =============
 * Each rendered message will also receive several GET variables in the URL. The only
 * one that you may initially want is the `$type` variable, but the others are also present
 * should you ever want them:
 *
 * - $input->get('type');          // Message type, either "html" or "text" (string)
 * - $input->get('subscriber_id'); // ID of subscriber (int)
 * - $input->get('message_id');    // ID of message (int)
 * - $input->get('list_id');       // ID of subscriber list (int)
 * 
 * Note: if you are viewing a page using this template on its own (without ProMailer)
 * then none of these GET variables will be present, unless you populate them yourself.
 *
 * TIPS
 * ====
 * Use absolute (not relative) URLs for links to any assets such as other URLs or images.
 * For instance, your URLs should begin with "https://domain.com/" and not "/". However,
 * there are places where you may not have control over this (like in CKEditor body copy),
 * so do not worry, ProMailer will convert any relative URLs to absolute for you.
 *
 * Email clients can sometimes be pretty primitive with their HTML rendering ability,
 * especially Microsoft Outlook. But even Gmail has its quirks. If you don’t have the time
 * or patience to test your email in various email clients, one option is to use an
 * existing HTML/CSS email framework. Here are a few examples:
 *
 *  - Foundation for Emails: https://foundation.zurb.com/emails.html
 *  - MJML (Mail Jet Markup Language): https://mjml.io
 *  - Maizzle: https://maizzle.com/
 *  - Cerberus: https://tedgoas.github.io/Cerberus/
 *
 */

if(!defined("PROCESSWIRE")) die();

/** @var Page $page */
/** @var WireInput $input */
/** @var Sanitizer $sanitizer */
/** …and all the other ProcessWire API variables */

// HTML email output
if($input->get('type') === 'html') { ?>
<html>
    <head>
        <title>{subject}</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1">
        <base target="_blank">
        <style>
            body {
                background-color: #F0F1F3;
                font-family: 'Helvetica Neue', 'Segoe UI', Helvetica, sans-serif;
                font-size: 15px;
                line-height: 26px;
                margin: 0;
                color: #444;
            }

            pre {
                background: #f4f4f4f4;
                padding: 2px;
            }

            table {
                width: 100%;
                border: 1px solid #ddd;
            }
            table td {
                border-color: #ddd;
                padding: 5px;
            }

            .wrap {
                background-color: #fff;
                padding: 30px;
                max-width: 525px;
                margin: 0 auto;
                border-radius: 5px;
            }

            .button {
                background: #0055d4;
                border-radius: 3px;
                text-decoration: none !important;
                color: #fff !important;
                font-weight: bold;
                padding: 10px 30px;
                display: inline-block;
            }
            .button:hover {
                background: #111;
            }

            .footer {
                text-align: center;
                font-size: 12px;
                color: #888;
            }
                .footer a {
                    color: #888;
                    margin-right: 5px;
                }

            .gutter {
                padding: 30px;
            }

            img {
                max-width: 100%;
                height: auto;
                border-radius: 5px;
            }

            a {
                color: #0055d4;
            }
                a:hover {
                    color: #111;
                }
            @media screen and (max-width: 600px) {
                .wrap {
                    max-width: auto;
                }
                .gutter {
                    padding: 10px;
                }
            }
        </style>
    </head>
<body style="background-color: #F0F1F3;font-family: 'Helvetica Neue', 'Segoe UI', Helvetica, sans-serif;font-size: 15px;line-height: 26px;margin: 0;color: #444;">
    <div class="gutter" style="padding: 30px;">&nbsp;</div>
    <div class="wrap" style="background-color: #fff;padding: 30px;max-width: 525px;margin: 0 auto;border-radius: 5px;">
	    <h1><?=$page->title?></h1>
	    <? if (!empty($page->splash)): ?><img src="<?=$page->splash->url?>" border="0"><? endif; ?>
        <?=$page->get('body')?>
    </div>
    
    <div class="footer" style="text-align: center;font-size: 12px;color: #888;">
        <p>
            <a href="{unsubscribe_url}" style="color: #888;">Click here to unsubscribe</a>
        </p>
    </div>
    <div class="gutter" style="text-align: center;padding: 30px;"><?=$page->title?></div>
</body>
</html>
	<?php

} else if($input->get('type') === 'text') {

	// Text-based email output (optional)
	if($input->get('preview')) header('content-type: text/plain');
	echo $sanitizer->getTextTools()->markupToText(strtoupper($page->title) . "\n\n" . $page->get('body'));
	echo "\n\n---\n\nTo unsubscribe visit: {unsubscribe_url}";

} else { // show preview links to our text and HTML emails ?>

	<html>
	<body>
		<ul>
			<li><a href='./?type=html&preview=1'>Preview HTML email</a></li>	
			<li><a href='./?type=text&preview=1'>Preview TEXT-only email</a></li>
			<?php if($page->editable()) echo "<li><a href='$page->editUrl'>Edit this email</a></li>"; ?>
		</ul>
	</body>
	</html>

	<?php
} // finished



