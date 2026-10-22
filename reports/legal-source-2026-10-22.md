# Legal source check — 50cc and electric-vehicle pages (22 October 2026)

Question: do the corrected 50cc product page (`/san-pham/xe-ga-50cc/`, PR #52) and the licence passages published earlier (PR #51) agree with the **official** text of the law they cite, and does the repository now prove it repeatably?

## What was read

The sandbox cannot reach `datafiles.chinhphu.vn`, so the official PDF was read on GitHub Actions with one public GET (`tools/qa/legal-source-check.mjs`, workflow *Legal source check (read-only)*). Run [39854185181](https://github.com/thuexemayhanoi/theme/actions/runs/39854185181) (commit `c537f7a`), 21:04 UTC:

| Fact | Value |
| --- | --- |
| URL | `https://datafiles.chinhphu.vn/cpp/files/vbpq/2026/3/55-vbhn-vpqh.pdf` (HTTP 200, `application/pdf`) |
| Size / SHA-256 | 2,379,606 bytes / `9a20c8e616d444e3abe63c765a225d0797b7079c9a18e5fe7da0fc4f46847dac` |
| PDF metadata | 74 pages; modified 23 March 2026 08:51 UTC |
| Text starts | "Luật Trật tự, an toàn giao thông đường bộ số 36/2024/QH15 ngày 27 tháng 6 năm 2024 của Quốc hội, có hiệu lực kể từ ngày 01 tháng 01 năm 2025, được sửa đổi, bổ sung bởi: Luật số 118/2025/QH15 ngày 10 tháng 12 năm 2025…" |
| Cover wording | "Văn bản hợp nhất" and "55/VBHN-VPQH" are **not in the PDF's text layer** (information only; the file name, the amending-law list and the modification date identify it as the March 2026 consolidated text) |

## Provisions found verbatim (10 of 10)

| Page claim | Provision in the official text |
| --- | --- |
| A vehicle with a heat engine is a *xe gắn máy* only if its design speed is not above 50 km/h and its working or equivalent displacement is not above 50 cm³ (electric: not above 04 kW); it does not include *xe đạp máy* | Điều 34 khoản 1 điểm g |
| Electric bicycles are *xe đạp máy* when the motor assists the pedalling and cuts out when the rider stops pedalling or at 25 km/h | Điều 34 khoản 2 điểm b |
| Riders of a *xe gắn máy* need no driving licence, but must know the traffic rules, have the skills, and meet age and health conditions | Điều 56 khoản 1 ("trừ người lái xe gắn máy quy định tại khoản 4 Điều này") and khoản 4 |
| Motorcycles beyond that class need a licence: A1 up to 125 cm³ or 11 kW, A above | Điều 57 khoản 1 điểm a, b |
| A person aged 16 or over may ride a *xe gắn máy*; 18 for licence classes A1, A, B1, B, C1… | Điều 59 khoản 1 điểm a, b |

Each fragment is asserted by `tools/qa/lib/legal-provisions.mjs` (unit tests: `tests/node/legal-provisions.test.mjs`). If the official wording ever changes, the check turns red and the page text must be reviewed before either is edited.

## Verdict on the published sentences

1. "Xe sử dụng động cơ nhiệt chỉ thuộc loại xe gắn máy khi … không lớn hơn 50 km/h … không lớn hơn 50 cm³ … không phải có giấy phép lái xe, nhưng phải từ đủ 16 tuổi …" — **accurate** (≤ 50 cm³, not "< 50"; ≤ 50 km/h).
2. Citation "Điều 34, 56 và 59" — **correct**; Điều 57 (licence classes) is also relevant if a page explains what a larger scooter needs.
3. "không suy điều kiện giấy phép chỉ từ tên gọi 50cc" — **accurate**: the class depends on design speed and displacement, not on the marketing name.
4. Practical advice (check brakes, tyres, lights; keep distance; do not weave) — not a statutory claim and not verified against a rule; the law forbids "lạng lách, đánh võng", the page says "luồn lách".
5. "không mặc định mọi học sinh đều được lái xe 50cc" — **accurate**.

The removed claims ("không cần bằng lái" for every renter, students as a blanket case) were over-stated for the same reasons.

## Gaps and optional improvements

- **Possible later amendments:** the PDF is the 23 March 2026 text. A review agent found none between 23 March and 22 October 2026 but could not exclude one; re-run the check after any legal change.
- **Optional wording (WORDPRESS_ACTION_REQUIRED, owner's choice):** the sentence "Nếu xe không thuộc phân loại này, cần đối chiếu điều kiện và giấy phép phù hợp trước khi lái" could state the rule: a *xe mô tô* needs a valid licence (A1 up to 125 cm³ or 11 kW, A above) and a rider of 18 or over (Điều 57, 59). Electric vehicles qualify as *xe gắn máy* only up to 04 kW and 50 km/h. This repository does not edit WordPress content.
- Not verified: the 2–3 metre following-distance topic of post 823, Thông tư 38/2024/TT-BGTVT, and fines for lending a vehicle to an unqualified rider; "Cảnh sát giao thông: 113" is not a statutory matter (the post removed the label and the phone lines).
