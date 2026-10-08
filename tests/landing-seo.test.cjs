const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const origin = 'https://orcamento.venezapiscinas.com.br';
const read = (name) => fs.readFileSync(path.join(root, name), 'utf8');
const home = read('index.html');

test('public pages each have one preferred HTTPS canonical', () => {
  for (const [name, suffix] of [['index.html', '/'], ['posts.html', '/posts.html']]) {
    const matches = [...read(name).matchAll(/<link\s+rel="canonical"\s+href="([^"]+)"\s*\/?>/g)];
    assert.equal(matches.length, 1);
    assert.equal(matches[0][1], origin + suffix);
  }
});

test('structured site identity and social metadata use the same origin', () => {
  const block = home.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/);
  assert.ok(block);
  const site = JSON.parse(block[1]);
  assert.equal(site['@context'], 'https://schema.org');
  assert.equal(site['@type'], 'WebSite');
  assert.equal(site.name, 'Veneza Piscinas');
  assert.equal(site.url, origin + '/');
  assert.equal(site.inLanguage, 'pt-BR');
  assert.ok(home.includes(`<meta property="og:url" content="${site.url}">`));
  const image = new URL(home.match(/property="og:image" content="([^"]+)"/)[1]);
  assert.equal(image.origin, origin);
  assert.ok(fs.statSync(path.join(root, image.pathname)).isFile());
  assert.ok(home.includes('name="twitter:card" content="summary_large_image"'));
});

test('sitemap lists only indexable pages and robots advertises it', () => {
  const urls = [...read('sitemap.xml').matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1]);
  assert.deepEqual(urls, [origin + '/', origin + '/posts.html']);
  assert.ok(read('sitemap.xml').includes('xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'));
  assert.ok(read('robots.txt').includes(`Sitemap: ${origin}/sitemap.xml`));
  assert.ok(read('robots.txt').includes('Allow: /'));
  assert.ok(home.includes('content="index, follow, max-image-preview:large"'));
  assert.ok(read('font-showcase.html').includes('content="noindex, follow"'));
});

test('Pages stages discovery files without copying server configuration', () => {
  const workflow = read('.github/workflows/deploy-pages.yml');
  assert.ok(workflow.includes('cp index.html posts.html font-showcase.html robots.txt sitemap.xml _pages/'));
  assert.ok(workflow.includes('node --test tests/landing-seo.test.cjs'));
  assert.ok(!workflow.includes('cp .htaccess'));
});
