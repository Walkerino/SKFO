function InputfieldFormBuilderFile() {
	
	var $ = jQuery;

	// image mime types
	var imageTypes = [
		'image/jpeg',
		'image/png',
		'image/apng',
		'image/gif',
		'image/svg+xml',
		'image/webp',
		'image/avif'
	];

	// file extensions to mime types (images or other types)
	var extsToTypes = {
		'jpeg': 'image/jpeg',
		'jpg': 'image/jpeg',
		'png': 'image/png',
		'gif': 'image/gif',
		'svg': 'image/svg+xml',
		'webp': 'image/webp',
		'avif': 'image/avif',
	};

	// given the <input> element return the FileReader files variable
	function getFileFromInput($input) {
		var input = $input[0];
		if(input.files && input.files[0]) {
			return input.files[0];
		}
		return null;
	}

	// get the file size (in bytes) for the file in given $input
	function getFileSize($input) {
		var input = $input[0];
		if(input.files && input.files[0]) {
			var file = input.files[0];
			return file.size;
		}
		return 0;
	}

	// get the lowercase extension of the given filename
	function getFileExt(name) {
		var pos = name.lastIndexOf('.');
		if(pos === -1) return '';
		var ext = name.substring(pos+1);
		return ext.toLowerCase();
	}

	// is the given FileReader file an image?
	function isImageType(file) {
		var valid = false;
		for(var n = 0; n < imageTypes.length; n++) {
			if(file.type === imageTypes[n]) valid = true;
			if(valid) break;
		}
		return valid;
	}

	// check that given $input has a valid file and alert with error if not
	function checkValidFile($input) {

		var $wrap = $input.closest('.InputfieldFormBuilderFileList');
		var maxSize = parseInt($wrap.attr('data-maxsize'));
		var file = getFileFromInput($input);
		var fileExt = getFileExt(file.name);
		var valid = true;
		var error = '';

		if(!file) return true;

		// check valid size
		if(file.size > 0 && maxSize > 0) {
			if(file.size > maxSize) {
				error = $wrap.attr('data-maxsize-label');
				valid = false;
			}
		}

		if(valid) {
			// check valid type and extension
			for(var ext in extsToTypes) {
				var type = extsToTypes[ext];
				if(file.type === type) {
					valid = fileExt === ext;
				} else if(fileExt === ext) {
					valid = file.type === type;
				}
				if(valid) break;
			}
			if(!valid) {
				error = $wrap.attr('data-badtype-label');
			}
		}

		if(!valid) {
			$input.val('');
			if(error.length) alert(error);
			return false;
		}

		return true;
	}

	// set the preview image for the given file $input
	function setPreviewImage($input) {

		var $wrap = $input.closest('.InputfieldFormBuilderFileUpload');
		var $div = $wrap.find('.InputfieldFormBuilderFilePreviewImage');
		var $img = $div.children('img');
		var file = getFileFromInput($input);
		var hide = false;

		if(file) {
			if(isImageType(file)) {
				var reader = new FileReader();
				reader.onload = function(e) {
					$img.attr('src', e.target.result);
					$div.addClass('InputfieldFormBuilderFilePreviewImageSet');
				}
				reader.readAsDataURL(file);
				hide = false;
			} else {
				hide = true;
			}
		} else {
			hide = true;
		}

		if(hide) {
			$div.removeClass('InputfieldFormBuilderFilePreviewImageSet');
			$img.attr('src', '#');
		}
	} // setPreviewImage

	// handle the input event for a file input
	function fileInputEvent() {
		var $input = $(this);
		if(!$input.val()) {
			setPreviewImage($input); 
			return;
		}

		if(window.FileReader) {
			if(checkValidFile($input)) {
				setPreviewImage($input);
			} else {
				return false;
			}
		}

		// var $wrapper = $input.closest('.InputfieldFormBuilderFileUpload') ;
		// var hiddenCls = 'InputfieldFormBuilderFileUploadHidden';
		// $wrapper.next('.' + hiddenCls).removeClass(hiddenCls);
		var $content = $input.closest('.InputfieldContent');
		if($content.find('.InputfieldFormBuilderFileUploadHidden').length) {
			// show 'Add file' button again if any files left
			$content.find('.InputfieldFormBuilderFileAdd').removeAttr('hidden');
		}
	}

	// event handler for the "add file" link
	function addFileClickEvent() {
		var $button = $(this);
		var $content = $button.closest('.InputfieldContent') ;
		var $items = $content.find('.InputfieldFormBuilderFileUploadHidden');
		
		$button.parent().attr('hidden', true); // hide the "add file" link if no more files
		var $item = $items.first();
		$item.removeClass('InputfieldFormBuilderFileUploadHidden').find('input[type=file]').trigger('click');
		$content.find('.InputfieldFormBuilderFileListHidden').removeClass('InputfieldFormBuilderFileListHidden');
		
		return false;
	}

	$(document).on('input', '.InputfieldFormBuilderFileUpload input[type=file]', fileInputEvent);
	$(document).on('click', '.InputfieldFormBuilderFileAdd > button', addFileClickEvent); 
}

/*
// For future addition:
// Example of how to add a drop zone where there is <div id='dropzone'>Drop files in here</div>
var $zone = $('#dropzone');
$zone.on('dragover drop', function(e) {
	e.preventDefault();
}).on('drop', function(e) {
	$('#test')[0].files = e.originalEvent.dataTransfer.files;
});
*/

jQuery(document).ready(InputfieldFormBuilderFile);