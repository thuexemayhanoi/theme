#!/usr/bin/env bash
# Build BOTH independent WordPress plugins in dist/, never put plugin sources in theme ZIP.
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist
python3 -I - <<'PY'
import pathlib, sys, zipfile

root = pathlib.Path("plugins")
for slug, main in [
    ("nguyentu-legacy-compat", "nguyentu-legacy-compat.php"),
    ("nguyentu-smart-core", "nguyentu-smart-core.php"),
]:
    src = root / slug
    required = [main, "readme.txt", "uninstall.php"]
    if any(not (src / name).is_file() for name in required):
        sys.exit(f"Plugin {slug}: a required file is missing")
    out = pathlib.Path("dist") / (slug + ".zip")
    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in sorted(src.rglob("*")):
            if not path.is_file() or path.name.startswith(".") or path.suffix in (".key", ".pem"):
                continue
            archive.write(path, slug + "/" + path.relative_to(src).as_posix())
    with zipfile.ZipFile(out) as archive:
        paths = archive.namelist()
        for name in required:
            if slug + "/" + name not in paths:
                sys.exit(f"Plugin {slug}: malformed archive, missing {name}")
        if any(name.startswith(slug + "/tests/") or "/.env" in name for name in paths):
            sys.exit(f"Plugin {slug}: development or private file leaked")
    print(f"{out}: {len(paths)} files, {out.stat().st_size // 1024} KB")
PY
