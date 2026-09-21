(function () {
  'use strict';

  const filterButtons = document.querySelectorAll('.filter-button');
  const products = document.querySelectorAll('.catalog-card');
  const emptyState = document.querySelector('.catalog-empty');
  const catalogStatus = document.querySelector('.catalog-status');

  if (emptyState && catalogStatus) {
    filterButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        const category = button.dataset.filter;
        let visibleCount = 0;

        filterButtons.forEach(function (item) {
          const isActive = item === button;
          item.classList.toggle('active', isActive);
          item.setAttribute('aria-pressed', String(isActive));
        });

        products.forEach(function (product) {
          const isVisible = category === 'todos' || product.dataset.category === category;
          product.hidden = !isVisible;
          if (isVisible) visibleCount += 1;
        });

        emptyState.hidden = visibleCount !== 0;
        catalogStatus.textContent = visibleCount === 1
          ? '1 produto em exibição'
          : visibleCount + ' produtos em exibição';
      });
    });
  }

  document.querySelectorAll('.faq-question').forEach(function (button) {
    button.addEventListener('click', function () {
      const isExpanded = button.getAttribute('aria-expanded') === 'true';
      const answer = document.getElementById(button.getAttribute('aria-controls'));
      const sign = button.querySelector('.faq-sign');

      if (!answer || !sign) return;

      button.setAttribute('aria-expanded', String(!isExpanded));
      answer.hidden = isExpanded;
      sign.textContent = isExpanded ? '+' : '−';
    });
  });
}());
