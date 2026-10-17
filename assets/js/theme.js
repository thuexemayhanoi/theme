/*
 * Nguyễn Tú Editorial Commerce — theme.js (deferred, no dependencies).
 * Progressive enhancement only: menus, search and content stay usable without it.
 */
(function () {
	'use strict';

	var doc = document;
	var body = doc.body;
	var $ = function (sel, root) { return (root || doc).querySelector(sel); };
	var $$ = function (sel, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); };
	var desktop = window.matchMedia('(min-width: 1024px)');

	/* ---------- Sticky header shadow ---------- */
	var header = $('#site-header');
	if (header && 'IntersectionObserver' in window) {
		var sentinel = doc.createElement('div');
		sentinel.setAttribute('aria-hidden', 'true');
		sentinel.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:1px;pointer-events:none';
		body.insertBefore(sentinel, body.firstChild);
		new IntersectionObserver(function (entries) {
			header.classList.toggle('is-stuck', !entries[0].isIntersecting);
		}).observe(sentinel);
	}

	/* ---------- Off-canvas menu (below 1024px) ---------- */
	var nav = $('#nt-nav');
	var toggle = $('[data-nt-menu-toggle]');
	var closeBtn = $('[data-nt-menu-close]');
	var scrim = $('[data-nt-scrim]');
	var returnFocus = null;

	function focusables(root) {
		return $$('a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])', root)
			.filter(function (el) { return el.getClientRects().length > 0; });
	}

	function setMenu(open) {
		if (!nav || !toggle) { return; }
		nav.classList.toggle('is-open', open);
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		toggle.querySelector('.screen-reader-text').textContent = open ? 'Đóng menu' : 'Mở menu';
		if (scrim) { scrim.hidden = !open; }
		body.classList.toggle('nt-lock', open);
		if (open) {
			returnFocus = doc.activeElement;
			(closeBtn || focusables(nav)[0] || nav).focus();
		} else if (returnFocus && returnFocus.focus) {
			returnFocus.focus();
			returnFocus = null;
		}
	}

	if (nav && toggle) {
		toggle.addEventListener('click', function () { setMenu(!nav.classList.contains('is-open')); });
		if (closeBtn) { closeBtn.addEventListener('click', function () { setMenu(false); }); }
		if (scrim) { scrim.addEventListener('click', function () { setMenu(false); }); }
		nav.addEventListener('keydown', function (e) {
			if (e.key !== 'Tab' || !nav.classList.contains('is-open') || desktop.matches) { return; }
			var items = focusables(nav);
			if (!items.length) { return; }
			var first = items[0];
			var last = items[items.length - 1];
			if (e.shiftKey && doc.activeElement === first) { e.preventDefault(); last.focus(); }
			else if (!e.shiftKey && doc.activeElement === last) { e.preventDefault(); first.focus(); }
		});
		var onBreakpoint = function () { if (desktop.matches && nav.classList.contains('is-open')) { setMenu(false); } };
		if (desktop.addEventListener) { desktop.addEventListener('change', onBreakpoint); } else if (desktop.addListener) { desktop.addListener(onBreakpoint); }
	}

	/* ---------- Sub-menu disclosure buttons ---------- */
	function setSub(btn, open) {
		var li = btn.closest('li');
		if (!li) { return; }
		li.classList.toggle('is-open', open);
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
	}
	function closeAllSubs(except) {
		$$('[data-nt-sub-toggle][aria-expanded="true"]').forEach(function (btn) {
			if (!except || !btn.closest('li').contains(except)) { setSub(btn, false); }
		});
	}
	$$('[data-nt-sub-toggle]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var li = btn.closest('li');
			var open = !(li && li.classList.contains('is-open'));
			if (desktop.matches && open) { closeAllSubs(btn); }
			setSub(btn, open);
		});
	});
	// Desktop panels open on mouse hover (CSS) or when the disclosure button is activated; keyboard
	// focus passing by never opens them, so Tab moves straight to the next top-level link.
	// aria-expanded therefore always mirrors the button state. A click or focus moving elsewhere closes it.
	function closeIfOutside(e) {
		if (!desktop.matches) { return; }
		var openBtn = $('[data-nt-sub-toggle][aria-expanded="true"]');
		var top = openBtn && openBtn.closest('.nt-menu__item--d0');
		if (top && !top.contains(e.target)) { closeAllSubs(); }
	}
	doc.addEventListener('click', closeIfOutside);
	doc.addEventListener('focusin', closeIfOutside);
	doc.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') { return; }
		if (nav && nav.classList.contains('is-open') && !desktop.matches) { setMenu(false); return; }
		var open = $('[data-nt-sub-toggle][aria-expanded="true"]');
		if (open) {
			var li = open.closest('.nt-menu__item--d0') || open.closest('li');
			var focusInside = li && li.contains(doc.activeElement);
			$$('[data-nt-sub-toggle]').forEach(function (b) { setSub(b, false); });
			if (focusInside) { open.focus(); }
			if (doc.activeElement && doc.activeElement.blur && desktop.matches && li && li.contains(doc.activeElement) && doc.activeElement !== open) { open.focus(); }
		}
		var panel = $('#nt-search-panel');
		if (panel && !panel.hidden) { setSearch(false); }
	});
	doc.addEventListener('click', function (e) {
		if (desktop.matches && !e.target.closest('.nt-nav')) { closeAllSubs(null); }
	});

	/* ---------- Search panel ---------- */
	var searchBtn = $('[data-nt-search-toggle]');
	var searchPanel = $('#nt-search-panel');
	function setSearch(open) {
		if (!searchBtn || !searchPanel) { return; }
		searchPanel.hidden = !open;
		searchBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
		searchBtn.querySelector('.screen-reader-text').textContent = open ? 'Đóng ô tìm kiếm' : 'Mở ô tìm kiếm';
		if (open) {
			var input = searchPanel.querySelector('input[type="search"]');
			if (input) { input.focus(); }
		} else {
			searchBtn.focus();
		}
	}
	if (searchBtn && searchPanel) {
		searchBtn.addEventListener('click', function () { setSearch(searchPanel.hidden); });
	}

	/* ---------- TOC scroll-spy ---------- */
	var toc = $('[data-nt-toc]');
	if (toc && 'IntersectionObserver' in window) {
		var links = $$('[data-nt-toc-link]', toc);
		var map = {};
		links.forEach(function (a) { map[a.getAttribute('href').slice(1)] = a; });
		var current = null;
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) { return; }
				var link = map[entry.target.id];
				if (!link) { return; }
				if (current) { current.classList.remove('is-active'); current.removeAttribute('aria-current'); }
				link.classList.add('is-active');
				link.setAttribute('aria-current', 'location');
				current = link;
			});
		}, { rootMargin: '-15% 0px -75% 0px' });
		Object.keys(map).forEach(function (id) {
			var h = doc.getElementById(id);
			if (h) { observer.observe(h); }
		});
	}

	/* ---------- Share: copy link ---------- */
	$$('[data-nt-share]').forEach(function (box) {
		var btn = box.querySelector('[data-nt-copy]');
		var status = box.querySelector('[data-nt-share-status]');
		if (!btn || !navigator.clipboard) { return; }
		btn.hidden = false;
		btn.addEventListener('click', function () {
			navigator.clipboard.writeText(box.getAttribute('data-url') || location.href).then(function () {
				if (status) { status.textContent = 'Đã sao chép liên kết'; }
			});
		});
	});

	/* ---------- Click-to-load map and video (no third-party request until asked) ---------- */
	$$('[data-nt-map]').forEach(function (box) {
		var loadBtn = box.querySelector('[data-nt-map-load]');
		if (!loadBtn) { return; }
		loadBtn.hidden = false;
		loadBtn.addEventListener('click', function () {
			var frame = doc.createElement('iframe');
			frame.src = box.getAttribute('data-src');
			frame.title = 'Bản đồ vị trí cửa hàng';
			frame.loading = 'lazy';
			frame.referrerPolicy = 'no-referrer-when-downgrade';
			frame.setAttribute('allowfullscreen', '');
			box.textContent = '';
			box.appendChild(frame);
			frame.focus();
		});
	});
	$$('[data-nt-video]').forEach(function (box) {
		var link = box.querySelector('a');
		if (!link) { return; }
		link.addEventListener('click', function (e) {
			var id = box.getAttribute('data-id');
			var kind = box.getAttribute('data-nt-video');
			var src = kind === 'youtube'
				? 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0'
				: (kind === 'vimeo' ? 'https://player.vimeo.com/video/' + encodeURIComponent(id) + '?autoplay=1' : '');
			if (!src) { return; }
			e.preventDefault();
			var frame = doc.createElement('iframe');
			frame.src = src;
			frame.title = 'Video';
			frame.allow = 'autoplay; encrypted-media; picture-in-picture';
			frame.setAttribute('allowfullscreen', '');
			box.textContent = '';
			box.appendChild(frame);
		});
	});
}());
