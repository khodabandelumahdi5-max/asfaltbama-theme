#!/usr/bin/env python3
"""List internal links in the content files that point to slugs the
manifest does not create and that are not known Elementor pages."""
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent / "asfaltbama-child" / "content"
m = json.loads((ROOT / "manifest.json").read_text(encoding="utf-8"))
known = {p["slug"] for p in m["pages"]} | {p["slug"] for p in m["posts"]}
# Pages built with Elementor on the site, not in the manifest.
known |= {"isogam-waterproofing", "asphalt-paving", "excavation-and-grading", "asphalt-joint-sealing",
          "machinery-rental", "demolition-scrap", "contact-us", "about-us", "articles", "asphalt-cost-per-square-meter", ""}
bad = 0
for f in sorted(ROOT.rglob("*.html")):
    for slug in re.findall(r'href="https://asfaltbama\.com/([^"/#?]*)/?', f.read_text(encoding="utf-8")):
        if slug not in known:
            print(f.relative_to(ROOT), slug)
            bad += 1
print("broken:", bad)
