=== Nguyễn Tú Smart Core ===
Contributors: thuexemayhanoi
Tags: local-business, seo, internal-linking, calculator, structured-data
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.0.1
License: GPLv2 or later

Zero-config business data and WordPress companion tools for thuexemaynguyentu.com.

== Description ==

One plugin ZIP, no account, external API, API key, setup wizard or database migration.

* Verified Nguyễn Tú business data (NAP, hours, owner-confirmed rental references). Reads the theme data when available.
* Schema: honors the active theme's Yoast enrichment without creating a second Organization; if the theme changes, cautiously enriches Yoast when no Local SEO module takes ownership. Without a recognized SEO plugin, emits a conservative graph on eligible pages only.
* Tools > Nguyễn Tú Core: read-only health and internal-link sample of up to 20 recent articles. This is not a whole-site broken-link checker.
* Reference rental estimate: automatically appears after either the native Nguyễn Tú homepage price table or the verified original price table embedded in the historic homepage content. The stored homepage content is never edited. It also works through shortcode [nguyentu_rental_calculator] in other content. Shows day-rate estimates only and links to Zalo for confirmation. No bookings are placed.
* Zero database writes; no external JavaScript/CDN, tracking, cookies or heavy runtime index.
* Native Quick Call and Chatbot remain in the theme; this plugin never duplicates them.

== Installation ==

1. Plugins > Add New > Upload Plugin.
2. Select nguyentu-smart-core.zip and choose Install, then Activate.
3. All automatic functions start. Optional Tools > Nguyễn Tú Core is read-only. No settings to fill in.

The calculator appears automatically below the site's verified homepage rental price table, whether native or historical content. On other pages, place [nguyentu_rental_calculator] only if you want a calculator there.

== SEO safety ==

* Yoast SEO owns title, meta, canonicals, robots, XML sitemap and its structured-data graph.
* Does not alter content, post categories, products, taxonomies or URLs.
* Does not invent ratings, fake reviews, rental bookings, live inventory or Product offers.
* Actual rich-result appearance, rankings and indexing cannot be guaranteed.
* Domain protection prevents Nguyễn Tú data appearing on unrelated domains by default.

== Removal ==

Deactivation stops the plugin. Uninstall creates no content changes or database cleanup because this plugin does not own any persistent state.

== Notes ==

Sources and packaging scripts live in https://github.com/thuexemayhanoi/theme/ . GitHub Actions builds the ZIP as an artifact. The theme package deliberately excludes plugins.
