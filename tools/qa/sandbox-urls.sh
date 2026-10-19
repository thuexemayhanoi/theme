#!/usr/bin/env bash
# Builds reports/seo-urls.sandbox.csv = golden list + sandbox-only rows (sample posts, featured items,
# cart pages) read from the sandbox database. Read-only.
set -euo pipefail
cd "$(dirname "$0")/../.."
WP="bash tools/sandbox/wp.sh"
out=reports/seo-urls.sandbox.csv
cp reports/seo-urls.csv "$out"
sample=$($WP post list --post_type=post --post_status=publish --orderby=ID --order=ASC --posts_per_page=12 --field=url | sed 's#^https\?://[^/]*##')
while IFS= read -r p; do [ -n "$p" ] && echo "post,\"$p\",sandbox: post sample,200,," >> "$out"; done <<< "$sample"
items=$($WP post list --post_type=featured_item --post_status=publish --orderby=ID --order=ASC --posts_per_page=-1 --field=url | sed 's#^https\?://[^/]*##')
while IFS= read -r p; do [ -n "$p" ] && echo "featured_item,\"$p\",sandbox: featured_item,200,," >> "$out"; done <<< "$items"
echo "$(($(wc -l < "$out") - 1)) URLs in $out"
