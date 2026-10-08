// Run with the existing pena-browser-e2e:ci image (no installs or external network):
// docker run --rm --network none --mount "type=bind,source=<repo>/site,target=/site,readonly" --entrypoint node pena-browser-e2e:ci /site/tests/responsive-smoke.mjs
import assert from 'node:assert/strict';
import http from 'node:http';
import { access, mkdir, readFile } from 'node:fs/promises';
import { extname, resolve } from 'node:path';
import { chromium } from '/tests/node_modules/playwright-core/index.mjs';

const root = '/site';
const widths = [320, 375, 480, 768, 1024, 1440];
const pages = ['index.html', 'solucoes.html', 'produtos.html', 'projetos.html', 'veneza.html', 'contato.html', 'conhecimento.html'];
const screenshots = process.env.SITE_RESPONSIVE_SCREENSHOTS;
const types = { '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.svg': 'image/svg+xml' };
const server = http.createServer(async (request, response) => {
  try {
    const pathname = decodeURIComponent(new URL(request.url, 'http://localhost').pathname);
    const file = resolve(root, '.' + (pathname === '/' ? '/index.html' : pathname));
    if (!file.startsWith(root + '/')) throw new Error('Path outside fixture');
    const body = await readFile(file);
    response.writeHead(200, { 'Content-Type': types[extname(file)] || 'application/octet-stream' });
    response.end(body);
  } catch {
    response.writeHead(404);
    response.end('Missing local fixture');
  }
});

await new Promise((done) => server.listen(0, '127.0.0.1', done));
const origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({ executablePath: '/usr/bin/chromium-browser', headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));
page.on('console', (message) => {
  // Remote fonts and historical article media are deliberately blocked.
  if (message.type() === 'error' && !/Failed to load resource/.test(message.text())) errors.push(message.text());
});
await page.route(/^https?:\/\//, (route) => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());

try {
  if (screenshots) await mkdir(screenshots, { recursive: true });
  await page.goto(`${origin}/conhecimento.html`);
  await page.locator('#content-featured h3 a').waitFor();
  pages.push(await page.locator('#content-featured h3 a').getAttribute('href'));

  for (const file of pages) {
    assert.equal((await page.goto(`${origin}/${file}`)).status(), 200);
    if (file === 'conhecimento.html') await page.locator('#content-grid .content-card').first().waitFor();
    if (file.startsWith('artigo.html')) {
      await page.locator('#article-body').waitFor();
      await page.waitForFunction(() => document.querySelector('#article-body').textContent.trim().length > 0);
    }
    const links = await page.locator('.main-nav a, .footer-grid a').evaluateAll((anchors) => anchors.map((anchor) => anchor.href));
    for (const href of links) {
      const url = new URL(href);
      if (url.origin === origin) await access(resolve(root, '.' + url.pathname));
    }
    const broken = await page.evaluate(async (localOrigin) => {
      const images = [...document.images].filter((image) => image.getAttribute('src') && new URL(image.src).origin === localOrigin);
      images.forEach((image) => { image.loading = 'eager'; });
      await Promise.all(images.map((image) => image.decode().catch(() => {})));
      return images.filter((image) => !image.naturalWidth).map((image) => image.src);
    }, origin);
    assert.deepEqual(broken, [], `${file}: missing local images`);

    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 });
      await page.evaluate(() => scrollTo(0, 0));
      const metrics = await page.evaluate(() => {
        const image = document.querySelector('.footer-grid img');
        const css = getComputedStyle(image), box = image.getBoundingClientRect();
        return {
          overflow: document.documentElement.scrollWidth > innerWidth + 1,
          ratio: (box.width - parseFloat(css.paddingLeft) - parseFloat(css.paddingRight)) / (box.height - parseFloat(css.paddingTop) - parseFloat(css.paddingBottom))
        };
      });
      assert.equal(metrics.overflow, false, `${file}: overflow at ${width}px`);
      assert.ok(Math.abs(metrics.ratio - 440 / 129) < .01, `${file}: distorted footer logo at ${width}px`);
      const toggle = page.locator('[data-nav-toggle]'), nav = page.locator('[data-main-nav]');
      if (width <= 1040) {
        assert.equal(await toggle.isVisible(), true);
        await toggle.focus();
        await page.keyboard.press('Enter');
        assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
        assert.equal(await nav.isVisible(), true);
        await page.keyboard.press('Tab');
        assert.equal(await page.locator('.main-nav a').first().evaluate((anchor) => anchor === document.activeElement), true);
        await page.keyboard.press('Escape');
        assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
        assert.equal(await toggle.evaluate((element) => element === document.activeElement), true);
        await toggle.click();
        await toggle.click();
        assert.equal(await nav.isVisible(), false);
      } else {
        assert.equal(await toggle.isVisible(), false);
        assert.equal(await nav.isVisible(), true);
      }
      if (screenshots && ['index.html', 'contato.html'].includes(file) && [320, 768].includes(width)) {
        await page.screenshot({ path: `${screenshots}/${file.replace('.html', '')}-${width}.png`, fullPage: true });
      }
    }
    console.log(`PASS ${file}: ${widths.length} viewports, menu, logo, links and local images`);
  }

  for (const width of [320, 768]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(`${origin}/contato.html?assunto=Ilumina%C3%A7%C3%A3o%20LED`);
    assert.equal(await page.locator('[name=assunto]').inputValue(), 'Iluminação LED');
    const outside = await page.locator('input, select, textarea, button[type=submit]').evaluateAll((controls) => controls.filter((control) => {
      const box = control.getBoundingClientRect();
      return box.left < 0 || box.right > innerWidth + 1;
    }).map((control) => control.name || control.tagName));
    assert.deepEqual(outside, [], `Contact controls overflow at ${width}px`);
    await page.locator('button[type=submit]').click();
    assert.ok(page.url().startsWith(`${origin}/contato.html`));
    assert.equal(await page.locator('[name=nome]').evaluate((element) => element.validity.valueMissing), true);
    await page.locator('[name=nome]').fill('Teste mobile');
    await page.locator('[name=tipo]').selectOption({ label: 'Residencial' });
    await page.locator('[name=volume]').fill('40');
    await page.locator('[name=detalhes]').fill('Água & iluminação');
    let target = '';
    await page.route('https://wa.me/**', (route) => {
      target = route.request().url();
      return route.fulfill({ status: 200, contentType: 'text/html', body: '<title>Captured WhatsApp request</title>' });
    });
    await Promise.all([page.waitForURL('https://wa.me/**'), page.locator('button[type=submit]').click()]);
    const url = new URL(target), message = url.searchParams.get('text');
    assert.equal(url.pathname, '/5581982983545');
    for (const text of ['Nome: Teste mobile', 'Assunto: Iluminação LED', 'Volume aproximado: 40 m³', 'Detalhes: Água & iluminação']) assert.ok(message.includes(text));
    await page.unroute('https://wa.me/**');
    await page.goto(`${origin}/index.html`);
    await page.locator('[data-nav-toggle]').click();
    await page.locator('.main-nav a[href="contato.html"]').click();
    assert.ok(page.url().endsWith('/contato.html'));
    assert.equal(await page.locator('[data-nav-toggle]').getAttribute('aria-expanded'), 'false');
    console.log(`PASS contact ${width}px: controls, validation, subject, WhatsApp and menu navigation`);
  }
  assert.deepEqual(errors, [], 'JavaScript errors');
  console.log(`PASS: ${pages.length * widths.length} responsive checks; no JavaScript errors or external API access.`);
} finally {
  await browser.close();
  await new Promise((done) => server.close(done));
}
