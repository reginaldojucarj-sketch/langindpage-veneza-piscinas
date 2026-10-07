(function () {
  'use strict';
  const service = window.VENEZA_CONTENT;
  const target = document.getElementById('content-article');
  if (!service || !target) return;
  const id = new URLSearchParams(location.search).get('id');
  const setMeta = (name, value, property) => {
    const attribute = property ? 'property' : 'name';
    let tag = document.querySelector('meta[' + attribute + '="' + name + '"]');
    if (!tag) {
      tag = document.createElement('meta');
      tag.setAttribute(attribute, name);
      document.head.append(tag);
    }
    tag.content = value;
  };
  service.one(id).then((post) => {
    if (!post) {
      const title = document.createElement('h1');
      title.textContent = 'Artigo não encontrado';
      const message = document.createElement('p');
      message.textContent = 'Este conteúdo não está disponível.';
      const link = document.createElement('a');
      link.className = 'text-link';
      link.href = 'conhecimento.html';
      link.textContent = 'Voltar à Central de Conhecimento →';
      target.replaceChildren(title, message, link);
      document.title = 'Artigo não encontrado | Veneza Piscinas';
      return;
    }
    const title = post.title || 'Artigo ' + post.id;
    const description = service.summary(post);
    document.title = title + ' | Veneza Piscinas';
    setMeta('description', description);
    setMeta('og:title', document.title, true);
    setMeta('og:description', description, true);
    setMeta('og:url', location.href, true);
    const coverUrl = service.safeUrl(post.image);
    if (coverUrl) setMeta('og:image', coverUrl, true);
    document.getElementById('article-breadcrumb').textContent = title;
    document.getElementById('article-category').textContent = service.categories(post).join(' · ') || 'Artigo';
    document.getElementById('article-title').textContent = title;
    const details = [service.dateLabel(post), post.author].filter(Boolean);
    document.getElementById('article-details').textContent = details.join(' · ');
    const intro = document.getElementById('article-summary');
    intro.textContent = service.plainText(post.description || post.snippet);
    intro.hidden = !intro.textContent;
    const cover = document.getElementById('article-cover');
    if (coverUrl) {
      cover.src = coverUrl;
      cover.alt = post.image_name || title;
      cover.hidden = false;
      cover.addEventListener('error', () => { cover.hidden = true; });
    }
    service.renderBody(post.html, document.getElementById('article-body'));
  }).catch((error) => {
    console.error('Não foi possível carregar o artigo.', error);
    const title = document.createElement('h1');
    title.textContent = 'Artigo temporariamente indisponível';
    const message = document.createElement('p');
    message.textContent = 'Tente novamente mais tarde.';
    target.replaceChildren(title, message);
    document.title = 'Artigo indisponível | Veneza Piscinas';
  });
})();
