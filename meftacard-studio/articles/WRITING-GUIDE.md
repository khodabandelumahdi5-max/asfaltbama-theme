# Writing guide for MEFTACARD Studio articles

Every article is saved in WordPress as a **draft** for the owner to review. Never publish.

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
secondary_keywords: ["...", "...", "..."]
cta: https://meftacard.com/ar/studio/   # or /studio/ for English
---
```
Then the body in Markdown, starting with `# <title>`.

## Content rules
- English 900–1,400 words; Arabic 650–1,100 words (Arabic says the same in fewer words). Useful and specific, written for business owners in Iraq, the Gulf and Lebanon.
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
