/**
 * JS for Date Range Inputfield 
 *
 * This file is part of the ProFields package
 * Please do not distribute.
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 */ 

let InputfieldDateRange = {
	
	/**
	 * Has document.ready event already occurred?
	 * 
	 */
	isReady: false,
	
	/**
	 * Queued items to be converted to datepickers at document.ready
	 * 
 	 */	
	queue: [],
	
	/**
	 * Initialized datepickers
	 * 
 	 */	
	datepickers: {},
	
	/**
	 * Add new datepicker item
	 * 
 	 * @param id
	 * @param options
	 * 
	 */	
	add: function(id, options) {
		if(this.isReady) {
			// initialize now if document.ready already occurred
			this.initInput(id, options);
		} else {
			// queue to initialize on document.ready
			this.queue.push({id: id, options: options});
		}
	},
	
	/**
	 * Called on document ready to initialize all queued date range inputs
	 * 
 	 */	
	ready: function() {
		for(let n = 0; n < this.queue.length; n++) {
			this.initInput(this.queue[n]['id'], this.queue[n]['options']); 
		}
		this.isReady = true;
		this.queue = [];
	},
	
	/**
	 * Event handler for single-day selection mode
	 * 
	 * @param td
	 * 
	 */
	onSingleDayClick: function(td) {
		// auto-select the end date to be same as start date
		if(this.end) return;
		$(td).trigger('click');
	},
	
	/**
	 * Return the tooltip text for hovering days in day mode
	 * 
 	 * @param days
	 * @param options
	 * @returns {string|string}
	 * 
	 */	
	hoveringTooltipForDayMode(days, options) {
		if(typeof options.i18n === 'undefined') {
			return days > 1 ? days + ' days' : '1 day';
		} else {
			let label = days > 1 ? options.i18n['days'] : options.i18n['day'];
			return days + ' ' + label;
		}
	},
	
	/**
	 * Reduce input value to just show one date (1-day mode)
	 * 
 	 * @param input
	 * 
	 */	
	makeInput1Day: function(input) {
		let val = input.val();
		let pos = val.indexOf(' - ');
		if(pos == -1) return;
		input.val(val.substring(0, pos));
	},
	
	/**
	 * Get the <input> element 
	 * 
 	 * @param id
	 * @returns {*|jQuery|HTMLElement}
	 * 
	 */	
	getInput: function(id) {
		return $('#' + id);
	},
	
	/**
	 * Get the datepicker instance
	 * 
 	 * @param id
	 * @returns {*}
	 * 
	 */	
	getDatepicker: function(id) {
		return this.datepickers[id];
	},
	
	
	/**
	 * Destroy the datepicker instance
	 * 
 	 * @param id
	 * 
	 */	
	destroy: function(id) {
		let datepicker = this.getDatepicker(id);
		if(datepicker) datepicker.destroy();
		this.datepickers[id] = null;
		let $input = $('#' + id);
		$input.removeClass('InputfieldDateRangeReady');
	},
	
	/**
	 * Reset the datepicker instance (destroy and re-create)
	 * 
 	 * @param id
	 * @param options
	 * 
	 */	
	reset: function(id, options) {
		let $this = this;
		console.log("reset " + id);
		this.destroy(id); 
		$this.initInput(id, options);
	}, 
	
	/**
	 * Initialize date range input
	 * 
 	 * @param id
	 * @param options
	 * 
	 */	
	initInput: function(id, options) {
		
		let input = document.getElementById(id);
		let $this = this;
		
		if(typeof this.datepickers[id] !== 'undefined' && this.datepickers[id] !== null) return;
		if(input.classList.contains('InputfieldDateRangeReady')) return
		
		if(typeof InputfieldDateRangeLabels !== 'undefined') {
			options.i18n = InputfieldDateRangeLabels;
		} else if(typeof ProcessWire.config['InputfieldDateRangeLabels'] !== 'undefined') {
			options.i18n = ProcessWire.config['InputfieldDateRangeLabels'];
		}
		
		if(options.labels) {
			// labels unique to this instance of datepicker
			for(let key in options.labels) {
				options.i18n[key] = options.labels[key];
			}
			options.labels = null;
		}
		
		if(options['dayLabelMode']) {
			options.hoveringTooltip = function(nights) {
				return $this.hoveringTooltipForDayMode(nights + 1, options);
			};
		}
		
		if(options['maxNights'] < 0) {
			options.onDayClick = this.onSingleDayClick;
			options.minNights = 0;
		}
		
		options.onSelectRange = function(e) {
			let input = $this.getInput(id);
			if(options['maxNights'] < 0) $this.makeInput1Day(input);
			input.trigger('change');
		};
		
		let datepicker = new HotelDatepicker(input, options);
		this.datepickers[id] = datepicker;
		// console.log('options', options);
		input.classList.add('InputfieldDateRangeReady');
	}
};

jQuery(document).ready(function() {
	InputfieldDateRange.ready();
}); 