const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const origin = 'https://equipamentos.venezapiscinas.com.br';
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

test('article archive shares its canonical URL, title, description and a local image', () => {
  const posts = read('posts.html');
  assert.ok(posts.includes(`property="og:url" content="${origin}/posts.html"`));
  assert.ok(posts.includes('name="twitter:card" content="summary_large_image"'));
  assert.equal(posts.match(/property="og:title" content="([^"]+)"/)[1], posts.match(/<title>(.+)<\/title>/)[1]);
  assert.equal(posts.match(/property="og:description" content="([^"]+)"/)[1], posts.match(/name="description" content="([^"]+)"/)[1]);
  const image = new URL(posts.match(/property="og:image" content="([^"]+)"/)[1]);
  assert.equal(image.origin, origin);
  assert.ok(fs.statSync(path.join(root, image.pathname)).isFile());
});

test('landing pages allow a keyboard shortcut to main content without inline styles', () => {
  for (const [name, target] of [['index.html', 'inicio'], ['posts.html', 'conteudo'], ['font-showcase.html', 'conteudo']]) {
    const page = read(name);
    assert.match(page, new RegExp(`<body>\\s*<a class="skip-link" href="#${target}"`));
    assert.match(page, new RegExp(`<main[^>]+id="${target}"[^>]+tabindex="-1"`));
    assert.ok(page.includes('href="assets/css/skip-link.css"'));
    assert.doesNotMatch(page, /<style\b|\sstyle=/);
  }
});

test('the landing sends restrictive headers and can compress its text assets', () => {
  const config = read('.htaccess');
  assert.match(config, /<If "%\{HTTP_HOST\} =~ m#\^.+equipamentos\|orcamento/);
  for (const header of ['Strict-Transport-Security', 'X-Frame-Options', 'X-Content-Type-Options', 'Referrer-Policy', 'Content-Security-Policy']) {
    assert.ok(config.includes(`Header always set ${header} "`));
  }
  assert.match(config, /frame-ancestors 'none'; form-action 'none'; script-src 'self'/);
  assert.doesNotMatch(config, /unsafe-inline|includeSubDomains/);
  assert.match(config, /AddOutputFilterByType DEFLATE [^\n]+text\/javascript/);
});

test('all hero and testimonial videos defer loading, preserving format fallbacks and controls', () => {
  const hero = home.match(/<video class="hero-photo"[\s\S]*?<\/video>/g);
  assert.equal(hero.length, 4);
  for (const video of hero) {
    assert.match(video, /preload="none"/);
    assert.match(video, /data-src="[^"]+\.webm"[\s\S]+data-src="[^"]+\.mp4"/);
    assert.doesNotMatch(video, /<source\s+src=/);
  }
  const testimonials = home.match(/<video class="testimonial-video"[^>]+>/g);
  assert.equal(testimonials.length, 5);
  for (const video of testimonials) assert.match(video, /controls playsinline preload="none"/);
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
  assert.ok(workflow.includes('scripts/deploy-servhost-landing.py --stage-pages _pages'));
  assert.ok(workflow.includes("python3 -m unittest discover -s tests -p 'test_landing_deploy.py' -v"));
  assert.ok(workflow.includes('node --test tests/landing-seo.test.cjs'));
  assert.ok(!workflow.includes('cp -R'));
  assert.ok(!workflow.includes('cp .htaccess'));
});

test('local commercial intent is visible without changing product interaction keys', () => {
  assert.match(home, /<h1 id="hero-title">Equipamentos para piscinas em Recife,/);
  assert.ok(home.includes('<h3>Filtros e bombas para piscinas</h3>'));
  assert.ok(home.includes('Atendimento em Recife e região metropolitana'));
  assert.ok(home.includes('data-modal-trigger="filtracao"'));
  assert.ok(home.includes('https://wa.me/5581982983545'));
});

test('migration redirects only landing hosts and preserves equivalent paths', () => {
  const config = read('.htaccess');
  assert.equal((config.match(/\(\?:orcamento\|equipamentos\)/g) || []).length, 2);
  assert.ok(config.includes(`RewriteRule ^ ${origin}%{REQUEST_URI} [R=301,L,NE]`));
  assert.ok(config.includes(`RewriteRule ^index\\.html$ ${origin}/ [R=301,L,NE]`));
  assert.ok(!config.includes('QSD'));
  assert.ok(!config.includes('pena|'));
  assert.ok(read('.github/workflows/deploy-servhost.yml').includes(`url: ${origin}/`));
  assert.ok(read('scripts/deploy-servhost-landing.py').includes(`REMOTE_ROOT = "/public_html/${new URL(origin).hostname}"`));
});
