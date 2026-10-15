#!/usr/bin/env python3
"""Build featured images from real project photos (tools/photo_covers.js)
for the articles listed in COVERS, write them to
content/images/covers/<slug>-photo-cover.jpg with the Asfaltbama IPTC
credit, and point the manifest at them: the photo cover becomes the
featured image, the old generated text cover is released.

Needs Node with Playwright (NODE_PATH) and Pillow. Originals live in
photos-original/ (not shipped in the theme).
"""
import json
import re
import subprocess
import sys
import tempfile
from pathlib import Path

from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parent))
from watermark import XMP  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent
CONTENT = ROOT / "asfaltbama-child" / "content"
COVERS = {
    # asphalt
    "asphalt-price-per-ton": "takhlie-asfalt-garm-roye-ghirpashi-ghaboos.jpg",
    "asphalt-price-factors": "asfalt-mohavate-mojtame-maskoni-finisher-ghaltak.jpg",
    "asphalt-tonnage-calculation": "asfalt-mohavate-mojtame-maskoni-finisher-ghaltak.jpg",
    "asphalt-price-per-cm": "gheirpashi-bastar-asfalt-mohavate-mojtame.jpg",
    "topeka-vs-binder-asphalt": "asfalt-mohavate-mojtame-maskoni-finisher-ghaltak.jpg",
    "cold-vs-hot-asphalt": "takhlie-asfalt-garm-roye-ghirpashi-ghaboos.jpg",
    "asphalt-plant": "takhlie-asfalt-garm-roye-ghirpashi-ghaboos.jpg",
    "asphalt-yard-parking": "IMG_1304.jpeg",
    "parking-ramp-asphalt": "IMG_1304.jpeg",
    "asphalt-drainage-ponding": "IMG_1304.jpeg",
    "site-landscaping-asphalt": "asfalt-mohavate-mojtame-maskoni-finisher-ghaltak.jpg",
    "asphalt-paving-season": "asfalt-mohavate-mojtame-maskoni-finisher-ghaltak.jpg",
    "roller-finisher-rental": "asfalt-mohavate-mojtame-maskoni-finisher-ghaltak.jpg",
    "subgrade-preparation-compaction": "gheirpashi-bastar-asfalt-mohavate-mojtame.jpg",
    "engineered-backfill": "gheirpashi-bastar-asfalt-mohavate-mojtame.jpg",
    "asphalt-over-concrete": "ghirpashi-ghabl-asfalt-khiaban-ghaboos.jpg",
    "asphalt-milling": "ghirpashi-navar-asfalt-imen-sazi-zaferanieh.jpg",
    "asphalt-patching-guide": "ghirpashi-navar-asfalt-imen-sazi-zaferanieh.jpg",
    "curb-installation-guide": "ghirpashi-kenar-jadval-dariche-zaferanieh.jpg",
    "alley-asphalt": "ghirpashi-ghabl-asfalt-koocheh-zaferanieh.jpg",
    "asphalt-contract-checklist": "asfalt-mohavate-mojtame-maskoni-finisher-ghaltak.jpg",
    "asphalt-isogam-warranty-guide": "isogam-foildar-doorchini-darz-ghiri.jpg",
    # bitumen
    "bitumen-price": "ghirpashi-ghabl-asfalt-khiaban-ghaboos.jpg",
    "bitumen-types": "ghirpashi-ghabl-asfalt-koocheh-zaferanieh.jpg",
    "cold-bitumen": "ghirpashi-kenar-jadval-dariche-zaferanieh.jpg",
    "buy-drum-bitumen": "ghirgooni-sarvis-behdashti-ghir-dagh-kaf.jpg",
    # isogam and bitumen roofing
    "isogam-installation-steps": "nasb-isogam-foildar-delijan.jpg",
    "isogam-gas-torch": "nasb-isogam-foildar-delijan.jpg",
    "isogam-delijan": "nasb-isogam-foildar-delijan.jpg",
    "isogam-price-guide": "ghirkeshi-isogam-foildar-poshtbam.jpg",
    "isogam-roll-price": "ghirkeshi-isogam-foildar-poshtbam.jpg",
    "isogam-brands-price": "isogam-foildar-poshtbam-elahieh.jpg",
    "roof-waterproofing-methods": "isogam-foildar-poshtbam-elahieh.jpg",
    "glass-wool-isogam": "isogam-foildar-doorchini-divar.jpg",
    "best-isogam": "isogam-foildar-doorchini-divar.jpg",
    "roof-leak-after-isogam": "isogam-foildar-doorchini-darz-ghiri.jpg",
    "isogam-over-old-isogam": "isogam-poshtbam-hampooshani-darz-ghiri.jpg",
    "roof-winter-checklist": "isogam-poshtbam-paye-kooler.jpg",
    "isogam-cost-responsibility": "isogam-poshtbam-paye-kooler.jpg",
    "exir-isogam": "isogam-foildar-bam-sanati-borj-khonak-kon.jpg",
    "bitumen-roofing-vs-isogam": "ghirgooni-hammam-kaf-va-divar.jpg",
    "bathroom-waterproofing": "ghirgooni-hammam-kaf-va-divar.jpg",
    "bathroom-bitumen-roofing": "ghirgooni-sarvis-behdashti-konj-dorchini.jpg",
    "bitumen-roofing-steps": "ghirgooni-sarvis-behdashti-dorchini-looleh.jpg",
    "bitumen-roofing-price": "ghirgooni-sarvis-behdashti-ghir-dagh-kaf.jpg",
    # excavation, demolition, machinery
    "bobcat-rental-guide": "bobcat-bargiri-nakhale-kamyon.jpg",
    "construction-debris-removal": "bobcat-bargiri-nakhale-kamyon.jpg",
    "demolition-cost-guide": "bargiri-khak-nakhale-bobcat-kargah-shahri.jpg",
    "excavation-cost-guide": "bargiri-khak-nakhale-bobcat-kargah-shahri.jpg",
    "land-grading-guide": "bargiri-khak-nakhale-bobcat-kargah-shahri.jpg",
}
CATS = {
    "asphalt-paving": "آسفالت‌کاری", "cost-estimation": "قیمت و برآورد", "waterproofing-isogam": "ایزوگام و قیرگونی",
    "bitumen": "قیر", "excavation-grading": "خاکبرداری و زیرسازی", "demolition": "تخریب و نخاله",
    "machinery-rental": "اجاره ماشین‌آلات", "contracts-warranty": "قرارداد و ضمانت", "joint-sealing": "درزگیری آسفالت",
}


def split_title(t):
    t = re.sub(r"\s*\|.*$", "", t).strip()
    m = re.match(r"^(.*?)\s*[؛:]\s*(.+)$", t)
    if m:
        return m.group(1), m.group(2)
    m = re.match(r"^(.*?؟)\s*(.+)$", t)
    return (m.group(1), m.group(2)) if m else (t, "")


def main():
    manifest_path = CONTENT / "manifest.json"
    m = json.loads(manifest_path.read_text(encoding="utf-8"))
    posts = {p["slug"]: p for p in m["posts"]}
    items = []
    for slug, photo in COVERS.items():
        p = posts[slug]
        head, sub = split_title(p.get("seo_title") or p["title"])
        items.append({"file": f"{slug}-photo-cover", "photo": photo, "title": head, "sub": sub,
                      "cat": CATS.get(p["category"], "آسفالت با ما"), "slug": slug, "full": (f"{head} {sub}" if head.endswith("؟") else f"{head}؛ {sub}") if sub else head})
    with tempfile.TemporaryDirectory() as tmp:
        spec = Path(tmp) / "items.json"
        spec.write_text(json.dumps(items, ensure_ascii=False), encoding="utf-8")
        subprocess.run(["node", str(ROOT / "tools" / "photo_covers.js"), str(spec), tmp], check=True)
        for it in items:
            exif = Image.Exif()
            exif[0x013B] = "Asfaltbama - asfaltbama.com"
            exif[0x8298] = "(c) Asfaltbama (asfaltbama.com)"
            Image.open(Path(tmp) / f"{it['file']}.png").convert("RGB").save(
                CONTENT / "images" / "covers" / f"{it['file']}.jpg", "JPEG", quality=78, optimize=True,
                progressive=True, exif=exif, xmp=XMP)

    replace = set(m.get("replace_featured_sources", []))
    new_files = {f"images/covers/{it['file']}.jpg" for it in items}
    for im in m["images"]:
        if im["file"] in new_files:
            continue
        for it in items:
            if it["slug"] in im.get("featured_for", []):
                im["featured_for"].remove(it["slug"])
                replace.add(Path(im["file"]).name)
    m["images"] = [im for im in m["images"] if im["file"] not in new_files]
    for it in items:
        m["images"].append({
            "file": f"images/covers/{it['file']}.jpg",
            "title": "تصویر شاخص: " + it["full"],
            "alt": f"{it['full']} | آسفالت با ما",
            "featured_for": [it["slug"]],
        })
    m["replace_featured_sources"] = sorted(replace)
    manifest_path.write_text(json.dumps(m, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(len(items), "photo covers")


if __name__ == "__main__":
    main()
