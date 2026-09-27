#!/usr/bin/env python3
"""Record the hashes of every committed version of each article file in
content/manifest.json (posts[].prev_hashes).

The importer replaces an existing article with a newer file only while
its content still matches one of these versions, so articles edited in
WordPress are never overwritten. Hashes are whitespace-insensitive, the
same as asfaltbama_importer_nhash() in PHP.
"""
import hashlib
import json
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CONTENT = ROOT / "asfaltbama-child" / "content"
MANIFEST = CONTENT / "manifest.json"


def nhash(text):
    text = text.strip(" \t\n\r\0\x0b")
    return hashlib.md5(re.sub(r"[ \t\n\r\f\x0b]+", " ", text).encode("utf-8")).hexdigest()


def git(*args):
    return subprocess.run(["git", *args], cwd=ROOT, capture_output=True, text=True, check=True).stdout


def main():
    manifest = json.loads(MANIFEST.read_text(encoding="utf-8"))
    for post in manifest["posts"]:
        rel = "asfaltbama-child/content/" + post["file"]
        current = nhash((CONTENT / post["file"]).read_text(encoding="utf-8"))
        hashes = []
        for commit in git("log", "--format=%H", "--follow", "--", rel).split():
            try:
                old = git("show", f"{commit}:{rel}")
            except subprocess.CalledProcessError:
                continue
            h = nhash(old)
            if h != current and h not in hashes:
                hashes.append(h)
        if hashes:
            post["prev_hashes"] = hashes
        else:
            post.pop("prev_hashes", None)
    MANIFEST.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


if __name__ == "__main__":
    main()
