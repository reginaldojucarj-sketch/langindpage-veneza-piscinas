(function () {
  'use strict';

  var filterButtons = document.querySelectorAll('.filter-button');
  var products = document.querySelectorAll('.catalog-card');
  var emptyState = document.querySelector('.catalog-empty');
  var catalogStatus = document.querySelector('.catalog-status');

  filterButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      var category = button.dataset.filter;
      var visibleCount = 0;

      filterButtons.forEach(function (item) {
        var isActive = item === button;
        item.classList.toggle('active', isActive);
        item.setAttribute('aria-pressed', String(isActive));
      });

      products.forEach(function (product) {
        var isVisible = category === 'todos' || product.dataset.category === category;
        product.hidden = !isVisible;
        if (isVisible) visibleCount += 1;
      });

      emptyState.hidden = visibleCount !== 0;
      catalogStatus.textContent = visibleCount === 1
        ? '1 produto em exibição'
        : visibleCount + ' produtos em exibição';
    });
  });

  document.querySelectorAll('.faq-question').forEach(function (button) {
    button.addEventListener('click', function () {
      var isExpanded = button.getAttribute('aria-expanded') === 'true';
      var answer = document.getElementById(button.getAttribute('aria-controls'));
      var sign = button.querySelector('.faq-sign');

      button.setAttribute('aria-expanded', String(!isExpanded));
      answer.hidden = isExpanded;
      sign.textContent = isExpanded ? '+' : '−';
    });
  });
}());
