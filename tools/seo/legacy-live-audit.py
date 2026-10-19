#!/usr/bin/env python3
"""Read-only HTTP regression check for the 19 audited legacy objects and controls."""
import argparse
import concurrent.futures
import datetime
import json
import pathlib
import re
import sys
from html.parser import HTMLParser
from urllib.parse import urlsplit
import urllib.request
import urllib.error


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Head(HTMLParser):
    def __init__(self):
        super().__init__()
        self.canonical = []
        self.meta = {}
        self.links = []
        self.title = ""
        self.in_title = False

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "link" and a.get("rel") == "canonical":
            self.canonical.append(a.get("href"))
        if tag == "meta" and (a.get("name") or a.get("property")):
            self.meta[a.get("name") or a.get("property")] = a.get("content", "")
        if tag == "a" and a.get("href"):
            self.links.append(a["href"])
        if tag == "title":
            self.in_title = True

    def handle_endtag(self, tag):
        if tag == "title":
            self.in_title = False

    def handle_data(self, data):
        if self.in_title:
            self.title += data


def main():
    p = argparse.ArgumentParser()
    p.add_argument("--base", default="https://thuexemaynguyentu.com")
    p.add_argument("--out", required=True)
    p.add_argument("--before")
    p.add_argument("--verify", action="store_true")
    args = p.parse_args()
    root = pathlib.Path(__file__).resolve().parents[2]
    evidence = json.loads((root / "tests/fixtures/legacy-public-urls.json").read_text())
    base = args.base.rstrip("/")
    cases = []
    for obj in evidence["yoast"]:
        if obj["object_type"] not in ("post", "term"):
            continue
        path = urlsplit(obj["permalink"]).path
        canonical = base + path
        cases.append({"kind": "canonical", "url": canonical, "expected": canonical, "id": obj["object_id"]})
        prefix = "featured_item" if obj["object_type"] == "post" else "featured_item_category"
        slug = path.strip("/").split("/")[-1]
        cases.append({"kind": "alias", "url": f"{base}/{prefix}/{slug}/", "expected": canonical, "id": obj["object_id"]})
    controls = ["/", "/bo-suu-tap/", "/featured_item/", "/lien-he/", "/blog/", "/shop/",
                "/san-pham/xe-may-honda-wave/", "/robots.txt", "/sitemap_index.xml",
                "/featured_item-sitemap.xml", "/featured_item_category-sitemap.xml",
                "/bo-suu-tap/nt-definitely-missing/", "/featured_item_category/nt-definitely-missing/",
                "/bo-suu-tap/nt-definitely-missing/thue-xe-may/", "/nt-definitely-missing/"]
    # Product slug is discovered from the existing audit, not assumed by the checker.
    baseline = json.loads((root / "docs/wordpress-baseline-2026-10-17.json").read_text())
    product = baseline["products"][0]["post_name"]
    controls[6] = f"/san-pham/{product}/"
    cases += [{"kind": "control", "url": base + x} for x in controls]

    def fetch(case):
        try:
            req = urllib.request.Request(case["url"], headers={"User-Agent": "NguyenTu-Legacy-SEO-Audit/1.1"})
            try:
                r = urllib.request.build_opener(NoRedirect).open(req, timeout=25)
            except urllib.error.HTTPError as ex:
                r = ex
            body = r.read().decode("utf-8", "replace")
            h = Head()
            if "text/html" in r.headers.get("Content-Type", ""):
                h.feed(body)
            row = dict(case, status=r.code, location=r.headers.get("Location"),
                       canonical=h.canonical, title=h.title, robots=h.meta.get("robots"),
                       description=h.meta.get("description"), links=sorted(set(h.links)))
            if "xml" in r.headers.get("Content-Type", ""):
                row["sitemap_urls"] = re.findall(r"<loc>(.*?)</loc>", body)
            return row
        except (urllib.error.URLError, TimeoutError, OSError) as ex:
            return dict(case, error=str(ex))

    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        rows = list(pool.map(fetch, cases))
    failures = []
    for r in rows:
        if r.get("error"):
            failures.append(f"FETCH {r['url']}: {r['error']}")
        elif r["kind"] == "canonical" and (r["status"] != 200 or r["canonical"] != [r["expected"]]):
            failures.append(f"CANONICAL {r['url']}: HTTP {r['status']} {r['canonical']}")
        elif r["kind"] == "alias" and (r["status"] != 301 or r["location"] != r["expected"]):
            failures.append(f"ALIAS {r['url']}: HTTP {r['status']} -> {r['location']}")
        elif "nt-definitely-missing" in r["url"] and r["status"] != 404:
            failures.append(f"404 {r['url']}: HTTP {r['status']}")
    if args.before:
        before = {r["url"]: r for r in json.loads(pathlib.Path(args.before).read_text())["rows"]}
        for r in rows:
            if r["kind"] == "control" and r.get("status") == 200 and before.get(r["url"], {}).get("status") == 200:
                for key in ("canonical", "title", "robots", "description"):
                    if r.get(key) != before[r["url"]].get(key):
                        failures.append(f"CONTROL {key} changed: {r['url']}")
    output = {"checked_at": datetime.datetime.now(datetime.timezone.utc).isoformat(),
              "rows": rows, "failures": failures}
    pathlib.Path(args.out).write_text(json.dumps(output, ensure_ascii=False, indent=2) + "\n")
    print(f"Checked {len(rows)} URLs; {len(failures)} failures; report: {args.out}")
    for f in failures:
        print(f)
    return 1 if args.verify and failures else 0


if __name__ == "__main__":
    sys.exit(main())
