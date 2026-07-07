/*!
 * Evo-CMS: Main Javascript
 */

function report(pid) {
	var reason = prompt('Pour quelle raison souhaitez-vous signaler?');
	if (reason) {
		$.post('', { 'csrf': csrf, 'report': reason, 'pid': pid }, function () { alert('Merci!'); });
	}
	return false;
}

/* Modified from annakata : http://stackoverflow.com/a/487049 */
function insertGetParam(key, value, qs) {
	key = encodeURIComponent(key);
	value = encodeURIComponent(value);

	var qs = qs || document.location.search;
	if (qs.substr(0, 1) == '?') {
		qs = qs.substr(1);
	}

	var kvp = qs.split('&');

	if (kvp == '') {
		return key + '=' + value;
	} else {
		var i = kvp.length; var x;
		while (i--) {
			x = kvp[i].split('=');

			if (x[0] == key) {
				if (value == '') {
					kvp[i] = '';
				} else {
					x[1] = value;
					kvp[i] = x.join('=');
				}
				break;
			}
		}
		if (i < 0 && value != '') { kvp[kvp.length] = [key, value].join('='); }

		return kvp.filter(function (v) { return v !== '' }).join('&');
	}
}


function poptart(message, sound, location, timeout) {
	var pop = $('div.poptart');
	if (pop.length == 0) {
		pop = $('<div class="poptart" style="display:none"></div>').appendTo('body');
	}

	sound = sound || false;
	location = location || 'bottom-right';
	timeout = timeout || 0;

	//if (pop.html() != message || !pop.is(':visible')) {

	pop.removeClass('top-left top-right bottom-left bottom-right').addClass(location);
	pop.stop();

	pop.fadeOut(500, function () {
		pop.html(message);
		if (timeout <= 0) {
			pop.slideDown(500);
		} else {
			pop.slideDown(500).delay(timeout).slideUp(500);
		}
	});
}


function ServerPoll() {
	$.get(site_url + '/ajax.php?action=servers&tz=' + (new Date()).getTimezoneOffset(), function (data) {
		$('#notifications').html(data);
	});
}


function draganddrop() {
	if (!$.fn.tableDnD) {
		return;
	}
	$('.sortable').tableDnD({
		onDrop: function (table, row) {
			$.post('', $.tableDnD.serialize() + '&csrf=' + csrf, function (data) { $(table).html($('#' + $(table).attr('id'), data).html()); draganddrop(); });
		}
	});
}


function spoiler(that) {
	var block = $(that).parent();
	var spoiler = block.find('> div');

	if (!spoiler.html().match(/<(div|p|br|table|embed|img\s*src|ul|ol)/)) { // probably inline content, let's hide the header
		$(that).hide();
		spoiler.fadeIn('slow', function () {
			block.toggleClass('visible', spoiler.is(':visible'));
		});
	} else {
		spoiler.toggle('slow', function () {
			block.toggleClass('visible', spoiler.is(':visible'));
		});
	}

	return false;
}


function hashchanged(event) {
	var hash = window.location.hash;

	if (hash.length < 2) return;

	if (hash.substr(0, 6) == '#alert') {
		var e = $('#msg' + hash.substr(6));
		if (!e) return;
		window.scrollTo(0, e.offset().top);
		e.css({ 'border-radius': '5px', 'background-color': '#f2dede', 'transition': 'background-color 1s linear' });
	} else if (hash.substr(0, 4) == '#msg') {
		$('.forum .highlight, .commentaires .highlight').removeClass('highlight');
		$(hash).addClass('highlight');
	} else {
		$('[data-bs-toggle="tab"][href="' + hash + '"], [data-bs-toggle="tab"][data-bs-target="' + hash + '"]').click();
	}
	return false;
}


function ajaxupload(oncomplete) {
	var e = $('<input type="file" class="hide">');

	e.appendTo('body');

	e.on('change', function () {

		var file = $(this)[0].files[0];

		if (!file) {
			return;
		} else if (typeof max_upload_size != 'undefined' && max_upload_size > 0 && file.size > max_upload_size) {
			alert('Fichier trop gros! Maximum: ' + (max_upload_size / 1024 / 1024) + ' MB');
			return;
		}

		var form = new FormData();
		form.append("ajaxup", file);

		$('body').append('<div class="modal-backdrop in"></div><div id="spinner" title="loading"><i class="fa-solid fa-5x fa-arrows-rotate fa-spin"></i><br><strong>Uploading...</strong></div></di>');

		$.ajax({
			url: '',
			xhr: function () {
				var myXhr = $.ajaxSettings.xhr();
				if (myXhr.upload) {
					myXhr.upload.addEventListener('progress', function (e) { if (e.lengthComputable) { $('#spinner strong').html('Uploading... ' + Math.round(e.loaded / e.total * 100) + '%'); } }, false);
				}
				return myXhr;
			},
			data: form,
			dataType: 'json',
			processData: false,
			contentType: false,
			type: 'POST',
			error: function (xhr) {
				alert("Erreur d'upload: " + xhr.responseText);
			},
			complete: function () {
				$('#spinner, .modal-backdrop').remove();
			},
			success: function (data) {
				if (typeof oncomplete == 'object' || typeof oncomplete == 'function') {
					oncomplete(data);
				}
			}
		});
	});
	e.click();
}



$.fn.image_selector = function (select) {
	var optgroup = select.find('option:selected').parent('optgroup');
	var that = $(this);

	if (optgroup.length == 0) {
		var images = select.find('option');
		group = '___root';
	} else {
		var images = optgroup.find('option');
		group = optgroup.attr('label');
	}

	if (images.length == 0) return;

	var selector_box = $('<div></div>').addClass('image_selector').attr('data-group', group);

	images.each(function () {
		var option = $(this);
		if (option.val() === '' && !option.attr('data-src-alt')) return;
		var img = $('<img>', { 'data-value': option.val(), 'data-group': group, title: option.text(), src: option.attr('data-src-alt') || site_url + option.val() });

		// Bootstrap 5 tooltip initialization for dynamic images
		img.on('DOMNodeInserted', function() {
			new bootstrap.Tooltip(this, { placement: 'bottom' });
		});
		option.attr('data-group', group);

		if (option.is(':selected'))
			img.addClass('selected');

		img.click(function () {
			select.val($(this).attr('data-value')).change().focus();
			option.focus();
		});

		selector_box.append(img);
	});

	select.unbind("change.imagesel keyup.imagesel click.imagesel");

	select.bind("change.imagesel keyup.imagesel click.imagesel", function () {

		var src = $(this).find(':selected').attr('data-src-alt') || site_url + $(this).find(':selected').val();

		$('#image_selector_preview').attr('src', src);

		if ($(this).find(':selected').attr('data-group') != selector_box.attr('data-group')) {
			selector_box.remove();
			that.image_selector(select);
		} else {
			selector_box.find('img').removeClass('selected');
			selector_box.find('img[data-value="' + $(this).val() + '"]').addClass('selected');
		}
	});

	if ($(this).length != 0) {
		$(this).html(selector_box);
	} else {
		select.after(selector_box);
	}
}


function autocomplete(callback, query, css) {
	autocomplete.popup = autocomplete.popup ||
		$('<div/>')
			.addClass('list-group autocomplete')
			.css({ position: 'absolute', 'min-width': '300px', 'max-height': '250px', 'z-index': 999, 'overflow-y': 'auto' })
			.appendTo('body');

	autocomplete.open = autocomplete.open || false;

	var popup = autocomplete.popup;

	autocomplete.next = function () {
		if (!this.open) return false;
		var next = this.popup.find('.active').removeClass('active').next();
		if (!next.length) {
			next = this.popup.find('a:first-child');
		}
		next.addClass('active');
		return this.value = next.attr('data-complete');
	}

	autocomplete.prev = function () {
		if (!this.open) return false;
		var prev = this.popup.find('.active').removeClass('active').prev();
		if (!prev.length) {
			prev = this.popup.find('a:last-child');
		}
		prev.addClass('active');
		return this.value = prev.attr('data-complete');
	}

	autocomplete.select = function () {
		if (!this.open) return false;
		return this.popup.find('.active').click();
	}

	autocomplete.hide = function () {
		this.popup.slideUp('fast');
		this.open = false;
		return !this.open;
	}

	if (typeof callback != 'object' && typeof callback != 'function') {
		autocomplete.hide();
		return;
	}

	query.action = query.action || 'userlist';

	popup.css(css);

	$.get(site_url + '/ajax.php', query, function (items) {
		popup.find('a').remove();
		for (var i in items) {
			items[i] = Object.keys(items[i]).map(function (key) { return items[i][key]; });
			var img = typeof items[i][2] == 'string' ?
				'<img class="float-end" style="max-height: 20px" src="' + items[i][2] + '">' : '';

			items[i][1] = items[i][1] || items[i][0];

			var u = $('<a href="" data-complete="' + items[i][0] + '"' +
				'class="list-group-item">' + items[i][1] + img + '</a>');
			u.click(function () {
				callback($(this).attr('data-complete'));
				autocomplete.hide();
				return false;
			});
			popup.append(u).slideDown('fast');
		};
		popup.find('a:first-child').addClass('active');
		autocomplete.value = i ? items[i][0] : null;
		autocomplete.open = !!i;
	}, 'json');
}


function pageload() {
	if (window.Prism) {
		Prism.highlightAll();
	}
	draganddrop();
	// Bootstrap 5 tooltips initialization
	var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]:not([title=""])'));
	var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
		return new bootstrap.Tooltip(tooltipTriggerEl, { placement: 'bottom' });
	});
	$('.fancybox-image, .gallery > .gallery-container a, a[href$=".png"], a[href$=".jpg"], a[href$=".gif"]').not('.no-fancy').fancybox({
		openEffect: 'elastic',
		openSpeed: 150,
		closeEffect: 'elastic',
		closeSpeed: 150,
		type: 'image',
		beforeShow: function () {
			var alt = this.element.find('img').attr('alt');
			this.inner.find('img').attr('alt', alt);
			this.title = alt;
		},
		helpers: {
			overlay: {
				css: {
					'background': 'transparent'
				}
			}
		},
		closeClick: true,
	});
	$(".fancybox").fancybox();
	$('.fancybox-ajax').fancybox({ type: 'ajax', scrolling: 'auto' });
	$('a.confirm, button.confirm, input.confirm').click(function () {
		return confirm('Êtes vous certain de vouloir effectuer cette action?');
	});
}


// From http://stackoverflow.com/questions/10211145/getting-current-date-and-time-in-javascript
// For todays date;
Date.prototype.today = function () {
	return ((this.getDate() < 10) ? "0" : "") + this.getDate() + "/" + (((this.getMonth() + 1) < 10) ? "0" : "") + (this.getMonth() + 1) + "/" + this.getFullYear();
}

// For the time now
Date.prototype.timeNow = function () {
	return ((this.getHours() < 10) ? "0" : "") + this.getHours() + ":" + ((this.getMinutes() < 10) ? "0" : "") + this.getMinutes() + ":" + ((this.getSeconds() < 10) ? "0" : "") + this.getSeconds();
}


window.onhashchange = hashchanged;
hashchanged();
pageload();
ServerPoll();

$('#avatar_selector_box').image_selector($('select.avatar_selector'));

setTimeout(function () {
	$('.alert-success.auto-dismiss').slideUp('slow');
}, 1800);


$.fn.autocomplete = function () {

}

$('[data-autocomplete]').on('keyup focusin', function (e) {
	var that = this;
	var m = $(this).attr('data-autocomplete-instant');

	if (e.keyCode == 9 || e.keyCode == 38 || e.keyCode == 40) {
		return false;
	}

	if (that.value.length < (m == undefined ? 1 : m)) return autocomplete();

	if (typeof that.acEnabled == 'undefined') {
		that.acEnabled = true;
		$(that).attr('autocomplete', 'off').unbind('blur').blur(function () {
			setTimeout(autocomplete, 100); // Time for the click event to register before in slides up.
		});
	}

	autocomplete(
		function (user) { that.value = user; },
		{ action: that.getAttribute('data-autocomplete'), query: that.value },
		{
			top: $(that).offset().top + $(that).outerHeight(true),
			left: $(that).offset().left,
			'min-width': $(that).outerWidth(true)
		}
	);
})
	.on('keydown', function (e) {
		if (!autocomplete.open) return;
		switch (e.keyCode) {
			case 9: //Tab
				autocomplete.select();
				e.preventDefault();
				break;
			case 38: //up
				autocomplete.prev();
				e.preventDefault();
				break;
			case 40: //down
				autocomplete.next();
				e.preventDefault();
				break;
		}
	});

$('[data-autocomplete]').attr('autocomplete', 'off');


$('#filter').attr('autocomplete', 'off').keyup(function () {
	var filter = $(this).val();
	var qs = insertGetParam('filter', filter, insertGetParam('pn', ''));

	$.get('?' + qs,
		function (data) {
			$('#content').html($('#content', '<div>' + data + '</div>').html());
			history.replaceState(null, null, '?' + qs);
		}
	);
});

$('form').on('submit', function () {
	/* If we do a real "disabled" the value of the button won't be sent. Some of our forms depend on it */
	$(this).find(':submit').click(function () { return false; }).addClass('disabled');
});


/* chrome fix */
window.addEventListener('load', function () {
	document.onkeydown = function (e) {
		if (e.ctrlKey && e.keyCode === 83) {
			$('textarea').parents('form').submit();
			return false;
		}
	};
});

function adminFileTypeDetect(mime, extension) {
	mime = String(mime || '').toLowerCase();
	extension = String(extension || '').toLowerCase();

	if (mime.indexOf('image/') === 0) {
		return 'image';
	}

	if (mime.indexOf('video/') === 0) {
		return 'video';
	}

	if (mime.indexOf('audio/') === 0) {
		return 'audio';
	}

	if (['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'tgz', 'jar', 'apk'].indexOf(extension) !== -1
		|| mime.indexOf('zip') !== -1
		|| mime.indexOf('compressed') !== -1
		|| mime.indexOf('archive') !== -1) {
		return 'archive';
	}

	if (['xls', 'xlsx', 'ods', 'csv'].indexOf(extension) !== -1
		|| mime.indexOf('spreadsheet') !== -1
		|| mime.indexOf('sheet') !== -1) {
		return 'spreadsheet';
	}

	if (['ppt', 'pptx', 'odp'].indexOf(extension) !== -1
		|| mime.indexOf('presentation') !== -1) {
		return 'presentation';
	}

	if (['html', 'htm', 'css', 'js', 'ts', 'json', 'xml', 'php', 'py', 'java', 'c', 'cpp', 'h'].indexOf(extension) !== -1
		|| mime.indexOf('javascript') !== -1
		|| mime.indexOf('json') !== -1
		|| mime.indexOf('xml') !== -1) {
		return 'code';
	}

	if (['ttf', 'otf', 'woff', 'woff2', 'eot'].indexOf(extension) !== -1
		|| mime.indexOf('font') !== -1) {
		return 'font';
	}

	if (['epub', 'mobi', 'azw', 'azw3'].indexOf(extension) !== -1) {
		return 'ebook';
	}

	if (['sql', 'db', 'sqlite'].indexOf(extension) !== -1
		|| mime.indexOf('sql') !== -1) {
		return 'database';
	}

	if (['exe', 'msi', 'dmg', 'deb', 'rpm'].indexOf(extension) !== -1
		|| mime.indexOf('executable') !== -1
		|| mime.indexOf('octet-stream') !== -1 && ['exe', 'msi', 'dmg', 'deb', 'rpm', 'bin'].indexOf(extension) !== -1) {
		return 'executable';
	}

	if (['pdf', 'doc', 'docx', 'odt', 'rtf'].indexOf(extension) !== -1
		|| mime.indexOf('pdf') !== -1
		|| mime.indexOf('document') !== -1
		|| mime.indexOf('word') !== -1) {
		return 'document';
	}

	if (['txt', 'md', 'log', 'diff'].indexOf(extension) !== -1
		|| mime.indexOf('text/') === 0) {
		return 'text';
	}

	if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'].indexOf(extension) !== -1) {
		return 'image';
	}

	return 'unknown';
}

function adminFileTypeIconFromFile(file, typeMap, extensionMap) {
	var parts = String(file.name || '').split('.');
	var extension = parts.length > 1 ? parts.pop().toLowerCase() : '';

	if (extensionMap && extensionMap[extension]) {
		return extensionMap[extension];
	}

	var type = adminFileTypeDetect(file.type, extension);

	if (typeMap && typeMap[type]) {
		return typeMap[type];
	}

	return (typeMap && typeMap.unknown) || { icon: 'fa-file', accent: 'secondary' };
}

function adminFileDropzoneGetMaps($zone) {
	var typeMap = $zone.data('fileTypeMap');
	var extensionMap = $zone.data('fileExtensionMap');

	if (!typeMap || typeof typeMap !== 'object') {
		try {
			typeMap = JSON.parse($zone.attr('data-file-type-map') || '{}');
		} catch (error) {
			typeMap = {};
		}
	}

	if (!extensionMap || typeof extensionMap !== 'object') {
		try {
			extensionMap = JSON.parse($zone.attr('data-file-extension-map') || '{}');
		} catch (error) {
			extensionMap = {};
		}
	}

	return { typeMap: typeMap, extensionMap: extensionMap };
}

function adminFileDropzoneApplyDetailBgIcon($icon, fileMeta) {
	var accent = fileMeta && fileMeta.accent ? fileMeta.accent : 'secondary';
	var icon = fileMeta && fileMeta.icon ? fileMeta.icon : 'fa-file';

	$icon.attr(
		'class',
		'icon-background icon-background--rotate-45 admin-file-dropzone__detail-bg-icon admin-file-dropzone__detail-bg-icon--'
			+ accent
			+ ' fas '
			+ icon
	);
}

function adminFileDropzoneFormatFileSize(bytes) {
	var units = ['B', 'KB', 'MB', 'GB', 'TB'];
	var size = bytes || 0;
	var unit = 0;

	while (size >= 1024 && unit < units.length - 1) {
		size /= 1024;
		unit += 1;
	}

	return (unit === 0 ? size : size.toFixed(2)) + ' ' + units[unit];
}

function adminFileDropzoneGetExtension(name) {
	var parts = String(name || '').split('.');

	return parts.length > 1 ? parts.pop().toUpperCase() : '—';
}

function adminFileDropzoneHumanName(filename) {
	var base = String(filename || '').replace(/\.[^.]+$/, '');

	base = base.replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim();

	if (!base) {
		return filename;
	}

	return base.charAt(0).toUpperCase() + base.slice(1);
}

function adminFileDropzoneNowPosted() {
	var now = new Date();
	var pad = function (value) {
		return String(value).padStart(2, '0');
	};

	return now.getFullYear()
		+ '-' + pad(now.getMonth() + 1)
		+ '-' + pad(now.getDate())
		+ ' ' + pad(now.getHours())
		+ ':' + pad(now.getMinutes());
}

function adminFormSetFieldValue($form, fieldName, value) {
	var $field = $form.find('[name="' + fieldName + '"]');

	if (!$field.length) {
		return;
	}

	var el = $field[0];

	if (el.tagName === 'TEXTAREA' && el.id) {
		var applied = false;
		var applyValue = function () {
			if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances[el.id]) {
				CKEDITOR.instances[el.id].setData(value);
				applied = true;
				return true;
			}

			$field.val(value);
			applied = true;
			return false;
		};

		if (!applyValue() && typeof CKEDITOR !== 'undefined') {
			var attempts = 0;
			var timer = window.setInterval(function () {
				if (applyValue() || ++attempts >= 20) {
					window.clearInterval(timer);

					if (!applied) {
						$field.val(value);
					}
				}
			}, 250);
		}

		return;
	}

	$field.val(value);
}

function adminFileDropzoneGetAutofillConfig($zone) {
	var config = $zone.data('autofill');

	if (!config || typeof config !== 'object') {
		try {
			config = JSON.parse($zone.attr('data-autofill') || 'null');
		} catch (error) {
			config = null;
		}
	}

	return config;
}

function adminFileDropzoneBuildDescription(file, $detail) {
	var lines = [];
	var sizeLabel = $detail.data('labelSize');
	var typeLabel = $detail.data('labelType');
	var mimeLabel = $detail.data('labelMime');

	if (sizeLabel) {
		lines.push('<p><strong>' + sizeLabel + ':</strong> ' + adminFileDropzoneFormatFileSize(file.size) + '</p>');
	}

	if (typeLabel) {
		lines.push('<p><strong>' + typeLabel + ':</strong> ' + adminFileDropzoneGetExtension(file.name) + '</p>');
	}

	if (mimeLabel) {
		lines.push('<p><strong>' + mimeLabel + ':</strong> ' + (file.type || '—') + '</p>');
	}

	return lines.join('');
}

function adminFileDropzoneApplyAutofill($zone, file, $detail) {
	var config = adminFileDropzoneGetAutofillConfig($zone);

	if (!config || !config.fields) {
		return;
	}

	var $form = config.form ? $(config.form) : $zone.closest('form');

	if (!$form.length) {
		return;
	}

	var fields = config.fields;

	if (fields.caption) {
		adminFormSetFieldValue($form, fields.caption, adminFileDropzoneHumanName(file.name));
	}

	if (fields.name) {
		adminFormSetFieldValue($form, fields.name, file.name);
	}

	if (fields.posted) {
		adminFormSetFieldValue($form, fields.posted, adminFileDropzoneNowPosted());
	}

	if (fields.description && config.build_description) {
		adminFormSetFieldValue($form, fields.description, adminFileDropzoneBuildDescription(file, $detail));
	}
}

function adminInitFileDropzones(context) {
	var $root = context ? $(context) : $(document);

	$root.find('.admin-file-dropzone').each(function () {
		var $zone = $(this);

		if ($zone.data('dropzoneInit')) {
			return;
		}

		$zone.data('dropzoneInit', true);

		var $input = $zone.find('.admin-file-dropzone__input');
		var $dropArea = $zone.find('.admin-file-dropzone__zone');
		var $preview = $zone.find('[data-dropzone-preview]');
		var $summary = $zone.find('[data-dropzone-summary]');
		var $detail = $zone.find('[data-dropzone-detail]');
		var hasDetail = $detail.length > 0;
		var autoSubmit = $zone.is('[data-auto-submit]');
		var hasPreview = $preview.length > 0;
		var tracksSelection = hasPreview || hasDetail;
		var detailObjectUrls = [];

		function revokeDetailObjectUrls() {
			detailObjectUrls.forEach(function (src) {
				URL.revokeObjectURL(src);
			});
			detailObjectUrls = [];
		}

		function formatFileSize(bytes) {
			return adminFileDropzoneFormatFileSize(bytes);
		}

		function getFileExtension(name) {
			return adminFileDropzoneGetExtension(name);
		}

		function clearFiles() {
			var emptyTransfer = new DataTransfer();
			$input[0].files = emptyTransfer.files;
			$input.val('');
			updatePreview();
		}

		function renderDetail(file) {
			var $media = $detail.find('[data-dropzone-detail-media]');
			var $name = $detail.find('[data-dropzone-detail-name]');
			var $meta = $detail.find('[data-dropzone-detail-meta]');
			var $statusText = $detail.find('[data-dropzone-detail-status-text]');
			var maps = adminFileDropzoneGetMaps($zone);
			var fileMeta = adminFileTypeIconFromFile(file, maps.typeMap, maps.extensionMap);

			revokeDetailObjectUrls();
			$media.empty();
			$meta.empty();
			$statusText.text($detail.data('detailStatus') || file.name);
			$name.text(file.name);

			if (file.type.indexOf('image/') === 0) {
				var imageUrl = URL.createObjectURL(file);
				detailObjectUrls.push(imageUrl);
				$media.append(
					$('<img class="admin-file-dropzone__detail-image">')
						.attr('src', imageUrl)
						.attr('alt', file.name)
				);
			} else {
				$media.append(
					$('<span class="admin-file-dropzone__detail-file admin-file-dropzone__detail-file--' + fileMeta.accent + '">')
						.append($('<i class="fa-solid ' + fileMeta.icon + '" aria-hidden="true">'))
						.append($('<span class="admin-file-dropzone__detail-ext">').text(getFileExtension(file.name)))
				);
			}

			adminFileDropzoneApplyDetailBgIcon($detail.find('[data-dropzone-detail-bg-icon]'), fileMeta);

			[
				{ label: $detail.data('labelSize'), value: formatFileSize(file.size) },
				{ label: $detail.data('labelType'), value: getFileExtension(file.name) },
				{ label: $detail.data('labelMime'), value: file.type || '—' }
			].forEach(function (item) {
				if (!item.label) {
					return;
				}

				$meta.append(
					$('<div class="admin-file-dropzone__detail-meta-item">')
						.append($('<dt>').text(item.label))
						.append($('<dd>').text(item.value))
				);
			});

			adminFileDropzoneApplyAutofill($zone, file, $detail);
		}

		function updateDetailView(files) {
			if (!hasDetail) {
				return;
			}

			var $headerActions = $zone.closest('.admin-settings-subsection').find('[data-dropzone-header-actions]');

			if (!files.length) {
				revokeDetailObjectUrls();
				$dropArea.prop('hidden', false);
				$detail.prop('hidden', true);

				if ($headerActions.length) {
					$headerActions.prop('hidden', true);
				}

				return;
			}

			$dropArea.prop('hidden', true);
			$detail.prop('hidden', false);

			if ($headerActions.length) {
				$headerActions.prop('hidden', false);
			}

			renderDetail(files[0]);
		}
		var accepted = ($input.attr('accept') || '').split(',').map(function (value) {
			return value.trim().toLowerCase();
		}).filter(Boolean);

		function fileAllowed(file) {
			if (!accepted.length) {
				return true;
			}

			var name = file.name.toLowerCase();
			var type = (file.type || '').toLowerCase();

			return accepted.some(function (rule) {
				if (rule.charAt(0) === '.') {
					return name.endsWith(rule);
				}

				if (rule.slice(-2) === '/*') {
					return type.indexOf(rule.slice(0, -1)) === 0;
				}

				return type === rule;
			});
		}

		function updatePreview() {
			var files = Array.from($input[0].files || []);
			var $title = $zone.find('.admin-file-dropzone__title');
			var defaultTitle = $title.data('defaultTitle');

			if (defaultTitle === undefined) {
				$title.data('defaultTitle', $title.text());
				defaultTitle = $title.text();
			}

			$preview.find('img').each(function () {
				var src = this.src;

				if (src.indexOf('blob:') === 0) {
					URL.revokeObjectURL(src);
				}
			});

			$preview.empty();
			$zone.toggleClass('has-selection', files.length > 0);
			updateDetailView(files);

			if (hasDetail) {
				$preview.prop('hidden', true);

				if ($summary.length) {
					$summary.prop('hidden', true);
				}

				if (!files.length) {
					$title.text(defaultTitle);
				}

				return;
			}

			if (!files.length) {
				$preview.prop('hidden', true);
				$title.text(defaultTitle);

				if ($summary.length) {
					$summary.prop('hidden', true);
				}

				return;
			}

			if (files.length === 1 && $zone.hasClass('admin-file-dropzone--compact')) {
				$title.text(files[0].name);
			} else {
				$title.text(defaultTitle);
			}

			$preview.prop('hidden', false);

			files.forEach(function (file, index) {
				var $item = $('<figure class="admin-file-dropzone__preview-item">');
				var maps = adminFileDropzoneGetMaps($zone);
			var fileMeta = adminFileTypeIconFromFile(file, maps.typeMap, maps.extensionMap);

				if (file.type.indexOf('image/') === 0) {
					$item.append($('<img class="admin-file-dropzone__preview-image">').attr('src', URL.createObjectURL(file)).attr('alt', file.name));
				} else {
					$item.append(
						$('<span class="admin-file-dropzone__preview-file admin-file-dropzone__preview-file--' + fileMeta.accent + '">')
							.append($('<i class="fa-solid ' + fileMeta.icon + '" aria-hidden="true"></i>'))
					);
				}

				$item.append($('<figcaption class="admin-file-dropzone__preview-name">').text(file.name));
				$item.append(
					$('<button type="button" class="admin-file-dropzone__preview-remove" aria-label="Remove">')
						.html('<i class="fa-regular fa-trash-can" aria-hidden="true"></i>')
						.data('index', index)
				);
				$preview.append($item);
			});

			if ($summary.length) {
				$summary.text(($summary.data('template') || '').replace('%count%', files.length)).prop('hidden', false);
			}
		}

		function setFiles(fileList, accumulate) {
			var dt = new DataTransfer();

			if (accumulate && hasPreview && $input[0].files) {
				Array.from($input[0].files).forEach(function (file) {
					dt.items.add(file);
				});
			}

			Array.from(fileList).forEach(function (file) {
				if (fileAllowed(file)) {
					dt.items.add(file);
				}
			});

			$input[0].files = dt.files;

			if (hasPreview) {
				updatePreview();
			} else if (hasDetail) {
				updateDetailView(Array.from($input[0].files || []));
				$zone.toggleClass('has-selection', $input[0].files && $input[0].files.length > 0);
			}

			if (autoSubmit && dt.files.length) {
				$zone.closest('form').trigger('submit');
			}
		}

		$preview.on('click', '.admin-file-dropzone__preview-remove', function (event) {
			event.preventDefault();
			event.stopPropagation();

			var removeIndex = $(this).data('index');
			var dt = new DataTransfer();

			Array.from($input[0].files).forEach(function (file, index) {
				if (index !== removeIndex) {
					dt.items.add(file);
				}
			});

			$input[0].files = dt.files;
			updatePreview();
		});

		var $dropzoneSection = $zone.closest('.admin-settings-subsection');
		var $dropzoneEventsRoot = $dropzoneSection.length ? $dropzoneSection : $zone;

		$dropzoneEventsRoot.on('click', '[data-dropzone-detail-remove]', function (event) {
			event.preventDefault();
			event.stopPropagation();
			clearFiles();
		});

		$dropArea.on('dragenter dragover', function (event) {
			event.preventDefault();
			event.stopPropagation();
			$dropArea.addClass('is-dragover');
		});

		$dropArea.on('dragleave dragend drop', function (event) {
			if (event.type !== 'drop') {
				$dropArea.removeClass('is-dragover');
			}
		});

		$dropArea.on('drop', function (event) {
			event.preventDefault();
			event.stopPropagation();
			$dropArea.removeClass('is-dragover');

			if (event.originalEvent.dataTransfer && event.originalEvent.dataTransfer.files.length) {
				setFiles(event.originalEvent.dataTransfer.files, hasPreview && !autoSubmit);
			}
		});

		$zone.on('click', '[data-dropzone-trigger]', function (event) {
			event.preventDefault();
			event.stopPropagation();
			$input.trigger('click');
		});

		$dropArea.on('click', function (event) {
			if ($(event.target).closest('[data-dropzone-trigger], .admin-file-dropzone__preview-remove, [data-dropzone-detail-remove], button, a, input').length) {
				return;
			}

			$input.trigger('click');
		});

		$dropArea.on('keydown', function (event) {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				$input.trigger('click');
			}
		});

		$input.on('change', function () {
			if (tracksSelection) {
				updatePreview();
			}

			if (autoSubmit && this.files && this.files.length) {
				$zone.closest('form').trigger('submit');
			}
		});
	});
}

function adminInitAvatarsBoard(context) {
	var $root = context ? $(context).find('.admin-avatars').addBack('.admin-avatars') : $('.admin-avatars');

	if (!$root.length) {
		return;
	}

	function adminReplaceAlerts(html) {
		var $alerts = $('#admin-page .alerts');

		if (!$alerts.length) {
			return;
		}

		$alerts.html(html || '');
	}

	function adminKeepCategoryOpen($article) {
		var $collapse = $article.find('.admin-avatars-category__body');
		var $toggle = $article.find('.admin-collapsible__toggle');

		if (!$collapse.length) {
			return;
		}

		$collapse.addClass('show');
		$toggle.removeClass('collapsed').attr('aria-expanded', 'true');
	}

	function adminApplyAvatarsPayload($article, payload) {
		if (!payload) {
			return;
		}

		if (payload.body) {
			var $body = $article.find('.admin-avatars-category__body');
			$body.html(payload.body);
			adminInitFileDropzones($body[0]);
			adminInitImagePreview($body[0]);
		}

		if (payload.meta) {
			$article.find('.admin-avatars-category__meta').text(payload.meta);
		}

		if (payload.stats) {
			$root.find('.admin-stat-grid').first().replaceWith(payload.stats);
		}

		if (typeof payload.alerts === 'string') {
			adminReplaceAlerts(payload.alerts);
		}

		adminKeepCategoryOpen($article);
	}

	function adminAvatarsFallbackName(file, index) {
		var ext = 'png';

		if (file.type === 'image/jpeg') {
			ext = 'jpg';
		} else if (file.type === 'image/gif') {
			ext = 'gif';
		} else if (file.type && file.type.indexOf('image/') === 0) {
			ext = file.type.split('/')[1].replace('jpeg', 'jpg') || 'png';
		}

		return 'avatar-' + Date.now() + '-' + index + '.' + ext;
	}

	function adminBuildAvatarsFormData($form, submitter) {
		var formData = new FormData();

		$form.find('input, select, textarea').each(function () {
			var $field = $(this);
			var name = $field.attr('name');
			var type = ($field.attr('type') || '').toLowerCase();

			if (!name || type === 'file' || type === 'submit' || type === 'button') {
				return;
			}

			if ((type === 'checkbox' || type === 'radio') && !$field.prop('checked')) {
				return;
			}

			formData.append(name, $field.val());
		});

		if (submitter && submitter.name) {
			formData.append(submitter.name, submitter.value);
		}

		var $fileInput = $form.find('.admin-file-dropzone__input');
		Array.from($fileInput[0].files || []).forEach(function (file, index) {
			var filename = file.name && String(file.name).trim() !== ''
				? file.name
				: adminAvatarsFallbackName(file, index);

			formData.append('upload[]', file, filename);
		});

		return formData;
	}

	function adminSubmitAvatarsCategory($form, submitter) {
		var $article = $form.closest('.admin-avatars-category');
		var $body = $article.find('.admin-avatars-category__body');
		var formData = adminBuildAvatarsFormData($form, submitter);

		$body.addClass('admin-avatars-category__body--busy');

		return $.ajax({
			url: '?page=avatars&view=library',
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			dataType: 'json'
		}).done(function (payload) {
			adminApplyAvatarsPayload($article, payload);
		}).fail(function (xhr) {
			var payload = xhr.responseJSON;

			if (payload && payload.alerts) {
				adminReplaceAlerts(payload.alerts);
			}
		}).always(function () {
			$body.removeClass('admin-avatars-category__body--busy');
		});
	}

	$root.on('submit', '.admin-avatars-category__form', function (event) {
		var submitter = event.originalEvent && event.originalEvent.submitter;

		if (submitter && submitter.name === 'delete_category') {
			return;
		}

		var $form = $(this);
		var isDelete = submitter && submitter.name === 'delete_avatar';
		var $fileInput = $form.find('.admin-file-dropzone__input');
		var isUpload = $fileInput.length && $fileInput[0].files && $fileInput[0].files.length > 0;

		if (!isDelete && !isUpload) {
			return;
		}

		event.preventDefault();
		adminSubmitAvatarsCategory($form, submitter);
	});
}

function adminInitImagePreview(context) {
	var $scope = context ? $(context) : $(document);

	$scope.find('.admin-image-preview-modal').each(function () {
		var $modal = $(this);
		if (!$modal.parent().is('body')) {
			$modal.appendTo('body');
		}
	});

	$scope.on('click', '[data-admin-image-preview]', function (event) {
		if ($(event.target).closest('.admin-avatars-card__delete, button[name="delete_avatar"], a, input').length) {
			return;
		}

		var $trigger = $(this);
		var url = $trigger.attr('data-admin-image-preview') || $trigger.find('img').attr('src');
		var title = $trigger.attr('data-admin-image-title') || $trigger.find('img').attr('alt') || '';
		var modalId = $trigger.attr('data-admin-image-modal') || 'admin-image-preview-modal';
		var $modal = $('#' + modalId);

		if (!$modal.length || !url) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		if (!$modal.parent().is('body')) {
			$modal.appendTo('body');
		}

		var $img = $modal.find('.admin-image-preview-modal__img');
		$modal.find('.admin-image-preview-modal__title').text(title);
		$img.attr({ alt: title }).addClass('admin-image-preview-modal__img--loading');

		var preview = new Image();
		preview.onload = preview.onerror = function () {
			$img.attr('src', url).removeClass('admin-image-preview-modal__img--loading');

			if (window.bootstrap && bootstrap.Modal) {
				bootstrap.Modal.getOrCreateInstance($modal[0]).show();
			}
		};
		preview.src = url;
	});
}

function adminIconSelectResolveClasses(value) {
	if (!value) {
		return '';
	}

	var stylePrefixes = {
		fas: 'fa-solid',
		far: 'fa-regular',
		fab: 'fa-brands',
		fa: 'fa-solid',
		'fa-solid': 'fa-solid',
		'fa-regular': 'fa-regular',
		'fa-brands': 'fa-brands'
	};

	var tokens = String(value).trim().split(/\s+/);
	var style = 'fa-solid';
	var icon = '';

	tokens.forEach(function (token) {
		if (stylePrefixes[token]) {
			style = stylePrefixes[token];
			return;
		}

		if (token.indexOf('fa-') === 0) {
			icon = token;
		}
	});

	if (!icon && tokens.length === 1) {
		icon = tokens[0].indexOf('fa-') === 0 ? tokens[0] : 'fa-' + tokens[0];
	}

	if (!icon && value.indexOf('fa-') === 0) {
		icon = value;
	}

	if (!icon) {
		icon = 'fa-' + value;
	}

	return style + ' ' + icon;
}

function adminInitIconSelects(context) {
	var $scope = context ? $(context) : $(document);

	$scope.find('select[data-admin-icon-select]').each(function () {
		var $native = $(this);

		if ($native.data('adminIconSelectInit')) {
			return;
		}

		$native.data('adminIconSelectInit', true);

		var selectId = $native.attr('id') || '';
		var searchPlaceholder = $native.attr('data-search-placeholder') || 'Search…';
		var $main = $native.closest('.admin-icon-select__main');
		var $wrap = $native.closest('.admin-icon-select');
		var $trigger = $('<button type="button" class="admin-icon-select__trigger form-control"></button>');
		var $dropdown = $('<div class="admin-icon-select__dropdown" hidden></div>');
		var $search = $('<input type="search" class="admin-icon-select__search form-control form-control-sm">')
			.attr({ autocomplete: 'off', placeholder: searchPlaceholder });
		var $list = $('<ul class="admin-icon-select__list" role="listbox"></ul>');
		var items = [];

		$main.prepend($trigger);
		$main.append($dropdown);
		$dropdown.append($search, $list);

		$native.addClass('admin-icon-select__native').attr({ tabindex: '-1', 'aria-hidden': 'true' });

		$native.children().each(function () {
			if (this.tagName === 'OPTGROUP') {
				var group = this.getAttribute('label') || '';

				$(this).children('option').each(function () {
					items.push({
						group: group,
						value: this.value,
						label: $(this).text().trim()
					});
				});
				return;
			}

			if (this.tagName === 'OPTION') {
				items.push({
					group: '',
					value: this.value,
					label: $(this).text().trim()
				});
			}
		});

		function renderList(filter) {
			var query = (filter || '').toLowerCase();
			var currentGroup = null;

			$list.empty();

			items.forEach(function (item) {
				if (query && item.label.toLowerCase().indexOf(query) === -1 && item.value.toLowerCase().indexOf(query) === -1) {
					return;
				}

				if (item.group && item.group !== currentGroup) {
					currentGroup = item.group;
					$list.append($('<li class="admin-icon-select__group" role="presentation"></li>').text(currentGroup));
				}

				var classes = adminIconSelectResolveClasses(item.value);
				var $option = $('<li class="admin-icon-select__option" role="option"></li>')
					.attr('data-value', item.value)
					.toggleClass('is-selected', $native.val() === item.value);

				if (classes) {
					$option.append($('<i aria-hidden="true"></i>').addClass(classes));
				} else {
					$option.append($('<i class="fa-regular admin-icon-select__option-empty fa-circle" aria-hidden="true"></i>'));
				}

				$option.append($('<span class="admin-icon-select__option-label"></span>').text(item.label));
				$list.append($option);
			});
		}

		function updateTrigger() {
			var value = $native.val() || '';
			var label = '';
			var classes = adminIconSelectResolveClasses(value);

			items.some(function (item) {
				if (item.value === value) {
					label = item.label;
					return true;
				}

				return false;
			});

			if (!label) {
				label = items.length && items[0].value === '' ? items[0].label : '—';
			}

			$trigger.empty();

			if (classes) {
				$trigger.append($('<i class="admin-icon-select__trigger-icon" aria-hidden="true"></i>').addClass(classes));
			}

			$trigger
				.append($('<span class="admin-icon-select__trigger-label"></span>').text(label))
				.append($('<i class="fa-solid admin-icon-select__caret fa-chevron-down" aria-hidden="true"></i>'));
		}

		function close() {
			$dropdown.prop('hidden', true);
			$wrap.removeClass('is-open');
			$trigger.attr('aria-expanded', 'false');
			$(document).off('click.adminIconSelect');
		}

		function open() {
			renderList('');
			$dropdown.prop('hidden', false);
			$wrap.addClass('is-open');
			$trigger.attr('aria-expanded', 'true');
			$search.val('').trigger('focus');

			$(document).on('click.adminIconSelect', function (event) {
				if (!$wrap[0].contains(event.target)) {
					close();
				}
			});
		}

		function selectValue(value) {
			$native.val(value).trigger('change');
			updateTrigger();
			close();
		}

		$trigger.attr({
			type: 'button',
			'aria-haspopup': 'listbox',
			'aria-expanded': 'false'
		});

		if (selectId) {
			$list.attr('id', selectId + '-list');
			$trigger.attr('aria-controls', selectId + '-list');
		}

		updateTrigger();

		$trigger.on('click', function (event) {
			event.preventDefault();
			event.stopPropagation();

			if ($wrap.hasClass('is-open')) {
				close();
			} else {
				open();
			}
		});

		$list.on('click', '.admin-icon-select__option', function () {
			selectValue($(this).attr('data-value'));
		});

		$search.on('input', function () {
			renderList(this.value);
		});

		$search.on('click', function (event) {
			event.stopPropagation();
		});

		$native.on('change', updateTrigger);
		$wrap.addClass('is-ready');
	});
}

function adminInitToolbarSearchLive($form, $input) {
	var targetSelector = $form.attr('data-admin-toolbar-search-target') || '[data-admin-toolbar-search-rows]';
	var $target = $(targetSelector).first();

	if (!$target.length) {
		return;
	}

	var $rows = $target.find('tr[data-search-text]');

	if (!$rows.length) {
		$rows = $target.find('tr');
	}

	var $empty = $($form.attr('data-admin-toolbar-search-empty') || '');
	var $count = $($form.attr('data-admin-toolbar-search-count') || '');
	var $reset = $form.find('[data-admin-toolbar-search-reset]');
	var $tableWrap = $target.closest('.admin-modules-table-wrap');

	function normalizeQuery(value) {
		return String(value || '').toLowerCase().trim();
	}

	function syncSearchUrl(query) {
		if (typeof insertGetParam !== 'function') {
			return;
		}

		var qs = insertGetParam('filter', query, insertGetParam('pn', '', window.location.search));

		if (qs) {
			history.replaceState(null, '', '?' + qs);
		}
	}

	function applyFilter() {
		var query = normalizeQuery($input.val());
		var visible = 0;

		$rows.each(function () {
			var haystack = String($(this).attr('data-search-text') || $(this).text() || '').toLowerCase();
			var match = query === '' || haystack.indexOf(query) !== -1;

			$(this).toggle(match);

			if (match) {
				visible += 1;
			}
		});

		if ($count.length) {
			$count.text(String(visible));
		}

		if ($empty.length) {
			$empty.toggleClass('d-none', visible > 0 || query === '');
		}

		if ($tableWrap.length) {
			$tableWrap.toggleClass('d-none', visible === 0 && query !== '');
		}

		if ($reset.length) {
			$reset.toggleClass('d-none', query === '').prop('hidden', query === '');
		}

		syncSearchUrl(query);
	}

	$input.on('input', applyFilter);

	$input.on('keydown', function (event) {
		if (event.key === 'Enter') {
			event.preventDefault();
		}
	});

	$form.on('submit', function (event) {
		event.preventDefault();
		applyFilter();
	});

	$reset.on('click', function (event) {
		event.preventDefault();
		$input.val('').trigger('focus');
		applyFilter();
	});

	applyFilter();
}

function adminInitToolbarSearch(context) {
	var $scope = context ? $(context) : $(document);

	$scope.find('[data-admin-toolbar-search]').each(function () {
		var $form = $(this);

		if ($form.data('adminToolbarSearchInit')) {
			return;
		}

		$form.data('adminToolbarSearchInit', true);
		$form.find('.admin-filter-chips').remove();

		var $input = $form.find('[data-admin-toolbar-search-input]');

		if (!$input.length) {
			$input = $form.find('input[name="filter"]');
		}

		if (!$input.length) {
			return;
		}

		var mode = String($form.attr('data-admin-toolbar-search-mode') || 'remote').toLowerCase();

		$input.attr('autocomplete', 'off');

		if (mode === 'live') {
			adminInitToolbarSearchLive($form, $input);
			return;
		}

		var debounceTimer = null;
		var delay = parseInt($form.attr('data-admin-toolbar-search-delay') || '350', 10);

		function submitSearch() {
			$form.find('input[name="pn"], input[name="prevpn"]').remove();
			$form.get(0).submit();
		}

		$input.on('input', function () {
			window.clearTimeout(debounceTimer);
			debounceTimer = window.setTimeout(submitSearch, delay);
		});

		$input.on('keydown', function (event) {
			if (event.key === 'Enter') {
				window.clearTimeout(debounceTimer);
			}
		});

		$form.on('submit', function () {
			$form.find('input[name="pn"], input[name="prevpn"]').remove();
		});
	});
}

function adminInitTabsPanelResize() {
	if (adminInitTabsPanelResize.initialized) {
		return;
	}

	adminInitTabsPanelResize.initialized = true;

	function getTabTarget(tab) {
		var selector = tab.getAttribute('data-bs-target') || tab.getAttribute('href') || '';

		if (!selector || selector.charAt(0) !== '#') {
			return null;
		}

		try {
			return document.querySelector(selector);
		} catch (e) {
			return null;
		}
	}

	function releasePanel(panel) {
		window.clearTimeout(panel.adminTabsResizeTimer);
		panel.style.height = '';
		panel.classList.remove('admin-tabs-panel--resizing');
	}

	function shouldResizePanel(panel) {
		return !panel.classList.contains('admin-modules-board__body--content')
			&& !panel.classList.contains('admin-user-view-board__body--content');
	}

	function lockPanel(panel) {
		window.clearTimeout(panel.adminTabsResizeTimer);
		panel.style.height = panel.getBoundingClientRect().height + 'px';
		panel.classList.add('admin-tabs-panel--resizing');
	}

	$(document).on('show.bs.tab', '[data-bs-toggle="tab"]', function () {
		var target = getTabTarget(this);
		var panel = target ? target.closest('.admin-tabs-panel') : null;

		if (!panel) {
			return;
		}

		if (!shouldResizePanel(panel)) {
			releasePanel(panel);
			return;
		}

		lockPanel(panel);
	});

	$(document).on('shown.bs.tab', '[data-bs-toggle="tab"]', function () {
		var target = getTabTarget(this);
		var panel = target ? target.closest('.admin-tabs-panel') : null;

		if (!panel) {
			return;
		}

		if (!shouldResizePanel(panel)) {
			releasePanel(panel);
			return;
		}

		var nextHeight = panel.scrollHeight;

		window.requestAnimationFrame(function () {
			panel.style.height = nextHeight + 'px';
		});

		panel.adminTabsResizeTimer = window.setTimeout(function () {
			releasePanel(panel);
		}, 260);

		$(panel).one('transitionend.adminTabsResize', function (event) {
			if (event.originalEvent && event.originalEvent.propertyName === 'height') {
				releasePanel(panel);
			}
		});
	});
}

function adminInitTableTextSize(context) {
	var $scope = context ? $(context) : $(document);

	$scope.find('[data-admin-table-text-size]').each(function () {
		var $wrap = $(this);

		if ($wrap.data('adminTableTextSizeInit')) {
			return;
		}

		$wrap.data('adminTableTextSizeInit', true);

		var storageKey = $wrap.attr('data-admin-table-text-size-key') || 'admin-table-text-size';
		var minLevel = parseInt($wrap.attr('data-admin-table-text-size-min') || '0', 10);
		var maxLevel = parseInt($wrap.attr('data-admin-table-text-size-max') || '4', 10);
		var defaultLevel = parseInt($wrap.attr('data-admin-table-text-size-default') || '1', 10);
		var storedLevel = parseInt(window.localStorage.getItem(storageKey), 10);
		var $decrease = $wrap.find('[data-admin-table-text-size-down]');
		var $increase = $wrap.find('[data-admin-table-text-size-up]');

		function clampLevel(level) {
			return Math.max(minLevel, Math.min(maxLevel, level));
		}

		function applyLevel(level) {
			level = clampLevel(level);
			$wrap.attr('data-table-text-size', String(level));
			window.localStorage.setItem(storageKey, String(level));
			$decrease.prop('disabled', level <= minLevel);
			$increase.prop('disabled', level >= maxLevel);
		}

		applyLevel(Number.isFinite(storedLevel) ? storedLevel : defaultLevel);

		$decrease.on('click', function () {
			applyLevel(parseInt($wrap.attr('data-table-text-size') || String(defaultLevel), 10) - 1);
		});

		$increase.on('click', function () {
			applyLevel(parseInt($wrap.attr('data-table-text-size') || String(defaultLevel), 10) + 1);
		});
	});
}

$(function () {
	adminInitFileDropzones();
	adminInitImagePreview();
	adminInitIconSelects();
	adminInitAvatarsBoard();
	adminInitToolbarSearch();
	adminInitTabsPanelResize();
	adminInitTableTextSize();
});