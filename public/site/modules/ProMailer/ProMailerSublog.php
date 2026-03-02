<?php namespace ProcessWire;

/**
 * ProMailer Subscriber log 
 * 
 * Not currently active (work in progress for future use)
 *
 * 
 */
class ProMailerSublog extends Wire {

	const actionAuto = 0;
	const actionUser = 1;
	const actionAdmin = 2;

	const typeAdminAdd = 1; // manually added
	const typeSubscribe = 2; // user requested subscribe
	const typeConfirm = 3;
	const typeAdminConfirm = 4; // user confirmed
	const typeUnsubscribe = 16;
	const typeSend = 32;
	const typeSendFail = 64;
	const typeBounce = 128;
	const typeComplaint = 256;
	
	protected $names = array();
	protected $labels = array();
	protected $promailer;
	
	public function __construct(ProMailer $promailer) {
		parent::__construct();
		$this->promailer = $promailer;
		
		$this->names = array(
			10 => 'subscribeRequest',
			11 => 'subscribeImport',
			15 => 'subscribeFail',
			20 => 'confirmSend',
			21 => 'confirmResend',
			25 => 'confirmFail',
			30 => 'confirmed',
			31 => 'confirmedAdmin',
			35 => 'confirmedFail',
			40 => 'unsubscribeRequest',
			45 => 'unsubscribeFail',
			50 => 'unsubscribed', 
			51 => 'unsubscribedAdmin',
			60 => 'messageSend',
			65 => 'messageFail',
			70 => 'bounce',
			71 => 'complaint',
		);
		
		$this->labels = array(
			10 => $this->_('Subscribe request from user on front-end subscribe form'), 
			11 => $this->_('Manually added by import from admin user or API'),
			15 => $this->_('Subscribe failure'),
			20 => $this->_('Send double opt-in confirmation email'), 
			21 => $this->_('Manually re-send double opt-in confirmation email'), 
			25 => $this->_('Failed to send confirmation email'),
			// ... continue from here
		);

	}
	
	public function table() {
		return 'promailer_sublog';
	}

	public function install() {
		$database = $this->wire()->database;
		$engine = $this->wire()->config->dbEngine;
		$charset = $this->wire()->config->dbCharset;
		$table = $this->table();

		$database->exec("
			CREATE TABLE `$table` (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				subscriber_id INT UNSIGNED NOT NULL, 
				list_id INT UNSIGNED NOT NULL, 
				message_id INT UNSIGNED DEFAULT 0,
				user_id INT UNSIGNED DEFAULT 0,
				data TEXT DEFAULT NULL,
				created INT UNSIGNED NOT NULL DEFAULT 0,
				INDEX subscriber_id (subscriber_id),
				INDEX list_id (list_id),
				INDEX user_id (user_id),
				INDEX message_id (message_id),
				INDEX created (created),
			) ENGINE=$engine DEFAULT CHARSET=$charset
		");
	}
	
}