/**
 * ProcessWire Repeater Matrix Inputfield Javascript
 *
 * Copyright 2023 by Ryan Cramer 
 *
 */

function InputfieldRepeaterMatrixEvents() {

	/**
	 * True when a custom add link is clicked and in progress
	 * 
	 * @type {boolean}
	 * 
	 */
	var customAdding = false;

	/**
	 * Current spinner
	 * 
	 */
	var $currentSpinner = null;

	/**
	 * Get options for vex dialog for when an addTypeCustom add/insert link is clicked (only)
	 * 
	 * @param $custom
	 * @param options
	 * @returns {*}
	 * 
	 */
	function getAddTypeCustomDialogOptions($custom, options) {
		
		var label = $custom.attr('data-label');
		var maxWidth = $custom.closest('.InputfieldContent').width();
		
		options.input = $custom[0].outerHTML;
		options.buttons = [ vex.dialog.buttons.NO ];
		options.afterOpen = function() {
			var $vexContent = $(this.rootEl).find('.vex-content');
			$vexContent.css('width', '90%');
			$custom = $vexContent.find('.InputfieldRepeaterMatrixAddCustom');
			$custom.removeClass('InputfieldRepeaterMatrixHidden');
			var width = $custom.outerWidth() + 40;
			if(width > maxWidth) width = maxWidth;
			if(width < 450 && maxWidth > 0) width = maxWidth;
			$vexContent.css('width', width + 'px');
			// UIkit.tooltip($vexContent.find('InputfieldRepeaterMatrixAddImage'));
			// if(typeof UIkit === "undefined") ProcessWireAdmin.setupTooltips();
		};
		
		if(typeof label !== "undefined" && label.length) {
			options.message = label;
		}
		
		if(typeof options.callback === "undefined") {
			options.callback = function(/*data*/) {
				if(!customAdding) stopSpinner(); // add cancelled
			}
		}
		
		return options; 
	}

	/**
	 * Start given spinner and remember it for stopSpinner() call
	 * 
	 * @param $spinner
	 * 
	 */
	function startSpinner($spinner) {
		$spinner.removeClass($spinner.attr('data-off')).addClass($spinner.attr('data-on'));
		$currentSpinner = $spinner;
	}

	/**
	 * Stop current running spinner (or one given)
	 * 
	 * @param $spinner
	 * 
	 */
	function stopSpinner($spinner) {
		if(typeof $spinner === "undefined") $spinner = $currentSpinner;
		if(!$currentSpinner) return;
		$spinner.removeClass($spinner.attr('data-on')).addClass($spinner.attr('data-off'));
		$currentSpinner = null;
	}
	
	/**
	 * Event called when a new matrix item is added
	 * 
	 * @param e
	 * @param $addItem
	 * 
	 */
	var eventRepeaterAdd = function(e, $addItem) {
		var $addLink = $(this);
		var $label = $addItem.children('label');
		var text = $label.text();
		var html = $label.html();
		var newText = $addLink.attr('data-label');
		var addType = $addLink.attr('data-type');

		html = html.replace(text, newText);
		$label.html(html);

		var $input = $addLink.closest('.InputfieldRepeaterMatrixAddItem').find('.InputfieldRepeaterMatrixAddItemsQty');
		var name = $input.attr('data-name') + '_type[]';
		var $input = $("<input />").attr('type', 'hidden').attr('name', name).val(addType);
		$addItem.append($input);
		customAdding = false;
		stopSpinner();
	};

	/**
	 * Event called when a custom add link is clicked
	 * 
	 * @returns {boolean}
	 * 
	 */
	var eventAddTypeCustomClick = function(/*e*/) {
		var $a = $(this);
		var href = $a.attr('href');
		if(href.indexOf('#') === 0 && href !== '#') {
			var $addLink = $(href);
			if($addLink.length) {
				customAdding = true;
				// click the corresponding add link so its events fire
				$addLink.trigger('click');
				// if add link within dialog clicked then close the dialog
				if($a.closest('.vex-dialog-input').length) vex.closeTop();
				return false;
			}
		}
	};

	/**
	 * The "Add New" label/link at bottom is clicked when using custom add links
	 * 
	 */
	var eventCustomAddLinkClick = function(/*e*/) {
		var $a = $(this);
		var $custom = $a.siblings('.InputfieldRepeaterMatrixAddCustom');
		var options = getAddTypeCustomDialogOptions($custom, {});
		var $spinner = $a.find('.InputfieldRepeaterSpinner');
		startSpinner($spinner); 
		vex.dialog.open(options);
		return false;
	}; 

	/**
	 * Event called when a selection is made from the “add item” <select>
	 *
	 * @returns {boolean}
	 *
	 */
	var eventAddTypeSelectInput = function(/*e*/) {
		var id = $(this).val();
		if(id.length) $('#' + id).trigger('click');
		$(this).val('');
		return false;
	};
	
	/**
	 * Event called when a selection is made from the “insert item” <select>
	 *
	 * @returns {boolean}
	 *
	 */
	var eventInsertTypeSelectInput = function(/*e*/) {
		var $this = $(this);
		if($this.val() && $this.val().length) {
			var id = $(this).val();
			var $link = $('#' + id); 
			if($link.length) {
				$this.closest('.vex-dialog-form').find('.vex-dialog-button-primary').trigger('click');
				$link.trigger('click');
				vex.closeTop();
			}
		}
		return true;
	};

	/**
	 * Called when the "repeaterinsert" event is fired
	 * 
	 * @param e
	 * @param $placeholder
	 * @param $item
	 * @param insertBefore
	 * 
	 */
	var eventRepeaterInsert = function(e, $placeholder, $item, insertBefore) {
		
		var $inputfield = $item.closest('.InputfieldRepeaterMatrix'); 
		var $addLinksParent = $inputfield.children('.InputfieldContent').children('.InputfieldRepeaterMatrixAddItem');
		var $custom = $addLinksParent.siblings('.InputfieldRepeaterMatrixAddCustom');
		var $select = $addLinksParent.find('.InputfieldRepeaterMatrixAddSelect');
		
		if(insertBefore) {} // ignore
		
		var options = {
			// buttons: [
			//	 $.extend({}, vex.dialog.buttons.YES, { text: 'Continue' }),
			//	 $.extend({}, vex.dialog.buttons.NO, { text: 'Cancel' })
			// ],
			message: '', // message displayed at top
			input: '' // HTML content that is to be displayed
		};

		if($custom.length) {
			// custom add links in use 
			options.callback = function(/*data*/) {
				if(customAdding) return; // insert proceeding
				$placeholder.remove(); // insert cancelled
				stopSpinner();
			};
			options = getAddTypeCustomDialogOptions($custom, options);
			
		} else if($select.length) {
			// select box in use
			options.message = $select.attr('data-label');
			$select = $select.clone();
			$select.removeClass('InputfieldRepeaterMatrixAddSelect')
				.addClass('InputfieldRepeaterMatrixInsertSelect')
				.attr('name', 'insert_matrix_type_select');
			options.input = $select[0].outerHTML;
			options.callback = function(data) {
				var value = data ? data['insert_matrix_type_select'] : null; // selected value
				if(value) return; // insert proceeding
				$placeholder.remove(); // insert cancelled
			}; 
			
		} else {
			return;
		}
	
		// open the add-type selection dialog
		vex.dialog.open(options);
	};
	
	/**
	 * Init
	 * 
	 */
	function init() {
		$(document)
			.on('repeateradd', '.InputfieldRepeaterMatrix .InputfieldRepeaterMatrixAddLink', eventRepeaterAdd)
			.on('repeaterinsert', '.InputfieldRepeaterItem', eventRepeaterInsert)
			.on('input', 'select.InputfieldRepeaterMatrixAddSelect', eventAddTypeSelectInput)
			.on('input', 'select.InputfieldRepeaterMatrixInsertSelect', eventInsertTypeSelectInput)
			.on('click', '.InputfieldRepeaterMatrixAddCustom a', eventAddTypeCustomClick)
			.on('click', '.InputfieldRepeaterMatrixCustomAddLink', eventCustomAddLinkClick);
	}
	
	init();
}

var InputfieldRepeaterMatrixTools = {
	
	getItemDepth: function($item) {
		return parseInt($item.attr('data-depth'));
	},
	
	itemAllowsParent: function($item, $parent) {
		if($item && $parent) {} // ignore
		return true;
	},
	
	sortableItemAllowed: function($item) {
		var $lastParent = null;
		var $parent = null;
		var depth = this.getItemDepth($item);
		var $this = this;

		$item.parent().children().each(function() {
			if($parent) return;
			var $li = $(this);
			if($this.getItemDepth($li) === (depth-1)) {
				$lastParent = $li;
			} else if($item.attr('data-page') === $li.attr('data-page')) {
				$parent = $lastParent;
			}
		});
		
		return $parent ? this.itemAllowsParent($item, $parent) : true;
	}
	
};

$(document).ready(function() {
	InputfieldRepeaterMatrixEvents();
}); 