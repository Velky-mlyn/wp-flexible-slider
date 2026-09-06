(function () {
	'use strict';
	const container = document.getElementById('mfs-slides');
	if (!container || !wp.components.FocalPointPicker) return;
	const { createElement: h, useState, useEffect } = wp.element;
	const roots = new Map();

	function imageUrl(slide) {
		const type = slide.querySelector('.mfs-slide-type').value;
		return type === 'video' ? '' : (slide.dataset.imageUrl || (type === 'post' ? slide.dataset.linkedImageUrl : '') || '');
	}

	function Picker({ slide }) {
		const x = slide.querySelector('.mfs-focal-x');
		const y = slide.querySelector('.mfs-focal-y');
		const [url, setUrl] = useState(imageUrl(slide));
		const [point, setPoint] = useState({ x: Number(x.value) / 100, y: Number(y.value) / 100 });
		function update(next) {
			const value = { x: Math.round(next.x * 100) / 100, y: Math.round(next.y * 100) / 100 };
			x.value = String(Math.round(value.x * 100));
			y.value = String(Math.round(value.y * 100));
			setPoint(value);
		}
		useEffect(function () {
			function refresh() {
				const next = imageUrl(slide);
				if (next !== url) {
					setUrl(next);
					update({ x: 0.5, y: 0.35 });
				}
			}
			slide.addEventListener('mfs-image-change', refresh);
			slide.addEventListener('change', refresh);
			return function () {
				slide.removeEventListener('mfs-image-change', refresh);
				slide.removeEventListener('change', refresh);
			};
		}, [url]);
		if (!url) return h('p', null, mfsAdmin.focalEmpty);
		const style = { backgroundImage: 'url("' + url.replace(/"/g, '%22') + '")', backgroundPosition: (point.x * 100) + '% ' + (point.y * 100) + '%' };
		return h(wp.element.Fragment, null,
			h('p', null, mfsAdmin.focalHelp),
			h(wp.components.FocalPointPicker, { url, value: point, onChange: update, onDrag: update }),
			h('div', { className: 'mfs-focal-previews' },
				h('div', null, h('p', null, mfsAdmin.focalDesktop), h('div', { className: 'mfs-focal-preview', style, 'aria-hidden': true })),
				h('div', null, h('p', null, mfsAdmin.focalMobile), h('div', { className: 'mfs-focal-preview mfs-focal-preview-mobile', style, 'aria-hidden': true }))
			),
			h(wp.components.Button, { variant: 'secondary', onClick: function () { update({ x: 0.5, y: 0.5 }); } }, mfsAdmin.focalReset)
		);
	}

	function mount(slide) {
		if (roots.has(slide)) return;
		const node = slide.querySelector('.mfs-focal-root');
		if (!node) return;
		if (wp.element.createRoot) {
			const root = wp.element.createRoot(node);
			roots.set(slide, function () { root.unmount(); });
			root.render(h(Picker, { slide }));
		} else {
			roots.set(slide, function () { wp.element.unmountComponentAtNode(node); });
			wp.element.render(h(Picker, { slide }), node);
		}
	}
	container.querySelectorAll('.mfs-slide-editor').forEach(mount);
	new MutationObserver(function () {
		container.querySelectorAll('.mfs-slide-editor').forEach(mount);
		roots.forEach(function (unmount, slide) {
			if (!container.contains(slide)) { unmount(); roots.delete(slide); }
		});
	}).observe(container, { childList: true });
}());
