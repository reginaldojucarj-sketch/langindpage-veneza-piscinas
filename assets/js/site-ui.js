(function () {
  'use strict';

  const siteHeader = document.querySelector('.site-header');
  let headerUpdatePending = false;

  function updateCompactHeader() {
    if (!siteHeader) return;

    const isMobile = window.matchMedia('(max-width: 1000px)').matches;
    const isShortLandscape = window.matchMedia('(orientation: landscape) and (max-height: 600px)').matches;
    const isCompact = siteHeader.classList.contains('is-compact');
    const compactThreshold = isShortLandscape ? 24 : 72;
    const shouldCompact = isMobile && (isCompact ? window.scrollY > 12 : window.scrollY >= compactThreshold);

    siteHeader.classList.toggle('is-compact', shouldCompact);
    document.documentElement.classList.toggle('header-compact', shouldCompact);
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
