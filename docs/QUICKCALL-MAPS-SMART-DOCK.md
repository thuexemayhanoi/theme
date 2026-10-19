# QUICK CALL MAPS + SMART DOCK — 2026-10-19

## Scope and verified business target

Owner-supplied directions share URL: `https://share.google/KHz2FVQKhtFilL01j`.
Public Google Maps place verified independently as **Thuê xe máy ở Hà Nội - Nguyễn Tú**, 112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hanoi. Automatic navigation through the `share.google` shortener occasionally triggers Google's bot-verification page; the exact user-requested link is preserved in the contact button, not rewritten into a made-up permalink. Canonical business Google Maps CID remains `https://maps.google.com/maps?cid=7448790013669858019` for existing schema, NAP and other parts of the site.

## Single authoritative configuration

- `inc/business.php`: `quick_maps_url` separately controls the Quick Call directions button; `maps_url` is untouched.
- `inc/settings.php`: under **Giao diện → Nguyễn Tú** the Quick Call directions link can be changed without modifying the business's main Maps URL; five individual contact buttons still have central ON/OFF settings.
- `inc/assist.php`: maps ON by default (Call, Zalo, WhatsApp and Chat remain ON). No extra widget, plugin, external JavaScript or schema script.

## Avoidance behavior (no animation-heavy library)

- The native contact bubble respects `env(safe-area-inset-bottom)`; visible bottom overlays with actual fixed/sticky geometry are detected by **three bounded** `elementsFromPoint` samples at the launcher’s normal on-screen position.
- The bubble moves **above** the obstruction with 12px of clearance, and returns to its original bottom offset when the obstruction disappears. No drifting or cumulative offsets on scroll. Footer content remains accessible through the existing native-layout bottom padding.
- Throttled with `requestAnimationFrame`, resize/scroll/visualViewport events, a mutation observer for newly opened/closed bars, and a low-frequency fallback check for CSS-only display changes. No jQuery or third-party layout service.
- If a full-screen modal leaves no safe available space, the floating contact bubble yields to the modal until dismissed; it does not overlay the visitor's consent or dialogue.
- Existing QuickCall/MotoAI plugin precedence remains intact; the chatbot dialog still hides the QuickCall bubble while open. The menu is scrollable within the reduced viewport height.

## Release QA

- Existing unit and browser checks for click targets, Gọi/Zalo/WhatsApp, Chat, SEO, WooCommerce and footer remain.
- Additional tests assert the exact requested Maps link, canonical Maps CID unchanged, fixed dock avoidance at 360/390/768/1024/1440px, position restoration after dock removal and modal priority.
- **Do not mark PASS before the matching SHA's CI completes successfully.**
- Production verify the href in public HTML and use the browser to check two mobile widths + desktop. If the hosting's Deployer for Git does not pick up the merge, do not repeatedly push; follow the approved single-deploy and cache-purge flow.
- Rollback by reverting only the QuickCall maps/dock merge commit; existing QuickCall and chatbot remain functional.
- The already open chatbot source-precision PR #9 is separate and is **not changed** here; merge it only after independently validated QA and conflict-safe integration with any legacy URL changes.
