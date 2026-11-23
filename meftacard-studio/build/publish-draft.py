#!/usr/bin/env python3
"""Build media, run the quality gate and save a MEFTACARD Studio article to WordPress.

Usage:
  python3 meftacard-studio/build/publish-draft.py <article.md> [...]            # save as draft
  python3 meftacard-studio/build/publish-draft.py --publish <article.md> [...]  # publish if the gate passes
  python3 meftacard-studio/build/publish-draft.py --upgrade <article.md>        # upgrade a published article (gate must pass)

For each Markdown file (front matter as in WRITING-GUIDE.md) it:
  1. runs build/check-article.py; with --publish a FAIL keeps the post as a draft,
  2. renders a cover (1200x630), a key-points image (1200x675) and a ~30 s MP4 video (1280x720)
     from the front-matter `key_points`, uploads them with alt text,
  3. converts the body to HTML, inserts the key-points image before the first H2 and the video
     (with VideoObject structured data) before the FAQ, plus service and country links,
  4. creates/updates the post (category 41 EN / 42 AR, featured image, Rank Math meta),
  5. after publishing, re-checks the live page (HTTP 200, one H1, language, images with alt, video).
Publishing fires the site's automatic Telegram post. Auth to meftacard.com comes from the proxy.
"""
import html, json, os, re, shutil, subprocess, sys, tempfile, time
from datetime import datetime, timezone

API = 'https://meftacard.com/wp-json'
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CATS = {'en': 41, 'ar': 42}
COUNTRIES = {
    'ar': [('iraq', 'العراق'), ('uae', 'دبي والإمارات'), ('saudi-arabia', 'السعودية'), ('oman', 'عُمان'), ('qatar', 'قطر'), ('kuwait', 'الكويت'), ('bahrain', 'البحرين'), ('lebanon', 'لبنان')],
    'en': [('iraq', 'Iraq'), ('uae', 'Dubai & UAE'), ('saudi-arabia', 'Saudi Arabia'), ('oman', 'Oman'), ('qatar', 'Qatar'), ('kuwait', 'Kuwait'), ('bahrain', 'Bahrain'), ('lebanon', 'Lebanon')],
}
FONTS = '<link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@500;600;700&family=IBM+Plex+Sans+Arabic:wght@500;600;700&display=swap" rel="stylesheet">'

try:
    import markdown
except ImportError:
    subprocess.run([sys.executable, '-m', 'pip', 'install', '-q', 'markdown'], check=True)
    import markdown
from PIL import Image


def req(method, path, data=None, raw_file=None, headers=()):
    cmd = ['curl', '-s', '-m', '300', '-X', method, API + path]
    for h in headers:
        cmd += ['-H', h]
    if raw_file:
        cmd += ['--data-binary', '@' + raw_file]
    elif data is not None:
        cmd += ['-H', 'Content-Type: application/json', '--data-binary', '@-']
    out = ''
    for attempt in range(4):
        r = subprocess.run(cmd, input=json.dumps(data) if data is not None and not raw_file else None, capture_output=True, text=True)
        out = r.stdout
        try:
            return json.loads(out)
        except Exception:
            time.sleep(4 * (attempt + 1))
    raise RuntimeError(f'API call failed: {method} {path}: {out[:300]}')


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
    return meta, re.sub(r'^\s*# .*\n', '', body, count=1)


def mark_svg():
    return ''.join(l for l in open(os.path.join(ROOT, 'brand/mark.svg'), encoding='utf-8') if '<path' in l)


def shoot(html_doc, ids, size, tmp):
    """Render elements with the given ids from one HTML document to PNG files."""
    src = os.path.join(tmp, f'm-{ids[0]}.html')
    open(src, 'w', encoding='utf-8').write(html_doc)
    js = ("const {chromium}=require('playwright');(async()=>{const b=await chromium.launch();"
          f"const p=await b.newPage({{viewport:{{width:{size[0]},height:{size[1]}}}}});"
          f"await p.goto('file://{src}',{{waitUntil:'networkidle'}});"
          f"for(const id of {json.dumps(ids)}) await p.locator('#'+id).screenshot({{path:'{tmp}/'+id+'.png'}});"
          "await b.close()})()")
    subprocess.run(['node', '-e', js], check=True)
    return [os.path.join(tmp, i + '.png') for i in ids]


def to_webp(png, out, q=82):
    Image.open(png).convert('RGB').save(out, 'WEBP', quality=q, method=6)
    return out


def render_media(meta, tmp):
    ar = meta['lang'] == 'ar'
    d, font = ('rtl' if ar else 'ltr'), ("'IBM Plex Sans Arabic'" if ar else "'Inter Tight'")
    mark, t = mark_svg(), html.escape(meta['title'])
    pts = [html.escape(p) for p in meta['key_points'][:5]]
    brand = f'<div style="display:flex;align-items:center;gap:14px;font:700 26px \'Inter Tight\';direction:ltr"><svg viewBox="0 4 100 64" width="64" fill="currentColor">{mark}</svg>meftacard <span style="font-weight:500;opacity:.6">Studio</span></div>'
    # cover 1200x630
    cover = f'''<div id="cover" dir="{d}" style="width:1200px;height:630px;background:#FAFAF8;color:#16171A;position:relative;box-sizing:border-box;padding:72px 84px;display:flex;flex-direction:column;justify-content:space-between;font-family:{font}">{brand}
<div style="font-size:{60 if len(meta['title']) < 60 else 50}px;font-weight:700;line-height:{1.3 if ar else 1.08};letter-spacing:{0 if ar else '-.03em'};max-width:1000px">{t}</div>
<div style="position:absolute;bottom:0;{'right' if ar else 'left'}:84px;width:140px;height:10px;background:#2BD4A4"></div></div>'''
    head = 'أهم النقاط' if ar else 'Key points'
    kp = f'''<div id="kp" dir="{d}" style="width:1200px;height:675px;background:#16171A;color:#F4F3EF;box-sizing:border-box;padding:60px 72px;display:flex;flex-direction:column;justify-content:center;gap:30px;font-family:{font}">
<div style="display:flex;justify-content:space-between;align-items:center"><div style="font-size:40px;font-weight:700">{head}</div><span style="color:#2BD4A4">{brand}</span></div>
{''.join(f'<div style="display:flex;gap:18px;align-items:flex-start;font-size:{26 if len(p) < 70 else 22}px;line-height:1.45"><span style="flex:none;width:44px;height:44px;border-radius:50%;background:#2BD4A4;color:#0B1E2A;display:grid;place-items:center;font:700 20px Inter Tight">{i+1}</span><span>{p}</span></div>' for i, p in enumerate(pts))}</div>'''
    wrap = lambda x: f'<!doctype html><html><head><meta charset="utf-8">{FONTS}</head><body style="margin:0">{x}</body></html>'
    cpng = shoot(wrap(cover), ['cover'], (1200, 630), tmp)[0]       # one element per page: no scroll/clipping issues
    kpng = shoot(wrap(kp), ['kp'], (1200, 675), tmp)[0]
    # video slides 1280x720
    cta = ('أونلاين بالكامل · الدفع بعملة تيثر (USDT)' if ar else 'Fully online · Pay in Tether (USDT)')
    slides = [f'<div id="s0" dir="{d}" style="width:1280px;height:720px;background:#FAFAF8;color:#16171A;box-sizing:border-box;padding:80px 96px;display:flex;flex-direction:column;justify-content:space-between;font-family:{font}">{brand}<div style="font-size:58px;font-weight:700;line-height:{1.3 if ar else 1.1}">{t}</div><div style="width:140px;height:10px;background:#2BD4A4"></div></div>']
    for i, p in enumerate(pts):
        slides.append(f'<div id="s{i+1}" dir="{d}" style="width:1280px;height:720px;background:#16171A;color:#F4F3EF;box-sizing:border-box;padding:96px;display:flex;flex-direction:column;justify-content:center;gap:28px;font-family:{font}"><div style="font:700 30px Inter Tight;color:#2BD4A4">{i+1:02d} / {len(pts):02d}</div><div style="font-size:{50 if len(p) < 70 else 42}px;font-weight:700;line-height:1.35">{p}</div></div>')
    slides.append(f'<div id="s{len(pts)+1}" dir="{d}" style="width:1280px;height:720px;background:#0B1E2A;color:#F4F3EF;box-sizing:border-box;padding:96px;display:flex;flex-direction:column;justify-content:center;align-items:center;gap:30px;font-family:{font};text-align:center"><span style="color:#2BD4A4;transform:scale(1.6)">{brand}</span><div style="font-size:44px;font-weight:700;margin-top:30px">meftacard.com/studio</div><div style="font-size:30px;opacity:.85">{cta}</div><div style="font:600 30px Inter Tight;color:#2BD4A4">Telegram: @meftacard</div></div>')
    ids = [f's{i}' for i in range(len(slides))]
    spngs = [shoot(f'<!doctype html><html><head><meta charset="utf-8">{FONTS}</head><body style="margin:0">{sl}</body></html>', [i], (1280, 720), tmp)[0]
             for sl, i in zip(slides, ids)]
    lst = os.path.join(tmp, 'list.txt')
    with open(lst, 'w') as f:
        for i, s in enumerate(spngs):
            f.write(f"file '{s}'\nduration {5 if i in (0, len(spngs) - 1) else 4.5}\n")
        f.write(f"file '{spngs[-1]}'\n")
    mp4 = os.path.join(tmp, f"video-{meta['slug']}.mp4")
    ff = shutil.which('ffmpeg')
    if not ff:
        subprocess.run([sys.executable, '-m', 'pip', 'install', '-q', 'imageio-ffmpeg'], check=True)
        import imageio_ffmpeg
        ff = imageio_ffmpeg.get_ffmpeg_exe()
    subprocess.run([ff, '-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', lst,
                    '-vf', 'fps=30,format=yuv420p,scale=1280:720', '-c:v', 'libx264', '-preset', 'medium', '-crf', '24',
                    '-movflags', '+faststart', mp4], check=True)
    dur = float(subprocess.run([shutil.which('ffprobe') or 'ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', mp4],
                               capture_output=True, text=True).stdout.strip() or 30)
    return (to_webp(cpng, os.path.join(tmp, f"cover-{meta['slug']}.webp")),
            to_webp(kpng, os.path.join(tmp, f"keypoints-{meta['slug']}.webp")), mp4, int(round(dur)))


def upload(path, ctype, alt):
    m = req('POST', '/wp/v2/media', raw_file=path, headers=[f'Content-Disposition: attachment; filename={os.path.basename(path)}', f'Content-Type: {ctype}'])
    if not m.get('id'):
        raise RuntimeError(f'upload failed for {path}: {str(m)[:200]}')
    req('POST', f"/wp/v2/media/{m['id']}", {'alt_text': alt, 'title': alt})
    return m


def live_check(url, lang):
    r = subprocess.run(['curl', '-s', '-m', '60', '-w', '\n%{http_code}', url], capture_output=True, text=True)
    b, code = r.stdout.rsplit('\n', 1)
    imgs = re.findall(r'<img[^>]*>', b[b.find('<main'):] if '<main' in b else b)
    checks = [('HTTP 200', code == '200'), ('exactly one H1', len(re.findall(r'<h1', b)) == 1),
              (f'lang="{lang}"', f'lang="{lang}"' in (re.search(r'<html[^>]*>', b) or [''])[0]),
              ('all content images have alt text', all(re.search(r'alt="[^"]+"', i) for i in imgs)),
              ('video embedded', '<video' in b), ('VideoObject structured data', 'VideoObject' in b)]
    for n, ok in checks:
        print(('    ✓ ' if ok else '    ✗ ') + n)
    return all(ok for _, ok in checks)


def main(path, publish, upgrade=False):
    meta, body = parse(path)
    lang = meta['lang']
    print(f'→ {meta["slug"]} ({lang})')
    gate = subprocess.run([sys.executable, os.path.join(ROOT, 'build/check-article.py'), path], capture_output=True, text=True)
    print(gate.stdout.rstrip())
    passed = gate.returncode == 0
    if not meta.get('key_points') or len(meta['key_points']) < 4:
        raise SystemExit('  ✗ front matter needs key_points (4–5 items) for the images and video')

    tmp = tempfile.mkdtemp()
    cover, kp, mp4, dur = render_media(meta, tmp)
    mc = upload(cover, 'image/webp', meta['title'])
    mk = upload(kp, 'image/webp', ('أهم النقاط: ' if lang == 'ar' else 'Key points: ') + meta['title'])
    mv = upload(mp4, 'video/mp4', ('فيديو: ' if lang == 'ar' else 'Video: ') + meta['title'])
    print(f'  ✓ media uploaded: cover {mc["id"]}, key points {mk["id"]}, video {mv["id"]} ({dur}s)')

    body_html = markdown.markdown(body, extensions=['tables', 'fenced_code', 'sane_lists'])
    body_html = body_html.replace('<table>', '<figure class="wp-block-table"><table>').replace('</table>', '</table></figure>')
    kp_fig = f'<figure class="wp-block-image size-large"><img src="{mk["source_url"]}" alt="{html.escape(("أهم النقاط: " if lang == "ar" else "Key points: ") + meta["title"])}" width="1200" height="675" loading="lazy" decoding="async"/></figure>'
    body_html = body_html.replace('<h2>', kp_fig + '\n<h2>', 1)
    vid_obj = {'@context': 'https://schema.org', '@type': 'VideoObject', 'name': meta['title'], 'description': meta['meta_description'],
               'thumbnailUrl': [mc['source_url']], 'uploadDate': datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'),
               'duration': f'PT{dur}S', 'contentUrl': mv['source_url'], 'inLanguage': lang}
    video = (f'<figure class="wp-block-video"><video controls preload="metadata" playsinline width="1280" height="720" poster="{mc["source_url"]}" src="{mv["source_url"]}"></video>'
             f'<figcaption>{html.escape(meta["title"])}</figcaption></figure>\n<script type="application/ld+json">{json.dumps(vid_obj, ensure_ascii=False)}</script>')
    faq = re.search(r'<h2>(أسئلة شائعة|Frequently asked questions)', body_html, re.I)
    body_html = body_html[:faq.start()] + video + '\n' + body_html[faq.start():] if faq else body_html + '\n' + video

    pre = '/ar' if lang == 'ar' else ''
    links = ' · '.join(f'<a href="https://meftacard.com{pre}/studio/{s}/">{n}</a>' for s, n in COUNTRIES[lang])
    block = (f'<h2>خدماتنا حسب الدولة</h2>\n<p>تصميم مواقع وسيو ودعم المواقع ووكلاء الذكاء الاصطناعي، أونلاين بالكامل ودون حاجة للحضور، والدفع بعملة تيثر (USDT): {links}.</p>\n<p>تابع <a href="https://t.me/meftacardchannel">قناتنا على تيليغرام</a> لأحدث المقالات.</p>\n<p><strong><a href="https://meftacard.com/ar/studio/free-website-review/">احصل على مراجعة مجانية لموقعك خلال 48 ساعة</a></strong>، دون أي التزام.</p>'
             if lang == 'ar' else
             f'<h2>Our services by country</h2>\n<p>Website design, SEO, website support and AI agents, fully online with no visit needed, paid in Tether (USDT): {links}.</p>\n<p>Follow our <a href="https://t.me/meftacardchannel">Telegram channel</a> for new guides.</p>\n<p><strong><a href="https://meftacard.com/studio/free-website-review/">Get a free website review in 48 hours</a></strong>, no obligation.</p>')
    cover_fig = f'<!--mc-cover--><figure class="wp-block-image size-large"><img src="{mc["source_url"]}" alt="{html.escape(meta["title"])}" width="1200" height="630" decoding="async" fetchpriority="high"/></figure><!--/mc-cover-->'
    content = f'<div lang="{lang}" dir="{"rtl" if lang == "ar" else "ltr"}">\n{cover_fig}\n{body_html}\n<!--mc-countries-->\n{block}\n</div>'

    existing = req('GET', f"/wp/v2/posts?slug={meta['slug']}&status=draft,publish,pending,private&context=edit&_fields=id,status")
    if existing and existing[0]['status'] == 'publish':
        if not upgrade:
            raise SystemExit(f"  ✗ {meta['slug']} is already published; use --upgrade to improve it")
        if not passed:
            raise SystemExit(f"  ✗ {meta['slug']} upgrade not applied: quality gate failed (published version left unchanged)")
    status = 'publish' if (publish and passed) else 'draft'
    keep_published = bool(existing and existing[0]['status'] == 'publish')
    data = {'title': meta['title'], 'slug': meta['slug'], 'content': content, 'status': 'publish' if keep_published else 'draft',
            'categories': [CATS[lang]], 'excerpt': meta['meta_description'], 'featured_media': mc['id']}
    post = req('POST', f"/wp/v2/posts/{existing[0]['id']}" if existing else '/wp/v2/posts', data)
    if not post.get('id'):
        raise SystemExit(f'  ✗ could not save post: {str(post)[:300]}')
    kw = ','.join([meta['focus_keyword']] + [k for k in meta.get('secondary_keywords', []) if k != meta['focus_keyword']][:4])
    req('POST', '/rankmath/v1/updateMeta', {'objectType': 'post', 'objectID': post['id'], 'meta': {
        'rank_math_title': meta['seo_title'], 'rank_math_description': meta['meta_description'], 'rank_math_focus_keyword': kw}})
    if keep_published or status == 'publish':     # publish last, so Rank Math meta and media are in place first
        if not keep_published:
            post = req('POST', f"/wp/v2/posts/{post['id']}", {'status': 'publish'})
        time.sleep(8)
        print('  live check:')
        live_ok = live_check(post['link'], lang)
        tg = req('GET', f"/wp/v2/posts/{post['id']}?context=edit&_fields=meta").get('meta', {})
        print(f"    {'✓' if tg.get('mc_tg_posted') else '…'} Telegram post: {tg.get('mc_tg_posted') or tg.get('mc_tg_error') or 'pending'}")
        print(f"  ✓ PUBLISHED: {post['link']}" + ('' if live_ok else '  (live check found issues above)'))
    else:
        why = 'quality gate failed' if publish and not passed else 'draft mode'
        print(f"  • saved as DRAFT ({why}): https://meftacard.com/wp-admin/post.php?post={post['id']}&action=edit")
    return post['id']


if __name__ == '__main__':
    args = sys.argv[1:]
    publish = '--publish' in args
    upgrade = '--upgrade' in args          # improve an already-published article (only if the gate passes)
    files = [a for a in args if a not in ('--publish', '--upgrade')]
    if not files:
        raise SystemExit(__doc__)
    for p in files:
        main(p, publish or upgrade, upgrade)
