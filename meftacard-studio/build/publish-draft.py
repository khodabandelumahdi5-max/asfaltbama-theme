#!/usr/bin/env python3
"""Save a MEFTACARD Studio article as a WordPress DRAFT, ready for review.

Usage: python3 meftacard-studio/build/publish-draft.py meftacard-studio/articles/<lang>/<slug>.md [...]

For each Markdown file (front matter as in WRITING-GUIDE.md) it:
  1. renders a branded 1200x630 cover image (Playwright) and uploads it with alt text,
  2. converts the body to HTML (drops the leading H1, which WordPress shows as the title),
  3. creates or updates the post as status=draft in category 41 (EN) / 42 (AR),
     with the cover at the top, featured image, and country links at the end,
  4. writes Rank Math SEO title, description and focus keywords.
Authentication to meftacard.com is handled by the environment's proxy.
"""
import html, json, os, re, subprocess, sys, tempfile, time

API = 'https://meftacard.com/wp-json'
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CATS = {'en': 41, 'ar': 42}
COUNTRIES = {
    'ar': [('iraq', 'العراق'), ('uae', 'الإمارات'), ('saudi-arabia', 'السعودية'), ('qatar', 'قطر'), ('kuwait', 'الكويت'), ('oman', 'عُمان'), ('bahrain', 'البحرين'), ('lebanon', 'لبنان')],
    'en': [('iraq', 'Iraq'), ('uae', 'UAE'), ('saudi-arabia', 'Saudi Arabia'), ('qatar', 'Qatar'), ('kuwait', 'Kuwait'), ('oman', 'Oman'), ('bahrain', 'Bahrain'), ('lebanon', 'Lebanon')],
}

try:
    import markdown
except ImportError:
    subprocess.run([sys.executable, '-m', 'pip', 'install', '-q', 'markdown'], check=True)
    import markdown


def req(method, path, data=None, raw_file=None, headers=()):
    cmd = ['curl', '-s', '-m', '120', '-X', method, API + path]
    for h in headers:
        cmd += ['-H', h]
    if raw_file:
        cmd += ['--data-binary', '@' + raw_file]
    elif data is not None:
        cmd += ['-H', 'Content-Type: application/json', '--data-binary', '@-']
    for attempt in range(4):
        r = subprocess.run(cmd, input=json.dumps(data) if data is not None and not raw_file else None, capture_output=True, text=True)
        try:
            return json.loads(r.stdout)
        except Exception:
            time.sleep(3 * (attempt + 1))
    raise RuntimeError(f'API call failed: {method} {path}: {r.stdout[:300]}')


def parse(path):
    text = open(path, encoding='utf-8').read()
    _, fm, body = text.split('---', 2)
    meta = {}
    for line in fm.strip().splitlines():
        if ':' not in line:
            continue
        k, v = line.split(':', 1)
        v = v.split(' #')[0].strip()
        meta[k.strip()] = json.loads(v) if v.startswith(('"', '[')) else v
    for k in ('lang', 'slug', 'title', 'seo_title', 'meta_description', 'focus_keyword'):
        if not meta.get(k):
            raise SystemExit(f'{path}: missing front matter field "{k}"')
    if len(meta['seo_title']) > 62:
        print(f'  ! seo_title is {len(meta["seo_title"])} chars (aim for ≤ 60)')
    if not 110 <= len(meta['meta_description']) <= 160:
        print(f'  ! meta_description is {len(meta["meta_description"])} chars (aim for 130–155)')
    return meta, re.sub(r'^\s*# .*\n', '', body, count=1)


def cover(meta):
    """Render the branded cover with the same design as the existing article covers."""
    mark = ''.join(l for l in open(os.path.join(ROOT, 'brand/mark.svg'), encoding='utf-8') if '<path' in l)
    ar = meta['lang'] == 'ar'
    kicker = meta.get('kicker') or ('MEFTACARD Studio' if not ar else 'MEFTACARD Studio')
    t = html.escape(meta['title'])
    page = f'''<!doctype html><html><head><meta charset="utf-8"><link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@500;600;700&family=IBM+Plex+Sans+Arabic:wght@600;700&display=swap" rel="stylesheet"></head>
<body style="margin:0"><div id="c" dir="{'rtl' if ar else 'ltr'}" style="width:1200px;height:630px;background:#FAFAF8;position:relative;box-sizing:border-box;padding:72px 84px;display:flex;flex-direction:column;justify-content:space-between;font-family:{"'IBM Plex Sans Arabic'" if ar else "'Inter Tight'"}">
<div style="display:flex;align-items:center;gap:14px;font:700 26px 'Inter Tight';color:#16171A;direction:ltr"><svg viewBox="0 4 100 64" width="64" fill="#16171A">{mark}</svg>meftacard <span style="font-weight:500;color:#5C5F66">Studio</span></div>
<div><div style="font-size:{60 if len(meta['title']) < 60 else 50}px;font-weight:700;line-height:{1.3 if ar else 1.08};letter-spacing:{0 if ar else '-.03em'};color:#16171A;max-width:1000px">{t}</div></div>
<div style="position:absolute;bottom:0;{'right' if ar else 'left'}:84px;width:140px;height:10px;background:#2BD4A4"></div></div></body></html>'''
    tmp = tempfile.mkdtemp()
    src, png = os.path.join(tmp, 'c.html'), os.path.join(tmp, 'c.png')
    open(src, 'w', encoding='utf-8').write(page)
    js = ("const {chromium}=require('playwright');(async()=>{const b=await chromium.launch();const p=await b.newPage({viewport:{width:1200,height:630}});"
          f"await p.goto('file://{src}',{{waitUntil:'networkidle'}});await p.locator('#c').screenshot({{path:'{png}'}});await b.close()}})()")
    subprocess.run(['node', '-e', js], check=True)
    out = os.path.join(tmp, f"cover-{meta['slug']}.webp")
    from PIL import Image
    Image.open(png).convert('RGB').save(out, 'WEBP', quality=82, method=6)
    return out


def main(path):
    meta, body = parse(path)
    lang = meta['lang']
    print(f'→ {meta["slug"]} ({lang})')
    img = cover(meta)
    media = req('POST', '/wp/v2/media', raw_file=img, headers=[f'Content-Disposition: attachment; filename={os.path.basename(img)}', 'Content-Type: image/webp'])
    req('POST', f"/wp/v2/media/{media['id']}", {'alt_text': meta['title'], 'title': meta['title']})

    body_html = markdown.markdown(body, extensions=['tables', 'fenced_code', 'sane_lists'])
    body_html = body_html.replace('<table>', '<figure class="wp-block-table"><table>').replace('</table>', '</table></figure>')
    pre = '/ar' if lang == 'ar' else ''
    links = ' · '.join(f'<a href="https://meftacard.com{pre}/studio/{s}/">{n}</a>' for s, n in COUNTRIES[lang])
    block = (f'<h2>خدماتنا حسب الدولة</h2>\n<p>تصميم مواقع وسيو ودعم المواقع ووكلاء الذكاء الاصطناعي، أونلاين بالكامل ودون حاجة للحضور، والدفع بعملة تيثر (USDT): {links}.</p>\n<p>تابع <a href="https://t.me/meftacardchannel">قناتنا على تيليغرام</a> لأحدث المقالات.</p>'
             if lang == 'ar' else
             f'<h2>Our services by country</h2>\n<p>Website design, SEO, website support and AI agents, fully online with no visit needed, paid in Tether (USDT): {links}.</p>\n<p>Follow our <a href="https://t.me/meftacardchannel">Telegram channel</a> for new guides.</p>')
    fig = f'<!--mc-cover--><figure class="wp-block-image size-large"><img src="{media["source_url"]}" alt="{html.escape(meta["title"])}" width="1200" height="630" decoding="async" fetchpriority="high"/></figure><!--/mc-cover-->'
    content = f'<div lang="{lang}" dir="{"rtl" if lang == "ar" else "ltr"}">\n{fig}\n{body_html}\n<!--mc-countries-->\n{block}\n</div>'

    existing = req('GET', f"/wp/v2/posts?slug={meta['slug']}&status=draft,publish,pending,private&context=edit&_fields=id,status")
    if existing and existing[0]['status'] == 'publish':
        raise SystemExit(f"  ✗ {meta['slug']} is already published; not touching it")
    data = {'title': meta['title'], 'slug': meta['slug'], 'content': content, 'status': 'draft',
            'categories': [CATS[lang]], 'excerpt': meta['meta_description'], 'featured_media': media['id']}
    post = req('POST', f"/wp/v2/posts/{existing[0]['id']}" if existing else '/wp/v2/posts', data)
    if not post.get('id'):
        raise SystemExit(f'  ✗ could not save post: {str(post)[:300]}')
    kw = ','.join([meta['focus_keyword']] + [k for k in meta.get('secondary_keywords', []) if k != meta['focus_keyword']][:4])
    req('POST', '/rankmath/v1/updateMeta', {'objectType': 'post', 'objectID': post['id'], 'meta': {
        'rank_math_title': meta['seo_title'], 'rank_math_description': meta['meta_description'], 'rank_math_focus_keyword': kw}})
    words = len(re.sub(r'<[^>]+>', ' ', body_html).split())
    print(f"  ✓ draft saved: id={post['id']} status={post['status']} words≈{words} edit: https://meftacard.com/wp-admin/post.php?post={post['id']}&action=edit")
    return post['id']


if __name__ == '__main__':
    if len(sys.argv) < 2:
        raise SystemExit(__doc__)
    for p in sys.argv[1:]:
        main(p)
