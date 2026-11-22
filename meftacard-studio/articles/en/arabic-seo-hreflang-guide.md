---
lang: en
slug: arabic-seo-hreflang-guide
title: "Arabic SEO: Ranking in Arabic and English Without Splitting Your Authority"
seo_title: "Arabic SEO Guide: hreflang, RTL & Bilingual Websites"
meta_description: "A practical Arabic SEO guide for bilingual Middle East websites: URL structure, hreflang, lang and dir, Arabic keyword research and costly mistakes."
focus_keyword: "Arabic SEO"
secondary_keywords: ["hreflang Arabic English", "bilingual website SEO", "SEO Middle East", "multilingual SEO GCC"]
cta: https://meftacard.com/studio/
---

# Arabic SEO: Ranking in Arabic and English Without Splitting Your Authority

Most Middle East businesses need to be found in two languages. The usual result is a site that ranks in neither. An English site with an Arabic toggle that swaps text on the same URL, an Arabic page tagged as English, or a machine-translated copy that competes with its own original.

Arabic SEO is not just "SEO in Arabic". It is a set of technical and editorial decisions that tell Google clearly which page serves which language and region, and then give Arabic searchers content written for them. This guide covers both.

## Step 1: Give each language its own URL

Google needs a separate, crawlable URL for each language version. Three structures work:

| Structure | Example | When to use |
|---|---|---|
| Subdirectory | example.com/ar/ | Best default: shares domain authority, easy to manage |
| Subdomain | ar.example.com | When a separate team or platform runs the Arabic site |
| Country domain | example.sa / example.iq | When you have a legal entity and distinct offer per country |

What does **not** work: switching language with JavaScript or a cookie on the same URL. Google generally indexes one version, usually the default, and the other language is effectively invisible.

## Step 2: Set lang and dir correctly

Every Arabic page should declare itself:

```html
<html lang="ar" dir="rtl">
```

This sounds trivial, yet it is one of the most common errors we see. Sites built on a Persian or English base theme frequently output `lang="fa-IR"` or `lang="en"` on Arabic articles. That sends a conflicting language signal and affects how the page is understood and served.

Use region codes only if you genuinely target regions differently: `ar-SA`, `ar-AE`, `ar-IQ`.

## Step 3: Implement hreflang both ways

hreflang tells Google that two URLs are equivalents in different languages. Each page must list itself and all its alternates, and the tags must be reciprocal:

```html
<link rel="alternate" hreflang="en" href="https://example.com/services/" />
<link rel="alternate" hreflang="ar" href="https://example.com/ar/services/" />
<link rel="alternate" hreflang="x-default" href="https://example.com/services/" />
```

Common mistakes:

- **Missing return tags.** If the Arabic page does not point back to the English page, Google may ignore the pair.
- **Pointing to non-canonical URLs.** hreflang targets must be the canonical, indexable version.
- **Canonical to the other language.** Each language version should be canonical to itself, never to its translation.

## Step 4: Research Arabic keywords from scratch

Translating your English keyword list produces phrases nobody types. Arabic search behaviour has its own patterns:

- **Dialect versus Modern Standard Arabic.** Iraqis, Saudis and Emiratis use different everyday words, but commercial searches lean towards MSA terms. Example: "تصميم مواقع" (website design) rather than dialect phrasing.
- **Spelling variants.** Hamza and taa marbuta are often typed inconsistently (ا/أ/إ, ة/ه). Google normalises many of these, but check the forms that appear in results and suggestions.
- **Mixed-script queries.** Searchers often combine Arabic with English brand or technical terms: "سعر iPhone", "شركة سيو".
- **Location modifiers.** "في العراق", "في الرياض", "بغداد" carry strong local intent.

Validate candidates against real result pages: who ranks, what format they use (lists, service pages, articles) and what questions appear in "People also ask".

## Step 5: Write for Arabic readers, not for translation

- Lead with the answer. Arabic readers on mobile scan just like English readers do.
- Use local examples, currencies (IQD, SAR, AED) and payment methods.
- Keep brand names and technical terms the way people actually write them, often in Latin script.
- Edit machine output with a native speaker. Fluent but unnatural Arabic is easy to spot and erodes trust.

## Step 6: Get the RTL experience right

User experience feeds ranking signals indirectly through engagement. In right-to-left layouts:

- Mirror navigation, icons that imply direction (arrows) and progress indicators.
- Keep numbers, prices, code and phone numbers left-to-right inside Arabic text, or they render in the wrong order.
- Use a font designed for Arabic screens, at a slightly larger size than your Latin text.
- Test real content on real phones. Arabic words are often longer and wrap differently.

## Step 7: Structured data in each language

Schema markup should match the visible language of the page. An Arabic page with English FAQ schema creates a mismatch. Generate structured data from the page content itself so both stay in sync.

## Step 8: Measure each language separately

In Google Search Console, filter performance by page path (`/ar/`) and by country. Track rankings for the Arabic keyword set on its own. Blended reports hide the fact that one language is carrying the other.

## Quick audit: ten questions for your bilingual site

1. Does each language have its own URL?
2. Is `lang` correct on every page, including blog posts?
3. Is `dir="rtl"` set on Arabic pages?
4. Are hreflang tags reciprocal and pointing to canonical URLs?
5. Is each version canonical to itself?
6. Was the Arabic keyword list researched natively?
7. Has a native speaker edited the Arabic copy?
8. Do numbers and prices render correctly in RTL?
9. Does structured data match the page language?
10. Are both languages reported separately in Search Console?

Related: [how much a website costs in the GCC](https://meftacard.com/website-design-cost-gcc-2026/) and [the B2B export website](https://meftacard.com/b2b-export-website-rfq/).

## How we build bilingual sites

Every [MEFTACARD Studio](https://meftacard.com/studio/) track ships with separate English and Arabic URLs, correct language attributes, reciprocal hreflang and structured data generated per language, from the $1,250 Local track to the $25,000+ Enterprise ecosystem.

*Want a bilingual SEO review of your current site? [Message us on Telegram](https://t.me/meftacard).*
