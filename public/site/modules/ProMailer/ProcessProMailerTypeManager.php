<?php namespace ProcessWire;

abstract class ProcessProMailerTypeManager extends Wire {

	/** @var ProMailer Promailer */
	protected $promailer;
	
	/** @var ProcessProMailer $process */
	protected $process;

	public function __construct(ProMailer $promailer, ProcessProMailer $process) {
		$this->promailer = $promailer;
		$this->process = $process;
		$promailer->wire($this);
		parent::__construct();
	}

	public function label($key) {
		return $this->process->label($key);
	}
}