=== Nguyễn Tú Legacy Compat (featured_item) ===
Contributors: nguyentu
Requires at least: 7.1
Tested up to: 7.1.3
Requires PHP: 8.2
Stable tag: 1.1.0
License: GPLv2 or later

Keeps the featured_item post type and the featured_item_category taxonomy (and their /bo-suu-tap/ URLs) alive when the Flatsome theme is replaced.

== Description ==

1. Activate this plugin while the old theme is still active: it stores the exact registration arguments in one option.
2. After the old theme is gone, the plugin registers the same post type and taxonomy from that snapshot (only if nobody else has).
3. With the old theme active again, the plugin stays dormant.

It never creates, edits or deletes posts, terms, menus, media or SEO data. WP-CLI: `wp nguyentu-compat status|capture|export-urls|compare|verify`.

== Changelog ==

= 1.1.0 =
* Restore the original /bo-suu-tap/ term and comma-separated single URLs verified on 2026-10-19.
* 301 resolved aliases to the corresponding public object; keep invalid paths as 404.
* Recover partial registrations; create an additive rewrite/snapshot backup before supervised flush.
* No automatic rewrite flush or edits to content, taxonomy relationships or Yoast metadata.

= 1.0.0 =
* First release.
