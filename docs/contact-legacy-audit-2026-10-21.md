# Contact page #372 — 2026-10-21

Scope: /lien-he/ only. The live WordPress HTML still renders legacy Flatsome shortcodes through the independent Nguyen Tu theme. Observed an inline black section, translucent white card, 60px mobile padding, inherited dark-mode text, and two map embeds (the second is an old Moto Open map). The user's iPhone screenshot shows unreadable text and a large blank-looking card.

Prepared branch: fix/contact-legacy-layout-20261021. Added scoped assets/css/legacy-contact.css (dark/light contrast, mobile padding, responsive map, hides the second map visually without deleting stored content). This file is NOT YET ENQUEUED. inc/contact-legacy.php currently contains only a page predicate; no hook is registered.

BLOCKED: GitHub connector safety gate rejected edits containing the WordPress enqueue function and a full replacement of inc/enqueue.php. Do not merge/deploy this incomplete branch. Next step: safely register the stylesheet for page ID 372 after nt-legacy at priority 21; then run CI, browser QA at 390/768/1440 in both color modes, and verify live. Preserve SEO metadata, stored content, media, menu and URL. Real iOS Safari remains untested.
