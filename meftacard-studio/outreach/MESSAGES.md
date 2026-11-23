# Outreach messages — MEFTACARD Studio

Short, personal, one clear offer: a free website review. Send from your business WhatsApp/Telegram or email.
Replace the parts in {braces}. Send 10–15 a day. Reply to every answer within a few hours.

---

## 1. Business WITH a website that has problems (Arabic — WhatsApp / Instagram)

```
السلام عليكم {اسم الشركة}،
اطّلعت على موقعكم {الموقع} ولاحظت {مشكلة واحدة واضحة، مثلاً: أنه لا يوجد زر واتساب على الجوال / أنه بطيء على الجوال / أنه بدون نسخة عربية}.

جهزنا لكم مراجعة مجانية مختصرة فيها أهم 5 إصلاحات تجلب لكم عملاء أكثر من Google.
هل أرسلها لكم هنا؟ بلا أي التزام.

مهدي — MEFTACARD Studio
meftacard.com/ar/studio
```

## 2. Business WITHOUT a website (Arabic — Instagram / WhatsApp)

```
السلام عليكم {اسم النشاط}،
صفحتكم على إنستغرام ممتازة، لكن عند البحث في Google عن "{الخدمة} في {المدينة}" لا يظهر لكم موقع، فيذهب العملاء للمنافسين.

نصمم مواقع للشركات في العراق وعُمان بالعربية والإنجليزية، أونلاين بالكامل، وبسعر ثابت يبدأ من 1,250$، والدفع بعملة تيثر.
هل تحبون أن أرسل لكم فكرة سريعة لشكل موقعكم؟ مجاناً وبلا التزام.

مهدي — MEFTACARD Studio
```

## 3. Company with a website (English — email, for Oman / corporate)

```
Subject: Quick review of {domain}

Hello {Company} team,

I looked at {domain} on my phone and noticed {one specific issue, e.g. there is no Arabic version / the site has no HTTPS padlock / no WhatsApp button}.

We prepared a short free review with the five fixes that would bring you more enquiries from Google. Shall I send it over? No obligation.

Best regards,
Mahdi — MEFTACARD Studio
https://meftacard.com/studio/ · Telegram @meftacard
```

## 4. Follow-up after 3 days (Arabic)

```
مرحباً مجدداً {الاسم}، أردت فقط التأكد أن رسالتي وصلت 🙏
المراجعة المجانية لموقعكم جاهزة، أرسلها لكم متى ما ناسبكم.
```

## 5. After sending the review (Arabic)

```
هذه المراجعة المختصرة لموقعكم 👆
أهم شيء برأينا هو {الإصلاح رقم 1}.
إذا أحببتم أن ننفذ الإصلاحات، نرسل لكم سعراً ثابتاً، والعمل أونلاين بالكامل والدفع بعملة تيثر (USDT).
وإذا عندكم أي سؤال عن المراجعة، أنا موجود.
```

---

## Rules that keep outreach effective and respectful
- One message, one specific observation, one offer. No long sales pitch.
- Contact businesses only on their **public business** channels (website contact, business WhatsApp/Instagram). Do not message personal numbers.
- If someone says no or does not reply after one follow-up, stop.
- Never promise rankings or results you cannot guarantee.

## Turning a free-review request into a report (2 minutes)
```
python3 meftacard-studio/build/site-review.py <their-site> --lang ar --out review.md
```
Read the report, adjust anything that does not fit, then send it. Use `--lang en` for English.

## Qualifying a list of sites quickly
```
python3 meftacard-studio/build/site-review.py --score site1.com site2.com site3.com
```
Low scores (below 60) are the best prospects.
