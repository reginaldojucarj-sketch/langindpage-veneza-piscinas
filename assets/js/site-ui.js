(function () {
  'use strict';
  var siteHeader = document.querySelector('.site-header'), headerUpdatePending = false;
  function updateCompactHeader() {
    var mobileHeader = window.matchMedia('(max-width: 1000px)').matches;
    var landscapeHeader = window.matchMedia('(orientation: landscape) and (max-height: 600px)').matches;
    var compactNow = siteHeader.classList.contains('is-compact');
    var compactThreshold = landscapeHeader ? 24 : 72;
    var shouldCompact = mobileHeader && (compactNow ? window.scrollY > 12 : window.scrollY >= compactThreshold);
    siteHeader.classList.toggle('is-compact', shouldCompact);
    document.documentElement.classList.toggle('header-compact', shouldCompact);
    headerUpdatePending = false;
  }
  function requestHeaderUpdate() {
    if (headerUpdatePending) return;
    headerUpdatePending = true;
    window.requestAnimationFrame(updateCompactHeader);
  }
  window.addEventListener('scroll', requestHeaderUpdate, { passive: true });
  window.addEventListener('resize', requestHeaderUpdate);
  updateCompactHeader();
  var backToTop = document.querySelector('.back-to-top'), backToTopWasVisible = false;
  function updateBackToTop() { var isVisible = window.scrollY >= 150; backToTop.hidden = !isVisible; if (isVisible && !backToTopWasVisible) { backToTop.classList.add('is-bouncing') } if (!isVisible) { backToTop.classList.remove('is-bouncing') } backToTopWasVisible = isVisible }
  window.addEventListener('scroll', updateBackToTop, { passive: true });
  updateBackToTop();
  backToTop.addEventListener('mouseenter', function () { backToTop.classList.remove('is-bouncing') });
  backToTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }) });
  var whatsappFloat = document.querySelector('.whatsapp-float');
  if (whatsappFloat) {
    window.setTimeout(function () { whatsappFloat.classList.remove('is-intro') }, 3000);
  }
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var revealItems = document.querySelectorAll('.hero-panel,.section-heading,.priority-card,.catalog-card,.advantage-card,.scope-note,.step,.gallery-card,.quote,.faq-panel,.cta-panel');
    revealItems.forEach(function (item) { item.classList.add('reveal-item') });
    document.documentElement.classList.add('js-reveal');
    var revealObserver = new IntersectionObserver(function (entries) { entries.forEach(function (entry) { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); revealObserver.unobserve(entry.target) } }) }, { threshold: .1, rootMargin: '0px 0px -40px 0px' });
    revealItems.forEach(function (item) { revealObserver.observe(item) });
  }
}());
