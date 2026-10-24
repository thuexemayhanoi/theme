# Homepage service intent — candidate release

The owner explicitly authorized homepage SEO improvement for “thuê xe máy Hà Nội” with safe publishing after QA. Existing homepage H1/title and the day/week/month/deposit table are useful and remain. Research found that rental decisions, current pickup information, visitor paperwork and FAQ were less immediate than the editorial feed. The existing homepage price calculator and all article discovery blocks remain.

## Changes and boundaries

- Hero: current business address/hours, links to prices/deposit, rental steps, FAQ and the existing Zalo contact. Existing Call/Directions renderer and fixed contact/assistant are unchanged.
- Single price table, calculator and product section move ahead of editorial cards; no duplicated price source or invented inventory.
- New homepage-only decision section: existing supporting URLs for price/monthly/Vision/50cc/procedures/areas, real pickup point, delivery-by-agreement, English essentials and six customer FAQ. No new doorway URL, synthetic translation/hreflang, reviews or FAQ rich-result promises.
- Hero image alone adds the standard LiteSpeed no-lazy attribute; existing eager loading, native dimensions/srcset and priority remain.
- Homepage description/social copy is the only planned Yoast data update; preserve title, H1, canonical, robots and all other URLs. Mark homepage cornerstone using Yoast's registered update ability.

## QA and rollback

Run existing CI PHP lint/PHPCS/unit/integration/sandbox suites and the additional 360/390/768/1440 light/dark behavior tests before merge. Verify actual deployment/served image attributes, one price table/calculator, FAQ/English content, internal link targets, canonical/robots/H1 and protected widget uniqueness after publish. No static lint or Chromium emulation establishes actual iPhone Safari behavior or field Core Web Vitals.

Restore a theme regression by reverting this release via the existing main deployment path; restore homepage metadata separately from the saved before snapshot. Neither theme activation, stale WPVibe draft publishing nor permalink flushing is part of this release.
