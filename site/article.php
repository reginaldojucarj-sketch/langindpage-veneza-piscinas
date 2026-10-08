<?php
declare(strict_types=1);

require __DIR__.'/lib/knowledge.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

$slug = $_GET['slug'] ?? null;
$id = $_GET['id'] ?? null;
$result = ['status' => 404];
if (is_string($slug) && $id === null && veneza_valid_slug($slug)) {
    $result = veneza_fetch_article('slug', $slug);
} elseif (is_string($id) && $slug === null && preg_match('/^[1-9][0-9]*$/D', $id) === 1 && strlen($id) <= 18) {
    $result = veneza_fetch_article('id', $id);
    if ($result['status'] === 200) {
        header('Location: '.veneza_article_url($result['post']['slug']), true, 302);
        exit;
    }
}

$status = $result['status'];
http_response_code($status);
if ($status !== 200) {
    header('X-Robots-Tag: noindex, nofollow');
    if ($status === 503) header('Retry-After: 60');
}
$post = $status === 200 ? $result['post'] : null;
$title = $post === null ? ($status === 503 ? 'Artigo temporariamente indisponível' : 'Artigo não encontrado') : $post['title'];
$description = $post === null ? 'Consulte a Central de Conhecimento da Veneza Piscinas.' : veneza_description($post);
$canonical = $post === null ? null : veneza_article_url($post['slug']);
$cover = $post === null ? null : veneza_safe_url($post['image'] ?? null);
$date = $post === null ? null : veneza_date($post['published_at'] ?? null);
$category = $post === null ? '' : trim((string) ($post['category'] ?? ''));
$author = $post === null ? '' : trim((string) ($post['author'] ?? ''));
$body = $post === null ? '' : veneza_sanitize_html($post['html'] ?? '');
$jsonLd = null;
if ($post !== null) {
    $structured = [
        '@context' => 'https://schema.org', '@type' => 'BlogPosting',
        'headline' => $title, 'mainEntityOfPage' => $canonical,
        'publisher' => ['@type' => 'Organization', 'name' => 'Veneza Piscinas', 'url' => VENEZA_ARTICLE_HOST.'/'],
    ];
    if ($description !== '') $structured['description'] = $description;
    if ($date !== null) $structured['datePublished'] = $date;
    if ($cover !== null) $structured['image'] = $cover;
    $jsonLd = json_encode($structured, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= veneza_escape($title) ?> | Veneza Piscinas</title>
  <meta name="description" content="<?= veneza_escape($description) ?>">
  <?php if ($post === null): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
  <?php if ($canonical !== null): ?>
  <link rel="canonical" href="<?= veneza_escape($canonical) ?>">
  <meta property="og:type" content="article"><meta property="og:site_name" content="Veneza Piscinas">
  <meta property="og:title" content="<?= veneza_escape($title) ?> | Veneza Piscinas">
  <meta property="og:description" content="<?= veneza_escape($description) ?>">
  <meta property="og:url" content="<?= veneza_escape($canonical) ?>">
  <?php if ($cover !== null): ?><meta property="og:image" content="<?= veneza_escape($cover) ?>"><?php endif; ?>
  <?php if ($date !== null): ?><meta property="article:published_time" content="<?= veneza_escape($date) ?>"><?php endif; ?>
  <script type="application/ld+json"><?= $jsonLd ?></script>
  <?php endif; ?>
  <link rel="stylesheet" href="/assets/css/site.css?v=20261008"><script src="/assets/js/site.js" defer></script><script src="/assets/js/ssr-article.js" defer></script>
</head>
<body>
  <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
  <div class="topline"><div class="container"><span>Especialistas em piscinas em Recife e Região Metropolitana</span><a href="tel:+5581982983545">(81) 98298-3545</a></div></div>
  <header class="site-header"><div class="container nav-shell"><a class="brand" href="/index.html" aria-label="Veneza Piscinas — início"><img src="/assets/images/logo-veneza-piscinas.png" width="440" height="129" alt="Veneza Piscinas"></a><button class="nav-toggle" type="button" aria-controls="main-nav" aria-expanded="false" data-nav-toggle>Menu</button><nav class="main-nav" id="main-nav" aria-label="Navegação principal" data-main-nav><a href="/index.html">Início</a><a href="/solucoes.html">Soluções</a><a href="/produtos.html">Produtos</a><a href="/conhecimento/" aria-current="page">Central de Conhecimento</a><a href="/projetos.html">Projetos</a><a href="/veneza.html">A Veneza</a><a class="button button--primary" href="/contato.html">Fale com a Veneza <span aria-hidden="true">↗</span></a></nav></div></header>
  <main id="conteudo"><div class="container"><nav class="breadcrumb content-breadcrumb" aria-label="Caminho da página"><a href="/index.html">Início</a><span aria-hidden="true">/</span><a href="/conhecimento/">Central de Conhecimento</a><span aria-hidden="true">/</span><span><?= veneza_escape($title) ?></span></nav>
    <article class="content-article">
      <?php if ($post !== null): ?>
      <?php if ($category !== ''): ?><span class="eyebrow"><?= veneza_escape($category) ?></span><?php endif; ?>
      <h1><?= veneza_escape($title) ?></h1>
      <?php if ($date !== null || $author !== ''): ?><p class="content-article__details"><?= veneza_escape(implode(' · ', array_filter([$date === null ? '' : (new DateTimeImmutable($date))->format('d/m/Y'), $author]))) ?></p><?php endif; ?>
      <?php if ($description !== ''): ?><p class="lead content-article__summary"><?= veneza_escape($description) ?></p><?php endif; ?>
      <?php if ($cover !== null): ?><img class="content-article__cover" src="<?= veneza_escape($cover) ?>" alt="<?= veneza_escape(($post['image_name'] ?? '') ?: $title) ?>" data-article-cover><p class="content-cover-fallback" hidden data-cover-fallback>Imagem indisponível.</p><?php else: ?><p class="content-cover-fallback">Imagem não disponível para este artigo.</p><?php endif; ?>
      <div class="content-article__body"><?= $body !== '' ? $body : '<p>Este artigo não possui texto disponível.</p>' ?></div>
      <?php else: ?>
      <h1><?= veneza_escape($title) ?></h1>
      <p class="lead content-article__summary"><?= $status === 503 ? 'Não foi possível consultar o conteúdo agora. Tente novamente mais tarde.' : 'Este conteúdo não está disponível.' ?></p>
      <?php endif; ?>
      <div class="content-article__foot"><a class="text-link" href="/conhecimento/">← Voltar à Central de Conhecimento</a><a class="button button--primary" href="/contato.html">Fale com a Veneza</a></div>
    </article></div></main>
  <footer class="site-footer"><div class="container"><div class="footer-grid"><div><a href="/index.html"><img src="/assets/images/logo-veneza-piscinas.png" width="440" height="129" alt="Veneza Piscinas"></a><p>Equipamentos, projeto e orientação técnica para piscinas em Recife e Região Metropolitana.</p></div><div><h2>Explore</h2><ul><li><a href="/solucoes.html">Soluções</a></li><li><a href="/produtos.html">Produtos</a></li><li><a href="/conhecimento/">Central de Conhecimento</a></li><li><a href="/projetos.html">Projetos</a></li><li><a href="/veneza.html">A Veneza</a></li></ul></div><div><h2>Fale com a Veneza</h2><ul><li><a href="/contato.html">Contato e orientação</a></li><li><a href="https://wa.me/5581982983545" target="_blank" rel="noopener noreferrer">WhatsApp</a></li><li><a href="tel:+5581982983545">(81) 98298-3545</a></li><li><a href="mailto:reginaldo.venezapiscinas@gmail.com">E-mail</a></li></ul></div><div><h2>Visite a loja</h2><address>Estr. dos Remédios, 2044<br>Ilha do Retiro, Recife, PE</address><p><a href="https://www.google.com/maps?cid=10536772784314389247" target="_blank" rel="noopener noreferrer">Abrir no Google Maps ↗</a></p></div></div><div class="footer-bottom"><span>© 2026 Veneza Piscinas. Todos os direitos reservados.</span><span>Site institucional em desenvolvimento.</span></div></div></footer>
</body>
</html>
