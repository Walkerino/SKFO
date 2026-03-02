function ComboConfig() {
	
	let $newFieldTemplate = jQuery('#_i0_fieldset');
	
	function setupSortable() {
		let $fields = jQuery('#ComboConfigFields').children('div').children('.Inputfields');
		$fields.sortable({
			axis: 'y',
			items: '.ComboConfigField',
			handle: '> .InputfieldHeader',
			stop: function(ui, event) {
				let order = '';
				jQuery('.ComboConfigField').each(function() {
					let n = jQuery(this).attr('data-combo-n');
					if(n !== '0') order += (',' + n);
				});
				jQuery('#ComboConfigOrder').val(order.substring(1));
			}
		});
	}
	
	function setupField($item) {
		let $header = $item.children('.InputfieldHeader');
		let $columnWidthInput = $item.find('.columnWidthInput');
		let $columnWidthHeader = $columnWidthInput.closest('.Inputfield').children('.InputfieldHeader');
		let $width = $('<span />')
			.addClass('ComboConfigFieldWidth')
			.addClass('ui-priority-secondary')
			.css({ float: 'right', marginRight: '20px' })
			.text($item.attr('data-combo-w') + '%');
		let $detachIcon = $('<i/>').addClass('ComboDetachIcon fa fa-exchange fa-rotate-90')
			.addClass('ui-priority-secondary')
			.css({ float: 'right', marginRight: '20px' });
		if($columnWidthHeader.length) {
			let html = $columnWidthHeader.html();
			$columnWidthHeader.html(html.replace(/\(\d+%\)/, ''));
		}
		setupDelete($item);
		$header.append($width).append($detachIcon);
		$columnWidthInput.on('change', function() {
			let val = jQuery(this).val();
			$item.attr('data-combo-w', parseInt(val));
			$width.text(val);
		}); 
		$width.on('click', function() {
			Inputfields.find($columnWidthInput);
			return false;
		}); 
		$item.find('.ComboDetachInput').on('change', function() {
			if(jQuery(this).val().length) {
				$item.addClass('ComboStateDetached');
			} else {
				$item.removeClass('ComboStateDetached'); 
			}
		}).closest('.Inputfield').find('.fa-exchange').addClass('fa-rotate-90');
	}

	function setupNewField($item) {
		let $nameField = null;
		$item.addClass('ComboConfigFieldNew');
		jQuery('input.ComboFieldLabel', $item).on('focus', function() {
			$nameField = jQuery(this).closest('.Inputfield').next('.Inputfield').find('input');
			if($nameField.val().length > 0) $nameField = null;
		}).on('blur', function() {
			$nameField = null;
		}).on('keyup change', function(e) {
			if($nameField === null) return;
			let value = jQuery(this).val();
			value = value.replace(/[^a-zA-Z0-9_]/g, '_');
			if(value.indexOf('_') > -1) value = value.replace(/__+/, '_');
			$nameField.val(value.toLowerCase());
		});
		Inputfields.init($item);
		let $langTabs = $item.find('.langTabs');
		if($langTabs.length) { // && $langTabs.find('langTabsInit').length < 1) {
			$langTabs.removeClass('langTabsInit');
			$langTabs.closest('.Inputfield').find('.langTabsToggle').remove();
			setupLanguageTabs($item);
		}
	}
	
	function addField() {
		// first check if there is already one they can use
		let $option = jQuery('select.ComboFieldType:not([name="i0_type"]) option:selected[value=""]');
		if($option.length) {
			Inputfields.find($option.closest('.Inputfield'));
			return;
		}
		
		// add a new field
		let html = $newFieldTemplate[0].outerHTML; // new item template
		let $qty = jQuery('#ComboConfigQty');
		let n = parseInt($qty.val()) + 1;
		html = html.replace(/i0_/g, 'i' + n + '_');
		let $li = jQuery(html);
		$li.prop('data-combo-n', n);
		$li.removeProp('hidden').removeAttr('hidden').hide();
		$qty.val(n);
		jQuery('.ComboConfigField').last().after($li);
		setupNewField($li);
		setupField($li);
		$li.slideDown();
	}

	let deleteClickEvent = function() {
		let $item = jQuery(this).closest('.ComboConfigField');
		let $type = $item.find('.ComboFieldType');
		if($type.val() === 'DELETE') {
			$item.removeClass('ComboConfigFieldDeletePending');
			$type.val($type.attr('data-prev-type'));
		} else {
			$item.addClass('ComboConfigFieldDeletePending');
			$type.attr('data-prev-type', $type.val());
			$type.val('DELETE');
			Inputfields.close($item);
		}
		return false;
	};
	
	function setupDelete($item) {
		let $header = $item.children('.InputfieldHeader');
		let $icon = $("<i class='ComboConfigFieldDeleteAction fa fa-trash-o'></i>").css({
			float: 'right',
			position: 'relative',
			top: '5px',
			right: '5px'
		});
		$header.append($icon);
		$icon.on('click', deleteClickEvent); 
		let $type = $item.find('.ComboFieldType');
		$type.on('change', function() {
			if(jQuery(this).val() === 'DELETE') {
				$item.addClass('ComboConfigFieldDeletePending');
				Inputfields.close($item);
			} else {
				$item.removeClass('ComboConfigFieldDeletePending');
			}
		}); 
	}

	jQuery('#ComboConfigAdd').on('click', function() { 
		addField(); 
		return false; 
	}); 
	setupSortable();
	
	jQuery('.ComboConfigField').each(function() {
		if(jQuery(this).attr('id') === $newFieldTemplate.attr('id')) return;
		setupField(jQuery(this));
	}); 
}

jQuery(document).ready(function() {
	ComboConfig();
}); 