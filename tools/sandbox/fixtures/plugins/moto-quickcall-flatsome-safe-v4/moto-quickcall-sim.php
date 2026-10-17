<?php
/**
 * Plugin Name: Moto QuickCall Pro+ Social (Flatsome Safe v4) - SIMULATION
 * Description: SIMULATION ONLY - NOT the real plugin. Stand-in that prints a fixed bottom contact bar (Call + Zalo) so a theme can be tested for layout overlap / duplicate sticky bars. Lives in the sandbox only (tools/sandbox/fixtures/plugins).
 * Version:     0.0.0-sim
 * Author:      Sandbox simulation
 * License:     GPL-2.0-or-later
 *
 * Documented behaviour (from the brief, not from the real plugin's code):
 *   - printed in wp_footer on the front end
 *   - position:fixed; bottom:0; full width; z-index:9999
 *   - Call  -> tel:0942467674
 *   - Zalo  -> https://zalo.me/0942467674
 * Real plugin slug: moto-quickcall-flatsome-safe-v4 (directory name is kept identical on purpose).
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
<!-- SIMULATION: moto-quickcall-flatsome-safe-v4 (tools/sandbox/fixtures) -->
<style id="moto-quickcall-sim-css">
#moto-quickcall-sim{position:fixed;left:0;right:0;bottom:0;z-index:9999;display:flex;gap:8px;align-items:center;justify-content:center;box-sizing:border-box;height:56px;padding:8px 12px;background:#fff;border-top:1px solid #d0d5dd;box-shadow:0 -2px 8px rgba(0,0,0,.12);font:600 14px/1 system-ui,sans-serif}
#moto-quickcall-sim a{flex:1 1 0;max-width:260px;display:flex;align-items:center;justify-content:center;box-sizing:border-box;height:40px;border-radius:20px;color:#fff;text-decoration:none;white-space:nowrap;overflow:hidden}
#moto-quickcall-sim .mqc-call{background:#16a34a}
#moto-quickcall-sim .mqc-zalo{background:#0068ff}
</style>
<div id="moto-quickcall-sim" class="moto-quickcall-bar" data-sim="moto-quickcall" role="complementary" aria-label="Liên hệ nhanh (mô phỏng)">
	<a class="mqc-btn mqc-call" href="tel:0942467674">Gọi 0942 467 674</a>
	<a class="mqc-btn mqc-zalo" href="https://zalo.me/0942467674" target="_blank" rel="noopener">Zalo</a>
</div>
		<?php
	},
	99
);
