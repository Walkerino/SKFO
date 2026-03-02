<?php namespace ProcessWire;

/**
 * FormBuilder Uikit 3.x framework definition file
 *
 * @property string $ukURL
 * @property string $inputSize
 * @property string $buttonSize
 * @property string $buttonType
 * @property int $buttonFull
 * @property int $boldLabels
 * @property bool $horizontal
 * @property int $horizHeaderWidth
 * @property int $inlineErrorBelow
 * @property string $css
 *
 */

class FormBuilderFrameworkUikit3 extends FormBuilderFramework {

	/**
	 * Load framework
	 * 
	 */
	public function load() {
		
		$config = $this->wire()->config;
		
		$markup = array(
			'list' => "<div {attrs}>{out}</div>",
			'item' => "<div {attrs}>{out}</div>",
			'item_label' => "<label class='InputfieldHeader uk-form-label' for='{for}'>{out}</label>",
			'item_label_hidden' => "<label class='InputfieldHeader InputfieldHeaderHidden'><span>{out}</span></label>",
			'item_content' => 
				"<div class='InputfieldContent uk-form-controls {class}'>" . 
					($this->inlineErrorBelow ? "{out}{error}" : "{out}") . 
				"</div>",
			'item_error' => "<p class='uk-text-danger uk-text-small uk-margin-small'><span uk-icon='icon: warning'></span> {out}</p>",
			'item_description' => "<p class='uk-text-muted uk-text-small uk-margin-small'>{out}</p>",
			'item_notes' => "<p class='uk-text-small uk-text-muted uk-margin-small'>{out}</p>",
			'success' => 
				"<div class='uk-alert uk-alert-success' uk-alert>" . 
					"<span uk-icon='icon: check'></span> {out} " . 
					"<a class='uk-alert-close' uk-close></a>" . 
				"</div>",
			'error' => 
				"<div class='uk-alert uk-alert-danger' uk-alert>" . 
					"<span uk-icon='icon: warning'></span> {out} " . 
					"<a class='uk-alert-close' uk-close></a>" . 
				"</div>",
			'item_icon' => "",
			'item_toggle' => "",
			'InputfieldFieldset' => array(
				'item' => "<fieldset {attrs}>{out}</fieldset>",
				'item_label' => "<legend class='uk-legend'>{out}</legend>",
				'item_label_hidden' => "<legend>{out}</legend>",
				'item_content' => "<div class='InputfieldContent'>{out}</div>",
				'item_description' => "<p class='uk-text-muted'>{out}</p>",
			),
		);

		$classes = array(
			'form' => 'InputfieldFormNoHeights',
			'list' => 'Inputfields',
			'list_clearfix' => 'uk-clearfix',
			'item' => 'Inputfield Inputfield_{name} {class}',
			'item_required' => 'InputfieldStateRequired',
			'item_error' => 'InputfieldStateError',
			'item_collapsed' => 'InputfieldStateCollapsed',
			'item_column_width' => 'InputfieldColumnWidth',
			'item_column_width_first' => 'InputfieldColumnWidthFirst',
			'InputfieldCheckboxes' => array(
				'item_content' => 'uk-form-controls-text'
			),
			'InputfieldCheckbox' => array(
				'item_content' => 'uk-form-controls-text'
			),
			'InputfieldRadios' => array(
				'item_content' => 'uk-form-controls-text'
			),
			'InputfieldFieldset' => array(
				'item' => 'Inputfield Inputfield_{name} uk-fieldset {class}'
			)
		);
		
		if($this->boldLabels) $classes['form'] .= ' InputfieldFormBoldLabels';
		
		if((int) $this->horizontal) {
			// for uk-form-horizontal
			$classes['form'] .= " uk-form-horizontal InputfieldFormNoWidths";
			$markup['item_content'] = "<div class='InputfieldContent uk-form-controls {class}'>{out}{description}{notes}</div>";

			// the following duplicates the styles from uikit.css, but uses our custom widths
			// this is necessary because uikit only supports horizontal forms at 960px and above
			// and the width (200px) is fixed, when we need variable/user definable width.
			$mobilePx = $this->form->mobilePx;
			if(ctype_digit("$mobilePx")) $mobilePx = ((int) $mobilePx) . "px";
			if($mobilePx != '1px') $this->addInlineStyles("
				@media (min-width: $mobilePx) {
					.InputfieldForm.uk-form-horizontal .uk-form-label {
						display: block;
						margin-bottom: 5px;
						font-weight: bold;
					}
				}
				@media (min-width: $mobilePx) {
					.InputfieldForm.uk-form-horizontal .uk-form-label {
						width: {$this->horizHeaderWidth}%; 
						margin-top: 5px;
						float: left; 

					}
					.InputfieldForm.uk-form-horizontal .uk-form-controls {
						margin-left: {$this->horizHeaderWidth}%; 
						padding-left: 1em;
					}
					.InputfieldForm.uk-form-horizontal .uk-form-controls-text {
						padding-top: 5px;
					}
				}
				");
		} else {
			$classes['form'] .= " uk-form-stacked";
		}

		InputfieldWrapper::setMarkup($markup);
		InputfieldWrapper::setClasses($classes);

		$ukURL = $this->ukURL;
		if(strpos($ukURL, '//') !== false) {
			$ukURL = rtrim($ukURL, '/');
		} else {
			$ukURL = $config->urls->root . trim($ukURL, '/');
		}

		$css = $this->css;
		if(!$css) $css = 'uikit.min.css';

		if($this->allowLoad('framework')) {
			$config->styles->prepend("$ukURL/css/$css");
			$config->scripts->append("$ukURL/js/uikit.min.js");
			$config->scripts->append("$ukURL/js/uikit-icons.min.js");
		}
		
		$fbURL = $config->urls('FormBuilder');
		$config->styles->append($fbURL . 'frameworks/FormBuilderFrameworkUikit3.css');
		$config->styles->append($fbURL . 'FormBuilder.css');
		$config->inputfieldColumnWidthSpacing = 0;

		// load custom theme stylesheets, where found
		if(!$this->form->theme) $this->form->theme = 'delta';

		// hooks
		$this->addHookBefore('InputfieldSubmit::render', $this, 'hookInputfieldSubmitRender');
		$this->addHookBefore('FormBuilderProcessor::renderReady', $this, 'hookBeforeRenderReady');
		$this->addHookAfter('InputfieldFormBuilderFile::render', $this, 'hookAfterFileRender'); 
	}

	/**
	 * Hook after file render
	 * 
	 * @param HookEvent $event
	 * 
	 */
	public function hookAfterFileRender($event) {

		/** @var InputfieldFormBuilderFile $inputfield */
		// $inputfield = $event->object;
		
		if(!preg_match_all('!(<input[^>]+?type=.file.[^>]*>)!', $event->return, $matches)) return;
		
		$out = $event->return;
		$placeholder = $this->wire()->sanitizer->entities1($this->_('Select file'));
		
		foreach($matches[1] as /* $key => */ $match) {
			$rep =
				"<div class='uk-margin-small-right uk-margin-small-top' uk-form-custom='target: true'>" .
					$match . 
					"<input class='uk-input uk-form-width-medium' type='text' placeholder='$placeholder' disabled>" .
				"</div>";
			$out = str_replace($match, $rep, $out);
		}
		
		$event->return = $out;
	}

	/**
	 * Hook before render ready
	 * 
	 * @param HookEvent $event
	 * 
	 */
	public function hookBeforeRenderReady($event) {
		/** @var InputfieldWrapper $inputfields */
		$inputfields = $event->arguments(0);
		foreach($inputfields->getAll() as $in) {
			$this->renderReadyInputfield($in);
		}
	}

	/**
	 * Prepare inputfield for render
	 *
	 * @param Inputfield $in
	 * @throws WireException
	 *
	 */
	protected function renderReadyInputfield(Inputfield $in) {
		
		if($in instanceof InputfieldPage) {
			$cn = $in->inputfield;
		} else {
			$cn = $in->getSetting('inputfieldClass');
			if(empty($cn)) $cn = $in->className();
		}
		
		if($in instanceof InputfieldTextarea) {
			$in->addClass('uk-textarea');
			
		} else if($in instanceof InputfieldText || $in instanceof InputfieldInteger || $in instanceof InputfieldDatetime) {
			$in->addClass('uk-input');
			
		} else if($in instanceof InputfieldURL) {
			$in->addClass('uk-input');
			
		} else if($in instanceof InputfieldRadios || $cn === 'InputfieldRadios') {
			$in->addClass('uk-radio');
			
		} else if($cn == 'InputfieldSelect' || $cn == 'InputfieldAsmSelect' || $cn == 'InputfieldSelectMultiple') {
			$in->addClass('uk-select');
			
		} else if($cn == 'InputfieldCheckbox') {
			$in->addClass('uk-checkbox');
			
		} else if($cn == 'InputfieldCheckboxes') {
			$in->addClass('uk-checkbox');
			
		} else if($cn == 'InputfieldCombo') {
			foreach($in->getInputfields() as $i) {
				$this->renderReadyInputfield($i);
			}
		} else if($cn == 'InputfieldFormBuilderFile') {
			/** @var InputfieldFormBuilderFile $in */
			$in->set('checkboxClass', 'uk-checkbox');
			$in->set('descClass', $in->descRows > 1 ? 'uk-textarea' : 'uk-input');
			$in->set('itemTag', 'div'); 
			$in->set('itemClass', 'uk-card uk-card-default uk-card-body uk-margin-small'); 
			$in->set('itemHeaderTag', 'label');
			$in->set('itemHeaderClass', 'uk-display-block');
			$in->set('addFileClass', 'uk-button uk-button-primary uk-button-small'); 
		}
		
		if($this->inputSize) {
			if(!$in->hasClass('uk-checkbox') && !$in->hasClass('uk-radio')) {
				$in->addClass("uk-form-" . $this->inputSize);
				if($in->attr('size')) $in->removeAttr('size'); // Uikit does not apply size classes when input has size attr
			}
		}
		
		if(count($in->getErrors())) {
			$in->addClass('uk-form-danger');
			$in->addClass('uk-text-danger', 'headerClass');
			$in->appendMarkup .=
				"<script>if(typeof jQuery !== 'undefined') " . 
					"jQuery('#wrap_$in->id').find(':input:visible').on('focus', function() { " .
						"jQuery(this).removeClass('uk-form-danger').closest('.Inputfield').children('.InputfieldHeader').removeClass('uk-text-danger');" .
					"});" . 
				"</script>";
		}
	}

	/**
	 * Hook to replace submit button
	 * 
	 * @param HookEvent $event
	 * 
	 */
	public function hookInputfieldSubmitRender($event) {
		/** @var InputfieldSubmit $in */
		$in = $event->object;
		$event->replace = true;
		$classes = array('uk-button');
		$buttonType = $this->buttonType;
		if(!$buttonType) $buttonType = 'default';
		if($in->secondary) $buttonType = 'secondary';
		if($this->buttonSize) $classes[] = "uk-button-$this->buttonSize";
		if($this->buttonType) $classes[] = "uk-button-$buttonType";
		if($this->buttonFull) $classes[] = "uk-width-1-1";
		$class = implode(' ', $classes);
		$value1 = $this->wire()->sanitizer->entities($in->attr('value'));
		// $value2 = $in->entityEncode($in->value, Inputfield::textFormatBasic);
		$value2 = $in->html ? $in->html : $in->entityEncode($in->get('text|value'), Inputfield::textFormatBasic);
		$event->return = "<button type='submit' name='$in->name' value='$value1' class='$class'>$value2</button>";
	}

	/**
	 * Return Inputfields for configuration of framework
	 *
	 * @return InputfieldWrapper
	 *
	 */
	public function getConfigInputfields() {
		
		$modules = $this->wire()->modules;
		$config = $this->wire()->config;
		$forms = $this->wire('forms'); /** @var FormBuilder $forms*/
		$inputfields = parent::getConfigInputfields();
		$defaults = self::getConfigDefaults();
		$defaultLabel = $this->_('Default value:') . ' ';

		/** @var InputfieldURL $f */
		$f = $modules->get('InputfieldURL');
		$f->attr('name', 'ukURL');
		$f->label = $this->_('URL to Uikit framework');
		$f->description = $this->_('Specify a URL/path relative to root of ProcessWire installation.');
		$f->notes = sprintf(
			$this->_('Core Uikit location: %s'),
			'`/wire/modules/AdminTheme/AdminThemeUikit/uikit/dist/`'
		) . "\n";
		$f->attr('value', $this->ukURL);
		if($this->ukURL != $defaults['ukURL']) $f->notes .= $defaultLabel . "`$defaults[ukURL]`\n";
		$f->notes .= $this->_('Or specify location of your own copy.');
		$inputfields->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'css');
		$f->label = $this->_('Uikit CSS theme file');

		$_ukPath = $forms->frameworksPath() . 'uikit/css/';
		if(strpos($this->ukURL, '//') !== false) {
			// http URL, we can't identify CSS files there, so use our default 
			$ukPath = $_ukPath;
		} else {
			$ukPath = $config->paths->root . trim($this->ukURL, '/') . '/css/';
			if(!is_dir($ukPath)) {
				$f->error("Unable to locate path: $ukPath");
				$ukPath = $_ukPath;
			}
		}

		try {
			foreach(new \DirectoryIterator($ukPath) as $file) {
				if($file->isDir() || $file->isDot() || $file->getExtension() != 'css') continue;
				$f->addOption($file->getBasename());
			}
		} catch(\Exception $e) {
			$this->error($e->getMessage());
		}
		$f->attr('value', $this->css);
		$inputfields->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'horizontal');
		$f->label = $this->_('Form style');
		$f->addOption(0, $this->_('Stacked (default)'));
		$f->addOption(1, $this->_('Horizontal (2-column)'));
		$f->attr('value', $this->horizontal);
		$f->optionColumns = 1;
		$f->columnWidth = 50;
		$f->description = $this->_('Please note that individual field column widths (if used) are not applicable when using the *Horizontal* style.');
		$inputfields->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'inlineErrorBelow');
		$f->label = $this->_('Inline error placement');
		$f->addOption(0, $this->_('Above inputs'));
		$f->addOption(1, $this->_('Below inputs'));
		$f->attr('value', (int) $this->inlineErrorBelow);
		$f->optionColumns = 1;
		$f->columnWidth = 50;
		$inputfields->add($f);

		/** @var InputfieldInteger $f */
		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'horizHeaderWidth');
		$f->label = $this->_('Percent width for label columns (horizontal style only)');
		$f->description = $this->_('Specify a value between 5% and 90% percent to determine the width of the label column. The input column will have the remaining percent, i.e. if you specify 30% here, the label column will have 30% width and the input column will have 70% width.');
		$f->min = 5;
		$f->max = 90;
		$f->attr('value', $this->horizHeaderWidth);
		$f->showIf = 'frUikit3_horizontal=1';
		$inputfields->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'inputSize');
		$f->label = $this->_('Input size');
		$f->addOption('small', $this->_('Small'));
		$f->addOption('', $this->_x('Normal', 'sizeType'));
		$f->addOption('large', $this->_('Large'));
		$f->attr('value', $this->inputSize);
		$f->columnWidth = 34;
		$inputfields->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'buttonType');
		$f->label = $this->_('Submit button type');
		$f->addOption('default', $this->_('Default'));
		$f->addOption('primary', $this->_('Primary'));
		$f->addOption('secondary', $this->_('Secondary'));
		$f->addOption('danger', $this->_('Danger'));
		$f->attr('value', $this->buttonType ? $this->buttonType : 'primary');
		$f->columnWidth = 33;
		$inputfields->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'buttonSize');
		$f->label = $this->_('Submit button size');
		$f->addOption('small', $this->_('Small'));
		$f->addOption('', $this->_('Normal'));
		$f->addOption('large', $this->_('Large'));
		$f->attr('value', $this->buttonSize);
		$f->columnWidth = 33;
		$inputfields->add($f);

		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->attr('name', 'buttonFull');
		$f->label = $this->_('Full width button?');
		if($this->buttonFull) $f->attr('checked', 'checked');
		$f->columnWidth = 50;
		$inputfields->add($f);
	
		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->attr('name', 'boldLabels');
		$f->label = $this->_('Use bolder form labels?');
		$f->columnWidth = 50;
		if($this->boldLabels) $f->attr('checked', 'checked');
		$inputfields->add($f);

		return $inputfields;
	}

	/**
	 * Get configuration defaults
	 * 
	 * @return array
	 * 
	 */
	public function getConfigDefaults() {
		$config = $this->wire()->config;
		$ukURL = str_replace($config->urls->root, '/', $config->urls('FormBuilder') . 'frameworks/uikit3/');
		return array_merge(parent::getConfigDefaults(), array(
			'ukURL' => $ukURL,
			'horizontal' => 0,
			'horizHeaderWidth' => 30,
			'inlineErrorBelow' => 0,
			'css' => 'uikit.min.css',
			'inputSize' => '',
			'buttonType' => '',
			'buttonSize' => '',
			'buttonFull' => 0,
			'boldLabels' => 0, 
		));
	}

	/**
	 * Get the framework URL
	 *
	 * @return string
	 *
	 */
	public function getFrameworkURL() {
		return $this->ukURL;
	}

	/**
	 * Get the framework version
	 *
	 * @return string
	 *
	 */
	static public function getFrameworkVersion() {
		return '3.6.22';
	}

}
