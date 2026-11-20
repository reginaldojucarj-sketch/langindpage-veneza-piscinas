(function () {
  'use strict';

  if (window.location.origin === 'https://equipamentos.venezapiscinas.com.br') {
    const preferenceKey = 'veneza-ads-measurement';
    let measurementEnabled = false;
    let tagLoaded = false;
    const settings = document.createElement('details');
    const summary = document.createElement('summary');
    summary.textContent = 'Preferências de medição';
    const description = document.createElement('p');
    description.textContent = 'Permita que o Google Ads meça cliques para abrir o WhatsApp.';
    const preference = document.createElement('button');
    preference.type = 'button';
    settings.append(summary, description, preference);
    const footer = document.querySelector('footer');
    if (footer) footer.append(settings);

    try {
      measurementEnabled = localStorage.getItem(preferenceKey) === 'allowed';
    } catch {
      // The preference can still apply to this page when storage is unavailable.
    }

    function updateMeasurement() {
      preference.textContent = measurementEnabled ? 'Desativar medição' : 'Permitir medição';
      if (measurementEnabled && !tagLoaded) {
        tagLoaded = true;
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        window.gtag('consent', 'default', {
          ad_storage: 'denied',
          ad_user_data: 'denied',
          ad_personalization: 'denied'
        });
        window.gtag('js', new Date());
        window.gtag('config', 'AW-882259007', { allow_ad_personalization_signals: false });
        const tag = document.createElement('script');
        tag.async = true;
        tag.src = 'https://www.googletagmanager.com/gtag/js?id=AW-882259007';
        document.head.append(tag);
      }
      if (tagLoaded) window.gtag('consent', 'update', {
        ad_storage: measurementEnabled ? 'granted' : 'denied',
        ad_user_data: measurementEnabled ? 'granted' : 'denied',
        ad_personalization: 'denied'
      });
    }

    preference.addEventListener('click', function () {
      measurementEnabled = !measurementEnabled;
      try {
        localStorage.setItem(preferenceKey, measurementEnabled ? 'allowed' : 'denied');
      } catch {
        // Keep the choice in memory for this page.
      }
      updateMeasurement();
    });
    updateMeasurement();

    function reportWhatsAppClick(navigate) {
      if (!measurementEnabled) {
        if (navigate) navigate();
        return;
      }
      const parameters = { send_to: 'AW-882259007/c_ykCLiYrbwdEL_o2KQD' };
      if (navigate) {
        let navigated = false;
        const finish = function () {
          if (navigated) return;
          navigated = true;
          navigate();
        };
        window.setTimeout(finish, 500);
        parameters.event_callback = finish;
        parameters.event_timeout = 500;
      }
      window.gtag('event', 'conversion', parameters);
    }

    document.addEventListener('click', function (event) {
      if (event.defaultPrevented || !(event.target instanceof Element)) return;
      const link = event.target.closest('a[href^="https://wa.me/"], a[href^="https://api.whatsapp.com/"]');
      if (!link) return;
      if (measurementEnabled && (!link.target || link.target === '_self') &&
          !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
        event.preventDefault();
        reportWhatsAppClick(function () { window.location.assign(link.href); });
      } else {
        reportWhatsAppClick();
      }
    });
  }

  const siteHeader = document.querySelector('.site-header');
  const productNavigation = siteHeader ? siteHeader.querySelector('.main-nav') : null;
  const productMenuToggle = siteHeader ? siteHeader.querySelector('.compact-products-toggle') : null;
  let headerUpdatePending = false;

  function closeProductMenu(restoreFocus) {
    if (!productNavigation || !productMenuToggle) return;
    productNavigation.classList.remove('is-open');
    productMenuToggle.setAttribute('aria-expanded', 'false');
    if (restoreFocus) productMenuToggle.focus();
  }

  function updateCompactHeader() {
    if (!siteHeader) return;

    const isMobile = window.matchMedia('(max-width: 1000px)').matches;
    const isShortLandscape = window.matchMedia('(orientation: landscape) and (max-height: 600px)').matches;
    const isCompact = siteHeader.classList.contains('is-compact');
    const compactThreshold = isShortLandscape ? 24 : 72;
    const shouldCompact = isMobile && (isCompact ? window.scrollY > 12 : window.scrollY >= compactThreshold);

    siteHeader.classList.toggle('is-compact', shouldCompact);
    document.documentElement.classList.toggle('header-compact', shouldCompact);
    if (!shouldCompact) closeProductMenu(false);
    headerUpdatePending = false;
  }

  function requestHeaderUpdate() {
    if (headerUpdatePending) return;
    headerUpdatePending = true;
    window.requestAnimationFrame(updateCompactHeader);
  }

  if (siteHeader) {
    window.addEventListener('scroll', requestHeaderUpdate, { passive: true });
    window.addEventListener('resize', requestHeaderUpdate);
    updateCompactHeader();
  }

  if (productNavigation && productMenuToggle) {
    productMenuToggle.addEventListener('click', function () {
      const willOpen = !productNavigation.classList.contains('is-open');
      productNavigation.classList.toggle('is-open', willOpen);
      productMenuToggle.setAttribute('aria-expanded', String(willOpen));
    });

    productNavigation.querySelectorAll('[data-modal-link]').forEach(function (link) {
      link.addEventListener('click', function () {
        closeProductMenu(false);
      });
    });

    document.addEventListener('click', function (event) {
      if (!productNavigation.classList.contains('is-open')) return;
      if (!productNavigation.contains(event.target)) closeProductMenu(false);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape' || !productNavigation.classList.contains('is-open')) return;
      closeProductMenu(true);
    });
  }

  const backToTopButton = document.querySelector('.back-to-top');
  let backToTopWasVisible = false;

  function updateBackToTop() {
    if (!backToTopButton) return;

    const isVisible = window.scrollY >= 150;
    backToTopButton.hidden = !isVisible;

    if (isVisible && !backToTopWasVisible) backToTopButton.classList.add('is-bouncing');
    if (!isVisible) backToTopButton.classList.remove('is-bouncing');
    backToTopWasVisible = isVisible;
  }

  if (backToTopButton) {
    window.addEventListener('scroll', updateBackToTop, { passive: true });
    backToTopButton.addEventListener('mouseenter', function () {
      backToTopButton.classList.remove('is-bouncing');
    });
    backToTopButton.addEventListener('click', function () {
      const behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
      window.scrollTo({ top: 0, behavior: behavior });
    });
    updateBackToTop();
  }

  const whatsappButton = document.querySelector('.whatsapp-float');
  if (whatsappButton) {
    window.setTimeout(function () {
      whatsappButton.classList.remove('is-intro');
    }, 3000);
  }

  const allowsMotion = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if ('IntersectionObserver' in window && allowsMotion) {
    const revealSelector = [
      '.hero-panel',
      '.section-heading',
      '.priority-card',
      '.catalog-card',
      '.advantage-card',
      '.scope-note',
      '.step',
      '.gallery-card',
      '.quote',
      '.faq-panel',
      '.cta-panel'
    ].join(',');
    const revealItems = document.querySelectorAll(revealSelector);
    const revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        revealObserver.unobserve(entry.target);
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

    revealItems.forEach(function (item) {
      item.classList.add('reveal-item');
      revealObserver.observe(item);
    });
    document.documentElement.classList.add('js-reveal');
  }
}());
