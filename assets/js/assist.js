/*
 * Native Nguyễn Tú launcher and verified-content assistant.
 * Progressive enhancement only; no cookies, localStorage, analytics, AI API or crawler.
 */
(function () {
	'use strict';
	var doc = document;
	var quickcall = doc.getElementById('nt-native-quickcall');
	var menu = quickcall && quickcall.querySelector('#nt-qc-menu');
	var launcher = quickcall && quickcall.querySelector('[data-nt-qc-launch]');
	var assist = doc.querySelector('[data-nt-assistant]');
	var panel = assist && assist.querySelector('.nt-assist__panel');
	var closeButton = assist && assist.querySelector('[data-nt-chat-close]');
	var form = assist && assist.querySelector('[data-nt-chat-form]');
	var input = assist && assist.querySelector('#nt-assist-question');
	var log = assist && assist.querySelector('[data-nt-chat-log]');
	var submit = form && form.querySelector('button[type="submit"]');
	var previousFocus = null;

	function toggleMenu(open) {
		if (!menu || !launcher) { return; }
		menu.hidden = !open;
		launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
		launcher.setAttribute('aria-label', open ? 'Đóng các cách liên hệ' : 'Mở các cách liên hệ');
	}
	function toggleChat(open) {
		if (!panel) { return; }
		panel.hidden = !open;
		doc.body.classList.toggle('nt-chat-is-open', open);
		if (open) {
			previousFocus = doc.activeElement;
			toggleMenu(false);
			if (input) { input.focus(); }
		} else if (previousFocus && previousFocus.focus) {
			previousFocus.focus();
			previousFocus = null;
		}
	}
	function openChat() {
		if (panel) { toggleChat(true); return; }
		// Legacy MotoAI is still active during the safe migration period.
		if (window.MotoAI_v38 && typeof window.MotoAI_v38.open === 'function') {
			window.MotoAI_v38.open();
		} else {
			var legacy = doc.getElementById('mta-bubble');
			if (legacy) { legacy.click(); }
		}
		toggleMenu(false);
	}

	if (launcher) {
		launcher.addEventListener('click', function () { toggleMenu(menu.hidden); });
	}
	doc.querySelectorAll('[data-nt-chat-open]').forEach(function (button) {
		button.addEventListener('click', openChat);
	});
	if (closeButton) {
		closeButton.addEventListener('click', function () { toggleChat(false); });
	}
	doc.addEventListener('click', function (event) {
		if (menu && !menu.hidden && quickcall && !quickcall.contains(event.target)) {
			toggleMenu(false);
		}
	});
	doc.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			if (panel && !panel.hidden) { toggleChat(false); }
			else if (menu && !menu.hidden) { toggleMenu(false); launcher.focus(); }
		}
		if (event.key === 'Tab' && panel && !panel.hidden) {
			var items = Array.from(panel.querySelectorAll('a[href],button:not([disabled]),input:not([disabled])'));
			var first = items[0];
			var last = items[items.length - 1];
			if (event.shiftKey && doc.activeElement === first) { event.preventDefault(); last.focus(); }
			else if (!event.shiftKey && doc.activeElement === last) { event.preventDefault(); first.focus(); }
		}
	});

	function addMessage(text, style, sources) {
		if (!log) { return; }
		var box = doc.createElement('div');
		box.className = 'nt-assist__message nt-assist__message--' + style;
		var body = doc.createElement('p');
		body.textContent = text;
		box.appendChild(body);
		if (Array.isArray(sources) && sources.length) {
			var list = doc.createElement('ul');
			list.className = 'nt-assist__sources';
			sources.slice(0, 4).forEach(function (source) {
				if (!source || !source.url || !source.title) { return; }
				try {
					var url = new URL(source.url, location.origin);
					if (url.origin !== location.origin) { return; }
					var item = doc.createElement('li');
					var link = doc.createElement('a');
					link.href = url.href;
					link.textContent = source.title;
					link.target = '_blank';
					link.rel = 'noopener noreferrer';
					item.appendChild(link);
					list.appendChild(item);
				} catch { /* untrusted result is ignored */ }
			});
			if (list.children.length) { box.appendChild(list); }
		}
		log.appendChild(box);
		log.scrollTop = log.scrollHeight;
	}
	function send(question) {
		if (!assist || !form || !input || !submit || !log) { return; }
		var text = question.trim().slice(0, 180);
		if (text.length < 2 || submit.disabled) { return; }
		addMessage(text, 'user');
		input.value = '';
		submit.disabled = true;
		var controller = new AbortController();
		var timeout = setTimeout(function () { controller.abort(); }, 12000);
		fetch(assist.getAttribute('data-endpoint'), {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'omit',
			cache: 'no-store',
			body: JSON.stringify({ question: text }),
			signal: controller.signal
		}).then(function (response) {
			if (!response.ok) { throw new Error('request unavailable'); }
			return response.json();
		}).then(function (data) {
			addMessage(String(data.answer || 'Không có dữ liệu xác minh.'), 'bot', data.sources);
		}).catch(function () {
			addMessage('Chưa tra cứu được lúc này. Bạn vui lòng gọi 0942 467 674 hoặc nhắn Zalo để được hỗ trợ.', 'bot');
		}).finally(function () {
			clearTimeout(timeout);
			submit.disabled = false;
			if (panel && !panel.hidden) { input.focus(); }
		});
	}
	if (form) {
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			if (input) { send(input.value); }
		});
	}
	doc.querySelectorAll('[data-nt-chat-suggest]').forEach(function (button) {
		button.addEventListener('click', function () {
			openChat();
			send(button.getAttribute('data-nt-chat-suggest') || '');
		});
	});
}());
