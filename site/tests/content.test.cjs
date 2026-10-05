const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/content.js'), 'utf8');
const local = [{ id: 1, title: 'Cópia antiga', status: 'PP' }];

function service(apiBaseUrl, fetch) {
  const window = {
    VENEZA_CONTENT_CONFIG: { apiBaseUrl },
    VENEZA_POSTS: local
  };
  const context = {
    window,
    DOMParser: class { parseFromString() { return { body: { textContent: '' } }; } },
    fetch,
    URL,
    Intl,
    console
  };
  vm.runInNewContext(source, context);
  return window.VENEZA_CONTENT;
}

test('sem API configurada usa a cópia local', async () => {
  const content = service('', () => { throw new Error('Não deveria consultar a rede'); });
  assert.equal((await content.list())[0].id, 1);
  assert.equal((await content.one(1)).id, 1);
});

test('artigo retirado da API não reaparece da cópia local', async () => {
  const content = service('https://api.example.com', async () => ({ status: 404 }));
  assert.equal(await content.one(1), null);
});

test('falha da API não aciona a cópia local', async () => {
  const content = service('https://api.example.com', async () => { throw new Error('offline'); });
  await assert.rejects(content.list(), /offline/);
});

test('API só mostra artigos com estado PP', async () => {
  const content = service('https://api.example.com', async () => ({
    ok: true,
    json: async () => ({ data: [
      { id: 2, status: 'PO' },
      { id: 3, status: 'PP' },
      { id: 4, status: 'PE' }
    ] })
  }));
  assert.deepEqual(Array.from(await content.list(), (post) => post.id), [3]);
});
