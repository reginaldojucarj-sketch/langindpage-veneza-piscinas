(() => {
  'use strict';
  const target = document.getElementById('swagger-ui');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!target || !csrf || typeof window.SwaggerUIBundle !== 'function') return;
  window.SwaggerUIBundle({
    url: target.dataset.specUrl,
    dom_id: '#swagger-ui',
    presets: [window.SwaggerUIBundle.presets.apis, window.SwaggerUIStandalonePreset],
    layout: 'StandaloneLayout',
    validatorUrl: 'none',
    withCredentials: true,
    persistAuthorization: false,
    requestInterceptor(request) {
      const url = new URL(request.url, window.location.href);
      if (url.origin !== window.location.origin) throw new Error('A documentação só consulta esta origem.');
      request.headers = request.headers || {};
      request.headers.Accept = 'application/json';
      if (!['GET', 'HEAD', 'OPTIONS'].includes((request.method || 'GET').toUpperCase())) {
        request.headers['X-CSRF-TOKEN'] = csrf;
      }
      return request;
    },
  });
})();
