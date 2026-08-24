(function () {
	'use strict';

	const container = document.getElementById('mfs-slides');
	const addButton = document.getElementById('mfs-add-slide');
	const template = document.getElementById('mfs-slide-template');
	const contentModal = document.getElementById('mfs-content-modal');
	const contentSearch = document.getElementById('mfs-content-search');
	const contentType = document.getElementById('mfs-content-type');
	const contentResults = document.getElementById('mfs-content-results');
	const contentStatus = document.getElementById('mfs-content-search-status');
	const contentLoadMore = document.getElementById('mfs-content-load-more');

	if (!container || !addButton || !template) {
		return;
	}

	let dragged = null;
	let activePicker = null;
	let contentPage = 1;
	let contentRequest = 0;
	let contentSearchTimer = null;
	let contentItems = new Map();
	let contentReturnFocus = null;

	function reindex() {
		container.querySelectorAll('.mfs-slide-editor').forEach(function (slide, index) {
			slide.dataset.slideIndex = index;
			slide.querySelectorAll('[name]').forEach(function (field) {
				field.name = field.name.replace(/mfs_slides\[[^\]]+\]/, 'mfs_slides[' + index + ']');
			});
		});
	}

	function toggleFields(slide) {
		const type = slide.querySelector('.mfs-slide-type').value;
		slide.querySelectorAll('[data-types]').forEach(function (field) {
			field.hidden = !field.dataset.types.split(',').includes(type);
		});
	}

	function updateSummary(slide) {
		const title = slide.querySelector('.mfs-title-input').value.trim();
		const postId = slide.querySelector('.mfs-post-id');
		const linked = slide.querySelector('.mfs-content-title');
		const linkedTitle = postId && Number(postId.value) && linked ? linked.textContent.trim() : '';
		slide.querySelector('.mfs-slide-summary').textContent = title || linkedTitle || mfsAdmin.untitledSlide;
	}

	function updateContentSelection(picker, item) {
		const selection = picker.querySelector('.mfs-content-selection');
		const oldId = selection.querySelector('.mfs-content-id');
		const id = document.createElement(item.edit_url ? 'a' : 'span');
		id.className = 'mfs-content-id';
		id.textContent = '#' + item.id;
		if (item.edit_url) {
			id.href = item.edit_url;
			id.target = '_blank';
			id.rel = 'noopener noreferrer';
			id.title = mfsAdmin.editContent;
		}
		oldId.replaceWith(id);
		picker.querySelector('.mfs-post-id').value = item.id;
		selection.querySelector('.mfs-content-title').textContent = item.title;
		selection.querySelector('.mfs-content-type').textContent = item.type_label;
		selection.querySelector('.mfs-content-status').textContent = item.status_label;
		selection.querySelector('.mfs-content-date').textContent = item.date || '';
		const warning = selection.querySelector('.mfs-content-warning');
		warning.textContent = item.warning || '';
		warning.hidden = !item.warning;
		selection.hidden = false;
		picker.querySelector('.mfs-choose-content').textContent = mfsAdmin.changeContent;
		picker.querySelector('.mfs-clear-content').disabled = false;
		updateSummary(picker.closest('.mfs-slide-editor'));
	}

	function clearContentSelection(picker) {
		picker.querySelector('.mfs-post-id').value = '0';
		picker.querySelector('.mfs-content-selection').hidden = true;
		picker.querySelector('.mfs-choose-content').textContent = mfsAdmin.chooseContent;
		picker.querySelector('.mfs-clear-content').disabled = true;
		updateSummary(picker.closest('.mfs-slide-editor'));
	}

	function closeContentPicker() {
		if (!contentModal || contentModal.hidden) return;
		contentRequest += 1;
		contentModal.hidden = true;
		document.body.classList.remove('mfs-content-modal-open');
		activePicker = null;
		if (contentReturnFocus) contentReturnFocus.focus();
		contentReturnFocus = null;
	}

	function openContentPicker(picker, trigger) {
		if (!contentModal) return;
		activePicker = picker;
		contentReturnFocus = trigger;
		contentSearch.value = '';
		contentType.value = '';
		contentResults.replaceChildren();
		contentItems = new Map();
		contentStatus.textContent = mfsAdmin.searchPrompt;
		contentLoadMore.hidden = true;
		contentModal.hidden = false;
		document.body.classList.add('mfs-content-modal-open');
		setTimeout(function () { contentSearch.focus(); }, 0);
	}

	function createContentResult(item) {
		const result = document.createElement('article');
		result.className = 'mfs-content-result';
		const details = document.createElement('div');
		details.className = 'mfs-content-result-details';
		const title = document.createElement('strong');
		title.textContent = item.title;
		const meta = document.createElement('div');
		meta.className = 'mfs-content-meta';
		[item.type_label, item.status_label, item.date].filter(Boolean).forEach(function (value) {
			const span = document.createElement('span');
			span.textContent = value;
			meta.appendChild(span);
		});
		const id = document.createElement(item.edit_url ? 'a' : 'span');
		id.className = 'mfs-content-id';
		id.textContent = '#' + item.id;
		if (item.edit_url) {
			id.href = item.edit_url;
			id.target = '_blank';
			id.rel = 'noopener noreferrer';
			id.title = mfsAdmin.editContent;
		}
		meta.appendChild(id);
		details.append(title, meta);
		const select = document.createElement('button');
		select.type = 'button';
		select.className = 'button button-primary mfs-select-content';
		select.dataset.contentId = item.id;
		select.textContent = mfsAdmin.selectContent;
		result.append(details, select);
		return result;
	}

	function searchContent(reset) {
		const term = contentSearch.value.trim();
		if (!/^\d+$/.test(term) && term.length < 2) {
			if (reset) contentResults.replaceChildren();
			contentStatus.textContent = mfsAdmin.searchPrompt;
			contentLoadMore.hidden = true;
			return;
		}
		if (reset) {
			contentPage = 1;
			contentResults.replaceChildren();
			contentItems = new Map();
		}
		const request = ++contentRequest;
		contentStatus.textContent = mfsAdmin.searching;
		contentLoadMore.hidden = true;
		const url = new URL(mfsAdmin.ajaxUrl);
		url.searchParams.set('action', 'mfs_search_content');
		url.searchParams.set('nonce', mfsAdmin.contentNonce);
		url.searchParams.set('q', term);
		url.searchParams.set('post_type', contentType.value);
		url.searchParams.set('page_number', contentPage);
		fetch(url.toString(), { credentials: 'same-origin' })
			.then(function (response) { return response.json(); })
			.then(function (response) {
				if (request !== contentRequest) return;
				if (!response.success) throw new Error('Search failed');
				response.data.items.forEach(function (item) {
					contentItems.set(String(item.id), item);
					contentResults.appendChild(createContentResult(item));
				});
				contentStatus.textContent = contentResults.children.length ? '' : mfsAdmin.noResults;
				contentLoadMore.hidden = !response.data.has_more;
			})
			.catch(function () {
				if (request === contentRequest) contentStatus.textContent = mfsAdmin.searchError;
			});
	}

	function chooseMedia(field, kind) {
		const type = kind === 'video' ? 'video' : 'image';
		const titleKey = kind === 'video' ? 'videoTitle' : (kind === 'poster' ? 'posterTitle' : 'imageTitle');
		const frame = wp.media({
			title: mfsAdmin[titleKey],
			button: { text: mfsAdmin.useMedia },
			library: { type: type },
			multiple: false
		});

		frame.on('select', function () {
			const media = frame.state().get('selection').first().toJSON();
			field.querySelector('.mfs-media-id').value = media.id;
			const preview = field.querySelector('.mfs-media-preview');
			preview.replaceChildren();
			if (kind === 'video') {
				const code = document.createElement('code');
				code.textContent = media.filename || media.url.split('/').pop();
				preview.appendChild(code);
			} else {
				const image = document.createElement('img');
				image.src = media.sizes && media.sizes.thumbnail ? media.sizes.thumbnail.url : media.url;
				image.alt = '';
				preview.appendChild(image);
			}
			field.querySelector('.mfs-clear-media').disabled = false;
		});

		frame.open();
	}

	function initialise(slide) {
		toggleFields(slide);
		updateSummary(slide);
	}

	container.querySelectorAll('.mfs-slide-editor').forEach(initialise);

	addButton.addEventListener('click', function () {
		const index = container.querySelectorAll('.mfs-slide-editor').length;
		container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index));
		initialise(container.lastElementChild);
		reindex();
	});

	container.addEventListener('click', function (event) {
		const remove = event.target.closest('.mfs-remove-slide');
		if (remove && window.confirm(mfsAdmin.confirmRemove)) {
			remove.closest('.mfs-slide-editor').remove();
			reindex();
			return;
		}

		const choose = event.target.closest('.mfs-choose-media');
		if (choose) {
			chooseMedia(choose.closest('.mfs-media-field'), choose.dataset.mediaKind);
			return;
		}

		const chooseContent = event.target.closest('.mfs-choose-content');
		if (chooseContent) {
			openContentPicker(chooseContent.closest('.mfs-content-picker'), chooseContent);
			return;
		}

		const clearContent = event.target.closest('.mfs-clear-content');
		if (clearContent) {
			clearContentSelection(clearContent.closest('.mfs-content-picker'));
			return;
		}

		const clear = event.target.closest('.mfs-clear-media');
		if (clear) {
			const field = clear.closest('.mfs-media-field');
			field.querySelector('.mfs-media-id').value = '';
			field.querySelector('.mfs-media-preview').replaceChildren();
			clear.disabled = true;
		}
	});

	container.addEventListener('change', function (event) {
		const slide = event.target.closest('.mfs-slide-editor');
		if (!slide) return;
		if (event.target.matches('.mfs-slide-type')) toggleFields(slide);
		if (event.target.matches('.mfs-title-input')) updateSummary(slide);
	});

	container.addEventListener('input', function (event) {
		if (event.target.matches('.mfs-title-input')) updateSummary(event.target.closest('.mfs-slide-editor'));
	});

	container.addEventListener('dragstart', function (event) {
		const slide = event.target.closest('.mfs-slide-editor');
		if (!slide) return;
		dragged = slide;
		slide.classList.add('is-dragging');
		event.dataTransfer.effectAllowed = 'move';
	});

	container.addEventListener('dragover', function (event) {
		if (!dragged) return;
		event.preventDefault();
		const target = event.target.closest('.mfs-slide-editor');
		if (!target || target === dragged) return;
		const box = target.getBoundingClientRect();
		container.insertBefore(dragged, event.clientY < box.top + box.height / 2 ? target : target.nextSibling);
	});

	container.addEventListener('dragend', function () {
		if (dragged) dragged.classList.remove('is-dragging');
		dragged = null;
		reindex();
	});

	if (contentModal) {
		contentModal.addEventListener('click', function (event) {
			if (event.target.closest('.mfs-close-content-modal') || event.target.matches('.mfs-content-modal-backdrop')) {
				closeContentPicker();
				return;
			}
			const select = event.target.closest('.mfs-select-content');
			if (select && activePicker) {
				const item = contentItems.get(select.dataset.contentId);
				if (item) updateContentSelection(activePicker, item);
				closeContentPicker();
			}
		});
		contentSearch.addEventListener('input', function () {
			window.clearTimeout(contentSearchTimer);
			contentSearchTimer = window.setTimeout(function () { searchContent(true); }, 300);
		});
		contentType.addEventListener('change', function () { searchContent(true); });
		contentLoadMore.addEventListener('click', function () {
			contentPage += 1;
			searchContent(false);
		});
		document.addEventListener('keydown', function (event) {
			if (contentModal.hidden) return;
			if (event.key === 'Escape') {
				closeContentPicker();
				return;
			}
			if (event.key !== 'Tab') return;
			const focusable = Array.from(contentModal.querySelectorAll('button:not([disabled]):not([hidden]), input:not([disabled]), select:not([disabled]), a[href]'))
				.filter(function (element) { return element.offsetParent !== null; });
			if (!focusable.length) return;
			const first = focusable[0];
			const last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		});
	}
}());
