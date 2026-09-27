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
    "isogam-price-guide": {
        "fa": {
            "excerpt": "۸ عامل تعیین‌کننده‌ی قیمت ایزوگام، انواع ایزوگام و کاربردشان، ۱۲ کنترل ساده برای تشخیص ایزوگام مرغوب، تقلب‌های رایج، محاسبه‌ی تعداد رول با مثال و مقایسه‌ی پیشنهاد قیمت اجرا.",
            "seo_description": "قیمت ایزوگام به ضخامت و وزن، الیاف، قیر، روکش و برند بستگی دارد. تشخیص ایزوگام مرغوب با ۱۲ کنترل، محاسبه‌ی تعداد رول با مثال و مقایسه‌ی قیمت اجرا.",
        },
        "en": {
            "slug": "isogam-membrane-price-guide",
            "title": "Isogam Membrane Price Guide: Cost Factors, Quality Checks and How Many Rolls You Need",
            "excerpt": "What sets the price of an Isogam membrane, the types and their uses, twelve quality checks, common market tricks, how to calculate the number of rolls and how to compare installation quotations.",
            "seo_title": "Isogam Membrane Price: Cost Factors and Roll Calculator | Asfaltbama",
            "seo_description": "Isogam membrane price depends on thickness, reinforcement, bitumen, finish and brand. Twelve quality checks, a roll calculator with examples and how to compare quotes.",
            "focus_keyword": "Isogam membrane price",
        },
        "ar": {
            "slug": "isogam-price-guide-ar",
            "title": "سعر الإيزوجام: عوامل التكلفة وفحص الجودة وحساب عدد اللفائف",
            "excerpt": "ما الذي يحدد سعر الإيزوجام، وأنواعه واستخداماتها، واثنا عشر فحصاً للجودة، وأساليب الغش الشائعة، وطريقة حساب عدد اللفائف، وكيف تقارن عروض التنفيذ.",
            "seo_title": "سعر الإيزوجام: عوامل التكلفة وحساب اللفائف | أسفلت با ما",
            "seo_description": "سعر الإيزوجام يعتمد على السماكة والتسليح والبيتومين والوجه والعلامة. اثنا عشر فحصاً للجودة، وحساب عدد اللفائف بأمثلة، ومقارنة عروض التنفيذ.",
            "focus_keyword": "سعر الإيزوجام",
        },
    },
    "roof-leak-after-isogam": {
        "fa": {
            "title": "نشتی پشت‌بام بعد از ایزوگام: ۱۲ علت رایج، روش پیدا کردن و تعمیر",
            "excerpt": "۱۲ علت رایج نشتی پشت‌بام بعد از ایزوگام با نشانه‌های هر کدام، روش گام‌به‌گام پیدا کردن محل نشت، وصله‌ی اصولی، تعمیر موضعی یا تعویض کامل، ضمانت و پیشگیری.",
            "seo_title": "نشتی پشت‌بام بعد از ایزوگام؛ ۱۲ علت و راه تعمیر | آسفالت با ما",
            "seo_description": "نشتی پشت‌بام بعد از ایزوگام معمولاً از درزها، لبه‌ها، ناودان یا پایه‌هاست. ۱۲ علت رایج، پیدا کردن محل نشت با آب‌ریزی، وصله‌ی اصولی و پیشگیری.",
        },
        "en": {
            "slug": "roof-leak-after-waterproofing",
            "title": "Roof Leaks After Waterproofing: 12 Causes, How to Find and Repair Them",
            "excerpt": "Twelve common causes of roof leaks after Isogam or bitumen waterproofing, how to locate the entry point step by step, how a proper patch is made, and when to re-waterproof instead of patching.",
            "seo_title": "Roof Leak After Waterproofing: 12 Causes and Repairs | Asfaltbama",
            "seo_description": "A roof leak after waterproofing usually comes from laps, upstands, drains or plinths. Learn 12 common causes, how to find the leak with a water test and how to repair it.",
            "focus_keyword": "roof leak after waterproofing",
        },
        "ar": {
            "slug": "roof-leak-after-waterproofing-ar",
            "title": "تسرب المياه من السطح بعد العزل: ١٢ سبباً وطريقة التشخيص والإصلاح",
            "excerpt": "اثنا عشر سبباً شائعاً لتسرب المياه من السطح بعد العزل بالإيزوجام أو القير، وكيف تحدد نقطة الدخول خطوة بخطوة، وكيف تُنفّذ الرقعة الصحيحة، ومتى تعيد العزل بدل الترقيع.",
            "seo_title": "تسرب المياه من السطح بعد العزل: ١٢ سبباً والحل | أسفلت با ما",
            "seo_description": "تسرب المياه من السطح بعد العزل يأتي غالباً من التراكبات والدروات والمصارف والقواعد. تعرّف على ١٢ سبباً وطريقة تحديد التسرب باختبار الماء والإصلاح الصحيح.",
            "focus_keyword": "تسرب المياه من السطح",
        },
    },
    "roof-waterproofing-methods": {
        "fa": {
            "excerpt": "ایزوگام، قیرگونی، عایق مایع، عایق سیمانی، ورق پلیمری و فوم پاششی در یک مقایسه‌ی کامل؛ انتخاب عایق برای هر نوع بام و اقلیم، عایق حرارتی، نقاط حساس، هزینه و نگهداری.",
            "seo_description": "عایق‌کاری پشت‌بام: مقایسه‌ی ایزوگام، قیرگونی، عایق مایع، سیمانی، ورق پلیمری و فوم پاششی، انتخاب برای هر نوع بام، عایق حرارتی، هزینه و نگهداری.",
        },
        "en": {
            "slug": "roof-waterproofing-systems",
            "title": "Roof Waterproofing Methods: Comparing Every Option for Gulf Roofs",
            "excerpt": "Membranes, bitumen and jute, liquid, cementitious, PVC/TPO and sprayed foam compared: the right system for each roof type and climate, thermal insulation and inverted roofs, details, cost and maintenance.",
            "seo_title": "Roof Waterproofing Methods Compared | Asfaltbama",
            "seo_description": "Roof waterproofing methods compared: Isogam membranes, bitumen and jute, liquid, cementitious, PVC/TPO and sprayed foam, with the right choice for Gulf roofs.",
            "focus_keyword": "roof waterproofing methods",
        },
        "ar": {
            "slug": "roof-waterproofing-systems-ar",
            "title": "طرق عزل الأسطح: مقارنة شاملة واختيار النظام المناسب",
            "excerpt": "الإيزوجام والقير والجنفاص والعزل السائل والإسمنتي والأغشية الصناعية والرغوة المرشوشة في مقارنة واحدة: النظام المناسب لكل سطح ومناخ، والعزل الحراري والسطح المقلوب، والتكلفة والصيانة.",
            "seo_title": "طرق عزل الأسطح: مقارنة شاملة | أسفلت با ما",
            "seo_description": "طرق عزل الأسطح: مقارنة بين الإيزوجام والقير والجنفاص والعزل السائل والإسمنتي والأغشية الصناعية والرغوة المرشوشة، والاختيار الصحيح لأسطح الخليج والعراق.",
            "focus_keyword": "عزل الأسطح",
        },
    },
    "bitumen-roofing-vs-isogam": {
        "fa": {
            "excerpt": "مقایسه‌ی قیرگونی و ایزوگام در ۱۲ معیار، رفتار در برابر عوامل تخریب، انتخاب برای بام روباز، موزاییکی، تراس، سرویس، پی و بام صنعتی، هزینه‌ی چرخه‌ی عمر و جدول تصمیم‌گیری.",
            "seo_description": "قیرگونی یا ایزوگام؟ مقایسه در ۱۲ معیار، انتخاب برای بام روباز، زیر موزاییک، سرویس، پی و بام صنعتی، هزینه‌ی واقعی و جدول تصمیم‌گیری سریع.",
        },
        "en": {
            "slug": "isogam-vs-bitumen-jute-waterproofing",
            "title": "Isogam Membrane or Bitumen and Jute? Choosing the Right Waterproofing",
            "excerpt": "Twelve criteria, six causes of deterioration, the right system for exposed roofs, tiled roofs, terraces, bathrooms, foundations and industrial roofs, life-cycle cost and a quick decision table.",
            "seo_title": "Isogam Membrane or Bitumen and Jute? Full Comparison | Asfaltbama",
            "seo_description": "Isogam membrane or bitumen and jute? A comparison on 12 criteria, the right choice for exposed and tiled roofs, wet areas, foundations and industrial roofs in the Gulf.",
            "focus_keyword": "Isogam membrane or bitumen and jute",
        },
        "ar": {
            "slug": "isogam-vs-bitumen-jute-ar",
            "title": "الإيزوجام أم القير والجنفاص؟ مقارنة شاملة ودليل الاختيار",
            "excerpt": "اثنا عشر معياراً وستة عوامل تلف، والنظام المناسب للسطح المكشوف والمبلّط والتراس والحمام والأساسات والأسطح الصناعية، مع تكلفة دورة الحياة وجدول قرار سريع.",
            "seo_title": "الإيزوجام أم القير والجنفاص؟ مقارنة شاملة | أسفلت با ما",
            "seo_description": "الإيزوجام أم القير والجنفاص؟ مقارنة في ١٢ معياراً، والاختيار الصحيح للأسطح المكشوفة والمبلطة والحمامات والأساسات والأسطح الصناعية في الخليج والعراق.",
            "focus_keyword": "الإيزوجام أم القير والجنفاص",
        },
    },
    "bitumen-roofing-steps": {
        "fa": {
            "excerpt": "انتخاب قیر و گونی، برآورد مصالح، ۱۴ مرحله‌ی اجرای قیرگونی دو لایه، جزئیات دیوار و ناودان، آزمایش آب‌بندی، لایه‌ی محافظ و تعمیر قیرگونی قدیمی.",
            "seo_description": "اجرای قیرگونی پشت‌بام در ۱۴ مرحله: انتخاب قیر و گونی، شیب‌بندی و ماهیچه‌کشی، پرایمر، دو لایه قیر و گونی، آزمایش آب‌بندی، لایه‌ی محافظ و تعمیر.",
        },
        "en": {
            "slug": "bitumen-jute-roof-waterproofing",
            "title": "Hot Bitumen and Jute Roof Waterproofing: A Complete Guide",
            "excerpt": "Choosing the bitumen and jute, preparing the deck, building two layers, detailing upstands and drains, flood testing and protecting the roof: the complete guide to built-up bituminous waterproofing.",
            "seo_title": "Bitumen and Jute Roofing: Materials, Steps, Checks | Asfaltbama",
            "seo_description": "Hot bitumen and jute roofing step by step: bitumen grades, jute, primer, the two-layer build-up, upstands, flood test, protection and repairs.",
            "focus_keyword": "bitumen and jute roofing",
        },
        "ar": {
            "slug": "bitumen-jute-waterproofing-ar",
            "title": "العزل بالقير والجنفاص: المواد والمراحل والتفاصيل",
            "excerpt": "اختيار القير والجنفاص، وتجهيز السطح، وبناء طبقتين، وتفاصيل الدروات والمصارف، واختبار الغمر وحماية السطح: الدليل الكامل للعزل البيتوميني المنفّذ في الموقع.",
            "seo_title": "العزل بالقير والجنفاص: دليل التنفيذ الكامل | أسفلت با ما",
            "seo_description": "العزل بالقير والجنفاص خطوة بخطوة: درجات القير والجنفاص والبرايمر وبناء طبقتين ورفع العزل على الدروات واختبار الغمر والحماية والإصلاح.",
            "focus_keyword": "العزل بالقير والجنفاص",
        },
    },
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


CATEGORY = {
    "asphalt-paving": ("Asphalt paving", "رصف الأسفلت"),
    "excavation-grading": ("Excavation & grading", "الحفر وتسوية الأراضي"),
    "waterproofing-isogam": ("Waterproofing", "العزل المائي"),
    "joint-sealing": ("Crack sealing", "سدّ الشقوق"),
    "cost-estimation": ("Cost estimation", "تقدير التكاليف"),
    "demolition": ("Demolition", "الهدم"),
    "machinery-rental": ("Machinery", "المعدات"),
    "bitumen": ("Bitumen", "البيتومين"),
    "contracts-warranty": ("Contracts & warranty", "العقود والضمان"),
}


def featured_images(manifest, pages_by_lang):
    """Give each English/Arabic article a featured image: the Persian
    article's photo when it has one, otherwise a cover in its own
    language (images/covers/intl/<page-slug>.webp, rendered by
    tools/render_intl_covers.py). Returns the covers that are needed."""
    covers = []
    images = manifest["images"]
    for fa_slug, versions in pages_by_lang.items():
        # A project photo wins over a branded Persian cover.
        candidates = [i for i in images if fa_slug in i.get("featured_for", [])]
        source = next((i for i in candidates if "/covers/" not in i["file"]), candidates[0] if candidates else None)
        for lang, page in versions.items():
            if source and "/covers/" not in source["file"]:
                if page["slug"] not in source["featured_for"]:
                    source["featured_for"].append(page["slug"])
                continue
            file = f"images/covers/intl/{page['slug']}.webp"
            entry = next((i for i in images if i["file"] == file), None)
            if not entry:
                entry = {"file": file, "featured_for": []}
                images.append(entry)
            entry.update({"title": page["title"], "alt": page["title"]})
            if page["slug"] not in entry["featured_for"]:
                entry["featured_for"].append(page["slug"])
            cat = CATEGORY.get(page["category"], ("Guides", "أدلة"))
            covers.append({"file": page["slug"], "lang": lang, "title": page["title"],
                           "cat": cat[1] if lang == "ar" else cat[0]})
    return covers


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

    made = {}
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
            made.setdefault(slug, {})[lang] = {"slug": v["slug"], "title": v["title"], "category": post["category"]}
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

    covers = featured_images(manifest, made)
    MANIFEST.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (ROOT / "tools" / "intl_covers.json").write_text(json.dumps(covers, ensure_ascii=False, indent=1), encoding="utf-8")


if __name__ == "__main__":
    main()
