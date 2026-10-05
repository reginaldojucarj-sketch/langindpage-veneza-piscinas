(function () {
  'use strict';
  document.documentElement.classList.add('has-js');

  const toggle = document.querySelector('[data-nav-toggle]');
  const nav = document.querySelector('[data-main-nav]');

  if (toggle && nav) {
    function closeMenu() {
      nav.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', function () {
      const open = toggle.getAttribute('aria-expanded') !== 'true';
      nav.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', String(open));
    });

    nav.addEventListener('click', function (event) {
      if (event.target.closest('a')) closeMenu();
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && nav.classList.contains('is-open')) {
        closeMenu();
        toggle.focus();
      }
    });

    document.addEventListener('click', function (event) {
      if (nav.classList.contains('is-open') && !nav.contains(event.target) && !toggle.contains(event.target)) closeMenu();
    });
  }

  const contactForm = document.querySelector('[data-contact-form]');
  if (contactForm) {
    const requestedSubject = new URLSearchParams(window.location.search).get('assunto');
    const subjectField = contactForm.elements.namedItem('assunto');
    if (requestedSubject && subjectField && Array.from(subjectField.options).some(function (option) { return option.value === requestedSubject; })) {
      subjectField.value = requestedSubject;
    }
    contactForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!contactForm.reportValidity()) return;

      const data = new FormData(contactForm);
      const lines = [
        'Olá, Veneza Piscinas! Gostaria de orientação sobre minha piscina.',
        'Nome: ' + String(data.get('nome')).trim(),
        'Tipo de piscina: ' + String(data.get('tipo')).trim(),
        'Assunto: ' + String(data.get('assunto')).trim()
      ];
      const volume = String(data.get('volume') || '').trim();
      const detalhes = String(data.get('detalhes') || '').trim();
      if (volume) lines.push('Volume aproximado: ' + volume + ' m³');
      if (detalhes) lines.push('Detalhes: ' + detalhes);
      window.location.href = 'https://wa.me/5581982983545?text=' + encodeURIComponent(lines.join('\n'));
    });
  }
})();
