# Writing guide for MEFTACARD Studio articles

Every article must pass `build/check-article.py`. Articles that pass are **published** with `publish-draft.py --publish` (this also posts to the Telegram channel automatically). Articles that fail stay **drafts** and the reason goes in the report.

**Current market focus: Iraq and Oman first** (at least 2 of every 3 daily articles). Europe and the USA come later; do not target them yet.

## File format
Save each article as `meftacard-studio/articles/<lang>/<slug>.md` with this front matter (same as the existing files):

```
---
lang: ar            # or en
slug: <slug from TOPICS.md>
title: "<H1, natural, includes the focus keyword>"
seo_title: "<≤ 60 characters, focus keyword near the start>"
meta_description: "<130–155 characters, includes the focus keyword>"
focus_keyword: "<from TOPICS.md>"
secondary_keywords: ["<3–5 keywords competitors rank for>", "...", "..."]
key_points: ["<4–5 short takeaways, ≤ 90 characters each; used for the key-points image and the video>", "...", "...", "..."]
cta: https://meftacard.com/ar/studio/   # or /studio/ for English
---
```
Then the body in Markdown, starting with `# <title>`.

## Content rules
- **At least 2,500 words**, in both Arabic and English. Every section must be useful: steps, examples, local details, tables, checklists. No filler or repetition to reach the count.
- At least 6 H2 sections. Use the secondary keywords naturally; each must appear in the text.
- Competitor keywords to work in where relevant — Arabic: تصميم مواقع، شركة تصميم مواقع، تصميم مواقع متجاوبة، تصميم متاجر إلكترونية، تصميم صفحات هبوط، خدمات سيو، شركة سيو، تحسين محركات البحث، السيو المحلي، كتابة محتوى سيو، صيانة المواقع، دعم المواقع، تحسين سرعة الموقع، تصميم تطبيقات، شات بوت واتساب. English: web design company, website development, responsive web design, e-commerce website design, landing page design, UI/UX design, SEO services, local SEO, technical SEO, SEO content writing, website maintenance, website support, WordPress maintenance, app design, AI chatbot.
- Link at least one reputable external source (official site, government or industry data) for any figure.
- Arabic articles in clear Modern Standard Arabic with local examples (cities, currencies, payment methods). English articles in plain British-neutral English.
- Focus keyword in the H1, the first paragraph and one H2. No keyword stuffing.
- Structure: short intro that answers the question → 4–7 H2 sections → a table or checklist where it helps → "أسئلة شائعة" / "Frequently asked questions" with 3–4 Q&As → a closing CTA line to Telegram: `[راسلنا على تيليغرام](https://t.me/meftacard)` / `[Message us on Telegram](https://t.me/meftacard)`.
- **No invented statistics, client names, testimonials or results.** Prices or figures from the web must be linked to their source and labelled as estimates. Never claim years of experience.
- Target markets, name them explicitly where relevant: Iraq, Dubai and the UAE, Saudi Arabia, Oman, Qatar, Kuwait, Bahrain and Lebanon (العراق، دبي والإمارات، السعودية، عُمان، قطر، الكويت، البحرين، لبنان).
- Mention where relevant: fully online (no visit needed), payment in Tether (USDT), fixed prices (Local $1,250, Trade & Export $2,250, Enterprise from $25,000), in-person support on Trade & Enterprise.

## Internal links (at least 3)
- The matching service page: `https://meftacard.com/ar/studio/<service>/` or `https://meftacard.com/studio/<service>/`
  (services: website-design, seo-services, website-support, ai-agents, app-design)
- A country page when relevant: `https://meftacard.com/ar/studio/<country>/` (iraq, uae, saudi-arabia, qatar, kuwait, oman, bahrain, lebanon)
- One or two existing articles in the same language (see TOPICS.md, published section).

## After writing
Run `python3 meftacard-studio/build/publish-draft.py meftacard-studio/articles/<lang>/<slug>.md` for each file.
It creates the cover image, uploads it with alt text, saves the WordPress draft (category, Rank Math title/description/keyword, cover at the top, country links at the end) and prints the post ID.

## Telegram post for each article
For every draft, also prepare a ready-to-paste post for the channel https://t.me/meftacardchannel, in the article's language:
a hook line with 🔹, two sentences, three ✅ bullets, `📖` + the article URL (https://meftacard.com/<slug>/), the market flags line
(🇮🇶 العراق · 🇦🇪 دبي · 🇸🇦 السعودية · 🇴🇲 عُمان for Arabic, 🇮🇶 Iraq · 🇦🇪 Dubai · 🇸🇦 Saudi Arabia · 🇴🇲 Oman for English) and `💬 @meftacard`.
Include these posts in the final report so the owner can post them after publishing the article.

## Upgrading earlier articles
Earlier articles are brought up to the same standard from the UPGRADE QUEUE in TOPICS.md: same slug and language, expanded to ≥ 2,500 useful words with key_points, FAQ, service links and a source. Run the gate until PASS, then `publish-draft.py --upgrade <file>`. The live version is replaced only when the gate passes.

## Marketing skills (installed 2026-11-24)
This repo ships the marketingskills library in `.claude/skills/` (53 skills) and the shared context in `.agents/product-marketing.md`.
For every article and page:
1. Read `.agents/product-marketing.md` first (positioning, packages, voice, hard rules, learnings).
2. Draft with the `copywriting` skill; then run the `copy-editing` skill to remove AI tells ("it's not X, it's Y", "no X, no Y, no Z", trailing comma clauses).
3. Apply `ai-seo` for answer-ready sections (clear question headings, direct first sentences, citable facts with sources) and `schema` for JSON-LD.
4. Outreach copy uses `cold-email` (email first; WhatsApp only for people who replied — see Learnings in the context doc).
These add to, and never override, the quality gate (check-article.py) and the hard rules above.
