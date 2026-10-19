=== Nguyễn Tú Legacy Compat (featured_item) ===
Contributors: nguyentu
Requires at least: 7.1
Tested up to: 7.1.3
Requires PHP: 8.2
Stable tag: 1.1.0
License: GPLv2 or later

Keeps the featured_item post type and the featured_item_category taxonomy (and their /bo-suu-tap/ URLs) alive when the Flatsome theme is replaced.

== Description ==

1. The independent plugin restores the verified legacy URL policy using the existing post/term data; reactivating the old theme is not required.
2. Existing registration snapshots are retained; only missing registrations are recovered. When both are provided elsewhere, the plugin stays dormant.
3. A one-time additive backup precedes any supervised rewrite flush. No automatic rewrite flush occurs on normal requests.

It never creates, edits or deletes posts, terms, menus, media or SEO data. WP-CLI: `wp nguyentu-compat status|capture|export-urls|compare|verify`.

== Changelog ==

= 1.1.0 =
* Restore the original /bo-suu-tap/ term and comma-separated single URLs verified on 2026-10-19.
* 301 resolved aliases to the corresponding public object; keep invalid paths as 404.
* Recover partial registrations; create an additive rewrite/snapshot backup before supervised flush.
* No automatic rewrite flush or edits to content, taxonomy relationships or Yoast metadata.

= 1.0.0 =
* First release.
