#!/usr/bin/env bash
# Builds dist/nguyentu-editorial-commerce.zip: the installable theme only (no tests, tools, docs,
# reports, plugins, vendor or CI files). Verifies the archive layout before finishing.
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist
python3 -I - <<'PY'
import os, re, sys, zipfile, pathlib
root = pathlib.Path(".")
slug = "nguyentu-editorial-commerce"
skip_dirs = {".git", ".github", "node_modules", "vendor", "tests", "tools", "docs", "reports", "plugins", "dist", ".phpunit.cache", "playwright-report", "test-results"}
skip_files = re.compile(r"^(composer\.(json|lock)|package(-lock)?\.json|phpunit.*\.xml(\.dist)?|phpcs\.xml(\.dist)?|eslint\.config\.mjs|playwright\.config\.mjs|\.editorconfig|\.gitignore|\.gitattributes|README\.md|CLAUDE\.md)$")
out = root / "dist" / f"{slug}.zip"
if out.exists():
    out.unlink()
count = 0
with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = sorted(d for d in dirnames if d not in skip_dirs)
        for name in sorted(filenames):
            p = pathlib.Path(dirpath) / name
            if skip_files.match(name) or name.endswith(".zip") or name == ".htaccess" and pathlib.Path(dirpath) == root:
                continue
            z.write(p, f"{slug}/{p.relative_to(root).as_posix()}")
            count += 1
with zipfile.ZipFile(out) as z:
    names = z.namelist()
    must = [f"{slug}/style.css", f"{slug}/functions.php", f"{slug}/index.php", f"{slug}/front-page.php"]
    missing = [m for m in must if m not in names]
    leaked = [n for n in names if re.search(rf"^{slug}/(tests|tools|docs|reports|plugins|vendor|node_modules|\.git)/", n)]
    if missing or leaked:
        sys.exit(f"bad archive: missing={missing} leaked={leaked[:5]}")
print(f"{out}: {count} files, {out.stat().st_size // 1024} KB")
PY
