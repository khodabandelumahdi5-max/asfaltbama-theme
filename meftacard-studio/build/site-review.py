#!/usr/bin/env python3
"""Free website review: checks a site and writes a short report with the five priority fixes.

Usage:
  python3 meftacard-studio/build/site-review.py <url> [--lang ar|en] [--out report.md]
  python3 meftacard-studio/build/site-review.py --score <url> [...]     # one-line score per site (lead qualification)

Checks (mobile-first): HTTPS, load time on a mid-range phone, mobile viewport, horizontal scroll,
page weight, title/description, H1, language/direction, Arabic version, images without alt,
WhatsApp/phone links, Google Maps / LocalBusiness data, sitemap and robots.txt.
Only public pages are fetched. The report is a starting point: read it before sending.
"""
import json, re, subprocess, sys, time
from urllib.parse import urljoin, urlparse

UA = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Mobile Safari/537.36'


def curl(url, head=False):
    cmd = ['curl', '-s', '-L', '-m', '25', '-A', UA, '-w', '\n%{http_code} %{url_effective} %{time_total} %{size_download}']
    if head:
        cmd += ['-o', '/dev/null']
    r = subprocess.run(cmd + [url], capture_output=True, text=True, errors='replace')
    body, meta = (r.stdout.rsplit('\n', 1) + [''])[:2] if '\n' in r.stdout else ('', r.stdout)
    parts = meta.split()
    if len(parts) < 4:
        return 0, url, 0.0, 0, ''
    return int(parts[0]), parts[1], float(parts[2]), int(parts[3]), body


def browser_metrics(url):
    """Load the page as a phone and measure what a visitor experiences."""
    js = r"""
const {chromium,devices}=require('playwright');(async()=>{
 const b=await chromium.launch();const ctx=await b.newContext({...devices['Pixel 7']});const p=await ctx.newPage();
 let bytes=0;p.on('response',async r=>{try{const h=r.headers()['content-length'];if(h)bytes+=parseInt(h)}catch(e){}});
 const t0=Date.now();let ok=true;
 try{await p.goto(process.argv[1],{waitUntil:'load',timeout:45000})}catch(e){ok=false}
 const load=(Date.now()-t0)/1000;
 const m=ok?await p.evaluate(()=>{const lcp=performance.getEntriesByType('largest-contentful-paint').pop();
   return {overflow:document.documentElement.scrollWidth-innerWidth,
           small:[...document.querySelectorAll('a,button')].filter(e=>{const r=e.getBoundingClientRect();return r.width>0&&r.height>0&&(r.height<32||r.width<32)}).length,
           imgs:document.images.length,fcp:(performance.getEntriesByName('first-contentful-paint')[0]||{}).startTime||0}}):{};
 console.log(JSON.stringify({ok,load,bytes,...m}));await b.close()})()"""
    r = subprocess.run(['node', '-e', js, url], capture_output=True, text=True, timeout=120)
    try:
        return json.loads(r.stdout.strip().splitlines()[-1])
    except Exception:
        return {'ok': False}


def analyse(url):
    if not url.startswith('http'):
        url = 'https://' + url
    code, final, t, size, html = curl(url)
    if code == 0 or not html:
        code, final, t, size, html = curl(url.replace('https://', 'http://'))
    f = {'url': url, 'final': final, 'code': code, 'ttfb_total': t, 'html_kb': round(size / 1024)}
    if code != 200:
        f['down'] = True
        return f
    low = html.lower()
    host = urlparse(final).netloc
    f['https'] = final.startswith('https://')
    f['viewport'] = 'name="viewport"' in low or "name='viewport'" in low
    f['title'] = (re.search(r'<title[^>]*>(.*?)</title>', html, re.S | re.I) or [None, ''])[1].strip()
    f['desc'] = (re.search(r'<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)', html, re.I) or [None, ''])[1].strip()
    f['h1'] = len(re.findall(r'<h1[\s>]', low))
    tag = (re.search(r'<html[^>]*>', html, re.I) or [''])[0] if re.search(r'<html[^>]*>', html, re.I) else ''
    tag = re.search(r'<html[^>]*>', html, re.I).group(0) if re.search(r'<html[^>]*>', html, re.I) else ''
    f['lang'] = (re.search(r'lang=["\']([^"\']+)', tag) or [None, ''])[1]
    f['rtl'] = bool(re.search(r'dir=["\']?rtl', tag, re.I))
    f['arabic_text'] = len(re.findall(r'[؀-ۿ]{3,}', re.sub(r'<[^>]+>', ' ', html))) > 30
    f['hreflang'] = 'hreflang=' in low
    imgs = re.findall(r'<img[^>]*>', html, re.I)
    f['imgs'] = len(imgs)
    f['imgs_no_alt'] = sum(1 for i in imgs if not re.search(r'alt=["\'][^"\']+', i))
    f['whatsapp'] = 'wa.me/' in low or 'api.whatsapp.com' in low or 'whatsapp://' in low
    f['tel'] = 'href="tel:' in low or "href='tel:" in low
    f['maps'] = 'google.com/maps' in low or 'goo.gl/maps' in low or 'maps.app.goo.gl' in low
    f['schema_local'] = bool(re.search(r'"@type"\s*:\s*"(LocalBusiness|Organization|Dentist|MedicalClinic|Restaurant|Store|AutoRepair|ProfessionalService)', html))
    f['sitemap'] = curl(urljoin(final, '/sitemap.xml'), head=True)[0] == 200 or curl(urljoin(final, '/sitemap_index.xml'), head=True)[0] == 200
    f['robots'] = curl(urljoin(final, '/robots.txt'), head=True)[0] == 200
    f['copyright_year'] = max([int(y) for y in re.findall(r'(?:©|&copy;|copyright)\s*(?:\d{4}\s*[-–]\s*)?(20\d\d)', low)] or [0])
    f.update({'m_' + k: v for k, v in browser_metrics(final).items()})
    return f


def issues(f, lang):
    """Return (score, list of (weight, title, why, fix)) in the requested language."""
    ar = lang == 'ar'
    out = []
    def add(w, en, ar_t):
        out.append((w, ar_t if ar else en))
    if f.get('down'):
        add(100, ('The website does not open', f'It returned HTTP {f.get("code")}; visitors and Google see an error.', 'Fix hosting or the domain first.'),
                 ('الموقع لا يفتح', f'يعطي خطأ HTTP {f.get("code")}، فيرى الزوار وGoogle صفحة خطأ.', 'إصلاح الاستضافة أو الدومين أولاً.'))
        return 0, out
    load = f.get('m_load') or f.get('ttfb_total', 0)
    if not f.get('https'):
        add(30, ('No HTTPS padlock', 'Browsers mark the site "Not secure", which scares visitors away and hurts rankings.', 'Install an SSL certificate and redirect all pages to https.'),
                ('لا يوجد قفل HTTPS', 'المتصفح يعرض الموقع على أنه "غير آمن"، فيبتعد الزوار ويتراجع الترتيب.', 'تفعيل شهادة SSL وتحويل كل الصفحات إلى https.'))
    if not f.get('viewport') or (f.get('m_overflow') or 0) > 8:
        add(28, ('Not mobile-friendly', 'On phones the page needs zooming or scrolls sideways; most visitors in the region use phones.', 'Rebuild the layout mobile-first (responsive design).'),
                ('غير مناسب للجوال', 'على الجوال تحتاج الصفحة إلى تكبير أو تتحرك جانبياً، وأغلب الزوار في المنطقة يستخدمون الجوال.', 'إعادة بناء التصميم للجوال أولاً (تصميم متجاوب).'))
    if load > 6:
        add(25, (f'Very slow on mobile ({load:.1f}s)', 'Many visitors leave before the page finishes loading.', 'Compress images, use caching and lighter themes or hosting.'),
                (f'بطيء جداً على الجوال ({load:.1f} ثانية)', 'كثير من الزوار يغادرون قبل اكتمال تحميل الصفحة.', 'ضغط الصور وتفعيل التخزين المؤقت واستخدام قالب أو استضافة أخف.'))
    elif load > 3.5:
        add(14, (f'Slow on mobile ({load:.1f}s)', 'Speed affects both visitors and Google rankings.', 'Compress images and remove unused scripts.'),
                (f'بطيء على الجوال ({load:.1f} ثانية)', 'السرعة تؤثر على الزوار وعلى ترتيب Google.', 'ضغط الصور وإزالة السكربتات غير المستخدمة.'))
    if not (f.get('whatsapp') or f.get('tel')):
        add(22, ('No one-tap WhatsApp or call button', 'Customers in the region prefer WhatsApp; without a button many simply leave.', 'Add WhatsApp and call buttons on every page.'),
                ('لا يوجد زر واتساب أو اتصال بلمسة واحدة', 'العملاء في المنطقة يفضلون واتساب، ومن دون زر يغادر كثيرون.', 'إضافة زري واتساب واتصال في كل صفحة.'))
    elif not f.get('whatsapp'):
        add(10, ('No WhatsApp button', 'WhatsApp is the main way customers contact businesses in Iraq and the Gulf.', 'Add a WhatsApp button with a ready message.'),
                ('لا يوجد زر واتساب', 'واتساب هو الطريقة الأساسية لتواصل العملاء مع الشركات في العراق والخليج.', 'إضافة زر واتساب برسالة جاهزة.'))
    if not f.get('arabic_text'):
        add(18, ('No Arabic version', 'Arabic-speaking customers and Arabic searches are missed.', 'Add Arabic pages with proper right-to-left design.'),
                ('لا توجد نسخة عربية', 'يضيع العملاء الناطقون بالعربية والبحث العربي.', 'إضافة صفحات عربية بتصميم صحيح من اليمين إلى اليسار.'))
    elif f.get('arabic_text') and not (f.get('rtl') or f.get('lang', '').lower().startswith(('ar', 'fa', 'ku', 'ckb', 'ur'))):
        add(12, ('Arabic content not marked as Arabic', 'The page language/direction is wrong, so Google may not show it for Arabic searches.', 'Set lang="ar" and dir="rtl" on Arabic pages.'),
                ('المحتوى العربي غير معرّف كعربي', 'لغة الصفحة أو اتجاهها خاطئ، فقد لا يعرضها Google في البحث العربي.', 'ضبط lang="ar" وdir="rtl" في الصفحات العربية.'))
    if not f.get('title') or len(f.get('title', '')) < 15 or re.search(r'(^|\|\s*)(home|homepage|welcome|index)\s*($|\||-|–|:)|my website|storefront|just another|coming soon|untitled|^one moment', f.get('title', ''), re.I):
        add(15, ('Missing or weak page title', 'The title is the main line shown in Google results.', 'Write a clear title with your service and city.'),
                ('عنوان الصفحة مفقود أو ضعيف', 'العنوان هو السطر الرئيسي الذي يظهر في نتائج Google.', 'كتابة عنوان واضح يتضمن الخدمة والمدينة.'))
    if not f.get('desc'):
        add(10, ('No meta description', 'Google writes its own snippet, often not the one that sells.', 'Write a 140–155 character description with a reason to click.'),
                ('لا يوجد وصف للصفحة', 'Google يكتب مقتطفاً من عنده، غالباً لا يقنع العميل.', 'كتابة وصف من 140 إلى 155 حرفاً يعطي سبباً للنقر.'))
    if f.get('h1', 0) != 1:
        add(8, ('Main heading (H1) missing or repeated', 'Search engines use the H1 to understand what the page is about.', 'Use exactly one clear H1 per page.'),
                ('العنوان الرئيسي (H1) مفقود أو مكرر', 'محركات البحث تعتمد على H1 لفهم موضوع الصفحة.', 'استخدام عنوان H1 واحد وواضح في كل صفحة.'))
    if not (f.get('maps') or f.get('schema_local')):
        add(12, ('Weak local signals (Google Maps)', 'No map or business data, so it is harder to appear in "near me" and Maps results.', 'Link your Google Business Profile and add LocalBusiness data.'),
                ('إشارات محلية ضعيفة (خرائط Google)', 'لا توجد خريطة أو بيانات نشاط، فيصعب الظهور في نتائج "بالقرب مني" والخرائط.', 'ربط ملف Google التجاري وإضافة بيانات LocalBusiness.'))
    if f.get('imgs') and f.get('imgs_no_alt', 0) > max(2, f['imgs'] * 0.3):
        add(6, (f'{f["imgs_no_alt"]} images without alt text', 'Images without descriptions cannot appear in Google Images.', 'Add short descriptive alt text to images.'),
                (f'{f["imgs_no_alt"]} صورة بدون نص بديل', 'الصور بلا وصف لا تظهر في بحث صور Google.', 'إضافة نص بديل قصير يصف كل صورة.'))
    if not f.get('sitemap'):
        add(6, ('No sitemap', 'Google discovers new pages more slowly.', 'Generate an XML sitemap and submit it in Search Console.'),
                ('لا توجد خريطة موقع', 'Google يكتشف الصفحات الجديدة ببطء.', 'إنشاء خريطة موقع XML وإرسالها في Search Console.'))
    if f.get('copyright_year') and f['copyright_year'] < 2024:
        add(5, (f'Looks outdated (© {f["copyright_year"]})', 'An old year in the footer makes visitors wonder if the business is still active.', 'Update the content and footer.'),
                (f'يبدو قديماً (© {f["copyright_year"]})', 'سنة قديمة في أسفل الموقع تجعل الزائر يتساءل هل النشاط ما زال قائماً.', 'تحديث المحتوى وأسفل الصفحة.'))
    out.sort(key=lambda x: -x[0])
    score = max(0, 100 - sum(w for w, _ in out))
    return score, out


def report(f, lang):
    ar = lang == 'ar'
    score, iss = issues(f, lang)
    host = urlparse(f.get('final') or f['url']).netloc or f['url']
    L = []
    if ar:
        L += [f'# مراجعة مجانية لموقع {host}', '', f'**التقييم العام:** {score} من 100', '']
        L += ['## أهم الإصلاحات حسب الأولوية', '']
    else:
        L += [f'# Free website review: {host}', '', f'**Overall score:** {score}/100', '']
        L += ['## Priority fixes', '']
    for i, (_, (t, why, fix)) in enumerate(iss[:5], 1):
        L += [f'**{i}. {t}**', f'- {"لماذا يهم" if ar else "Why it matters"}: {why}', f'- {"الحل" if ar else "Fix"}: {fix}', '']
    good = []
    if f.get('https'): good.append('HTTPS' if not ar else 'قفل HTTPS مفعّل')
    if f.get('viewport') and (f.get('m_overflow') or 0) <= 8: good.append('mobile layout' if not ar else 'تصميم مناسب للجوال')
    if f.get('whatsapp'): good.append('WhatsApp button' if not ar else 'زر واتساب')
    if f.get('arabic_text'): good.append('Arabic content' if not ar else 'محتوى عربي')
    if good:
        L += ['## ' + ('ما هو جيد في موقعك' if ar else 'What is already good'), '', '، '.join(good) if ar else ', '.join(good), '']
    if not iss:
        L += ['موقعك في حالة جيدة. ننصح فقط بالاستمرار في نشر المحتوى وجمع التقييمات.' if ar else 'Your site is in good shape. Keep publishing content and collecting reviews.', '']
    if ar:
        L += ['## الخطوة التالية', '',
              'يمكنكم تنفيذ هذه الإصلاحات بأنفسكم أو مع أي فريق. وإذا أردتم أن نتولاها نحن بالكامل:',
              '- ردّوا على رسالتنا بكلمة «تنفيذ»، ونرسل لكم خلال 24 ساعة خطة بسعر ثابت ومدة تسليم واضحة.',
              '- العمل أونلاين بالكامل، ولا نحتاج منكم إلا الموافقة.',
              '- الدفع بعملة تيثر (USDT).', '',
              '---', 'MEFTACARD Studio · https://meftacard.com/ar/studio/ · Telegram @meftacard']
    else:
        L += ['## Next step', '',
              'You can apply these fixes yourselves or with any team. If you would rather we handle all of it:',
              '- Reply to our message with "go", and within 24 hours we send a fixed-price plan with a clear delivery date.',
              '- Everything is done online; all we need from you is approval.',
              '- Payment in Tether (USDT).', '',
              '---', 'MEFTACARD Studio · https://meftacard.com/studio/ · Telegram @meftacard']
    return '\n'.join(L), score, iss


if __name__ == '__main__':
    a = sys.argv[1:]
    lang = a[a.index('--lang') + 1] if '--lang' in a else 'ar'
    out = a[a.index('--out') + 1] if '--out' in a else None
    urls = [x for i, x in enumerate(a) if not x.startswith('--') and (i == 0 or a[i - 1] not in ('--lang', '--out'))]
    if '--score' in a:
        for u in urls:
            f = analyse(u); s, iss = issues(f, 'en')
            print(f'{s:3d} | {u} | {"; ".join(t for _, (t, _, _) in iss[:3])}')
    else:
        f = analyse(urls[0]); text, s, _ = report(f, lang)
        if out:
            open(out, 'w', encoding='utf-8').write(text)
        print(text)
