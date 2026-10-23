# Homepage Matrix curation — 23 October 2026

Scope authorized by the owner: curate only the existing homepage body, using Pillar–Hub–Silo–Topic Cluster; keep the latest posts automatic and avoid widespread content changes.

## Grounding

- Verified production: https://thuexemaynguyentu.com/, active independent `theme`, static Page 562. Authenticated WPVibe read on 23 October 2026.
- Current Matrix: `NGUYEN-TU-SEO-MASTER-MATRIX-2026-10-23.xlsx`, version 3, last modified 03:14:13 UTC. These selections reflect the existing keyword mapping, not new pages or new taxonomy.
- All 12 selected IDs were verified as published Posts in authenticated WordPress and the public REST API immediately before implementation.
- Base: main `9768d6c` (includes the latest WooCommerce QA and homepage images). No edits from the older local checkout were included.

## Stable selection in existing slots

| Role | Existing slot | Published IDs, in order |
|---|---|---|
| Pillar | Existing homepage intro/H1, products and prices | Page 562, unchanged |
| Hub and service silo | Existing 4 featured cards | 476 price; 1060 monthly; 895 50cc; 829 procedures |
| Local silos | First existing topic row, now “Thuê xe máy theo khu vực” | 847 Long Biên; 841 Hoàn Kiếm; 862 Tây Hồ; 836 Phố Cổ; 857 Ba Đình |
| Supporting content and delivery hub | Second existing topic row, now “Kinh nghiệm chọn xe và giao nhận” | 1062 scooter/manual comparison; 823 rental experience; 887 delivery conditions/cost |
| Broader discovery | Existing real category hub navigation | Unchanged categories and links |
| Automatic fresh content | Existing latest-post section | Latest 4 published Posts, date descending, excluding all curated cards |

Selections are a presentation policy, not a change to a Post's category, content or SEO role. There are no new URLs, landing pages, taxonomy changes or competing keyword targets. Published status is checked by WordPress queries. Unpublished selected articles disappear without replacement by random recent content. Queries resolve permalinks from WordPress and preserve editorial order. Empty filtered selections are skipped to avoid WordPress's empty `post__in` behaviour.

## Preservation and rollback

Only `template-parts/home/editorial.php` changes runtime behaviour. No CSS, JavaScript, Quick Call/Assistant, header/footer, settings, enqueue, SEO/schema, H1, metadata, media, prices, products or stored Page/Post content changes. Existing 17-image integration is preserved from current main. Existing section count/layout stays the same; the local row grows from three to five cards to cover the five existing area silos.

Rollback: revert the curation commit; no database restore is needed. New publishing continues normally before and after rollback.

## Validation

- Local Node suite: 135 PASS, 1 SKIP, 0 failures (136 total).
- ESLint on modified browser tests: PASS. `git diff --check`: PASS.
- Integration tests exercise new publishing without displacing curated cards, automatic latest updates, duplicate exclusion and hiding a selected draft. Browser tests assert all three groups and their order using actual sandbox permalinks.
- Sandbox keeps exactly 160 synthetic Posts: twelve existing fixtures use the verified Matrix IDs and slugs; the remaining IDs stay 10013–10160. Text and categories remain explicitly synthetic, not production copies.
- PHP lint/PHPCS/PHPUnit and sandbox browser testing are performed by the PR's GitHub CI; this local environment has no PHP runtime. See PR checks for their independently recorded results.
- Native iPhone/iPad Safari and ranking impact: NOT_TESTED. Chromium responsive QA does not imply native Safari coverage.

Release status at authoring: feature branch prepared, LIVE_UNCHANGED. Merge/deployment and public verification are recorded separately in the task's completion result.

QA correction: the first browser run exposed a synthetic-slug mismatch at Post 895 (the existing 50cc shortcut now resolves this fixture). Reused all twelve verified production slugs in the disposable fixtures; production templates and WordPress content require no further change. Three homepage assertions failed in that run; 221 other browser tests passed. Full CI must pass after this fixture-only correction.
