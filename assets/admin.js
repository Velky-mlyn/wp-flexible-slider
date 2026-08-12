(function () {
	'use strict';

	const container = document.getElementById('mfs-slides');
	const addButton = document.getElementById('mfs-add-slide');
	const template = document.getElementById('mfs-slide-template');

	if (!container || !addButton || !template) {
		return;
	}

	let dragged = null;

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
		const linked = slide.querySelector('.mfs-post-field select');
		const linkedTitle = linked && linked.selectedIndex > 0 ? linked.options[linked.selectedIndex].text : '';
		slide.querySelector('.mfs-slide-summary').textContent = title || linkedTitle || 'Untitled slide';
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
		if (event.target.matches('.mfs-title-input, .mfs-post-field select')) updateSummary(slide);
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
}());
