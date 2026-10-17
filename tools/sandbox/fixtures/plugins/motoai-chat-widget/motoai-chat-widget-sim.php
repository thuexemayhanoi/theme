<?php
/**
 * Plugin Name: MotoAI Chat Widget - SIMULATION
 * Description: SIMULATION ONLY - NOT the real plugin. Stand-in that prints a fixed 56px chat bubble (bottom-right) with a click-to-open panel, so a theme can be tested for overlap with other fixed UI (e.g. the QuickCall bar). No network calls, no AI. Lives in the sandbox only (tools/sandbox/fixtures/plugins).
 * Version:     0.0.0-sim
 * Author:      Sandbox simulation
 * License:     GPL-2.0-or-later
 *
 * Documented behaviour (from the brief, not from the real plugin's code):
 *   - printed in wp_footer on the front end
 *   - position:fixed; 56x56 bubble at the bottom-right corner
 *   - click toggles a panel
 * Real plugin slug: motoai-chat-widget (directory name is kept identical on purpose).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_footer',
	static function () {
		if ( is_admin() || is_customize_preview() ) {
			return;
		}
		?>
<!-- SIMULATION: motoai-chat-widget (tools/sandbox/fixtures) -->
<style id="motoai-chat-sim-css">
#motoai-chat-bubble-sim{position:fixed;right:16px;bottom:16px;z-index:99999;width:56px;height:56px;border:0;border-radius:50%;padding:0;background:#0b3d91;color:#fff;font:700 22px/56px system-ui,sans-serif;text-align:center;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.3)}
#motoai-chat-panel-sim{position:fixed;right:16px;bottom:84px;z-index:99999;box-sizing:border-box;width:320px;max-width:calc(100vw - 32px);height:420px;max-height:70vh;display:flex;flex-direction:column;background:#fff;border:1px solid #d0d5dd;border-radius:12px;box-shadow:0 8px 28px rgba(0,0,0,.25);font:14px/1.4 system-ui,sans-serif;overflow:hidden}
#motoai-chat-panel-sim[hidden]{display:none}
#motoai-chat-panel-sim header{padding:12px 14px;background:#0b3d91;color:#fff;font-weight:700}
#motoai-chat-panel-sim .motoai-sim-body{flex:1;padding:12px 14px;overflow:auto;color:#344054}
</style>
<button id="motoai-chat-bubble-sim" type="button" data-sim="motoai-chat" aria-label="Mở chat MotoAI (mô phỏng)" aria-expanded="false" aria-controls="motoai-chat-panel-sim">AI</button>
<div id="motoai-chat-panel-sim" role="dialog" aria-label="MotoAI chat (mô phỏng)" hidden>
	<header>MotoAI - mô phỏng</header>
	<div class="motoai-sim-body">Xin chào! Đây là khung chat mô phỏng dùng để kiểm tra bố cục. Không có kết nối AI thật.</div>
</div>
<script id="motoai-chat-sim-js">
(function(){var b=document.getElementById('motoai-chat-bubble-sim'),p=document.getElementById('motoai-chat-panel-sim');if(!b||!p){return;}
b.addEventListener('click',function(){var open=p.hasAttribute('hidden');if(open){p.removeAttribute('hidden');}else{p.setAttribute('hidden','');}b.setAttribute('aria-expanded',open?'true':'false');});})();
</script>
		<?php
	},
	99
);
