// Generates the Persian Studio pages: dist/fa/studio/index.html and dist/fa/studio/<slug>/index.html
// Run: node build/fa-pages.js
const fs = require('fs');
const path = require('path');
const ROOT = path.resolve(__dirname, '..');
const BASE = 'https://meftacard.com';
const C = require('./fa-content');
const CSS = fs.readFileSync(path.join(ROOT, 'index.html'), 'utf8').match(/<style>([\s\S]*?)<\/style>/)[1];
const MARK = fs.readFileSync(path.join(ROOT, 'brand/mark.svg'), 'utf8').split('\n').filter((l) => l.includes('<path')).join('');
const OG = `${BASE}/wp-content/uploads/2026/11/meftacard-studio-og.png`;
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const tg = (t) => `https://t.me/meftacard?text=${encodeURIComponent(t)}`;
const faUrl = (slug) => `${BASE}/fa/studio/${slug ? slug + '/' : ''}`;
const enUrl = (slug) => `${BASE}/studio/${slug ? slug + '/' : ''}`;
const arUrl = (slug) => `${BASE}/ar/studio/${slug ? slug + '/' : ''}`;
const FONT = 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&family=Inter:wght@500;600&display=swap';
const WA = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm0 18.2a8.2 8.2 0 01-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1112 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 01-3.3-2.9c-.3-.4.3-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 00-.7.3 3 3 0 00-.9 2.2 5.2 5.2 0 001.1 2.8 11.9 11.9 0 004.6 4c1.7.7 2.4.8 3.2.7a2.8 2.8 0 001.8-1.3 2.3 2.3 0 00.2-1.3c-.1-.1-.3-.2-.5-.3z"/></svg>`;
const TG = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.9 4.3l-3.2 15.2c-.2 1.1-.9 1.3-1.8.8l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.4-5 9.1-8.2c.4-.4-.1-.6-.6-.2L6.2 13.1l-4.8-1.5c-1-.3-1.1-1 .2-1.5L20.5 2.9c.9-.3 1.6.2 1.4 1.4z"/></svg>`;
const PK = [['Local', '۱٬۲۵۰ دلار', 'یک‌جا، تتر · تحویل ۱۴ روزه'], ['Trade & Export', '۲٬۲۵۰ دلار', 'یک‌جا، تتر · تحویل ۳۰ روزه'], ['Enterprise', 'از ۲۵٬۰۰۰ دلار', 'مرحله‌ای، تتر · ۱۲ تا ۱۶ هفته']];
const WHY = [['کاملاً آنلاین', 'از اولین گفتگو تا تحویل و پشتیبانی، نیازی به مراجعه حضوری نیست.'], ['پرداخت با تتر (USDT)', 'بدون حواله بانکی و کارت خارجی؛ پرداخت در چند دقیقه می‌رسد.'], ['قیمت ثابت و کتبی', 'کار، قیمت و تاریخ تحویل قبل از شروع کتبی می‌شود.'], ['همه‌چیز مال شماست', 'کد، دامنه، هاست و حساب‌ها از روز اول به نام شما ثبت می‌شود.']];
const EXTRA_CSS = `
body{font-family:Vazirmatn,Inter,system-ui,sans-serif}
.crumb{font-size:14px;color:var(--ink-2);margin-bottom:22px}.crumb a{color:var(--ink-2)}
.intro p{color:var(--ink-2);font-size:clamp(17px,1.5vw,19px);max-width:62ch;margin-top:16px;line-height:1.9}
.inc{display:grid;grid-template-columns:repeat(3,1fr);gap:0;margin-top:32px;border-top:1px solid var(--ink)}
.inc div{padding:24px 0 28px 24px;border-bottom:1px solid var(--line)}
.inc h3{font-size:18px;margin-bottom:8px}.inc p{color:var(--ink-2);font-size:15.5px;line-height:1.85}
.inc a{border-bottom:1px solid var(--line-2)}
.two{display:grid;grid-template-columns:1fr 1.3fr;gap:64px;align-items:start}
.who{list-style:none;display:grid;gap:10px;margin-top:20px}.who li{padding-inline-start:20px;position:relative;font-size:16px}.who li::before{content:"";position:absolute;inset-inline-start:0;top:.8em;width:8px;height:1.5px;background:var(--ink)}
.steps{list-style:none;counter-reset:s;border-top:1px solid var(--ink);margin-top:20px}.steps li{counter-increment:s;display:grid;grid-template-columns:44px 1fr;padding:18px 0;border-bottom:1px solid var(--line)}
.steps li::before{content:"0" counter(s);color:var(--accent);font-size:14px;font-variant-numeric:tabular-nums;padding-top:3px}.steps b{font-size:17px}.steps p{grid-column:2;color:var(--ink-2);font-size:15.5px;margin-top:4px}
.why{display:grid;grid-template-columns:repeat(4,1fr);gap:28px;margin-top:28px}.why h3{font-size:17px;margin-bottom:6px}.why p{color:var(--ink-2);font-size:15px;line-height:1.8}
.links3{display:grid;grid-template-columns:repeat(2,1fr);gap:48px}.links3 a{display:block;padding:9px 0;border-bottom:1px solid var(--line);font-size:15.5px}.links3 a:hover{color:var(--accent)}.links3 h3{font-size:15px;color:var(--ink-2);font-weight:500;margin-bottom:6px}
.faq p{line-height:1.9}
@media (max-width:900px){.inc,.why,.links3,.two{grid-template-columns:1fr}.two,.links3{gap:32px}}
@media (max-width:600px){.top .btn{display:none}}`;

function shell({ slug, title, desc, alt, name, ld, body, msg }) {
  const self = faUrl(slug);
  const hreflang = alt
    ? `<link rel="alternate" hreflang="fa" href="${self}"><link rel="alternate" hreflang="en" href="${enUrl(slug)}"><link rel="alternate" hreflang="ar" href="${arUrl(slug)}"><link rel="alternate" hreflang="x-default" href="${enUrl(slug)}">`
    : `<link rel="alternate" hreflang="fa" href="${self}">`;
  const langNav = alt
    ? `<nav class="lang"><a href="/fa/studio/${slug ? slug + '/' : ''}" aria-current="page">فارسی</a><a href="/studio/${slug ? slug + '/' : ''}" hreflang="en" lang="en">EN</a><a href="/ar/studio/${slug ? slug + '/' : ''}" hreflang="ar" lang="ar">عربي</a></nav>`
    : `<nav class="lang"><a href="/fa/studio/${slug}/" aria-current="page">فارسی</a><a href="/studio/" hreflang="en" lang="en">EN</a></nav>`;
  return `<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(title)}</title>
<meta name="description" content="${esc(desc)}">
<link rel="canonical" href="${self}">
${hreflang}
<meta property="og:type" content="website"><meta property="og:title" content="${esc(title)}"><meta property="og:description" content="${esc(desc)}"><meta property="og:url" content="${self}"><meta property="og:image" content="${OG}"><meta property="og:locale" content="fa_IR">
<meta name="twitter:card" content="summary_large_image"><meta name="robots" content="index,follow,max-image-preview:large"><meta name="theme-color" content="#FAFAF8">
<link rel="icon" href="data:image/svg+xml,${encodeURIComponent(`<svg xmlns='http://www.w3.org/2000/svg' viewBox='-6 -14 112 112'><rect x='-6' y='-14' width='112' height='112' rx='24' fill='#0E0F11'/><g fill='#2BD4A4'>${MARK.replace(/"/g, "'")}</g></svg>`)}">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="${FONT}" rel="stylesheet">
${ld.map((j) => `<script type="application/ld+json">${JSON.stringify(j)}</script>`).join('\n')}
<style>${CSS}${EXTRA_CSS}</style>
</head>
<body>
<a class="skip" href="#main">رفتن به محتوا</a>
<header class="top"><div class="wrap">
  <a class="brand" href="/fa/studio/" aria-label="MEFTACARD Studio"><svg class="brand-logo" viewBox="0 4 100 64" aria-hidden="true" focusable="false">${MARK}</svg>MEFTACARD <span>Studio</span></a>
  <nav class="links"><a href="#included">خدمات</a><a href="#packages">بسته‌ها</a><a href="#contact">تماس</a></nav>
  ${langNav}
  <a class="btn btn-dark" href="#contact">تماس با ما</a>
</div></header>
<main id="main">
${body}
<section id="contact"><div class="wrap ct">
  <div><h2>بگویید چه چیزی لازم دارید</h2><p class="sub">ظرف یک روز کاری با برآورد کار، زمان‌بندی و قیمت ثابت جواب می‌دهیم.</p></div>
  <div class="direct" style="margin-top:0"><a href="${tg(msg)}" target="_blank" rel="noopener"><span>تلگرام</span><small dir="ltr">@meftacard</small></a><a class="js-wa" href="#"><span>واتس‌اپ</span><small>شروع گفتگو</small></a></div>
</div></section>
</main>
<footer><div class="wrap"><span>© 2026 MEFTACARD Studio</span><nav class="flinks"><a href="/fa/studio/">MEFTACARD Studio</a><a href="/studio/">English</a><a href="/ar/studio/">العربية</a><a href="https://t.me/meftacardchannel" target="_blank" rel="noopener">کانال تلگرام</a></nav></div></footer>
<div class="floats"><a class="float float-wa js-wa" href="#" aria-label="WhatsApp">${WA}</a><a class="float float-tg" href="${tg(msg)}" target="_blank" rel="noopener" aria-label="Telegram @meftacard">${TG}</a></div>
<script>const WA_PARTS=["98","919","129","9559"];const WA_MSG=${JSON.stringify(msg)};document.querySelectorAll('.js-wa').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();window.open('https://wa.me/'+WA_PARTS.join('')+'?text='+encodeURIComponent(WA_MSG),'_blank','noopener');}));</script>
</body>
</html>`;
}

const provider = { '@type': 'ProfessionalService', name: 'MEFTACARD Studio', url: BASE + '/fa/studio/', logo: OG, sameAs: ['https://t.me/meftacard', 'https://t.me/meftacardchannel'] };
const faqLd = (faq) => ({ '@context': 'https://schema.org', '@type': 'FAQPage', inLanguage: 'fa', mainEntity: faq.map(([q, a]) => ({ '@type': 'Question', name: q, acceptedAnswer: { '@type': 'Answer', text: a } })) });
const packages = (msg) => `<div class="cards" style="margin-top:28px">${PK.map((p, i) => `<article class="card${i === 2 ? ' big' : ''}"><h3>${esc(p[0])}</h3><div><div class="price num">${p[1]}</div><div class="unit">${esc(p[2])}</div></div><a class="btn ${i === 0 ? 'btn-line' : 'btn-dark'}" href="${tg(msg + ' (بسته ' + p[0] + ')')}" target="_blank" rel="noopener">انتخاب ${esc(p[0])}</a></article>`).join('')}</div>`;
const why = `<section style="padding-top:24px"><div class="wrap"><h2 style="font-size:clamp(24px,2.4vw,30px)">چرا MEFTACARD Studio</h2><div class="why">${WHY.map(([h, p]) => `<div><h3>${esc(h)}</h3><p>${esc(p)}</p></div>`).join('')}</div></div></section>`;
const faqHtml = (faq) => `<section id="faq" style="padding-top:24px"><div class="wrap qa"><h2>سؤال‌های رایج</h2><div class="faq">${faq.map(([q, a]) => `<details><summary>${esc(q)}</summary><p>${esc(a)}</p></details>`).join('')}</div></div></section>`;
const freeReview = `<section style="padding-top:8px"><div class="wrap"><div class="freeband"><div><b>بررسی رایگان سایت در ۴۸ ساعت</b><p>لینک سایت‌تان را بفرستید؛ مهم‌ترین اصلاحات را رایگان و بدون تعهد برای‌تان می‌فرستیم.</p></div><a class="btn btn-dark" href="${tg('سلام، لطفاً سایت من را رایگان بررسی کنید: ')}" target="_blank" rel="noopener">بررسی رایگان می‌خواهم</a></div></div></section>`;

function hubPage() {
  const h = C.hub, msg = 'سلام MEFTACARD Studio، درباره طراحی سایت و سئو سؤال دارم.';
  const ld = [{ '@context': 'https://schema.org', ...provider, '@id': faUrl('') + '#org', description: h.desc, inLanguage: 'fa', areaServed: ['Iran', 'Afghanistan', 'Iraq', 'Oman', 'United Arab Emirates'].map((n) => ({ '@type': 'Country', name: n })), priceRange: '$1,250 - $25,000+' }, faqLd(h.faq)];
  const body = `<section class="hero"><div class="wrap intro"><h1>${esc(h.h1)}</h1>${h.intro.map((p) => `<p>${esc(p)}</p>`).join('')}
  <div class="actions"><a class="btn btn-dark" href="${tg(msg)}" target="_blank" rel="noopener">پیام در تلگرام</a><a class="btn btn-line" href="#packages">دیدن بسته‌ها</a></div></div></section>
<section id="included" style="padding-top:8px"><div class="wrap"><h2 style="font-size:clamp(26px,2.8vw,36px)">خدمات ما</h2>
  <div class="inc">${h.services.map(([s, t, p]) => `<div><h3><a href="/fa/studio/${s}/">${esc(t)}</a></h3><p>${esc(p)}</p></div>`).join('')}</div></div></section>
<section id="packages" style="padding-top:16px"><div class="wrap"><h2 style="font-size:clamp(26px,2.8vw,36px)">بسته‌های طراحی سایت</h2><p class="sub">قیمت ثابت، کار مشخص و تاریخ تحویل مشخص. روی هر بسته بزنید تا در تلگرام با ما صحبت کنید.</p>${packages(msg)}
  <p class="pk-note">سایت نمونه: <a class="more" href="https://asfaltbama.com" target="_blank" rel="noopener">asfaltbama.com</a> · نمونه‌کارهای بیشتر: <a class="more" href="/studio/#work">صفحه نمونه‌کارها</a></p></div></section>
${why}${freeReview}${faqHtml(h.faq)}`;
  return shell({ slug: '', title: h.title, desc: h.desc, alt: true, name: 'MEFTACARD Studio', ld, body, msg });
}

function servicePage(s) {
  const msg = `سلام MEFTACARD Studio، درباره ${s.name} سؤال دارم.`;
  const self = faUrl(s.slug);
  const ld = [
    { '@context': 'https://schema.org', '@type': 'Service', '@id': self + '#service', name: s.name, url: self, inLanguage: 'fa', description: s.desc, image: OG, provider, offers: { '@type': 'AggregateOffer', priceCurrency: 'USD', lowPrice: '1250', acceptedPaymentMethod: 'USDT (Tether)' } },
    { '@context': 'https://schema.org', '@type': 'BreadcrumbList', itemListElement: [{ '@type': 'ListItem', position: 1, name: 'MEFTACARD Studio', item: faUrl('') }, { '@type': 'ListItem', position: 2, name: s.name, item: self }] },
    faqLd(s.faq),
  ];
  const others = C.pages.filter((x) => x.slug !== s.slug);
  const body = `<section class="hero"><div class="wrap intro">
  <p class="crumb"><a href="/fa/studio/">MEFTACARD Studio</a> / ${esc(s.name)}</p>
  <h1>${esc(s.h1)}</h1>${s.intro.map((p) => `<p>${esc(p)}</p>`).join('')}
  <div class="actions"><a class="btn btn-dark" href="${tg(msg)}" target="_blank" rel="noopener">پیام در تلگرام</a><a class="btn btn-line" href="#packages">دیدن قیمت‌ها</a></div></div></section>
<section id="included" style="padding-top:8px"><div class="wrap"><h2 style="font-size:clamp(26px,2.8vw,36px)">${esc(s.incH)}</h2><div class="inc">${s.inc.map(([h, p]) => `<div><h3>${esc(h)}</h3><p>${esc(p)}</p></div>`).join('')}</div></div></section>
<section style="padding-top:16px"><div class="wrap"><h2 style="font-size:clamp(26px,2.8vw,36px)">${esc(s.typesH)}</h2><div class="inc">${s.types.map(([h, p]) => `<div><h3>${esc(h)}</h3><p>${esc(p)}</p></div>`).join('')}</div></div></section>
<section style="padding-top:16px"><div class="wrap two">
  <div><h2 style="font-size:clamp(24px,2.4vw,30px)">${esc(s.whoH)}</h2><ul class="who">${s.who.map((w) => `<li>${esc(w)}</li>`).join('')}</ul></div>
  <div><h2 style="font-size:clamp(24px,2.4vw,30px)">${esc(s.procH)}</h2><ol class="steps">${s.proc.map(([h, p]) => `<li><b>${esc(h)}</b><p>${esc(p)}</p></li>`).join('')}</ol></div></div></section>
<section id="packages" style="padding-top:16px"><div class="wrap"><h2 style="font-size:clamp(26px,2.8vw,36px)">${esc(s.priceH)}</h2><p class="sub">${esc(s.price)}</p>${packages(msg)}
  <p class="pk-note">سایت نمونه: <a class="more" href="https://asfaltbama.com" target="_blank" rel="noopener">asfaltbama.com</a> · <a class="more" href="/fa/studio/#packages">همه بسته‌ها</a></p></div></section>
${why}${freeReview}${faqHtml(s.faq)}
<section style="padding-top:24px"><div class="wrap links3">
  <div><h3>خدمات دیگر</h3>${others.map((x) => `<a href="/fa/studio/${x.slug}/">${esc(x.name)}</a>`).join('')}</div>
  <div><h3>زبان‌های دیگر</h3>${s.alt ? `<a href="/studio/${s.slug}/" lang="en">English</a><a href="/ar/studio/${s.slug}/" lang="ar">العربية</a>` : `<a href="/studio/" lang="en">MEFTACARD Studio (English)</a><a href="/ar/studio/" lang="ar">MEFTACARD Studio (العربية)</a>`}</div>
</div></section>`;
  return shell({ slug: s.slug, title: s.title, desc: s.desc, alt: s.alt, name: s.name, ld, body, msg });
}

const out = [];
const write = (rel, html) => { const f = path.join(ROOT, 'dist', rel); fs.mkdirSync(path.dirname(f), { recursive: true }); fs.writeFileSync(f, html);
  const words = html.replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/g, ' ').replace(/<[^>]+>/g, ' ').split(/\s+/).filter(Boolean).length; out.push({ rel, words }); };
write('fa/studio/index.html', hubPage());
for (const s of C.pages) write(`fa/studio/${s.slug}/index.html`, servicePage(s));
console.table(out);
for (const s of [C.hub, ...C.pages]) { if (s.title.length > 60) console.warn('TITLE >60:', s.title.length, s.title); if (s.desc.length > 158 || s.desc.length < 110) console.warn('DESC length', s.desc.length, s.slug); }
