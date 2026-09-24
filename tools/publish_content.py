#!/usr/bin/env python3
"""Publish articles and site pages from content/ to asfaltbama.com.

Uses the WordPress REST API with an Application Password read from the
WP_USER and WP_APP_PASSWORD environment variables. Safe to re-run: posts
and pages are looked up by slug and updated instead of duplicated.

What it does:
  1. Moves the About page to /about-us/ (the header menu links there).
  2. Creates the "مقالات" page at /articles/ and sets it as the posts page.
  3. Creates/updates the pages in the manifest (e.g. /services/).
  4. Creates/updates the posts in the manifest, credited to a site
     administrator other than the API user.
  5. Sets Rank Math title, description and focus keyword for each.

Usage:
  python3 tools/publish_content.py --dry-run     # show what would change
  python3 tools/publish_content.py               # publish
  python3 tools/publish_content.py --status draft
"""

import argparse
import base64
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request

SITE = os.environ.get("WP_SITE", "https://asfaltbama.com").rstrip("/")
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CONTENT = os.path.join(ROOT, "asfaltbama-child", "content")
ALL_STATUSES = "publish,draft,pending,private,future"


class WP:
    def __init__(self, user, password, dry_run):
        token = base64.b64encode(f"{user}:{password}".encode()).decode()
        self.headers = {
            "Authorization": f"Basic {token}",
            "Content-Type": "application/json; charset=utf-8",
            "User-Agent": "asfaltbama-publisher/1.0",
        }
        self.dry_run = dry_run

    def request(self, method, path, params=None, body=None):
        url = f"{SITE}/wp-json/{path.lstrip('/')}"
        if params:
            url += "?" + urllib.parse.urlencode(params)
        data = json.dumps(body).encode() if body is not None else None
        req = urllib.request.Request(url, data=data, method=method, headers=self.headers)
        try:
            with urllib.request.urlopen(req, timeout=60) as resp:
                return json.loads(resp.read().decode() or "null")
        except urllib.error.HTTPError as err:
            detail = err.read().decode(errors="replace")[:500]
            raise SystemExit(f"{method} {url} -> HTTP {err.code}: {detail}")

    def get(self, path, **params):
        return self.request("GET", path, params=params)

    def write(self, method, path, body, label):
        if self.dry_run:
            print(f"  [dry-run] {method} {path}: {label}")
            return None
        return self.request(method, path, body=body)


def read(rel):
    with open(os.path.join(CONTENT, rel), encoding="utf-8") as fh:
        return fh.read()


def find_by_slug(wp, kind, slug):
    found = wp.get(f"wp/v2/{kind}", slug=slug, status=ALL_STATUSES, context="edit")
    return found[0] if found else None


def upsert(wp, kind, slug, fields):
    existing = find_by_slug(wp, kind, slug)
    body = dict(fields, slug=slug)
    if existing:
        print(f"  update {kind[:-1]} #{existing['id']} /{slug}/")
        result = wp.write("POST", f"wp/v2/{kind}/{existing['id']}", body, f"update /{slug}/")
        return result or existing
    print(f"  create {kind[:-1]} /{slug}/")
    return wp.write("POST", f"wp/v2/{kind}", body, f"create /{slug}/")


def set_seo(wp, obj, item):
    if not obj:
        return
    meta = {
        "rank_math_title": item.get("seo_title", ""),
        "rank_math_description": item.get("seo_description", ""),
        "rank_math_focus_keyword": item.get("focus_keyword", ""),
    }
    meta = {k: v for k, v in meta.items() if v}
    if meta:
        wp.write(
            "POST",
            "rankmath/v1/updateMeta",
            {"objectType": "post", "objectID": obj["id"], "meta": meta},
            f"Rank Math meta for #{obj['id']}",
        )


def pick_author(wp, api_user_id):
    admins = wp.get("wp/v2/users", roles="administrator", context="edit", per_page=100)
    others = [u for u in admins if u["id"] != api_user_id]
    for u in others:
        if u.get("slug") == "admin":
            return u
    return others[0] if others else None


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--dry-run", action="store_true", help="show changes without writing")
    ap.add_argument("--status", default="publish", choices=["publish", "draft"], help="status for new posts")
    args = ap.parse_args()

    user, password = os.environ.get("WP_USER"), os.environ.get("WP_APP_PASSWORD")
    if not user or not password:
        sys.exit("WP_USER and WP_APP_PASSWORD must be set.")

    wp = WP(user, password, args.dry_run)
    with open(os.path.join(CONTENT, "manifest.json"), encoding="utf-8") as fh:
        manifest = json.load(fh)

    me = wp.get("wp/v2/users/me", context="edit")
    print(f"Connected to {SITE} as {me['name']} (roles: {', '.join(me.get('roles', []))})")

    author = pick_author(wp, me["id"])
    print(f"Posts will be credited to: {author['name'] if author else me['name']}")

    # 1. About page -> /about-us/
    print("\nAbout page")
    about = manifest["about_page"]
    if find_by_slug(wp, "pages", about["new_slug"]):
        print(f"  /{about['new_slug']}/ already exists")
    else:
        page = find_by_slug(wp, "pages", about["current_slug"])
        if page:
            print(f"  move page #{page['id']} to /{about['new_slug']}/")
            wp.write("POST", f"wp/v2/pages/{page['id']}", {"slug": about["new_slug"]}, "change slug")
        else:
            print("  About page not found; skipped")

    # 2. Articles page and posts page setting
    print("\nArticles page")
    pp = manifest["posts_page"]
    articles = upsert(wp, "pages", pp["slug"], {"title": pp["title"], "content": "", "status": "publish"})
    set_seo(wp, articles, pp)
    settings = wp.get("wp/v2/settings")
    if articles and settings.get("page_for_posts") != articles["id"]:
        if "page_for_posts" not in settings:
            print("  NOTE: page_for_posts is not exposed by the REST API on this site.")
            print("        Set it by hand: Settings > Reading > Posts page = مقالات")
        else:
            print(f"  set posts page to #{articles['id']} (was {settings.get('page_for_posts')})")
            wp.write("POST", "wp/v2/settings", {"page_for_posts": articles["id"]}, "page_for_posts")

    # 3. Pages
    print("\nPages")
    for item in manifest["pages"]:
        page = upsert(wp, "pages", item["slug"], {
            "title": item["title"],
            "content": read(item["file"]),
            "excerpt": item.get("excerpt", ""),
            "status": "publish",
        })
        set_seo(wp, page, item)

    # 4. Posts
    print("\nPosts")
    categories = {c["slug"]: c["id"] for c in wp.get("wp/v2/categories", per_page=100)}
    for item in manifest["posts"]:
        fields = {
            "title": item["title"],
            "content": read(item["file"]),
            "excerpt": item["excerpt"],
            "categories": [categories[item["category"]]] if item["category"] in categories else [],
        }
        if not find_by_slug(wp, "posts", item["slug"]):
            fields["status"] = args.status
            if author:
                fields["author"] = author["id"]
        post = upsert(wp, "posts", item["slug"], fields)
        set_seo(wp, post, item)

    print("\nDone." + (" (dry run, nothing was changed)" if args.dry_run else ""))


if __name__ == "__main__":
    main()
