<?php namespace ProcessWire;

/**
 * FormBuilder Bootstrap 5 framework definition file
 * 
 * @property int $horizontal
 * @property string $bootHorizHeaderClass
 * @property string $bootHorizContentClass
 * @property string $bootURL
 * @property string $inputSize
 * @property string $buttonType
 * @property string $buttonSize
 * @property string $buttonFull
 *
 */

class FormBuilderFrameworkBootstrap5 extends FormBuilderFramework {

	/**
	 * Load framework
	 * 
	 */
	public function load() {
	
		$fieldset = array(
			'markup' => array(
				'item' => "<fieldset {attrs}><div class='card-body'>{out}</div></fieldset>",
				'item_label' => "<legend class='card-title'>{out}</legend>",
				'item_label_hidden' => "<div class='InputfieldHeader InputfieldHeaderHidden'>{out}</div>",
				'item_description' => "<p class='form-text text-muted'>{out}</p>",
			),
			'classes' => array(
				'item' => 'card fieldset Inputfield InputfieldFieldset'
			),
		);
		
		$markup = array(
			'list' => "<div {attrs}>{out}</div>",
			'item' => "<div {attrs}>{out}</div>",
			'item_label' => "<label class='InputfieldHeader form-label' for='{for}'>{out}</label>",
			'item_label_hidden' => "<label class='InputfieldHeader InputfieldHeaderHidden'><span>{out}</span></label>",
			'item_content' => "<div class='InputfieldContent {class}'>{out}</div>",
			'item_error' => "<p class='text-danger'>{out}</p>",
			'item_description' => "<p class='form-text text-muted'>{out}</p>",
			'item_notes' => "<p class='form-text text-muted'>{out}</p>",
			'success' => "<div class='alert alert-success'>{out}</div>",
			'error' => "<div class='alert alert-danger'>{out}</div>",
			'item_icon' => "",
			'item_toggle' => "",
			'InputfieldFieldset' => $fieldset['markup'],
			'InputfieldCombo' => array(
				'item_label' => "<h5 class='InputfieldHeader'>{out}</h5>",
			),
		);

		$classes = array(
			'form' => 'InputfieldFormNoHeights',
			'list' => 'Inputfields',
			'list_clearfix' => 'clearfix',
			'item' => 'Inputfield Inputfield_{name} {class}',
			'item_required' => 'InputfieldStateRequired',
			'item_error' => 'InputfieldStateError has-error',
			'item_collapsed' => 'InputfieldStateCollapsed',
			'item_column_width' => 'InputfieldColumnWidth',
			'item_column_width_first' => 'InputfieldColumnWidthFirst',
			'InputfieldCheckbox' => array('item_content' => 'form-check'),
			'InputfieldRadios' => array('item_content' => 'radio'),
			'InputfieldFieldset' => $fieldset['classes'],
		);

		InputfieldWrapper::setMarkup($markup);
		InputfieldWrapper::setClasses($classes);

		$config = $this->wire()->config;
		$framework = $this;
		$frURL = $this->bootURL;
		$fbURL = $config->urls('FormBuilder');
		
		if(strpos($frURL, '//') !== false) {
			$frURL = rtrim($frURL, '/');
		} else {
			$frURL = $config->urls->root . trim($frURL, '/');
		}
		
		if($this->allowLoad('framework')) {
			$config->styles->prepend("$frURL/css/bootstrap.min.css");
			$config->scripts->append("$frURL/js/bootstrap.min.js");
		}
	
		$config->styles->append($fbURL . 'FormBuilder.css');
		$config->styles->append($fbURL . 'frameworks/FormBuilderFrameworkBootstrap5.css');
		$config->inputfieldColumnWidthSpacing = 0;

		// load custom theme stylesheets, where found
		if(!$this->form->theme) $this->form->theme = 'delta';

		$this->addHookBefore('FormBuilderProcessor::renderReady', function(HookEvent $event) use($framework) {
			$inputfields = $event->arguments(0);
			foreach($inputfields->getAll() as $in) {
				$framework->renderReadyInputfield($in);
			}
		}); 

		$this->addHookBefore('InputfieldCheckboxes::render, InputfieldRadios::render', function(HookEvent $event) {
			/** @var InputfieldCheckboxes|InputfieldRadios $in */
			$in = $event->object;
			$in->addClass('form-check-input');
		}); 
		
		$this->addHookAfter('InputfieldCheckboxes::render, InputfieldRadios::render', function(HookEvent $event) {
			/** @var string $out */
			$out = $event->return;
			$out = str_replace('<li', '<li class="form-check"', $out);
			$out = str_replace('<label', '<label class="form-check-label"', $out);
			$event->return = $out;
		});
		
		$this->addHookBefore('InputfieldSubmit::render', function(HookEvent $event) {
			/** @var InputfieldSubmit $in */
			$in = $event->object;
			$event->replace = true;
			$classes = array('btn');
			$classes[] = $this->buttonType ? "btn-$this->buttonType" : "btn-primary";
			if($this->buttonSize) $classes[] = "btn-$this->buttonSize";
			if($this->buttonFull) $classes[] = "btn-block";
			$class = implode(' ', $classes);
			$value = $in->attr('value');
			$value1 = $this->wire()->sanitizer->entities($value);
			// $value2 = $in->entityEncode($value, Inputfield::textFormatBasic);
			$value2 = $in->html ? $in->html : $in->entityEncode($in->get('text|value'), Inputfield::textFormatBasic);
			$event->return = "<button type='submit' name='$in->name' value='$value1' class='$class'>$value2</button>";
		});
	}
	
	/**
	 * Prepare inputfield for render
	 *
	 * @param Inputfield $in
	 * @throws WireException
	 *
	 */
	public function renderReadyInputfield(Inputfield $in) {
		
		$inputSize = $this->inputSize; 
		$ifc = $in->getSetting('inputfieldClass');
		$cn = $in->className();
		
		if($in instanceof InputfieldCheckboxes || $ifc === 'InputfieldCheckboxes') {
			// ok: handled by hook
			
		} else if($in instanceof InputfieldRadios || $ifc === 'InputfieldRadios') {
			// ok: handled by hook
			
		} else if($in instanceof InputfieldPage) {
			if(strpos($in->get('inputfield'), 'InputfieldSelect') !== false) {
				$in->addClass("form-select custom-select");
				if($inputSize) $in->addClass(str_replace('form-control-', 'custom-select-', $inputSize));
			}
			
		} else if($cn == 'InputfieldSelect') {
			$in->addClass("form-select custom-select");
			if($inputSize) $in->addClass(str_replace('form-control-', 'custom-select-', $inputSize));

		} else if($cn == 'InputfieldSelectMultiple') {
			$in->addClass('form-select');

		} else if($cn == 'InputfieldCheckbox') {
			$in->addClass('form-check-input');
			$labelAttrs = $in->getSetting('labelAttrs');
			if(!isset($labelAttrs['class'])) $labelAttrs['class'] = '';
			$labelAttrs['class'] = trim("$labelAttrs[class] form-check-label");
			$in->set('labelAttrs', $labelAttrs);
			
		} else if($cn === 'InputfieldCombo') {
			/** @var InputfieldCombo $in */
			foreach($in->getInputfields() as $i) {
				$this->renderReadyInputfield($i);
			}
			
		} else if($cn === 'InputfieldFormBuilderFile') {
			/** @var InputfieldFormBuilderFile $in */
			$in->set('checkboxClass', 'form-check-input');
			$in->set('descClass', 'form-control');
			$in->set('itemTag', 'div');
			$in->set('itemClass', 'card mb-2');
			$in->set('itemHeaderTag', 'p');
			$in->set('itemHeaderClass', 'card-header');
			$in->set('itemBodyClass', 'card-body');
			$in->set('addFileClass', 'btn btn-sm btn-outline-primary');
			$in->addClass('form-control'); 

		} else {
			// all others receive form-control class
			$in->addClass("form-control");
			if($inputSize) $in->addClass($inputSize);
		}
	}

	/**
	 * Return Inputfields for configuration of framework
	 *
	 * @return InputfieldWrapper
	 *
	 */
	public function getConfigInputfields() {
	
		$modules = $this->wire()->modules;
		$inputfields = parent::getConfigInputfields();
		$defaults = $this->getConfigDefaults();
		$defaultLabel = $this->_('Default value:') . ' ';

		/** @var InputfieldURL $f */
		$f = $modules->get('InputfieldURL'); 
		$f->attr('name', 'bootURL'); 
		$f->label = $this->_('URL to Bootstrap framework'); 
		$f->description = $this->_('Specify a URL/path relative to root of ProcessWire installation.'); 
		$f->attr('value', $this->bootURL); 
		if($this->bootURL != $defaults['bootURL']) $f->notes = $defaultLabel . $defaults['bootURL']; 
		$inputfields->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'inputSize');
		$f->label = $this->_('Input size');
		$f->addOption('form-control-sm', $this->_('Small'));
		$f->addOption('', $this->_x('Normal', 'sizeType'));
		$f->addOption('form-control-lg', $this->_('Large'));
		$f->attr('value', $this->inputSize);
		$f->columnWidth = 34;
		$inputfields->add($f);
		
		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios'); 
		$f->attr('name', 'buttonType'); 
		$f->label = $this->_('Submit button type'); 
		$f->addOption('', $this->_x('Default', 'buttonType'));
		$f->addOption('primary', 'Primary');
		$f->addOption('secondary', 'Secondary');
		$f->addOption('success', $this->_('Success'));
		$f->addOption('danger', $this->_('Danger'));
		$f->addOption('warning', $this->_('Warning'));
		$f->addOption('info', $this->_('Info'));
		$f->addOption('light', $this->_('Light'));
		$f->addOption('dark', $this->_('Dark'));
		$f->addOption('link', $this->_('Link'));
		$f->attr('value', $this->buttonType); 
		$f->columnWidth = 33; 
		$inputfields->add($f); 

		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'buttonSize');
		$f->label = $this->_('Submit button size'); 
		$f->addOption('sm', $this->_('Small'));
		$f->addOption('', $this->_('Medium (default)')); 
		$f->addOption('lg', $this->_('Large'));
		$f->attr('value', $this->buttonSize);
		$f->columnWidth = 33; 
		$inputfields->add($f);

		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox'); 
		$f->attr('name', 'buttonFull'); 
		$f->label = $this->_('Full width button?'); 
		if($this->buttonFull) $f->attr('checked', 'checked');
		$f->columnWidth = 34; 
		$inputfields->add($f); 
		
		return $inputfields;
	}

	/**
	 * Get default config settings
	 * 
	 * @return array
	 * 
	 */
	public function getConfigDefaults() {
		$config = $this->wire()->config;
		$bootURL = str_replace($config->urls->root, '/', $config->urls('FormBuilder') . 'frameworks/bootstrap5/');
		return array_merge(parent::getConfigDefaults(), array(
			'horizontal' => 0,
			'bootURL' => $bootURL, 
			'inputSize' => '',
			'buttonType' => '',
			'buttonSize' => '',
			'buttonFull' => 0, 
		));
	}

	/**
	 * Get framework URL
	 * 
	 * @return string
	 * 
	 */
	public function getFrameworkURL() {
		return $this->bootURL;
	}

	/**
	 * Get the framework version
	 *
	 * @return string
	 *
	 */
	static public function getFrameworkVersion() {
		return '5.0.1';
	}

}
