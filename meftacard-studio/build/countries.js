// Generates one Studio page per country, in Arabic and English:
//   dist/studio/<slug>/index.html   and   dist/ar/studio/<slug>/index.html
// Each page carries country-specific facts (cities, payments, domain, data law, languages)
// so it is genuinely useful locally, not a copy with the country name swapped.
// Run: node build/countries.js
const fs = require('fs');
const path = require('path');
const ROOT = path.resolve(__dirname, '..');
const BASE = 'https://meftacard.com';
const CSS = fs.readFileSync(path.join(ROOT, 'index.html'), 'utf8').match(/<style>([\s\S]*?)<\/style>/)[1];
const OG = `${BASE}/wp-content/uploads/2026/11/meftacard-studio-og.png`;
const MEDIA = JSON.parse(fs.readFileSync(path.join(ROOT, 'work/img/media.json'), 'utf8'));
const CIMG = { iraq:'qalaa', uae:'nahrain', 'saudi-arabia':'dijla', qatar:'nahrain', kuwait:'shatt', oman:'shatt', bahrain:'sanad', lebanon:'asfaltbama-desktop' };

const C = [
  { slug:'iraq', code:'IQ',
    en:{ name:'Iraq', in:'in Iraq', cities:'Baghdad, Basra, Erbil, Najaf, Mosul and Sulaymaniyah',
      pay:'ZainCash, FastPay and Qi Card', cur:'Iraqi dinar (IQD) or US dollars', domain:'.iq or .com',
      law:'Iraq has no single data-protection law yet, so we follow GCC-level practice by default.',
      lang:'Arabic and English, with Kurdish (Sorani) pages for the Kurdistan Region on request.',
      local:'Most Iraqi customers browse on mobile, often on unstable connections, and expect to reach you on WhatsApp or Telegram. Competition in Arabic search is still light compared with the Gulf, so a well-built site can climb quickly.',
      faq:[['Can you add ZainCash or Qi Card payments?','Yes. We integrate the local payment method your customers use, or keep ordering on WhatsApp if that suits your business better.'],
           ['Do you build Kurdish websites?','Yes. For businesses in Erbil, Sulaymaniyah and Duhok we can add Kurdish (Sorani) pages alongside Arabic and English.']]},
    ar:{ name:'العراق', in:'في العراق', cities:'بغداد والبصرة وأربيل والنجف والموصل والسليمانية',
      pay:'زين كاش وFastPay وكي كارد', cur:'الدينار العراقي أو الدولار', domain:'.iq أو .com',
      law:'لا يوجد في العراق قانون موحد لحماية البيانات حتى الآن، لذلك نطبق افتراضياً ممارسات دول الخليج.',
      lang:'العربية والإنجليزية، مع صفحات بالكردية (السورانية) لإقليم كردستان عند الطلب.',
      local:'أغلب العملاء في العراق يتصفحون من الجوال، وكثيراً على إنترنت غير مستقر، ويتوقعون التواصل عبر واتساب أو تيليغرام. والمنافسة في البحث العربي ما زالت أخف من الخليج، فيمكن للموقع الجيد أن يتقدم بسرعة.',
      faq:[['هل يمكن إضافة الدفع بزين كاش أو كي كارد؟','نعم. نربط طريقة الدفع المحلية التي يستخدمها عملاؤك، أو نبقي الطلب عبر واتساب إن كان ذلك أنسب لعملك.'],
           ['هل تصممون مواقع باللغة الكردية؟','نعم. للشركات في أربيل والسليمانية ودهوك يمكننا إضافة صفحات بالكردية (السورانية) إلى جانب العربية والإنجليزية.']]}},
  { slug:'uae', code:'AE',
    en:{ name:'the UAE', in:'in the UAE', cities:'Dubai, Abu Dhabi, Sharjah, Ajman and Ras Al Khaimah',
      pay:'cards, Apple Pay, and Tabby or Tamara instalments', cur:'UAE dirham (AED)', domain:'.ae or .com',
      law:'We follow the UAE personal data protection law (Federal Decree-Law No. 45 of 2021) for forms, chat and analytics.',
      lang:'English-first sites with full Arabic versions, since both are used daily in business.',
      local:'The UAE is one of the most competitive web markets in the region. Speed, a polished English and Arabic experience and clear pricing make the difference between a visit and an enquiry.',
      faq:[['Do you work with free-zone companies?','Yes. We work with mainland and free-zone businesses across all seven emirates.'],
           ['Can you integrate Tabby or Tamara?','Yes, for e-commerce sites on our Trade and Enterprise packages, together with card and Apple Pay checkout.']]},
    ar:{ name:'الإمارات', in:'في الإمارات', cities:'دبي وأبوظبي والشارقة وعجمان ورأس الخيمة',
      pay:'البطاقات وApple Pay والتقسيط عبر تابي وتمارا', cur:'الدرهم الإماراتي', domain:'.ae أو .com',
      law:'نلتزم بقانون حماية البيانات الشخصية الإماراتي (المرسوم بقانون اتحادي رقم 45 لسنة 2021) في النماذج والمحادثات والتحليلات.',
      lang:'مواقع بالإنجليزية أولاً مع نسخة عربية كاملة، لأن اللغتين تُستخدمان يومياً في الأعمال.',
      local:'الإمارات من أكثر أسواق الويب تنافساً في المنطقة. السرعة وتجربة متقنة بالعربية والإنجليزية ووضوح الأسعار هي ما يحوّل الزيارة إلى طلب.',
      faq:[['هل تعملون مع شركات المناطق الحرة؟','نعم. نعمل مع شركات البر الرئيسي والمناطق الحرة في جميع الإمارات السبع.'],
           ['هل يمكن ربط تابي أو تمارا؟','نعم، للمتاجر الإلكترونية في باقتي التجارة والمؤسسات، إلى جانب الدفع بالبطاقة وApple Pay.']]}},
  { slug:'saudi-arabia', code:'SA',
    en:{ name:'Saudi Arabia', in:'in Saudi Arabia', cities:'Riyadh, Jeddah, Dammam, Mecca, Medina and Khobar',
      pay:'Mada, STC Pay, Apple Pay, and Tabby or Tamara', cur:'Saudi riyal (SAR)', domain:'.sa or .com',
      law:'We follow the Saudi Personal Data Protection Law, overseen by SDAIA, including notices and data handling for forms and chat.',
      lang:'Arabic-first sites with an English version for international partners.',
      local:'Saudi buyers expect Arabic first, fast mobile pages and familiar payment methods like Mada. Riyadh is the most competitive city; Jeddah and the Eastern Province still have room to grow.',
      faq:[['Can you integrate Mada and STC Pay?','Yes. Mada and STC Pay are standard on our Saudi e-commerce builds.'],
           ['Is the site Arabic-first?','Yes. For Saudi businesses we design in Arabic first and add English as a full second version.']]},
    ar:{ name:'السعودية', in:'في السعودية', cities:'الرياض وجدة والدمام ومكة والمدينة والخبر',
      pay:'مدى وSTC Pay وApple Pay وتابي وتمارا', cur:'الريال السعودي', domain:'.sa أو .com',
      law:'نلتزم بنظام حماية البيانات الشخصية الذي تشرف عليه سدايا، بما في ذلك الإشعارات ومعالجة بيانات النماذج والمحادثات.',
      lang:'مواقع بالعربية أولاً مع نسخة إنجليزية للشركاء الدوليين.',
      local:'المشتري السعودي يتوقع العربية أولاً وصفحات سريعة على الجوال وطرق دفع مألوفة مثل مدى. الرياض الأكثر تنافساً، بينما ما زالت جدة والمنطقة الشرقية تتسعان للنمو.',
      faq:[['هل تربطون مدى وSTC Pay؟','نعم. مدى وSTC Pay أساسيان في متاجرنا الإلكترونية في السعودية.'],
           ['هل يكون الموقع بالعربية أولاً؟','نعم. للشركات السعودية نصمم بالعربية أولاً ونضيف الإنجليزية كنسخة ثانية كاملة.']]}},
  { slug:'qatar', code:'QA',
    en:{ name:'Qatar', in:'in Qatar', cities:'Doha, Lusail, Al Wakrah and Al Khor',
      pay:'cards, Apple Pay and NAPS debit', cur:'Qatari riyal (QAR)', domain:'.qa or .com',
      law:'We follow Qatar\'s personal data privacy law (Law No. 13 of 2016) for forms, chat and analytics.',
      lang:'Arabic and English with equal depth, as government and corporate buyers use both.',
      local:'Qatar has a compact, high-value market with many energy and government-linked buyers. Trust signals, bilingual quality and fast support matter more than volume.',
      faq:[['Do you work with energy and industrial companies in Qatar?','Yes. Our Enterprise package is built for energy, industrial and group companies, with supplier portals and 24/7 support.'],
           ['Can the site be hosted in the region?','Yes. On Enterprise projects we can host in-region where your policy requires it.']]},
    ar:{ name:'قطر', in:'في قطر', cities:'الدوحة ولوسيل والوكرة والخور',
      pay:'البطاقات وApple Pay وبطاقات نابس', cur:'الريال القطري', domain:'.qa أو .com',
      law:'نلتزم بقانون حماية خصوصية البيانات الشخصية القطري (القانون رقم 13 لسنة 2016) في النماذج والمحادثات والتحليلات.',
      lang:'العربية والإنجليزية بالعمق نفسه، لأن الجهات الحكومية والشركات تستخدم اللغتين.',
      local:'سوق قطر صغير الحجم وعالي القيمة، وفيه كثير من المشترين في الطاقة والجهات المرتبطة بالحكومة. إشارات الثقة وجودة اللغتين وسرعة الدعم أهم من الكمية.',
      faq:[['هل تعملون مع شركات الطاقة والصناعة في قطر؟','نعم. باقة المؤسسات مصممة لشركات الطاقة والصناعة والمجموعات، مع بوابات الموردين ودعم على مدار الساعة.'],
           ['هل يمكن استضافة الموقع داخل المنطقة؟','نعم. في مشاريع المؤسسات يمكننا الاستضافة داخل المنطقة إذا تطلبت سياستكم ذلك.']]}},
  { slug:'kuwait', code:'KW',
    en:{ name:'Kuwait', in:'in Kuwait', cities:'Kuwait City, Hawalli, Salmiya, Farwaniya and Ahmadi',
      pay:'KNET, cards and Apple Pay', cur:'Kuwaiti dinar (KWD)', domain:'.kw or .com',
      law:'We apply GCC-level data-protection practice to forms, chat and analytics.',
      lang:'Arabic and English, with Arabic copy written for Kuwaiti customers.',
      local:'Kuwaiti customers shop heavily on mobile and Instagram and expect KNET at checkout. A fast site that links cleanly from social profiles converts best.',
      faq:[['Can you integrate KNET?','Yes. KNET is standard on our Kuwait e-commerce builds, alongside cards and Apple Pay.'],
           ['Can the site work with our Instagram store?','Yes. We link products and enquiries from Instagram to the site and to WhatsApp.']]},
    ar:{ name:'الكويت', in:'في الكويت', cities:'مدينة الكويت وحولي والسالمية والفروانية والأحمدي',
      pay:'كي نت والبطاقات وApple Pay', cur:'الدينار الكويتي', domain:'.kw أو .com',
      law:'نطبق ممارسات حماية البيانات المعتمدة في دول الخليج على النماذج والمحادثات والتحليلات.',
      lang:'العربية والإنجليزية، بمحتوى عربي مكتوب للعميل الكويتي.',
      local:'العميل الكويتي يتسوق كثيراً من الجوال وإنستغرام ويتوقع كي نت عند الدفع. الموقع السريع المرتبط جيداً بحسابات التواصل هو الأعلى تحويلاً.',
      faq:[['هل تربطون كي نت؟','نعم. كي نت أساسي في متاجرنا الإلكترونية في الكويت، إلى جانب البطاقات وApple Pay.'],
           ['هل يعمل الموقع مع متجرنا على إنستغرام؟','نعم. نربط المنتجات والاستفسارات من إنستغرام بالموقع وبواتساب.']]}},
  { slug:'oman', code:'OM',
    en:{ name:'Oman', in:'in Oman', cities:'Muscat, Salalah, Sohar, Nizwa and Sur',
      pay:'cards, Apple Pay and local bank gateways', cur:'Omani rial (OMR)', domain:'.om or .com',
      law:'We follow Oman\'s Personal Data Protection Law (Royal Decree 6/2022) for forms, chat and analytics.',
      lang:'Arabic and English, with Arabic-first content for local customers.',
      local:'Oman\'s online market is growing steadily, with tourism, logistics and industry outside Muscat underserved online. Local SEO in Salalah, Sohar and Nizwa is still very winnable.',
      faq:[['Do you work outside Muscat?','Yes. Everything is done online, so we work with businesses in Salalah, Sohar, Nizwa and anywhere in Oman.'],
           ['Can you build tourism and hotel sites?','Yes, with booking enquiries, WhatsApp and Arabic and English content for local and international guests.']]},
    ar:{ name:'عُمان', in:'في عُمان', cities:'مسقط وصلالة وصحار ونزوى وصور',
      pay:'البطاقات وApple Pay وبوابات البنوك المحلية', cur:'الريال العُماني', domain:'.om أو .com',
      law:'نلتزم بقانون حماية البيانات الشخصية العُماني (المرسوم السلطاني 6/2022) في النماذج والمحادثات والتحليلات.',
      lang:'العربية والإنجليزية، بمحتوى عربي أولاً للعملاء المحليين.',
      local:'السوق الإلكتروني في عُمان ينمو بثبات، والسياحة والخدمات اللوجستية والصناعة خارج مسقط ما زالت أقل حضوراً على الإنترنت. والسيو المحلي في صلالة وصحار ونزوى ما زال سهل المنافسة.',
      faq:[['هل تعملون خارج مسقط؟','نعم. كل العمل يتم عبر الإنترنت، لذلك نعمل مع الشركات في صلالة وصحار ونزوى وأي مكان في عُمان.'],
           ['هل تصممون مواقع السياحة والفنادق؟','نعم، مع طلبات الحجز وواتساب ومحتوى بالعربية والإنجليزية للضيوف المحليين والدوليين.']]}},
  { slug:'bahrain', code:'BH',
    en:{ name:'Bahrain', in:'in Bahrain', cities:'Manama, Muharraq, Riffa, Isa Town and Hamad Town',
      pay:'BenefitPay, cards and Apple Pay', cur:'Bahraini dinar (BHD)', domain:'.bh or .com',
      law:'We follow Bahrain\'s Personal Data Protection Law (Law No. 30 of 2018) for forms, chat and analytics.',
      lang:'English and Arabic, reflecting Bahrain\'s international business community.',
      local:'Bahrain is small and well connected, with a strong finance and services sector. Clear bilingual sites with BenefitPay and fast WhatsApp contact perform best.',
      faq:[['Can you integrate BenefitPay?','Yes. BenefitPay is standard on our Bahrain e-commerce builds, alongside cards and Apple Pay.'],
           ['Do you work with fintech and services companies?','Yes. We build bilingual sites, portals and AI agents for finance and professional-services firms.']]},
    ar:{ name:'البحرين', in:'في البحرين', cities:'المنامة والمحرق والرفاع ومدينة عيسى ومدينة حمد',
      pay:'BenefitPay والبطاقات وApple Pay', cur:'الدينار البحريني', domain:'.bh أو .com',
      law:'نلتزم بقانون حماية البيانات الشخصية البحريني (القانون رقم 30 لسنة 2018) في النماذج والمحادثات والتحليلات.',
      lang:'الإنجليزية والعربية، بما يناسب مجتمع الأعمال الدولي في البحرين.',
      local:'البحرين سوق صغير ومترابط بقطاع مالي وخدمي قوي. المواقع الثنائية اللغة الواضحة مع BenefitPay وتواصل سريع عبر واتساب هي الأفضل أداءً.',
      faq:[['هل تربطون BenefitPay؟','نعم. BenefitPay أساسي في متاجرنا الإلكترونية في البحرين، إلى جانب البطاقات وApple Pay.'],
           ['هل تعملون مع شركات التقنية المالية والخدمات؟','نعم. نبني مواقع وبوابات ووكلاء ذكاء اصطناعي ثنائيي اللغة لشركات المال والخدمات المهنية.']]}},
  { slug:'lebanon', code:'LB',
    en:{ name:'Lebanon', in:'in Lebanon', cities:'Beirut, Tripoli, Jounieh, Sidon and Zahle',
      pay:'cash on delivery, OMT and Whish Money, plus cards for export customers', cur:'US dollars', domain:'.lb or .com',
      law:'We apply GCC-level data-protection practice to forms, chat and analytics.',
      lang:'Arabic, English and French, since many Lebanese businesses work in all three.',
      local:'Many Lebanese businesses sell to the Gulf and the diaspora as much as locally. Lightweight pages that load on weak connections, and trilingual content, make a real difference.',
      faq:[['Can you build in French as well?','Yes. We add French alongside Arabic and English for businesses that need it.'],
           ['Can we sell to the Gulf and the diaspora?','Yes. Our Trade & Export package is built for selling abroad, with international SEO and quote requests.']]},
    ar:{ name:'لبنان', in:'في لبنان', cities:'بيروت وطرابلس وجونيه وصيدا وزحلة',
      pay:'الدفع عند الاستلام وOMT وWhish Money، والبطاقات لعملاء التصدير', cur:'الدولار الأمريكي', domain:'.lb أو .com',
      law:'نطبق ممارسات حماية البيانات المعتمدة في دول الخليج على النماذج والمحادثات والتحليلات.',
      lang:'العربية والإنجليزية والفرنسية، لأن كثيراً من الشركات اللبنانية تعمل باللغات الثلاث.',
      local:'كثير من الشركات اللبنانية تبيع للخليج والمغتربين بقدر ما تبيع محلياً. الصفحات الخفيفة التي تعمل على اتصال ضعيف والمحتوى بثلاث لغات يصنعان فرقاً حقيقياً.',
      faq:[['هل تصممون بالفرنسية أيضاً؟','نعم. نضيف الفرنسية إلى جانب العربية والإنجليزية للشركات التي تحتاجها.'],
           ['هل يمكننا البيع للخليج والمغتربين؟','نعم. باقة التجارة والتصدير مصممة للبيع خارج البلد، مع سيو دولي وطلبات عروض أسعار.']]}},
];

const ART = {
  en:[['website-design-cost-gcc-2026','How much does a website cost in the GCC?'],['oil-and-gas-website-design','Oil and gas website design'],['arabic-seo-hreflang-guide','Arabic SEO for bilingual websites'],['whatsapp-ai-agent-business-uae-saudi','WhatsApp AI agents for business'],['b2b-export-website-rfq','The B2B export website']],
  ar:[['website-design-cost-iraq-ar','كم تكلفة تصميم موقع في العراق؟'],['seo-iraq-guide-ar','السيو في العراق: دليل الشركات'],['google-business-profile-local-seo-iraq-ar','ملف Google التجاري والظهور في الخرائط'],['whatsapp-ai-bot-business-ar','بوت واتساب بالذكاء الاصطناعي للشركات'],['mobile-app-development-cost-iraq-gulf-ar','تكلفة تصميم تطبيق جوال']],
};

const T = {
  en:{ dir:'ltr', font:'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
    studio:'/studio/', home:'Studio', other:'عربي', skip:'Skip to content', nav:['Packages','Services','Contact'],
    h1:(c)=>`Website design and SEO services ${c.in}`,
    title:(c)=>`Website Design & SEO Services ${c.in.replace(/^in /,'in ')} | MEFTACARD Studio`,
    desc:(c)=>`Buy website design, SEO, website support and AI agents ${c.in}. Fully online, no visit needed. Pay in Tether (USDT), fixed prices from $1,250.`,
    lead:(c)=>`We design websites, run SEO and support your site and AI agents for businesses across ${c.cities}. Everything is done online, so you don't need to meet us in person.`,
    onH:'Fully online. No visit needed.', on:['A video call to understand your business and goals.','Designs and progress shared online for your feedback at every step.','Launch, training and support, all remote. In-person visits are optional on larger projects.'],
    pkH:'Packages', pkSub:'Same fixed prices everywhere. Tap a package to talk to us on Telegram.',
    p:[['Local','$1,250','one-time, USDT','For shops, clinics and service businesses that need to be found on Google and Maps.','Start with Local'],
       ['Trade & Export','$2,250','one-time, USDT','For distributors and exporters who need a catalog that brings in quote requests.','Start with Trade'],
       ['Enterprise','$25,000+','by milestone, USDT','For oil, gas, industrial and group companies: website, app, AI agents, SEO and 24/7 support.','Book a briefing']],
    cmp:'Compare everything included',
    ut:['Pay in Tether (USDT)',(c)=>`Pay with the Tether digital currency ${c.in}, with no bank transfer needed.`,'No international bank transfer or foreign card needed','Payment arrives in minutes, so work starts sooner','Same dollar price in every country','Low network fees on TRC-20, or ERC-20 if you prefer'],
    locH:(c)=>`What we set up for ${c.name}`, loc:['Cities for local SEO','Payments','Currency','Domain','Languages','Data protection'],
    svH:'Services', sv:[['Website design','Bilingual business sites, catalogs and online stores built to rank.'],['SEO services','Technical fixes, keyword research, content and local SEO for Google Maps.'],['Website support','Updates, backups, security and changes, monthly or on demand.'],['AI agent development and support','WhatsApp and web agents in Arabic and English, monitored after launch.'],['App design','iOS and Android apps for customers, dealers and field teams.']],
    fqH:'Questions', fqRemote:['Do we need to meet in person?','No. Everything, from the first call to launch and support, is done online. In-person support is available on the Trade & Export and Enterprise packages if you want it.'],
    fqPay:['How do we pay?','In USDT (Tether) on TRC-20 or ERC-20. Local and Trade: half to start, half at launch. Enterprise: per approved milestone.'],
    otherH:'Other countries', readH:'Further reading', ctH:'Tell us about your project', ctSub:'We reply within one business day with a scope, timeline and fixed price.',
    msg:(c)=>`Hello MEFTACARD Studio, I'm a business ${c.in} and I'd like to discuss a project.`,
    msgP:(c,p)=>`Hello MEFTACARD Studio, I'm a business ${c.in} and I'd like to start the ${p} package.`,
    copy:'© 2026 MEFTACARD Studio' },
  ar:{ dir:'rtl', font:'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@500;600&display=swap',
    studio:'/ar/studio/', home:'الاستوديو', other:'EN', skip:'تخطَّ إلى المحتوى', nav:['الباقات','الخدمات','تواصل معنا'],
    h1:(c)=>`تصميم مواقع وخدمات سيو ${c.in}`,
    title:(c)=>`تصميم مواقع وخدمات سيو ${c.in} | MEFTACARD Studio`,
    desc:(c)=>`شراء تصميم مواقع وسيو ودعم المواقع ووكلاء الذكاء الاصطناعي ${c.in}، أونلاين دون حاجة للحضور. الدفع بعملة تيثر وأسعار تبدأ من 1,250$.`,
    lead:(c)=>`نصمم المواقع ونعمل على السيو وندعم موقعك ووكلاء الذكاء الاصطناعي للشركات في ${c.cities}. كل العمل يتم أونلاين، فلا حاجة للقائنا حضورياً.`,
    onH:'تصميم أونلاين بالكامل، دون حاجة للحضور.', on:['مكالمة فيديو لفهم عملك وأهدافك.','نشارك التصاميم وسير العمل أونلاين لتبدي رأيك في كل مرحلة.','الإطلاق والتدريب والدعم عن بُعد. والزيارات الحضورية اختيارية في المشاريع الأكبر.'],
    pkH:'الباقات', pkSub:'الأسعار ثابتة في كل الدول. اضغط على الباقة لتتحدث معنا على تيليغرام.',
    p:[['المحلية','$1,250','دفعة واحدة، USDT','للمحلات والعيادات وشركات الخدمات التي تريد الظهور في بحث Google وخرائطه.','ابدأ بالباقة المحلية'],
       ['التجارة والتصدير','$2,250','دفعة واحدة، USDT','للموزعين والمصدّرين الذين يحتاجون كتالوجاً يجلب طلبات عروض الأسعار.','ابدأ بباقة التجارة'],
       ['المؤسسات','$25,000+','حسب المراحل، USDT','لشركات النفط والغاز والصناعة والمجموعات: موقع وتطبيق ووكلاء ذكاء اصطناعي وسيو ودعم على مدار الساعة.','احجز إحاطة']],
    cmp:'قارن كل ما تشمله الباقات',
    ut:['الدفع بعملة تيثر (USDT)',(c)=>`ادفع بعملة تيثر الرقمية ${c.in}، دون حاجة لتحويل بنكي.`,'لا حاجة لتحويل بنكي دولي أو بطاقة أجنبية','يصل الدفع خلال دقائق، فيبدأ العمل أسرع','السعر نفسه بالدولار في كل الدول','رسوم شبكة منخفضة عبر TRC-20، أو ERC-20 إن فضّلت'],
    locH:(c)=>`ما نجهزه لعملاء ${c.name}`, loc:['مدن السيو المحلي','الدفع','العملة','الدومين','اللغات','حماية البيانات'],
    svH:'الخدمات', sv:[['تصميم مواقع','مواقع شركات ثنائية اللغة وكتالوجات ومتاجر إلكترونية مبنية لتتصدر البحث.'],['خدمات السيو','إصلاحات تقنية وبحث كلمات مفتاحية ومحتوى وسيو محلي لخرائط Google.'],['دعم المواقع وصيانتها','تحديثات ونسخ احتياطي وأمان وتعديلات، شهرياً أو عند الحاجة.'],['تطوير ودعم وكلاء الذكاء الاصطناعي','وكلاء على واتساب والموقع بالعربية والإنجليزية مع متابعة بعد الإطلاق.'],['تصميم تطبيقات','تطبيقات iOS وAndroid للعملاء والوكلاء والفرق الميدانية.']],
    fqH:'الأسئلة', fqRemote:['هل نحتاج إلى لقاء حضوري؟','لا. كل شيء، من أول مكالمة حتى الإطلاق والدعم، يتم أونلاين. والدعم الحضوري متاح في باقتي التجارة والتصدير والمؤسسات إن رغبتم.'],
    fqPay:['كيف ندفع؟','بعملة USDT (تيثر) عبر TRC-20 أو ERC-20. المحلية والتجارة: نصف عند البدء ونصف عند الإطلاق. المؤسسات: حسب المراحل المعتمدة.'],
    otherH:'دول أخرى', readH:'اقرأ أيضاً', ctH:'أخبرنا عن مشروعك', ctSub:'نرد خلال يوم عمل واحد بنطاق وجدول زمني وسعر ثابت.',
    msg:(c)=>`مرحباً MEFTACARD Studio، لدي شركة ${c.in} وأود مناقشة مشروع.`,
    msgP:(c,p)=>`مرحباً MEFTACARD Studio، لدي شركة ${c.in} وأود البدء بباقة ${p}.`,
    copy:'© 2026 MEFTACARD Studio' },
};

const esc = (s) => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const url = (lang, slug) => `${BASE}${lang === 'ar' ? '/ar' : ''}/studio/${slug}/`;
const WA = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm0 18.2a8.2 8.2 0 01-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1112 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 01-3.3-2.9c-.3-.4.3-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 00-.7.3 3 3 0 00-.9 2.2 5.2 5.2 0 001.1 2.7 11.9 11.9 0 004.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 001.8-1.2 2.2 2.2 0 00.2-1.2c-.1-.1-.3-.2-.5-.3z"/></svg>`;
const TG = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.9 4.3l-3.2 15.2c-.2 1.1-.9 1.3-1.8.8l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.4-5 9.1-8.2c.4-.4-.1-.6-.6-.2L6.2 13.1l-4.8-1.5c-1-.3-1.1-1 .2-1.5L20.5 2.9c.9-.3 1.6.2 1.4 1.4z"/></svg>`;
const tg = (t) => `https://t.me/meftacard?text=${encodeURIComponent(t)}`;

function page(c, lang) {
  const t = T[lang], d = c[lang], other = lang === 'ar' ? 'en' : 'ar';
  const self = url(lang, c.slug);
  const faqs = [...d.faq, t.fqRemote, t.fqPay];
  const ld = [
    { '@context':'https://schema.org', '@type':'ProfessionalService', '@id':self+'#service', name:'MEFTACARD Studio', url:self, inLanguage:lang,
      image:OG, description:t.desc(d), areaServed:{ '@type':'Country', name:c.en.name.replace(/^the /,'') },
      serviceType:['Website design','SEO services','Website support and maintenance','AI agent development and support','Mobile app design'],
      paymentAccepted:'USDT (Tether)', sameAs:['https://t.me/meftacard'], parentOrganization:{ '@type':'Organization', name:'MEFTACARD', url:BASE } },
    { '@context':'https://schema.org', '@type':'BreadcrumbList', itemListElement:[
      { '@type':'ListItem', position:1, name:'MEFTACARD Studio', item:BASE+t.studio },
      { '@type':'ListItem', position:2, name:d.name.replace(/^the /,''), item:self } ] },
    { '@context':'https://schema.org', '@type':'FAQPage', inLanguage:lang, mainEntity:faqs.map(([q,a])=>({ '@type':'Question', name:q, acceptedAnswer:{ '@type':'Answer', text:a } })) },
  ];
  const facts = [d.cities, d.pay, d.cur, d.domain, d.lang, d.law];
  return `<!doctype html>
<html lang="${lang}" dir="${t.dir}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(t.title(d))}</title>
<meta name="description" content="${esc(t.desc(d))}">
<link rel="canonical" href="${self}">
<link rel="alternate" hreflang="${lang}-${c.code}" href="${self}">
<link rel="alternate" hreflang="${other}-${c.code}" href="${url(other, c.slug)}">
<link rel="alternate" hreflang="x-default" href="${url('en', c.slug)}">
<meta property="og:type" content="website"><meta property="og:title" content="${esc(t.title(d))}"><meta property="og:description" content="${esc(t.desc(d))}">
<meta property="og:url" content="${self}"><meta property="og:image" content="${OG}"><meta property="og:locale" content="${lang === 'ar' ? 'ar_AR' : 'en_US'}">
<meta name="twitter:card" content="summary_large_image"><meta name="robots" content="index,follow,max-image-preview:large"><meta name="theme-color" content="#FAFAF8">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%2316171A'/%3E%3Ctext x='32' y='44' font-family='Arial' font-weight='800' font-size='34' text-anchor='middle' fill='%23FAFAF8'%3EM%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="${t.font}" rel="stylesheet">
${ld.map((j)=>`<script type="application/ld+json">${JSON.stringify(j)}</script>`).join('\n')}
<style>${CSS}
.crumb{font-size:14px;color:var(--ink-2);margin-bottom:22px}.crumb a{color:var(--ink-2)}.crumb a:hover{color:var(--ink)}
.online{display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-top:1px solid var(--ink);margin-top:32px}
.online li{list-style:none;padding:20px 24px 24px 0;border-bottom:1px solid var(--line);color:var(--ink-2);font-size:15.5px;counter-increment:o}
.online li::before{content:"0" counter(o);display:block;font-size:13px;color:var(--accent);margin-bottom:6px;font-variant-numeric:tabular-nums}
.online{counter-reset:o}
.facts-tbl{margin-top:28px;background:var(--paper);border:1px solid var(--line);border-radius:20px;overflow:hidden}
.facts-tbl table{min-width:0}
.svc5{display:grid;grid-template-columns:repeat(5,1fr);gap:20px;margin-top:28px}.svc5 h3{font-size:17px;margin-bottom:6px;letter-spacing:-.01em}.svc5 p{font-size:14.5px;color:var(--ink-2)}
.links2{display:grid;grid-template-columns:1fr 1fr;gap:56px}.links2 a{display:block;padding:10px 0;border-bottom:1px solid var(--line);font-size:15.5px}.links2 a:hover{color:var(--accent)}.links2 h3{font-size:15px;color:var(--ink-2);font-weight:500;margin-bottom:8px;letter-spacing:0}
@media (max-width:900px){.online,.svc5,.links2{grid-template-columns:1fr}.svc5{gap:16px}.links2{gap:32px}}
</style>
</head>
<body>
<a class="skip" href="#main">${t.skip}</a>
<header class="top"><div class="wrap">
  <a class="brand" href="${t.studio}">MEFTACARD <span>Studio</span></a>
  <nav class="links"><a href="#packages">${t.nav[0]}</a><a href="#services">${t.nav[1]}</a><a href="#contact">${t.nav[2]}</a></nav>
  <nav class="lang"><a href="${t.studio}${c.slug}/" aria-current="page">${lang === 'ar' ? 'عربي' : 'EN'}</a><a href="${url(other, c.slug).replace(BASE,'')}" hreflang="${other}" lang="${other}">${t.other}</a></nav>
  <a class="btn btn-dark" href="#contact">${t.nav[2]}</a>
</div></header>
<main id="main">
<section class="hero"><div class="wrap">
  <p class="crumb"><a href="${t.studio}">MEFTACARD Studio</a> / ${esc(d.name.replace(/^the /,''))}</p>
  <h1>${esc(t.h1(d))}</h1>
  <p class="sub">${esc(t.lead(d))}</p>
  <div class="actions"><a class="btn btn-dark" href="#packages">${t.pkH}</a><a class="btn btn-line" href="${tg(t.msg(d))}" target="_blank" rel="noopener"><bdi dir="ltr">Telegram @meftacard</bdi></a></div>
  <h2 style="font-size:clamp(22px,2.2vw,28px);margin-top:56px">${t.onH}</h2>
  <ol class="online">${t.on.map((x)=>`<li>${esc(x)}</li>`).join('')}</ol>
</div></section>

<section id="packages" style="padding-top:8px"><div class="wrap">
  <h2 style="font-size:clamp(26px,2.6vw,34px)">${t.pkH}</h2><p class="sub" style="margin-top:8px;font-size:17px">${t.pkSub}</p>
  <div class="cards" style="margin-top:24px">${t.p.map((p,i)=>`
    <article class="card${i===2?' big':''}"><h3>${esc(p[0])}</h3><div><div class="price num">${p[1]}</div><div class="unit">${esc(p[2])}</div></div><p class="who">${esc(p[3])}</p>
    <a class="btn ${i===0?'btn-line':'btn-dark'}" href="${tg(t.msgP(d,p[0]))}" target="_blank" rel="noopener">${esc(p[4])}</a></article>`).join('')}
  </div>
  <div class="usdt"><div class="usdt-h"><span class="usdt-mark num">₮</span><div><h3>${t.ut[0]}</h3><p>${esc(t.ut[1](d))}</p></div></div><ul>${t.ut.slice(2).map((x)=>`<li>${esc(x)}</li>`).join('')}</ul></div>
  <p class="pk-note"><a class="more" href="${t.studio}#packages">${t.cmp} →</a></p>
</div></section>

<section style="padding-top:24px"><div class="wrap">
  <h2 style="font-size:clamp(26px,2.6vw,34px)">${esc(t.locH(d))}</h2>
  <p class="sub">${esc(d.local)}</p>
  <div class="browser" style="margin-top:28px"><div class="bar"><i></i><i></i><i></i></div><img src="${MEDIA[CIMG[c.slug]]}" width="1440" height="900" loading="lazy" alt="${esc(t.h1(d))} — MEFTACARD Studio"></div>
  <div class="facts-tbl"><div class="tbl" style="border-top:0"><table><tbody>
    ${t.loc.map((k,i)=>`<tr><th>${esc(k)}</th><td>${esc(facts[i]).replace(/(\.[a-z]{2,3}\b)/g,'<bdi dir="ltr">$1</bdi>')}</td></tr>`).join('')}
  </tbody></table></div></div>
</div></section>

<section id="services" style="padding-top:24px"><div class="wrap">
  <h2 style="font-size:clamp(26px,2.6vw,34px)">${t.svH}</h2>
  <div class="svc5">${t.sv.map(([h,p],i)=>`<div><h3><a href="${t.studio}${['website-design','seo-services','website-support','ai-agents','app-design'][i]}/" style="border-bottom:1px solid var(--line-2)">${esc(h)}</a></h3><p>${esc(p)}</p></div>`).join('')}</div>
</div></section>

<section id="faq" style="padding-top:24px"><div class="wrap qa">
  <h2>${t.fqH}</h2>
  <div class="faq">${faqs.map(([q,a])=>`<details><summary>${esc(q)}</summary><p>${esc(a)}</p></details>`).join('')}</div>
</div></section>

<section style="padding-top:24px"><div class="wrap links2">
  <div><h3>${t.otherH}</h3>${C.filter((x)=>x.slug!==c.slug).map((x)=>`<a href="${t.studio}${x.slug}/">${esc(t.h1(x[lang]))}</a>`).join('')}</div>
  <div><h3>${t.readH}</h3>${ART[lang].map(([s,l])=>`<a href="${BASE}/${s}/">${esc(l)}</a>`).join('')}</div>
</div></section>

<section id="contact"><div class="wrap ct">
  <div><h2>${t.ctH}</h2><p class="sub">${t.ctSub}</p></div>
  <div class="direct" style="margin-top:0">
    <a href="${tg(t.msg(d))}" target="_blank" rel="noopener"><span>Telegram</span><small dir="ltr">@meftacard</small></a>
    <a class="js-wa" href="#"><span>WhatsApp</span><small>${lang === 'ar' ? 'يفتح محادثة' : 'Opens a chat'}</small></a>
  </div>
</div></section>
</main>
<footer><div class="wrap"><span>${t.copy}</span><nav class="flinks"><a href="${t.studio}">MEFTACARD Studio</a><a href="${BASE}/contact/">${lang === 'ar' ? 'اتصل بنا' : 'Contact'}</a><a href="${BASE}/privacy-policy/">${lang === 'ar' ? 'الخصوصية' : 'Privacy'}</a></nav></div></footer>
<div class="floats">
  <a class="float float-wa js-wa" href="#" aria-label="WhatsApp">${WA}</a>
  <a class="float float-tg" href="${tg(t.msg(d))}" target="_blank" rel="noopener" aria-label="Telegram @meftacard">${TG}</a>
</div>
<script>
const WA_PARTS=["98","919","129","9559"];const WA_MSG=${JSON.stringify(t.msg(d))};
document.querySelectorAll('.js-wa').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();window.open('https://wa.me/'+WA_PARTS.join('')+'?text='+encodeURIComponent(WA_MSG),'_blank','noopener');}));
</script>
</body>
</html>`;
}

for (const c of C) for (const lang of ['en', 'ar']) {
  const f = path.join(ROOT, 'dist', lang === 'ar' ? 'ar' : '', 'studio', c.slug, 'index.html');
  fs.mkdirSync(path.dirname(f), { recursive: true });
  fs.writeFileSync(f, page(c, lang));
}
fs.writeFileSync(path.join(ROOT, 'dist', 'countries.json'), JSON.stringify(C.map((c)=>({ slug:c.slug, en:T.en.h1(c.en), ar:T.ar.h1(c.ar) })), null, 1));
console.log('wrote', C.length * 2, 'country pages');
