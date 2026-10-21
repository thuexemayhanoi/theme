# AGENTS.md — Operating contract for every coding AI (ChatGPT Work, Claude, Codex, etc.)

> **P0 OWNER LOCK — EFFECTIVE 2026-10-21: QUICK CALL + NGUYỄN TÚ ASSISTANT ARE FROZEN.**
> This is a **change freeze**, not a disable/feature shutdown. The owner confirms these features are working acceptably. **Do not modify, optimize, redesign, replace, deactivate, reinstall, migrate, or refactor either feature without new explicit owner approval for the specific proposed change.** A general "complete the theme" or "fix everything" prompt does not override this lock.
>
> **English summary:** READ-ONLY inspection permitted. NO modifications to Quick Call or Assistant code, styles, assets, options, endpoints, UI, behavior, caching, routing, business-facing messages or deployments affecting them without explicit, feature-specific owner consent. Ask first, with exact files, problem evidence, regression risk and rollback. Preserve the existing UI and functionality on mobile/tablet/desktop.

## A. Protected scope — explicit owner approval required

Current native features on `https://thuexemaynguyentu.com/`:
- Quick Call fixed launcher, expansion menu, contact buttons (Call, Zalo, WhatsApp, AI Chat, Directions), verified URLs, labels, colors, positioning, safe-area and bottom-dock avoidance.
- Nguyễn Tú Assistant (local, rule/verified-content retrieval; not an external generative AI), chatbot panel, mobile keyboard and VisualViewport behavior, focus/scroll/close, topic/source cache, suggestions, rate limits and WordPress REST response.
- Their integration with footer/header, enqueue/dequeue logic, Light/Dark appearance, site-wide contact settings, opening hours display, and any feature-dependent settings or CSS/JS.

**Known sensitive paths (non-exhaustive):**
- `assets/css/assist.css`
- `assets/js/assist.js`
- `inc/assist.php`
- The Quick Call / Assistant portions of `inc/business.php`, `inc/enqueue.php`, `inc/settings.php`, `functions.php` and related templates/hooks.
- The `nt-native-quickcall`, `nt-native-assistant`, `nt-qc*`, `nt-assist*`, `data-nt-chat-*` elements/selectors and the `/wp-json/nguyentu/v1/assist` endpoint.
- Any shared layout, typography, z-index, color tokens, CSS, script loading, cache or performance changes **if they alter these features**.

Do NOT disable old/new Quick Call plugins, introduce another widget/chatbot, change contact links/numbers, edit Assistant search corpus, alter its prompts/logic, move docking positions, change chat fullscreen mode, add an AI API or vendor/model, silently change chat storage, or add new chat features.

**A request to audit, optimize performance, redesign website, align global colors or complete Work does not authorize these protected changes.** Even "minor" changes must be approved by the owner first.

## B. Allowed without approval

- Read-only inspection of code, status and live behavior; compare checksums/diffs, run isolated tests, and report any issue affecting Quick Call/Assistant.
- Work on unrelated theme templates/components/SEO/performance features, preserving the same functioning protected features.
- Add regression assertions and document observations without modifying protected runtime behavior.
- If a shared edit could affect the protected UI/behavior, test it explicitly; if preserving is uncertain, stop that edit and request authorization before merge/deploy.

## C. Required approval protocol to unfreeze a specific part

Before editing, **ask the owner in conversation**:
1. Exact observed failure or desired feature and evidence (URL, device, reproduction).
2. Exact files and affected behavior; alternatives and expected benefit.
3. Risks (iPhone Safari keyboard/dock, conversion CTA, SEO, speed, regressions).
4. Smallest patch, test cases, rollback commit/backup.
5. Specific request for consent.

Proceed **only after explicit consent for that described change**. Consent is scoped; do not interpret it as permission to refactor unrelated Quick Call/Assistant behavior. A serious regression or security issue may be reported urgently, but still requires authorization before a production change unless the owner separately delegates emergency changes.

## D. Release gates — protection against indirect breakage

For any other PR that touches shared styling/JS, header/footer, layout, assets, theme settings or perf:
- Compare protected files with the baseline; expected: **zero modifications** unless scoped owner approval is documented.
- Regression-check presence and uniqueness: one native Quick Call launcher and one Assistant panel; no duplicate old plugin widgets.
- Keep Call/Zalo/WhatsApp/Directions links and user-approved appearance, z-index, safe-area, keyboard/focus/scroll behavior as-is.
- Validate on 360/390/768/1440 in test environment when available; **real iPhone Safari remains NOT_TESTED unless explicitly tested**.
- If a test finds a protected feature problem, document it as `OWNER_APPROVAL_REQUIRED`; do not "fix along the way".
- Never merge a change that knowingly breaks protected features. For unrelated changes, rollback the offending shared change or ask the owner.

## E. General maintenance and shipping

Read `README.md`, `CLAUDE.md`, `docs/ARCHITECTURE.md`, `docs/SEO-HULK.md`, then inspect the **current** live site; historical snapshots are not present-state guarantees. **AUDIT FIRST, REUSE BEFORE REBUILD.** Prefer small, independent fixes to stable shared components. Preserve WordPress content, menus, WooCommerce, featured-item URLs, Yoast SEO, canonical, robots, sitemap, H1 and schema.

Work only in `thuexemayhanoi/theme` unless the owner grants a separate scope. Implement on feature branches, test before merge; `main` may trigger production WordPress deployment. Record precise `TESTED / NOT_TESTED / MERGED / LIVE_VERIFIED` separately. No API costs, new plugins, bulk SEO content edits or irreversible WordPress changes without required approvals.

**Owner lock precedence:** This P0 freeze overrides broader instructions elsewhere in the repository that request building, optimizing or deactivating Quick Call or the chatbot. It remains active until the owner explicitly lifts or narrows it.
