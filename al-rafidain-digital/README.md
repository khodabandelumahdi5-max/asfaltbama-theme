# Al-Rafidain Digital — الرافدين ديجيتال — ڕافیدەین دیجیتاڵ

A bilingual (Iraqi business Arabic / Kurdish Sorani) RTL agency website. It is a static
site: HTML5, compiled and purged Tailwind CSS, and vanilla JS. It has no runtime
dependencies and no framework, so you deploy it by uploading the folder to any static host
(Cloudflare Pages, Netlify, Vercel, S3, or Nginx).

## Before going live: replace the placeholders

| What | Where |
|---|---|
| WhatsApp Business number (`9647XXXXXXXXX`) | `assets/js/config.js` → `whatsappNumber`, and `telephone` in the JSON-LD in `index.html` |
| RFQ inbox (`[INSERT_EMAIL_HERE]`) | `assets/js/config.js` → `rfqEmail`, and `email` in the JSON-LD in `index.html` |
| Domain (`https://www.your-domain.iq`) | `index.html` (canonical, hreflang, OG, JSON-LD), `robots.txt`, `sitemap.xml` |
| Prices and the USD→IQD rate | `assets/js/config.js` → `pricing`, `usdToIqd` (keep the "تبدأ من" figures on the cards in sync) |
| Social share image | add `assets/img/og-image.png` (1200×630) |

Quick domain swap: `grep -rl your-domain.iq . | xargs sed -i 's/www.your-domain.iq/www.example.iq/g'`

### RFQ delivery
* **Default:** the form opens the visitor's mail client with a structured, pre-filled message to `rfqEmail`.
* **Recommended for production:** set `rfqEndpoint` to an HTTPS endpoint that accepts JSON
  (Formspree, Basin, or your own API). Submissions are POSTed with no page reload, and the
  success and error states are localized. The form includes a honeypot field, required-field
  and format validation, and a soft warning for free webmail addresses.

## Structure

```
index.html            Page markup. Arabic copy is server-rendered here, so it is indexable and works without JS.
assets/js/config.js   Every business setting (phone, email, endpoint, price book).
assets/js/i18n.js     Kurdish Sorani dictionary + runtime strings (WhatsApp messages, form/estimator text) in both languages.
assets/js/app.js      Language switch, WhatsApp deep links, city tabs + geo-routing, estimator, RFQ form, VIP widget.
src/styles.css        Tailwind source (design tokens, glass, gold frame, components).
assets/css/app.css    Built CSS. Committed so that deploying needs no build step.
scripts/check-i18n.mjs  Fails if any translatable key is missing in Kurdish (or is unused).
```

## Features
* **Language switching:** Arabic ⇄ Kurdish switch instantly. The choice persists in
  `localStorage` and in `?lang=ckb`. Browsers set to `ckb`/`ku` get Kurdish automatically, and
  a pre-paint bootstrap prevents a flash of Arabic. Arabic uses IBM Plex Sans Arabic. The
  Kurdish font (Vazirmatn, which covers ڕ ڵ ۆ ێ ە) loads only when Kurdish is shown.
* **Geo-routing:** each of the 7 cities has a sector-specific panel and a deep-linkable URL
  (`/#city-erbil` or `/?city=najaf`). All panels stay in the DOM, so crawlers can index them.
  Each city's WhatsApp CTA pre-fills the city and sector.
* **Enterprise & Government portal:** an animated gold glass frame, the turnkey, security,
  account-manager and SLA pillars, an SLA matrix, an enterprise WhatsApp channel and the
  formal RFQ form.
* **Pricing:** three tiers, plus an estimator that shows one-time and monthly ranges in USD and
  IQD. It auto-includes items already in a package and can send the estimate over WhatsApp.
* **Floating VIP WhatsApp widget:** pinned to the bottom and safe-area aware on mobile, with
  four pre-filled topics in the active language.
* **SEO:** JSON-LD for Organization/ProfessionalService (with `areaServed` for all 7 cities),
  OfferCatalog, WebSite and FAQPage. Also hreflang `ar-IQ`/`ckb-IQ`, a sitemap with
  alternates, semantic headings, and local keyword lines for each city.
* **Performance:** about 7.5 KB of gzipped CSS, deferred JS of about 20 KB total, no images
  above the fold (inline SVG only), and `font-display: swap`.
* **Accessibility:** a skip link, ARIA tabs with RTL arrow-key navigation, labelled form
  controls with `aria-invalid`, visible focus rings, and support for `prefers-reduced-motion`.

## Develop

```bash
npm install
npm run dev          # rebuild CSS on change
npm run serve        # http://localhost:4173
npm run build        # minified production CSS
npm run check:i18n   # translation coverage
```

## SEO note
Kurdish is rendered client-side from `?lang=ckb`. Google renders JavaScript, so this works.
For the strongest Kurdish rankings, pre-render a static `/ckb/` copy (for example with a
headless-browser snapshot at build time) and point the `ckb-IQ` hreflang at it.
