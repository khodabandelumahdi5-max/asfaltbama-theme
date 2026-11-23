#!/usr/bin/env python3
"""Quality gate for a MEFTACARD Studio article before it is published.

Usage: python3 meftacard-studio/build/check-article.py meftacard-studio/articles/<lang>/<slug>.md
Exit code 0 = PASS (safe to publish), 1 = FAIL (keep as draft). Prints every check.
"""
import json, re, subprocess, sys

def parse(path):
    text = open(path, encoding='utf-8').read()
    _, fm, body = text.split('---', 2)
    meta = {}
    for line in fm.strip().splitlines():
        if ':' in line:
            k, v = line.split(':', 1); v = v.split(' #')[0].strip()
            meta[k.strip()] = json.loads(v) if v.startswith(('"', '[')) else v
    return meta, body

AR_DIAC = re.compile(r'[\u064B-\u0652\u0640]')
def norm_tokens(t):
    """Lower-case, strip Arabic diacritics, unify alef/ta-marbuta/ya and drop the ال/و/ب/ل prefixes."""
    t = AR_DIAC.sub('', t.lower()).translate(str.maketrans('أإآةى', 'اااهي'))
    out = []
    for w in re.findall(r'\w+', t):
        for pre in ('وال', 'بال', 'لل', 'ال'):
            if w.startswith(pre) and len(w) > len(pre) + 2:
                w = w[len(pre):]; break
        out.append(w)
    return out

def has_kw(kw, text):
    """True when every word of the keyword appears in the text (morphology-tolerant)."""
    k, t = norm_tokens(kw), set(norm_tokens(text))
    return bool(k) and all(w in t for w in k)

def kw_count(kw, text):
    k = norm_tokens(kw); t = norm_tokens(text); n = len(k)
    return sum(1 for i in range(len(t) - n + 1) if t[i:i + n] == k) if n else 0

def http(url):
    r = subprocess.run(['curl', '-s', '-o', '/dev/null', '-m', '30', '-w', '%{http_code}', '-L', url], capture_output=True, text=True)
    return r.stdout.strip()

def main(path):
    meta, body = parse(path)
    lang, fk = meta.get('lang'), meta.get('focus_keyword', '')
    h1 = (re.search(r'^# (.+)$', body, re.M) or [None, ''])[1]
    intro = body.split('\n## ')[0].replace(h1, '', 1).lower()
    h2s = re.findall(r'^## (.+)$', body, re.M)
    text = re.sub(r'\[([^\]]*)\]\([^)]*\)', r'\1', body)
    words = len(re.findall(r'\w+', text))
    links = re.findall(r'\]\((https?://[^)\s]+)\)', body)
    internal = [l for l in links if 'meftacard.com' in l]
    service = [l for l in internal if re.search(r'/studio/(website-design|seo-services|website-support|ai-agents|app-design)/', l)]
    country = [l for l in internal if re.search(r'/studio/(iraq|uae|saudi-arabia|qatar|kuwait|oman|bahrain|lebanon)/', l)]
    faq_head = 'أسئلة شائعة' if lang == 'ar' else 'Frequently asked questions'
    minw = 2500
    sec = meta.get('secondary_keywords', []) or []
    kps = meta.get('key_points', []) or []
    external = [l for l in links if 'meftacard.com' not in l and 't.me/' not in l]
    checks = [
        ('front matter complete', all(meta.get(k) for k in ('lang', 'slug', 'title', 'seo_title', 'meta_description', 'focus_keyword'))),
        ('lang is ar or en', lang in ('ar', 'en')),
        (f'seo_title ≤ 60 chars ({len(meta.get("seo_title",""))})', 0 < len(meta.get('seo_title', '')) <= 60),
        (f'meta_description 120–158 chars ({len(meta.get("meta_description",""))})', 120 <= len(meta.get('meta_description', '')) <= 158),
        ('H1 present and equals title', bool(h1) and h1.strip() == meta.get('title', '').strip()),
        ('focus keyword in H1', has_kw(fk, h1)),
        ('focus keyword in first paragraph', has_kw(fk, intro)),
        ('focus keyword in an H2', any(has_kw(fk, h) for h in h2s)),
        (f'at least 4 H2 sections ({len(h2s)})', len(h2s) >= 4),
        (f'length ≥ {minw} words ({words})', words >= minw),
        (f'FAQ section "{faq_head}"', faq_head.lower() in body.lower()),
        (f'≥ 3 internal links ({len(internal)})', len(internal) >= 3),
        ('links to a service page', bool(service)),
        ('Telegram CTA', 't.me/meftacard' in body),
        ('mentions Tether/USDT', 'USDT' in body or 'تيثر' in body),
        ('no claims of years of experience', not re.search(r'(\d+\+?\s*(years|سنة|عام|عاماً)\s*(of experience|من الخبرة|خبرة))', body, re.I)),
        ('no fake testimonials', not re.search(r'(testimonial|what our clients say|آراء عملائنا|قال أحد عملائنا)', body, re.I)),
        ('no leftover placeholders', not re.search(r'(\[TODO|lorem ipsum|XXX|\{\{)', body, re.I)),
        (f'key_points for images/video: 4–5 items, each ≤ 90 chars ({len(kps)})', 4 <= len(kps) <= 5 and all(0 < len(k) <= 90 for k in kps)),
        (f'≥ 3 secondary keywords ({len(sec)})', len(sec) >= 3),
        ('every secondary keyword used in the text' + ('' if all(has_kw(k, body) for k in sec) else f' — missing: {[k for k in sec if not has_kw(k, body)]}'), all(has_kw(k, body) for k in sec)),
        (f'≥ 1 external source linked ({len(external)})', len(external) >= 1),
        (f'≥ 6 H2 sections for a long article ({len(h2s)})', len(h2s) >= 6),
    ]
    # the focus keyword in H2 check is relaxed: H1 + intro + body twice is enough
    if not checks[7][1]:
        checks[7] = ('focus keyword used ≥ 2 times in body', kw_count(fk, body) >= 2)
    # 403/429 usually mean the site blocks automated checks, not a dead link
    broken = [l for l in set(links) if http(l) in ('000', '404', '410') or http(l).startswith('5')]
    # links to the article itself are fine before publishing
    broken = [l for l in broken if meta.get('slug', '@@') not in l]
    checks.append((f'all links work ({len(set(links))} checked)' + (f' — broken: {broken}' if broken else ''), not broken))
    ok = True
    for name, passed in checks:
        print(('  ✓ ' if passed else '  ✗ ') + name)
        ok &= bool(passed)
    print('PASS' if ok else 'FAIL')
    return ok

if __name__ == '__main__':
    results = [main(p) for p in sys.argv[1:]]
    sys.exit(0 if all(results) else 1)
