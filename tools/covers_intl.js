// Usage: node covers_intl.js items.json outdir
const { chromium } = require('playwright');
const fs = require('fs');
const items = JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
const out = process.argv[3];
const font = 'file://' + require('path').resolve(__dirname, '../asfaltbama-child') + '/assets/fonts/Vazirmatn-Variable.woff2';
const logo = 'file://' + require('path').resolve(__dirname, '../asfaltbama-child') + '/assets/images/logo-light.png';
function split(t){
  const m = t.match(/^(.*?)(\s*[:؛?؟]\s*)(.+)$/);
  if(!m) return [t,''];
  let head=m[1], sep=m[2].trim();
  if(sep==='?'||sep==='؟') head+=sep;
  return [head, m[3]];
}
(async()=>{
  const b = await chromium.launch();
  const p = await b.newPage({viewport:{width:1200,height:675}});
  for (const it of items){
    const [h,sub]=split(it.title);
    const rtl = it.lang==='ar';
    const brand = rtl ? 'أسفلت با ما' : 'Asfaltbama';
    const size = h.length>44?52:h.length>30?60:h.length>20?68:76;
    const html=`<!doctype html><html dir="${rtl?'rtl':'ltr'}" lang="${it.lang}"><head><style>
@font-face{font-family:V;src:url(${font}) format('woff2');font-weight:100 900}
*{margin:0;box-sizing:border-box}
body{width:1200px;height:675px;font-family:V;overflow:hidden;position:relative;
 background:radial-gradient(900px 500px at ${rtl?'15%':'85%'} 10%,#1e3a5f 0%,transparent 60%),linear-gradient(135deg,#0f172a 0%,#111c33 55%,#0b1222 100%);color:#fff}
.clip{position:fixed;inset:0;overflow:hidden}.wm{position:absolute;${rtl?'left':'right'}:-90px;bottom:-120px;width:560px;height:560px;opacity:.07;background:url(${logo}) center/contain no-repeat}
.road{position:absolute;left:0;right:0;bottom:0;height:18px;background:repeating-linear-gradient(90deg,#f59e0b 0 70px,transparent 70px 110px);opacity:.9}
.band{position:absolute;left:0;right:0;bottom:18px;height:6px;background:#1e293b}
.wrap{position:absolute;inset:64px 72px 80px 72px;display:flex;flex-direction:column}
.chip{align-self:flex-start;background:#f59e0b;color:#0f172a;font-weight:800;font-size:26px;padding:8px 22px;border-radius:999px}
h1{margin-top:34px;font-weight:900;font-size:${size}px;line-height:${rtl?1.45:1.2};max-width:1020px;letter-spacing:${rtl?0:-0.5}px}
p{margin-top:18px;font-weight:500;font-size:32px;line-height:1.5;color:#cbd5e1;max-width:1000px}
.foot{margin-top:auto;display:flex;align-items:center;gap:18px}
.foot img{width:84px;height:84px}
.foot b{font-size:32px;font-weight:800;display:block}
.foot span{font-size:22px;color:#94a3b8;font-weight:500;direction:ltr;display:block;text-align:${rtl?'right':'left'}}
</style></head><body><div class="clip"><div class="wm"></div></div><div class="band"></div><div class="road"></div>
<div class="wrap"><div class="chip">${it.cat}</div><h1>${h}</h1>${sub?'<p>'+sub+'</p>':''}
<div class="foot"><img src="${logo}"><div><b>${brand}</b><span>asfaltbama.com</span></div></div></div></body></html>`;
    fs.writeFileSync(out+'/_t.html',html); await p.goto('file://'+out+'/_t.html',{waitUntil:'load'});
    await p.evaluate(()=>document.fonts.ready);
    await p.screenshot({path: out+'/'+it.file+'.png'});
  }
  await b.close();
})();
