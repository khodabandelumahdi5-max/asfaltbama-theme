#!/usr/bin/env python3
"""Render the English/Arabic article covers listed in tools/intl_covers.json
(written by tools/intl_articles.py) that do not exist yet, as 1200x675
WebP files in content/images/covers/intl/.

Needs Node with Playwright (NODE_PATH pointing at its node_modules) and
Pillow.
"""
import json
import subprocess
import tempfile
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "asfaltbama-child" / "content" / "images" / "covers" / "intl"


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    items = json.loads((ROOT / "tools" / "intl_covers.json").read_text(encoding="utf-8"))
    todo = [i for i in items if not (OUT / f"{i['file']}.webp").exists()]
    if not todo:
        print("no new covers")
        return
    with tempfile.TemporaryDirectory() as tmp:
        spec = Path(tmp) / "items.json"
        spec.write_text(json.dumps(todo, ensure_ascii=False), encoding="utf-8")
        subprocess.run(["node", str(ROOT / "tools" / "covers_intl.js"), str(spec), tmp], check=True)
        for item in todo:
            Image.open(Path(tmp) / f"{item['file']}.png").convert("RGB").save(
                OUT / f"{item['file']}.webp", "WEBP", quality=82, method=6)
            print("cover", item["file"])


if __name__ == "__main__":
    main()
