#!/usr/bin/env python3
"""Build an outreach pack from site-review --score output.

  python3 lead-pack.py scores.txt [more.txt ...] --names names.tsv --max 60 --out DIR

For every site scoring below 60 it:
  * reads the public contact details the business publishes on its own site
    (home + contact page: tel:/wa.me links, phone numbers, emails),
  * writes a personal Arabic message built from that site's real top issue,
  * adds a one-tap WhatsApp link when a public mobile/WhatsApp number exists.
Nothing is sent. The output is a Markdown table + per-lead messages to review.
"""
import argparse, json, os, re, subprocess, sys
from urllib.parse import quote

UA = "Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 Chrome/126 Mobile Safari/537.36"
CC = {".iq": "964", ".om": "968", ".ae": "971", ".sa": "966", ".qa": "974", ".kw": "965", ".bh": "973", ".lb": "961"}
COUNTRY = {"964": "العراق", "968": "عُمان", "971": "الإمارات", "966": "السعودية", "974": "قطر",
           "965": "الكويت", "973": "البحرين", "961": "لبنان"}

# Arabic phrasing of each issue the scorer reports (subject: "موقعكم").
ISSUE_AR = [
    (r"No HTTPS", "أن الموقع لا يعمل بقفل الأمان HTTPS، فيظهر للزائر تحذير «غير آمن» في المتصفح"),
    (r"Very slow on mobile \(([\d.]+)s\)", "أن الصفحة الرئيسية احتاجت في اختبارنا نحو {0} ثانية لتفتح على الجوال، وكثير من الزوار يغادرون قبل ذلك"),
    (r"Slow on mobile \(([\d.]+)s\)", "أن الصفحة الرئيسية احتاجت في اختبارنا نحو {0} ثانية لتفتح على الجوال"),
    (r"No one-tap WhatsApp", "أن الصفحة الرئيسية لا تحتوي زر واتساب أو اتصال بلمسة واحدة، والعميل في المنطقة يفضّل التواصل عبر واتساب"),
    (r"No Arabic version", "أنه لا توجد نسخة عربية من الموقع، فيصعب ظهوركم لمن يبحث بالعربية"),
    (r"Arabic content not marked", "أن المحتوى العربي غير معرّف كعربي في كود الصفحة، فقد لا يعرضه Google في البحث العربي"),
    (r"Not mobile friendly|overflow|horizontal", "أن الصفحة لا تتناسب مع شاشة الجوال"),
    (r"Missing or weak page title", "أن عنوان الصفحة في Google ضعيف ولا يذكر خدمتكم ومدينتكم"),
    (r"No meta description|page description", "أنه لا يوجد وصف للصفحة، فيكتب Google مقتطفاً عشوائياً"),
    (r"Weak local signals", "أن الموقع غير مربوط بخرائط Google، فيصعب ظهوركم في نتائج «بالقرب مني»"),
    (r"(\d+) images without alt", "أن {0} صورة بلا وصف نصي، فلا يفهمها Google"),
    (r"H1", "أن العنوان الرئيسي للصفحة مفقود أو مكرر"),
    (r"copyright|outdated", "أن تاريخ الموقع قديم، فيبدو للزائر وكأنه غير محدّث"),
]
SKIP = re.compile(r"(gov\.|\.gov|jobs?|careers|recruit|manpower|hr-|hrgate|talent|university|college|"
                  r"school|ministry|marriott|millennium|neom|news|unitad|undp|giz)", re.I)


def fetch(url):
    try:
        return subprocess.run(["curl", "-sL", "-m", "20", "-A", UA, url], capture_output=True,
                              text=True, errors="ignore").stdout
    except Exception:
        return ""


def cc_for(domain):
    for tld, cc in CC.items():
        if domain.endswith(tld):
            return cc
    return None


def norm_phone(raw, cc):
    d = re.sub(r"\D", "", raw)
    if d.startswith("00"):
        d = d[2:]
    if cc and d.startswith("0") and not d.startswith("00"):
        d = cc + d[1:]
    if cc and len(d) <= 9 and not d.startswith(cc):
        d = cc + d
    if re.search(r"1234567|0000000|(\d)\1{6}", d):
        return None
    return d if 10 <= len(d) <= 15 and d[:3] in COUNTRY else None


def is_mobile(d):
    rules = {"964": r"^9647\d{9}$", "968": r"^968[79]\d{7}$", "971": r"^9715\d{8}$", "966": r"^9665\d{8}$",
             "974": r"^974[3567]\d{7}$", "965": r"^965[569]\d{7}$", "973": r"^973[36]\d{7}$", "961": r"^961(3|7[016-9]|81)\d{6}$"}
    return any(re.match(p, d) for p in rules.values())


def contacts(domain):
    html = fetch(f"https://{domain}") or fetch(f"http://{domain}")
    links = re.findall(r'href=["\']([^"\']*(?:contact|اتصل|تواصل)[^"\']*)["\']', html, re.I)[:2]
    for l in links:
        if l.startswith("/"):
            l = f"https://{domain}{l}"
        if l.startswith("http") and domain in l:
            html += fetch(l)
    cc = cc_for(domain)
    wa, phones = [], []
    for m in re.findall(r"(?:wa\.me/|api\.whatsapp\.com/send\?phone=|whatsapp://send\?phone=)\+?(\d{8,15})", html):
        p = norm_phone(m, cc)
        if p and p not in wa:
            wa.append(p)
    for m in re.findall(r'tel:([+\d\s()-]{7,20})', html) + re.findall(r'(?<![\d/=.])(\+?(?:00)?9[67]\d[\d\s-]{7,13})(?!\d)', html):
        p = norm_phone(m, cc)
        if p and p not in phones and p not in wa:
            phones.append(p)
    emails = []
    for e in re.findall(r"[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}", html):
        e = e.lower().rstrip(".")
        if e.split("@")[1].endswith(domain.split(".", 1)[-1] if domain.count(".") > 1 else domain) or domain in e:
            if not re.search(r"\.(png|jpg|webp|svg)$", e) and e not in emails:
                emails.append(e)
    return wa, phones, emails[:2]


def issues_ar(raw, has_wa=False):
    out = []
    for part in [p.strip() for p in raw.split(";")]:
        if has_wa and 'WhatsApp' in part:
            continue
        for pat, ar in ISSUE_AR:
            m = re.search(pat, part, re.I)
            if m:
                out.append(ar.format(*m.groups()))
                break
    return out


def message(name, domain, issues):
    first = issues[0] if issues else "عدة نقاط تمنع الموقع من جلب عملاء من Google"
    extra = f"\nوأيضاً {issues[1]}." if len(issues) > 1 else ""
    return (f"السلام عليكم فريق {name}،\n"
            f"تصفحت موقعكم {domain} من الجوال، ولاحظت {first}.{extra}\n\n"
            "جهزنا لكم مراجعة مجانية مختصرة فيها أهم 5 إصلاحات مرتبة حسب الأولوية، "
            "كل إصلاح منها يعني عملاء أكثر يصلون إليكم من Google.\n"
            "هل أرسلها لكم هنا؟ بلا أي التزام.\n\n"
            "مهدي — MEFTACARD Studio\nmeftacard.com/ar/studio")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("scores", nargs="+")
    ap.add_argument("--names", help="TSV: domain<TAB>company name")
    ap.add_argument("--max", type=int, default=60)
    ap.add_argument("--threshold", type=int, default=60)
    ap.add_argument("--out", default="pack")
    a = ap.parse_args()
    names = {}
    if a.names and os.path.exists(a.names):
        for line in open(a.names, encoding="utf-8"):
            if "\t" in line:
                d, n = line.rstrip("\n").split("\t", 1)
                names[d.strip()] = n.strip()
    rows = []
    for f in a.scores:
        for line in open(f, encoding="utf-8", errors="ignore"):
            m = re.match(r"\s*(\d+)\s*\|\s*([^|]+?)\s*\|\s*(.*)", line)
            if m and int(m.group(1)) < a.threshold and not SKIP.search(m.group(2)):
                src = "964" if os.path.basename(f).startswith("iq") else "968" if os.path.basename(f).startswith("om") else None
                rows.append((int(m.group(1)), m.group(2), m.group(3), src))
    seen, leads = set(), []
    for score, domain, raw, src in sorted(rows):
        if domain in seen:
            continue
        seen.add(domain)
        wa, phones, emails = contacts(domain)
        if not (wa or phones or emails):
            continue
        name = names.get(domain, domain.split(".")[0].replace("-", " ").title())
        iss = issues_ar(raw, has_wa=bool(wa))
        msg = message(name, domain, iss)
        target = wa[0] if wa else next((p for p in phones if is_mobile(p)), None)
        leads.append(dict(src=src or cc_for(domain), score=score, domain=domain, name=name, issues_en=raw, issues_ar=iss,
                          whatsapp=wa, phones=phones, emails=emails, wa_target=target,
                          wa_link=f"https://wa.me/{target}?text={quote(msg)}" if target else None,
                          message=msg))
        print(f"{score:3d} {domain:32s} wa={wa[:1]} tel={phones[:1]} mail={emails[:1]}", file=sys.stderr)
        if len(leads) >= a.max:
            break
    os.makedirs(a.out, exist_ok=True)
    json.dump(leads, open(os.path.join(a.out, "leads.json"), "w"), ensure_ascii=False, indent=1)
    md = ["# بسته پیام‌رسانی — شرکت‌های با سایت ضعیف\n",
          "امتیاز از ۱۰۰ (پایین‌تر = نیاز بیشتر). فقط اطلاعات تماس عمومی که خود شرکت روی سایتش گذاشته.\n",
          "| # | شرکت | سایت | امتیاز | واتساپ یک‌لمسی | تلفن | ایمیل |", "|---|---|---|---|---|---|---|"]
    for i, l in enumerate(leads, 1):
        cc = (l["wa_target"] or (l["phones"] or [""])[0])[:3] or l["src"] or ""
        md.append(f"| {i} | {l['name']} ({COUNTRY.get(cc, '—')}) | {l['domain']} | {l['score']} | "
                  f"{'[ارسال](' + l['wa_link'] + ')' if l['wa_link'] else '—'} | "
                  f"{'+' + l['phones'][0] if l['phones'] else '—'} | {l['emails'][0] if l['emails'] else '—'} |")
    md.append("\n---\n")
    for i, l in enumerate(leads, 1):
        md.append(f"## {i}. {l['name']} — {l['domain']} (امتیاز {l['score']})\n")
        md.append("```\n" + l["message"] + "\n```\n")
    open(os.path.join(a.out, "PACK.md"), "w", encoding="utf-8").write("\n".join(md))
    print(f"{len(leads)} leads -> {a.out}/PACK.md", file=sys.stderr)


if __name__ == "__main__":
    main()
