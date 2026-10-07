import assert from 'node:assert/strict';
import { join } from 'node:path';
import { chromium } from 'playwright-core';

const origin = process.env.PENA_BROWSER_ORIGIN;
if (origin !== 'http://pena-browser-e2e-server:8000') {
  throw new Error('Browser smoke test requires the isolated synthetic server.');
}

const browser = await chromium.launch({ executablePath: '/usr/bin/chromium-browser', args: ['--no-sandbox'], headless: true });
try {
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  const screenshotDir = process.env.PENA_BROWSER_SCREENSHOT_DIR;
  const color = (locator, property) => locator.evaluate((element, name) => getComputedStyle(element)[name], property);
  const checkMobile = async (action, screenshotName) => {
    await page.setViewportSize({ width: 375, height: 812 });
    const layout = await page.evaluate(() => ({
      scrollWidth: document.documentElement.scrollWidth,
      viewportWidth: window.innerWidth,
    }));
    assert.ok(layout.scrollWidth <= layout.viewportWidth, `Unexpected horizontal overflow: ${JSON.stringify(layout)}`);
    assert.equal(await action.isVisible(), true);
    const bounds = await action.boundingBox();
    assert.ok(bounds && bounds.x >= 0 && bounds.x + bounds.width <= 376, `Mobile action is clipped: ${JSON.stringify(bounds)}`);
    if (screenshotDir) await page.screenshot({ path: join(screenshotDir, screenshotName), fullPage: true });
    await page.setViewportSize({ width: 1280, height: 720 });
  };

  await page.goto(`${origin}/docs`);
  await page.waitForURL('**/admin/login');
  assert.equal(await color(page.locator('html'), 'backgroundColor'), 'rgb(247, 243, 252)');
  assert.equal(await color(page.locator('.brand'), 'color'), 'rgb(109, 40, 217)');
  assert.equal(await color(page.getByRole('button', { name: 'Entrar' }), 'backgroundColor'), 'rgb(109, 40, 217)');
  if (screenshotDir) await page.screenshot({ path: join(screenshotDir, 'login.png'), fullPage: true });
  await checkMobile(page.getByRole('button', { name: 'Entrar' }), 'login-mobile.png');
  await page.getByLabel('E-mail').fill('browser@example.test');
  await page.getByLabel('Senha').fill('Synthetic-browser-123!');
  const [loginResponse] = await Promise.all([
    page.waitForResponse((response) => response.request().method() === 'POST' && response.url().endsWith('/admin/login')),
    page.getByRole('button', { name: 'Entrar' }).click(),
  ]);
  try {
    await page.waitForURL('**/docs', { timeout: 15000 });
  } catch (error) {
    const messages = await page.locator('[role="alert"], .alert, .error').allTextContents();
    throw new Error(`Login did not reach Swagger: POST ${loginResponse.status()}, URL ${page.url()}, messages ${JSON.stringify(messages)}`, { cause: error });
  }
  await page.locator('#swagger-ui .opblock').first().waitFor({ timeout: 30000 });
  assert.equal(await page.getByRole('main', { name: 'Documentação interativa da API' }).count(), 1);
  assert.equal(await color(page.locator('.pena-docs-header'), 'backgroundColor'), 'rgb(50, 25, 81)');
  assert.equal(await color(page.locator('.swagger-ui .topbar'), 'backgroundColor'), 'rgb(50, 25, 81)');
  const specResponse = await page.request.get(`${origin}/openapi/admin-v1.json`);
  assert.equal(specResponse.status(), 200);
  const spec = await specResponse.json();
  assert.equal(spec.openapi, '3.0.3');

  const me = page.locator('#swagger-ui .opblock', { has: page.locator('.opblock-summary-path', { hasText: /^\/api\/admin\/v1\/me$/ }) });
  await me.locator('.opblock-summary').click();
  await me.getByRole('button', { name: 'Try it out' }).click();
  assert.equal(await color(me.getByRole('button', { name: 'Execute' }), 'backgroundColor'), 'rgb(109, 40, 217)');
  await me.getByRole('button', { name: 'Execute' }).click();
  const liveResponse = me.locator('.live-responses-table');
  await liveResponse.locator('.response-col_status', { hasText: '200' }).waitFor();
  assert.match(await liveResponse.textContent(), /browser@example\.test/);

  await page.goto(`${origin}/admin`);
  assert.equal(await color(page.locator('html'), 'backgroundColor'), 'rgb(247, 243, 252)');
  assert.equal(await color(page.getByRole('link', { name: /Gerenciar artigos/ }), 'color'), 'rgb(109, 40, 217)');
  const logout = page.getByRole('button', { name: 'Sair' });
  assert.equal(await color(logout, 'backgroundColor'), 'rgb(237, 227, 250)');
  await logout.hover();
  assert.equal(await color(logout, 'backgroundColor'), 'rgb(224, 206, 245)');
  assert.equal(await color(logout, 'color'), 'rgb(40, 24, 59)');
  await page.mouse.move(0, 0);
  if (screenshotDir) await page.screenshot({ path: join(screenshotDir, 'dashboard.png'), fullPage: true });
  await checkMobile(page.getByRole('link', { name: /Gerenciar artigos/ }), 'dashboard-mobile.png');

  await page.goto(`${origin}/admin/posts`);
  const search = page.getByRole('searchbox', { name: 'Buscar título ou URL amigável' });
  const status = page.getByRole('combobox', { name: 'Estado' });
  const filter = page.getByRole('button', { name: 'Filtrar' });
  const [searchBounds, statusBounds, filterBounds] = await Promise.all([
    search.boundingBox(), status.boundingBox(), filter.boundingBox(),
  ]);
  assert.ok(searchBounds && statusBounds && filterBounds);
  assert.ok(Math.abs(searchBounds.y - statusBounds.y) <= 2, 'Desktop filters should share one row.');
  assert.ok(searchBounds.x + searchBounds.width < statusBounds.x, 'Status should follow the search field.');
  assert.ok(statusBounds.x + statusBounds.width < filterBounds.x, 'Filter action should follow the status field.');
  await page.setViewportSize({ width: 320, height: 720 });
  const narrowLayout = await page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    viewportWidth: window.innerWidth,
  }));
  assert.ok(narrowLayout.scrollWidth <= narrowLayout.viewportWidth, `Posts overflow at 320px: ${JSON.stringify(narrowLayout)}`);
  const [narrowSearch, narrowStatus, narrowFilter] = await Promise.all([
    search.boundingBox(), status.boundingBox(), filter.boundingBox(),
  ]);
  assert.ok(narrowSearch && narrowStatus && narrowFilter);
  assert.ok(narrowSearch.y + narrowSearch.height < narrowStatus.y, 'Narrow filters should stack.');
  assert.ok(narrowStatus.y + narrowStatus.height < narrowFilter.y, 'Narrow filter action should follow the fields.');
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.getByRole('link', { name: 'Novo artigo' }).click();
  await page.getByLabel('Título').fill('Artigo E2E seguro');
  const imageData = await page.evaluate(() => {
    const canvas = document.createElement('canvas');
    canvas.width = 40;
    canvas.height = 30;
    const context = canvas.getContext('2d');
    context.fillStyle = '#7c3aed';
    context.fillRect(0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/png').split(',')[1];
  });
  const [mediaPage] = await Promise.all([
    page.waitForEvent('popup'),
    page.getByRole('link', { name: /Abrir biblioteca para enviar ou escolher imagem/ }).click(),
  ]);
  mediaPage.on('pageerror', (error) => errors.push(error.message));
  assert.match(mediaPage.url(), /\?picker=[a-f0-9]{32}$/);
  await mediaPage.getByLabel('Texto alternativo').fill('Capa sintética do artigo E2E');
  await mediaPage.getByLabel('Arquivo').setInputFiles({
    name: 'capa-e2e.png',
    mimeType: 'image/png',
    buffer: Buffer.from(imageData, 'base64'),
  });
  await mediaPage.getByRole('button', { name: 'Enviar e processar imagem' }).click();
  await mediaPage.locator('[data-media-card]').first().waitFor();
  await mediaPage.locator('[data-media-card]').first().getByRole('button', { name: 'Selecionar para artigo' }).click();
  await page.getByRole('status').filter({ hasText: 'Imagem da biblioteca selecionada' }).waitFor();
  assert.match(await page.getByLabel('Capa da biblioteca').inputValue(), /^[0-9a-f-]{36}$/);
  assert.equal(await page.getByLabel('Título').inputValue(), 'Artigo E2E seguro');
  await mediaPage.close();
  await page.getByLabel('Conteúdo HTML').fill('<p>Conteúdo <strong>seguro</strong></p><script>malicioso()</script>');
  await page.getByLabel('Autor').selectOption({ index: 1 });
  await page.getByLabel('Categoria principal').selectOption('1');
  await page.getByRole('checkbox', { name: 'Tratamento' }).check();
  await page.getByRole('button', { name: 'Salvar artigo' }).click();
  await page.waitForURL(/\/admin\/posts\/\d+\/edit$/);
  const postId = Number(new URL(page.url()).pathname.match(/\/posts\/(\d+)\/edit$/)?.[1]);
  assert.ok(Number.isSafeInteger(postId) && postId > 0);
  assert.match(await page.locator('main').textContent(), /Estado atual:\s*PO/);
  assert.equal((await page.request.get(`${origin}/api/public/posts/${postId}`)).status(), 404);

  await page.getByRole('link', { name: 'Ver prévia privada' }).click();
  assert.equal(await page.locator('.post-preview strong').textContent(), 'seguro');
  assert.equal(await page.locator('.post-preview script').count(), 0);
  await page.getByRole('link', { name: 'Voltar ao editor' }).click();
  await page.getByLabel('Título').fill('Artigo E2E revisado');
  await page.getByRole('button', { name: 'Salvar artigo' }).click();
  await page.getByRole('status').filter({ hasText: 'Artigo salvo.' }).waitFor();
  assert.equal(await page.getByLabel('Título').inputValue(), 'Artigo E2E revisado');

  await page.getByRole('button', { name: 'Publicar artigo' }).click();
  await page.waitForURL('**/admin/posts');
  const published = await page.request.get(`${origin}/api/public/posts/${postId}`);
  assert.equal(published.status(), 200);
  assert.equal((await published.json()).data.slug, 'artigo-e2e-seguro');

  await page.goto(`${origin}/admin/posts/${postId}/edit`);
  await page.getByRole('button', { name: 'Ocultar artigo' }).click();
  await page.waitForURL('**/admin/posts');
  assert.equal((await page.request.get(`${origin}/api/public/posts/${postId}`)).status(), 404);

  await page.goto(`${origin}/admin/posts/${postId}/edit`);
  await page.getByRole('checkbox', { name: /Confirmo a exclusão lógica/ }).check();
  await page.getByRole('button', { name: 'Excluir logicamente' }).click();
  await page.waitForURL('**/admin/posts');
  await page.goto(`${origin}/admin/posts/${postId}/edit`);
  assert.match(await page.locator('main').textContent(), /Estado atual:\s*PE/);
  assert.equal((await page.request.get(`${origin}/api/public/posts/${postId}`)).status(), 404);
  assert.deepEqual(errors, []);

  await page.getByRole('button', { name: 'Sair' }).click();
  await page.waitForURL('**/admin/login');
  const loggedOutSpecStatus = await page.evaluate(async (url) => {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    return response.status;
  }, `${origin}/openapi/admin-v1.json`);
  assert.equal(loggedOutSpecStatus, 401);
  console.log('PASS: Chrome headless purple theme, Swagger, cross-tab media selection and full synthetic editorial cycle.');
} finally {
  await browser.close();
}
