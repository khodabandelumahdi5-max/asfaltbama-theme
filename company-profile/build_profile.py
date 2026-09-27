#!/usr/bin/env python3
"""
Company profile of Ofogh Apadana Pasargad Co. (Asfaltbama) on company
letterhead, in English and Arabic, as A4 PDFs.

Only facts the company stands behind are used: 25+ years, 850+ projects,
own site team and equipment, written warranty, the services and the
markets served. There is deliberately no project reference list; add real
projects to PROJECTS below (name, city, year, scope) when available.

    python3 company-profile/build_profile.py      # writes the .html files
    node company-profile/render.js                # prints them to PDF
"""

import html
import os

ROOT = os.path.dirname(os.path.abspath(__file__))
IMG = os.path.join(ROOT, '..', 'asfaltbama-child', 'content', 'images')
FONT = os.path.join(ROOT, '..', 'asfaltbama-child', 'assets', 'fonts', 'Vazirmatn-Variable.woff2')
LOGO = os.path.join(ROOT, '..', 'asfaltbama-child', 'assets', 'images', 'logo.png')

EMAIL = 'ofoghapadanapasargad@gmail.com'
WHATSAPP = '+98 919 129 9559'
WEB = 'asfaltbama.com'

# Real completed projects only: (name, city/country, year, scope).
PROJECTS = []

PAV = 'asfalt-mohavate-mojtame-maskoni-finisher-ghaltak.jpg'
PRIME = 'gheirpashi-bastar-asfalt-mohavate-mojtame.jpg'
BOB = 'bobcat-bargiri-nakhale-kamyon.jpg'
ISO1 = 'isogam-poshtbam-hampooshani-darz-ghiri.jpg'
ISO2 = 'isogam-poshtbam-paye-kooler.jpg'
ISO3 = 'isogam-divar-jan-panah-labe-bam.jpg'
ISO4 = 'isogam-kenar-divar-mahiche-kesh.jpg'

E = html.escape


def src(name):
    return 'file://' + os.path.abspath(os.path.join(IMG, name))


T = {
    'en': {
        'dir': 'ltr',
        'company': 'Ofogh Apadana Pasargad Co.',
        'brand': 'Asfaltbama',
        'tagline': 'Asphalt Paving · Isogam Waterproofing · Bitumen Roofing',
        'doc': 'Company Profile',
        'cover_title': 'Asphalt paving and roof waterproofing contractor',
        'cover_lead': 'Hot-mix asphalt for industrial yards, car parks and compounds; isogam membrane and hot bitumen roofing for flat roofs, foundations and wet areas. Our own site team and equipment, and a written warranty on every job.',
        'figures': [('25+', 'years of experience'), ('850+', 'completed projects'), ('Written', 'warranty on every job')],
        'markets_title': 'Markets served',
        'markets': 'Iran (Tehran and Alborz) · Iraq (Karbala, Najaf, Basra, Erbil) · Kuwait · Oman · Bahrain · United Arab Emirates',
        'about_title': 'About us',
        'about': [
            'Ofogh Apadana Pasargad Co., trading as Asfaltbama, is a Tehran-based contractor specialised in asphalt paving and bituminous waterproofing, with more than 25 years of experience and over 850 completed projects.',
            'We carry out the work with our own site team and equipment, from base preparation to the final surface and from roof preparation to the flood test. Every job is handed over with a written warranty.',
            'We work directly for owners and as a specialist subcontractor for main contractors, following the project specification and the approvals required locally.',
        ],
        'services_title': 'Core services',
        'services': [
            (PAV, 'Asphalt paving', ['Industrial yards, warehouses and loading areas', 'Car parks, compounds and villa driveways', 'Base preparation, prime and tack coats', 'Binder and wearing courses with paver and tandem rollers', 'Patching, milling and overlay; hot-poured crack sealing']),
            (ISO1, 'Isogam membrane waterproofing', ['Torch-applied modified bitumen membranes (APP), aluminium-foil faced', 'Primer, fillets and upstands on parapets', 'Lapped, heat-sealed seams', 'Detailing around AC units, ducts, pipes and drains', 'Flood test before handover']),
            (ISO4, 'Bitumen roofing (hot bitumen and jute)', ['Two full layers laid crosswise', 'Blown bitumen for heat resistance', 'Roofs, foundations, basements and wet areas', 'Protection layer against sun and traffic', 'Written warranty']),
        ],
        'heat_title': 'Specified for hot climates',
        'heat': [('Bitumen grade', 'Harder or polymer-modified grades where the specification allows'),
                 ('Compaction', 'Rolling completed while the mix is hot; night work in summer'),
                 ('Roof finish', 'Reflective aluminium-foil membranes or a protection layer'),
                 ('Drainage', 'Falls and outlets checked before paving and roofing')],
        'how_title': 'How we work on projects abroad',
        'how': [('Enquiry', 'Photos, drawings and approximate areas by email or WhatsApp.'),
                ('Technical review', 'Scope, specification, climate, loads and drainage.'),
                ('Written proposal', 'Method statement, thicknesses, materials, schedule and price.'),
                ('Mobilisation', 'Our site team, with materials and equipment as agreed.'),
                ('Execution and testing', 'Quality checks during the works; flood tests on roofs.'),
                ('Handover', 'Records and a written warranty.')],
        'quality_title': 'Quality and safety',
        'quality': ['Compacted thickness and layer details written into the contract', 'Surface preparation checked before every membrane and asphalt layer', 'Flood test of roofs before handover', 'Safe handling of hot bitumen; protective equipment on site'],
        'gallery_title': 'From our sites',
        'gallery': [(PAV, 'Paving and compaction of a residential compound yard'), (PRIME, 'Prime coat on the compacted base'), (BOB, 'Site preparation with a skid-steer loader'),
                    (ISO1, 'Isogam with lapped, sealed seams'), (ISO3, 'Membrane turned up the parapet'), (ISO2, 'Waterproofing around AC unit bases')],
        'refs_title': 'Project references',
        'refs_empty': 'Project references and site visits in Tehran are available on request.',
        'contact_title': 'Contact',
        'contact': [('Email', EMAIL), ('WhatsApp', WHATSAPP), ('Website', WEB), ('Office', 'Tehran, Iran')],
        'page': 'Page',
    },
    'ar': {
        'dir': 'rtl',
        'company': 'شركة أفق آبادانا باسارجاد',
        'brand': 'أسفلت با ما',
        'tagline': 'رصف الأسفلت · العزل بالإيزوجام · العزل بالقير والجنفاص',
        'doc': 'ملف الشركة',
        'cover_title': 'مقاول رصف الأسفلت والعزل المائي للأسطح',
        'cover_lead': 'أسفلت ساخن للساحات الصناعية ومواقف السيارات والمجمعات، وعزل الأسطح والأساسات والحمامات بلفائف الإيزوجام وبالقير والجنفاص. فريق تنفيذ ومعدات خاصة بنا، وضمان مكتوب لكل عمل.',
        'figures': [('+٢٥', 'عاماً من الخبرة'), ('+٨٥٠', 'مشروعاً منجزاً'), ('ضمان', 'مكتوب لكل عمل')],
        'markets_title': 'الأسواق التي نخدمها',
        'markets': 'إيران (طهران والبرز) · العراق (كربلاء، النجف، البصرة، أربيل) · الكويت · سلطنة عُمان · البحرين · الإمارات العربية المتحدة',
        'about_title': 'من نحن',
        'about': [
            'شركة أفق آبادانا باسارجاد، وعلامتها التجارية «أسفلت با ما»، مقاول مقره طهران متخصص في رصف الأسفلت والعزل البيتوميني، بخبرة تزيد على ٢٥ عاماً وأكثر من ٨٥٠ مشروعاً منجزاً.',
            'ننفّذ الأعمال بفريقنا ومعداتنا، من تجهيز طبقة الأساس حتى السطح النهائي، ومن تجهيز السطح حتى اختبار الغمر بالماء. ويُسلَّم كل عمل مع ضمان مكتوب.',
            'نعمل مباشرة لصالح المالكين وكمقاول باطن متخصص لصالح المقاولين الرئيسيين، وفق مواصفات المشروع والموافقات المحلية.',
        ],
        'services_title': 'خدماتنا الأساسية',
        'services': [
            (PAV, 'رصف الأسفلت', ['الساحات الصناعية والمستودعات ومناطق التحميل', 'مواقف السيارات والمجمعات وممرات الفلل', 'تجهيز طبقة الأساس ورش البرايم كوت والتاك كوت', 'الطبقة الرابطة والسطحية بالفرّاشة والحادلات', 'الترقيع والكشط وإعادة الرصف وسد الشقوق بالمستيك الحار']),
            (ISO1, 'العزل المائي بالإيزوجام', ['لفائف البيتومين المعدّل (APP) بوجه ألمنيوم، تُلصق باللهب', 'دهان تأسيسي وزوايا مدوّرة ورفع العزل على جدران السترة', 'وصلات متراكبة ملحومة بالحرارة', 'تفاصيل حول المكيفات ومجاري الهواء والأنابيب والمصارف', 'اختبار الغمر بالماء قبل التسليم']),
            (ISO4, 'العزل بالقير والجنفاص', ['طبقتان كاملتان بشكل متعامد', 'قير مؤكسد مقاوم للحرارة', 'الأسطح والأساسات والسراديب والحمامات', 'طبقة حماية من الشمس والحركة', 'ضمان مكتوب']),
        ],
        'heat_title': 'مواصفات للمناخ الحار',
        'heat': [('درجة القير', 'درجات أصلب أو قير معدّل بالبوليمر حيث تسمح المواصفات'),
                 ('الدكّ', 'إكمال الدكّ والخلطة ساخنة، والعمل ليلاً في الصيف'),
                 ('سطح العزل', 'لفائف عاكسة بوجه ألمنيوم أو طبقة حماية'),
                 ('التصريف', 'مراجعة الميول والمصارف قبل الرصف والعزل')],
        'how_title': 'آلية العمل في المشاريع الخارجية',
        'how': [('الاستفسار', 'الصور والمخططات والمساحات التقريبية عبر البريد أو واتساب.'),
                ('الدراسة الفنية', 'نطاق العمل والمواصفات والمناخ والأحمال والتصريف.'),
                ('العرض المكتوب', 'طريقة التنفيذ والسماكات والمواد والجدول الزمني والسعر.'),
                ('التجهيز', 'فريق التنفيذ مع المواد والمعدات حسب الاتفاق.'),
                ('التنفيذ والاختبار', 'فحوصات الجودة أثناء العمل واختبار الغمر للأسطح.'),
                ('التسليم', 'السجلات وضمان مكتوب.')],
        'quality_title': 'الجودة والسلامة',
        'quality': ['كتابة السماكة بعد الدكّ وتفاصيل الطبقات في العقد', 'فحص تجهيز السطح قبل كل طبقة عزل أو أسفلت', 'اختبار غمر الأسطح بالماء قبل التسليم', 'التعامل الآمن مع القير الساخن ومعدات الوقاية في الموقع'],
        'gallery_title': 'من مواقع عملنا',
        'gallery': [(PAV, 'رصف ودكّ ساحة مجمع سكني'), (PRIME, 'رش البرايم كوت على طبقة الأساس'), (BOB, 'تجهيز الموقع بالبوبكات'),
                    (ISO1, 'إيزوجام بوصلات متراكبة ومحكمة'), (ISO3, 'رفع العزل على جدار السترة'), (ISO2, 'العزل حول قواعد المكيفات')],
        'refs_title': 'مراجع المشاريع',
        'refs_empty': 'مراجع المشاريع وزيارة مواقع العمل في طهران متاحة عند الطلب.',
        'contact_title': 'التواصل',
        'contact': [('البريد الإلكتروني', EMAIL), ('واتساب', WHATSAPP), ('الموقع', WEB), ('المكتب', 'طهران، إيران')],
        'page': 'صفحة',
    },
}


def letterhead(t, n, total):
    return f'''<header class="lh"><div class="lh__brand"><img src="file://{os.path.abspath(LOGO)}" alt=""><div><b>{E(t['company'])}</b><span>{E(t['brand'])} · شرکت افق آپادانا پاسارگاد</span></div></div>
<div class="lh__meta"><span>{E(t['doc'])}</span><span class="ltr">{E(EMAIL)}</span><span class="ltr">{E(WHATSAPP)} · {E(WEB)}</span></div></header>'''


def footer(t, n, total):
    return f'<footer class="ft"><span>{E(t["company"])} · {E(t["tagline"])}</span><span>{E(t["page"])} {n} / {total}</span></footer>'


def build(lang):
    t = T[lang]
    total = 4
    figs = ''.join(f'<li><b>{E(a)}</b><span>{E(b)}</span></li>' for a, b in t['figures'])
    p1 = f'''<section class="page">{letterhead(t, 1, total)}
<div class="cover">
<span class="kicker">{E(t['doc'])}</span>
<h1>{E(t['cover_title'])}</h1>
<p class="lead">{E(t['cover_lead'])}</p>
<div class="cover__img"><img src="{src(PAV)}" alt=""><img src="{src(ISO1)}" alt=""><img src="{src(ISO3)}" alt=""></div>
<ul class="figs">{figs}</ul>
<div class="markets"><b>{E(t['markets_title'])}</b><span>{E(t['markets'])}</span></div>
</div>{footer(t, 1, total)}</section>'''

    about = ''.join(f'<p>{E(x)}</p>' for x in t['about'])
    svc = ''
    for photo, title, items in t['services']:
        lis = ''.join(f'<li>{E(i)}</li>' for i in items)
        svc += f'<div class="svc"><img src="{src(photo)}" alt=""><div><h3>{E(title)}</h3><ul>{lis}</ul></div></div>'
    p2 = f'''<section class="page">{letterhead(t, 2, total)}
<h2>{E(t['about_title'])}</h2>{about}
<h2>{E(t['services_title'])}</h2>{svc}
{footer(t, 2, total)}</section>'''

    heat = ''.join(f'<li><b>{E(a)}</b><span>{E(b)}</span></li>' for a, b in t['heat'])
    how = ''.join(f'<li><b>{E(a)}</b><span>{E(b)}</span></li>' for a, b in t['how'])
    qual = ''.join(f'<li>{E(x)}</li>' for x in t['quality'])
    p3 = f'''<section class="page">{letterhead(t, 3, total)}
<h2>{E(t['heat_title'])}</h2><ul class="grid4">{heat}</ul>
<h2>{E(t['how_title'])}</h2><ol class="steps">{how}</ol>
<h2>{E(t['quality_title'])}</h2><ul class="checks">{qual}</ul>
{footer(t, 3, total)}</section>'''

    gal = ''.join(f'<figure><img src="{src(f)}" alt=""><figcaption>{E(c)}</figcaption></figure>' for f, c in t['gallery'])
    if PROJECTS:
        refs = '<table class="refs">' + ''.join(f'<tr><td>{E(a)}</td><td>{E(b)}</td><td>{E(str(c))}</td><td>{E(d)}</td></tr>' for a, b, c, d in PROJECTS) + '</table>'
    else:
        refs = f'<p>{E(t["refs_empty"])}</p>'
    contact = ''.join(f'<li><span>{E(a)}</span><b class="ltr">{E(b)}</b></li>' for a, b in t['contact'])
    p4 = f'''<section class="page">{letterhead(t, 4, total)}
<h2>{E(t['gallery_title'])}</h2><div class="gal">{gal}</div>
<h2>{E(t['refs_title'])}</h2>{refs}
<div class="contact"><h2>{E(t['contact_title'])}</h2><ul>{contact}</ul></div>
{footer(t, 4, total)}</section>'''

    css = f'''@font-face{{font-family:V;src:url(file://{os.path.abspath(FONT)}) format("woff2");font-weight:100 900}}
@page{{size:A4;margin:0}}
*{{box-sizing:border-box;margin:0}}
body{{font-family:V;color:#1e293b;font-size:10.5pt;line-height:1.75}}
.ltr{{direction:ltr;unicode-bidi:isolate}}
.page{{position:relative;width:210mm;height:297mm;padding:0 16mm 20mm;overflow:hidden;page-break-after:always;background:#fff}}
.lh{{display:flex;justify-content:space-between;align-items:center;margin:0 -16mm 9mm;padding:7mm 16mm 6mm;background:#0f172a;color:#fff;border-bottom:3mm solid #f59e0b}}
.lh__brand{{display:flex;align-items:center;gap:4mm}}.lh__brand img{{width:17mm;height:17mm;border-radius:50%;background:#fff}}
.lh__brand b{{display:block;font-size:14pt;font-weight:900}}.lh__brand span{{color:#fbbf24;font-size:9pt;font-weight:700}}
.lh__meta{{display:flex;flex-direction:column;align-items:flex-end;font-size:8.5pt;color:#cbd5e1;line-height:1.6}}[dir=rtl] .lh__meta{{align-items:flex-start}}
.lh__meta span:first-child{{color:#fbbf24;font-weight:800;font-size:9.5pt}}
.ft{{position:absolute;left:16mm;right:16mm;bottom:8mm;display:flex;justify-content:space-between;padding-top:3mm;border-top:.4mm solid #e2e8f0;font-size:7.5pt;color:#64748b}}
h1{{font-size:24pt;font-weight:900;color:#0f172a;line-height:1.3;margin:3mm 0 4mm}}
h2{{font-size:14pt;font-weight:900;color:#0f172a;margin:6mm 0 3mm;padding-inline-start:3mm;border-inline-start:1.2mm solid #f59e0b}}
h3{{font-size:12pt;font-weight:900;color:#0f172a;margin-bottom:1.5mm}}
p{{margin:0 0 2.5mm}}
.kicker{{display:inline-block;padding:1mm 4mm;border-radius:9mm;background:#fef3c7;color:#b45309;font-weight:900;font-size:9pt}}
.lead{{font-size:11.5pt;color:#334155}}
.cover__img{{display:grid;grid-template-columns:2fr 1fr 1fr;gap:3mm;margin:7mm 0}}.cover__img img{{width:100%;height:62mm;object-fit:cover;border-radius:3mm}}
.figs{{display:grid;grid-template-columns:repeat(3,1fr);gap:4mm;list-style:none;padding:0;margin:0 0 7mm}}
.figs li{{padding:5mm;border-radius:3mm;background:#0f172a;color:#cbd5e1;text-align:center}}.figs b{{display:block;color:#fbbf24;font-size:20pt;font-weight:900;line-height:1.3}}
.markets{{padding:5mm;border:.4mm solid #e2e8f0;border-inline-start:1.5mm solid #f59e0b;border-radius:3mm;background:#fffbeb}}.markets b{{display:block;color:#0f172a;font-weight:900;margin-bottom:1mm}}
.svc{{display:grid;grid-template-columns:45mm 1fr;gap:5mm;align-items:start;margin:0 0 4mm;padding:3.5mm;border:.4mm solid #e2e8f0;border-radius:3mm}}.svc img{{width:45mm;height:34mm;object-fit:cover;border-radius:2mm}}
.svc ul,.checks{{padding-inline-start:5mm;margin:0}}.svc li{{font-size:9.5pt;line-height:1.7}}
.grid4{{display:grid;grid-template-columns:1fr 1fr;gap:3mm;list-style:none;padding:0}}.grid4 li{{padding:4mm;border-radius:3mm;background:#fef3c7}}.grid4 b{{display:block;color:#0f172a;font-weight:900}}.grid4 span{{font-size:9.5pt}}
.steps{{display:grid;grid-template-columns:repeat(3,1fr);gap:3mm;list-style:none;padding:0;counter-reset:s}}.steps li{{counter-increment:s;padding:4mm;border:.4mm solid #e2e8f0;border-radius:3mm}}
.steps li::before{{content:counter(s,decimal-leading-zero);display:block;color:#f59e0b;font-size:15pt;font-weight:900;line-height:1.2}}.steps b{{display:block;color:#0f172a}}.steps span{{font-size:9pt;color:#475569}}
.checks li{{margin-bottom:1.5mm}}
.gal{{display:grid;grid-template-columns:repeat(3,1fr);gap:3mm}}.gal figure{{margin:0}}.gal img{{width:100%;height:44mm;object-fit:cover;border-radius:2.5mm}}.gal figcaption{{font-size:8pt;color:#475569;line-height:1.5;margin-top:1mm}}
.refs{{width:100%;border-collapse:collapse;font-size:9pt}}.refs td{{border-bottom:.3mm solid #e2e8f0;padding:1.5mm}}
.contact{{margin-top:6mm;padding:6mm;border-radius:3mm;background:#0f172a;color:#cbd5e1}}.contact h2{{color:#fff;margin-top:0}}
.contact ul{{list-style:none;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:2mm 6mm}}.contact b{{display:block;color:#fbbf24;font-size:11pt}}.contact span{{font-size:8.5pt}}'''
    doc = f'<!doctype html><html lang="{lang}" dir="{t["dir"]}"><head><meta charset="utf-8"><title>{E(t["company"])} — {E(t["doc"])}</title><style>{css}</style></head><body>{p1}{p2}{p3}{p4}</body></html>'
    path = os.path.join(ROOT, f'profile-{lang}.html')
    with open(path, 'w', encoding='utf-8') as fh:
        fh.write(doc)
    print(path)


if __name__ == '__main__':
    build('en')
    build('ar')
