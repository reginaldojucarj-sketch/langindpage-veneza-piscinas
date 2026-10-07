const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/content.js'), 'utf8');
const listSource = fs.readFileSync(path.join(__dirname, '../assets/js/knowledge-list.js'), 'utf8');
const configSource = fs.readFileSync(path.join(__dirname, '../assets/js/config.js'), 'utf8');
const local = [{ id: 1, title: 'Cópia antiga', status: 'PP' }];

function service(apiBaseUrl, fetch, posts = local, friendlyArticleBase = '') {
  const window = {
    VENEZA_CONTENT_CONFIG: { apiBaseUrl, friendlyArticleBase },
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
      assert.equal(url, 'https://api.example.com/api/public/posts?per_page=100');
      assert.equal(options.headers.Accept, 'application/json');
      assert.equal(options.credentials, undefined);
      return { ok: true, json: async () => envelope ? { data: local } : local };
    });
    assert.equal((await content.list())[0].id, 1);
  }
});

test('API recolhe todas as páginas de resumos sem exigir o HTML dos artigos', async () => {
  const requested = [];
  const content = service('https://api.example.com', async (url) => {
    requested.push(url);
    const page = url.includes('page=2') ? 2 : 1;
    return {
      ok: true,
      json: async () => page === 1
        ? { data: [{ id: 2, title: 'Segundo', status: 'PP', sort_order: 2 }], meta: { current_page: 1, last_page: 2, snapshot: 'a'.repeat(64) } }
        : { data: [{ id: 1, title: 'Primeiro', status: 'PP', sort_order: 1 }], meta: { last_page: 2, current_page: 2, snapshot: 'a'.repeat(64) } }
    };
  });

  const posts = await content.list();

  assert.deepEqual(requested, [
    'https://api.example.com/api/public/posts?per_page=100',
    'https://api.example.com/api/public/posts?page=2&per_page=100'
  ]);
  assert.deepEqual(Array.from(posts, (post) => post.id), [1, 2]);
  assert.equal(posts.some((post) => Object.hasOwn(post, 'html')), false);
});

test('busca remota consulta o corpo no servidor sem incluí-lo na resposta resumida', async () => {
  const content = service('https://api.example.com', async (url) => {
    assert.equal(url, 'https://api.example.com/api/public/posts?per_page=100&q=energia%20%26%20cloro');
    return {
      ok: true,
      json: async () => ({
        data: [{ id: 8, title: 'Aquecimento', status: 'PP', snippet: 'Conforto térmico' }],
        meta: { current_page: 1, last_page: 1, snapshot: 'c'.repeat(64) }
      })
    };
  });

  const posts = await content.search('energia & cloro');

  assert.equal(posts[0].id, 8);
  assert.equal(Object.hasOwn(posts[0], 'html'), false);
});

test('API discards mixed pages when the publication or editorial snapshot changes mid-load', async () => {
  const content = service('https://api.example.com', async (url) => ({
    ok: true,
    json: async () => url.includes('page=2')
      ? { data: [{ id: 2, status: 'PP' }], meta: { current_page: 2, last_page: 2, snapshot: 'b'.repeat(64) } }
      : { data: [{ id: 1, status: 'PP' }], meta: { current_page: 1, last_page: 2, snapshot: 'a'.repeat(64) } }
  }));

  await assert.rejects(content.list(), /lista de artigos mudou durante o carregamento/i);
});

test('API 409 reports that the published list changed and requests a reload', async () => {
  const content = service('https://api.example.com', async () => ({ status: 409, ok: false }));
  await assert.rejects(content.list(), /lista de artigos mudou durante o carregamento.*recarregue/i);
});

test('snapshot institucional contém apenas IDs publicados únicos', () => {
  const window = {};
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/data/posts-data.js'), 'utf8'), { window });
  assert.equal(window.VENEZA_POSTS.length, 152);
  assert.equal(new Set(window.VENEZA_POSTS.map((post) => post.id)).size, 152);
  assert.ok(window.VENEZA_POSTS.every((post) => post.status === 'PP'));
  assert.ok(window.VENEZA_POSTS.every((post) => /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(post.slug) && post.slug.length <= 200));
  assert.equal(new Set(window.VENEZA_POSTS.map((post) => post.slug)).size, 152);
});

test('origem pública ativa a API e URL amigável apenas no domínio institucional HTTPS', () => {
  for (const [protocol, hostname, active] of [
    ['https:', 'venezapiscinas.com.br', true], ['https:', 'www.venezapiscinas.com.br', true],
    ['http:', 'venezapiscinas.com.br', false], ['https:', 'preview.example.test', false]
  ]) {
    const window = {};
    vm.runInNewContext(configSource, { window, location: { protocol, hostname } });
    assert.equal(Boolean(window.VENEZA_CONTENT_CONFIG.apiBaseUrl), active);
    assert.equal(Boolean(window.VENEZA_CONTENT_CONFIG.friendlyArticleBase), active);
  }
});

test('a rota PHP pode configurar a API de homologação HTTPS sem depender do snapshot', () => {
  const window = {};
  const document = { querySelector: () => ({ content: 'https://api-homolog.example.test' }) };
  vm.runInNewContext(configSource, {
    window, document, location: { protocol: 'https:', hostname: 'site-homolog.example.test' }
  });
  assert.equal(window.VENEZA_CONTENT_CONFIG.apiBaseUrl, 'https://api-homolog.example.test');
  assert.equal(window.VENEZA_CONTENT_CONFIG.friendlyArticleBase, '/conhecimento/');
});

test('links amigáveis exigem slug válido e não dependem do ID', () => {
  const content = service('https://api.example.test', null, local, '/conhecimento/');
  assert.equal(content.articleUrl({ id: 7, slug: 'agua-da-piscina' }), '/conhecimento/agua-da-piscina');
  assert.equal(content.articleUrl({ id: 7, slug: 'https://evil.test/' }), 'artigo.html?id=7');
  assert.equal(content.articleUrl({ id: 7, slug: 'MAIUSCULO' }), 'artigo.html?id=7');
});

test('snapshot local é carregado sob demanda e o modo remoto não o injeta', async () => {
  let loads = 0;
  const window = { VENEZA_CONTENT_CONFIG: { apiBaseUrl: '' } };
  const document = {
    createElement: () => ({}),
    head: { append(script) {
      loads += 1;
      assert.equal(script.src, 'assets/data/posts-data.js');
      window.VENEZA_POSTS = local;
      script.onload();
    } }
  };
  vm.runInNewContext(source, {
    window, document, DOMParser: class { parseFromString() { return { body: { textContent: '' } }; } }, URL, Intl
  });
  assert.equal((await window.VENEZA_CONTENT.list()).length, 1);
  assert.equal((await window.VENEZA_CONTENT.one(1)).id, 1);
  assert.equal(loads, 1);

  const remote = service('https://api.example.test', async () => ({ ok: true, json: async () => ({ data: [] }) }), undefined);
  assert.deepEqual(Array.from(await remote.list()), []);
});

test('a listagem inicial não substitui resultados de uma busca iniciada antes dela terminar', async () => {
  class Element {
    constructor() {
      this.children = [];
      this.listeners = {};
      this.classList = { add() {} };
      this.hidden = false;
      this.value = '';
      this.textContent = '';
    }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren(...nodes) {
      this.children = nodes.flatMap((node) => node instanceof Fragment ? node.children : [node]);
    }
    addEventListener(name, callback) { this.listeners[name] = callback; }
  }
  class Fragment extends Element {}

  const elements = Object.fromEntries([
    'content-grid', 'content-search', 'content-category', 'content-count',
    'content-empty', 'content-more', 'content-featured'
  ].map((id) => [id, new Element()]));
  let resolveList;
  let searches = 0;
  const window = {
    VENEZA_CONTENT: {
      apiEnabled: true,
      list: () => new Promise((resolve) => { resolveList = resolve; }),
      search: async () => {
        searches += 1;
        return [{ id: 2, title: 'Resultado remoto', status: 'PP' }];
      },
      normalize: (value) => String(value).toLowerCase(),
      categories: () => [],
      summary: () => 'Resumo',
      plainText: () => '',
      safeUrl: () => null,
      articleUrl: () => '#artigo',
      dateLabel: () => ''
    },
    setTimeout: (callback) => { callback(); return 1; },
    clearTimeout() {}
  };

  vm.runInNewContext(listSource, {
    window,
    document: {
      getElementById: (id) => elements[id],
      createElement: () => new Element(),
      createDocumentFragment: () => new Fragment()
    },
    console,
    Set
  });

  elements['content-search'].value = 'tratamento';
  elements['content-search'].listeners.input();
  await Promise.resolve();
  resolveList([{ id: 1, title: 'Artigo não correspondente', status: 'PP' }]);
  await Promise.resolve();
  await Promise.resolve();
  await Promise.resolve();

  assert.equal(searches, 2);
  assert.equal(elements['content-grid'].children.length, 1);
  assert.equal(elements['content-grid'].children[0].children[0].children[1].children[0].textContent, 'Resultado remoto');
});
