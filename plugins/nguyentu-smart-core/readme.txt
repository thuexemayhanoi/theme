=== Nguyễn Tú Smart Core ===
Contributors: thuexemayhanoi
Tags: local-business, structured-data, schema, seo
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later

Verified business data and schema coexistence for thuexemaynguyentu.com.

== Description ==

* Shares the verified business profile and reference rental prices with the Nguyễn Tú theme.
* The theme's existing Yoast LocalBusiness enrichment remains in control when that theme is active.
* When the theme changes, preserves a conservative Yoast Organization enrichment unless Yoast Local SEO handles it.
* When no supported SEO plugin is present, produces one Organization/LocalBusiness and WebSite graph on eligible public pages; no title, canonical, robots, sitemap, review, rating, Offer, or Product metadata.
* Tools > Nguyễn Tú Core provides a read-only health/status view.
* Does not create any new WordPress options or data tables. Does not edit existing content.

== Installation ==

Code is staged in the repository for QA. No plugin package is provided in this revision.
Packaging and production activation require separate owner approval and QA.

== FAQ ==

= Does simply pushing this directory to GitHub activate the plugin? =

No. WordPress plugins live under wp-content/plugins and must be installed and activated separately. This repository's theme build excludes plugins/.

= Will the plugin overwrite Yoast SEO schema or canonical data? =

No. Yoast owns its graph and document metadata. The native theme continues to manage the current Yoast Organization enrichment. The plugin only uses a fallback when the theme or SEO plugin is absent.

= Are all pages guaranteed rich results? =

No. Structured data describes real facts; eligibility, search appearance and ranking are determined by search engines.

= What about Quick Call and chatbot? =

Those remain native theme modules and are outside this plugin.
