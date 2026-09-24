# MASTER PROMPT — نسخه نهایی ادغام‌شده

## IRAN AUTOMOTIVE AFTERMARKET — BRAND + E-COMMERCE + CRM + ERP PLATFORM

> این نسخه، دو پرامپت قبلی (نسخه «Global Brand» و نسخه «Iran-First / CRM / ERP») را در یک پرامپت واحد ادغام می‌کند،
> تکرارها را حذف می‌کند، و بخش‌هایی را اضافه می‌کند که در هر دو نسخه جا افتاده بودند:
> واقعیت‌های خاص بازار ایران (نوسان ارز، چک، سامانه مودیان، اینماد، تحریم)، معماری Database،
> مشخصات Admin Panel، و وضعیت فعلی زیرساخت (WordPress + Hello Elementor).
>
> از بخش `START` به پایین را عیناً کپی کن و به Gemini بده.

---

## START — متن پرامپت

### 0. ROLE

تو از این لحظه در نقش یک تیم چندتخصصی در سطح جهانی فعالیت می‌کنی؛ نه یک دستیار معمولی.

نقش‌های هم‌زمان:

- Automotive Aftermarket Strategist و Spare Parts Business Consultant
- Iranian Market Analyst و Persian E-commerce Specialist
- Brand Strategist و Naming Expert و Brand Identity Designer
- E-commerce Architect و Product Manager
- CRM Architect و ERP Consultant و Inventory Management Specialist
- B2B Sales Strategist و B2C Growth Strategist
- Data Architect و Financial & Sales Analytics Specialist
- UX/UI Designer (Mobile-First, RTL)
- SEO Strategist و Digital Marketing Strategist
- Competitive Intelligence Analyst

هدف: ساخت یک **کسب‌وکار واقعی و قابل توسعه در بازار ایران** — نه صرفاً طراحی یک سایت زیبا یا چند اسم.

---

### 1. CRITICAL RULES (قبل از هر چیز)

1. **بدون تحقیق اسم پیشنهاد نده.** ترتیب کار همیشه:
   `RESEARCH → STRATEGY → POSITIONING → NAMING → BRAND → PRODUCT → SYSTEM → WEBSITE → SALES → GROWTH`
2. **دامنه را حدس نزن.** اگر ابزار جستجوی واقعی داری، `.ir` و `.com` را بررسی کن و منبع بررسی را بنویس. اگر نداری: `UNVERIFIED`.
3. **Trademark را تأییدشده اعلام نکن.** فقط `Preliminary Screening` ارائه بده، نه نظر حقوقی قطعی.
4. **داده ساختگی نساز.** آمار، اندازه بازار، نام رقیب، قیمت یا ادعا بدون منبع = ممنوع.
5. برچسب‌گذاری اجباری:
   - `VERIFIED (منبع: ...)` — داده واقعی با منبع
   - `UNVERIFIED` — اطلاعاتی که نتوانستی بررسی کنی
   - `ASSUMPTION` — عدد فرضی برای مدل‌سازی
   - `HYPOTHESIS` — فرضیه استراتژیک
   - `NEEDS VALIDATION` — نیاز به بررسی میدانی یا حقوقی
6. هیچ Feature‌ای را فقط برای زیبایی اضافه نکن. هر Feature باید با حداقل یکی از این‌ها توجیه شود:
   `Revenue / Cost Reduction / Customer Experience / Operational Efficiency / Data / Scalability / Retention / Trust`
7. هر تصمیم مهم را با این چهار بخش توضیح بده: `WHY / HOW / EXPECTED IMPACT / RISK`
8. از عبارت «بهترین گزینه» بدون شرط استفاده نکن؛ بگو هر گزینه **در چه شرایطی** مناسب‌تر است.
9. از تقلید مستقیم برندهای مشهور خودداری کن.
10. B2B از روز اول در معماری دیده شود، حتی اگر MVP فقط B2C باشد.
11. Mobile-First، فارسی و RTL از ابتدا.

---

### 2. BUSINESS OBJECTIVE

می‌خواهم یک برند تخصصی در حوزه **فروش لوازم یدکی و قطعات خودرو** در بازار ایران بسازم که از ابتدا با معماری حرفه‌ای طراحی شود تا در آینده به یک
**AUTOMOTIVE AFTERMARKET PLATFORM** تبدیل شود.

سیستم باید از این مشتریان/ذی‌نفعان پشتیبانی کند:
B2C، B2B، عمده‌فروشی، خرده‌فروشی، تعمیرگاه‌ها، مکانیک‌ها، فروشندگان قطعات، شرکت‌ها، ناوگان‌ها، نمایندگان، تأمین‌کنندگان.

دامنه محصول قابل توسعه به:
قطعات مصرفی، موتوری، برقی، بدنه، جلوبندی، ترمز، تعلیق، گیربکس، فیلترها، روغن و روانکار، باتری، لاستیک، لوازم جانبی، ابزار تعمیرگاهی —
برای خودروهای **داخلی** (ایران‌خودرو، سایپا و ...)، **چینی (مونتاژ داخل)** و **خارجی**؛ شامل OEM، Aftermarket و (در صورت امکان قانونی) قطعات استوک.

**این پروژه فقط فروشگاه اینترنتی نیست.** ما یک سیستم یکپارچه نیاز داریم:

```
CUSTOMER ● PRODUCT ● FITMENT ● INVENTORY ● PRICE ● ORDER ● PAYMENT
● CRM ● ACCOUNTING ● SUPPLIER ● SALES ● ANALYTICS ● WEBSITE
```

**اصل مهم معماری:** «حساب کاربری مشتری»، «پرونده CRM» و «حساب مالی مشتری» سه ماژول **جدا ولی متصل** هستند
(با یک `Customer ID` واحد). یک تعمیرگاه می‌تواند هم‌زمان اکانت خرید، پرونده CRM، سابقه سفارش، مانده‌حساب، سقف اعتبار و قیمت اختصاصی داشته باشد.

---

### 3. IRAN-FIRST REALITIES (الزامات خاص ایران)

تمام معماری اولیه برای شرایط ایران طراحی شود. علاوه بر موارد پایه
(فارسی، RTL، تومان/ریال، موبایل ایران، کد ملی در صورت نیاز، استان/شهر/کد پستی، پشتیبانی تلفنی، پیامک)،
این واقعیت‌ها را **صریحاً تحلیل کن** و در طراحی لحاظ کن:

| موضوع | چه چیزی باید بررسی/طراحی شود |
|---|---|
| **نوسان ارز و تورم** | قیمت قطعات (به‌خصوص وارداتی) مرتب تغییر می‌کند. سیستم قیمت‌گذاری باید از «ضریب/فرمول قیمت» (مثلاً بر مبنای نرخ ارز یا درصد افزایش گروهی) پشتیبانی کند، نه فقط ویرایش دستی. اعتبار زمانی پیش‌فاکتورها و قیمت‌ها حیاتی است. |
| **تقویم شمسی** | تمام تاریخ‌ها در UI و گزارش‌ها شمسی؛ در Database استاندارد (UTC). |
| **درگاه پرداخت** | درگاه‌های متصل به شاپرک، کارت‌به‌کارت با تأیید دستی، پرداخت در محل، و بررسی خرید اقساطی/BNPL داخلی. نام سرویس‌ها و شرایطشان را با برچسب VERIFIED/UNVERIFIED بیاور. |
| **چک (B2B)** | در بازار عمده ایران، پرداخت با چک رایج است. حسابداری مشتری باید چک دریافتنی/پرداختنی، تاریخ سررسید، وضعیت (وصول، برگشتی) و ارتباط با سامانه چک صیادی را پشتیبانی کند. |
| **مالیات و فاکتور رسمی** | الزامات سامانه مودیان / صورتحساب الکترونیکی و نرخ فعلی مالیات بر ارزش افزوده را بررسی کن (`NEEDS VALIDATION`). سیستم باید فاکتور رسمی و غیررسمی را تفکیک کند. |
| **اعتماد و مجوز** | نماد اعتماد الکترونیکی (اینماد)، ساماندهی، و قوانین تجارت الکترونیک ایران. |
| **ارسال** | پست، شرکت‌های پیک و ارسال بین‌شهری، و باربری برای قطعات حجیم/سنگین (بدنه، گیربکس). هزینه ارسال بر اساس وزن/حجم. |
| **تحریم و زیرساخت** | بسیاری از سرویس‌های خارجی (پلتفرم‌های فروشگاهی SaaS، درگاه‌های بین‌المللی، برخی Cloudها و APIها) از ایران در دسترس نیستند یا ریسک قطع دارند. هر انتخاب تکنولوژی را از نظر «قابل استفاده از ایران» و «ریسک قطع» بررسی کن. |
| **اختلال اینترنت** | در شرایط محدودیت اینترنت بین‌الملل، سایت و پنل مدیریت باید روی هاست داخل ایران کار کند و به سرویس خارجی حیاتی وابسته نباشد. |
| **اصالت کالا** | قطعات تقلبی مشکل جدی بازار است. طراحی مکانیزم اصالت (برچسب/QR/سریال، گارانتی، منبع تأمین). |
| **داده سازگاری خودرو** | دیتابیس‌های بین‌المللی Fitment ممکن است پوشش ضعیفی برای خودروهای ایرانی داشته باشند و لایسنسشان تحت تأثیر تحریم باشد (`UNVERIFIED`). احتمالاً باید دیتابیس Fitment داخلی ساخته شود — استراتژی ساخت آن را پیشنهاد بده. |

---

### 4. CURRENT TECH CONTEXT

در حال حاضر یک سایت **WordPress با قالب Hello Elementor** آماده شده است.
بررسی کن و صادقانه بگو:

- آیا WordPress + WooCommerce (+ افزونه‌های فارسی) برای **MVP** کافی است؟
- در چه نقطه‌ای از رشد (تعداد SKU، مشتری B2B، حجم سفارش، نیاز به حسابداری/چک/اعتبار) این معماری به سقف می‌رسد؟
- مسیر مهاجرت چیست؟ (مثلاً WooCommerce به‌عنوان Frontend فروش + Backend/ERP جدا، یا Headless، یا سیستم اختصاصی)
- چه داده‌ها و ساختارهایی باید **از روز اول** طوری طراحی شوند که مهاجرت بعدی بدون از دست دادن داده ممکن باشد؟ (SKU، Customer ID، Fitment، Price History)

---

### 5. DOMAIN STRATEGY

- دامنه اصلی: **`.ir`** (مثلاً `brand.ir`)
- دامنه `.com`: دارایی استراتژیک برای توسعه آینده و حفاظت برند
- محدودیت‌های ثبت `.ir` (ثبت از طریق ایرنیک و الزامات هویتی) و محدودیت‌های ثبت/پرداخت `.com` از ایران را بررسی کن.
- وضعیت دامنه را **هرگز** حدس نزن → فقط `VERIFIED` با منبع یا `UNVERIFIED`.

---

### 6. BRAND NAMING

اسم باید: کوتاه، ساده، قابل تلفظ در فارسی و انگلیسی، قابل یادآوری، مدرن، ایرانی‌پسند، مناسب Automotive، مناسب B2B و B2C، و قابل توسعه باشد.

- نباید بیش از حد به مفهوم «قطعه» محدود شود؛ برند باید بتواند به خدمات، تعمیرگاه، ناوگان، تکنولوژی خودرو، Marketplace و B2B گسترش یابد.
- از اسم‌های طولانی، کاملاً توصیفی، املای پیچیده (در فارسی یا انگلیسی)، شبیه برندهای مشهور، یا دارای ریسک Trademark پرهیز کن.
- املای فارسی و لاتین باید یک‌به‌یک و بدون ابهام باشد (کاربر بتواند اسم را بعد از شنیدن در تلفن، درست تایپ کند).

**فرآیند:**

1. حداقل **50 نام** تولید کن (در چند خانواده: ریشه فارسی، ترکیبی، انتزاعی، لاتین‌پایه).
2. غربال با معیارها (امتیاز 1 تا 5 در یک جدول):
   Brandability، Memorability، Pronunciation، Persian Fit، English Fit، Automotive Fit، B2B Fit، B2C Fit، Expansion Potential،
   Trademark Risk، Search Collision، Social Handle Collision، Domain Potential
3. **TOP 10** با تحلیل کامل برای هر نام:
   Brand Logic، Brand Meaning، Pronunciation (فارسی + لاتین)، Positioning، Potential Tagline (فارسی و انگلیسی)،
   `.ir` Domain Status، `.com` Domain Status، Trademark Preliminary Screening، Main Risk

---

### 7. CUSTOMER DATABASE & ACCOUNT

**رکورد مشتری (حداقل):**
Customer ID، نام، نام خانوادگی / نام شرکت، شناسه ملی یا کد ملی (اختیاری)، کد اقتصادی (برای فاکتور رسمی)، موبایل، تلفن، ایمیل،
استان، شهر، آدرس‌ها، کد پستی، تاریخ ثبت‌نام، نوع مشتری، خودروهای مشتری، سفارش‌ها، تعداد و مبلغ خرید، آخرین خرید، میانگین خرید،
بدهی، پرداخت‌ها، چک‌ها، اعتبار، فاکتورها، سطح قیمت، تخفیف اختصاصی، فروشنده اختصاصی، یادداشت فروشنده، وضعیت مشتری.

**پنل مشتری:**
Profile، Orders، Invoices، Payments، Account Statement، Addresses، Vehicles (Garage)، Favorites، Warranty، Returns، Credit، Loyalty، Support Tickets.

**Vehicle Garage:**
مشتری خودروهایش را ثبت کند: برند، مدل، سال، تیپ، موتور، گیربکس، VIN (اختیاری)، پلاک (اختیاری و با رعایت حریم خصوصی).
هنگام خرید، گزینه «**قطعات مخصوص خودروهای من**» نمایش داده شود.

---

### 8. CRM, SEGMENTATION & SALES PIPELINE

CRM باید جواب این سؤال‌ها را بدهد: چه کسی مشتری است؟ چه خریده؟ کی؟ چند بار؟ چقدر؟ چقدر بدهکار است؟ آخرین تماس کی بوده؟ فروشنده چه گفته؟ چه کالایی احتمالاً دوباره لازم دارد؟

**Segmentها:** New، Regular، VIP، Wholesale، Mechanic، Repair Shop، Dealer، Fleet، Inactive، High Value، Credit Customer
+ امکان ساخت **Segment سفارشی** با فیلتر (مثلاً: «تعمیرگاه‌های تهران که ۹۰ روز خرید نکرده‌اند»).

**Sales Pipeline:**
`Lead → Contacted → Interested → Quotation → Negotiation → Order → Paid → Delivered → Repeat Customer`

امکانات: Follow-up، Reminder، Notes، Call Log، ثبت پیام‌ها (در حد مجاز قانونی و فنی)، و **Sales Representative** اختصاصی
(تمام فعالیت‌های مشتری به حساب فروشنده او ثبت شود؛ پایه محاسبه پورسانت).

**پرسوناها** (در Phase 1 تحلیل شوند): خریدار عادی، راننده حرفه‌ای/تاکسی، مکانیک، تعمیرگاه، فروشنده قطعات (مغازه)، ناوگان، خریدار عمده.
برای هرکدام: Need، Pain Point، Buying Trigger، Objection، Trust Factor، Price Sensitivity، Search Behavior، Purchase Journey، Preferred Channel، Repeat Potential.

---

### 9. CUSTOMER ACCOUNTING & CREDIT

برای هر مشتری یک **حساب مالی** (دفتر معین مشتری):
بدهکار، بستانکار، پرداخت، فاکتور، تخفیف، برگشت از فروش، چک (دریافتی، سررسید، برگشتی)، مانده حساب.

مثال:

```
مشتری A — خرید: 20,000,000 تومان | پرداخت: 15,000,000 | مانده: 5,000,000
```

**اعتبار B2B:**

```
Credit Limit: 100,000,000 | Used: 40,000,000 | Available: 60,000,000
```

در زمان ثبت سفارش، سیستم بررسی کند: آیا اعتبار کافی است؟ (چک‌های وصول‌نشده در محاسبه اعتبار مصرف‌شده لحاظ شوند.)
اگر نه: مسدود کردن، نیاز به تأیید مدیر فروش، یا پرداخت بخشی از مبلغ.

---

### 10. PRODUCT, FITMENT & SEARCH

**رکورد محصول:**
Product ID، SKU، نام فارسی، نام انگلیسی، نام‌های عامیانه/مترادف، Brand، Category، Subcategory، Part Number، OEM Number(s)،
Cross-Reference (شماره‌های جایگزین)، Barcode، Supplier(s)، Country of Origin، Quality Tier (اصلی / OEM / Aftermarket / استوک)،
قیمت‌ها (بخش 11)، Stock (بخش 12)، Minimum Stock، Warranty، Weight/Dimensions، Images، Specifications، Compatible Vehicles، Status.

**Vehicle Fitment (هسته تجربه کاربری):**
`Brand → Model → Year → Engine → Trim` — سیستم فقط قطعات سازگار را نشان دهد.
مدل داده Fitment ساختارمند باشد (نه متن آزاد در توضیحات محصول).

**Smart Search:**
نام فارسی، نام انگلیسی، نام عامیانه، Part Number، OEM، SKU، Barcode، خودرو، برند، دسته — حتی با غلط تایپی و فینگلیش.
مثال‌ها که همه باید به نتیجه درست برسند:
`لنت 206` / `لنت ترمز جلو پژو ۲۰۶` / `lent 206` / `Brake Pad Peugeot 206` / شماره فنی قطعه.
(توجه: اعداد فارسی و انگلیسی، «ی/ك» عربی و فارسی، و نیم‌فاصله باید نرمال‌سازی شوند.)

**صفحه محصول:** نام، تصاویر، برند، Part/OEM Number، سازگاری، مشخصات، قیمت، موجودی، گارانتی، اصالت، زمان ارسال، شرایط مرجوعی،
راهنمای نصب، محصولات مرتبط/جایگزین، نظرات مشتریان.

---

### 11. PRICE MANAGEMENT

سطوح قیمت: Cost، Retail، Wholesale، VIP، B2B، Dealer، Promotional، و **قیمت اختصاصی هر مشتری**.

- **Price History**: قیمت قبلی، قیمت جدید، تاریخ، کاربر تغییردهنده، دلیل.
- **Bulk Price Rules**: افزایش درصدی برای یک برند/دسته/تأمین‌کننده، یا قیمت بر مبنای فرمول (Cost × ضریب، یا نرخ ارز × قیمت ارزی × ضریب).
- **حفاظت حاشیه سود**: هشدار اگر قیمت فروش پایین‌تر از Cost یا حداقل حاشیه تعیین‌شده باشد.
- **اعتبار پیش‌فاکتور**: قیمت در Quotation تا تاریخ مشخص ثابت بماند.

---

### 12. INVENTORY, MULTI-WAREHOUSE & SUPPLIERS

- موجودی Real-Time با تفکیک: Available، Reserved، Sold، Damaged، Returned، Incoming.
- **Multi-Warehouse** (مثلاً تهران، کرج، شیراز، تبریز) با موجودی جداگانه و انتقال بین انبار.
- **Low Stock Alert** (مثال: `Brake Pad XYZ — Stock: 3 / Minimum: 5`).
- **Dead Stock Report** (خواب سرمایه): کالاهایی که X روز فروش نداشته‌اند.
- **Supplier Database**: نام، رابط، تلفن، محصولات، سابقه خرید، قیمت‌ها، شرایط پرداخت، بدهی/بستانکاری، چک‌ها، Lead Time، Reliability Data.
- **Purchase Orders** از تأمین‌کننده و ورود به انبار.

---

### 13. ORDERS, QUOTATIONS & B2B PORTAL

**سفارش:** Order ID، Customer، Products، Quantity، Price، Discount، Shipping، Payment، Status، Invoice، Tracking، Return.
**Status:** `Pending → Confirmed → Processing → Packed → Shipped → Delivered` و `Cancelled` / `Returned`.

**Quotation (پیش‌فاکتور):** فروشنده برای مشتری پیش‌فاکتور بسازد (مثلاً ۲۰ قلم، قیمت ویژه، تخفیف، شرایط پرداخت، مدت اعتبار)؛
مشتری تأیید کند → تبدیل خودکار به **Order**. ارسال لینک پیش‌فاکتور از طریق پیامک.

**B2B Portal:**
Special Pricing، Wholesale، Credit، Bulk Order، **Quick Order** (ورود لیست SKU/Part Number یا آپلود فایل)، Repeat Order،
Invoice، Account Statement، Purchase History، Sales Representative، Support.

---

### 14. ADMIN PANEL & "NO DEVELOPER DEPENDENCY"

پنل مدیریت یکی از مهم‌ترین بخش‌های پروژه است. اصل: **ADMIN-FIRST** — عملیات روزمره نباید نیاز به تغییر کد داشته باشد.

بدون برنامه‌نویس باید بتوانم مدیریت کنم:
محصول، قیمت، موجودی، مشتری، سفارش، پیش‌فاکتور، فاکتور، تخفیف، دسته‌بندی، برند، خودرو/Fitment، محتوا، کاربر، فروشنده، انبار، تأمین‌کننده.

**Bulk Update (حیاتی):**
- Import/Export با **Excel و CSV** (مثال ستون‌ها: `SKU | Product | Price | Stock | Warehouse`)
- آپلود فایل → **پیش‌نمایش تغییرات و گزارش خطا قبل از اعمال** → تأیید → اعمال
- امکان **Rollback** یک Import اشتباه
- Bulk Edit مستقیم در جدول پنل (فیلتر + ویرایش گروهی)
- API برای اتصال سیستم‌های دیگر (مثلاً نرم‌افزار حسابداری فعلی)

مثال معیار موفقیت: «اگر فردا ۵۰۰ محصول جدید داشته باشم یا قیمت ۱۰۰۰ محصول تغییر کند، خودم در کمتر از یک ساعت انجامش بدهم.»

---

### 15. ROLES, AUDIT LOG & SECURITY

**RBAC — Roles:**
Super Admin، Admin، Sales Manager، Sales Agent، Warehouse، Accounting، Customer Support، Content Manager، Marketing، Supplier، Customer.
هر نقش فقط به بخش لازم دسترسی دارد (مثلاً انباردار Cost Price را نبیند).

**Audit Log:** چه کسی؟ چه چیزی؟ کی؟ مقدار قبلی و جدید؟
مثال: `Ali changed product price — Old: 10,000,000 — New: 11,500,000 — Timestamp: ...`

**Security:** Authentication (ورود با OTP پیامکی)، Authorization، 2FA برای کاربران داخلی، Password Hashing، Session Security،
Backup خودکار (داخل و خارج از سرور)، Rate Limiting، Fraud Detection (سفارش‌های مشکوک، کارت‌به‌کارت جعلی)، Data Privacy.

---

### 16. DASHBOARDS, ANALYTICS & BI

**Sales Dashboard:** Today's Sales، Today's Orders، Revenue، Gross Profit، Outstanding Receivables، چک‌های سررسید این هفته،
Inventory Value، Low Stock، Top Products، Top Customers، New vs Repeat Customers.

**Financial Dashboard:** Sales، Purchases، Revenue، Gross Profit، Expenses، Receivables، Payables، Cash Flow، Inventory Value.

**Product Analytics:** Views، Orders، Sales، Revenue، Margin، Stock، Return Rate، Repeat Purchase، Search-without-result (قطعاتی که جستجو شده ولی موجود نبوده).

**Customer Analytics:** Total Orders، Total Spend، AOV، Last Purchase، LTV، Outstanding Balance، Favorite Categories/Brands.

**سؤال‌های BI که سیستم باید در آینده جواب دهد:**
پرفروش‌ترین کالا؟ باارزش‌ترین مشتری؟ سودآورترین کالا؟ کالای خواب سرمایه؟ تأمین‌کننده با بهترین قیمت؟ کمبودها؟ مشتریانی که مدتی خرید نکرده‌اند؟

---

### 17. AUTOMATION, NOTIFICATIONS & MARKETING

- **Service/Replacement Reminder:** مثلاً مشتری ۶ ماه پیش لنت یا فیلتر خریده → یادآوری پیامکی.
- **Cross-Sell:** پیشنهاد محصول مکمل (لنت → دیسک، روغن → فیلتر روغن).
- **Win-back:** مشتری غیرفعال → پیشنهاد اختصاصی.
- **Notifications** برای: سفارش، پرداخت، ارسال، تخفیف، کاهش موجودی، بدهی، سررسید چک، فاکتور، پیگیری مشتری.
- کانال‌ها: پیامک (اولویت اول در ایران)، ایمیل، Push، و پیام‌رسان‌ها در حد مجاز.
- اتصال به CRM و Analytics.

---

### 18. WEBSITE, UX & SEO

**وب‌سایت:** Mobile-First، RTL، سریع (حتی روی اینترنت موبایل ضعیف)، SEO-Friendly، امن، مقیاس‌پذیر.

**صفحات:** Home، Shop، Categories، Brands، Vehicles (Vehicle Selector)، Product Detail، Search، Compare، Garage، Account، Orders،
Support، B2B، Blog، Contact، صفحات اعتماد (اصالت، گارانتی، مرجوعی، درباره ما، نماد اعتماد).

**SEO Architecture:**

```
/parts/            /parts/brake/        /parts/brake/pads/
/cars/             /cars/peugeot/       /cars/peugeot/206/
/brands/           /brands/<brand>/
/products/<slug>/
/blog/
```

+ صفحات **Programmatic SEO**: `خودرو + قطعه`، `برند + قطعه`، `مدل + قطعه` (مثلاً «لنت ترمز جلو پژو ۲۰۶»)
+ Buying Guides، مقایسه‌ها، FAQ، Local SEO، Schema (Product، Offer، Breadcrumb).
تصمیم درباره slug فارسی یا لاتین را با دلیل مشخص کن.

**Trust System:** تأیید اصالت، گارانتی، مرجوعی شفاف، فاکتور، رهگیری مرسوله، نظرات خریداران واقعی، پشتیبانی تخصصی (کارشناس قطعه)، راهنمای نصب.

---

### 19. TECHNOLOGY ARCHITECTURE

- **API-First** از روز اول تا اپلیکیشن آینده بدون بازسازی کل سیستم ساخته شود.
- PWA در برابر اپ Android/iOS را بررسی کن (با توجه به محدودیت‌های فروشگاه‌های اپ برای کاربران ایرانی و وجود فروشگاه‌های اپ داخلی).
- برای هر لایه چند گزینه + دلیل + **سازگاری با شرایط ایران (تحریم، هاست داخلی)**:
  Frontend، Backend، Database، Search (با پشتیبانی درست از فارسی)، Cache، Storage، API، Authentication، Payment، SMS، CRM، Accounting، Analytics، Monitoring، Backup، Hosting/Cloud.
- **Scalability:** محصولات `1K → 10K → 100K → 1M` و مشتریان `1K → 100K → 1M`.
- **AI Layer (فاز بعد):** AI Search، AI Parts Finder (مثلاً از روی عکس قطعه یا توصیف مشکل)، AI Customer Support، AI Sales Assistant، AI Demand/Inventory Forecast، AI Pricing Analysis، AI Recommendation.

---

### 20. BRAND SYSTEM & VISUAL DIRECTION (فقط بعد از تأیید من)

Brand Strategy: Mission، Vision، Purpose، Promise، Positioning، Personality، Archetype، Tone of Voice، Values، UVP.
Brand Identity: Name، Tagline، Logo، Color، Typography (فونت فارسی + لاتین هماهنگ)، Iconography، Photography، Packaging، Shipping Box، Invoice، Business Card، Uniform، Signage، Social، Website Identity.

جهت بصری: **Modern، Minimal، Technical، Trustworthy، Premium، Scalable.**
از کلیشه‌های Automotive (چرخ‌دنده، آچار، موتور، ماشین کامل، فلش‌های تکراری) فقط وقتی استفاده کن که ایده واقعاً متمایزی بسازد.

**Brand Architecture (در صورت منطقی بودن):**

```
                 MASTER BRAND
                      │
      ┌───────────────┼───────────────┐
     B2C             B2B          SUPPLIERS
      └───────────────┼───────────────┘
                 CORE PLATFORM
      ┌───────────────┼───────────────┐
     CRM          INVENTORY       ACCOUNTING
      └───────────────┼───────────────┘
                 DATA PLATFORM
                      │
                AI / ANALYTICS

توسعه آینده: PARTS → SERVICES → GARAGE → FLEET → LOGISTICS → AUTOMOTIVE TECH
```

---

### 21. ROADMAP

| فاز | عنوان |
|---|---|
| 1 | Market Research & Brand Discovery |
| 2 | Brand Strategy |
| 3 | Naming (نهایی‌سازی) |
| 4 | Domain & Trademark Screening |
| 5 | Database Architecture |
| 6 | Admin Panel |
| 7 | UX/UI |
| 8 | E-commerce MVP |
| 9 | CRM |
| 10 | Inventory & Suppliers |
| 11 | Accounting (حساب مشتری، چک، فاکتور رسمی) |
| 12 | B2B Portal |
| 13 | Analytics & BI |
| 14 | AI |
| 15 | Scale |

برای هر فاز: Objective، Tasks، Team، Budget Category، KPI، Risk، Exit Criteria.
(Database و Admin Panel عمداً قبل از UX/UI آمده‌اند؛ چون «قیمت و موجودی را سریع تغییر بدهم + مشتری، بدهی و خریدش را ببینم» قلب این کسب‌وکار است.)

---

### 22. FINAL OUTPUT — فقط PHASE 1

در این پاسخ **فقط Phase 1** را انجام بده:

1. تحلیل بازار لوازم یدکی ایران (اندازه، روند، اثر تحریم و ارز بر عرضه) — با منبع یا برچسب
2. ساختار بازار (زنجیره واردکننده/تولیدکننده → عمده‌فروش → بنکدار → مغازه → تعمیرگاه → مصرف‌کننده؛ و نقش بازارهای سنتی قطعه)
3. بخش‌بندی مشتریان و ۷ پرسونا
4. مشکلات مشتری (تشخیص قطعه درست، اصالت، قیمت، موجودی، اعتماد، ارسال، گارانتی، مرجوعی)
5. رقبا — مستقیم (فروشگاه‌های آنلاین تخصصی قطعه) و غیرمستقیم (مارکت‌پلیس‌های عمومی، موتورهای مقایسه قیمت، فروش سنتی، شبکه‌های اجتماعی) — با Competitive Gap Analysis
6. مدل‌های کسب‌وکار (حداقل ۵: Pure E-commerce، Marketplace، B2B Platform، Hybrid B2B+B2C، Parts Intelligence + Commerce) با Revenue Model، Initial Capital، Complexity، Scalability، Margin، Operational Difficulty، Technology Requirement، Competitive Advantage
7. فرصت‌ها و شکاف‌های استراتژیک بازار
8. قلمروهای جایگاه‌یابی برند (Brand Positioning Territories)
9. Naming Strategy
10. حداقل **۵۰ نام** پیشنهادی
11. جدول غربال اولیه
12. **۱۰ نام منتخب** با تحلیل کامل
13. بررسی اولیه `.ir` و `.com` (فقط VERIFIED با منبع یا UNVERIFIED)
14. معماری پیشنهادی برند
15. پیشنهاد ساختار کسب‌وکار + ارزیابی اولیه اینکه WordPress/WooCommerce فعلی برای MVP کافی است یا نه
16. فهرست **سؤال‌هایی که باید از من بپرسی** (سرمایه اولیه، شهر، دسترسی به تأمین‌کننده، تمرکز روی خودروهای داخلی/چینی/خارجی، تیم) تا Phase 2 دقیق‌تر شود

**بعد از پایان Phase 1 متوقف شو** و از من تأیید بگیر.
تا تأیید من وارد طراحی نهایی Logo، Website، UI، Database یا Code **نشو**.

---

### ULTIMATE GOAL

یک برند ایرانی تخصصی و قابل توسعه در حوزه Automotive Aftermarket که از یک فروشگاه لوازم یدکی شروع می‌شود،
اما از نظر تکنولوژی و معماری قابلیت تبدیل شدن به یک پلتفرم بزرگ B2B + B2C را دارد.

تمرکز: **TRUST · DATA · CUSTOMER · INVENTORY · SALES · CRM · SCALABILITY · AUTOMATION**

**اکنون Phase 1 را شروع کن.** قبل از هر پیشنهاد نام یا طراحی، بازار و فرصت را تحلیل کن.
