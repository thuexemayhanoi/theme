#!/usr/bin/env bash
# Builds dist/nguyentu-legacy-compat.zip (companion plugin, installed separately from the theme).
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist
python3 -I - <<'PY'
import os, pathlib, sys, zipfile
src = pathlib.Path("plugins/nguyentu-legacy-compat")
out = pathlib.Path("dist/nguyentu-legacy-compat.zip")
if out.exists():
    out.unlink()
n = 0
with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for dirpath, _, files in os.walk(src):
        for f in sorted(files):
            p = pathlib.Path(dirpath) / f
            if f == ".htaccess" and pathlib.Path(dirpath) == src.parent:
                continue
            z.write(p, f"nguyentu-legacy-compat/{p.relative_to(src).as_posix()}")
            n += 1
with zipfile.ZipFile(out) as z:
    if "nguyentu-legacy-compat/nguyentu-legacy-compat.php" not in z.namelist():
        sys.exit("bad archive: main file missing")
print(f"{out}: {n} files, {out.stat().st_size // 1024} KB")
PY
