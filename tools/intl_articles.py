#!/usr/bin/env python3
"""English and Arabic versions of the articles, and the Persian SEO
fields that change when an article is expanded.

Writes content/manifest.json:
- pages[]: one "layout": "article" page per language version, plus the
  two guides hubs ("layout": "article-hub");
- posts[]: hreflang_group on each Persian article that has versions, and
  the updated Persian title/excerpt/SEO fields listed in FA.

Run after adding an entry, then tools/prev_hashes.py.
"""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CONTENT = ROOT / "asfaltbama-child" / "content"
MANIFEST = CONTENT / "manifest.json"

HUBS = [
    {
        "slug": "asphalt-waterproofing-guides",
        "lang": "en",
        "title": "Asphalt Paving and Waterproofing Guides",
        "file": "pages/guides/guides-en.html",
        "excerpt": "Field-tested guides on asphalt paving, Isogam membranes and hot bitumen waterproofing for owners, consultants and contractors in Iraq and the Gulf.",
        "seo_title": "Asphalt Paving and Waterproofing Guides | Asfaltbama",
        "seo_description": "Practical guides on asphalt paving, Isogam roof membranes and bitumen waterproofing from a contractor with 25+ years of experience, for Iraq and the Gulf.",
        "focus_keyword": "asphalt paving and waterproofing guides",
    },
    {
        "slug": "asphalt-waterproofing-guides-ar",
        "lang": "ar",
        "title": "أدلة رصف الأسفلت والعزل المائي",
        "file": "pages/guides/guides-ar.html",
        "excerpt": "أدلة عملية في رصف الأسفلت والعزل بالإيزوجام والقير للمالكين والاستشاريين والمقاولين في العراق والخليج، من خبرة تزيد على ٢٥ عاماً.",
        "seo_title": "أدلة رصف الأسفلت والعزل المائي | أسفلت با ما",
        "seo_description": "أدلة عملية في رصف الأسفلت وعزل الأسطح بالإيزوجام والعزل بالقير والجنفاص من مقاول بخبرة تزيد على ٢٥ عاماً، للعراق ودول الخليج.",
        "focus_keyword": "أدلة رصف الأسفلت والعزل المائي",
    },
]

# Persian article slug -> {"fa": updated Persian fields, "en": {...}, "ar": {...}}
ARTICLES = {
    "isogam-installation-steps": {
        "fa": {
            "excerpt": "از بازدید و شیب‌بندی تا پرایمر، هم‌پوشانی رول‌ها، دورچینی، محافظت و آزمایش آب‌بندی؛ راهنمای کامل ۱۱ مرحله‌ای اجرای ایزوگامی که سال‌ها بدون نشتی بماند.",
            "seo_title": "مراحل اجرای ایزوگام پشت‌بام؛ ۱۱ گام اصولی | آسفالت با ما",
            "seo_description": "اجرای ایزوگام پشت‌بام در ۱۱ گام: آماده‌سازی و شیب‌بندی، ماهیچه‌کشی، پرایمر، هم‌پوشانی، دورچینی، محافظت و تست آب‌بندی ۲۴ تا ۴۸ ساعته.",
        },
        "en": {
            "slug": "isogam-roof-membrane-installation",
            "title": "Isogam Roof Membrane Installation: A Step-by-Step Guide",
            "excerpt": "From deck preparation and falls to primer, laps, torching, upstands and the flood test: eleven steps to an Isogam roof that stays watertight in the Gulf heat.",
            "seo_title": "Isogam Roof Membrane Installation: 11 Steps | Asfaltbama",
            "seo_description": "How to install an Isogam (APP modified bitumen) roof membrane: deck prep, falls, primer, laps, torching, upstands and a 24–48 hour flood test.",
            "focus_keyword": "Isogam roof membrane installation",
        },
        "ar": {
            "slug": "isogam-roof-installation-ar",
            "title": "مراحل عزل الأسطح بالإيزوجام: دليل التنفيذ خطوة بخطوة",
            "excerpt": "من تجهيز السطح والميول إلى البرايمر والتراكب واللحام والدروات واختبار الغمر: إحدى عشرة خطوة لعزل سطح بالإيزوجام يصمد أمام حر الخليج والعراق.",
            "seo_title": "عزل الأسطح بالإيزوجام: ١١ خطوة للتنفيذ الصحيح | أسفلت با ما",
            "seo_description": "مراحل عزل الأسطح بالإيزوجام: تجهيز السطح والميول والبرايمر والتراكب واللحام ورفع العزل على الدروات واختبار الغمر لمدة ٢٤ إلى ٤٨ ساعة.",
            "focus_keyword": "عزل الأسطح بالإيزوجام",
        },
    },
}


def main():
    manifest = json.loads(MANIFEST.read_text(encoding="utf-8"))
    pages = manifest["pages"]
    posts = {p["slug"]: p for p in manifest["posts"]}

    def upsert(entry):
        for i, page in enumerate(pages):
            if page["slug"] == entry["slug"]:
                pages[i] = entry
                return
        pages.append(entry)

    for hub in HUBS:
        upsert(dict(hub, layout="article-hub", managed=True, hreflang_group="guides-intl"))

    for slug, versions in ARTICLES.items():
        post = posts[slug]
        group = "art-" + slug
        post["hreflang_group"] = group
        post.update(versions.get("fa", {}))
        for lang in ("en", "ar"):
            if lang not in versions:
                continue
            v = versions[lang]
            if not (CONTENT / "articles" / lang / f"{slug}.html").exists():
                raise SystemExit(f"missing articles/{lang}/{slug}.html")
            upsert({
                "slug": v["slug"],
                "title": v["title"],
                "file": f"articles/{lang}/{slug}.html",
                "excerpt": v["excerpt"],
                "seo_title": v["seo_title"],
                "seo_description": v["seo_description"],
                "focus_keyword": v["focus_keyword"],
                "lang": lang,
                "layout": "article",
                "category": post["category"],
                "managed": True,
                "hreflang_group": group,
            })

    MANIFEST.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


if __name__ == "__main__":
    main()
