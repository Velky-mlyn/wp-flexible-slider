(function () {
	'use strict';

	const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

	document.querySelectorAll('.mfs-slider').forEach(function (slider) {
		const slides = Array.from(slider.querySelectorAll('.mfs-slide'));
		const dots = Array.from(slider.querySelectorAll('.mfs-dots button'));
		const previous = slider.querySelector('.mfs-previous');
		const next = slider.querySelector('.mfs-next');
		const shouldAutoplay = slider.dataset.autoplay === '1' && slides.length > 1;
		const loop = slider.dataset.loop === '1';
		const pauseOnHover = slider.dataset.pauseOnHover === '1';
		const videoAutoplay = slider.dataset.videoAutoplay === '1';
		const interval = Math.max(1500, Number.parseInt(slider.dataset.interval, 10) || 5000);
		let current = 0;
		let timer = null;

		if (!slides.length) return;

		function playActiveVideo() {
			slides.forEach(function (slide, index) {
				const video = slide.querySelector('video');
				if (!video) return;
				if (index === current && videoAutoplay && !reducedMotion.matches) {
					video.play().catch(function () {});
				} else {
					video.pause();
				}
			});
		}

		function show(index) {
			if (loop) index = (index + slides.length) % slides.length;
			else index = Math.max(0, Math.min(slides.length - 1, index));
			current = index;
			slides.forEach(function (slide, slideIndex) {
				const active = slideIndex === current;
				slide.classList.toggle('is-active', active);
				slide.setAttribute('aria-hidden', active ? 'false' : 'true');
				slide.inert = !active;
			});
			dots.forEach(function (dot, dotIndex) {
				if (dotIndex === current) dot.setAttribute('aria-current', 'true');
				else dot.removeAttribute('aria-current');
			});
			if (!loop) {
				if (previous) previous.disabled = current === 0;
				if (next) next.disabled = current === slides.length - 1;
			}
			playActiveVideo();
		}

		function stop() {
			if (timer) window.clearInterval(timer);
			timer = null;
		}

		function start() {
			stop();
			if (shouldAutoplay && !reducedMotion.matches && !document.hidden) {
				timer = window.setInterval(function () { show(current + 1); }, interval);
			}
		}

		if (previous) previous.addEventListener('click', function () { show(current - 1); start(); });
		if (next) next.addEventListener('click', function () { show(current + 1); start(); });
		dots.forEach(function (dot) {
			dot.addEventListener('click', function () { show(Number.parseInt(dot.dataset.slide, 10)); start(); });
		});
		slider.addEventListener('keydown', function (event) {
			if (event.key === 'ArrowLeft') { event.preventDefault(); show(current - 1); start(); }
			if (event.key === 'ArrowRight') { event.preventDefault(); show(current + 1); start(); }
		});
		if (pauseOnHover) {
			slider.addEventListener('mouseenter', stop);
			slider.addEventListener('mouseleave', start);
			slider.addEventListener('focusin', stop);
			slider.addEventListener('focusout', start);
		}
		document.addEventListener('visibilitychange', start);
		reducedMotion.addEventListener('change', function () { playActiveVideo(); start(); });
		show(0);
		start();
	});
}());
