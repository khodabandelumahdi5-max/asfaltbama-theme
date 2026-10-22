/**
 * Al-Rafidain Digital — site configuration.
 * Everything a non-developer needs to change lives here.
 */
window.RD_CONFIG = {
  /* WhatsApp Business number, international format, digits only (no "+", no spaces). */
  whatsappNumber: '9647XXXXXXXXX',

  /* Corporate inbox that receives Enterprise RFQ submissions. */
  rfqEmail: '[INSERT_EMAIL_HERE]',

  /*
   * Optional HTTPS endpoint that accepts a JSON POST (Formspree, Basin, your own API…).
   * Leave empty to fall back to a pre-filled e-mail to `rfqEmail`.
   */
  rfqEndpoint: '',

  /* Exchange rate used by the estimator to show IQD next to USD. */
  usdToIqd: 1310,

  /*
   * Price book for the estimator (USD). `once` = one-time project fee range,
   * `monthly` = recurring range. `includedIn` = packages that already contain the add-on.
   */
  pricing: {
    packages: {
      basic:      { once: [1200, 2500] },
      ecommerce:  { once: [3500, 7500] },
      enterprise: { once: [9000, 25000] },
    },
    addons: {
      flutter:   { once: [4000, 12000], includedIn: ['enterprise'] },
      zaincash:  { once: [500, 900],    includedIn: ['ecommerce', 'enterprise'] },
      qicard:    { once: [500, 900],    includedIn: ['ecommerce', 'enterprise'] },
      fastpay:   { once: [500, 900],    includedIn: ['ecommerce', 'enterprise'] },
      erp:       { once: [5000, 20000] },
      content:   { once: [400, 1500] },
      seo:       { monthly: [400, 1500] },
    },
    support: {
      none:     { monthly: [0, 0] },
      standard: { monthly: [150, 300] },
      vip:      { monthly: [600, 1500], includedIn: ['enterprise'] },
    },
  },
};
