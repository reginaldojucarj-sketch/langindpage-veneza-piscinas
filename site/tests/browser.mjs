import assert from 'node:assert/strict';
import { chromium } from '/tests/node_modules/playwright-core/index.mjs';

const origin = process.env.SITE_E2E_ORIGIN;
if (origin !== 'http://pena-site-e2e-server:8092') {
  throw new Error('The site browser test requires its isolated synthetic server.');
}
const remoteOrigin = process.env.SITE_E2E_REMOTE_ORIGIN;
if (remoteOrigin !== 'http://pena-site-e2e-server:8093') {
  throw new Error('The remote API browser test requires its isolated synthetic server.');
}

const browser = await chromium.launch({ executablePath: '/usr/bin/chromium-browser', args: ['--no-sandbox'], headless: true });
try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.route(/^https:\/\//, (route) => route.abort());

  const listing = await page.goto(`${origin}/conhecimento/`);
  assert.equal(listing.status(), 200);
  await page.locator('#content-featured h3').waitFor();
  assert.equal(await page.locator('#content-featured .content-card').count(), 1);
  const featuredTitle = await page.locator('#content-featured h3').textContent();
  assert.equal((await page.locator('#content-grid h3').allTextContents()).includes(featuredTitle), false);
  await page.keyboard.press('Tab');
  assert.equal(await page.evaluate(() => document.activeElement.classList.contains('skip-link')), true);
  await page.getByLabel('Buscar artigos').fill('ÁGUA');
  assert.match(await page.locator('#content-count').textContent(), /artigo/);
  assert.ok(await page.locator('#content-grid .content-card').count() > 0);
  await page.getByLabel('Buscar artigos').fill('');
  const options = await page.locator('#content-category option').allTextContents();
  assert.ok(options.length > 1);
  await page.locator('#content-category').selectOption({ index: 1 });
  assert.ok(await page.locator('#content-grid .content-card').count() > 0);
  await page.locator('#content-category').selectOption('');
  for (const width of [320, 480, 1440]) {
    await page.setViewportSize({ width, height: 800 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), true, `Overflow at ${width}px`);
  }

  const article = await page.goto(`${origin}/conhecimento/agua-da-piscina`);
  assert.equal(article.status(), 200);
  assert.equal(await page.title(), 'Água & sol | Veneza Piscinas');
  assert.equal(await page.locator('link[rel="canonical"]').getAttribute('href'), 'https://venezapiscinas.com.br/conhecimento/agua-da-piscina');
  assert.equal(await page.locator('meta[property="og:url"]').getAttribute('content'), 'https://venezapiscinas.com.br/conhecimento/agua-da-piscina');
  assert.equal(await page.locator('article h1').textContent(), 'Água & sol');
  assert.equal(await page.locator('.content-article__body strong').textContent(), 'seguro');
  assert.equal(await page.locator('.content-article__body iframe').count(), 1);
  assert.equal((await page.content()).includes('malicioso()'), false);
  await page.locator('[data-cover-fallback]').waitFor({ state: 'visible' });
  for (const width of [320, 480, 1440]) {
    await page.setViewportSize({ width, height: 800 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), true, `Article overflow at ${width}px`);
  }

  assert.equal((await page.goto(`${origin}/conhecimento/retirado`)).status(), 404);
  assert.equal((await page.goto(`${origin}/conhecimento/indisponivel`)).status(), 503);
  assert.deepEqual(errors, []);
  console.log('PASS: Chromium listing, search, featured card, keyboard, responsive article, sanitizer, media fallback and HTTP errors.');

  // The second PHP server injects a real HTTPS API origin into config.js. Only
  // browser requests to that synthetic origin are fulfilled; no network API or
  // local article snapshot is available to the remote-mode assertions.
  const remotePage = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const remoteErrors = [];
  const apiRequests = [];
  const snapshotRequests = [];
  let apiMode = 'ok';
  const snapshot = 'a'.repeat(64);
  const remotePosts = Array.from({ length: 15 }, (_, index) => ({
    id: index + 1,
    title: index === 0 ? 'Artigo remoto em destaque' : `Artigo remoto ${index + 1}`,
    slug: `artigo-remoto-${index + 1}`,
    status: 'PP',
    sort_order: index + 1,
    category: 'Tratamento',
    description: 'Resumo recebido da API.',
    published_at: '2026-01-02 10:30:00'
  }));
  remotePage.on('pageerror', (error) => remoteErrors.push(error.message));
  await remotePage.route('**/assets/data/posts-data.js', (route) => {
    snapshotRequests.push(route.request().url());
    return route.abort();
  });
  await remotePage.route(/^https:\/\//, (route) => {
    const url = new URL(route.request().url());
    if (url.origin !== 'https://api-homolog.example.test') return route.abort();
    apiRequests.push(url.href);
    if (url.pathname !== '/api/public/posts') return route.abort();
    const headers = { 'Access-Control-Allow-Origin': '*', 'Content-Type': 'application/json; charset=utf-8' };
    if (apiMode === 'error') return route.fulfill({ status: 503, headers, body: '{}' });
    const searchTerm = url.searchParams.get('q');
    const pageNumber = Number(url.searchParams.get('page') || 1);
    const posts = searchTerm
      ? [{ id: 99, title: 'Filtros com eficiencia', slug: 'filtros-com-eficiencia', status: 'PP', category: 'Equipamentos' }]
      : pageNumber === 1 ? remotePosts.slice(0, 13) : remotePosts.slice(13);
    const currentSnapshot = apiMode === 'changed' && pageNumber === 2 ? 'b'.repeat(64) : snapshot;
    return route.fulfill({ status: 200, headers, body: JSON.stringify({
      data: posts,
      meta: { current_page: pageNumber, last_page: searchTerm ? 1 : 2, snapshot: currentSnapshot }
    }) });
  });

  assert.equal((await remotePage.goto(`${remoteOrigin}/conhecimento/`)).status(), 200);
  assert.equal(await remotePage.locator('meta[name="veneza-public-api-origin"]').getAttribute('content'), 'https://api-homolog.example.test');
  assert.equal(await remotePage.evaluate(() => window.VENEZA_CONTENT_CONFIG.apiBaseUrl), 'https://api-homolog.example.test');
  assert.equal(await remotePage.evaluate(() => window.VENEZA_CONTENT.apiEnabled), true);
  await remotePage.getByText('15 artigos encontrados').waitFor();
  assert.equal(await remotePage.locator('#content-featured h3').textContent(), 'Artigo remoto em destaque');
  assert.equal(await remotePage.locator('#content-grid .content-card').count(), 12);
  assert.equal(await remotePage.locator('#content-more').isVisible(), true);
  await remotePage.locator('#content-more').click();
  assert.equal(await remotePage.locator('#content-grid .content-card').count(), 14);
  assert.equal(await remotePage.locator('#content-featured a').first().getAttribute('href'), '/conhecimento/artigo-remoto-1');
  assert.ok(apiRequests.some((url) => url.includes('/api/public/posts?per_page=100')));
  assert.ok(apiRequests.some((url) => url.includes('/api/public/posts?page=2&per_page=100')));

  await remotePage.getByLabel('Buscar artigos').fill('filtros');
  await remotePage.getByText('1 artigo encontrado').waitFor();
  assert.equal(await remotePage.locator('#content-grid h3').textContent(), 'Filtros com eficiencia');
  assert.equal(await remotePage.locator('#content-featured').isVisible(), false);
  assert.ok(apiRequests.some((url) => url.includes('/api/public/posts?per_page=100&q=filtros')));
  assert.deepEqual(snapshotRequests, []);

  await remotePage.getByLabel('Buscar artigos').fill('');
  await remotePage.getByText('15 artigos encontrados').waitFor();
  apiMode = 'error';
  await remotePage.reload();
  await remotePage.getByText('Artigos indisponíveis no momento.').waitFor();
  assert.equal(await remotePage.locator('#content-grid .content-card').count(), 0);
  assert.deepEqual(snapshotRequests, []);

  apiMode = 'changed';
  await remotePage.reload();
  await remotePage.getByText('A lista mudou durante o carregamento.').waitFor();
  assert.equal(await remotePage.locator('#content-grid .content-card').count(), 0);
  assert.deepEqual(snapshotRequests, []);
  assert.deepEqual(remoteErrors, []);
  console.log('PASS: Chromium remote HTTPS API config, pagination, search, failures and no snapshot fallback.');
} finally {
  await browser.close();
}
