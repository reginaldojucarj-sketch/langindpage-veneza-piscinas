const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/content.js'), 'utf8');
const local = [{ id: 1, title: 'Cópia antiga', status: 'PP' }];

function service(apiBaseUrl, fetch, posts = local) {
  const window = {
    VENEZA_CONTENT_CONFIG: { apiBaseUrl },
    VENEZA_POSTS: posts
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

test('410 também retira o artigo sem fallback', async () => {
  const content = service('https://api.example.com', async () => ({ status: 410 }));
  assert.equal(await content.one(1), null);
});

test('respostas inválidas, erro HTTP e JSON quebrado não usam snapshot', async () => {
  for (const response of [
    { status: 503, ok: false },
    { status: 200, ok: true, json: async () => ({ unexpected: [] }) },
    { status: 200, ok: true, json: async () => { throw new Error('JSON inválido'); } }
  ]) {
    await assert.rejects(service('https://api.example.com', async () => response).list());
  }
});

test('detalhe rejeita ID diferente e estado não publicado', async () => {
  for (const data of [{ id: 2, status: 'PP' }, { id: 1, status: 'PO' }, { id: 1, status: 'PE' }, null]) {
    const content = service('https://api.example.com', async () => ({ ok: true, json: async () => ({ data }) }));
    assert.equal(await content.one(1), null);
  }
});

test('ordem editorial prevalece; desempate usa data e ID, sem modificar snapshot', async () => {
  const posts = [
    { id: 1, status: 'PP', created_at: '2026-01-01' },
    { id: 2, status: 'PP', created_at: '2026-01-01' },
    { id: 3, status: 'PP', sort_order: 1 },
    { id: 4, status: 'PO', sort_order: 0 },
    { id: 5, status: 'PP', sort_order: 2 }
  ];
  const before = JSON.stringify(posts);
  const content = service('', null, posts);
  assert.deepEqual(Array.from(await content.list(), (post) => post.id), [3, 5, 2, 1]);
  assert.equal(JSON.stringify(posts), before);
  assert.equal(await content.one(4), null);
});

test('normalização de busca remove acentos e categorias não repetem ALL', () => {
  const content = service('', null);
  assert.equal(content.normalize('ÁGUA e Eficiência'), 'agua e eficiencia');
  assert.deepEqual(Array.from(content.categories({ category: 'ÁGUA', categories: 'água, ALL, +Filtros' })), ['Água', 'Filtros']);
});

test('URLs legadas são resolvidas e protocolos executáveis são rejeitados', () => {
  const content = service('', null);
  assert.equal(content.safeUrl('uploads/capa.jpg'), 'https://pena.venezapiscinas.com.br/uploads/capa.jpg');
  for (const url of ['javascript:alert(1)', 'data:text/html,x', 'file:///etc/passwd', 'vbscript:msgbox(1)']) {
    assert.equal(content.safeUrl(url), null);
  }
  assert.equal(content.articleUrl({ id: '1&other=2' }), 'artigo.html?id=1%26other%3D2');
});

test('API aceita envelope ou array direto e envia Accept JSON sem credenciais', async () => {
  for (const envelope of [true, false]) {
    const content = service('https://api.example.com///', async (url, options) => {
      assert.equal(url, 'https://api.example.com/api/public/posts');
      assert.equal(options.headers.Accept, 'application/json');
      assert.equal(options.credentials, undefined);
      return { ok: true, json: async () => envelope ? { data: local } : local };
    });
    assert.equal((await content.list())[0].id, 1);
  }
});

test('snapshot institucional contém apenas IDs publicados únicos', () => {
  const window = {};
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/data/posts-data.js'), 'utf8'), { window });
  assert.equal(window.VENEZA_POSTS.length, 152);
  assert.equal(new Set(window.VENEZA_POSTS.map((post) => post.id)).size, 152);
  assert.ok(window.VENEZA_POSTS.every((post) => post.status === 'PP'));
});
