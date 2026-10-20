/*
 * Native Nguyễn Tú launcher and verified-content assistant.
 * Progressive enhancement: bounded local topic/source cache, no chat-history persistence,
 * cookies, analytics, paid AI API, web crawler or framework.
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
	var chatViewportFrame = 0;
	var requestGeneration = 0;
	var activeRequest = null;
	var status = assist && assist.querySelector('[data-nt-chat-status]');
	var clearButton = assist && assist.querySelector('[data-nt-chat-clear]');
	var memoryKey = 'nt-assist-topic-v2';
	var sourcesKey = 'nt-assist-sources-v2';
	var topicTtl = 24 * 60 * 60 * 1000;
	var sourcesTtl = 6 * 60 * 60 * 1000;
	// Only model and duration are persisted. Never store a question or chat message.
	function safeRead(key) {
		try { return JSON.parse(window.localStorage.getItem(key) || 'null'); }
		catch { return null; }
	}
	function safeWrite(key, value, maxLength) {
		try {
			var text = JSON.stringify(value);
			if (text.length <= maxLength) { window.localStorage.setItem(key, text); }
		} catch { /* private browsing may disable storage */ }
	}
	function safeDelete(key) {
		try { window.localStorage.removeItem(key); }
		catch { /* storage unavailable */ }
	}
	function getMemory() {
		var saved = safeRead(memoryKey);
		if (!saved || saved.v !== 2 || !Number.isFinite(saved.until) || saved.until < Date.now()) {
			safeDelete(memoryKey);
			return { model: '', period: '' };
		}
		return {
			model: typeof saved.model === 'string' ? saved.model.slice(0, 60) : '',
			period: ['day', 'week', 'month'].includes(saved.period) ? saved.period : ''
		};
	}
	var currentContext = getMemory();
	function rememberContext(context) {
		if (!context || typeof context !== 'object') { return; }
		currentContext = {
			model: String(context.model || '').slice(0, 60),
			period: ['day', 'week', 'month'].includes(context.period) ? context.period : ''
		};
		if (currentContext.model || currentContext.period) {
			safeWrite(memoryKey, { v: 2, until: Date.now() + topicTtl,
				model: currentContext.model, period: currentContext.period }, 512);
		} else { safeDelete(memoryKey); }
	}
	function cacheTopic(query) {
		// Never persist a visitor question as plaintext; skip likely identifiers.
		if (/@|https?:|[0-9]{4}/i.test(query)) { return ''; }
		var text = query.trim().toLocaleLowerCase('vi').replace(/\s+/g, ' ').slice(0, 90);
		var hash = 2166136261;
		for (var i = 0; i < text.length; i++) {
			hash ^= text.charCodeAt(i);
			hash = Math.imul(hash, 16777619);
		}
		return 'topic-' + (hash >>> 0).toString(16);
	}
	function saveSources(query, intent, sources) {
		var key = cacheTopic(query);
		if (!key || intent !== 'search' || !Array.isArray(sources) || !sources.length) { return; }
		var existing = safeRead(sourcesKey);
		var items = existing && existing.v === 2 && existing.until > Date.now()
			&& Array.isArray(existing.items) ? existing.items : [];
		var safe = sources.slice(0, 3).filter(function (item) {
			if (!item || !item.url || !item.title) { return false; }
			try { return new URL(item.url).origin === location.origin; }
			catch { return false; }
		}).map(function (item) {
			return {
				title: String(item.title).slice(0, 90),
				url: String(item.url).slice(0, 240),
				excerpt: String(item.excerpt || '').slice(0, 135)
			};
		});
		if (!safe.length) { return; }
		items = items.filter(function (entry) { return entry.query !== key; });
		items.unshift({ query: key, sources: safe });
		safeWrite(sourcesKey, { v: 2, until: Date.now() + sourcesTtl, items: items.slice(0, 5) }, 8000);
	}
	function cachedSources(query) {
		var data = safeRead(sourcesKey);
		if (!data || data.v !== 2 || data.until < Date.now() || !Array.isArray(data.items)) {
			safeDelete(sourcesKey);
			return [];
		}
		var entry = data.items.find(function (item) { return item.query === cacheTopic(query); });
		return entry && Array.isArray(entry.sources) ? entry.sources : [];
	}
	function busy(state) {
		if (submit) { submit.disabled = state; }
		if (status) { status.hidden = !state; }
		if (form) { form.setAttribute('aria-busy', state ? 'true' : 'false'); }
	}


	/*
	 * Move the launcher only when an actual bottom overlay intersects its natural
	 * position. Use bounded hit-testing rather than scanning every WP post node.
	 * Recompute from its unshifted geometry, so movement never accumulates on scroll.
	 */
	var dockFrame = 0;
	var dockOffset = 0;
	function fixedObstacle(el, rect, viewportHeight) {
		for (var node = el; node && node !== doc.body; node = node.parentElement) {
			if (quickcall.contains(node) || (assist && assist.contains(node))) { return null; }
			var style = window.getComputedStyle(node);
			if (style.visibility === 'hidden' || parseFloat(style.opacity) < 0.05) { continue; }
			if (style.position !== 'fixed' && style.position !== 'sticky') { continue; }
			var box = node.getBoundingClientRect();
			if (box.width < 14 || box.height < 14 || box.top >= viewportHeight ||
				box.bottom <= rect.top || box.top >= rect.bottom ||
				box.right <= rect.left || box.left >= rect.right) { continue; }
			return box;
		}
		return null;
	}
	function updateDockAvoidance() {
		dockFrame = 0;
		if (!quickcall || !launcher || doc.hidden || !window.innerHeight) { return; }
		var rect = launcher.getBoundingClientRect();
		if (rect.width < 1 || rect.height < 1) { return; }
		// Rect is shifted up by the last known offset. Recover the actual baseline.
		var original = {
			left: rect.left,
			right: rect.right,
			top: rect.top + dockOffset,
			bottom: rect.bottom + dockOffset
		};
		// Three bounded samples cover the top, center and bottom of the bubble.
		var points = [
			[original.left + 7, original.top + 7],
			[(original.left + original.right) / 2, (original.top + original.bottom) / 2],
			[original.right - 7, original.bottom - 7]
		];
		var obstacleTop = window.innerHeight;
		for (var i = 0; i < points.length; i++) {
			var x = points[i][0];
			var y = points[i][1];
			if (x < 0 || x >= window.innerWidth || y < 0 || y >= window.innerHeight) { continue; }
			var layers = doc.elementsFromPoint(x, y);
			for (var k = 0; k < layers.length; k++) {
				var obstacle = fixedObstacle(layers[k], original, window.innerHeight);
				if (obstacle) { obstacleTop = Math.min(obstacleTop, obstacle.top); }
			}
		}
		var needed = Math.max(0, Math.ceil(original.bottom - obstacleTop + 12));
		var room = Math.max(0, original.top - 72);
		// A full-screen modal should take precedence over any floating launcher.
		var blockedByModal = needed > room && needed > 0;
		quickcall.classList.toggle('nt-qc--suspended', blockedByModal);
		var next = blockedByModal ? 0 : Math.min(needed, room);
		if (next !== dockOffset) {
			dockOffset = next;
			quickcall.style.setProperty('--nt-qc-dock-offset', next + 'px');
		}
	}
	function scheduleDockAvoidance() {
		if (quickcall && !dockFrame) { dockFrame = window.requestAnimationFrame(updateDockAvoidance); }
	}
	if (quickcall) {
		window.addEventListener('resize', scheduleDockAvoidance, { passive: true });
		window.addEventListener('scroll', scheduleDockAvoidance, { passive: true });
		window.addEventListener('pageshow', scheduleDockAvoidance);
		if (window.visualViewport) {
			window.visualViewport.addEventListener('resize', scheduleDockAvoidance, { passive: true });
		}
		if (window.MutationObserver && doc.body) {
			var dockObserver = new MutationObserver(function (changes) {
				if (changes.some(function (change) {
					return !quickcall.contains(change.target) && !(assist && assist.contains(change.target));
				})) { scheduleDockAvoidance(); }
			});
			dockObserver.observe(doc.body, {
				childList: true, subtree: true, attributes: true,
				attributeFilter: ['class', 'style', 'hidden', 'aria-hidden']
			});
		}
		// CSS-only dock appearance cannot always be observed by DOM mutations.
		window.setInterval(function () {
			if (!doc.hidden) { scheduleDockAvoidance(); }
		}, 1800);
		scheduleDockAvoidance();
	}

	function toggleMenu(open) {
		if (!menu || !launcher) { return; }
		menu.hidden = !open;
		launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
		launcher.setAttribute('aria-label', open ? 'Đóng các cách liên hệ' : 'Mở các cách liên hệ');
	}
	/*
	 * Mobile Safari moves the visible viewport when the keyboard opens. Place the
	 * chat in visual viewport coordinates instead of relying on 100dvh/fixed bottom.
	 * The constrained layout also works in narrow landscape and at large zoom.
	 */
	function syncChatViewport() {
		chatViewportFrame = 0;
		if (!panel || panel.hidden) { return; }
		var viewport = window.visualViewport;
		var width = Math.max(1, viewport ? viewport.width : window.innerWidth);
		var height = Math.max(1, viewport ? viewport.height : window.innerHeight);
		var left = viewport ? viewport.offsetLeft : 0;
		var top = viewport ? viewport.offsetTop : 0;
		var narrow = width < 768;
		var keyboard = viewport && (window.innerHeight - viewport.height - viewport.offsetTop > 120);
		var constrained = narrow || keyboard || height < 520;
		panel.classList.toggle('nt-assist__panel--viewport', !!constrained);
		if (!constrained) { return; }
		var margin = width < 390 ? 8 : 12;
		var panelWidth = Math.max(1, Math.floor(Math.min(390, width - margin * 2)));
		var panelHeight = Math.max(1, Math.floor(Math.min(600, height - margin * 2)));
		var panelLeft = left + (narrow ? margin : width - panelWidth - margin);
		var panelTop = top + Math.max(0, height - panelHeight - margin);
		panel.style.setProperty('--nt-chat-left', Math.round(panelLeft) + 'px');
		panel.style.setProperty('--nt-chat-top', Math.round(panelTop) + 'px');
		panel.style.setProperty('--nt-chat-width', panelWidth + 'px');
		panel.style.setProperty('--nt-chat-height', panelHeight + 'px');
	}
	function scheduleChatViewport() {
		if (panel && !panel.hidden && !chatViewportFrame) {
			chatViewportFrame = window.requestAnimationFrame(syncChatViewport);
		}
	}
	if (panel) {
		window.addEventListener('resize', scheduleChatViewport, { passive: true });
		window.addEventListener('orientationchange', scheduleChatViewport, { passive: true });
		if (window.visualViewport) {
			window.visualViewport.addEventListener('resize', scheduleChatViewport, { passive: true });
			window.visualViewport.addEventListener('scroll', scheduleChatViewport, { passive: true });
		}
	}
	function toggleChat(open) {
		if (!panel) { return; }
		if (panel.hidden === !open) { return; }
		panel.hidden = !open;
		if (open) { syncChatViewport(); }
		else {
			panel.classList.remove('nt-assist__panel--viewport');
			if (chatViewportFrame) { window.cancelAnimationFrame(chatViewportFrame); chatViewportFrame = 0; }
		}
		doc.querySelectorAll('[data-nt-chat-open]').forEach(function (button) {
			button.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		doc.body.classList.toggle('nt-chat-is-open', open);
		if (open) {
			previousFocus = doc.activeElement;
			toggleMenu(false);
			// Avoid popping the iPhone keyboard before the visitor chooses to type.
			// The close button is a safe initial focus target on narrow screens.
			if ((window.innerWidth < 768 || (window.matchMedia && window.matchMedia('(pointer: coarse)').matches)) && closeButton) { closeButton.focus({ preventScroll: true }); }
			else if (input) { input.focus({ preventScroll: true }); }
		} else if (previousFocus && previousFocus.focus) {
			// The chat was opened from a button inside the now-hidden QuickCall menu.
			if (launcher && menu && menu.contains(previousFocus)) { launcher.focus(); }
			else { previousFocus.focus(); }
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
			sources.slice(0, 3).forEach(function (source) {
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
			if (list.children.length) {
				var heading = doc.createElement('strong');
				heading.className = 'nt-assist__sources-title';
				heading.textContent = 'Đọc thêm từ website:';
				box.appendChild(heading);
				box.appendChild(list);
			}
		}
		log.appendChild(box);
		// One greeting + 12 recent messages, without persisting conversation text.
		while (log.children.length > 13) { log.removeChild(log.children[1]); }
		log.scrollTop = log.scrollHeight;
	}
	function send(question) {
		if (!assist || !form || !input || !submit || !log) { return; }
		var text = question.trim().slice(0, 180);
		if (text.length < 2 || submit.disabled) { return; }
		addMessage(text, 'user');
		input.value = '';
		busy(true);
		var generation = ++requestGeneration;
		var controller = new AbortController();
		activeRequest = controller;
		var timeout = setTimeout(function () { controller.abort(); }, 12000);
		fetch(assist.getAttribute('data-endpoint'), {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'omit',
			cache: 'no-store',
			body: JSON.stringify({ question: text, context: currentContext }),
			signal: controller.signal
		}).then(function (response) {
			if (!response.ok) { throw new Error('request unavailable'); }
			return response.json();
		}).then(function (data) {
			if (generation !== requestGeneration) { return; }
			addMessage(String(data.answer || 'Không có dữ liệu xác minh.'), 'bot', data.sources);
			rememberContext(data.context);
			saveSources(text, data.intent, data.sources);
		}).catch(function () {
			if (generation !== requestGeneration) { return; }
			var saved = cachedSources(text);
			if (saved.length) {
				addMessage('Chưa kết nối được. Đây là các nguồn đã lưu tạm từ lần tra cứu trước, có thể đã cũ. Vui lòng xác nhận giá và điều kiện với cửa hàng.', 'bot', saved);
			} else {
				addMessage('Chưa tra cứu được lúc này. Bạn vui lòng gọi Nguyễn Tú hoặc nhắn Zalo để được hỗ trợ.', 'bot');
			}
		}).finally(function () {
			clearTimeout(timeout);
			if (generation !== requestGeneration) { return; }
			activeRequest = null;
			busy(false);
			// Do not reopen the virtual keyboard after an answer arrives.
		});
	}
	if (clearButton) {
		clearButton.addEventListener('click', function () {
			// Invalidate any pending response so it cannot repopulate cleared memory.
			requestGeneration++;
			if (activeRequest) { activeRequest.abort(); activeRequest = null; }
			busy(false);
			safeDelete(memoryKey);
			safeDelete(sourcesKey);
			currentContext = { model: '', period: '' };
			if (log) {
				while (log.children.length > 1) { log.removeChild(log.lastElementChild); }
				log.scrollTop = 0;
			}
			addMessage('Bộ nhớ chủ đề và nguồn đã lưu trên thiết bị đã được xóa.', 'bot');
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
