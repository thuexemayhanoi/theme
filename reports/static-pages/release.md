# Static-page body release — 2026-10-21

Published scope: Giới thiệu (310), Về chúng tôi (424), Sứ mệnh và tầm nhìn (432), Liên hệ (372), FAQ (484), Chính sách bảo mật (326), Điều khoản sử dụng (449), Bộ sưu tập (494).

Homepage 562 and shop 3040 are excluded. Their current SEO headings and photos are preserved, not re-audited or rewritten by this change.

## Verified result

- Eight page bodies use shared typography, spacing, surfaces and responsive gutters.
- Legacy contact/collection presentation is replaced by native templates. Stored source remains recoverable; a later editor-authored native replacement takes priority.
- Decorative page images are removed from HTML output; functional form images/SVG icons are retained where applicable.
- Contact uses the saved Contact Form 7 shortcode once and the central business configuration. Three rental-period cards resolve real taxonomy URLs.
- 30 live page/viewport visits, 48 light/dark target-page states, 0 errors. All 10 titles/descriptions/canonical/robots/H1 values and menu/footer destinations match the captured baseline. Target-page content has one H1.
- WordPress cache was refreshed by republishing the eight existing public pages. Modified dates changed; body source and Yoast field values were not rewritten.
- Robots content and sitemap URL sets are preserved.

## Evidence

- [Merged PR 37](https://github.com/thuexemayhanoi/theme/pull/37)
- [Full release CI](https://github.com/thuexemayhanoi/theme/actions/runs/39542852690)
- [Live static-page QA](https://github.com/thuexemayhanoi/theme/actions/runs/39543853063)
- [Homepage live regression](https://github.com/thuexemayhanoi/theme/actions/runs/39542852730)
- `verified-live.json`: complete live records, 390/768/1440px, tested commit f37498c65a063e9f0b0c525a54c9ff87b1688689.
- `live-contact-desktop.png`: actual public Contact page, light mode.

The browser check uses Chromium emulation. Native Safari hardware and actual email delivery were not tested. No contact form was submitted.
