# Homepage Article schema correction

## Observed public state, 2026-10-21

The editorial homepage at https://thuexemaynguyentu.com/ has one visible H1, a self canonical and index/follow robots. Its Yoast graph also contains Article with the legacy Page 562 title and wordCount 6202, which does not describe the current service homepage template. WebPage, WebSite, BreadcrumbList and verified LocalBusiness remain applicable.

## Narrow change

Use Yoast's documented wpseo_schema_needs_article filter to return false only when is_front_page() and is_page(562). No SEO meta/DB writes, title/H1/canonical/robots changes, CSS, scripts, Quick Call or Assistant files. Other posts, pages and any replacement homepage retain Yoast's decision.

Reference: https://developer.yoast.com/features/schema/api/

## Verification

- Public before snapshot: observed through cloud browser DOM, 21/10.
- Integration cases: static homepage 562, regular post 823, other page 310, replacement homepage 310, and page 562 when no longer front page.
- PHP runtime is absent locally. PHP lint/PHPCS and sandbox integration must be run by the repository CI. Do not interpret this report as test success.
- LIVE_UNCHANGED while this branch is unmerged. After deployment, verify Article absent on homepage and present on a representative article; confirm title/H1/canonical/robots, WebPage and LocalBusiness unchanged.
- Mobile portrait/landscape and real iPhone Safari: NOT_TESTED in this session. PageSpeed mobile measurement is separate lab evidence.

## Rollback

Revert the inc/schema.php change (or the eventual squash commit) through GitHub, then verify the previous schema and unchanged page metadata. Do not alter canonical, indexability or WordPress content for rollback.

## Separate findings

The existing Person graph includes an unverified honorificSuffix MD. This patch does not change the global author profile. Review its underlying Yoast/user configuration separately with owner approval.
