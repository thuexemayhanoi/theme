# Approved legal-content corrections — 22 October 2026

The owner explicitly approved the duplicate category H1 and the legal/safety corrections, and authorised push after QA. This checkpoint records what was actually written separately from pending work.

## Published in WordPress and browser-verified

- Post 823, /kinh-nghiem-thue-xe-may-ha-noi/: replace universal 2–3 metre following-distance advice with conditions-based safe spacing; replace unconditional vehicle movement after an accident with scene preservation, emergency reporting, remaining at the scene and the legally defined emergency exception. Remove the incorrect “Cảnh sát giao thông: 113” label.
- Post 931, /so-sanh-gia-thue-xe-may-dien-va-xe-dap-dien-tai-ha-noi/: remove blanket A1 claim. Explain that classification, motor power and valid licence matter. Clarify that electric-bicycle classification depends on assistance/cut-off criteria, not marketing names.
- Source: https://datafiles.chinhphu.vn/cpp/files/vbpq/2026/3/55-vbhn-vpqh.pdf (Articles 34, 56, 57, 59, 80–81). Read the official consolidated law before editing.
- Public browser inspection confirmed the first three replacements, one H1, self canonical, index/follow and no desktop overflow. The subsequent bicycle-cell correction is covered by the extended live check.
- Surgical old/new snippets are in approved-legal-content-2026-10-22.json. To roll back, re-read the current post and reverse the exact recorded replacement through WordPress; do not overwrite later edits.

## QA in this branch

Extend the existing read-only live check from 62 to 68 viewport cases by including posts 823 and 931 among the ten representative routes. Assert the corrected legal text and official-source link, alongside existing HTTP/H1/canonical/indexability/overflow/JS and Quick Call-presence checks. CI evidence, not this document, determines PASS/FAIL.

No theme runtime, title, H1, canonical, redirects, indexability or protected Quick Call/Assistant implementation changes are included. This commit does not deploy WordPress post content; those writes were made through WP Agent. Real iPhone Safari and full editorial QA >=85 remain NOT_TESTED/NOT_GRADED.

## Approved but not yet written

- Product category 73, /danh-muc-san-pham/loai-xe/xe-dien/: exact stored shortcode [title text="Thuê Xe Máy Điện Hà Nội" tag_name="h1"] should use h2. Retain archive H1 “Xe Điện”, title, canonical, copy, slug and taxonomy. WP Agent refused the write and created a preview that requires a new user yes after viewing; no category write happened. Do not bypass that gate using another route.
- Product 669, /san-pham/xe-ga-50cc/: qualify “không cần bằng lái” by actual moped classification, designed speed <=50 km/h, combustion displacement <=50 cm³, minimum age 16, health, traffic knowledge and driving skills. REST read was truncated; dedicated WP Agent post editor does not support WooCommerce products; WPVibe full-content read was blocked by its rolling free-plan quota. Do not replace an incomplete product body or change its price. Obtain complete original content, prepare exact corrections and follow the native write approval flow.

## Wider matrix

44 URLs have received technical inspection; 24 posts have partial safe changes (these two are already included). The entire A–Z matrix is not complete. Full content QA, outstanding business checks and real Safari verification remain open. No paid research was invoked; provider credit totals are not exposed.
