function promailerSubscribersList() {
	
	var $content = $('#promailer-subscribers-list');
	var $form = $('#promailer-subscribers-form');
	var $find = $('#Inputfield_find_str');
	var listID = $content.attr('data-list');
	var timeout = null;
	var pageNum = 1;
	var pageNumUrlPrefix = ProcessWire.config['pageNumUrlPrefix'];
	var $sort, $pager, $spinner;

	// setup fields that are rendered in ajax data
	function setup() {
		$sort = $('#promailer-subscribers-sort');
		$pager = $('.MarkupPagerNav');
		$spinner = $('#promailer-spinner');
		
		$sort.on('change', function(e) {
			filter();
		});
		
		$pager.on('click', 'a', function(e) {
			if($(this).closest('.InputfieldForm').find('.InputfieldStateChanged').length) {
				// use full load when there have been changes so that the beforeunload event fires
				return true;
			}
			let url = $(this).attr('href');
			let re = new RegExp('/' + pageNumUrlPrefix + '([0-9]+)', 'i');
			let matches = url.match(re);
			if(matches) {
				pageNum = parseInt(matches[1]);
			} else {
				// fallback to default page[n] 
				matches = url.match(/\/page([0-9]+)/);
				pageNum = matches ? matches[1] : 1;
			}
			filter();	
			return false;
		}); 
		
		$sort.closest('.Inputfield').trigger('reloaded');
	}

	function filter() {
		let find = $find.val();
		let sort = $sort.val();
		let url = './';
		
		if(pageNum > 1) url += pageNumUrlPrefix + pageNum;
		url += '?list_id=' + listID;
		if(find.length) url += '&find_str=' + encodeURIComponent(find);
		if(sort.length) url += '&sort=' + sort;
		
		$spinner.fadeIn();
		
		$.get(url, function(data) {
			let $list = $(data).find('#promailer-subscribers-list');
			$content.html($list.html());
			$form.attr('action', url);
			setup();
		}); 
	}
	
	// ----------------------------------------

	$form.WireTabs({
		items: $('.Inputfields .ProMailerTab'),
		rememberTabs: true
	});
	
	setup();
	
	$find.on('input', function(e) {
		clearTimeout(timeout);
		timeout = setTimeout(function() { 
			pageNum = 1;
			filter(); 
		}, 500); 
	});
	
	$content.on('change', ':input', function(e) {
		if($(this).is('#promailer-subscribers-sort')) {
			e.stopPropagation();
			e.preventDefault();
			return false;
		}
		// console.log($(this).attr('name'));
		$(this).closest('.Inputfield').addClass('InputfieldStateChanged');
	});
	
}

function promailerSendingQueue() {
	
	var linkClicked = false;
	var messageId = $('#promailer-sending-queue').attr('data-message');
	
	setInterval(function() {
		if(linkClicked) return;
		$.get('../qstat/?message_id=' + messageId, function(data) {
			if(data.indexOf('..') === 0) {
				window.location.href = data; // url
			} else {
				$('#promailer-qstat').html(data); // status
			}
		})
	}, 2000);
	
	$('.InputfieldButtonLink').on('click', function() { 
		linkClicked = true; 
	}); 
	
	$('#promailer-button-stop').on('click', function() {
		let label = $('#promailer-button-stop').attr('data-confirm-label'); 
		if(!label) label = 'Are you sure you want abort this send?';
		if(confirm(label)) {
			return true;
		} else {
			return false;
		}
	}); 
}

$(document).ready(function() {
	if($('#promailer-subscribers-list').length) {
		// subscribers list
		promailerSubscribersList();
		
	} else if($('#promailer-sending-queue').length) {
		// sending queue in background
		promailerSendingQueue();
	}
}); 