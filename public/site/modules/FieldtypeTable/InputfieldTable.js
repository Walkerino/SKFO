/**
 * ProcessWire Table Inputfield
 *
 * Field that lets you define a database table of custom inputs. 
 *
 * Part of the ProFields package
 * Please do not distribute. 
 * 
 * Copyright 2024 by Ryan Cramer
 *
 * https://processwire.com/store/pro-fields/
 * 
 *
 */

function InputfieldTable() {

	/**
	 * Debug mode
	 * 
	 * @type {boolean}
	 * 
	 */
	var debug = false; 

	/**
	 * Timer used by setTimeout during search/filters
	 * 
	 */
	var searchTimer = null;
	
	/**
	 * Was last click event a shift-click event?
	 * 
	 * @type {boolean}
	 * 
	 */
	var lastClickHadShift = false;
	
	/**
	 * Log message to console
	 * 
	 * @param x
	 * 
	 */
	function consoleLog(x) {
		if(debug) console.log(x);
	}

	/**
	 * Given an Inputfield, return the table.InputfieldTable
	 *
	 * @param $inputfield
	 *
	 */
	function getTable($inputfield) {
		if(!$inputfield.hasClass('Inputfield') || !$inputfield.hasClass('InputfieldTable')) {
			$inputfield = getInputfield($inputfield);
		}
		return $inputfield.find('table.InputfieldTable:not(.InputfieldTableSearch)');
	}

	/**
	 * Get all table.InputfieldTable elements in the document
	 *
	 * @returns {*|jQuery|HTMLElement}
	 *
	 */
	function getAllTables() {
		return $("table.InputfieldTable:not(.InputfieldTableSearch)");
	}

	/**
	 * Given a $table (or any element within an Inputfield) return the corresponding .Inputfield
	 *
	 * @param $table
	 *
	 */
	function getInputfield($table) {
		if($table.is('.Inputfield.InputfieldTable')) return $table;
		return $table.closest('.Inputfield.InputfieldTable');
	}

	/**
	 * Given an Inputfield (or any item within) return the field name
	 * 
	 * @param $inputfield
	 * @param bool getRootField Get root field name if in a repeater?
	 * @returns string
	 * 
	 */
	function getFieldName($inputfield, getRootField) {
		if(!$inputfield.hasClass('Inputfield')) $inputfield = $inputfield.closest('.Inputfield.InputfieldTable'); 
		var fieldName = $inputfield.attr('id').replace('wrap_Inputfield_', '');
		if(typeof getRootField !== 'undefined' && getRootField && fieldName.indexOf('_repeater') > 0) {
			fieldName = fieldName.split('_repeater')[0];
		}
		return fieldName;
	}
	
	/**
	 * Get field-specific settings
	 *
	 * @param $inputfield
	 * @returns {boolean|*}
	 *
	 */
	function getFieldSettings($inputfield) {
		
		if(!$inputfield.hasClass('Inputfield')) $inputfield = getInputfield($inputfield);
		
		var fieldName = getFieldName($inputfield);
		var settings = ProcessWire.config['InputfieldTable_' + fieldName];
		
		if(typeof settings === 'undefined') {
			fieldName = getFieldName($inputfield, true); // get root fieldname
			settings = ProcessWire.config['InputfieldTable_' + fieldName];
		}
		
		if(typeof settings === 'undefined') return false;
		
		return settings;
	}
	
	/**
	 * Get all visible rows for the given table
	 *
	 * @param $table May be table, or any item within the Inputfield
	 *
	 */
	function getRows($table) {
		if(!$table.is('table') || $table.hasClass('InputfieldTableSearch')) {
			$table = getTable($table);
		}
		return $table.children('tbody').children('tr:not(.InputfieldTableRowTemplate)');
	}

	/**
	 * Make the given table drag/drop sortable
	 * 
	 * @param $table
	 * 
	 */
	function initSortable($table) {
		
		if(!$table.is("tbody")) $table = $table.children("tbody");
		
		var $nextSelectedRows = null;
		var $prevSelectedRows = null;
		var $selectedRow = null; // selected row that initiated sort
		
		function startSelectedRow($row) {
			$selectedRow = $row;
			$nextSelectedRows = $row.nextUntil(':not(.InputfieldTableRowSelected)', ':not(.ui-sortable-placeholder)');
			$prevSelectedRows = $row.prevUntil(':not(.InputfieldTableRowSelected)', ':not(.ui-sortable-placeholder)');
			$nextSelectedRows.hide();
			$prevSelectedRows.hide();
		}
		
		function finishSelectedRow() {
			var $lastRow = $selectedRow;
			$nextSelectedRows.each(function() {
				var $thisRow = $(this);
				$lastRow.after($thisRow);
				$lastRow = $thisRow;
			});
			$lastRow = $selectedRow;
			$prevSelectedRows.each(function() {
				var $thisRow = $(this);
				$lastRow.before($thisRow);
				$lastRow = $thisRow;
			});
			$nextSelectedRows.show();
			$prevSelectedRows.show();
			$nextSelectedRows = null;
			$prevSelectedRows = null;
			$selectedRow = null;
		}
		
		$table.sortable({
			axis: 'y',
			handle: '.InputfieldTableRowSortHandle',
			start: function(event, ui) {
				$table.addClass('sorting');
				if(ui.item.hasClass('InputfieldTableRowSelected')) startSelectedRow(ui.item);
			}, 
			stop: function(event, ui) {
				if($selectedRow) finishSelectedRow();
				updateRowSorts($table);
				var $inputfield = getInputfield($table);
				$inputfield.addClass('InputfieldStateChanged');
				$table.removeClass('sorting');
			},
			update: function(e, ui) {
				var $inputfield = getInputfield($table);
				$inputfield.trigger('sorted', [ ui.item ]); // sorted event recognized by PW 3.0.189+
			}
		});
		
		// if there is only a template row, then don't show the table at all (to hide the header)
		var numRows = $table.find('tr').length;
		if(numRows == 1) $table.parent('table').hide();
	}
	
	/**
	 * Update the sort value for each row
	 *
	 * @param $table
	 *
	 */
	function updateRowSorts($table) {
		var n = 0;
		var changed = false;
		var $tbody = $table.is('tbody') ? $table : $table.children('tbody');
		$tbody.children('tr').each(function() { 
			var $row = $(this);
			if($row.hasClass('InputfieldTableRowTemplate')) return;
			var $input = $row.find('.InputfieldTableRowSort');
			if(parseInt($input.val()) !== n) {
				$input.val(n);
				changed = true;
			}
			n++;
		});
		if(changed) {
			$table.closest('.Inputfield').trigger('changed').addClass('InputfieldStateChanged');
		}
		updateRowNumbers($table);
	}
	
	/**
	 * Update the number assigned to each row (for multi-table, non-paginated layouts only)
	 * 
	 * @param $table
	 * 
	 */
	function updateRowNumbers($table) {
		var n = 0;
		$table.find("td > .rowNum").each(function() {
			if(!$(this).is(":visible")) return;
			$(this).text(++n);
		});
	}

	/**
	 * Update attribute for given item to reflect the given rowNum
	 * 
	 * @param $item
	 * @param attrName
	 * @param rowNum
	 * @param oldRowNum Old row number if known or omit if new row
	 * 
	 */
	function updateAttribute($item, attrName, rowNum, oldRowNum) {
		var attrVal = $item.attr(attrName);
		if(typeof attrVal == "undefined" || attrVal.length == 0) return;
		if(typeof oldRowNum === 'undefined') {
			attrVal = attrVal.replace(/_0_/, '_' + rowNum + '_');
		} else {
			attrVal = attrVal.replace('_' + oldRowNum + '_', '_' + rowNum + '_');
		}
		$item.attr(attrName, attrVal);
	}

	/**
	 * Update all attributes for row to rename it 
	 * 
	 * @param $row
	 * @param newRowNum
	 * @param oldRowNum
	 * 
	 */
	function renameRowAttributes($row, newRowNum, oldRowNum) {
		if(typeof oldRowNum === 'undefined') oldRowNum = getRowNum($row);
		$row.find('*').each(function() {
			var $item = $(this);
			if($item.is(':input')) {
				updateAttribute($item, 'name', newRowNum, oldRowNum);
				if($item.hasClass('InputfieldTableRowSort')) $item.val(newRowNum);

			} else if($item.is('a')) {
				// update language tabs links
				var href = $item.attr('href');
				if(href.indexOf('#') === 0) {
					updateAttribute($item, 'href', newRowNum, oldRowNum);
				}
			} else if($item.is('label') && typeof $item.attr('for') !== 'undefined') {
				updateAttribute($item, 'for', newRowNum, oldRowNum);
			}
			updateAttribute($item, 'id', newRowNum, oldRowNum);
		});
		// specific to InputfieldPageAutocomplete
		$row.find('.InputfieldPageAutocomplete, .InputfieldPageAutocompleteTemplate').each(function() {
			var $input = $(this).find('input[type=text]');
			// updates attributes unique to autocomplete fields
			updateAttribute($input, 'data-parent-input', newRowNum, oldRowNum);
			var $ol = $(this).find('ol');
			if($ol.length) {
				updateAttribute($ol, 'data-id', newRowNum, oldRowNum);
				updateAttribute($ol, 'data-name', newRowNum, oldRowNum);
			}
		});
	}

	/**
	 * Get row number/sort vlaue
	 * 
	 * @param $row
	 * @returns {*}
	 * 
	 */
	function getRowNum($row) {
		var name = $row.find('.InputfieldTableRowSort').attr('name');
		var num = name.match(/_([0-9]+)__/)[1];
		return parseInt(num);
	}
	
	function getRowSort($row) {
		return parseInt($row.find('.InputfieldTableRowSort').val());
	}
	
	function setRowSort($row, sort) {
		$row.find('.InputfieldTableRowSort').val(sort);
	}
	
	function getRowID($row) {
		return parseInt($row.find('.InputfieldTableRowID').val());
	}

	/**
	 * Init PageAutocomplete for given item
	 * 
	 * @param $item
	 * @param cnt
	 * 
	function initAutocomplete($item, cnt) {
		$item.removeClass('InputfieldPageAutocompleteTemplate').addClass('InputfieldPageAutocomplete');
		var $input = $item.find("input[type=text]");
		// updates attributes unique to autocomplete fields
		updateAttribute($input, 'data-parent-input', cnt);
		var $ol = $item.find("ol");
		if($ol.length) {
			updateAttribute($ol, 'data-id', cnt);
			updateAttribute($ol, 'data-name', cnt);
		}
		$item.trigger('reloaded', ['InputfieldTable']);
	}
	 */
	
	function highlightRow($row, ms) {
		if(typeof ms === 'undefined') ms = 500;
		$row.addClass('ui-state-highlight');
		setTimeout(function() {
			$row.removeClass('ui-state-highlight');
		}, ms);
	}

	/**
	 * Initialize a table row
	 * 
	 * This initializes any asmSelects, language tabs, etc. 
	 * 
	 * @param $row
	 * 
	 */
	function initRow($row) {

		// asmSelect
		$row.find("select[multiple].InputfieldTable_asmSelect, select[multiple].InputfieldTable_pageAsmSelect").each(function() {
			//if($(this).parents('.asmContainer').length > 0) return;
			if($(this).parent().hasClass('asmContainer')) return;
			if(typeof config.InputfieldTable_asmSelect !== 'undefined') {
				$(this).asmSelect(config.InputfieldTable_asmSelect);
			} else {
				$(this).asmSelect(config.InputfieldAsmSelect); 
			}
		});

		// language tabs
		var $langTabs = $row.find(".InputfieldTable_langTabs");
		if($langTabs.length) {
			var options = {};
			if(typeof config.LanguageTabs != "undefined") options.active = config.LanguageTabs.activeTab;
			$langTabs.each(function() {
				// note we populate a fake ui-tabs class in InputfieldTable.module in order to make
				// PW's LanguageTabs avoid attempting to initialize it, since we initialize our own
				if($(this).hasClass('ui-tabs')) $(this).removeClass('ui-tabs').tabs(options);
				$(this).closest('td').addClass('InputfieldTable_hasLangTabs');
			});
		}

		// image file uploads and lightbox links
		var $imageLinks = $row.find('.InputfieldTableImage');
		if($imageLinks.length) {
			var useMagnific = typeof jQuery.magnificPopup !== 'undefined';
			$imageLinks.each(function() {
				var $a = $(this);
				var $img = $a.children('img').first();
				if(useMagnific && $a.is('a')) $a.magnificPopup({
					type: 'image',
					closeOnContentClick: true,
					closeBtnInside: true
				});
			});
		}
		
		$row.addClass('InputfieldTableRow');
	}
	
	/**
	 * Called on row about to be added
	 *
	 * @param $row
	 * @param n Sort value for new row (number of rows in table for newly added rows)
	 * 
	 */
	function addRowInit($row, n) {
		$row.find('input.hasDatepicker').removeClass('hasDatepicker').removeAttr('id').datepicker('destroy');
		$row.removeClass('InputfieldTableRowTemplate');
		renameRowAttributes($row, n);
	}

	/**
	 * Called on row after it has been added
	 *
	 * @param $row
	 * @param n Sort value for new row (number of rows in table for newly added rows)
	 *
	 */
	function addRowFinish($row, $table, n) {
		
		initRow($row);
		updateRowNumbers($table);

		// specific to InputfieldPageAutocomplete
		$row.find(".InputfieldPageAutocompleteTemplate").each(function() {
			$(this).removeClass('InputfieldPageAutocompleteTemplate').addClass('InputfieldPageAutocomplete');
			/*
			var $input = $(this).find("input[type=text]");
			// updates attributes unique to autocomplete fields
			updateAttribute($input, 'data-parent-input', n);
			var $ol = $(this).find("ol");
			if($ol.length) {
				updateAttribute($ol, 'data-id', n);
				updateAttribute($ol, 'data-name', n);
			}
			*/
			$(this).trigger('reloaded', ['InputfieldTable']);
		});
		
		$row.find('.InputfieldTextTags').each(function() {
			var html = $(this).html();
			html = html.replace('_InputfieldTextTags', 'InputfieldTextTags');
			$(this).html(html);
			$(this).trigger('reloaded', ['InputfieldTable']); 
		}); 

		// make the 'copy' action disabled for new rows with no ID
		var rowID = parseInt($row.find('.InputfieldTableRowID').val()); 
		if(rowID === 0) {
			var $select = $row.find('.InputfieldTableActionRowSelect');
			if($select.length) $select.find('option[value=copy]').prop('disabled', true);
		}

		highlightRow($row);
		$row.trigger('TableAddRow', [ $row ]); // requested by Robin S.
	}

	/**
	 * Handler for the "Add Row" click event
	 * 
	 * @returns {boolean}
	 * 
	 */
	function addRowClick() {
		addNewRow(getTable($(this)));
		return false;
	}
	
	/**
	 * Add new row to table and return it
	 * 
	 * @param $table
	 * @param $beforeRow Optionally insert before this row
	 * @param $afterRow Optional insert after this row
	 * 
	 */
	function addNewRow($table, $beforeRow, $afterRow) {
		if(!$table.is('table.InputfieldTable')) $table = getTable($table);
		var $tbody = $table.children('tbody');
		var numRows = $tbody.children('tr').length;
		var $rowTemplate = $tbody.children('.InputfieldTableRowTemplate');
		var $row = $rowTemplate.clone(true);
		
		addRowInit($row, numRows);
		
		if(typeof $beforeRow !== 'undefined' && $beforeRow) {
			$beforeRow.before($row);
		} else if(typeof $afterRow !== 'undefined' && $afterRow) {
			$afterRow.after($row);
		} else {
			$tbody.append($row);
		}
		$table.show();
		$row.addClass('InputfieldTableRowNew');
		$row.css('opacity', 0); 
		$row.fadeTo(250, 1.0); 
		
		addRowFinish($row, $table, numRows);
		
		return $row;
	}
	
	/**
	 * Paste given rows into given table
	 * 
	 * @param $table
	 * @param $rows
	 * @param $beforeRow Optionally paste before this row
	 * @param $afterRow Optional paste after this row
	 * 
	 */
	function pasteRows($table, $rows, $beforeRow, $afterRow) {
		var $tbody = $table.children('tbody');
		var $inputfield = getInputfield($table);
		var numRows = $tbody.children('tr').length;
		var before = $beforeRow ? true : false;
		var $initRow = before ? $beforeRow : $afterRow;
		
		$rows.find('.InputfieldTableRowID').val('0');
		$rows.find('.InputfieldTableRowSort').val('0');
		$rows.addClass('InputfieldTableRowPasted');
		
		$rows.each(function() {
			var $row = $(this);
			$row.hide();
			addRowInit($row, numRows);
			if(typeof $afterRow !== 'undefined' && $afterRow) {
				$afterRow.after($row);
				$afterRow = $row;
			} else if(typeof $beforeRow !== 'undefined' && $beforeRow) {
				$beforeRow.before($row);
				$afterRow = $row;
			} else {
				$tbody.append($row);
			}
			$table.show();
			$row.addClass('InputfieldTableRowNew');
			$row.show();
			addRowFinish($row, $table, numRows);
			numRows++;
		});
		
		updateRowSorts($table);
		$inputfield.trigger('pasted', [ $rows, $initRow, before ]);
	}

	/**
	 * Event handler for delete row click
	 * 
	 */
	function deleteRowClick() {
		
		var $row = $(this).closest('tr');
		var $input = $(this).siblings('.InputfieldTableRowDelete');
		
		if($row.hasClass('InputfieldTableRowNew') && !$row.hasClass('InputfieldTableRowChanged')) {
			// delete of newly added row, we can just remove it
			$row.remove();
			
		} else if($row.hasClass('InputfieldTableRowDeleted')) {
			// undelete
			$row.removeClass('InputfieldTableRowDeleted');
			$row.css('opacity', 1.0);
			$input.val(0);
			$row.closest('.Inputfield.InputfieldTable').find('.InputfieldTableDeleteAll').hide()
				.find('input').val('').removeAttr('checked');

		} else {
			// delete
			$row.addClass('InputfieldTableRowDeleted');
			$row.css('opacity', 0.3);
			$input.val(1);
		}
	}
	
	/**
	 * Event handler for delete row double-click
	 * 
	 */
	function deleteRowDblClick() {
		
		var $inputfield = getInputfield($(this));
		var settings = getFieldSettings($inputfield);
	
		if(!settings || !settings.useBigDelete) return false;	
		
		var message = ProcessWire.config.InputfieldTable.applyToAll;
		
		ProcessWire.confirm(message, function() {
			var $items = $inputfield.find('.InputfieldTableRowDeleteLink:visible');
			$items.trigger('click');
			setTimeout(function() {
				var numMarked = $inputfield.find('.InputfieldTableRowDeleted').length;
				var $deleteAll = $inputfield.find('.InputfieldTableDeleteAll');
				consoleLog($inputfield);
				var numPopulated = 0;
				$inputfield.find('.InputfieldTableSearch').find(':input').each(function() {
					if($(this).val().length > 0) numPopulated++;
				});
				if(numMarked == $items.length && numPopulated == 0) { 
					$deleteAll.show().find('input').val('DELETE-ALL').removeAttr('checked');
				} else {
					$deleteAll.hide().find('input').val('').removeAttr('checked');
				}
			}, 250);
		});
	}
	
	/**
	 * Event handler for clone row click
	 * 
	 * Thanks to Robin S for coming up with this. 
	 *
	 */
	function cloneRowClick($row, above) {
	
		if(typeof $row === 'undefined') $row = $(this).closest('tr.InputfieldTableRow');
		if(typeof above === 'undefined') above = false;
		
		var $inputfield = getInputfield($row);
		
		$inputfield.find('.InputfieldTableAddRow').trigger('click');
		
		var $newRow = $inputfield.find('.InputfieldTable > tbody > tr').last();
		var $rowCols = $row.find('td');
		var $newRowCols = $newRow.find('td');
		var baseFieldName = getFieldName($inputfield) + '_';
		
		$rowCols.each(function(colIndex) {
			$(this).find(':input[name]:not(.InputfieldTableRowID)').each(function(inputIndex) {
				var $input = $(this);
				if(!$input.attr('name').startsWith(baseFieldName)) return;
				var $match = $newRowCols.eq(colIndex).find(':input[name]:not(.InputfieldTableRowID)').eq(inputIndex);
				if(!$match.length) return;
				
				if($input.is('select')) {
					$input.children(':selected').each(function() {
						$match.children('[value="' + $(this).val() + '"]').prop('selected', true).end().trigger('change');
					});
					
				} else if($input.attr('type') === 'radio' && $input.closest('.InputfieldToggle').length) {
					$match.prop('checked', $input.is(':checked')); // no change event intentional
					
				} else if($input.is(':checked')) {
					$match.prop('checked', true).trigger('change');
					
				} else {
					$match.val($input.val()).trigger('change');
				}
				
				if($input.hasClass('InputfieldPageAutocompleteData')) {
					var $ol = $match.parent().siblings('ol');
					if($ol.length) {
						$ol.html($input.parent().siblings('ol').html());
					} else {
						$match.siblings('.ui-autocomplete-input').val($input.siblings('.ui-autocomplete-input').val());
					}
				}
			});
		});

		if(above) {
			$newRow.insertBefore($row);
		} else {
			$newRow.insertAfter($row);
		}
		$newRow.addClass('InputfieldTableRowClone');
		
		var n = 0;
		
		$inputfield.find('.InputfieldTable > tbody > tr:not(.InputfieldTableRowTemplate)').each(function() {
			var $tr = $(this);
			$tr.find('.InputfieldTableRowSort').val(n);
			$tr.find('.rowNum').text(n + 1);
			n++;
		});
		
		highlightRow($newRow);
	}

	/**
	 * Change event handler for :input's
	 * 
	 * @param e
	 *
	function inputChange(e) {
		$(this).closest('tr').addClass('InputfieldTableRowChanged');
	}
	 */

	/**
	 * Event handler for clicking a pagination link
	 * 
	 * @returns {boolean}
	 * 
	 */
	function pagerNavClick() {

		var $a = $(this);
		var $inputfield = getInputfield($a); // $a.closest('.Inputfield');
		var href;

		if($a.hasClass('InputfieldTableHeaderSortable')) {
			href = $a.attr('data-href');
		} else {
			href = $a.attr('href');
		}

		reloadContent($inputfield, href);

		return false;
	}
	
	/**
	 * Reload the .InputfieldTablePagerNav content from ajax request
	 * 
	 * @param $inputfield
	 * @param href
	 * 
	 */
	function reloadContent($inputfield, href) {
		
		if($inputfield.hasClass('InputfieldStateChanged')) {
			var message = ProcessWire.config.InputfieldTable.areYouSure;
			if(!confirm(message)) {
				return false;
			}
			$inputfield.removeClass('InputfieldStateChanged');
		}

		var $spinner = $("<i style='margin-left:0.5em;' class='fa fa-fw fa-spin fa-spinner ui-priority-secondary'></i>");
		var $pager = $inputfield.find('.MarkupPagerNav');
		var $content = $inputfield.find('.InputfieldTablePagerNavContent');

		if($pager.length && false) {
			$pager.append($spinner);
		} else {
			$inputfield.find('.InputfieldTablePagerNote').prepend($spinner);
		}
		
		consoleLog('ajax load start: ' + decodeURIComponent(href));
	
		var $tinymces = $inputfield.find('.InputfieldTinyMCELoaded');
		if($tinymces.length) InputfieldTinyMCE.destroyEditors($tinymces);

		$.get(href, function(data) {
			consoleLog('loaded data'); 
			$content.replaceWith($(data).find('.InputfieldTablePagerNavContent'));
			$inputfield.effect('highlight', 1000);
			$inputfield.removeClass('InputfieldStateChanged');
			var $table = $inputfield.find('table.InputfieldTable:not(.InputfieldTableSearch)');
			$table.children('tbody').children('tr:not(.InputfieldTableRowTemplate)').each(function() {
				initRow($(this));
			});
			consoleLog('initRows completed');
			$inputfield.trigger('reloaded', ['InputfieldTable']);
			consoleLog('trigger reloaded');
			$inputfield.find('.InputfieldPageAutocomplete').trigger('reloaded', ['InputfieldTable']);
			$inputfield.find('.InputfieldTextTags').trigger('reloaded', ['InputfieldTable']); 
			consoleLog('trigger reloaded autocomplete');
			$spinner.remove();
			consoleLog('ajax load done');
		});
	}

	/**
	 * Event handler for the "Reset Filters" action
	 * 
	 * Resets all find filters to be blank.
	 * 
	 * @returns {boolean}
	 * 
	 */
	function resetFilters() {
		
		var $a = $(this);
		var $inputfield = getInputfield($a);
		var $table = $a.closest('.InputfieldTableSearch');
		var href = $table.attr('data-search') + '&reset=1';
		
		$table.find(':input').each(function() {
			var $input = $(this);
			var type = $input.attr('type');
			if($input.is('select')) {
				$input.val('');
				$input.find('option[selected=selected]').removeAttr('selected');
			} else if(type == 'checkbox' || type == 'radio') {
				$input.removeAttr('checked');
			} else {
				$input.removeAttr('value');
			}
		});
		
		reloadContent($inputfield, href);
		
		return false;
	}

	/**
	 * Given search filter columns, build a selector string reflecting that search
	 * 
	 * @param $cols
	 * @returns {string}
	 * 
	 */
	function buildSelector($cols) {
		
		var operators = ['=', '>=', '<=', '!=', '%=', '<', '>', '^=', '$=', '*=', '~=', '!%='];
		var selector = '';
		
		$cols.each(function() {
			
			var $inputs = $(this).find(':input');
			
			$inputs.each(function() {

				var $input = $(this);
				var val = $input.val();
				
				val = typeof val == 'string' ? val.trim() : '';
				if(!val.length) return;

				// allow quotes to indicate "blank", but don't allow double quotes otherwise
				if(val != '""' && val.indexOf('"') > -1) return;

				var type = $input.is('select') ? 'select' : $input.attr('type');
				var op = '';
				var quoteVal = val.indexOf(',') > -1 || (val.indexOf("'") > -1 && val != "''");
				var name = $input.attr('name');
				var isArray = false;

				if(!name) return;
				name = name.replace('__find-', '');

				if(name.indexOf('[') > -1) {
					name = name.substring(0, name.length - 2);
					isArray = true;
				}

				if(isArray && val.indexOf(',') > -1) {
					if(val.indexOf(',') === 0) val = val.substring(1);
					val = val.split(',').join('|');
				}

				if(val.indexOf('=') > -1 || val.indexOf('<') === 0 || val.indexOf('>') === 0) {
					for(var n = 0; n < operators.length; n++) {
						if(val.indexOf(operators[n]) === 0) {
							op = operators[n];
							val = val.substring(op.length);
							consoleLog('name=' + name + ', op=' + op + ', val=' + val);
							break;
						}
					}
				}

				if(type == 'checkbox' || type == 'radio') {
					if(!$input.is(":checked")) return;
				} else if(type == 'number') {
					quoteVal = false;
				} else if(type == 'select') {
					// ok, fallback
				} else if(type == 'date' || type == 'datetime' || $input.attr('data-dateformat')) {
					// ok, fallback
				} else {
					if(!op.length) op = '%=';
				}

				// this is fallback
				if(!op.length) op = '=';

				if(quoteVal && val != '""' && val != "''") val = '"' + val + '"';
				var s = name + op + val + ',';
				if(selector.indexOf(s) == -1) selector += s;
			});
		});
		
		if(selector.length) selector = selector.substring(0, selector.length - 1);
		
		consoleLog('buildSelector: ' + selector);
		
		return selector;
	}

	/**
	 * Execute a search/filter of results
	 * 
	 * Search only executes once every 500ms, so this function may be called several times before 
	 * it is actually executed. 
	 * 
	 */
	function executeSearch() {

		var $input = $(this);
		var $inputfield = getInputfield($input);
		var isChanged = $inputfield.hasClass('InputfieldStateChanged');

		if(searchTimer) clearTimeout(searchTimer);

		searchTimer = setTimeout(function() {
			var $table = $input.closest('.InputfieldTableSearch');
			var $cols = $table.find('td');
			var selector = buildSelector($cols);
			var fieldName = getFieldName($inputfield);
			var find = '&' + fieldName + '_table_find=';
			var href = $table.attr('data-search');
			var pos = href.indexOf(find);
			if(pos > -1) href = href.substring(0, pos); // strip off "&find=" because we'll be adding a new one back
			consoleLog(selector);
			consoleLog(href + find + selector);
			href += find + encodeURIComponent(selector);
			if(!isChanged && $inputfield.hasClass('InputfieldStateChanged')) $inputfield.removeClass('InputfieldStateChanged');
			reloadContent($inputfield, href);
		}, 500);
	}

	/**
	 * Event heandler for the "Find" link click
	 * 
	 * @returns {boolean}
	 * 
	 */
	function searchToggleClick() {

		var $toggle = $(this);
		var $icon = $toggle.find('i.fa');
		var $inputfield = getInputfield($toggle);
		var $filters = $inputfield.find('.InputfieldTableSearch');

		if($filters.hasClass('toggling')) return false;

		// $icon.toggleClass($icon.attr('data-toggle-class'));

		if($filters.hasClass('InputfieldTableSearchActive')) {
			$filters.hide().removeClass('InputfieldTableSearchActive');
		} else {
			$filters.addClass('toggling').fadeIn('fast', function() {
				$filters.addClass('InputfieldTableSearchActive').removeClass('toggling');
			});
		}

		return false;
	}
	
	/**
	 * ID of last row that had its actions checkbox clicked
	 * 
	 * @type {number}
	 * 
	 */
	var lastCheckedRowID = 0;
	
	/**
	 * Event called when a row checkbox or sort arrow is clicked
	 * 
	 * Handles the shift-select for multiple rows
	 * 
	 * @param e
	 * 
	 */
	function rowSelectClick(e) {
		
		var checked = false;
		var selectedBackground = $('button.ui-button.ui-state-default[type=submit]').css('background');
		
		function getRowID($row) {
			var id = parseInt($row.find('.InputfieldTableRowID').val());
			// new or cloned rows have ID=0 so use negative sort value instead
			if(!id) id = -1 * parseInt($row.find('.InputfieldTableRowSort').val());
			return id;
		}
		
		function updateRowClass($tr) {
			if(checked) {
				$tr.addClass('InputfieldTableRowSelected').removeClass('uk-background-secondary');
			} else {
				$tr.removeClass('InputfieldTableRowSelected');
			}
		}
		
		var $row = $(this).closest('tr.InputfieldTableRow');
		var $inputfield = getInputfield($row);
		var rowID = getRowID($row);
		var $checkbox = $row.find('.InputfieldTableRowCheckbox');
		checked = $checkbox.is(':checked');
	
		if($(this).hasClass('InputfieldTableActionSort')) {
			// sort arrows clicked to select
			if(checked) {
				$checkbox.prop('checked', false);
				checked = false;
			} else {
				$checkbox.prop('checked', true);
				checked = true;
			}
		}
		
		updateRowClass($row);
		
		if(e.shiftKey || lastClickHadShift) {
			// selecting all rows between rowID and lastCheckedRowID 
			if(lastCheckedRowID) {
				var selecting = null; // true when selecting, false when finished
				$row.closest('tbody').children('tr').each(function() {
					if(selecting === false) return; // false when finished
					var $thisRow = $(this);
					var thisRowID = getRowID($thisRow);
					if(thisRowID == lastCheckedRowID || thisRowID == rowID) {
						selecting = selecting ? false : true;
					} else if(selecting) {
						$thisRow.find('.InputfieldTableRowCheckbox').prop('checked', checked);
						updateRowClass($thisRow);
					} else {
						// row outside of selection range
					}
				});
			}
		}
		
	
		lastCheckedRowID = rowID;
		
		updateActionSelect($inputfield);
	}
	
	/**
	 * Event when a file actions select is clicked
	 * 
	 * 
	 */
	function fileActionClick() {
		var $select = $(this);
		var $input = $select.next('.InputfieldTableFile');
		var value = $select.val();
		if(value === 'view') {
			window.open($select.attr('data-url'), $select.children().first().text());
			$select.val('');
		} else if(value === 'replace') {
			$input.removeAttr('hidden');
		}
		if(value != 'replace') {
			$input.attr('hidden', true);
		}
	}
	
	/**
	 * The 'input' event for input[type=file] 
	 * 
	 * Handles presentation of preview images.
	 * Used by the initRow() function.
	 * 
	 * @param e
	 * 
	 */
	function fileInputEvent(e) {
		
		var $input = $(this);
		var hide = true;
		var $img = $input.closest('td').find('img').first();
		
		if(!$img.length || !window.FileReader) return;
		
		if($input.val()) {
			var input = $input[0];
			var file = input.files && input.files[0] ? input.files[0] : null;
			var maxBytes = parseInt($input.attr('data-maxBytes'));
			
			if(maxBytes > 0 && file && file.size > maxBytes) {
				ProcessWire.alert(ProcessWire.config.InputfieldTable.maxBytes + ' (file=' + file.size + ', max=' + maxBytes + ')');
				e.stopPropagation();
				$input.val('');
				hide = true;
				
			} else if(file) {
				var reader = new FileReader();
				reader.onload = function(e) {
					var src = $img.attr('src');
					if(src.length && src.indexOf('data:') !== 0) {
						// remember previous value in case a replacement is jcancelled
						$img.attr('data-src', $img.attr('src'));
						$img.attr('data-srcset', $img.attr('srcset'));
					}
					$img.attr('src', e.target.result);
					$img.removeAttr('srcset');
				}
				reader.readAsDataURL(file);
				hide = false;
			}
		}
		if(hide) {
			if(typeof $img.attr('data-src') !== 'undefined') {
				$img.attr('src', $img.attr('data-src'));
				$img.attr('srcset', $img.attr('data-srcset')); 
				$img.removeAttr('data-src').removeAttr('data-srcset').removeAttr('hidden');
			} else {
				$img.attr('src', '');
				$img.attr('hidden', true);
			}
		} else {
			$img.removeAttr('hidden');
		}
	}
	
	/**
	 * Run whatever action is currently selected
	 * 
	 * @param $select
	 * 
	 */
	function runSelectedAction($select) {
		
		var action = $select.val();
		var $table = $select.closest('.InputfieldContent').find('table.InputfieldTable').eq(0);
		var $inputfield = getInputfield($table);
		var fieldSettings = getFieldSettings($inputfield);
		var $oneRow = $select.hasClass('InputfieldTableActionRowSelect') ? $select.closest('.InputfieldTableRow') : '';
		
		// $oneRow is used only if the action was selected from the row's action select
		// it becomes the actionable row when that row action select is used
		// when $oneRow is populated the $select is the <select> for the table row rather than the main actions one
		
		/**
		 * Get selected rows
		 * 
		 */	
		function getSelectedRows() {
			if($oneRow.length) return $oneRow;
			return $table.find('.InputfieldTableRowSelected');
		}
		
		/**
		 * Get all rows
		 * 
		 */
		function getAllRows() {
			return $table.find('.InputfieldTableRow:not(.InputfieldTableRowTemplate)');
		}
		
		/**
		 * Get the template row
		 * 
		 */
		function getTemplateRow() {
			return $table.find('.InputfieldTableRowTemplate').first();
		}
		
		/**
		 * Get the first selected row
		 * 
		 */
		function getFirstSelectedRow() {
			if($oneRow.length) return $oneRow;
			var $row = getSelectedRows().first();
			if(!$row.length) $row = getAllRows().first();
			if(!$row.length) $row = getTemplateRow();
			return $row;
		}
		
		/**
		 * Get the last selected row
		 * 
		 */
		function getLastSelectedRow() {
			if($oneRow.length) return $oneRow;
			var $row = getSelectedRows().last();
			if(!$row.length) $row = getAllRows().last();
			if(!$row.length) $row = getTemplateRow();
			return $row;
		}
		
		function startActivity() {
			var $icon = $inputfield.find('.InputfieldTableSelectAction').find('i');
			$icon.removeClass(fieldSettings.actionsIcon).addClass('fa-spin fa-cog');
		}
		
		function stopActivity() {
			var $icon = $inputfield.find('.InputfieldTableSelectAction').find('i');
			setTimeout(function() {
				$icon.removeClass('fa-spin fa-cog').addClass(fieldSettings.actionsIcon);
			}, 1000);
		}
		
		/**
		 * Updates the selected rows to show they are being acted upon
		 * 
		 */
		function startRowActivity($rows, iconClass) {
			if(typeof $rows === 'undefined') $rows = getSelectedRows();
			if(typeof iconClass === 'undefined') iconClass = 'fa-spin fa-cog';
			$rows.fadeTo('fast', 0.4, function() {
				$rows.fadeTo('fast', 1.0);
			});
			$rows.each(function() {
				var $row = $(this);
				var $actionIcon = $row.find('.InputfieldTableActionRowSelect').next('i');
				$actionIcon.removeClass(fieldSettings.actionsIcon).addClass(iconClass);
			});
		}
		
		/**
		 * Stops the activity indicator for selected rows
		 * 
		 */
		function stopRowActivity($rows, delay) {
			if(typeof $rows === 'undefined') $rows = getSelectedRows();
			if(typeof delay === 'undefined') delay = 250;
			var time1 = delay;
			var time2 = delay + 500;
			$rows.each(function() {
				var $row = $(this);
				var $actionIcon = $row.find('.InputfieldTableActionRowSelect').next('i');
				setTimeout(function() {
					$actionIcon.removeClass('fa-spin fa-cog').addClass('fa-check');
				}, time1);
				setTimeout(function() {
					$actionIcon.removeClass('fa-check').addClass(fieldSettings.actionsIcon);
				}, time2);
			});
		}
			
		/**
		 * Copy rows action
		 * 
		 */
		function actionCopy() {
			var $rows;
			var rowIDs = [];
			var numInvalid = 0;
			
			if($oneRow.length) {
				/*
				if($oneRow.hasClass('InputfieldTableRowSelected')) {
					// if action is from one row and it is selected, then copy all others selected as well
					$rows = $table.find('.InputfieldTableRowSelected');
				} else {
					// only copy the one row if it is not selected
					$rows = $oneRow;
				}
				*/
				$rows = $oneRow;
			} else {
				// copy all selected rows
				$rows = getSelectedRows();
			}
			
			$rows.each(function() {
				var rowID = parseInt($(this).find('.InputfieldTableRowID').val());
				if(rowID) {
					rowIDs.push(rowID);
				} else {
					numInvalid++;
				}
			});
			
			if(numInvalid) {
				ProcessWire.alert(ProcessWire.config.InputfieldTable.saveBeforeCopy); 
			} else {
				startActivity();
				startRowActivity($rows, 'fa-check');
				if(lastClickHadShift && $oneRow.length) {
					// if shift key active then add to existing copy selection
					var existingRowIDs = getPasteRowIDs($inputfield);
					for(var n = 0; n < rowIDs.length; n++) {
						existingRowIDs.push(rowIDs[n]); 
					}
					rowIDs = existingRowIDs;
				}
				setPasteRowIDs($inputfield, rowIDs);
				$select.children('option[value=paste]').prop('disabled', false);
				stopRowActivity($rows, 0);
				stopActivity();
				updateActionSelects();
			}
		}
		
		/**
		 * Paste rows action
		 * 
		 */
		function actionPaste() {
			var rowIDs = getPasteRowIDs($inputfield);
			var useAlert = false;
			
			if(!rowIDs.length) {
				ProcessWire.alert('No rows to paste');
				return;
			}
			
			startActivity();
		
			/*
			if($('.vex-dialog-button-primary').length === 0) {
				useAlert = true;
				// show an alert box about rows being pasted
				setTimeout(function() { $('.vex-dialog-button-primary').trigger('click'); }, 1000);
				ProcessWire.alert(ProcessWire.config.InputfieldTable.pastingNumRows.replace('%d', rowIDs.length), false);
			}
			*/
		
			var $mainSelect = $inputfield.find('.InputfieldTableActionSelect'); // since $select can be the $row one also
			var ajaxURL = $mainSelect.attr('data-ajax-url'); 
			var context = $inputfield.attr('data-context');
			
			ajaxURL = ajaxURL.replace(/\?id=\d+/, '?id=' + getPastePageID($inputfield));
			ajaxURL += '&table_row_ids=' + rowIDs.join(',');
			if(context) ajaxURL += '&table_context=' + context;
			
			jQuery.get(ajaxURL, function(data) {
				var $pasteRows = $(data).find('.InputfieldTable').children('tbody')
					.children('tr:not(.InputfieldTableRowTemplate)');
				$pasteRows.find('.InputfieldTableRowID').val('0');
				$pasteRows.find('.InputfieldTableRowSort').val('0');
				if(action === 'paste2') {
					// paste below
					var $lastSelectedRow = getLastSelectedRow();
					pasteRows($table, $pasteRows, null, $lastSelectedRow);
				} else if(action === 'paste1') {
					// paste above
					var $firstSelectedRow = getFirstSelectedRow();
					pasteRows($table, $pasteRows, $firstSelectedRow);
				} else if(action === 'paste') {
					pasteRows($table, $pasteRows);
				}
				startRowActivity($pasteRows);
				if(useAlert) {
					setTimeout(function() {
						$('.vex-dialog-button-primary').trigger('click');
					}, 1000);
				}
				stopActivity();
				setTimeout(function() {
					stopRowActivity($pasteRows);
				}, 1000);
			});
		}
		
		/**
		 * Add multiple rows action
		 * 
		 * @param above
		 * 
		 */
		function actionAddMulti(above) {
			setTimeout(function() {
				var select = "<select name='vex' class='uk-select'>";
				for(var n = 1; n <= 10; n++) {
					select += "<option>" + n + "</option>";
				}
				select += '</select>';
				var $select = $(select);
				$select.on('change', function() {
					$('.vex-dialog-button-primary').trigger('click');
				}); 
				$('.vex-dialog-prompt-input').after($select).remove();
			}, 100);
			ProcessWire.prompt(ProcessWire.config.InputfieldTable.howManyRows, '', function(value) {
				var str = value.toString();
				if(!str.match(/^\d+$/g)) return;
				value = parseInt(value);
				if(value < 1) return;
				for(var n = 0; n < value; n++) {
					if(typeof above === 'undefined') {
						addNewRow($table);
					} else if(above === true) {
						addNewRow($table, getFirstSelectedRow());
					} else if(above === false) {
						addNewRow($table, null, getLastSelectedRow());
					}
				}
			}); 
		}
		
		/**
		 * Add one row action
		 * 
		 */
		function actionAddOne(above) {
			if(above) {
				addNewRow($table, getFirstSelectedRow());
			} else {
				addNewRow($table, null, getFirstSelectedRow());
			}
		}
		
		/**
		 * Help action
		 * 
		 */
		function actionHelp() {
			if(!$('#InputfieldTableHelpStyle').length) $('head').append(
				'<style id="InputfieldTableHelpStyle" type="text/css">' + 
				'.vex.vex-theme-default .vex-content { width: 597px }' +
				'</style>'
			);
			var youtube =
				'<iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/fHAKJOoCsT8?si=GB6dMrVyq85dK1z9" ' +
				'title="YouTube video player" frameBorder="0" ' +
				'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" ' +
				'referrerPolicy="strict-origin-when-cross-origin" allowFullScreen></iframe>';
			ProcessWire.alert(youtube, true);
		}
		
		// ----------------------------------------------------------------------
		
		// remove all existing action classes from $table	
		$select.children('option').each(function() {
			$table.removeClass('InputfieldTableAction-' + $(this).val());
		});
		
		// add current action class to $table (not currently used)
		if(action.length) $table.addClass('InputfieldTableAction-' + action);
	
		if($oneRow.length && lastClickHadShift && action.substring(action.length-1) === '2') {
			// when shift-click, replace "below" (2) with "above" (1)
			action = action.replace('2', '1');
		}
	
		// re-sort the rows after executing action?
		var reSort = false;
		var $row;
	
		// execute selected action
		switch(action) {
			case 'copy': // copy rows
				actionCopy();
				break;
			case 'xcopy': // clear copy/paste queue
				setPasteRowIDs($inputfield, []);
				updateActionSelects();
				break;
			case 'paste': // paste
			case 'paste1': // paste above
			case 'paste2': // paste below
				actionPaste();
				reSort = true;
				break;
			case 'delete': // delete row(s)
				if($oneRow.length) {
					$oneRow.find('.InputfieldTableRowDeleteLink').trigger('click');
				} else {
					getSelectedRows().each(function() {
						$(this).find('.InputfieldTableRowDeleteLink').trigger('click');
					});
				}
				reSort = true;
				break;
			case 'undelete': // undelete row(s)
				getSelectedRows().filter('.InputfieldTableRowDeleted').each(function() {
					$(this).find('.InputfieldTableRowDeleteLink').trigger('click');
				});
				break;
			case 'select': // select row(s)
				if($oneRow.length) {
					$oneRow.find('.InputfieldTableActionSort').trigger('click');
				} else {
					getSelectedRows().each(function() {
						$(this).addClass('InputfieldTableRowSelected').removeClass('uk-background-secondary');
					});
				}
				break;
			case 'unselect': // unselect row(s)
				getSelectedRows().each(function() {
					$(this).removeClass('InputfieldTableRowSelected');
				});
				//$select.parent().prop('hidden', true);
				break;
			case 'all':	// select all
				$table.find('.InputfieldTableRow').addClass('InputfieldTableRowSelected');
				break;
			case 'all1': // select all above
				getSelectedRows().first().prevUntil('.InputfieldTableRowTemplate')
					.addClass('InputfieldTableRowSelected');
				break;
			case 'all2': // select all below
				getSelectedRows().last().nextAll('.InputfieldTableRow')
					.addClass('InputfieldTableRowSelected');
				break;
			case 'add': // add row(s)
				actionAddMulti();
				reSort = true;
				break;
			case 'add1': // add row above
				$row = addNewRow($table, getFirstSelectedRow());
				$row.find(':input:visible').first().trigger('focus');
				reSort = true;
				break;
			case 'add2': // add row below 
				$row = addNewRow($table, null, getLastSelectedRow());
				$row.find(':input:visible').first().trigger('focus');
				reSort = true;
				break;
			case 'add3': // add multiple rows above 
				actionAddMulti(true);
				reSort = true;
				break;
			case 'add4': // add multiple rows below
				actionAddMulti(false);
				reSort = true;
				break;
			case 'clone1': // clone row to new row above it
				cloneRowClick($oneRow, true);
				reSort = true;
				break;
			case 'clone2': // clone row to new row below it
				cloneRowClick($oneRow, false);
				reSort = true;
				break;
			case 'move1': // move to top
				$table.children('tbody').prepend(getSelectedRows());
				reSort = true;
				break;
			case 'move2': // move to bottom
				$table.children('tbody').append(getSelectedRows());
				reSort = true;
				break;
			case 'help': // help dialog
				actionHelp();
				break;
		}
		
		$select.val('');
		if(reSort) updateRowSorts($table);
		updateActionSelect($inputfield);
	}
	
	function getPasteCookieName($inputfield) {
		$inputfield = getInputfield($inputfield);
		return $inputfield.attr('data-field');
	}
	
	function setPasteRowIDs($inputfield, rowIDs) {
		$inputfield = getInputfield($inputfield);
		var pageId = $inputfield.attr('data-page');
		var cookieName = getPasteCookieName($inputfield);
		if(!pageId) pageId = $('#Inputfield_id').val();
		jQuery.cookie(cookieName, rowIDs);
		jQuery.cookie(cookieName + '_page', parseInt(pageId));
	}
	
	function getPasteRowIDs($inputfield) {
		$inputfield = getInputfield($inputfield);
		var cookieName = getPasteCookieName($inputfield);
		var rowIDs = jQuery.cookie(cookieName);
		if(!Array.isArray(rowIDs)) rowIDs = [];
		return rowIDs;
	}
	
	function getPastePageID($inputfield) {
		var cookieName = getPasteCookieName($inputfield) + '_page';
		var id = jQuery.cookie(cookieName);
		return (typeof id === 'undefined' || !id) ? 0 : parseInt(id);
	}
	
	/**
	 * Update disabled status of actions in the action select
	 * 
	 * @param $inputfield
	 * 
	 */
	function updateActionSelect($inputfield) {

		$inputfield = getInputfield($inputfield);
		
		var $select = $inputfield.find('.InputfieldTableActionSelect');
		if(!$select.length) return;
		
		var $table = $inputfield.find('table.InputfieldTable').children('tbody');
		var $rows = $table.find('.InputfieldTableRowSelected');
		var numSelected = $rows.length;
		var numOptions = $select.find('option').length;
		var hasPaste = getPasteRowIDs($inputfield).length > 0;
		
		if(numSelected) {
			$select.find('option').prop('disabled', false);
		} else {
			$select.find('option').prop('disabled', true);
		}

		// update undelete
		if(numSelected) {
			var $option = $select.find('option[value=undelete]');
			var qty = $table.find('.InputfieldTableRowDeleted.InputfieldTableRowSelected').length;
			$option.prop('disabled', qty > 0 ? false : true);
		}
	
		// update paste, paste1 and paste2 
		if(hasPaste) {
			$select.find('option[value=paste1], option[value=paste2]').prop('disabled', false);
			// $select.find('option[value=paste]').prop('disabled', true);
		} else {
			$select.find('option[value^=paste]').prop('disabled', true);
		}
		if(hasPaste) {
			$select.find('option[value=xcopy]').prop('disabled', false);
		}
	
		// select 'all', 'add' and 'help' always available
		$select.find('option[value=all],option[value^=add],option[value=help]').prop('disabled', false);
		
		var numOptionsAvailable = numOptions - $select.children('option:disabled').length; 
		
		if(numSelected || numOptionsAvailable) {
			$select.parent().prop('hidden', false);
		} else {
			$select.parent().prop('hidden', true);
		}
		
		$inputfield.find('.InputfieldTableActionRowSelect option[value^=paste]').prop('disabled', !hasPaste);
	}
	
	function updateActionSelects() {
		$('.Inputfield.InputfieldTable').each(function() {
			updateActionSelect($(this));
		});
	}
	
	/**
	 * Initialize Find/Search/Filters feature
	 * 
	 * @param $table
	 * 
	 */
	function initSearch($table) {
		
		var $inputfield = getInputfield($table); 
		var $searchTable = $inputfield.find('.InputfieldTableSearch');
		
		// make inputs in search/filters toggle ajax search request
		$searchTable.find(':input').each(function() {
			
			var $input = $(this);
			var type = $input.attr('type');
			
			if(type == 'radio' || type == 'checkbox') {
				$input.on('change', executeSearch);
				
			} else if($input.is('input') || $input.is('textarea')) {
				if($input.hasClass('ui-autocomplete-input')) {
					$input.on('change', executeSearch);
				} else {
					$input.on('keyup', executeSearch);
				}
				
			} else if($input.is('select')) {
				$input.on('change', executeSearch);
			}
		});

		// move the search/filters toggle to the header
		var $toggle = $inputfield.find('.InputfieldTableSearchToggle');
		if($toggle.length) {
			$inputfield.children('.InputfieldHeader').append($toggle);
		}
	}

	/**
	 * Initialize a table.InputfieldTable
	 * 
	 * @param $table
	 * 
	 */
	function initTable($table) {
		initSortable($table);
		var $rows = getRows($table);
		$rows.each(function() {
			initRow($(this));
		});
		initSearch($table);
		updateActionSelect($table);
	}

	/**
	 * Initialize InputfieldTable: to be called at document.ready()
	 * 
	 */
	function init() {
	
		// initialize events
		jQuery(document)
			.on('click', 'a.InputfieldTableAddRow', addRowClick)
			.on('click', '.InputfieldTableRowDeleteLink', deleteRowClick)
			.on('dblclick', '.InputfieldTableRowDeleteLink', deleteRowDblClick)
			.on('click', '.InputfieldTableRowCloneLink', cloneRowClick)
			.on('click', '.InputfieldTablePagerNav a', pagerNavClick)
			.on('click', '.InputfieldTableHeaderSortable', pagerNavClick)
			.on('click', '.InputfieldTableResetFilters', resetFilters)
			.on('click', '.InputfieldTableSearchToggle', searchToggleClick)
			.on('click', '.InputfieldTableActionSort', rowSelectClick)
			.on('change', '.InputfieldTableFileAction', fileActionClick)
			.on('input', 'input.InputfieldTableFile', fileInputEvent)
			.on('change', '.InputfieldTableActionSelect', function() {
				runSelectedAction($(this));
			})
			.on('click', '.InputfieldTableActionRowSelect', function(e) {
				lastClickHadShift = e.shiftKey;
			})
			.on('change', '.InputfieldTableActionRowSelect', function(e) {
				runSelectedAction($(this));
			})
			.on('cloned', '.InputfieldTable', function(e) {
				$(this).find('.InputfieldTableRowID').val(0);
			})
			.on('reloaded', '.Inputfield.InputfieldTable', function(event, from) {
				var $inputfield = $(this);
				// skip over locally generated reloaded events
				if(typeof from != "undefined" && from == 'InputfieldTable') {
					return;
				}
				initTable(getTable($inputfield));
				// notify autocompletes of the reloaded condition
				$inputfield.find('.InputfieldPageAutocomplete, .InputfieldTextTags')
					.trigger('reloaded', [ 'InputfieldTable' ]);
			})
			.on('change', '.InputfieldTableRow:not(.InputfieldTableRowNew)', function(e) {
				$(this).addClass('InputfieldTableRowChanged');
			}).on('visibilitychange', function(e) {
				if(document.visibilityState === "visible") {
					updateActionSelects();
				}
			}); 
		
		//$(document).on('change', '.InputfieldTable:not(.InputfieldTableSearch)', inputChange);

		// initialize tables
		var $tables = getAllTables();
		$tables.each(function() {
			var $table = $(this);
			initTable($table);
		});
		
		var background = $('button.ui-button.ui-state-default[type=submit]').css('background');
		$('head').append(
			'<style type="text/css">' +
				'table.InputfieldTable ' + 
				'tr.InputfieldTableRowSelected > td:not([class*="InputfieldTableAction"]) {' + 
					'background: ' + background + 
				'} ' + 
			"</style>"
		);
	}
	
	init();
}

jQuery(document).ready(function() {
	InputfieldTable();
});