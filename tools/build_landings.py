#!/usr/bin/env python3
"""
Build the landing pages (international Arabic/English pages and the
Persian industrial pages) as HTML files in
asfaltbama-child/content/pages/landing/.

The markup uses the .lp-* classes styled by assets/css/landing.css and two
shortcodes from includes/landing.php: [abm_img] for the project photos and
[abm_contact] for the WhatsApp / email / phone buttons. The content lives
here so the pages share one structure; edit the texts and run:

    python3 tools/build_landings.py
"""

import html
import os

OUT = os.path.join(os.path.dirname(__file__), '..', 'asfaltbama-child', 'content', 'pages', 'landing')
SITE = 'https://asfaltbama.com/'

PAV = 'asfalt-mohavate-mojtame-maskoni-finisher-ghaltak.jpg'
PRIME = 'gheirpashi-bastar-asfalt-mohavate-mojtame.jpg'
BOB = 'bobcat-bargiri-nakhale-kamyon.jpg'
BOB2 = 'bargiri-khak-nakhale-bobcat-kargah-shahri.jpg'
ISO1 = 'isogam-poshtbam-hampooshani-darz-ghiri.jpg'
ISO2 = 'isogam-poshtbam-paye-kooler.jpg'
ISO3 = 'isogam-divar-jan-panah-labe-bam.jpg'
ISO4 = 'isogam-kenar-divar-mahiche-kesh.jpg'
ENG = 'mohandes-asfaltbama-25-sal-tajrobe.webp'

E = html.escape

# --------------------------------------------------------------------------
# Shared texts per language
# --------------------------------------------------------------------------

BASE = {
    'en': {
        'dir': 'ltr',
        'trust': [('25+', 'years of experience'), ('850+', 'completed projects'), ('Written', 'warranty on every job')],
        'badge': 'Asphalt · Isogam · Bitumen roofing',
        'services_kicker': 'What we do',
        'services_title': 'Three specialisms, one accountable team',
        'services_lead': 'We focus on the work we know best: hot-mix asphalt paving, isogam membrane waterproofing and traditional hot bitumen roofing.',
        'cards': [
            (PAV, 'Paver and tandem roller laying a compound yard', 'Asphalt paving',
             'Hot-mix asphalt laid with pavers and compacted with tandem rollers for factory and warehouse yards, car parks, compounds and villa driveways.',
             ['Heavy-duty two-layer pavements for trucks', 'Base preparation, prime coat and compaction', 'Falls and drainage built into the surface', 'Patching, milling and overlay']),
            (ISO1, 'Foil-faced isogam membrane with lapped, sealed seams', 'Isogam membrane waterproofing',
             'Torch-applied modified bitumen membranes (isogam), aluminium-foil faced, for flat concrete roofs: lapped, heat-sealed and turned up every parapet.',
             ['Primer, fillets and upstands', 'Seams lapped and sealed with heat', 'Detailing around AC units, ducts and drains', 'Flood test before handover']),
            (ISO4, 'Waterproofing detail at the roof-to-wall junction', 'Bitumen roofing (hot bitumen & jute)',
             'The proven two-layer system of hot bitumen and jute, ideal under tiles and screeds, for roofs, foundations and wet areas.',
             ['Two full layers laid crosswise', 'Blown bitumen for heat resistance', 'Protection layer against sun and traffic', 'Written warranty']),
        ],
        'band_title': 'Built for 50 °C summers',
        'band_text': 'Pavement surfaces in the region reach far above the air temperature. Soft binders rut under trucks and exposed roofing ages fast. Our specifications are chosen for heat.',
        'specs': [('Bitumen grade', 'Harder or polymer-modified grades where the specification allows'),
                  ('Compaction', 'Rolling completed while the mix is hot; night work in summer'),
                  ('Roof finish', 'Reflective aluminium-foil membranes or a protection layer'),
                  ('Drainage', 'Falls to outlets checked before paving and roofing')],
        'loc_kicker': 'Where we work',
        'steps_kicker': 'How we work',
        'steps_title': 'From your first message to handover',
        'steps': [('Enquiry', 'Send photos, drawings and approximate areas on WhatsApp or by email.'),
                  ('Technical review', 'We review scope, specification, climate, loads and drainage.'),
                  ('Written proposal', 'Method statement, layer thicknesses, materials, schedule and price.'),
                  ('Mobilisation', 'Our site team, with materials and equipment as agreed.'),
                  ('Execution & testing', 'Quality checks during the works and flood tests on roofs.'),
                  ('Handover', 'Handover with records and a written warranty.')],
        'gallery_kicker': 'Our work',
        'gallery_title': 'Project photos from our own sites',
        'gallery': [(PAV, 'Paving and compaction of a residential compound yard'),
                    (ISO1, 'Isogam membrane with lapped, heat-sealed seams'),
                    (PRIME, 'Prime coat on the compacted base before paving'),
                    (ISO3, 'Membrane turned up and over the parapet wall'),
                    (ISO2, 'Waterproofing around AC unit bases'),
                    (BOB, 'Site preparation and loading with a skid-steer loader'),
                    (ISO4, 'Detailing at the roof-to-wall junction')],
        'faq_title': 'Frequently asked questions',
        'faq': [('Do you work as a subcontractor?', 'Yes. We work for main contractors or directly for owners, following the project specification and the approvals required locally.'),
                ('Can you supply materials only?', 'Yes. Isogam membranes and bitumen can be supplied separately; quantities and delivery are agreed per order.'),
                ('How quickly can we get a price?', 'Once we receive photos or drawings with areas and the location, we reply with technical questions or a preliminary proposal.')],
        'cta_title': 'Tell us about your project',
        'cta_text': 'Send photos, drawings and approximate areas. We reply on WhatsApp or by email.',
        'other': 'Other languages',
    },
    'ar': {
        'dir': 'rtl',
        'trust': [('+٢٥', 'عاماً من الخبرة'), ('+٨٥٠', 'مشروعاً منجزاً'), ('ضمان', 'مكتوب لكل عمل')],
        'badge': 'أسفلت · إيزوجام · قير وجنفاص',
        'services_kicker': 'خدماتنا',
        'services_title': 'ثلاثة تخصصات وفريق واحد مسؤول',
        'services_lead': 'نركّز على ما نتقنه: رصف الأسفلت الساخن، العزل المائي بلفائف الإيزوجام، والعزل التقليدي بالقير والجنفاص.',
        'cards': [
            (PAV, 'الفرّاشة والحادلة أثناء رصف ساحة مجمع', 'رصف الأسفلت',
             'خلطات أسفلتية ساخنة تُفرش بالفرّاشة وتُدكّ بالحادلات لساحات المصانع والمستودعات ومواقف السيارات والمجمعات وممرات الفلل.',
             ['رصف بطبقتين مقاوم لأحمال الشاحنات', 'تجهيز طبقة الأساس ورش البرايم كوت والدكّ', 'ميول وتصريف مدروس للمياه', 'الترقيع والكشط وإعادة الرصف']),
            (ISO1, 'لفائف إيزوجام بوجه ألمنيوم مع وصلات متراكبة ومحكمة', 'العزل المائي بالإيزوجام',
             'لفائف البيتومين المعدّل (الإيزوجام) بوجه ألمنيوم تُلصق باللهب على الأسطح الخرسانية، مع تراكب الوصلات ورفع العزل على جدران السترة.',
             ['دهان تأسيسي وزوايا مدوّرة ورفعات', 'وصلات متراكبة ملحومة بالحرارة', 'تفاصيل حول قواعد المكيفات ومجاري الهواء والمصارف', 'اختبار الغمر بالماء قبل التسليم']),
            (ISO4, 'تفاصيل العزل عند التقاء السطح بالجدار', 'العزل بالقير والجنفاص',
             'النظام التقليدي المجرَّب بطبقتين من القير الساخن والجنفاص (الخيش)، مثالي تحت البلاط والصبّات، للأسطح والأساسات والحمامات.',
             ['طبقتان كاملتان بشكل متعامد', 'قير مؤكسد مقاوم للحرارة', 'طبقة حماية من الشمس والحركة', 'ضمان مكتوب']),
        ],
        'band_title': 'مصمَّم لصيف ٥٠ درجة مئوية',
        'band_text': 'تتجاوز حرارة سطح الأسفلت في المنطقة حرارة الجو بكثير؛ القير الطري يتخدد تحت الشاحنات والعزل المكشوف يتقادم بسرعة. لذلك نختار المواصفات لتتحمل الحرارة.',
        'specs': [('درجة القير', 'درجات أصلب أو قير معدّل بالبوليمر حيث تسمح المواصفات'),
                  ('الدكّ', 'إكمال الدكّ والخلطة ساخنة، والعمل ليلاً في الصيف'),
                  ('سطح العزل', 'لفائف عاكسة بوجه ألمنيوم أو طبقة حماية'),
                  ('التصريف', 'مراجعة الميول والمصارف قبل الرصف والعزل')],
        'loc_kicker': 'أين نعمل',
        'steps_kicker': 'آلية العمل',
        'steps_title': 'من رسالتكم الأولى حتى التسليم',
        'steps': [('الاستفسار', 'أرسلوا الصور والمخططات والمساحات التقريبية عبر واتساب أو البريد الإلكتروني.'),
                  ('الدراسة الفنية', 'نراجع نطاق العمل والمواصفات والمناخ والأحمال والتصريف.'),
                  ('العرض المكتوب', 'طريقة التنفيذ وسماكات الطبقات والمواد والجدول الزمني والسعر.'),
                  ('التجهيز', 'فريق التنفيذ لدينا مع المواد والمعدات حسب الاتفاق.'),
                  ('التنفيذ والاختبار', 'فحوصات الجودة أثناء العمل واختبار الغمر للأسطح.'),
                  ('التسليم', 'التسليم مع السجلات وضمان مكتوب.')],
        'gallery_kicker': 'أعمالنا',
        'gallery_title': 'صور من مواقع مشاريعنا',
        'gallery': [(PAV, 'رصف ودكّ ساحة مجمع سكني'),
                    (ISO1, 'لفائف إيزوجام بوصلات متراكبة ملحومة بالحرارة'),
                    (PRIME, 'رش البرايم كوت على طبقة الأساس قبل الرصف'),
                    (ISO3, 'رفع العزل على جدار السترة وتغطيته'),
                    (ISO2, 'العزل حول قواعد المكيفات'),
                    (BOB, 'تجهيز الموقع والتحميل بالبوبكات'),
                    (ISO4, 'تفاصيل العزل عند التقاء السطح بالجدار')],
        'faq_title': 'أسئلة شائعة',
        'faq': [('هل تعملون كمقاول باطن؟', 'نعم، نعمل لصالح المقاول الرئيسي أو مباشرة لصالح المالك، وفق مواصفات المشروع والموافقات المحلية.'),
                ('هل يمكن توريد المواد فقط؟', 'نعم، يمكن توريد لفائف الإيزوجام والقير بشكل منفصل، وتُتفق الكميات والتسليم لكل طلب.'),
                ('متى نحصل على عرض السعر؟', 'بعد استلام الصور أو المخططات مع المساحات والموقع، نرد بالأسئلة الفنية أو بعرض أولي.')],
        'cta_title': 'حدّثونا عن مشروعكم',
        'cta_text': 'أرسلوا الصور والمخططات والمساحات التقريبية، ونرد عليكم عبر واتساب أو البريد الإلكتروني.',
        'other': 'لغات أخرى',
    },
    'fa': {
        'dir': 'rtl',
        'trust': [('+۲۵', 'سال سابقه'), ('+۸۵۰', 'پروژه‌ی موفق'), ('کتبی', 'ضمانت‌نامه‌ی هر پروژه')],
        'badge': 'آسفالت · ایزوگام · قیرگونی',
        'services_kicker': 'خدمات صنعتی',
        'services_title': 'سه تخصص، یک تیم پاسخ‌گو',
        'services_lead': 'روی کارهایی تمرکز داریم که بهتر از همه بلدیم: آسفالت گرم محوطه، عایق‌کاری با ایزوگام و قیرگونی دولایه.',
        'cards': [
            (PAV, 'فینیشر و غلتک هنگام آسفالت محوطه', 'آسفالت محوطه‌ی صنعتی',
             'پخش آسفالت گرم با فینیشر و کوبش با غلتک برای محوطه‌ی کارخانه، انبار، سردخانه و پارکینگ کامیون؛ از زیرسازی تا رویه.',
             ['آسفالت دو لایه (بیندر + توپکا) برای کامیون', 'زیرسازی، قیرپاشی و کوبش اصولی', 'شیب‌بندی و دفع آب سطوح بزرگ', 'لکه‌گیری، تراش و روکش']),
            (ISO1, 'ایزوگام فویل‌دار با هم‌پوشانی و درزگیری', 'ایزوگام بام سوله و انبار',
             'نصب ایزوگام فویل‌دار روی بام بتنی ساختمان‌های صنعتی و اداری؛ هم‌پوشانی درزها، ماهیچه‌کشی و بالا آوردن عایق روی جان‌پناه.',
             ['پرایمر، ماهیچه و برگشت روی دیوار', 'درزهای هم‌پوشان جوش‌خورده با حرارت', 'آب‌بندی دور چیلر، کانال و ناودان', 'تست آب پیش از تحویل']),
            (ISO4, 'جزئیات عایق در محل اتصال بام به دیوار', 'قیرگونی دولایه',
             'قیرگونی سنتی با دو لایه قیر داغ و گونی؛ مناسب زیر موزاییک و کف‌سازی، برای بام، پی و سرویس‌ها.',
             ['دو لایه‌ی کامل عمود بر هم', 'قیر دمیده‌ی مقاوم در برابر گرما', 'لایه‌ی محافظ در برابر آفتاب', 'ضمانت‌نامه‌ی کتبی']),
        ],
        'band_title': 'اجرای بدون توقف تولید',
        'band_text': 'در کارخانه‌ها هر ساعت توقف بارگیری هزینه دارد. محوطه و بام را بخش‌بندی می‌کنیم و کار را با برنامه‌ی تولید و بارگیری هماهنگ می‌کنیم.',
        'specs': [('بخش‌بندی', 'اجرای مرحله‌ای محوطه با مسیر جایگزین برای کامیون‌ها'),
                  ('زمان‌بندی', 'کار در ساعات کم‌تردد، شب یا روزهای تعطیل'),
                  ('بار سنگین', 'ضخامت و زیرسازی متناسب با سنگین‌ترین خودرو'),
                  ('بام', 'آب‌بندی بخش‌به‌بخش بدون آسیب به تجهیزات')],
        'loc_kicker': 'مناطق صنعتی',
        'steps_kicker': 'روند کار',
        'steps_title': 'از تماس اول تا تحویل',
        'steps': [('بازدید رایگان', 'بازدید از محوطه و بام، اندازه‌گیری و بررسی بستر و شیب‌ها.'),
                  ('بررسی فنی', 'تعیین ضخامت، نوع آسفالت و نوع عایق متناسب با بار و کاربری.'),
                  ('پیشنهاد مکتوب', 'مشخصات فنی، زمان‌بندی مرحله‌ای، قیمت و ضمانت.'),
                  ('اجرا', 'با ماشین‌آلات و تیم خود شرکت، هماهنگ با برنامه‌ی کارخانه.'),
                  ('کنترل و تست', 'کنترل ضخامت و تراکم، تست آب‌بندی بام.'),
                  ('تحویل', 'تحویل همراه با ضمانت‌نامه‌ی کتبی.')],
        'gallery_kicker': 'نمونه‌کارها',
        'gallery_title': 'عکس‌هایی از کارگاه‌های خودمان',
        'gallery': [(PAV, 'پخش و کوبش آسفالت محوطه‌ی مجتمع'),
                    (ISO1, 'ایزوگام با هم‌پوشانی و درزگیری'),
                    (PRIME, 'قیرپاشی بستر پیش از آسفالت'),
                    (ISO3, 'ایزوگام دیوار جان‌پناه و لبه‌ی بام'),
                    (ISO2, 'آب‌بندی دور پایه‌ی کولرها'),
                    (BOB, 'آماده‌سازی و بارگیری با بابکت'),
                    (ISO4, 'ایزوگام محل اتصال بام به دیوار')],
        'faq_title': 'سؤالات متداول',
        'faq': [('آسفالت محوطه و عایق بام را با هم اجرا می‌کنید؟', 'بله؛ هر دو کار با تیم و ماشین‌آلات خود شرکت و در یک قرارداد و یک برنامه‌ی زمان‌بندی انجام می‌شود.'),
                ('کارخانه در حین کار باید تعطیل شود؟', 'خیر؛ محوطه و بام بخش‌بندی می‌شوند و کار در ساعات کم‌تردد یا روزهای تعطیل انجام می‌شود.'),
                ('بازدید هزینه دارد؟', 'بازدید و برآورد اولیه رایگان است و پس از آن پیشنهاد قیمت و مشخصات فنی مکتوب ارائه می‌شود.')],
        'cta_title': 'بازدید رایگان کارخانه یا سوله',
        'cta_text': 'عکس و متراژ تقریبی را در واتساپ بفرستید یا تماس بگیرید تا برای بازدید هماهنگ کنیم.',
        'other': 'زبان‌های دیگر',
    },
}

LANG_LINKS = {
    'hub': {'en': 'asphalt-contractor-middle-east', 'ar': 'asphalt-contractor-middle-east-ar'},
    'iraq': {'en': 'asphalt-waterproofing-iraq', 'ar': 'asphalt-waterproofing-iraq-ar'},
    'kuwait': {'en': 'asphalt-waterproofing-kuwait', 'ar': 'asphalt-waterproofing-kuwait-ar'},
    'oman': {'en': 'asphalt-waterproofing-oman', 'ar': 'asphalt-waterproofing-oman-ar'},
    'bahrain': {'en': 'asphalt-waterproofing-bahrain', 'ar': 'asphalt-waterproofing-bahrain-ar'},
    'uae': {'en': 'asphalt-waterproofing-dubai-uae', 'ar': 'asphalt-waterproofing-dubai-uae-ar'},
}
LANG_NAMES = {'en': 'English', 'ar': 'العربية', 'fa': 'فارسی'}

# --------------------------------------------------------------------------
# Section builders
# --------------------------------------------------------------------------


def img(file, alt, cls='', eager=False, caption=''):
    attrs = f'file="{file}" alt="{E(alt)}"'
    if cls:
        attrs += f' class="{cls}"'
    if eager:
        attrs += ' eager="1"'
    if caption:
        attrs += f' caption="{E(caption)}"'
    return f'[abm_img {attrs}]'


def hero(lang, p):
    b = BASE[lang]
    photos = p.get('hero_photos', [PAV, ISO1, ISO3])
    alts = p.get('hero_alts', [b['cards'][0][1], b['cards'][1][1], b['gallery'][3][1]])
    trust = ''.join(f'<li><b>{E(n)}</b><span>{E(t)}</span></li>' for n, t in b['trust'])
    return f'''<section class="lp-hero">
<div class="lp-wrap lp-hero__grid">
<div class="lp-hero__text">
<span class="lp-eyebrow">{E(p['eyebrow'])}</span>
<h1>{E(p['h1'])}</h1>
<p class="lp-lead">{p['lead']}</p>
[abm_contact lang="{lang}" style="hero"]
<ul class="lp-trust">{trust}</ul>
</div>
<div class="lp-hero__media">
{img(photos[0], alts[0], 'lp-ph lp-ph--main', eager=True)}
{img(photos[1], alts[1], 'lp-ph lp-ph--top', eager=True)}
{img(photos[2], alts[2], 'lp-ph lp-ph--bottom', eager=True)}
<span class="lp-hero__badge">{E(b['badge'])}</span>
</div>
</div>
<div class="lp-road" aria-hidden="true"></div>
</section>'''


def services(lang, p):
    b = BASE[lang]
    cards = ''
    for i, (photo, alt, title, text, checks) in enumerate(p.get('cards', b['cards']), 1):
        lis = ''.join(f'<li>{E(c)}</li>' for c in checks)
        cards += f'''<article class="lp-card">
<div class="lp-card__media">{img(photo, alt, 'lp-card__img')}<span class="lp-card__num">0{i}</span></div>
<div class="lp-card__body"><h3>{E(title)}</h3><p>{E(text)}</p><ul class="lp-checks">{lis}</ul></div>
</article>
'''
    return f'''<section class="lp-sec">
<div class="lp-wrap">
<div class="lp-head"><span class="lp-kicker">{E(b['services_kicker'])}</span><h2>{E(p.get('services_title', b['services_title']))}</h2><p>{E(p.get('services_lead', b['services_lead']))}</p></div>
<div class="lp-cards">
{cards}</div>
</div>
</section>'''


def band(lang, p):
    b = BASE[lang]
    specs = ''.join(f'<li><b>{E(k)}</b><span>{E(v)}</span></li>' for k, v in b['specs'])
    return f'''<section class="lp-band">
<div class="lp-wrap lp-band__grid">
<div class="lp-band__text"><h2>{E(p.get('band_title', b['band_title']))}</h2><p>{E(p.get('band_text', b['band_text']))}</p></div>
<ul class="lp-specs">{specs}</ul>
</div>
</section>'''


def locations(lang, p):
    b = BASE[lang]
    items = ''
    for loc in p['locations']:
        name, detail = loc[0], loc[1]
        href = loc[2] if len(loc) > 2 else ''
        inner = f'<h3>{E(name)}</h3><p>{E(detail)}</p>'
        if href:
            items += f'<a class="lp-loc lp-loc--link" href="{SITE}{href}/">{inner}<span class="lp-loc__go" aria-hidden="true">←</span></a>\n' if b['dir'] == 'rtl' else f'<a class="lp-loc lp-loc--link" href="{SITE}{href}/">{inner}<span class="lp-loc__go" aria-hidden="true">→</span></a>\n'
        else:
            items += f'<div class="lp-loc">{inner}</div>\n'
    lead = f'<p>{E(p["loc_lead"])}</p>' if p.get('loc_lead') else ''
    return f'''<section class="lp-sec lp-sec--soft">
<div class="lp-wrap">
<div class="lp-head"><span class="lp-kicker">{E(b['loc_kicker'])}</span><h2>{E(p['loc_title'])}</h2>{lead}</div>
<div class="lp-locs">
{items}</div>
</div>
</section>'''


def steps(lang, p):
    b = BASE[lang]
    lis = ''.join(f'<li><b>{E(t)}</b><span>{E(d)}</span></li>' for t, d in b['steps'])
    return f'''<section class="lp-sec">
<div class="lp-wrap">
<div class="lp-head"><span class="lp-kicker">{E(b['steps_kicker'])}</span><h2>{E(b['steps_title'])}</h2></div>
<ol class="lp-steps">{lis}</ol>
</div>
</section>'''


def gallery(lang, p):
    b = BASE[lang]
    figs = '\n'.join(img(f, c, 'lp-g', caption=c) for f, c in p.get('gallery', b['gallery']))
    return f'''<section class="lp-sec lp-sec--dark">
<div class="lp-wrap">
<div class="lp-head lp-head--light"><span class="lp-kicker">{E(b['gallery_kicker'])}</span><h2>{E(b['gallery_title'])}</h2></div>
<div class="lp-gallery">
{figs}
</div>
</div>
</section>'''


def faq(lang, p):
    b = BASE[lang]
    items = ''.join(f'<details class="lp-faq__item"><summary>{E(q)}</summary><p>{E(a)}</p></details>\n' for q, a in p.get('faq', []) + b['faq'])
    return f'''<section class="lp-sec">
<div class="lp-wrap lp-wrap--narrow">
<div class="lp-head"><h2>{E(b['faq_title'])}</h2></div>
<div class="lp-faq">
{items}</div>
</div>
</section>'''


def cta(lang, p):
    b = BASE[lang]
    links = ''
    group = p.get('group')
    if group and group in LANG_LINKS:
        others = [(l, s) for l, s in LANG_LINKS[group].items() if l != lang]
        if lang != 'fa':
            others.append(('fa', 'industrial-asphalt-waterproofing'))
        links = '<p class="lp-langs">' + E(b['other']) + ': ' + ' · '.join(
            f'<a href="{SITE}{s}/" lang="{l}">{LANG_NAMES[l]}</a>' for l, s in others) + '</p>'
    guides = {
        'en': ('asphalt-waterproofing-guides', 'Technical guides: asphalt paving, Isogam and bitumen waterproofing'),
        'ar': ('asphalt-waterproofing-guides-ar', 'الأدلة الفنية: رصف الأسفلت والعزل بالإيزوجام والقير'),
    }
    if lang in guides:
        links += f'<p class="lp-langs"><a href="{SITE}{guides[lang][0]}/">{E(guides[lang][1])}</a></p>'
    return f'''<section class="lp-cta">
<div class="lp-wrap">
<h2>{E(p.get('cta_title', b['cta_title']))}</h2>
<p>{E(p.get('cta_text', b['cta_text']))}</p>
[abm_contact lang="{lang}" style="band"]
{links}
</div>
</section>'''


def page(lang, p):
    parts = [hero(lang, p), services(lang, p)]
    if p.get('intro'):
        parts.insert(1, f'<section class="lp-sec lp-sec--intro"><div class="lp-wrap lp-wrap--narrow lp-intro">{p["intro"]}</div></section>')
    parts += [band(lang, p), locations(lang, p), steps(lang, p), gallery(lang, p), faq(lang, p), cta(lang, p)]
    return '\n'.join(parts) + '\n'


# --------------------------------------------------------------------------
# Pages
# --------------------------------------------------------------------------

PAGES = {}

# ---------------------------- English -------------------------------------

PAGES['asphalt-contractor-middle-east'] = ('en', {
    'group': 'hub',
    'eyebrow': 'Iraq · Kuwait · Oman · Bahrain · UAE',
    'h1': 'Asphalt, Isogam & Bitumen Roofing Contractor for Iraq and the Gulf',
    'lead': '<strong>Asfaltbama</strong> (Ofogh Apadana Pasargad Co.) paves industrial yards, car parks and compounds and waterproofs flat roofs with isogam membranes and hot bitumen and jute. 25 years of experience, our own site team, a written warranty.',
    'loc_title': 'Countries and cities we serve',
    'loc_lead': 'Choose your country for climate-specific recommendations.',
    'locations': [
        ('Iraq', 'Karbala, Najaf, Basra and Erbil: hotels, compounds, factories and yards.', 'asphalt-waterproofing-iraq'),
        ('Kuwait', 'Kuwait City, Shuwaikh, Sabhan and Ahmadi: roofs and yards in extreme heat.', 'asphalt-waterproofing-kuwait'),
        ('Oman', 'Muscat, Sohar, Barka and Nizwa: drainage-first paving and roofing.', 'asphalt-waterproofing-oman'),
        ('Bahrain', 'Manama, Muharraq, Sitra and Hidd: coastal humidity and compact sites.', 'asphalt-waterproofing-bahrain'),
        ('Dubai & UAE', 'Dubai, Sharjah and Ajman: warehouse yards and reflective roof membranes.', 'asphalt-waterproofing-dubai-uae'),
    ],
})

PAGES['asphalt-waterproofing-iraq'] = ('en', {
    'group': 'iraq',
    'eyebrow': 'Karbala · Najaf · Basra · Erbil',
    'h1': 'Asphalt Paving, Isogam & Bitumen Roofing in Iraq',
    'lead': '<strong>Asfaltbama</strong> paves yards, car parks and compounds and waterproofs flat roofs in Iraq, with specifications chosen for 50 °C summers, dust and, in the Kurdistan Region, frost.',
    'loc_title': 'Karbala, Najaf, Basra and Erbil',
    'loc_lead': 'Every city has its own conditions. This is what we plan for in each.',
    'locations': [
        ('Karbala', 'Hotels, hussainiyas, pilgrim accommodation and car parks around the shrines carry enormous seasonal crowds. Roofs must stay watertight and pavements must carry buses and service vehicles; we schedule works outside peak visitation.'),
        ('Najaf', 'Fast-growing hotels, residential compounds and commercial buildings. Flat roofs under extreme sun need foil-faced isogam or protected bitumen roofing; car parks and yards need rut-resistant asphalt.'),
        ('Basra', 'Oil and port industry, very high heat and humidity, and saline soils. Strong bases, drainage and heat-resistant binders for yards, and careful surface preparation before roofing.'),
        ('Erbil', 'A construction boom and cold winters with frost and snow. Drainage, crack sealing before winter and membranes that stay flexible in the cold.'),
    ],
    'faq': [('Which Iraqi cities do you work in?', 'Mainly Karbala, Najaf, Basra and Erbil, and other cities depending on project size.')],
})

PAGES['asphalt-waterproofing-kuwait'] = ('en', {
    'group': 'kuwait',
    'eyebrow': 'Kuwait City · Shuwaikh · Sabhan · Ahmadi',
    'h1': 'Asphalt Paving, Isogam & Bitumen Roofing in Kuwait',
    'lead': 'Kuwait has some of the hottest summers on earth. <strong>Asfaltbama</strong> paves warehouse and factory yards and car parks, and waterproofs flat roofs with reflective isogam membranes and protected bitumen roofing built for that heat.',
    'loc_title': 'Where we work in Kuwait',
    'locations': [
        ('Kuwait City', 'Commercial and residential buildings: roof waterproofing with reflective membranes and car park paving.'),
        ('Shuwaikh & Sabhan industrial areas', 'Warehouse and workshop yards under heavy trucks: two-layer pavements and drainage.'),
        ('Ahmadi & the south', 'Industrial and oil-sector sites: heat-resistant binders, strong bases and roofs protected from sun.'),
    ],
    'faq': [('Can paving be done in summer?', 'Yes, usually at night or early morning to keep control of mix temperature and site conditions, subject to site rules.')],
})

PAGES['asphalt-waterproofing-oman'] = ('en', {
    'group': 'oman',
    'eyebrow': 'Muscat · Sohar · Barka · Nizwa',
    'h1': 'Asphalt Paving, Isogam & Bitumen Roofing in Oman',
    'lead': 'Oman combines intense heat, coastal humidity and short, violent rainstorms. <strong>Asfaltbama</strong> paves yards and car parks and waterproofs flat roofs with drainage planned first.',
    'band_title': 'Heat, humidity and sudden rain',
    'band_text': 'Water that cannot leave a surface soaks into the base and finds every weak roof joint. We check levels, falls and outlets before paving or roofing.',
    'loc_title': 'Where we work in Oman',
    'locations': [
        ('Muscat', 'Villas, compounds and commercial buildings: roof waterproofing and driveway and car park paving.'),
        ('Sohar', 'Industrial and port-related sites: heavy-duty yards and warehouse roofs.'),
        ('Barka & Nizwa', 'Residential and commercial projects: roofs and yards with careful drainage.'),
    ],
})

PAGES['asphalt-waterproofing-bahrain'] = ('en', {
    'group': 'bahrain',
    'eyebrow': 'Manama · Muharraq · Sitra · Hidd',
    'h1': 'Asphalt Paving, Isogam & Bitumen Roofing in Bahrain',
    'lead': 'Coastal humidity, salt air and summer heat are hard on roofs and pavements. <strong>Asfaltbama</strong> waterproofs flat roofs with isogam membranes and bitumen roofing and paves yards and car parks on compact urban sites.',
    'band_title': 'Humid, salty and hot',
    'band_text': 'Membranes only bond to clean, dry surfaces, and compact sites need careful logistics. We time the works around humidity and traffic and protect exposed roofing from sun.',
    'loc_title': 'Where we work in Bahrain',
    'locations': [
        ('Manama & Muharraq', 'Residential and commercial roofs; car park and courtyard paving on tight urban sites.'),
        ('Sitra & Hidd', 'Industrial and warehouse yards and roofs near the coast.'),
    ],
})

PAGES['asphalt-waterproofing-dubai-uae'] = ('en', {
    'group': 'uae',
    'eyebrow': 'Dubai · Sharjah · Ajman',
    'h1': 'Asphalt Paving, Isogam & Bitumen Roofing in Dubai and the UAE',
    'lead': '<strong>Asfaltbama</strong> paves warehouse and logistics yards, car parks and villa driveways and waterproofs flat roofs with reflective isogam membranes, working to the project specification and approvals.',
    'loc_title': 'Where we work in the UAE',
    'locations': [
        ('Dubai', 'Industrial and logistics areas, car parks and villas: heavy-duty paving and reflective roof membranes.'),
        ('Sharjah & Ajman', 'Warehouses, workshops and residential buildings: yards and roof waterproofing.'),
    ],
    'faq': [('Do you work with consultants and main contractors?', 'Yes. We submit method statements and material data for approval, follow the inspection and test plan and hand over roofs with flood-test records.')],
})

# ---------------------------- Arabic --------------------------------------

PAGES['asphalt-contractor-middle-east-ar'] = ('ar', {
    'group': 'hub',
    'eyebrow': 'العراق · الكويت · عُمان · البحرين · الإمارات',
    'h1': 'مقاول أسفلت وإيزوجام وعزل بالقير والجنفاص في العراق والخليج',
    'lead': '<strong>أسفلت با ما</strong> (شركة أفق آبادانا باسارجاد) ترصف الساحات الصناعية ومواقف السيارات والمجمعات، وتعزل الأسطح بلفائف الإيزوجام وبالقير والجنفاص. خبرة ٢٥ عاماً، فريق تنفيذ خاص بنا، وضمان مكتوب.',
    'loc_title': 'الدول والمدن التي نعمل فيها',
    'loc_lead': 'اختاروا بلدكم للاطلاع على توصيات مناسبة لمناخه.',
    'locations': [
        ('العراق', 'كربلاء والنجف والبصرة وأربيل: فنادق ومجمعات ومصانع وساحات.', 'asphalt-waterproofing-iraq-ar'),
        ('الكويت', 'مدينة الكويت والشويخ وصبحان والأحمدي: أسطح وساحات في حرارة قصوى.', 'asphalt-waterproofing-kuwait-ar'),
        ('سلطنة عُمان', 'مسقط وصحار وبركاء ونزوى: رصف وعزل مع أولوية للتصريف.', 'asphalt-waterproofing-oman-ar'),
        ('البحرين', 'المنامة والمحرق وسترة والحد: رطوبة ساحلية ومواقع ضيقة.', 'asphalt-waterproofing-bahrain-ar'),
        ('دبي والإمارات', 'دبي والشارقة وعجمان: ساحات مستودعات وعزل عاكس للأسطح.', 'asphalt-waterproofing-dubai-uae-ar'),
    ],
})

PAGES['asphalt-waterproofing-iraq-ar'] = ('ar', {
    'group': 'iraq',
    'eyebrow': 'كربلاء · النجف · البصرة · أربيل',
    'h1': 'رصف الأسفلت والعزل بالإيزوجام والقير والجنفاص في العراق',
    'lead': '<strong>أسفلت با ما</strong> ترصف الساحات ومواقف السيارات والمجمعات وتعزل الأسطح في العراق، بمواصفات مختارة لصيف ٥٠ درجة والغبار، ولصقيع إقليم كردستان.',
    'loc_title': 'كربلاء والنجف والبصرة وأربيل',
    'loc_lead': 'لكل مدينة ظروفها، وهذا ما نخطط له في كل منها.',
    'locations': [
        ('كربلاء', 'فنادق وحسينيات ومواكب ومواقف سيارات حول العتبات المقدسة تستقبل حشوداً هائلة في المواسم؛ يجب أن تبقى الأسطح محكمة وأن تتحمل الساحات الحافلات.', 'asphalt-waterproofing-karbala'),
        ('النجف', 'نمو سريع للفنادق والمجمعات السكنية والتجارية؛ أسطح تحت شمس قاسية تحتاج إيزوجام بوجه ألمنيوم، وساحات تحتاج أسفلتاً مقاوماً للتخدد.', 'asphalt-waterproofing-najaf'),
        ('البصرة', 'صناعة نفطية وموانئ، حرارة ورطوبة عالية جداً وتربة مالحة؛ أساسات قوية وتصريف وقير يتحمل الحرارة.', 'asphalt-waterproofing-basra'),
        ('أربيل', 'نهضة عمرانية وشتاء بارد مع صقيع وثلوج؛ تصريف وسد الشقوق قبل الشتاء وعزل يبقى مرناً في البرد.', 'asphalt-waterproofing-erbil'),
    ],
    'faq': [('في أي مدن العراق تعملون؟', 'أساساً في كربلاء والنجف والبصرة وأربيل، وفي مدن أخرى حسب حجم المشروع.')],
})

IRAQ_CITIES_AR = {
    'asphalt-waterproofing-karbala': {
        'city': 'كربلاء',
        'eyebrow': 'كربلاء المقدسة',
        'lead': 'تستقبل كربلاء ملايين الزائرين في المواسم، وتعمل فنادقها وحسينياتها ومواكبها ومواقف سياراتها بكامل طاقتها. <strong>أسفلت با ما</strong> تعزل الأسطح بالإيزوجام والقير والجنفاص وترصف الساحات ومواقف السيارات بمواصفات تتحمل الحرارة والأحمال.',
        'band_title': 'العمل قبل المواسم لا أثناءها',
        'band_text': 'نخطط لتنفيذ العزل والرصف قبل مواسم الزيارة الكبرى، ونقسم الأعمال إلى مراحل حتى تبقى الفنادق والمواقف في الخدمة.',
        'locations': [
            ('فنادق ومباني الزائرين', 'أسطح واسعة عليها مكيفات وخزانات؛ عزل متصل بالإيزوجام مع تفاصيل دقيقة حول كل قاعدة وأنبوب.'),
            ('الحسينيات والمواكب', 'أسطح وساحات تستقبل حشوداً كبيرة؛ عزل متين وأسفلت لممرات الخدمة.'),
            ('مواقف السيارات والحافلات', 'رصف بطبقتين مقاوم للتخدد تحت الحافلات، مع ميول ومصارف.'),
        ],
        'faq': [('هل يمكن التنفيذ دون إغلاق الفندق؟', 'نعم، يُقسَّم السطح والساحة إلى أجزاء ويُنفَّذ كل جزء على حدة.')],
    },
    'asphalt-waterproofing-najaf': {
        'city': 'النجف',
        'eyebrow': 'النجف الأشرف',
        'lead': 'تشهد النجف نمواً سريعاً في الفنادق والمجمعات السكنية والمباني التجارية. <strong>أسفلت با ما</strong> تعزل الأسطح المستوية بالإيزوجام بوجه ألمنيوم وبالقير والجنفاص، وترصف ساحات المجمعات ومواقف السيارات.',
        'locations': [
            ('الفنادق والمباني التجارية', 'عزل عاكس يحمي السطح من الشمس ويخفف الحرارة داخل المبنى.'),
            ('المجمعات السكنية', 'رصف الساحات والطرق الداخلية والمواقف مع تصريف مياه الأمطار.'),
            ('المباني قيد الإنشاء', 'عزل الأساسات والأسطح بالقير والجنفاص أو الإيزوجام قبل التشطيب.'),
        ],
    },
    'asphalt-waterproofing-basra': {
        'city': 'البصرة',
        'eyebrow': 'البصرة · الموانئ والصناعة',
        'lead': 'في البصرة تجتمع الحرارة الشديدة والرطوبة العالية والتربة المالحة مع حركة صناعية ونفطية كثيفة. <strong>أسفلت با ما</strong> ترصف الساحات الصناعية والمستودعات بطبقتين وتعزل الأسطح بالإيزوجام والقير والجنفاص.',
        'band_title': 'حرارة ورطوبة وتربة مالحة',
        'band_text': 'التربة المالحة والمياه الجوفية القريبة تُضعف الأساس؛ لذلك نبدأ بطبقة أساس قوية وتصريف جيد، ونجهّز الأسطح جافة ونظيفة قبل العزل.',
        'locations': [
            ('الساحات الصناعية والمستودعات', 'رصف بطبقتين (رابطة وسطحية) لأحمال الشاحنات والمقطورات.'),
            ('المواقع النفطية والمينائية', 'أساسات قوية وقير يتحمل الحرارة وميول للتصريف.'),
            ('المباني السكنية والتجارية', 'عزل الأسطح بالإيزوجام العاكس أو بالقير والجنفاص مع طبقة حماية.'),
        ],
    },
    'asphalt-waterproofing-erbil': {
        'city': 'أربيل',
        'eyebrow': 'أربيل · إقليم كردستان',
        'lead': 'تشهد أربيل نهضة عمرانية، وشتاؤها بارد مع صقيع وثلوج. <strong>أسفلت با ما</strong> ترصف الساحات والمجمعات والمواقف وتعزل الأسطح بالإيزوجام والقير والجنفاص بمواصفات تناسب الحر صيفاً والبرد شتاءً.',
        'band_title': 'صيف حار وشتاء بصقيع',
        'band_text': 'الماء المتجمد داخل الشقوق يوسّعها ويصنع الحفر؛ لذلك نهتم بالميول والتصريف ونسد الشقوق قبل الشتاء، ونختار عزلاً يبقى مرناً في البرد.',
        'locations': [
            ('المجمعات السكنية', 'رصف الساحات والطرق الداخلية والمواقف مع تصريف مياه الأمطار والثلوج.'),
            ('المباني التجارية والفنادق', 'عزل الأسطح بالإيزوجام مع تفاصيل دقيقة حول المعدات.'),
            ('الورش والمستودعات', 'ساحات بطبقتين لأحمال الشاحنات.'),
        ],
    },
}

for slug, c in IRAQ_CITIES_AR.items():
    PAGES[slug] = ('ar', {
        'group': None,
        'eyebrow': c['eyebrow'],
        'h1': f'رصف الأسفلت والعزل بالإيزوجام والقير والجنفاص في {c["city"]}',
        'lead': c['lead'],
        'band_title': c.get('band_title', BASE['ar']['band_title']),
        'band_text': c.get('band_text', BASE['ar']['band_text']),
        'loc_title': f'مشاريعنا في {c["city"]}',
        'locations': c['locations'] + [('مدن عراقية أخرى', 'كربلاء والنجف والبصرة وأربيل.', 'asphalt-waterproofing-iraq-ar')],
        'faq': c.get('faq', []),
        'cta_title': f'مشروعكم في {c["city"]}',
    })

PAGES['asphalt-waterproofing-kuwait-ar'] = ('ar', {
    'group': 'kuwait',
    'eyebrow': 'مدينة الكويت · الشويخ · صبحان · الأحمدي',
    'h1': 'رصف الأسفلت والعزل بالإيزوجام والقير والجنفاص في الكويت',
    'lead': 'صيف الكويت من الأشد حرارة في العالم. <strong>أسفلت با ما</strong> ترصف ساحات المستودعات والمصانع ومواقف السيارات، وتعزل الأسطح بلفائف إيزوجام عاكسة وبالقير والجنفاص المحمي، بمواصفات تتحمل هذه الحرارة.',
    'loc_title': 'أين نعمل في الكويت',
    'locations': [
        ('مدينة الكويت', 'مبانٍ تجارية وسكنية: عزل الأسطح بلفائف عاكسة ورصف المواقف.'),
        ('الشويخ وصبحان الصناعيتان', 'ساحات المستودعات والورش تحت الشاحنات: رصف بطبقتين وتصريف.'),
        ('الأحمدي والجنوب', 'مواقع صناعية ونفطية: قير يتحمل الحرارة وأساسات قوية وأسطح محمية من الشمس.'),
    ],
    'faq': [('هل يمكن الرصف في الصيف؟', 'نعم، غالباً ليلاً أو في الصباح الباكر للتحكم في حرارة الخلطة، وفق أنظمة الموقع.')],
})

PAGES['asphalt-waterproofing-oman-ar'] = ('ar', {
    'group': 'oman',
    'eyebrow': 'مسقط · صحار · بركاء · نزوى',
    'h1': 'رصف الأسفلت والعزل بالإيزوجام والقير والجنفاص في سلطنة عُمان',
    'lead': 'تجمع عُمان بين الحرارة الشديدة ورطوبة الساحل والأمطار الغزيرة المفاجئة. <strong>أسفلت با ما</strong> ترصف الساحات والمواقف وتعزل الأسطح مع جعل التصريف أولوية.',
    'band_title': 'حرارة ورطوبة وأمطار مفاجئة',
    'band_text': 'الماء الذي لا يغادر السطح يتسرب إلى طبقة الأساس ويجد كل وصلة ضعيفة في العزل؛ لذلك نراجع المناسيب والميول والمصارف قبل الرصف والعزل.',
    'loc_title': 'أين نعمل في عُمان',
    'locations': [
        ('مسقط', 'فلل ومجمعات ومبانٍ تجارية: عزل الأسطح ورصف الممرات والمواقف.'),
        ('صحار', 'مواقع صناعية ومينائية: ساحات بطبقتين وأسطح مستودعات.'),
        ('بركاء ونزوى', 'مشاريع سكنية وتجارية: أسطح وساحات مع تصريف مدروس.'),
    ],
})

PAGES['asphalt-waterproofing-bahrain-ar'] = ('ar', {
    'group': 'bahrain',
    'eyebrow': 'المنامة · المحرق · سترة · الحد',
    'h1': 'رصف الأسفلت والعزل بالإيزوجام والقير والجنفاص في البحرين',
    'lead': 'الرطوبة الساحلية وهواء البحر المالح وحرارة الصيف قاسية على الأسطح والأسفلت. <strong>أسفلت با ما</strong> تعزل الأسطح بالإيزوجام والقير والجنفاص، وترصف الساحات والمواقف في المواقع الحضرية الضيقة.',
    'band_title': 'رطوبة وملوحة وحرارة',
    'band_text': 'لا يلتصق العزل إلا على سطح نظيف وجاف، والمواقع الضيقة تحتاج إلى تنظيم دقيق؛ لذلك نوقّت الأعمال حسب الرطوبة والحركة ونحمي العزل المكشوف من الشمس.',
    'loc_title': 'أين نعمل في البحرين',
    'locations': [
        ('المنامة والمحرق', 'أسطح سكنية وتجارية، ورصف المواقف والساحات في المواقع الضيقة.'),
        ('سترة والحد', 'ساحات وأسطح صناعية ومستودعات قرب الساحل.'),
    ],
})

PAGES['asphalt-waterproofing-dubai-uae-ar'] = ('ar', {
    'group': 'uae',
    'eyebrow': 'دبي · الشارقة · عجمان',
    'h1': 'رصف الأسفلت والعزل بالإيزوجام والقير والجنفاص في دبي والإمارات',
    'lead': '<strong>أسفلت با ما</strong> ترصف ساحات المستودعات والمناطق اللوجستية ومواقف السيارات وممرات الفلل، وتعزل الأسطح بلفائف إيزوجام عاكسة، وفق مواصفات المشروع والموافقات.',
    'loc_title': 'أين نعمل في الإمارات',
    'locations': [
        ('دبي', 'المناطق الصناعية واللوجستية والمواقف والفلل: رصف مقاوم للأحمال وعزل عاكس للأسطح.'),
        ('الشارقة وعجمان', 'مستودعات وورش ومبانٍ سكنية: ساحات وعزل أسطح.'),
    ],
    'faq': [('هل تعملون مع الاستشاريين والمقاولين الرئيسيين؟', 'نعم، نقدم طريقة التنفيذ وبيانات المواد للاعتماد، ونتبع خطة الفحص، ونسلّم الأسطح مع سجلات اختبار الغمر.')],
})

# ---------------------------- Persian -------------------------------------

PAGES['industrial-asphalt-waterproofing'] = ('fa', {
    'group': None,
    'eyebrow': 'کارخانه‌ها · سوله‌ها · انبارها · شهرک‌های صنعتی',
    'h1': 'آسفالت کارخانه و عایق‌کاری صنعتی؛ محوطه، قیرگونی و ایزوگام سوله',
    'lead': 'محوطه و بام کارخانه با ساختمان مسکونی قابل مقایسه نیست: کامیون روی آسفالت ترمز می‌کند و دور می‌زند و نشتی بام سوله می‌تواند محصول و تجهیزات را از بین ببرد. <strong>آسفالت با ما</strong> با ۲۵ سال سابقه، آسفالت محوطه و ایزوگام و قیرگونی بام واحدهای صنعتی را با ماشین‌آلات و تیم خود اجرا می‌کند.',
    'hero_photos': [PAV, ISO1, ENG],
    'hero_alts': ['پخش آسفالت محوطه با فینیشر و غلتک', 'ایزوگام فویل‌دار با هم‌پوشانی', 'مهندس اجرایی آسفالت با ما؛ ۲۵ سال تجربه'],
    'loc_title': 'شهرک‌ها و مناطق صنعتی تحت پوشش',
    'locations': [
        ('شهرک صنعتی شمس‌آباد', 'آسفالت محوطه‌ی کارخانه و انبار، قیرگونی و ایزوگام بام سوله.', 'asphalt-shams-abad'),
        ('جاده مخصوص کرج', 'محوطه‌ی کارخانه، انبار، مرکز پخش و پارکینگ کامیون.', 'asphalt-jadeh-makhsous-karaj'),
        ('پاکدشت و ورامین', 'کارخانه‌ها و کارگاه‌ها، مسیر مزرعه و گلخانه.', 'asphalt-pakdasht-varamin'),
        ('شهریار', 'کارگاه‌ها و سوله‌ها، باغ‌ویلا و شهرک‌ها.', 'asphalt-shahriar'),
        ('عراق و کشورهای خلیج فارس', 'اطلاعات به زبان انگلیسی و عربی برای پروژه‌های خارج از ایران.', 'asphalt-contractor-middle-east'),
    ],
})

PAGES['asphalt-shams-abad'] = ('fa', {
    'group': None,
    'eyebrow': 'شهرک صنعتی شمس‌آباد · جنوب تهران',
    'h1': 'آسفالت، قیرگونی و ایزوگام در شهرک صنعتی شمس‌آباد',
    'lead': 'شهرک صنعتی شمس‌آباد در جنوب تهران و محدوده‌ی آزادراه تهران–قم، یکی از بزرگ‌ترین مجموعه‌های صنعتی استان است؛ کارخانه‌ها، سوله‌ها، انبارها و سردخانه‌ها با تردد مداوم کامیون. <strong>آسفالت با ما</strong> آسفالت محوطه و قیرگونی و ایزوگام بام واحدهای شمس‌آباد را با ماشین‌آلات خود اجرا می‌کند.',
    'hero_photos': [PAV, ISO3, BOB],
    'hero_alts': ['پخش و کوبش آسفالت محوطه', 'ایزوگام دیوار جان‌پناه و لبه‌ی بام', 'آماده‌سازی و بارگیری با بابکت'],
    'loc_title': 'بخش‌های محوطه و بام در واحدهای شمس‌آباد',
    'locations': [
        ('مسیر کامیون و تریلی', 'زیرسازی قوی و آسفالت دو لایه‌ی بیندر و توپکا.'),
        ('محل بارگیری و بارانداز', 'ضخامت بیشتر، کوبش دقیق و قفل لبه‌ها کنار سکو.'),
        ('بام سوله و ساختمان اداری', 'ایزوگام دولایه یا قیرگونی با شیب‌بندی به سمت ناودان‌ها و آب‌بندی دور تأسیسات.'),
        ('محوطه‌ی فرسوده', 'لکه‌گیری یا تراش و روکش بدون بالا آمدن تراز درها و سکوها.'),
        ('سایر مناطق صنعتی', 'جاده مخصوص کرج، پاکدشت و ورامین، شهریار.', 'industrial-asphalt-waterproofing'),
    ],
    'faq': [('برای آسفالت داخل شهرک مجوز لازم است؟', 'کار داخل محوطه‌ی واحد با هماهنگی خود کارخانه انجام می‌شود؛ برای معابر عمومی شهرک، هماهنگی با مدیریت شهرک لازم است.')],
})


def main():
    os.makedirs(OUT, exist_ok=True)
    for slug, (lang, p) in PAGES.items():
        with open(os.path.join(OUT, slug + '.html'), 'w', encoding='utf-8') as fh:
            fh.write(page(lang, p))
    print(f'{len(PAGES)} pages written to {os.path.normpath(OUT)}')


if __name__ == '__main__':
    main()
