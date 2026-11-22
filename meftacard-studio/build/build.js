// Builds two static, crawlable pages from index.html (English source):
//   dist/studio/index.html     (en, LTR)
//   dist/ar/studio/index.html  (ar, RTL)  — Arabic copy from ar-copy.js
// Also generates FAQPage JSON-LD from the visible FAQ of each language and an OG image.
// Run: node build/build.js
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const { AR, META } = require('./ar-copy');

const ROOT = path.resolve(__dirname, '..');
const SRC = fs.readFileSync(path.join(ROOT, 'index.html'), 'utf8');
const BASE = 'https://meftacard.com';
const FONTS = {
  en: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap',
  ar: 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap',
};

function out(rel, html) {
  const f = path.join(ROOT, 'dist', rel);
  fs.mkdirSync(path.dirname(f), { recursive: true });
  fs.writeFileSync(f, '<!doctype html>\n' + html);
  console.log('wrote', path.relative(ROOT, f), (html.length / 1024).toFixed(1) + ' KB');
}

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ javaScriptEnabled: false });
  const missing = new Set();

  for (const lang of ['en', 'ar']) {
    const page = await ctx.newPage();
    await page.setContent(SRC, { waitUntil: 'domcontentloaded' });
    const res = await page.evaluate(({ lang, AR, META, BASE, FONTS }) => {
      const miss = [];
      const $ = (s) => document.querySelector(s);
      const url = lang === 'ar' ? `${BASE}/ar/studio/` : `${BASE}/studio/`;
      const html = document.documentElement;

      if (lang === 'ar') {
        html.lang = 'ar'; html.dir = 'rtl';
        document.querySelectorAll('[data-i18n]').forEach((el) => {
          const k = el.dataset.i18n;
          if (AR[k] === undefined) miss.push(k); else el.innerHTML = AR[k];
        });
        document.querySelectorAll('[data-aria-ar]').forEach((el) => el.setAttribute('aria-label', el.dataset.ariaAr));
        document.querySelectorAll('[data-href-ar]').forEach((el) => el.setAttribute('href', el.dataset.hrefAr));
        document.querySelectorAll('.flip').forEach((p) => p.setAttribute('d', 'M13 4l-6 6 6 6'));
        document.title = META.title;
        $('meta[name=description]').content = META.desc;
        $('meta[property="og:title"]').content = META.title;
        $('meta[property="og:description"]').content = META.desc;
        $('meta[property="og:locale"]').content = 'ar_AR';
        $('meta[property="og:locale:alternate"]').content = 'en_US';
        document.querySelectorAll('.lang a').forEach((a) => a.toggleAttribute('aria-current', a.dataset.lang === 'ar'));
        document.querySelector('.lang a[data-lang="ar"]').setAttribute('aria-current', 'page');
      }
      $('link[rel=canonical]').href = url;
      $('meta[property="og:url"]').content = url;
      // fonts per language
      document.querySelector('link[href^="https://fonts.googleapis.com/css2"]').href = FONTS[lang];
      // service schema
      const svc = [...document.querySelectorAll('script[type="application/ld+json"]')][0];
      const data = JSON.parse(svc.textContent);
      data.url = url; data['@id'] = url + '#service'; data.inLanguage = lang;
      if (lang === 'ar') { data.description = META.desc; }
      svc.textContent = JSON.stringify(data, null, 2);
      // FAQ schema from the visible FAQ in this language
      const faq = {
        '@context': 'https://schema.org', '@type': 'FAQPage', inLanguage: lang,
        mainEntity: [...document.querySelectorAll('.faq details')].map((d) => ({
          '@type': 'Question', name: d.querySelector('summary').textContent.trim(),
          acceptedAnswer: { '@type': 'Answer', text: d.querySelector('p').textContent.trim() },
        })),
      };
      const s = document.createElement('script'); s.type = 'application/ld+json';
      s.textContent = JSON.stringify(faq, null, 2); svc.after(s);
      // strip build-only attributes
      document.querySelectorAll('[data-aria-ar]').forEach((el) => el.removeAttribute('data-aria-ar'));
      document.querySelectorAll('[data-href-ar]').forEach((el) => el.removeAttribute('data-href-ar'));
      document.querySelectorAll('[data-i18n]').forEach((el) => el.removeAttribute('data-i18n'));
      return { html: document.documentElement.outerHTML, miss };
    }, { lang, AR, META, BASE, FONTS });
    res.miss.forEach((k) => missing.add(k));
    out(lang === 'ar' ? 'ar/studio/index.html' : 'studio/index.html', res.html);
    await page.close();
  }

  // OG image 1200x630
  const og = await browser.newPage({ viewport: { width: 1200, height: 630 } });
  await og.setContent(`<html><head><link href="${FONTS.en}" rel="stylesheet"></head><body style="margin:0;width:1200px;height:630px;background:radial-gradient(700px 400px at 85% 10%,rgba(212,155,53,.18),transparent 70%),#07090C;color:#F5F7FA;font-family:Inter,sans-serif;display:flex;flex-direction:column;justify-content:center;padding:0 88px;box-sizing:border-box">
    <div style="display:flex;align-items:center;gap:14px;font-weight:700;letter-spacing:.06em;font-size:22px"><span style="width:40px;height:40px;border-radius:10px;background:#D49B35;color:#1A1205;display:grid;place-items:center">M</span>MEFTACARD <span style="color:#8B95A5;font-weight:500">STUDIO</span></div>
    <div style="font-size:68px;font-weight:600;letter-spacing:-.03em;line-height:1.05;margin-top:40px;max-width:950px">Digital infrastructure for the companies that <span style="color:#D49B35">run the region.</span></div>
    <div style="margin-top:36px;font-size:24px;color:#8B95A5">Web · Mobile · AI agents · SEO · 24/7 SLA — EN / AR</div></body></html>`, { waitUntil: 'networkidle' });
  fs.mkdirSync(path.join(ROOT, 'dist/studio'), { recursive: true });
  await og.screenshot({ path: path.join(ROOT, 'dist/studio/og.png') });
  console.log('wrote dist/studio/og.png');

  await browser.close();
  if (missing.size) { console.error('MISSING AR KEYS:', [...missing].join(', ')); process.exit(1); }
})();
