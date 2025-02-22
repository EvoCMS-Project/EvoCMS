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
		$('a[href="' + hash + '"][data-toggle="tab"]').click();
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

		$('body').append('<div class="modal-backdrop in"></div><div id="spinner" title="loading"><i class="fa fa-5x fa-sync fa-spin"></i><br><strong>Uploading...</strong></div></di>');

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

		img.tooltip({ placement: 'bottom' });
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
				'<img class="float-right" style="max-height: 20px" src="' + items[i][2] + '">' : '';

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
	Prism.highlightAll();
	draganddrop();
	$('[title]:not([title=""])').tooltip({ placement: 'bottom' });
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