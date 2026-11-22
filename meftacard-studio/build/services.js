// Generates the five service pages in English and Arabic:
//   dist/studio/<slug>/index.html   and   dist/ar/studio/<slug>/index.html
// Run: node build/services.js
const fs = require('fs');
const path = require('path');
const ROOT = path.resolve(__dirname, '..');
const BASE = 'https://meftacard.com';
const S = require('./services-content');
const MORE = require('./services-more');
const PROC_IMG = fs.existsSync(path.join(ROOT,'work/svcimg/media.json')) ? JSON.parse(fs.readFileSync(path.join(ROOT,'work/svcimg/media.json'),'utf8')) : {};
const CSS = fs.readFileSync(path.join(ROOT, 'index.html'), 'utf8').match(/<style>([\s\S]*?)<\/style>/)[1];
const MEDIA = JSON.parse(fs.readFileSync(path.join(ROOT, 'work/img/media.json'), 'utf8'));
const OG = `${BASE}/wp-content/uploads/2026/11/meftacard-studio-og.png`;
const MARK = fs.readFileSync(path.join(ROOT, 'brand/mark.svg'), 'utf8').split('\n').filter((l) => l.includes('<path')).join('');

const COUNTRIES = [['iraq','Iraq','العراق'],['uae','UAE','الإمارات'],['saudi-arabia','Saudi Arabia','السعودية'],['qatar','Qatar','قطر'],['kuwait','Kuwait','الكويت'],['oman','Oman','عُمان'],['bahrain','Bahrain','البحرين'],['lebanon','Lebanon','لبنان']];
const LOC = {
  iraq:{en:['Iraq','Baghdad, Basra, Erbil and Najaf','ZainCash, FastPay and Qi Card'],ar:['العراق','بغداد والبصرة وأربيل والنجف','زين كاش وFastPay وكي كارد']},
  uae:{en:['the UAE','Dubai, Abu Dhabi and Sharjah','cards, Apple Pay, Tabby and Tamara'],ar:['الإمارات','دبي وأبوظبي والشارقة','البطاقات وApple Pay وتابي وتمارا']},
  'saudi-arabia':{en:['Saudi Arabia','Riyadh, Jeddah and Dammam','Mada, STC Pay and Apple Pay'],ar:['السعودية','الرياض وجدة والدمام','مدى وSTC Pay وApple Pay']},
  qatar:{en:['Qatar','Doha, Lusail and Al Wakrah','cards, Apple Pay and NAPS'],ar:['قطر','الدوحة ولوسيل والوكرة','البطاقات وApple Pay ونابس']},
  kuwait:{en:['Kuwait','Kuwait City, Hawalli and Salmiya','KNET, cards and Apple Pay'],ar:['الكويت','مدينة الكويت وحولي والسالمية','كي نت والبطاقات وApple Pay']},
  oman:{en:['Oman','Muscat, Salalah and Sohar','cards, Apple Pay and local bank gateways'],ar:['عُمان','مسقط وصلالة وصحار','البطاقات وApple Pay وبوابات البنوك المحلية']},
  bahrain:{en:['Bahrain','Manama, Muharraq and Riffa','BenefitPay, cards and Apple Pay'],ar:['البحرين','المنامة والمحرق والرفاع','BenefitPay والبطاقات وApple Pay']},
  lebanon:{en:['Lebanon','Beirut, Tripoli and Jounieh','cash on delivery, OMT and Whish Money'],ar:['لبنان','بيروت وطرابلس وجونيه','الدفع عند الاستلام وOMT وWhish Money']},
};
const ART = {
  'website-design-cost-gcc-2026':'How much does a website cost in the GCC?', 'oil-and-gas-website-design':'Oil and gas website design', 'arabic-seo-hreflang-guide':'Arabic SEO for bilingual websites', 'whatsapp-ai-agent-business-uae-saudi':'WhatsApp AI agents for business', 'b2b-export-website-rfq':'The B2B export website',
  'website-design-cost-iraq-ar':'كم تكلفة تصميم موقع في العراق؟', 'seo-iraq-guide-ar':'السيو في العراق: دليل الشركات', 'google-business-profile-local-seo-iraq-ar':'ملف Google التجاري والظهور في الخرائط', 'whatsapp-ai-bot-business-ar':'بوت واتساب بالذكاء الاصطناعي للشركات', 'mobile-app-development-cost-iraq-gulf-ar':'تكلفة تصميم تطبيق جوال',
};
const NAMES = { 'website-design':['Website design','تصميم مواقع'], 'seo-services':['SEO services','خدمات السيو'], 'website-support':['Website support','دعم المواقع'], 'ai-agents':['AI agents','وكلاء الذكاء الاصطناعي'], 'app-design':['App design','تصميم تطبيقات'] };

const L = {
  en:{ dir:'ltr', font:'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', studio:'/studio/', other:'عربي', skip:'Skip to content',
    nav:['Services','Packages','Contact'], tg:'Message us on Telegram', pk:'See packages', whoH:'Who it is for', pkH:'Packages', whyH:'Why MEFTACARD Studio',
    why:[['Fully online','From the first call to launch and support, no visit is needed. In-person support is optional on larger packages.'],['Pay in Tether (USDT)','No international bank transfer or foreign card. Payment arrives in minutes and the dollar price is the same in every country.'],['Fixed price, written scope','Scope, price and launch date are agreed in writing before we start.'],['You own everything','Code, designs, domain, hosting and accounts are registered to your company.']],
    p:[['Local','$1,250','one-time, USDT'],['Trade & Export','$2,250','one-time, USDT'],['Enterprise','$25,000+','by milestone, USDT']], cmp:'Compare packages',
    cmpH:'MEFTACARD Studio, a freelancer or a large agency?', cmpCols:['MEFTACARD Studio','Freelancer','Large agency'],
    cmpRows:[['Price','Fixed, in writing','Low, but often changes','High, often hourly'],['Arabic quality','Native, separate pages','Often machine-translated','Varies'],['Who owns the site','You, from day one','Often unclear','Usually you'],['Support after launch','Included, plus care plans','Rarely','Paid contract'],['Payment','Tether (USDT)','Cash or transfer','Bank transfer'],['Process','Fully online','Varies','Often in-person meetings']],
    workH:'From our work', faqH:'Questions', otherH:'Other services', ctyH:'By country', readH:'Further reading', ctH:'Tell us what you need', ctSub:'We reply within one business day with a scope, timeline and fixed price.',
    msg:(n)=>`Hello MEFTACARD Studio, I'm interested in ${n.toLowerCase()}.`, svc:(c)=>`Website design and SEO in ${c}`, copy:'© 2026 MEFTACARD Studio', wa:'Opens a chat' },
  ar:{ dir:'rtl', font:'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@500;600&display=swap', studio:'/ar/studio/', other:'EN', skip:'تخطَّ إلى المحتوى',
    nav:['الخدمات','الباقات','تواصل معنا'], tg:'راسلنا على تيليغرام', pk:'اطّلع على الباقات', whoH:'لمن هذه الخدمة', pkH:'الباقات', whyH:'لماذا MEFTACARD Studio',
    why:[['أونلاين بالكامل','من أول مكالمة حتى الإطلاق والدعم، لا حاجة للحضور. والدعم الحضوري اختياري في الباقات الأكبر.'],['الدفع بعملة تيثر (USDT)','لا حاجة لتحويل بنكي دولي أو بطاقة أجنبية. يصل الدفع خلال دقائق، والسعر بالدولار نفسه في كل الدول.'],['سعر ثابت ونطاق مكتوب','نتفق كتابياً على النطاق والسعر وموعد الإطلاق قبل البدء.'],['كل شيء ملكك','الكود والتصاميم والدومين والاستضافة والحسابات مسجلة باسم شركتك.']],
    p:[['المحلية','$1,250','دفعة واحدة، USDT'],['التجارة والتصدير','$2,250','دفعة واحدة، USDT'],['المؤسسات','$25,000+','حسب المراحل، USDT']], cmp:'قارن الباقات',
    cmpH:'MEFTACARD Studio أم مستقل أم وكالة كبيرة؟', cmpCols:['MEFTACARD Studio','مستقل','وكالة كبيرة'],
    cmpRows:[['السعر','ثابت ومكتوب','منخفض لكنه يتغير غالباً','مرتفع، وغالباً بالساعة'],['جودة العربية','أصلية بصفحات منفصلة','مترجمة آلياً غالباً','متفاوتة'],['ملكية الموقع','لك من اليوم الأول','غير واضحة غالباً','لك عادة'],['الدعم بعد الإطلاق','مشمول مع خطط رعاية','نادراً','بعقد مدفوع'],['الدفع','تيثر (USDT)','نقداً أو تحويل','تحويل بنكي'],['طريقة العمل','أونلاين بالكامل','متفاوتة','اجتماعات حضورية غالباً']],
    workH:'من أعمالنا', faqH:'الأسئلة', otherH:'خدمات أخرى', ctyH:'حسب الدولة', readH:'اقرأ أيضاً', ctH:'أخبرنا بما تحتاجه', ctSub:'نرد خلال يوم عمل واحد بنطاق وجدول زمني وسعر ثابت.',
    msg:(n)=>`مرحباً MEFTACARD Studio، أنا مهتم بخدمة ${n}.`, svc:(c)=>`تصميم مواقع وسيو في ${c}`, copy:'© 2026 MEFTACARD Studio', wa:'يفتح محادثة' },
};
const esc = (s) => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const url = (lang, slug) => `${BASE}${lang === 'ar' ? '/ar' : ''}/studio/${slug}/`;
const tg = (t) => `https://t.me/meftacard?text=${encodeURIComponent(t)}`;
const WA = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm0 18.2a8.2 8.2 0 01-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1112 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 01-3.3-2.9c-.3-.4.3-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 00-.7.3 3 3 0 00-.9 2.2 5.2 5.2 0 001.1 2.7 11.9 11.9 0 004.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 001.8-1.2 2.2 2.2 0 00.2-1.2c-.1-.1-.3-.2-.5-.3z"/></svg>`;
const TG = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.9 4.3l-3.2 15.2c-.2 1.1-.9 1.3-1.8.8l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.4-5 9.1-8.2c.4-.4-.1-.6-.6-.2L6.2 13.1l-4.8-1.5c-1-.3-1.1-1 .2-1.5L20.5 2.9c.9-.3 1.6.2 1.4 1.4z"/></svg>`;

function page(s, lang) {
  const m = MORE[s.slug][lang];
  const t = L[lang], d = { ...s[lang], faq: [...s[lang].faq, ...MORE[s.slug][lang].faq] }, other = lang === 'ar' ? 'en' : 'ar', self = url(lang, s.slug), name = NAMES[s.slug][lang === 'ar' ? 1 : 0];
  const ld = [
    { '@context':'https://schema.org', '@type':'Service', '@id':self+'#service', name, serviceType:NAMES[s.slug][0], url:self, inLanguage:lang, description:d.desc, image:OG,
      provider:{ '@type':'ProfessionalService', name:'MEFTACARD Studio', url:BASE+'/studio/', logo:OG, sameAs:['https://t.me/meftacard'] },
      areaServed:COUNTRIES.map((c)=>({ '@type':'Country', name:c[1] })),
      offers:{ '@type':'AggregateOffer', priceCurrency:'USD', lowPrice:'1250', acceptedPaymentMethod:'USDT (Tether)' } },
    { '@context':'https://schema.org', '@type':'BreadcrumbList', itemListElement:[{ '@type':'ListItem', position:1, name:'MEFTACARD Studio', item:BASE+t.studio },{ '@type':'ListItem', position:2, name, item:self }] },
    { '@context':'https://schema.org', '@type':'FAQPage', inLanguage:lang, mainEntity:d.faq.map(([q,a])=>({ '@type':'Question', name:q, acceptedAnswer:{ '@type':'Answer', text:a } })) },
  ];
  const img = MEDIA[s.img];
  return `<!doctype html>
<html lang="${lang}" dir="${t.dir}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(d.title)}</title>
<meta name="description" content="${esc(d.desc)}">
<link rel="canonical" href="${self}">
<link rel="alternate" hreflang="${lang}" href="${self}"><link rel="alternate" hreflang="${other}" href="${url(other, s.slug)}"><link rel="alternate" hreflang="x-default" href="${url('en', s.slug)}">
<meta property="og:type" content="website"><meta property="og:title" content="${esc(d.title)}"><meta property="og:description" content="${esc(d.desc)}"><meta property="og:url" content="${self}"><meta property="og:image" content="${OG}"><meta property="og:locale" content="${lang === 'ar' ? 'ar_AR' : 'en_US'}">
<meta name="twitter:card" content="summary_large_image"><meta name="robots" content="index,follow,max-image-preview:large"><meta name="theme-color" content="#FAFAF8">
<link rel="icon" href="data:image/svg+xml,${encodeURIComponent(`<svg xmlns='http://www.w3.org/2000/svg' viewBox='-6 -14 112 112'><rect x='-6' y='-14' width='112' height='112' rx='24' fill='#0E0F11'/><g fill='#2BD4A4'>${MARK.replace(/"/g,"'")}</g></svg>`)}">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="${t.font}" rel="stylesheet">
${ld.map((j)=>`<script type="application/ld+json">${JSON.stringify(j)}</script>`).join('\n')}
<style>${CSS}
.crumb{font-size:14px;color:var(--ink-2);margin-bottom:22px}.crumb a{color:var(--ink-2)}
.intro p{color:var(--ink-2);font-size:clamp(17px,1.5vw,19px);max-width:62ch;margin-top:16px}
.inc{display:grid;grid-template-columns:repeat(3,1fr);gap:0;margin-top:32px;border-top:1px solid var(--ink)}
.inc div{padding:24px 24px 28px 0;border-bottom:1px solid var(--line)}
.inc h3{font-size:18px;letter-spacing:-.01em;margin-bottom:8px}.inc p{color:var(--ink-2);font-size:15.5px}
.two{display:grid;grid-template-columns:1fr 1.3fr;gap:64px;align-items:start}
.who{list-style:none;display:grid;gap:10px;margin-top:20px}.who li{padding-inline-start:20px;position:relative;font-size:16px}.who li::before{content:"";position:absolute;inset-inline-start:0;top:.7em;width:8px;height:1.5px;background:var(--ink)}
.steps{list-style:none;counter-reset:s;border-top:1px solid var(--ink);margin-top:20px}.steps li{counter-increment:s;display:grid;grid-template-columns:44px 1fr;padding:18px 0;border-bottom:1px solid var(--line)}
.steps li::before{content:"0" counter(s);color:var(--accent);font-size:14px;font-variant-numeric:tabular-nums;padding-top:3px}.steps b{font-size:17px}.steps p{grid-column:2;color:var(--ink-2);font-size:15.5px;margin-top:4px}
.why{display:grid;grid-template-columns:repeat(4,1fr);gap:28px;margin-top:28px}.why h3{font-size:17px;margin-bottom:6px;letter-spacing:-.01em}.why p{color:var(--ink-2);font-size:15px}
.links3{display:grid;grid-template-columns:repeat(3,1fr);gap:48px}.links3 a{display:block;padding:9px 0;border-bottom:1px solid var(--line);font-size:15.5px}.links3 a:hover{color:var(--accent)}.links3 h3{font-size:15px;color:var(--ink-2);font-weight:500;margin-bottom:6px;letter-spacing:0}
@media (max-width:900px){.inc,.why,.links3,.two{grid-template-columns:1fr}.two,.links3{gap:32px}}
</style>
</head>
<body>
<a class="skip" href="#main">${t.skip}</a>
<header class="top"><div class="wrap">
  <a class="brand" href="${t.studio}">MEFTACARD <span>Studio</span></a>
  <nav class="links"><a href="#included">${t.nav[0]}</a><a href="#packages">${t.nav[1]}</a><a href="#contact">${t.nav[2]}</a></nav>
  <nav class="lang"><a href="${t.studio}${s.slug}/" aria-current="page">${lang === 'ar' ? 'عربي' : 'EN'}</a><a href="${url(other, s.slug).replace(BASE,'')}" hreflang="${other}" lang="${other}">${t.other}</a></nav>
  <a class="btn btn-dark" href="#contact">${t.nav[2]}</a>
</div></header>
<main id="main">
<section class="hero"><div class="wrap intro">
  <p class="crumb"><a href="${t.studio}">MEFTACARD Studio</a> / ${esc(name)}</p>
  <h1>${esc(d.h1)}</h1>
  ${d.intro.map((p)=>`<p>${esc(p)}</p>`).join('')}
  <div class="actions"><a class="btn btn-dark" href="${tg(t.msg(name))}" target="_blank" rel="noopener">${t.tg}</a><a class="btn btn-line" href="#packages">${t.pk}</a></div>
</div></section>

<section id="included" style="padding-top:8px"><div class="wrap">
  <h2 style="font-size:clamp(26px,2.8vw,36px)">${esc(d.incH)}</h2>
  <div class="inc">${d.inc.map(([h,p])=>`<div><h3>${esc(h)}</h3><p>${esc(p)}</p></div>`).join('')}</div>
</div></section>

<section style="padding-top:16px"><div class="wrap">
  <h2 style="font-size:clamp(26px,2.8vw,36px)">${esc(m.typesH)}</h2>
  <div class="inc">${m.types.map(([h,p])=>`<div><h3>${esc(h)}</h3><p>${esc(p)}</p></div>`).join('')}</div>
</div></section>

<section style="padding-top:16px"><div class="wrap two">
  <div><h2 style="font-size:clamp(24px,2.4vw,30px)">${esc(d.whoH)}</h2><ul class="who">${d.who.map((w)=>`<li>${esc(w)}</li>`).join('')}</ul></div>
  <div><h2 style="font-size:clamp(24px,2.4vw,30px)">${esc(d.procH)}</h2><ol class="steps">${d.proc.map(([h,p])=>`<li><b>${esc(h)}</b><p>${esc(p)}</p></li>`).join('')}</ol></div>
</div></section>

${PROC_IMG[s.slug+'-'+lang] ? `<section style="padding-top:8px"><div class="wrap"><img src="${PROC_IMG[s.slug+'-'+lang]}" width="1200" height="675" loading="lazy" alt="${esc(d.procH)}" style="border-radius:16px;border:1px solid var(--line)"></div></section>` : ''}

<section style="padding-top:16px"><div class="wrap">
  <h2 style="font-size:clamp(26px,2.8vw,36px)">${esc(m.mistH)}</h2>
  <div class="inc">${m.mist.map(([h,p])=>`<div><h3>${esc(h)}</h3><p>${esc(p)}</p></div>`).join('')}</div>
</div></section>

<section id="packages" style="padding-top:16px"><div class="wrap">
  <h2 style="font-size:clamp(26px,2.8vw,36px)">${esc(d.priceH)}</h2>
  <p class="sub">${esc(d.price)}</p>
  <h3 style="font-size:19px;margin-top:28px">${esc(m.factH)}</h3><ul class="who">${m.fact.map((x)=>`<li>${esc(x)}</li>`).join('')}</ul>
  <div class="cards" style="margin-top:28px">${t.p.map((p,i)=>`<article class="card${i===2?' big':''}"><h3>${esc(p[0])}</h3><div><div class="price num">${p[1]}</div><div class="unit">${esc(p[2])}</div></div><a class="btn ${i===0?'btn-line':'btn-dark'}" href="${tg(t.msg(name)+' ('+p[0]+')')}" target="_blank" rel="noopener">${esc(p[0])}</a></article>`).join('')}</div>
  <p class="pk-note"><a class="more" href="${t.studio}#packages">${t.cmp} →</a></p>
</div></section>

<section style="padding-top:16px"><div class="wrap">
  <h2 style="font-size:clamp(24px,2.4vw,30px)">${t.workH}</h2>
  <a href="${t.studio}#work" style="display:block;margin-top:24px" class="browser"><div class="bar"><i></i><i></i><i></i></div><img src="${img}" width="1440" height="900" loading="lazy" alt="${esc(name)} — MEFTACARD Studio"></a>
</div></section>

<section style="padding-top:24px"><div class="wrap">
  <h2 style="font-size:clamp(24px,2.4vw,30px)">${t.cmpH}</h2>
  <div class="compare" style="margin-top:20px"><div class="tbl" style="border-top:0"><table><thead><tr><th></th>${t.cmpCols.map((c)=>`<th>${esc(c)}</th>`).join('')}</tr></thead><tbody>${t.cmpRows.map((r)=>`<tr><th>${esc(r[0])}</th>${r.slice(1).map((c,i)=>`<td${i===0?' style="font-weight:600;color:var(--ink)"':''}>${esc(c)}</td>`).join('')}</tr>`).join('')}</tbody></table></div></div>
</div></section>

<section style="padding-top:24px"><div class="wrap">
  <h2 style="font-size:clamp(24px,2.4vw,30px)">${t.whyH}</h2>
  <div class="why">${t.why.map(([h,p])=>`<div><h3>${esc(h)}</h3><p>${esc(p)}</p></div>`).join('')}</div>
</div></section>

<section style="padding-top:24px"><div class="wrap">
  <h2 style="font-size:clamp(24px,2.4vw,30px)">${lang==='ar'?`${esc(name)} حسب الدولة`:`${esc(name)} by country`}</h2>
  <div class="inc">${Object.entries(LOC).map(([slug,v])=>{const [c,cities,pay]=v[lang];return `<div><h3><a href="${t.studio}${slug}/" style="border-bottom:1px solid var(--line-2)">${esc(lang==='ar'?`${name} في ${c}`:`${name} in ${c}`)}</a></h3><p>${esc(lang==='ar'?`نخدم الشركات في ${cities} أونلاين بالكامل، مع صفحات بالعربية والإنجليزية وربط طرق الدفع المحلية مثل ${pay}، والدفع لنا بعملة تيثر.`:`We work with businesses in ${cities}, fully online, with Arabic and English pages and local payment methods such as ${pay}. You pay us in Tether (USDT).`)}</p></div>`}).join('')}</div>
</div></section>

<section id="faq" style="padding-top:24px"><div class="wrap qa">
  <h2>${t.faqH}</h2>
  <div class="faq">${d.faq.map(([q,a])=>`<details><summary>${esc(q)}</summary><p>${esc(a)}</p></details>`).join('')}</div>
</div></section>

<section style="padding-top:24px"><div class="wrap links3">
  <div><h3>${t.otherH}</h3>${S.filter((x)=>x.slug!==s.slug).map((x)=>`<a href="${t.studio}${x.slug}/">${esc(NAMES[x.slug][lang==='ar'?1:0])}</a>`).join('')}</div>
  <div><h3>${t.ctyH}</h3>${COUNTRIES.map((c)=>`<a href="${t.studio}${c[0]}/">${esc(t.svc(lang==='ar'?c[2]:c[1]))}</a>`).join('')}</div>
  <div><h3>${t.readH}</h3>${s.read[lang].map((a)=>`<a href="${BASE}/${a}/">${esc(ART[a])}</a>`).join('')}</div>
</div></section>

<section id="contact"><div class="wrap ct">
  <div><h2>${t.ctH}</h2><p class="sub">${t.ctSub}</p></div>
  <div class="direct" style="margin-top:0"><a href="${tg(t.msg(name))}" target="_blank" rel="noopener"><span>Telegram</span><small dir="ltr">@meftacard</small></a><a class="js-wa" href="#"><span>WhatsApp</span><small>${t.wa}</small></a></div>
</div></section>
</main>
<footer><div class="wrap"><span>${t.copy}</span><nav class="flinks"><a href="${t.studio}">MEFTACARD Studio</a><a href="${BASE}/contact/">${lang==='ar'?'اتصل بنا':'Contact'}</a><a href="${BASE}/privacy-policy/">${lang==='ar'?'الخصوصية':'Privacy'}</a></nav></div></footer>
<div class="floats"><a class="float float-wa js-wa" href="#" aria-label="WhatsApp">${WA}</a><a class="float float-tg" href="${tg(t.msg(name))}" target="_blank" rel="noopener" aria-label="Telegram @meftacard">${TG}</a></div>
<script>const WA_PARTS=["98","919","129","9559"];const WA_MSG=${JSON.stringify(t.msg(name))};document.querySelectorAll('.js-wa').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();window.open('https://wa.me/'+WA_PARTS.join('')+'?text='+encodeURIComponent(WA_MSG),'_blank','noopener');}));</script>
</body>
</html>`;
}

const out = [];
for (const s of S) for (const lang of ['en','ar']) {
  const f = path.join(ROOT, 'dist', lang === 'ar' ? 'ar' : '', 'studio', s.slug, 'index.html');
  fs.mkdirSync(path.dirname(f), { recursive: true });
  const h = page(s, lang); fs.writeFileSync(f, h);
  const words = h.replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/g,' ').replace(/<[^>]+>/g,' ').split(/\s+/).filter(Boolean).length;
  out.push({ slug:s.slug, lang, title:s[lang].title, h1:s[lang].h1, words });
}
fs.writeFileSync(path.join(ROOT, 'dist', 'services.json'), JSON.stringify(out, null, 1));
console.table(out.map(({slug,lang,words})=>({slug,lang,words})));
