<?php namespace ProcessWire;

/**
 * ProcessWire Form Builder Processor: Spam
 *
 * Copyright (C) 2023 by Ryan Cramer Design, LLC
 *
 * PLEASE DO NOT DISTRIBUTE
 *
 */

class FormBuilderProcessorSpam extends Wire {

	/**
	 * @var FormBuilderProcessor
	 *
	 */
	protected $processor;

	/**
	 * @var array 
	 * 
	 */
	protected $honeypots = array();

	/**
	 * Name of spam filter that detected spam or blank string when no spam (yet) detected
	 * 
	 * @var string
	 * 
	 */
	protected $isSpam = '';

	/**
	 * Construct
	 *
	 * @param FormBuilderProcessor $processor
	 *
	 */
	public function __construct(FormBuilderProcessor $processor) {
		parent::__construct();
		$this->processor = $processor;
		$honeypots = $this->processor->honeypot;
		$this->honeypots = is_array($honeypots) ? $honeypots : array($honeypots);
	}

	/**
	 * Form render ready
	 * 
	 * @param InputfieldForm $form
	 * @since 0.5.5
	 * 
	 */
	public function renderReady(InputfieldForm $form) {
		if(!empty($this->honeypots)) {
			foreach($this->honeypots as $n => $honeypot) {
				if(empty($honeypot) || !$n) continue; // apply to 2nd+ honeypots only
				$f = $form->getChildByName($honeypot);
				if(!$f) continue;
				$f->addClass('wrap_Inputfield-', 'wrapClass');
			}
		}
	}

	/**
	 * Form rendered
	 * 
	 * @param string $out
	 * @since 0.5.5
	 * 
	 */
	public function rendered(&$out) {
		if(!empty($this->honeypots)) {
			$honeypot = trim(reset($this->honeypots)); 
			// apply to 1st honeypot only
			if($honeypot) {
				$out = str_replace("wrap_Inputfield_$honeypot'", "wrap_Inputfield-'", $out);
				$out = str_replace("wrap_Inputfield_$honeypot\"", "wrap_Inputfield-\"", $out);
			}
		}
	}

	/**
	 * Does given processed form contain spam?
	 *
	 * - Returns name of spam filter that was triggered if yes.
	 * - Returns blank string if no.
	 *
	 * Note: form will fail silently if spam is detected, unless spam filter adds $form->error().
	 *
	 * @param InputfieldForm $form
	 * @param bool $processed Call once before form processed (false), once after (true)
	 * @return string
	 * @since 0.4.7
	 *
	 */
	public function isSpam(InputfieldForm $form, $processed) {
		return $processed ? $this->isSpam2($form) : $this->isSpam1($form);
	}

	/**
	 * Check for spam after processInput
	 * 
	 * @param InputfieldForm $form
	 * @return string
	 * 
	 */
	protected function isSpam1(InputfieldForm $form) {
		if($form) {}
		$input = $this->wire()->input;

		// check honeypots
		foreach($this->honeypots as $honeypot) {
			$honeypot = trim($honeypot);
			if(empty($honeypot)) continue;
			$value = $input->post($honeypot);
			if(!empty($value)) {
				$this->isSpam = 'honeypot';
				break;
			}
		}
		
		return $this->isSpam;
	}

	/**
	 * Check for spam after processInput
	 *
	 * @param InputfieldForm $form
	 * @return string
	 *
	 */
	protected function isSpam2(InputfieldForm $form) {
		
		// perform turing test
		if($this->processor->turingTest) {
			if($this->turingTest($form, $this->processor->turingTest)) {
				$this->isSpam = 'turingTest';
				return $this->isSpam;
			}
		}

		// check for spam words
		if(is_array($this->processor->spamWords) && count($this->processor->spamWords)) {
			if($this->spamWords($form, $this->processor->spamWords)) {
				$this->isSpam = 'keywords';
				return $this->isSpam;
			}
		}

		// perform Akismet spam filtering
		if($this->processor->akismet && !count($form->getErrors())) {
			if($this->akismet($form, $this->processor->akismet)) {
				$this->isSpam = 'akismet';
				return $this->isSpam;
			}
		}
		
		return '';
	}

	/**
	 * Check the submission against a turing test, when enabled
	 *
	 * @param InputfieldForm $form
	 * @param array $turingTests
	 * @return bool True if spam, false if not
	 *
	 */
	public function turingTest(InputfieldForm $form, $turingTests) {
		$isSpam = false;
		foreach($turingTests as $fieldName => $answer) {
			$field = $form->getChildByName($fieldName);
			if(!$field instanceof Inputfield) continue;
			$answer = strtolower($answer);
			$answers = strpos($answer, '|') ? explode('|', $answer) : array($answer);
			foreach($answers as $key => $value) {
				$answers[$key] = trim($value); 
			}
			$value = trim(strtolower((string) $field->attr('value')));
			if(!in_array($value, $answers, true)) {
				$field->error($this->_('Incorrect answer'));
				$isSpam = true;
			}
		}
		return $isSpam;
	}

	/**
	 * Check the submission against Akismet, when enabled
	 *
	 * Akismet check is not performed if other errors have already occurred.
	 *
	 * @param InputfieldForm $form
	 * @param string $akismet
	 * @return bool Returns true if spam, false if not
	 *
	 */
	public function akismet(InputfieldForm $form, $akismet) {

		$parts = explode(',', $akismet);
		while(count($parts) < 3) $parts[] = '';
		list($author, $email, $content) = $parts;

		$author = $form->getChildByName($author);
		$author = $author ? $author->attr('value') : '';

		$email = $form->getChildByName($email);
		$email = $email ? $email->attr('value') : '';

		$content = $form->getChildByName($content);
		$content = $content ? $content->attr('value') : '';

		require_once(dirname(__FILE__) . '/FormBuilderAkismet.php');

		/** @var FormBuilder $forms */
		$forms = $this->wire()->forms;
		$akismet = new FormBuilderAkismet($forms->akismetKey);

		if($akismet->isSpam($author, $email, $content)) {
			if($this->wire()->config->debug) {
				$this->processor->addError($this->_('Spam filter has been triggered'));
			} else {
				$this->processor->addError($this->_('Unable to process form submission'));
			}
			return true;
		}

		return false;
	}

	/**
	 * Check the submission against Akismet, when enabled
	 *
	 * Akismet check is not performed if other errors have already occurred.
	 *
	 * @param InputfieldForm $form
	 * @param array $spamWords
	 * @return bool True if spam, false if not
	 *
	 */
	public function spamWords(InputfieldForm $form, $spamWords) {

		if(!is_array($spamWords) || !count($spamWords)) return false;
		
		$isSpam = false;
		$operators = array('*=', '~=', '%=', '^=', '$=', '='); // note the '=' operator must be last

		// first check 'field_name=keyword' versions
		foreach($spamWords as $key => $spamWord) {

			$operator = '%=';
			$spamWord = trim($spamWord);

			if(!strlen($spamWord)) unset($spamWords[$key]);
			if(strpos($spamWord, '=') === false) continue;

			while(strpos($spamWord, '  ')) {
				$spamWord = str_replace('  ', ' ', $spamWord);
			}

			foreach($operators as $op) {
				if(strpos($spamWord, $op) !== false) {
					$operator = $op;
					break;
				}
			}

			if(strpos($spamWord, $operator) !== false) {
				list($fieldName, $spamWord) = explode($operator, $spamWord, 2);
			} else {
				$fieldName = '';
			}

			$fieldName = trim($fieldName);
			$spamWord = trim($spamWord);
			$inputfield = null;
			$values = array();
			$not = false;

			if($fieldName && strpos($fieldName, '!') !== false) {
				$not = true;
				$fieldName = trim($fieldName, '!');
			}

			if(strlen($fieldName)) {
				// single field
				if(isset($_POST[$fieldName])) {
					$value = $_POST[$fieldName];
				} else {
					$inputfield = $form->getChildByName(trim($fieldName));
					$value = $inputfield ? $inputfield->val() : '';
				}
				if(is_array($value)) {
					$values = $value;
				} else {
					$values[] = trim("$value");
				}
			} else {
				// all fields
				foreach($_POST as $value) {
					if(is_array($value)) {
						foreach($value as $v) {
							if(is_string($v) && !empty($v)) $values[] = $v;
						}
					} else if(is_string($value) && !empty($value)) {
						$values[] = $value;
					}
				}
			}

			foreach($values as $value) {
				if(is_array($value)) continue;
				$value = trim($value);
				switch($operator) {
					case '*=':
					case '~=':
						$spamWord = preg_quote($spamWord);
						$spamWord = str_replace(' ', '\s+', $spamWord);
						$re = '/\b' . $spamWord . ($operator === '~=' ? '\b' : '') . '/is';
						$isSpam = preg_match($re, $value);
						break;
					case '%=':
						$isSpam = stripos($value, $spamWord) !== false;
						break;
					case '=':
						$isSpam = strtolower($value) === strtolower($spamWord);
						break;
					case '^=':
						$isSpam = stripos($value, $spamWord) === 0;
						break;
					case '$=':
						$value = substr($value, -1 * strlen($spamWord));
						$isSpam = strtolower($value) === strtolower($spamWord);
						break;
					default:
						$isSpam = stripos($value, $spamWord) !== false;
				}
				if($not) $isSpam = !$isSpam;
				if($isSpam) break;
			}
			unset($spamWords[$key]);
			if($isSpam) break;
		}

		if(!$isSpam) {
			// next check for keywords that appear anywhere in POST data
			foreach($spamWords as $spamWord) {
				foreach($_POST as /* $key => */ $value) {
					$isSpam = stripos($value, $spamWord) !== false;
					if($isSpam) break;
				}
				if($isSpam) break;
			}
		}

		return $isSpam;
	}

}