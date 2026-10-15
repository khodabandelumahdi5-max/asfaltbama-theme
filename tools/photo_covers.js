// Usage: node photo_covers.js items.json outdir
// Featured images built from a real project photo: the photo on the left,
// the article's keyword title, subtitle and the Asfaltbama badge on a dark
// panel on the right. 1200x675, for Google Images, Discover and shares.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const b64 = f => fs.readFileSync(f).toString('base64');
const font = b64(root + '/asfaltbama-child/assets/fonts/Vazirmatn-Variable.woff2');
const logo = b64(root + '/asfaltbama-child/assets/images/logo.png');
const items = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const out = process.argv[3];
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1200, height: 675 } });
  for (const it of items) {
    const photo = b64(root + '/photos-original/' + it.photo);
    const size = it.title.length > 26 ? 58 : it.title.length > 18 ? 68 : 80;
    const html = `<!doctype html><html dir="rtl" lang="fa"><head><style>
@font-face{font-family:V;src:url(data:font/woff2;base64,${font}) format('woff2');font-weight:100 900}
*{margin:0;box-sizing:border-box}
body{width:1200px;height:675px;overflow:hidden;font-family:V;background:#0f172a;position:relative}
.ph{position:absolute;left:0;top:0;width:560px;height:675px;background:url(data:image/jpeg;base64,${photo}) center/cover;clip-path:polygon(0 0,100% 0,86% 100%,0 100%)}
.ph:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent 60%,rgba(15,23,42,.35))}
.pn{position:absolute;right:0;top:0;width:700px;height:675px;padding:54px 56px 40px 40px;display:flex;flex-direction:column;color:#fff;
 background:radial-gradient(600px 400px at 85% 0%,#1e3a5f 0%,transparent 70%),linear-gradient(160deg,#0f172a,#0b1222)}
.chip{align-self:flex-start;background:#f59e0b;color:#0f172a;font-weight:800;font-size:24px;padding:6px 20px;border-radius:999px}
h1{margin-top:28px;font-weight:900;font-size:${size}px;line-height:1.35}
.bar{width:120px;height:8px;border-radius:8px;background:#f59e0b;margin-top:22px}
p{margin-top:20px;font-size:30px;font-weight:500;line-height:1.55;color:#cbd5e1}
.ft{margin-top:auto;display:flex;align-items:center;gap:16px}
.ft img{width:92px;height:92px}
.ft b{display:block;font-size:34px;font-weight:900}
.ft span{display:block;font-size:22px;color:#fbbf24;direction:ltr;text-align:right;font-weight:600}
.road{position:absolute;left:0;right:0;bottom:0;height:12px;background:repeating-linear-gradient(90deg,#f59e0b 0 70px,#0f172a 70px 110px)}
</style></head><body><div class="ph"></div><div class="pn"><div class="chip">${it.cat}</div><h1>${it.title}</h1><div class="bar"></div>${it.sub ? '<p>' + it.sub + '</p>' : ''}
<div class="ft"><img src="data:image/png;base64,${logo}"><div><b>آسفالت با ما</b><span>asfaltbama.com · 0919 129 9559</span></div></div></div><div class="road"></div></body></html>`;
    await p.setContent(html);
    await p.evaluate(() => document.fonts.ready);
    await p.waitForTimeout(150);
    await p.screenshot({ path: out + '/' + it.file + '.png' });
  }
  await b.close();
})();
