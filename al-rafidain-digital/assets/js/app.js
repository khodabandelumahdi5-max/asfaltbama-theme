/**
 * Al-Rafidain Digital — interactions.
 * Vanilla JS, no dependencies. Requires config.js and i18n.js to load first.
 */
(function () {
  'use strict';

  var CFG = window.RD_CONFIG;
  var DICT = window.RD_I18N;
  var STR = window.RD_STRINGS;
  var doc = document.documentElement;
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  var state = { lang: 'ar', city: 'baghdad' };

  /* ------------------------------------------------------------------ */
  /*  i18n                                                               */
  /* ------------------------------------------------------------------ */
  // Arabic is authored in the HTML; capture it once so we can switch back.
  var AR = {};
  var AR_ATTR = [];
  $$('[data-i18n]').forEach(function (el) {
    var key = el.getAttribute('data-i18n');
    if (!(key in AR)) AR[key] = el.innerHTML.trim();
  });
  $$('[data-i18n-attr]').forEach(function (el) {
    el.getAttribute('data-i18n-attr').split(',').forEach(function (pair) {
      var parts = pair.split(':');
      var attr = parts[0].trim();
      var key = parts[1].trim();
      AR[key] = el.getAttribute(attr) || '';
      AR_ATTR.push({ el: el, attr: attr, key: key });
    });
  });

  function html(key) {
    if (state.lang === 'ckb' && DICT.ckb[key] != null) return DICT.ckb[key];
    return AR[key] != null ? AR[key] : '';
  }
  function text(key) {
    var div = document.createElement('div');
    div.innerHTML = html(key);
    return div.textContent.replace(/\s+/g, ' ').trim();
  }
  function t(key, vars) {
    var s = (STR[state.lang] && STR[state.lang][key]) || STR.ar[key] || key;
    if (vars) Object.keys(vars).forEach(function (k) { s = s.split('{' + k + '}').join(vars[k]); });
    return s;
  }

  var kurdishFontLoaded = false;
  function loadKurdishFont() {
    if (kurdishFontLoaded) return;
    kurdishFontLoaded = true;
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap';
    document.head.appendChild(link);
  }

  function setLang(lang, opts) {
    opts = opts || {};
    state.lang = lang === 'ckb' ? 'ckb' : 'ar';
    if (state.lang === 'ckb') loadKurdishFont();

    doc.lang = state.lang === 'ckb' ? 'ckb' : 'ar-IQ';
    doc.dir = 'rtl';

    $$('[data-i18n]').forEach(function (el) { el.innerHTML = html(el.getAttribute('data-i18n')); });
    AR_ATTR.forEach(function (a) { a.el.setAttribute(a.attr, html(a.key)); });

    document.title = t('meta.title');
    var desc = $('meta[name="description"]');
    if (desc) desc.setAttribute('content', t('meta.desc'));
    var ogLocale = $('meta[property="og:locale"]');
    if (ogLocale) ogLocale.setAttribute('content', state.lang === 'ckb' ? 'ckb_IQ' : 'ar_IQ');

    $$('[data-lang-btn]').forEach(function (btn) {
      var active = btn.getAttribute('data-lang-btn') === state.lang;
      btn.setAttribute('aria-pressed', String(active));
      btn.classList.toggle('bg-gold-400', active);
      btn.classList.toggle('text-slate-950', active);
      btn.classList.toggle('text-slate-300', !active);
      btn.classList.toggle('hover:text-white', !active);
    });

    if (opts.persist) {
      try { localStorage.setItem('rd-lang', state.lang); } catch (e) {}
      var url = new URL(location.href);
      if (state.lang === 'ckb') url.searchParams.set('lang', 'ckb');
      else url.searchParams.delete('lang');
      history.replaceState(null, '', url.pathname + url.search + url.hash);
    }

    doc.classList.remove('i18n-pending');
    refreshWhatsApp();
    updateEstimate();
    updateEmailHint();
  }

  $$('[data-lang-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setLang(btn.getAttribute('data-lang-btn'), { persist: true });
    });
  });

  /* ------------------------------------------------------------------ */
  /*  WhatsApp deep links                                                */
  /* ------------------------------------------------------------------ */
  function waUrl(message) {
    return 'https://wa.me/' + CFG.whatsappNumber + '?text=' + encodeURIComponent(message);
  }
  function waMessage(el) {
    var kind = el.getAttribute('data-wa');
    if (kind === 'city') {
      var city = el.getAttribute('data-wa-city');
      return t('wa.msg.city', { city: text('city.' + city), sector: text('geo.' + city + '.tag') });
    }
    if (kind === 'estimate') return estimateMessage();
    return t('wa.msg.' + kind);
  }
  function refreshWhatsApp() {
    $$('[data-wa]').forEach(function (el) {
      el.href = waUrl(waMessage(el));
      el.target = '_blank';
      el.rel = 'noopener';
    });
  }

  function formatPhone(digits) {
    // 9647XXXXXXXXX -> +964 7XX XXX XXXX
    var m = /^964(\d{3})(\d{3})(\d+)$/.exec(digits);
    return m ? '+964 ' + m[1] + ' ' + m[2] + ' ' + m[3] : '+' + digits;
  }
  $$('[data-wa-display]').forEach(function (el) { el.textContent = formatPhone(CFG.whatsappNumber); });
  $$('[data-rfq-email]').forEach(function (el) { el.textContent = CFG.rfqEmail; });
  $$('[data-mailto]').forEach(function (el) { el.href = 'mailto:' + CFG.rfqEmail; });

  /* ------------------------------------------------------------------ */
  /*  Header + mobile menu                                               */
  /* ------------------------------------------------------------------ */
  var bar = $('[data-header-bar]');
  var SCROLLED = ['border-white/10', 'bg-slate-950/80', 'backdrop-blur-xl', 'shadow-2xl', 'shadow-black/40'];
  function onScroll() {
    var scrolled = window.scrollY > 12;
    SCROLLED.forEach(function (c) { bar.classList.toggle(c, scrolled); });
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  var menuBtn = $('[data-menu-toggle]');
  var menu = $('#mobile-menu');
  function setMenu(open) {
    menu.classList.toggle('hidden', !open);
    menuBtn.setAttribute('aria-expanded', String(open));
  }
  menuBtn.addEventListener('click', function () { setMenu(menu.classList.contains('hidden')); });
  $$('a', menu).forEach(function (a) { a.addEventListener('click', function () { setMenu(false); }); });

  /* ------------------------------------------------------------------ */
  /*  City tabs + geo routing (#city-erbil or ?city=erbil)               */
  /* ------------------------------------------------------------------ */
  var tabs = $$('[data-city-tabs] [role="tab"]');
  var CITIES = tabs.map(function (tab) { return tab.getAttribute('data-city'); });

  function selectCity(city, opts) {
    opts = opts || {};
    if (CITIES.indexOf(city) === -1) return;
    state.city = city;
    tabs.forEach(function (tab) {
      var active = tab.getAttribute('data-city') === city;
      tab.setAttribute('aria-selected', String(active));
      tab.tabIndex = active ? 0 : -1;
      if (active && opts.focus) tab.focus();
      if (active && opts.reveal) tab.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
    });
    $$('[data-city-panel]').forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-city-panel') !== city;
    });
    var rfqCity = $('#rfq-city');
    if (rfqCity && opts.syncForm !== false) rfqCity.value = city;
    if (opts.updateUrl) history.replaceState(null, '', '#city-' + city);
  }

  tabs.forEach(function (tab, i) {
    tab.addEventListener('click', function () { selectCity(tab.getAttribute('data-city'), { updateUrl: true, reveal: true }); });
    tab.addEventListener('keydown', function (e) {
      // RTL: ArrowLeft moves forward, ArrowRight moves back.
      var next = { ArrowLeft: i + 1, ArrowRight: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
      if (next == null) return;
      e.preventDefault();
      next = (next + tabs.length) % tabs.length;
      selectCity(tabs[next].getAttribute('data-city'), { focus: true, updateUrl: true, reveal: true });
    });
  });

  $$('[data-city-link]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      selectCity(link.getAttribute('data-city-link'), { updateUrl: true });
      $('#cities').scrollIntoView({ behavior: 'smooth' });
    });
  });

  function routeFromUrl() {
    var match = /^#city-([a-z]+)$/.exec(location.hash);
    var param = new URLSearchParams(location.search).get('city');
    var city = match ? match[1] : param;
    if (city && CITIES.indexOf(city) !== -1) {
      selectCity(city);
      $('#cities').scrollIntoView();
    }
  }
  window.addEventListener('hashchange', routeFromUrl);

  /* ------------------------------------------------------------------ */
  /*  Pricing estimator                                                  */
  /* ------------------------------------------------------------------ */
  var est = $('[data-estimator]');
  var P = CFG.pricing;

  function usd(range) {
    if (!range[1]) return '$0';
    return '$' + range[0].toLocaleString('en-US') + ' – $' + range[1].toLocaleString('en-US');
  }
  function labelOf(input) {
    var span = input.parentElement.querySelector('[data-i18n]');
    return span ? span.textContent.trim() : input.value;
  }

  function computeEstimate() {
    var pkgInput = $('input[name="pkg"]:checked', est);
    var supInput = $('input[name="support"]:checked', est);
    var pkg = pkgInput.value;
    var once = P.packages[pkg].once.slice();
    var monthly = [0, 0];
    var addons = [];

    $$('input[name="addon"]', est).forEach(function (input) {
      var addon = P.addons[input.value];
      var included = (addon.includedIn || []).indexOf(pkg) !== -1;
      input.disabled = included;
      // Auto-tick add-ons the package already contains; untick them again if the package changes.
      if (included) {
        if (!input.checked) input.dataset.auto = '1';
        input.checked = true;
      } else if (input.dataset.auto) {
        input.checked = false;
        delete input.dataset.auto;
      }
      input.parentElement.querySelector('[data-included]').classList.toggle('hidden', !included);
      if (!input.checked) return;
      addons.push(labelOf(input));
      if (included) return;
      if (addon.once) { once[0] += addon.once[0]; once[1] += addon.once[1]; }
      if (addon.monthly) { monthly[0] += addon.monthly[0]; monthly[1] += addon.monthly[1]; }
    });

    var sup = P.support[supInput.value];
    var supIncluded = (sup.includedIn || []).indexOf(pkg) !== -1;
    $$('input[name="support"]', est).forEach(function (input) {
      var inc = (P.support[input.value].includedIn || []).indexOf(pkg) !== -1;
      input.parentElement.querySelector('[data-included]') &&
        input.parentElement.querySelector('[data-included]').classList.toggle('hidden', !inc);
    });
    if (!supIncluded) { monthly[0] += sup.monthly[0]; monthly[1] += sup.monthly[1]; }

    return {
      pkg: labelOf(pkgInput),
      support: labelOf(supInput),
      supIncluded: supIncluded,
      addons: addons,
      once: once,
      monthly: monthly,
    };
  }

  var lastEstimate = null;
  function updateEstimate() {
    if (!est) return;
    var e = (lastEstimate = computeEstimate());
    $('[data-est-once]').textContent = usd(e.once);
    $('[data-est-monthly]').textContent = usd(e.monthly);
    $('[data-est-permonth]').textContent = t('est.per_month');
    $('[data-est-vipnote]').textContent = e.supIncluded ? 'VIP ' + t('est.included_y1') : '';
    var link = $('[data-wa="estimate"]');
    if (link) link.href = waUrl(estimateMessage());
  }
  function estimateMessage() {
    var e = lastEstimate || computeEstimate();
    return t('wa.msg.estimate', {
      pkg: e.pkg,
      addons: e.addons.length ? e.addons.join('، ') : t('none'),
      support: e.support + (e.supIncluded ? ' (' + t('est.included_y1') + ')' : ''),
      once: usd(e.once),
      monthly: usd(e.monthly) + ' ' + t('est.per_month'),
    });
  }

  if (est) {
    est.addEventListener('change', updateEstimate);
    est.addEventListener('submit', function (e) { e.preventDefault(); });
  }
  $$('[data-pick-package]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = $('input[name="pkg"][value="' + btn.getAttribute('data-pick-package') + '"]', est);
      input.checked = true;
      updateEstimate();
      $('#estimator').scrollIntoView({ behavior: 'smooth' });
    });
  });

  /* ------------------------------------------------------------------ */
  /*  Enterprise RFQ form                                                */
  /* ------------------------------------------------------------------ */
  var form = $('#rfq-form');
  var statusEl = $('[data-rfq-status]');
  var emailInput = $('#rfq-email');
  var emailHint = $('[data-email-hint]');
  var FREE_MAIL = /@(gmail|googlemail|yahoo|hotmail|outlook|live|icloud|aol|yandex|mail)\./i;
  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  function updateEmailHint() {
    if (!emailInput) return;
    var show = FREE_MAIL.test(emailInput.value);
    emailHint.textContent = show ? t('form.email_free') : '';
    emailHint.classList.toggle('hidden', !show);
  }
  emailInput.addEventListener('input', updateEmailHint);

  function setStatus(kind, message) {
    statusEl.textContent = message;
    statusEl.classList.remove('hidden', 'bg-emerald-500/10', 'text-emerald-300', 'bg-rose-500/10', 'text-rose-300', 'bg-white/5', 'text-slate-300');
    var cls = { ok: ['bg-emerald-500/10', 'text-emerald-300'], err: ['bg-rose-500/10', 'text-rose-300'], info: ['bg-white/5', 'text-slate-300'] }[kind];
    cls.forEach(function (c) { statusEl.classList.add(c); });
  }

  function validate() {
    var firstInvalid = null;
    $$('[required]', form).forEach(function (field) {
      var value = field.type === 'checkbox' ? field.checked : field.value.trim();
      var ok = !!value;
      if (ok && field.type === 'email') ok = EMAIL_RE.test(field.value.trim());
      if (ok && field.type === 'tel') ok = field.value.replace(/\D/g, '').length >= 8;
      field.setAttribute('aria-invalid', String(!ok));
      if (!ok && !firstInvalid) firstInvalid = field;
    });
    return firstInvalid;
  }
  form.addEventListener('input', function (e) {
    if (e.target.getAttribute('aria-invalid') === 'true') e.target.setAttribute('aria-invalid', 'false');
  });

  function collect() {
    var fd = new FormData(form);
    var selectText = function (id) { var s = $(id); return s.options[s.selectedIndex].textContent.trim(); };
    return {
      organization: fd.get('organization').trim(),
      entityType: selectText('#rfq-type'),
      fullName: fd.get('fullName').trim(),
      jobTitle: fd.get('jobTitle').trim(),
      email: fd.get('email').trim(),
      phone: fd.get('phone').trim(),
      city: selectText('#rfq-city'),
      budget: selectText('#rfq-budget'),
      scope: $$('input[name="scope"]:checked', form).map(labelOf).join('، ') || t('none'),
      timeline: selectText('#rfq-timeline'),
      details: fd.get('details').trim(),
      language: state.lang,
      source: location.href,
    };
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (form.website.value) return; // honeypot
    var invalid = validate();
    if (invalid) {
      setStatus('err', t('form.invalid'));
      invalid.focus();
      return;
    }

    var data = collect();
    var submitBtn = $('[data-rfq-submit]', form);

    if (CFG.rfqEndpoint) {
      submitBtn.disabled = true;
      setStatus('info', t('form.sending'));
      fetch(CFG.rfqEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(Object.assign({ _replyto: data.email, _subject: t('form.subject', { org: data.organization }) }, data)),
      })
        .then(function (res) {
          if (!res.ok) throw new Error('HTTP ' + res.status);
          setStatus('ok', t('form.success'));
          form.reset();
          selectCity(state.city);
        })
        .catch(function () { setStatus('err', t('form.error')); })
        .then(function () { submitBtn.disabled = false; });
      return;
    }

    // No endpoint configured: hand off to the visitor's mail client.
    var labels = t('form.labels').split('|');
    var values = [data.organization, data.entityType, data.fullName, data.jobTitle, data.email, data.phone, data.city, data.budget, data.scope, data.timeline, data.details];
    var body = labels.map(function (l, i) { return l + ': ' + (values[i] || '—'); }).join('\n');
    location.href = 'mailto:' + CFG.rfqEmail +
      '?subject=' + encodeURIComponent(t('form.subject', { org: data.organization })) +
      '&body=' + encodeURIComponent(body);
    setStatus('ok', t('form.mailto'));
  });

  /* ------------------------------------------------------------------ */
  /*  Floating WhatsApp VIP widget                                       */
  /* ------------------------------------------------------------------ */
  var widget = $('[data-wa-widget]');
  var panel = $('#wa-panel');
  var toggle = $('[data-wa-toggle]');
  function setPanel(open) {
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
  }
  toggle.addEventListener('click', function () { setPanel(panel.hidden); });
  $('[data-wa-close]').addEventListener('click', function () { setPanel(false); toggle.focus(); });
  document.addEventListener('click', function (e) {
    if (!panel.hidden && !widget.contains(e.target)) setPanel(false);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (!panel.hidden) { setPanel(false); toggle.focus(); }
    if (!menu.classList.contains('hidden')) { setMenu(false); menuBtn.focus(); }
  });

  /* ------------------------------------------------------------------ */
  /*  Reveal on scroll + misc                                            */
  /* ------------------------------------------------------------------ */
  var revealEls = $$('.reveal');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add('is-visible'); });
  }

  $$('[data-year]').forEach(function (el) { el.textContent = new Date().getFullYear(); });

  /* ------------------------------------------------------------------ */
  /*  Boot                                                               */
  /* ------------------------------------------------------------------ */
  setLang(window.RD_INITIAL_LANG || 'ar');
  routeFromUrl();
})();
