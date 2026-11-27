(function () {
  const CFG = window.MEFTACARD_CONFIG || {
    defaultTheme: "light",
    defaultCorridor: "US",
    otpMode: "demo",
    cardFee: 5.00,
    defaultTopup: 25.00,
    ajaxUrl: "",
    restBase: "",
    nonce: "",
    wallets: {
      TON: "",
      BEP20: "",
      TRC20: ""
    },
    simPrices: { openai: 1.40, claude: 1.60, telegram: 0.95, whatsapp: 1.25, google: 1.10, cursor: 1.50 },
    corridors: null
  };

  const DEFAULT_CORRIDORS = [
    { id: "US", name: "United States", prefix: "+1", cur: "USD", rate: 1.0, lang: "en" },
    { id: "GB", name: "United Kingdom", prefix: "+44", cur: "GBP", rate: 0.79, lang: "en" },
    { id: "DE", name: "Germany", prefix: "+49", cur: "EUR", rate: 0.92, lang: "de" },
    { id: "FR", name: "France", prefix: "+33", cur: "EUR", rate: 0.92, lang: "fr" },
    { id: "CA", name: "Canada", prefix: "+1", cur: "CAD", rate: 1.39, lang: "en" },
    { id: "NL", name: "Netherlands", prefix: "+31", cur: "EUR", rate: 0.92, lang: "en" },
    { id: "SG", name: "Singapore", prefix: "+65", cur: "SGD", rate: 1.34, lang: "en" },
    { id: "TR", name: "Turkey", prefix: "+90", cur: "TRY", rate: 34.5, lang: "tr" },
    { id: "AE", name: "UAE", prefix: "+971", cur: "AED", rate: 3.67, lang: "ar" },
    { id: "SA", name: "Saudi Arabia", prefix: "+966", cur: "SAR", rate: 3.75, lang: "ar" },
    { id: "IQ", name: "Iraq", prefix: "+964", cur: "IQD", rate: 1310, lang: "ar" },
    { id: "EG", name: "Egypt", prefix: "+20", cur: "EGP", rate: 49.2, lang: "ar" },
    { id: "IR", name: "Iran", prefix: "+98", cur: "IRT", rate: 69000, lang: "fa" }
  ];

  const CORRIDORS = Array.isArray(CFG.corridors) && CFG.corridors.length ? CFG.corridors : DEFAULT_CORRIDORS;
  const sp = CFG.simPrices || {};
  const SIM_SERVICES = {
    openai:   { name: "OpenAI / ChatGPT",      base: parseFloat(sp.openai)   || 1.40, rate: "98.6%", speed: "14s" },
    claude:   { name: "Claude AI (Anthropic)", base: parseFloat(sp.claude)   || 1.60, rate: "98.2%", speed: "16s" },
    telegram: { name: "Telegram",              base: parseFloat(sp.telegram) || 0.95, rate: "99.1%", speed: "9s"  },
    whatsapp: { name: "WhatsApp Business",     base: parseFloat(sp.whatsapp) || 1.25, rate: "97.8%", speed: "12s" },
    google:   { name: "Google / Gemini",       base: parseFloat(sp.google)   || 1.10, rate: "98.4%", speed: "11s" },
    cursor:   { name: "Cursor / AWS / GitHub", base: parseFloat(sp.cursor)   || 1.50, rate: "98.9%", speed: "13s" }
  };

  const I18N = {
    en: {
      dir: "ltr",
      navAI: "AI & APIs", navSIM: "Virtual SIMs", navCorridors: "13 Corridors", navArticles: "Articles", navAbout: "About Us",
      ctaTop: "Verify & Checkout",
      liveBadge: "13 Global Corridors · Native USDT Settlement · 98.6% OTP Delivery",
      heroTitle: "AI Subscriptions, Holographic Virtual Cards & Non-VoIP SIMs.",
      heroLead: "Buy ChatGPT Plus, Claude Pro, Gemini API keys, and crypto-funded Visa cards settled instantly in USDT across 13 localized corridors.",
      heroCta1: "Buy AI Access Now", heroCta2: "Issue Virtual Card",
      mCorridor: "Active Corridor", mPrefix: "Telecom Prefix", mRate: "1 USDT Live Rate",
      flipBtn: "Flip 180°",
      aiEyebrow: "Row 1 · Priority Digital Access",
      aiTitle: "AI Accounts & Pay-As-You-Go API Credits",
      aiSub: "Instant automated delivery via encrypted vault or live OpenRouter/Gemini API key provisioning.",
      tabAll: "All", tabSub: "Subscriptions", tabApi: "API Credits", tabDev: "Dev Tools",
      buyUsdt: "Buy with USDT",
      bentoEyebrow: "Global Carrier & Settlement Layer",
      bentoTitle: "Non-VoIP Virtual Numbers & Zero-Fee USDT Infrastructure",
      simTitle: "Instant Non-VoIP Virtual Numbers",
      simDesc: "Real carrier SIM verification for OpenAI, Claude, Telegram & WhatsApp with 100% automatic refund if no SMS arrives.",
      feeTitle: "Network Fee Comparison",
      feeDesc: "Top up your internal USDT balance once to buy numbers in 0.2s with zero gas fees.",
      corTitle: "13 Geopolitical Corridors (Auto-Localization)",
      corDesc: "Select any market below — live FX rates, dial prefix, and interface language adapt automatically.",
      artEyebrow: "Knowledge Hub · SEO & Technical Playbooks",
      artTitle: "Latest Articles & AI Payment Guides",
      artSub: "Step-by-step fixes for Stripe declines, low-fee BEP20 settlement, and Non-VoIP verification.",
      readGuide: "Read Article →",
      aboutEyebrow: "About Meftacard",
      aboutTitle: "Built for Borderless AI, Telecom & Crypto Access.",
      aboutP1: "Meftacard was engineered to eliminate traditional banking barriers for global developers, agencies, and everyday AI users. Instead of fighting declined local cards, high intermediary fees, or blocked VoIP numbers, we unify 3D Secure virtual cards, real carrier Non-VoIP SIMs, and hard-capped AI API keys on instant USDT rails.",
      aboutP2: "Every order is backed by transparent live pricing across 13 corridors, automated OxaPay USDT confirmation, and a strict 10-minute 100% automatic refund guarantee on SMS verifications.",
      aboutCta1: "Contact Support", aboutCta2: "Telegram Desk",
      stat1Lbl: "Localized Corridors", stat1Sub: "Real-time FX & telecom prefixes",
      stat2Lbl: "OTP Delivery Rate", stat2Sub: "Verified Non-VoIP real SIMs",
      stat3Lbl: "Ledger Speed", stat3Sub: "$0 gas fee on internal balance",
      stat4Lbl: "Auto-Refund SLA", stat4Sub: "Instant credit on 10m SMS timeout",
      trust1T: "0-Second Auto-Refund SLA", trust1D: "No SMS within 10 minutes? 100% credited back to your USDT ledger instantly.",
      trust2T: "3D Secure Virtual Cards", trust2D: "Stripe, OpenAI, Anthropic, Cursor & AWS compatible BIN ranges.",
      trust3T: "Non-Custodial Privacy", trust3D: "Automated OxaPay USDT settlement with BEP20 (BSC) support."
    },
    de: {
      dir: "ltr",
      navAI: "KI & APIs", navSIM: "Virtuelle SIMs", navCorridors: "13 Korridore", navArticles: "Artikel", navAbout: "Über uns",
      ctaTop: "Verifizieren & Kaufen",
      liveBadge: "13 Globale Korridore · USDT-Abwicklung · 98,6% OTP-Quote",
      heroTitle: "KI-Abos, holografische virtuelle Karten & Non-VoIP SIMs.",
      heroLead: "Kaufen Sie ChatGPT Plus, Claude Pro, Gemini API-Guthaben und krypto-finanzierte Visa-Karten sofort mit USDT in 13 Korridoren.",
      heroCta1: "KI-Zugang kaufen", heroCta2: "Virtuelle Karte ausstellen",
      mCorridor: "Aktiver Korridor", mPrefix: "Vorwahl", mRate: "1 USDT Live-Kurs",
      flipBtn: "180° drehen",
      aiEyebrow: "Reihe 1 · Priorisierter KI-Zugang",
      aiTitle: "KI-Konten & API-Guthaben sofort per USDT",
      aiSub: "Sofortige Auslieferung für ChatGPT Plus, Claude Pro, Cursor Pro und Gemini/OpenRouter API-Keys.",
      tabAll: "Alle", tabSub: "Abos", tabApi: "API-Guthaben", tabDev: "Entwickler",
      buyUsdt: "Mit USDT kaufen",
      bentoEyebrow: "Carrier- & Zahlungsinfrastruktur",
      bentoTitle: "Non-VoIP Nummern & Gebührenvergleich",
      simTitle: "Sofortige Non-VoIP Rufnummern",
      simDesc: "Echte Mobilfunknummern für OpenAI, Claude & Telegram mit automatischer Rückerstattung bei Timeout.",
      feeTitle: "Netzwerk-Gebührenvergleich",
      feeDesc: "Laden Sie Ihr internes Guthaben einmal auf und kaufen Sie Nummern mit 0$ Gas-Gebühren.",
      corTitle: "13 Geopolitische Korridore (Auto-Lokalisierung)",
      corDesc: "Wählen Sie ein Land — Live-Wechselkurs, Vorwahl und Sprache passen sich automatisch an.",
      artEyebrow: "Wissenszentrum · Leitfäden",
      artTitle: "Aktuelle Artikel & KI-Zahlungsguides",
      artSub: "Anleitungen für Stripe-Fehlerbehebung, günstige BEP20-Transfers und Non-VoIP-Verifizierung.",
      readGuide: "Artikel lesen →",
      aboutEyebrow: "Über Meftacard",
      aboutTitle: "Entwickelt für grenzenlosen KI- & Krypto-Zugang.",
      aboutP1: "Meftacard beseitigt klassische Bankhürden für Entwickler und KI-Nutzer weltweit. Wir vereinen 3D-Secure-Karten, echte Non-VoIP-SIM-Nummern und limitierte KI-API-Schlüssel auf schnellen USDT-Netzwerken.",
      aboutP2: "Jede Bestellung profitiert von Live-Wechselkursen in 13 Korridoren, automatischer OxaPay-Bestätigung und 100% automatischer Rückerstattung bei SMS-Timeout.",
      aboutCta1: "Support kontaktieren", aboutCta2: "Telegram-Support",
      stat1Lbl: "Lokalisierte Korridore", stat1Sub: "Live-Kurse & Vorwahlen",
      stat2Lbl: "OTP-Erfolgsquote", stat2Sub: "Echte Non-VoIP SIM-Karten",
      stat3Lbl: "Ledger-Tempo", stat3Sub: "0$ Gas-Gebühr im Guthaben",
      stat4Lbl: "Auto-Refund SLA", stat4Sub: "Sofort-Gutschrift nach 10 Min.",
      trust1T: "Automatische Rückerstattung", trust1D: "Kein SMS-Code in 10 Min.? 100% sofort zurück auf Ihr USDT-Guthaben.",
      trust2T: "3D Secure Virtuelle Karten", trust2D: "Kompatibel mit Stripe, OpenAI, Anthropic, Cursor & AWS.",
      trust3T: "Schnelle Krypto-Abwicklung", trust3D: "Automatisierte OxaPay USDT-Zahlung über BEP20 (BSC)."
    },
    fr: {
      dir: "ltr",
      navAI: "IA & APIs", navSIM: "SIM Virtuelles", navCorridors: "13 Corridors", navArticles: "Articles", navAbout: "À propos",
      ctaTop: "Vérifier & Commander",
      liveBadge: "13 Corridors Mondiaux · Règlement USDT · 98,6% OTP",
      heroTitle: "Abonnements IA, Cartes Virtuelles Holographiques & SIM Non-VoIP.",
      heroLead: "Achetez ChatGPT Plus, Claude Pro, clés API Gemini et cartes Visa financées en USDT dans 13 corridors localisés.",
      heroCta1: "Accès IA instantané", heroCta2: "Émettre une carte",
      mCorridor: "Corridor actif", mPrefix: "Indicatif", mRate: "Taux 1 USDT",
      flipBtn: "Retourner 180°",
      aiEyebrow: "Rangée 1 · Accès IA Prioritaire",
      aiTitle: "Comptes IA & Crédits API Pay-As-You-Go",
      aiSub: "Livraison automatisée instantanée par coffre chiffré ou clé API OpenRouter/Gemini.",
      tabAll: "Tout", tabSub: "Abonnements", tabApi: "Crédits API", tabDev: "Outils Dev",
      buyUsdt: "Acheter en USDT",
      bentoEyebrow: "Réseau Opérateur & Paiement",
      bentoTitle: "Numéros Non-VoIP & Frais USDT",
      simTitle: "Numéros Virtuels Non-VoIP Instantanés",
      simDesc: "Vraies cartes SIM pour OpenAI, Claude & Telegram avec remboursement automatique à 100%.",
      feeTitle: "Comparateur de frais réseau",
      feeDesc: "Rechargez votre solde interne une fois pour acheter vos numéros sans frais de gaz.",
      corTitle: "13 Corridors Géopolitiques (Auto-Traduction)",
      corDesc: "Cliquez sur un pays pour adapter automatiquement la devise, l'indicatif et la langue.",
      artEyebrow: "Centre de Ressources · Guides",
      artTitle: "Derniers Articles & Guides IA en USDT",
      artSub: "Solutions pour les refus Stripe, paiements BEP20 à frais réduits et vérification Non-VoIP.",
      readGuide: "Lire l'article →",
      aboutEyebrow: "À propos de Meftacard",
      aboutTitle: "Conçu pour un accès IA et crypto sans frontières.",
      aboutP1: "Meftacard supprime les blocages bancaires traditionnels pour les développeurs et utilisateurs d'IA. Nous réunissons cartes virtuelles 3D Secure, vraies lignes Non-VoIP et clés API plafonnées en USDT.",
      aboutP2: "Chaque commande bénéficie de taux en direct sur 13 corridors, d'une confirmation OxaPay automatisée et d'un remboursement automatique à 100%.",
      aboutCta1: "Contacter le support", aboutCta2: "Support Telegram",
      stat1Lbl: "Corridors localisés", stat1Sub: "Devises & indicatifs en direct",
      stat2Lbl: "Taux de succès OTP", stat2Sub: "Vraies cartes SIM Non-VoIP",
      stat3Lbl: "Vitesse interne", stat3Sub: "0$ de frais de gaz",
      stat4Lbl: "Remboursement Auto", stat4Sub: "Crédit instantané après 10 min",
      trust1T: "Remboursement Auto Instantané", trust1D: "Aucun SMS reçu sous 10 min ? 100% recrédité immédiatement.",
      trust2T: "Cartes Virtuelles 3D Secure", trust2D: "BIN compatibles Stripe, OpenAI, Anthropic, Cursor & AWS.",
      trust3T: "Confidentialité & Rapidité", trust3D: "Règlement OxaPay USDT automatisé sur BEP20 (BSC)."
    },
    tr: {
      dir: "ltr",
      navAI: "Yapay Zeka", navSIM: "Sanal SIM", navCorridors: "13 Koridor", navArticles: "Makaleler", navAbout: "Hakkımızda",
      ctaTop: "Doğrula ve Öde",
      liveBadge: "13 Küresel Koridor · Anında USDT · %98,6 OTP Başarısı",
      heroTitle: "Yapay Zeka Hesapları, Holografik Sanal Kartlar & Non-VoIP SIM.",
      heroLead: "ChatGPT Plus, Claude Pro, Gemini API kredileri ve kripto destekli Visa kartlarını 13 koridorda USDT ile anında satın alın.",
      heroCta1: "AI Erişimi Al", heroCta2: "Sanal Kart Oluştur",
      mCorridor: "Aktif Koridor", mPrefix: "Ülke Kodu", mRate: "1 USDT Kuru",
      flipBtn: "180° Çevir",
      aiEyebrow: "1. Sıra · Öncelikli AI Erişimi",
      aiTitle: "Yapay Zeka Abonelikleri ve API Kredileri",
      aiSub: "Şifreli kasa veya anında OpenRouter/Gemini API anahtarı ile otomatik teslimat.",
      tabAll: "Tümü", tabSub: "Abonelikler", tabApi: "API Kredisi", tabDev: "Geliştirici",
      buyUsdt: "USDT ile Satın Al",
      bentoEyebrow: "Operatör ve Ödeme Altyapısı",
      bentoTitle: "Non-VoIP Sanal Numaralar & Ağ Ücretleri",
      simTitle: "Anında Non-VoIP Sanal Numaralar",
      simDesc: "OpenAI, Claude ve Telegram doğrulamaları için gerçek operatör hatları ve otomatik iade garantisi.",
      feeTitle: "Ağ Ücreti Karşılaştırması",
      feeDesc: "Dahili USDT bakiyenizi bir kez yükleyin, sıfır gaz ücretiyle anında numara alın.",
      corTitle: "13 Jeopolitik Koridor (Otomatik Dil & Kur)",
      corDesc: "Herhangi bir ülkeyi seçin; canlı yerel kur, telefon kodu ve dil otomatik güncellenir.",
      artEyebrow: "Bilgi Merkezi · Rehberler",
      artTitle: "Son Makaleler ve AI Ödeme Rehberleri",
      artSub: "Stripe kart reddi çözümleri, düşük ücretli BEP20 transferleri ve Non-VoIP doğrulama.",
      readGuide: "Makaleyi Oku →",
      aboutEyebrow: "Meftacard Hakkında",
      aboutTitle: "Sınırsız Yapay Zeka ve Kripto Erişimi İçin Tasarlandı.",
      aboutP1: "Meftacard, geliştiriciler ve global AI kullanıcıları için bankacılık engellerini ortadan kaldırır. 3D Secure sanal kartlar, gerçek Non-VoIP SIM hatları ve limitli API anahtarlarını tek bir USDT platformunda birleştiriyoruz.",
      aboutP2: "13 koridorda canlı döviz kuru, otomatik OxaPay onayı ve 10 dakikada SMS gelmezse %100 otomatik iade garantisi sunuyoruz.",
      aboutCta1: "Destek ile İletişim", aboutCta2: "Telegram Destek",
      stat1Lbl: "Yerel Koridor", stat1Sub: "Canlı döviz ve telefon kodu",
      stat2Lbl: "OTP Başarı Oranı", stat2Sub: "Gerçek Non-VoIP operatörler",
      stat3Lbl: "Bakiye Hızı", stat3Sub: "Dahili bakiyede 0$ gaz ücreti",
      stat4Lbl: "Otomatik İade", stat4Sub: "10 dk SMS zaman aşımında anında",
      trust1T: "Sıfır Saniye Otomatik İade", trust1D: "10 dakikada SMS gelmezse %100 anında bakiyenize iade edilir.",
      trust2T: "3D Secure Sanal Kartlar", trust2D: "Stripe, OpenAI, Claude, Cursor ve AWS faturalandırmasına uyumlu.",
      trust3T: "Güvenli Kripto Ödeme", trust3D: "OxaPay destekli BEP20 (BSC) otomatik USDT tahsilatı."
    },
    ar: {
      dir: "rtl",
      navAI: "الذكاء الاصطناعي", navSIM: "أرقام Non-VoIP", navCorridors: "13 ممراً", navArticles: "المقالات", navAbout: "من نحن",
      ctaTop: "تحقق وابدأ",
      liveBadge: "13 ممراً عالمياً · تسوية فورية عبر USDT · نسبة وصول 98.6%",
      heroTitle: "اشتراكات الذكاء الاصطناعي، بطاقات افتراضية ثلاثية الأبعاد وأرقام Non-VoIP.",
      heroLead: "اشترِ ChatGPT Plus وClaude Pro ومفاتيح Gemini API وبطاقات فيزا مشحونة بالكريبتو فوراً عبر USDT في 13 دولة.",
      heroCta1: "شراء حسابات AI الآن", heroCta2: "إصدار بطاقة افتراضية",
      mCorridor: "الممر النشط", mPrefix: "رمز الاتصال", mRate: "سعر 1 USDT المباشر",
      flipBtn: "اقلب البطاقة 180°",
      aiEyebrow: "الصف الأول · أولوية الذكاء الاصطناعي",
      aiTitle: "اشتراكات الذكاء الاصطناعي ورصيد API الفوري",
      aiSub: "تسليم آلي فوري عبر خزنة مشفرة أو توليد مباشر لمفاتيح Gemini وOpenRouter عبر USDT.",
      tabAll: "الكل", tabSub: "الاشتراكات", tabApi: "رصيد API", tabDev: "أدوات المطورين",
      buyUsdt: "شراء عبر USDT",
      bentoEyebrow: "البنية التحتية للاتصالات والدفع",
      bentoTitle: "أرقام Non-VoIP حقيقية ومقارنة رسوم الشبكات",
      simTitle: "أرقام هاتف حقيقية Non-VoIP للتفعيل الفوري",
      simDesc: "خطوط شرائح حقيقية لتفعيل OpenAI وClaude وتلغرام مع استرداد تلقائي 100% في حال عدم وصول الرمز.",
      feeTitle: "مقارنة رسوم شبكات التحويل",
      feeDesc: "اشحن رصيدك الداخلي مرة واحدة واشترِ الأرقام خلال 0.2 ثانية بدون رسوم شبكة.",
      corTitle: "13 ممراً دولياً (أسعار محدثة تلقائياً)",
      corDesc: "اختر أي دولة لتتحول اللغة وأسعار الصرف الحية ورمز الاتصال تلقائياً.",
      artEyebrow: "مركز المعرفة · أدلة تقنية",
      artTitle: "أحدث المقالات وأدلة الدفع بالكريبتو",
      artSub: "حلول رفض البطاقات على Stripe، وأرخص شبكات USDT، وتفعيل أرقام Non-VoIP الحقيقية.",
      readGuide: "اقرأ المقال ←",
      aboutEyebrow: "عن منصة Meftacard",
      aboutTitle: "بنية رقمية مصممة للوصول للذكاء الاصطناعي بلا حدود.",
      aboutP1: "أُنشئت منصة Meftacard لإزالة العوائق البنكية التقليدية أمام المطورين ومستخدمي الذكاء الاصطناعي في الشرق الأوسط والعالم. نجمع بين البطاقات الافتراضية 3D Secure وأرقام الشرائح الحقيقية Non-VoIP ومفاتيح API المحددة السقف عبر بوابة OxaPay الفورية.",
      aboutP2: "كل طلب محمي بأسعار صرف محدثة لحظياً في 13 دولة، وتأكيد تلقائي على البلوكشين، وضمان استرداد فوري 100% خلال 10 دقائق.",
      aboutCta1: "تواصل مع الدعم", aboutCta2: "دعم تلغرام",
      stat1Lbl: "ممراً دولياً", stat1Sub: "أسعار صرف حية ورموز اتصال",
      stat2Lbl: "نسبة وصول OTP", stat2Sub: "شرائح Non-VoIP حقيقية",
      stat3Lbl: "سرعة الرصيد الداخلي", stat3Sub: "0$ رسوم شبكة عند الشراء",
      stat4Lbl: "استرداد تلقائي", stat4Sub: "فوري عند انتهاء مهلة 10 دقائق",
      trust1T: "استرداد تلقائي فوري", trust1D: "لم يصلك رمز SMS خلال 10 دقائق؟ يُعاد المبلغ 100% لرصيدك فوراً.",
      trust2T: "بطاقات 3D Secure افتراضية", trust2D: "متوافقة مع Stripe وOpenAI وClaude وCursor وAWS.",
      trust3T: "بوابة OxaPay المؤتمتة", trust3D: "تأكيد تلقائي عبر البلوكشين لشبكات BEP20 (BSC)."
    },
    fa: {
      dir: "rtl",
      navAI: "اکانت و API هوش مصنوعی", navSIM: "سیم‌کارت مجازی", navCorridors: "۱۳ کریدور", navArticles: "مقالات", navAbout: "درباره ما",
      ctaTop: "احراز و خرید آنی",
      liveBadge: "۱۳ کریدور بین‌المللی · درگاه خودکار تتر · نرخ تحویل ۹۸.۶٪",
      heroTitle: "اکانت‌های هوش مصنوعی، کارت مجازی سه‌بعدی و سیم‌کارت Non-VoIP.",
      heroLead: "خرید فوری ChatGPT Plus، Claude Pro، کلید API جمنای و کارت ویزای شارژشده با تتر در ۱۳ کریدور بین‌المللی با نرخ آنلاین و تحویل خودکار.",
      heroCta1: "خرید اکانت هوش مصنوعی", heroCta2: "صدور کارت مجازی",
      mCorridor: "کریدور فعال", mPrefix: "پیش‌شماره", mRate: "نرخ آنلاین ۱ تتر",
      flipBtn: "چرخش ۱۸۰ درجه",
      aiEyebrow: "ردیف اول · دسترسی مستقیم به هوش مصنوعی",
      aiTitle: "فروش اکانت‌های هوش مصنوعی و اعتبار API",
      aiSub: "تحویل آنی از مخزن رمزنگاری‌شده یا صدور خودکار کلید API با سقف اعتبار دلخواه و تسویه تتر.",
      tabAll: "همه", tabSub: "اشتراک‌ها", tabApi: "اعتبار API", tabDev: "ابزار برنامه‌نویسی",
      buyUsdt: "خرید آنی با تتر",
      bentoEyebrow: "زیرساخت مخابراتی و پرداخت کریپتو",
      bentoTitle: "شماره مجازی واقعی Non-VoIP و مقایسه کارمزد شبکه‌ها",
      simTitle: "انتخاب‌گر زنده شماره مجازی Non-VoIP",
      simDesc: "خطوط سیم‌کارت واقعی برای وریفای OpenAI، کلود و تلگرام با تضمین ریفاند خودکار ۱۰۰٪ در صورت عدم دریافت کد.",
      feeTitle: "مقایسه کارمزد شبکه‌های تتر",
      feeDesc: "کیف پول داخلی را یک‌بار شارژ کنید و شماره‌ها را در ۰.۲ ثانیه با صفر کارمزد شبکه بخرید.",
      corTitle: "ماتریس ۱۳ کریدور بین‌المللی (نرخ آنلاین هر ۳۰ دقیقه)",
      corDesc: "با انتخاب هر کشور، زبان صفحه، پیش‌شماره و نرخ آنلاین تبدیل ارز محلی به صورت خودکار تنظیم می‌شود.",
      artEyebrow: "مرکز دانش · راهنماهای تخصصی",
      artTitle: "آخرین مقالات و آموزش‌های خرید با تتر",
      artSub: "راهکارهای تخصصی رفع خطای کارت Stripe در کلود و کرسر، خرید کلید API جمنای و انتخاب کم‌کارمزدترین شبکه تتر.",
      readGuide: "مطالعه مقاله ←",
      aboutEyebrow: "درباره مفتاکارت (Meftacard)",
      aboutTitle: "زیرساخت دسترسی بدون مرز به هوش مصنوعی، کارت مجازی و سیم‌کارت واقعی.",
      aboutP1: "مفتاکارت برای حذف موانع بانکی سنتی پیش پای برنامه‌نویسان، آژانس‌های دیجیتال و کاربران هوش مصنوعی طراحی شده است. به جای درگیری با رد شدن کارت‌های واسط، کارمزدهای پنهان یا مسدود شدن شماره‌های VoIP، ما کارت‌های مجازی 3D Secure، خطوط سیم‌کارت واقعی Non-VoIP و کلیدهای API با سقف دلاری سخت را روی درگاه خودکار تتر OxaPay یکپارچه کرده‌ایم.",
      aboutP2: "تمامی سفارش‌ها با نرخ ارز آنلاین در ۱۳ کریدور، تایید خودکار تراکنش روی بلاکچین و تضمین ریفاند خودکار ۱۰۰ درصدی در صورت عدم دریافت پیامک ظرف ۱۰ دقیقه پشتیبانی می‌شوند.",
      aboutCta1: "تماس با پشتیبانی", aboutCta2: "پشتیبانی تلگرام",
      stat1Lbl: "کریدور بین‌المللی", stat1Sub: "نرخ آنلاین ارز و پیش‌شماره بومی",
      stat2Lbl: "نرخ تحویل OTP", stat2Sub: "شماره‌های واقعی Non-VoIP",
      stat3Lbl: "سرعت کیف پول داخلی", stat3Sub: "صفر دلار کارمزد شبکه",
      stat4Lbl: "تضمین ریفاند خودکار", stat4Sub: "برگشت آنی در صورت نرسیدن کد",
      trust1T: "ریفاند خودکار ۰ ثانیه‌ای", trust1D: "کد پیامک ظرف ۱۰ دقیقه نرسید؟ ۱۰۰٪ مبلغ آنی به کیف پول داخلی برمی‌گردد.",
      trust2T: "کارت مجازی 3D Secure", trust2D: "سازگار با پرداخت Stripe، OpenAI، Claude، Cursor و AWS.",
      trust3T: "درگاه خودکار OxaPay", trust3D: "تایید لحظه‌ای روی شبکه BEP20 (BSC) با واریز مستقیم."
    }
  };

  function normalizeDigits(str) {
    return String(str || "")
      .replace(/[۰-۹]/g, d => String("۰۱۲۳۴۵۶۷۸۹".indexOf(d)))
      .replace(/[٠-٩]/g, d => String("٠١٢٣٤٥٦٧٨٩".indexOf(d)));
  }

  let activeCorridor = CORRIDORS.find(c => c.id === CFG.defaultCorridor) || CORRIDORS[0];
  let activeLang = activeCorridor.lang || "en";
  let selectedCardLoad = parseFloat(document.getElementById("cardLoadSelect").value) || 50;
  let activeCheckout = { product: "Order", amount: 22.00 };
  // Only networks listed here are offered to customers. Currently: USDT on BEP20 (BSC) only.
  const ENABLED_NETS = (Array.isArray(CFG.enabledNetworks) && CFG.enabledNetworks.length) ? CFG.enabledNetworks : ["BEP20"];
  let activePayNet = ENABLED_NETS[0];

  (function restrictNetworks() {
    document.querySelectorAll(".pay-net-pill").forEach(p => {
      if (!ENABLED_NETS.includes(p.dataset.net)) p.remove();
      else p.classList.toggle("active", p.dataset.net === activePayNet);
    });
    const lbl = document.getElementById("payNetLabel");
    if (lbl) lbl.textContent = `USDT (${activePayNet})`;
    const sel = document.getElementById("cardNetSelect");
    if (sel) {
      Array.from(sel.options).forEach(o => { if (!ENABLED_NETS.includes(o.value.split(" ")[0])) o.remove(); });
      const tag = document.getElementById("cardNetTag");
      if (tag && sel.options.length) tag.textContent = sel.options[0].value;
    }
    document.querySelectorAll(".fee-item").forEach(item => {
      const name = (item.querySelector(".fee-name") || {}).textContent || "";
      const m = name.match(/USDT\s*·\s*(TON|BEP20|TRC20)/);
      if (m && !ENABLED_NETS.includes(m[1])) item.remove();
    });
  })();
  let activeOrder = { order_id: "", track_id: "", expired_at: 0 };
  let timerInterval = null;
  let pollInterval = null;
  let localDemoCode = "";

  const corridorSelect = document.getElementById("corridorSelect");
  const langSelect = document.getElementById("langSelect");
  const themeToggle = document.getElementById("themeToggle");
  const corridorGrid = document.getElementById("corridorGrid");

  function setTheme(theme) {
    document.documentElement.setAttribute("data-theme", theme);
    themeToggle.textContent = theme === "dark" ? "☀ Light" : "☾ Slate";
  }
  setTheme(CFG.defaultTheme || "light");
  themeToggle.addEventListener("click", () => {
    const next = document.documentElement.getAttribute("data-theme") === "dark" ? "light" : "dark";
    setTheme(next);
  });

  function fmtLocal(usdt) {
    const val = usdt * activeCorridor.rate;
    const str = val >= 100 ? Math.round(val).toLocaleString("en-US") : val.toFixed(2);
    return `≈ ${str} ${activeCorridor.cur}`;
  }

  function refreshPrices() {
    document.querySelectorAll("[data-usdt-price]").forEach(el => {
      const amt = parseFloat(el.getAttribute("data-usdt-price"));
      const localEl = el.parentElement.querySelector("[data-local-price]");
      if (localEl) localEl.textContent = fmtLocal(amt);
    });
    updateSimCalc();
  }

  function applyLanguage(lang) {
    activeLang = I18N[lang] ? lang : "en";
    const pack = I18N[activeLang];
    langSelect.value = activeLang;
    document.documentElement.lang = activeLang;
    document.documentElement.dir = pack.dir;
    document.querySelectorAll("[data-i18n]").forEach(el => {
      const k = el.getAttribute("data-i18n");
      if (pack[k]) el.textContent = pack[k];
    });
  }

  function setCorridor(id, autoTranslate = true) {
    activeCorridor = CORRIDORS.find(c => c.id === id) || CORRIDORS[0];
    corridorSelect.value = activeCorridor.id;

    document.getElementById("statCorridor").textContent = activeCorridor.name;
    document.getElementById("statPrefix").textContent = activeCorridor.prefix;
    const rDisp = activeCorridor.rate >= 100
      ? Math.round(activeCorridor.rate).toLocaleString("en-US")
      : Number(activeCorridor.rate).toFixed(2);
    document.getElementById("statRate").textContent = `${rDisp} ${activeCorridor.cur}`;
    document.getElementById("cardCorridorTag").textContent = `${activeCorridor.id} · ${activeCorridor.prefix} · ${activeCorridor.cur}`;

    document.querySelectorAll(".corridor-btn").forEach(btn => {
      btn.classList.toggle("active", btn.dataset.id === activeCorridor.id);
    });

    if (autoTranslate) applyLanguage(activeCorridor.lang);
    refreshPrices();
  }

  CORRIDORS.forEach(c => {
    const opt = document.createElement("option");
    opt.value = c.id;
    opt.textContent = `${c.id} · ${c.name} (${c.cur})`;
    corridorSelect.appendChild(opt);

    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "corridor-btn";
    btn.dataset.id = c.id;
    btn.innerHTML = `<span class="corridor-code">${c.id} · ${c.name}</span><span class="corridor-sub">${c.prefix} · ${c.cur}</span>`;
    btn.addEventListener("click", () => setCorridor(c.id, true));
    corridorGrid.appendChild(btn);
  });

  corridorSelect.addEventListener("change", e => setCorridor(e.target.value, true));
  langSelect.addEventListener("change", e => applyLanguage(e.target.value));

  // 3D Holographic Card Physics + 180° Flip
  const tiltWrap = document.getElementById("cardTiltWrap");
  const flipper = document.getElementById("cardFlipper");
  const cardFront = document.getElementById("cardFront");
  const cardBack = document.getElementById("cardBack");
  const cvvBox = document.getElementById("cvvBox");

  function handleMove(clientX, clientY) {
    const rect = tiltWrap.getBoundingClientRect();
    const x = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
    const y = Math.max(0, Math.min(1, (clientY - rect.top) / rect.height));
    const rotY = (x - 0.5) * 24;
    const rotX = (0.5 - y) * 20;
    tiltWrap.style.transform = `rotateX(${rotX.toFixed(2)}deg) rotateY(${rotY.toFixed(2)}deg)`;
    const mx = `${(x * 100).toFixed(1)}%`;
    const my = `${(y * 100).toFixed(1)}%`;
    cardFront.style.setProperty("--mx", mx);
    cardFront.style.setProperty("--my", my);
    cardBack.style.setProperty("--mx", mx);
    cardBack.style.setProperty("--my", my);
  }

  tiltWrap.addEventListener("mousemove", e => handleMove(e.clientX, e.clientY));
  tiltWrap.addEventListener("touchmove", e => {
    if (e.touches && e.touches[0]) handleMove(e.touches[0].clientX, e.touches[0].clientY);
  }, { passive: true });
  tiltWrap.addEventListener("mouseleave", () => { tiltWrap.style.transform = "rotateX(0deg) rotateY(0deg)"; });

  document.getElementById("flipCardBtn").addEventListener("click", () => flipper.classList.toggle("is-flipped"));

  let cvvRevealed = false;
  cvvBox.addEventListener("click", () => {
    cvvRevealed = !cvvRevealed;
    cvvBox.textContent = cvvRevealed ? "CVV 849" : "CVV •••";
  });

  document.querySelectorAll(".swatch").forEach(sw => {
    sw.addEventListener("click", () => {
      document.querySelectorAll(".swatch").forEach(s => s.classList.remove("active"));
      sw.classList.add("active");
      cardFront.setAttribute("data-material", sw.dataset.mat);
      cardBack.setAttribute("data-material", sw.dataset.mat);
    });
  });

  document.getElementById("engraveInput").addEventListener("input", e => {
    const label = e.target.value.trim() ? e.target.value.trim().toUpperCase() : "MEFTA-FOUNDER";
    document.getElementById("cardHolder").textContent = label;
    document.getElementById("cardBackSig").textContent = label;
  });

  document.getElementById("cardLoadSelect").addEventListener("change", e => {
    selectedCardLoad = parseFloat(e.target.value);
    document.getElementById("cardLoadTag").textContent = `$${selectedCardLoad} LOAD`;
  });

  document.getElementById("cardNetSelect").addEventListener("change", e => {
    document.getElementById("cardNetTag").textContent = e.target.value;
  });

  // Row 1 AI Tabs
  document.querySelectorAll(".tab-btn").forEach(tab => {
    tab.addEventListener("click", () => {
      document.querySelectorAll(".tab-btn").forEach(t => t.classList.remove("active"));
      tab.classList.add("active");
      const filter = tab.dataset.filter;
      document.querySelectorAll(".ai-card").forEach(card => {
        card.classList.toggle("hidden", !(filter === "all" || card.dataset.cat === filter));
      });
    });
  });

  // API Credit Tier Selector
  document.querySelectorAll(".tier-pill[data-tier]").forEach(pill => {
    pill.addEventListener("click", () => {
      document.querySelectorAll(".tier-pill[data-tier]").forEach(p => p.classList.remove("active"));
      pill.classList.add("active");
      const tierVal = parseFloat(pill.dataset.tier);
      const priceEl = document.getElementById("apiPriceUsdt");
      priceEl.textContent = `$${tierVal.toFixed(2)} USDT`;
      priceEl.setAttribute("data-usdt-price", tierVal.toFixed(2));
      document.getElementById("apiBuyBtn").setAttribute("data-product", `Gemini & OpenRouter API ($${tierVal} Credit)`);
      document.getElementById("apiBuyBtn").setAttribute("data-amount", tierVal.toFixed(2));
      refreshPrices();
    });
  });

  // Non-VoIP SIM Calculator
  const simServiceEl = document.getElementById("simServiceSelect");
  const simCountryEl = document.getElementById("simCountrySelect");

  function updateSimCalc() {
    const svc = SIM_SERVICES[simServiceEl.value] || SIM_SERVICES.openai;
    const countryMult = parseFloat(simCountryEl.value) || 1.0;
    const finalPrice = +(svc.base * countryMult).toFixed(2);
    document.getElementById("simRateBadge").textContent = `${svc.rate} Success`;
    document.getElementById("simSpeedBadge").textContent = `Avg ${svc.speed}`;
    document.getElementById("simPriceUsdt").textContent = `$${finalPrice.toFixed(2)} USDT`;
    document.getElementById("simPriceLocal").textContent = fmtLocal(finalPrice);
    const buyBtn = document.getElementById("simBuyBtn");
    const countryName = simCountryEl.options[simCountryEl.selectedIndex].text;
    buyBtn.dataset.product = `Non-VoIP SIM: ${svc.name} (${countryName})`;
    buyBtn.dataset.amount = finalPrice.toFixed(2);
  }
  simServiceEl.addEventListener("change", updateSimCalc);
  simCountryEl.addEventListener("change", updateSimCalc);

  // =========================================================================
  // 3-STEP CHECKOUT MODAL WITH REAL OXAPAY WHITE LABEL INVOICE + LIVE POLLING
  // =========================================================================
  const modal = document.getElementById("checkoutModal");
  const panes = [1, 2, 3].map(n => document.getElementById(`stepPane${n}`));
  const inds = [1, 2, 3].map(n => document.getElementById(`stepInd${n}`));
  const modalStatus = document.getElementById("modalStatus");
  const otpBanner = document.getElementById("otpBanner");
  const otpInput = document.getElementById("otpInput");
  const humanSlider = document.getElementById("humanSlider");
  const sliderVal = document.getElementById("sliderVal");
  const liveStatusPill = document.getElementById("liveStatusPill");

  function stopTimers() {
    clearInterval(timerInterval);
    clearInterval(pollInterval);
  }

  function setStep(stepNum) {
    panes.forEach((p, idx) => p.classList.toggle("active", idx + 1 === stepNum));
    inds.forEach((el, idx) => {
      el.classList.toggle("active", idx + 1 === stepNum);
      el.classList.toggle("done", idx + 1 < stepNum);
    });
    modalStatus.textContent = "";
    modalStatus.className = "modal-status";
  }

  function startCountdown(expiredAtUnix) {
    clearInterval(timerInterval);
    const el = document.getElementById("payTimer");
    function tick() {
      const now = Math.floor(Date.now() / 1000);
      const secs = Math.max(0, (expiredAtUnix || (now + 1800)) - now);
      const m = String(Math.floor(secs / 60)).padStart(2, "0");
      const s = String(secs % 60).padStart(2, "0");
      el.textContent = `${m}:${s}`;
    }
    tick();
    timerInterval = setInterval(tick, 1000);
  }

  async function pollOrderStatus() {
    const DIRECT = window.MEFTA_DIRECT && window.MEFTA_DIRECT.statusUrl;
    if (!activeOrder.order_id || (!DIRECT && !CFG.restBase)) return;
    try {
      const url = DIRECT
        ? `${DIRECT}?order_id=${encodeURIComponent(activeOrder.order_id)}&_=${Date.now()}`
        : `${CFG.restBase}/order-status?order_id=${encodeURIComponent(activeOrder.order_id)}`;
      const res = await fetch(url);
      const data = await res.json();
      if (!data || !data.status) return;

      const st = String(data.status);
      liveStatusPill.textContent = `● ${st}`;
      if (st.toLowerCase() === "paid") {
        liveStatusPill.classList.add("paid");
        stopTimers();
        modalStatus.className = "modal-status ok";
        modalStatus.textContent = `Payment Confirmed (${data.order_id})! Delivery triggered to your email.`;
      } else if (st.toLowerCase() === "paying" || st.toLowerCase() === "confirming") {
        modalStatus.className = "modal-status ok";
        modalStatus.textContent = `Transaction detected on ${data.network}. Waiting for blockchain confirmation...`;
      } else if (st.toLowerCase() === "expired") {
        stopTimers();
        modalStatus.className = "modal-status err";
        modalStatus.textContent = "Invoice expired. Click a network pill above to generate a fresh USDT address.";
      }
    } catch (e) {}
  }

  async function createLiveInvoice(network) {
    if (!ENABLED_NETS.includes(network)) network = ENABLED_NETS[0];
    activePayNet = network;
    document.querySelectorAll(".pay-net-pill").forEach(p => p.classList.toggle("active", p.dataset.net === network));
    document.getElementById("payNetLabel").textContent = `USDT (${network})`;
    document.getElementById("walletAddressText").textContent = "Generating live USDT address...";
    liveStatusPill.classList.remove("paid");
    liveStatusPill.textContent = "● Generating Invoice";
    modalStatus.className = "modal-status";
    modalStatus.textContent = "Connecting to USDT payment gateway...";

    const email = document.getElementById("emailInput").value.trim();

    if (CFG.ajaxUrl) {
      try {
        const body = new URLSearchParams({
          action: "mefta_create_invoice",
          nonce: CFG.nonce,
          email,
          product: activeCheckout.product,
          amount: activeCheckout.amount.toFixed(2),
          network
        });
        const res = await fetch(CFG.ajaxUrl, { method: "POST", body });
        const json = await res.json();

        if (json.success && json.data) {
          const d = json.data;
          activeOrder = { order_id: d.order_id, track_id: d.track_id, expired_at: d.expired_at };
          document.getElementById("orderIdLabel").textContent = `Order: ${d.order_id}`;
          const payStr = (typeof d.pay_amount === "string" && d.pay_amount.indexOf(".") > -1) ? d.pay_amount : Number(d.pay_amount).toFixed(2);
          const amtEl = document.getElementById("exactPayAmount");
          amtEl.textContent = `${payStr} USDT`;
          amtEl.title = "Click to copy the exact amount";
          amtEl.style.cursor = "copy";
          amtEl.onclick = () => { if (navigator.clipboard) navigator.clipboard.writeText(payStr); modalStatus.className = "modal-status ok"; modalStatus.textContent = `Copied exact amount: ${payStr} USDT`; };
          document.getElementById("walletAddressText").textContent = d.address;
          liveStatusPill.textContent = `● ${d.status || "Waiting"}`;

          const qrImg = document.getElementById("liveQrImg");
          const qrSvg = document.getElementById("fallbackQrSvg");
          if (d.qr_code) {
            qrImg.src = d.qr_code;
            qrImg.style.display = "block";
            qrSvg.style.display = "none";
          } else {
            qrImg.style.display = "none";
            qrSvg.style.display = "block";
          }

          startCountdown(d.expired_at);
          clearInterval(pollInterval);
          if (d.gateway_mode === "oxapay" || d.gateway_mode === "direct") {
            pollInterval = setInterval(pollOrderStatus, 6000);
          }

          modalStatus.className = "modal-status ok";
          modalStatus.textContent = d.message;
          return;
        } else {
          modalStatus.className = "modal-status err";
          modalStatus.textContent = (json.data && json.data.message) || "Gateway error.";
          return;
        }
      } catch (err) {}
    }

    // SAFETY: never show a static/fallback wallet address. Only OxaPay-issued addresses are valid.
    document.getElementById("walletAddressText").textContent = "—";
    document.getElementById("exactPayAmount").textContent = "-- USDT";
    liveStatusPill.textContent = "● Unavailable";
    modalStatus.className = "modal-status err";
    modalStatus.textContent = "Payment gateway is temporarily unavailable. Please do NOT send any funds. Try again in a few minutes or contact support@meftacard.com.";
  }

  function openCheckout(product, amount) {
    stopTimers();
    activeCheckout = { product, amount: parseFloat(amount) || 22.00 };
    document.getElementById("modalProductTitle").textContent = activeCheckout.product;
    document.getElementById("modalAmountText").textContent = `$${activeCheckout.amount.toFixed(2)} USDT (${fmtLocal(activeCheckout.amount)})`;
    humanSlider.value = 0;
    sliderVal.textContent = "0%";
    otpInput.value = "";
    otpBanner.textContent = "";
    otpBanner.classList.remove("show");
    localDemoCode = "";
    setStep(1);
    modal.classList.add("open");
  }

  document.querySelectorAll(".open-checkout").forEach(btn => {
    btn.addEventListener("click", () => openCheckout(btn.dataset.product || "Meftacard Order", btn.dataset.amount || "22.00"));
  });

  document.getElementById("heroCardBuyBtn").addEventListener("click", () => {
    const net = document.getElementById("cardNetSelect").value;
    const fee = parseFloat(CFG.cardFee) || 0;
    const total = +(selectedCardLoad + fee).toFixed(2);
    const feeTxt = fee > 0 ? ` + $${fee.toFixed(2)} Fee` : "";
    openCheckout(`Holographic Virtual Card ($${selectedCardLoad}${feeTxt} · ${net})`, total);
  });

  document.getElementById("topAuthBtn").addEventListener("click", () => {
    openCheckout("Internal USDT Balance Top-Up", parseFloat(CFG.defaultTopup) || 25.00);
  });

  document.getElementById("closeModalBtn").addEventListener("click", () => { stopTimers(); modal.classList.remove("open"); });
  modal.addEventListener("click", e => { if (e.target === modal) { stopTimers(); modal.classList.remove("open"); } });

  humanSlider.addEventListener("input", () => { sliderVal.textContent = `${humanSlider.value}%`; });
  otpInput.addEventListener("input", () => { otpInput.value = normalizeDigits(otpInput.value).replace(/\D/g, "").slice(0, 6); });

  async function requestOtp() {
    const email = document.getElementById("emailInput").value.trim();
    if (!email || !email.includes("@")) {
      modalStatus.className = "modal-status err";
      modalStatus.textContent = "Please enter a valid email address.";
      return;
    }
    if (parseInt(humanSlider.value, 10) < 100) {
      modalStatus.className = "modal-status err";
      modalStatus.textContent = "Slide the Non-Bot verification bar to 100%.";
      return;
    }

    modalStatus.className = "modal-status";
    modalStatus.textContent = "Processing...";

    if (CFG.ajaxUrl) {
      try {
        const body = new URLSearchParams({ action: "mefta_send_otp", nonce: CFG.nonce, email });
        const res = await fetch(CFG.ajaxUrl, { method: "POST", body });
        const json = await res.json();
        if (json.success) {
          if (json.data.mode === "direct") {
            setStep(3);
            createLiveInvoice(activePayNet);
            return;
          }
          localDemoCode = json.data.demo_code || "";
          setStep(2);
          otpBanner.textContent = json.data.message;
          otpBanner.classList.add("show");
          otpInput.focus();
          return;
        } else {
          modalStatus.className = "modal-status err";
          modalStatus.textContent = (json.data && json.data.message) || "Could not send OTP.";
          return;
        }
      } catch (err) {}
    }

    // SAFETY: no client-side demo codes. The OTP must be generated and emailed by the server.
    modalStatus.className = "modal-status err";
    modalStatus.textContent = "Could not send the verification email. Please try again or contact support@meftacard.com.";
  }

  document.getElementById("sendOtpBtn").addEventListener("click", requestOtp);
  document.getElementById("resendOtpBtn").addEventListener("click", requestOtp);
  document.getElementById("backToStep1Btn").addEventListener("click", () => setStep(1));

  document.getElementById("verifyOtpBtn").addEventListener("click", async () => {
    const email = document.getElementById("emailInput").value.trim();
    const code = normalizeDigits(otpInput.value).replace(/\D/g, "");
    otpInput.value = code;

    if (code.length !== 6) {
      modalStatus.className = "modal-status err";
      modalStatus.textContent = "Please type the 6-digit verification code above.";
      otpInput.focus();
      return;
    }

    if (CFG.ajaxUrl) {
      try {
        const body = new URLSearchParams({ action: "mefta_verify_otp", nonce: CFG.nonce, email, code });
        const res = await fetch(CFG.ajaxUrl, { method: "POST", body });
        const json = await res.json();
        if (json.success) {
          setStep(3);
          createLiveInvoice(activePayNet);
          return;
        } else {
          modalStatus.className = "modal-status err";
          modalStatus.textContent = (json.data && json.data.message) || "Invalid code.";
          return;
        }
      } catch (err) {}
    }

    // SAFETY: verification happens only on the server.
    modalStatus.className = "modal-status err";
    modalStatus.textContent = "Verification service unavailable. Please try again.";
  });

  document.querySelectorAll(".pay-net-pill").forEach(pill => {
    pill.addEventListener("click", () => createLiveInvoice(pill.dataset.net));
  });

  document.getElementById("copyWalletBtn").addEventListener("click", () => {
    const text = document.getElementById("walletAddressText").textContent;
    if (navigator.clipboard) navigator.clipboard.writeText(text);
    modalStatus.className = "modal-status ok";
    modalStatus.textContent = `Copied ${activePayNet} USDT address.`;
  });

  document.getElementById("confirmPaidBtn").addEventListener("click", () => {
    modalStatus.className = "modal-status ok";
    modalStatus.textContent = "Checking live blockchain confirmation status...";
    pollOrderStatus();
  });

  setCorridor(activeCorridor.id, true);
})();
