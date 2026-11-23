// Free website review landing pages:
//   dist/studio/free-website-review/index.html  and  dist/ar/studio/free-website-review/index.html
// Run: node build/free-review.js
const fs = require('fs');
const path = require('path');
const ROOT = path.resolve(__dirname, '..');
const BASE = 'https://meftacard.com';
const CSS = fs.readFileSync(path.join(ROOT, 'index.html'), 'utf8').match(/<style>([\s\S]*?)<\/style>/)[1];
const OG = `${BASE}/wp-content/uploads/2026/11/meftacard-studio-og.png`;
const SLUG = 'free-website-review';
const MARK = fs.readFileSync(path.join(ROOT, 'brand/mark.svg'), 'utf8').split('\n').filter((l) => l.includes('<path')).join('');

const T = {
  en: {
    dir: 'ltr', font: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', studio: '/studio/', other: 'عربي',
    title: 'Free Website Review for Businesses | MEFTACARD Studio',
    desc: 'Get a free website review in 48 hours: speed, mobile, SEO, Arabic and English, Google Maps and contact. Five priority fixes, no obligation.',
    h1: 'Free website review, in 48 hours',
    lead: 'Send us your website and we will review it for free. Within 48 hours you get a short written report with the five fixes that will bring you the most customers. No cost, no obligation.',
    getH: 'What we check',
    get: [['Speed on mobile', 'How fast your pages load on a phone, and what slows them down.'], ['Mobile experience', 'Whether text, buttons and menus work well on small screens.'], ['SEO basics', 'Titles, descriptions, headings, indexing and the technical issues that hold rankings back.'], ['Arabic and English', 'Language settings, right-to-left layout and whether Google can show the right version.'], ['Google Maps presence', 'Your Google Business Profile, categories, photos and reviews.'], ['Ways to contact you', 'Whether visitors can reach you on WhatsApp, Telegram or by phone in one tap.']],
    youH: 'What you receive', you: ['A short written report in Arabic or English', 'Your five most important fixes, in priority order', 'Which fixes you can do yourself, and which need help', 'An honest opinion: we tell you if your site is already in good shape'],
    formH: 'Request your free review', formSub: 'Takes 30 seconds. We reply on Telegram or WhatsApp.',
    f: ['Your name', 'Website address', 'Business type', 'Country', 'Anything we should know? (optional)'], send: ['Send on Telegram', 'Send on WhatsApp'],
    noSite: 'No website yet? Send your Instagram page or business name and we will tell you what to build first.',
    faqH: 'Questions',
    faq: [['Is it really free?', 'Yes. The review is free and there is no obligation to buy anything afterwards.'], ['How long does it take?', 'You receive the written report within 48 hours on Telegram or WhatsApp.'], ['Which websites do you review?', 'Any business website, in Arabic or English, on any platform: WordPress, Shopify, custom or website builders.'], ['What happens after the review?', 'Nothing, unless you want help. If you do, we quote a fixed price, paid in Tether (USDT), fully online.']],
    msg: 'Hello MEFTACARD Studio, I would like a free website review.', countries: ['Iraq', 'Oman', 'UAE', 'Saudi Arabia', 'Qatar', 'Kuwait', 'Bahrain', 'Lebanon', 'Other'],
    L: ['Free website review request', 'Name', 'Website', 'Business', 'Country', 'Notes'], wa: 'Opens a chat', copy: '© 2026 MEFTACARD Studio', ch: 'Telegram channel',
  },
  ar: {
    dir: 'rtl', font: 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@500;600&display=swap', studio: '/ar/studio/', other: 'EN',
    title: 'مراجعة مجانية لموقعك خلال 48 ساعة | MEFTACARD Studio',
    desc: 'احصل على مراجعة مجانية لموقعك خلال 48 ساعة: السرعة والجوال والسيو والعربية والإنجليزية وخرائط Google، مع أهم خمسة إصلاحات ودون أي التزام.',
    h1: 'مراجعة مجانية لموقعك خلال 48 ساعة',
    lead: 'أرسل لنا رابط موقعك وسنراجعه مجاناً. خلال 48 ساعة تحصل على تقرير مكتوب مختصر بأهم خمسة إصلاحات تجلب لك عملاء أكثر. بلا تكلفة وبلا أي التزام.',
    getH: 'ماذا نراجع',
    get: [['السرعة على الجوال', 'مدى سرعة تحميل صفحاتك على الجوال وما الذي يبطئها.'], ['تجربة الجوال', 'هل النصوص والأزرار والقوائم تعمل جيداً على الشاشات الصغيرة.'], ['أساسيات السيو', 'العناوين والأوصاف والعناوين الفرعية والفهرسة والمشاكل التقنية التي تعيق الترتيب.'], ['العربية والإنجليزية', 'إعدادات اللغة والتصميم من اليمين إلى اليسار وهل يعرض Google النسخة الصحيحة.'], ['الظهور في خرائط Google', 'ملف Google التجاري والتصنيفات والصور والتقييمات.'], ['طرق التواصل معك', 'هل يستطيع الزائر الوصول إليك عبر واتساب أو تيليغرام أو الهاتف بلمسة واحدة.']],
    youH: 'ماذا تستلم', you: ['تقرير مكتوب مختصر بالعربية أو الإنجليزية', 'أهم خمسة إصلاحات لموقعك مرتبة حسب الأولوية', 'ما يمكنك إصلاحه بنفسك وما يحتاج إلى مساعدة', 'رأي صادق: نخبرك إن كان موقعك في حالة جيدة أصلاً'],
    formH: 'اطلب مراجعتك المجانية', formSub: 'يستغرق 30 ثانية. نرد عليك عبر تيليغرام أو واتساب.',
    f: ['اسمك', 'رابط الموقع', 'نوع النشاط', 'الدولة', 'هل هناك ما يجب أن نعرفه؟ (اختياري)'], send: ['أرسل عبر تيليغرام', 'أرسل عبر واتساب'],
    noSite: 'ليس لديك موقع بعد؟ أرسل صفحة إنستغرام أو اسم نشاطك وسنخبرك بما يجب بناؤه أولاً.',
    faqH: 'الأسئلة',
    faq: [['هل المراجعة مجانية فعلاً؟', 'نعم. المراجعة مجانية ولا يوجد أي التزام بشراء شيء بعدها.'], ['كم تستغرق؟', 'تستلم التقرير المكتوب خلال 48 ساعة عبر تيليغرام أو واتساب.'], ['ما المواقع التي تراجعونها؟', 'أي موقع شركة بالعربية أو الإنجليزية وعلى أي منصة: ووردبريس أو شوبيفاي أو موقع مخصص أو منصات بناء المواقع.'], ['ماذا يحدث بعد المراجعة؟', 'لا شيء، إلا إذا أردت المساعدة. وعندها نقدم لك سعراً ثابتاً، والدفع بعملة تيثر (USDT)، أونلاين بالكامل.']],
    msg: 'مرحباً MEFTACARD Studio، أود الحصول على مراجعة مجانية لموقعي.', countries: ['العراق', 'عُمان', 'الإمارات', 'السعودية', 'قطر', 'الكويت', 'البحرين', 'لبنان', 'أخرى'],
    L: ['طلب مراجعة مجانية للموقع', 'الاسم', 'الموقع', 'النشاط', 'الدولة', 'ملاحظات'], wa: 'يفتح محادثة', copy: '© 2026 MEFTACARD Studio', ch: 'قناتنا على تيليغرام',
  },
};
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const url = (lang) => `${BASE}${lang === 'ar' ? '/ar' : ''}/studio/${SLUG}/`;
const tg = (t) => `https://t.me/meftacard?text=${encodeURIComponent(t)}`;

function page(lang) {
  const t = T[lang], other = lang === 'ar' ? 'en' : 'ar', self = url(lang);
  const ld = [
    { '@context': 'https://schema.org', '@type': 'Service', name: t.h1, url: self, inLanguage: lang, description: t.desc, image: OG, serviceType: 'Website audit',
      provider: { '@type': 'ProfessionalService', name: 'MEFTACARD Studio', url: BASE + '/studio/', sameAs: ['https://t.me/meftacard', 'https://t.me/meftacardchannel'] },
      offers: { '@type': 'Offer', price: '0', priceCurrency: 'USD', availability: 'https://schema.org/InStock' },
      areaServed: ['Iraq', 'Oman', 'United Arab Emirates', 'Saudi Arabia', 'Qatar', 'Kuwait', 'Bahrain', 'Lebanon'].map((n) => ({ '@type': 'Country', name: n })) },
    { '@context': 'https://schema.org', '@type': 'FAQPage', inLanguage: lang, mainEntity: t.faq.map(([q, a]) => ({ '@type': 'Question', name: q, acceptedAnswer: { '@type': 'Answer', text: a } })) },
    { '@context': 'https://schema.org', '@type': 'BreadcrumbList', itemListElement: [{ '@type': 'ListItem', position: 1, name: 'MEFTACARD Studio', item: BASE + t.studio }, { '@type': 'ListItem', position: 2, name: t.h1, item: self }] },
  ];
  return `<!doctype html>
<html lang="${lang}" dir="${t.dir}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(t.title)}</title>
<meta name="description" content="${esc(t.desc)}">
<link rel="canonical" href="${self}">
<link rel="alternate" hreflang="${lang}" href="${self}"><link rel="alternate" hreflang="${other}" href="${url(other)}"><link rel="alternate" hreflang="x-default" href="${url('en')}">
<meta property="og:type" content="website"><meta property="og:title" content="${esc(t.title)}"><meta property="og:description" content="${esc(t.desc)}"><meta property="og:url" content="${self}"><meta property="og:image" content="${OG}"><meta property="og:locale" content="${lang === 'ar' ? 'ar_AR' : 'en_US'}">
<meta name="twitter:card" content="summary_large_image"><meta name="robots" content="index,follow,max-image-preview:large"><meta name="theme-color" content="#FAFAF8">
<link rel="icon" href="data:image/svg+xml,${encodeURIComponent(`<svg xmlns='http://www.w3.org/2000/svg' viewBox='-6 -14 112 112'><rect x='-6' y='-14' width='112' height='112' rx='24' fill='#0E0F11'/><g fill='#2BD4A4'>${MARK.replace(/"/g, "'")}</g></svg>`)}">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="${t.font}" rel="stylesheet">
${ld.map((j) => `<script type="application/ld+json">${JSON.stringify(j)}</script>`).join('\n')}
<style>${CSS}
.crumb{font-size:14px;color:var(--ink-2);margin-bottom:22px}.crumb a{color:var(--ink-2)}
.badge-free{display:inline-block;background:#ECF6F2;color:#1A7F5E;border:1px solid #CBE7DC;border-radius:999px;padding:6px 14px;font-weight:600;font-size:14px;margin-bottom:18px}
.chk{display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-top:1px solid var(--ink);margin-top:28px}
.chk div{padding:22px 24px 26px 0;border-bottom:1px solid var(--line)}.chk h3{font-size:18px;margin-bottom:6px;letter-spacing:-.01em}.chk p{color:var(--ink-2);font-size:15.5px}
.you{list-style:none;display:grid;gap:10px;margin-top:18px}.you li{padding-inline-start:26px;position:relative;font-size:16.5px}.you li::before{content:"✓";position:absolute;inset-inline-start:0;color:#1A7F5E;font-weight:700}
.nosite{margin-top:16px;color:#B9BCC2;font-size:15px}
@media (max-width:900px){.chk{grid-template-columns:1fr}}
@media (max-width:600px){.top .btn{display:none}}
</style>
</head>
<body>
<a class="skip" href="#main">${lang === 'ar' ? 'تخطَّ إلى المحتوى' : 'Skip to content'}</a>
<header class="top"><div class="wrap">
  <a class="brand" href="${t.studio}" aria-label="MEFTACARD Studio"><svg class="brand-logo" viewBox="0 4 100 64" aria-hidden="true" focusable="false">${MARK}</svg>MEFTACARD <span>Studio</span></a>
  <nav class="lang" style="margin-inline-start:auto"><a href="${t.studio}${SLUG}/" aria-current="page">${lang === 'ar' ? 'عربي' : 'EN'}</a><a href="${url(other).replace(BASE, '')}" hreflang="${other}" lang="${other}">${t.other}</a></nav>
  <a class="btn btn-dark" href="#request">${t.formH}</a>
</div></header>
<main id="main">
<section class="hero"><div class="wrap">
  <p class="crumb"><a href="${t.studio}">MEFTACARD Studio</a> / ${esc(t.h1)}</p>
  <span class="badge-free">${lang === 'ar' ? 'مجاناً · بلا التزام' : 'Free · No obligation'}</span>
  <h1>${esc(t.h1)}</h1>
  <p class="sub">${esc(t.lead)}</p>
  <div class="actions"><a class="btn btn-dark" href="#request">${t.formH}</a><a class="btn btn-line" href="${tg(t.msg)}" target="_blank" rel="noopener">Telegram @meftacard</a></div>
</div></section>
<section style="padding-top:8px"><div class="wrap">
  <h2 style="font-size:clamp(26px,2.8vw,36px)">${t.getH}</h2>
  <div class="chk">${t.get.map(([h, p]) => `<div><h3>${esc(h)}</h3><p>${esc(p)}</p></div>`).join('')}</div>
</div></section>
<section style="padding-top:16px;padding-bottom:24px"><div class="wrap">
  <h2 style="font-size:clamp(26px,2.8vw,36px)">${t.youH}</h2>
  <ul class="you">${t.you.map((x) => `<li>${esc(x)}</li>`).join('')}</ul>
</div></section>
<section id="request" style="padding-top:24px"><div class="wrap ct" style="padding-block:0">
  <div style="background:var(--ink);color:#fff;border-radius:24px;padding:40px;grid-column:1/-1;display:grid;grid-template-columns:1fr 1.2fr;gap:48px" class="reqbox">
    <div><h2 style="color:#fff">${t.formH}</h2><p class="sub" style="color:#B9BCC2">${t.formSub}</p><p class="nosite">${esc(t.noSite)}</p></div>
    <form class="consult" id="review-form" novalidate>
      <div class="field"><label for="r-name">${t.f[0]}</label><input id="r-name" name="name" required autocomplete="name"></div>
      <div class="field"><label for="r-site">${t.f[1]}</label><input id="r-site" name="site" required inputmode="url" placeholder="example.com" dir="ltr"></div>
      <div class="field"><label for="r-biz">${t.f[2]}</label><input id="r-biz" name="biz"></div>
      <div class="field"><label for="r-country">${t.f[3]}</label><select id="r-country" name="country">${t.countries.map((c) => `<option>${esc(c)}</option>`).join('')}</select></div>
      <div class="field full"><label for="r-notes">${t.f[4]}</label><textarea id="r-notes" name="notes"></textarea></div>
      <div class="sendrow"><button class="btn btn-white" type="submit" value="tg">${t.send[0]}</button><button class="btn btn-ghost" type="submit" value="wa">${t.send[1]}</button></div>
    </form>
  </div>
</div></section>
<section id="faq" style="padding-top:24px"><div class="wrap qa">
  <h2>${t.faqH}</h2>
  <div class="faq">${t.faq.map(([q, a]) => `<details><summary>${esc(q)}</summary><p>${esc(a)}</p></details>`).join('')}</div>
</div></section>
</main>
<footer><div class="wrap"><span>${t.copy}</span><nav class="flinks"><a href="${t.studio}">MEFTACARD Studio</a><a href="https://t.me/meftacardchannel" target="_blank" rel="noopener">${t.ch}</a><a href="${BASE}/privacy-policy/">${lang === 'ar' ? 'الخصوصية' : 'Privacy'}</a></nav></div></footer>
<style>@media (max-width:900px){.reqbox{grid-template-columns:1fr!important;padding:24px!important}}</style>
<script>
const WA_PARTS=["98","919","129","9559"];const L=${JSON.stringify(t.L)};
const f=document.getElementById('review-form');
f.addEventListener('submit',e=>{e.preventDefault();
  const miss=[f.name,f.site].find(x=>!x.value.trim()); if(miss){miss.focus();miss.style.borderColor='#F87171';return;}
  const text=[L[0],'',L[1]+': '+f.name.value.trim(),L[2]+': '+f.site.value.trim(),L[3]+': '+(f.biz.value.trim()||'-'),L[4]+': '+f.country.value,L[5]+': '+(f.notes.value.trim()||'-')].join('\\n');
  if(e.submitter&&e.submitter.value==='wa') window.open('https://wa.me/'+WA_PARTS.join('')+'?text='+encodeURIComponent(text),'_blank','noopener');
  else window.open('https://t.me/meftacard?text='+encodeURIComponent(text),'_blank','noopener');});
</script>
</body>
</html>`;
}
for (const lang of ['en', 'ar']) {
  const f = path.join(ROOT, 'dist', lang === 'ar' ? 'ar' : '', 'studio', SLUG, 'index.html');
  fs.mkdirSync(path.dirname(f), { recursive: true });
  fs.writeFileSync(f, page(lang));
}
console.log('wrote free review pages');
