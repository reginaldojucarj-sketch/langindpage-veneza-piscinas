(function () {
  'use strict';
  const cover = document.querySelector('[data-article-cover]');
  const fallback = document.querySelector('[data-cover-fallback]');
  if (!cover || !fallback) return;
  const unavailable = () => {
    cover.hidden = true;
    fallback.hidden = false;
  };
  cover.addEventListener('error', unavailable);
  if (cover.complete && cover.naturalWidth === 0) unavailable();
})();
