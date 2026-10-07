'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const script = fs.readFileSync(path.join(__dirname, '../../public/api-docs.js'), 'utf8');

test('Swagger UI stays on the current origin and sends CSRF only for mutations', () => {
  let options;
  const bundle = (value) => { options = value; };
  bundle.presets = { apis: {} };
  const context = {
    URL,
    document: {
      getElementById: () => ({ dataset: { specUrl: 'https://api.example.test/openapi/admin-v1.json' } }),
      querySelector: () => ({ content: 'synthetic-csrf' }),
    },
    window: { SwaggerUIBundle: bundle, SwaggerUIStandalonePreset: {}, location: { href: 'https://api.example.test/docs', origin: 'https://api.example.test' } },
  };
  vm.runInNewContext(script, context);
  assert.equal(options.validatorUrl, 'none');
  assert.equal(options.withCredentials, true);
  const get = options.requestInterceptor({ method: 'GET', url: 'https://api.example.test/api/admin/v1/me' });
  assert.equal(get.headers.Accept, 'application/json');
  assert.equal(get.headers['X-CSRF-TOKEN'], undefined);
  const post = options.requestInterceptor({ method: 'POST', url: 'https://api.example.test/api/admin/v1/posts' });
  assert.equal(post.headers['X-CSRF-TOKEN'], 'synthetic-csrf');
  assert.throws(() => options.requestInterceptor({ method: 'GET', url: 'https://other.example.test/openapi.json' }));
});
