# Companion plugin round trip (capture, replay, dormant)

Sandbox only. Old-theme registration is simulated by `tools/sandbox/fixtures/mu-plugins/nt-sim-flatsome-registration.php`
(the real Flatsome theme is proprietary and not obtainable here), so this proves the MECHANISM, not the real slugs.

## PASS - 1a simulator active: featured_item is registered by another component

```
key,value
registered_by_other_component,yes
replay_active,no
snapshot_source,runtime
snapshot_captured_at,2026-10-18T02:07:48+00:00
snapshot_order,"featured_item > featured_item_category"
cpt_rewrite,"{""slug"":""featured_item"",""with_front"":true,""pages"":true,""feeds"":false,""ep_mask"":1}"
tax_rewrite,"{""with_front"":true,""hierarchical"":false,""ep_mask"":0,""slug"":""bo-suu-tap""}"
post_type_exists,yes
taxonomy_exists,yes
published_items,15
stored_rewrite_rules,12
```

## PASS - 1b capture the registration (runtime snapshot)

```
Success: Đã ghi lại cấu hình đăng ký featured_item từ runtime.
```

## PASS - 1c export URLs before (15 items + 4 terms)

```
type,id,slug,status,path
term,77,bang-gia,term,/bo-suu-tap/bang-gia/
term,78,thue-ngay,term,/bo-suu-tap/thue-ngay/
term,79,thue-tuan,term,/bo-suu-tap/thue-tuan/
term,80,thue-thang,term,/bo-suu-tap/thue-thang/
item,587,thue-xe-may-ha-noi-1-ngay,publish,/featured_item/thue-xe-may-ha-noi-1-ngay/
item,682,dich-vu-thue-xe-may-moi-ngay-ha-noi,publish,/featured_item/dich-vu-thue-xe-may-moi-ngay-ha-noi/
item,686,thue-xe-may-theo-ngay-ha-noi-dat-xe-nhanh-coc-nhe,publish,/featured_item/thue-xe-may-theo-ngay-ha-noi-dat-xe-nhanh-coc-nhe/
item,693,thue-xe-may-1-ngay-ha-noi-giao-xe-tan-noi-thu-tuc-don-gian,publish,/featured_item/thue-xe-may-1-ngay-ha-noi-giao-xe-tan-noi-thu-tuc-don-gian/
item,696,thue-xe-may,publish,/featured_item/thue-xe-may/
item,701,thue-xe-may-ha-noi-theo-tuan-gia-uu-dai-giao-xe-nhanh,publish,/featured_item/thue-xe-may-ha-noi-theo-tuan-gia-uu-dai-giao-xe-nhanh/
item,703,dich-vu-thue-xe-may-1-tuan-tai-ha-noi-tien-loi-an-toan-coc-nhe,publish,/featured_item/dich-vu-thue-xe-may-1-tuan-tai-ha-noi-tien-loi-an-toan-coc-nhe/
item,705,thue-xe-may-moi-tuan-ha-noi-tiet-kiem-thuan-tien-giao-tan-noi,publish,/featured_item/thue-xe-may-moi-tuan-ha-noi-tiet-kiem-thuan-tien-giao-tan-noi/
item,708,thue-xe-may-ha-noi-1-tuan-thu-tuc-nhanh-coc-nhe-dich-vu-tot,publish,/featured_item/thue-xe-may-ha-noi-1-tuan-thu-tuc-nhanh-coc-nhe-dich-vu-tot/
item,710,bang-gia-thue-cac-loai-xe-may-1-tuan-ha-noi-giao-xe-tan-noi,publish,/featured_item/bang-gia-thue-cac-loai-xe-may-1-tuan-ha-noi-giao-xe-tan-noi/
item,793,dich-vu-thue-xe-may-thang-ha-noi-thuan-tien-an-toan-uy-tin,publish,/featured_item/dich-vu-thue-xe-may-thang-ha-noi-thuan-tien-an-toan-uy-tin/
item,795,thue-xe-may-ha-noi-hang-thang-giao-xe-tan-noi-coc-nhe-tien-loi,publish,/featured_item/thue-xe-may-ha-noi-hang-thang-giao-xe-tan-noi-coc-nhe-tien-loi/
item,802,dich-vu-thue-xe-may-1-thang,publish,/featured_item/dich-vu-thue-xe-may-1-thang/
item,809,cho-thue-xe-may-cac-loai-theo-thang-ha-noi-tien-loi-gia-tot,publish,/featured_item/cho-thue-xe-may-cac-loai-theo-thang-ha-noi-tien-loi-gia-tot/
item,811,cho-thue-xe-may-thang-ha-noi,publish,/featured_item/cho-thue-xe-may-thang-ha-noi/
```

## PASS - 2a simulator removed: the plugin now registers them itself (replay)

```
key,value
registered_by_other_component,no
replay_active,yes
snapshot_source,runtime
snapshot_captured_at,2026-10-18T02:07:57+00:00
snapshot_order,"featured_item > featured_item_category"
cpt_rewrite,"{""slug"":""featured_item"",""with_front"":true,""pages"":true,""feeds"":false,""ep_mask"":1}"
tax_rewrite,"{""with_front"":true,""hierarchical"":false,""ep_mask"":0,""slug"":""bo-suu-tap""}"
post_type_exists,yes
taxonomy_exists,yes
published_items,15
stored_rewrite_rules,12
```

## PASS - 2b export URLs after

```
type,id,slug,status,path
term,77,bang-gia,term,/bo-suu-tap/bang-gia/
term,78,thue-ngay,term,/bo-suu-tap/thue-ngay/
term,79,thue-tuan,term,/bo-suu-tap/thue-tuan/
term,80,thue-thang,term,/bo-suu-tap/thue-thang/
item,587,thue-xe-may-ha-noi-1-ngay,publish,/featured_item/thue-xe-may-ha-noi-1-ngay/
item,682,dich-vu-thue-xe-may-moi-ngay-ha-noi,publish,/featured_item/dich-vu-thue-xe-may-moi-ngay-ha-noi/
item,686,thue-xe-may-theo-ngay-ha-noi-dat-xe-nhanh-coc-nhe,publish,/featured_item/thue-xe-may-theo-ngay-ha-noi-dat-xe-nhanh-coc-nhe/
item,693,thue-xe-may-1-ngay-ha-noi-giao-xe-tan-noi-thu-tuc-don-gian,publish,/featured_item/thue-xe-may-1-ngay-ha-noi-giao-xe-tan-noi-thu-tuc-don-gian/
item,696,thue-xe-may,publish,/featured_item/thue-xe-may/
item,701,thue-xe-may-ha-noi-theo-tuan-gia-uu-dai-giao-xe-nhanh,publish,/featured_item/thue-xe-may-ha-noi-theo-tuan-gia-uu-dai-giao-xe-nhanh/
item,703,dich-vu-thue-xe-may-1-tuan-tai-ha-noi-tien-loi-an-toan-coc-nhe,publish,/featured_item/dich-vu-thue-xe-may-1-tuan-tai-ha-noi-tien-loi-an-toan-coc-nhe/
item,705,thue-xe-may-moi-tuan-ha-noi-tiet-kiem-thuan-tien-giao-tan-noi,publish,/featured_item/thue-xe-may-moi-tuan-ha-noi-tiet-kiem-thuan-tien-giao-tan-noi/
item,708,thue-xe-may-ha-noi-1-tuan-thu-tuc-nhanh-coc-nhe-dich-vu-tot,publish,/featured_item/thue-xe-may-ha-noi-1-tuan-thu-tuc-nhanh-coc-nhe-dich-vu-tot/
item,710,bang-gia-thue-cac-loai-xe-may-1-tuan-ha-noi-giao-xe-tan-noi,publish,/featured_item/bang-gia-thue-cac-loai-xe-may-1-tuan-ha-noi-giao-xe-tan-noi/
item,793,dich-vu-thue-xe-may-thang-ha-noi-thuan-tien-an-toan-uy-tin,publish,/featured_item/dich-vu-thue-xe-may-thang-ha-noi-thuan-tien-an-toan-uy-tin/
item,795,thue-xe-may-ha-noi-hang-thang-giao-xe-tan-noi-coc-nhe-tien-loi,publish,/featured_item/thue-xe-may-ha-noi-hang-thang-giao-xe-tan-noi-coc-nhe-tien-loi/
item,802,dich-vu-thue-xe-may-1-thang,publish,/featured_item/dich-vu-thue-xe-may-1-thang/
item,809,cho-thue-xe-may-cac-loai-theo-thang-ha-noi-tien-loi-gia-tot,publish,/featured_item/cho-thue-xe-may-cac-loai-theo-thang-ha-noi-tien-loi-gia-tot/
item,811,cho-thue-xe-may-thang-ha-noi,publish,/featured_item/cho-thue-xe-may-thang-ha-noi/
```

## PASS - 3a compare: every URL path is identical

```
Trước: 19 URL · Sau: 19 URL
Success: Tất cả URL featured_item giữ nguyên (path giống hệt).
```

## PASS - 3b verify: rewrite rules resolve every URL to itself and HTTP answers 200

```
Đã kiểm tra 19 URL (featured_item + term).
Success: Mọi URL featured_item và term resolve đúng về chính nó.
```

## PASS - 3c a featured single and a term answer 200 over HTTP without the old theme

```
/featured_item/thue-xe-may-ha-noi-1-ngay/ -> 200
/bo-suu-tap/bang-gia/ -> 200
```

## PASS - 4 old theme back (simulator ON): the plugin stays dormant

```
key,value
registered_by_other_component,yes
replay_active,no
snapshot_source,runtime
snapshot_captured_at,2026-10-18T02:07:57+00:00
snapshot_order,"featured_item > featured_item_category"
cpt_rewrite,"{""slug"":""featured_item"",""with_front"":true,""pages"":true,""feeds"":false,""ep_mask"":1}"
tax_rewrite,"{""with_front"":true,""hierarchical"":false,""ep_mask"":0,""slug"":""bo-suu-tap""}"
post_type_exists,yes
taxonomy_exists,yes
published_items,15
stored_rewrite_rules,12
```

