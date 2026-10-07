(function () {
  'use strict';
  const service = window.VENEZA_CONTENT;
  const grid = document.getElementById('content-grid');
  if (!service || !grid) return;
  const search = document.getElementById('content-search');
  const category = document.getElementById('content-category');
  const count = document.getElementById('content-count');
  const empty = document.getElementById('content-empty');
  const more = document.getElementById('content-more');
  const featured = document.getElementById('content-featured');
  let allPosts = [];
  let posts = [];
  let remoteSearchActive = false;
  let searchRequest = 0;
  let searchTimer = null;
  let visible = 12;
  const searchIndex = new WeakMap();

  function searchableText(post) {
    if (!searchIndex.has(post)) {
      searchIndex.set(post, service.normalize([
        post.title, post.description, post.snippet, post.keywords, post.author,
        service.plainText(post.html)
      ].join(' ')));
    }
    return searchIndex.get(post);
  }

  function listingChanged(error) {
    return /lista de artigos mudou/i.test(String(error && error.message || ''));
  }

  function card(post, isFeatured) {
    const item = document.createElement('article');
    item.className = isFeatured ? 'content-card content-card--featured' : 'content-card';
    const cover = service.safeUrl(post.image);
    if (cover) {
      const media = document.createElement('a');
      media.className = 'content-card__media';
      media.href = service.articleUrl(post);
      const img = service.image(cover, post.image_name || post.title);
      img.addEventListener('error', () => {
        media.hidden = true;
        if (isFeatured) item.classList.add('content-card--no-media');
      });
      media.append(img);
      item.append(media);
    } else if (isFeatured) item.classList.add('content-card--no-media');
    const content = document.createElement('div');
    content.className = 'content-card__body';
    const meta = document.createElement('span');
    meta.className = 'content-card__meta';
    meta.textContent = (isFeatured ? 'Em destaque · ' : '') + (service.categories(post).join(' · ') || 'Artigo');
    const heading = document.createElement('h3');
    const link = document.createElement('a');
    link.href = service.articleUrl(post);
    link.textContent = post.title || 'Artigo ' + post.id;
    heading.append(link);
    const excerpt = document.createElement('p');
    excerpt.textContent = service.summary(post);
    const bottom = document.createElement('div');
    bottom.className = 'content-card__bottom';
    const date = document.createElement('span');
    date.textContent = service.dateLabel(post);
    const read = document.createElement('a');
    read.href = service.articleUrl(post);
    read.textContent = 'Ler artigo →';
    bottom.append(date, read);
    content.append(meta, heading, excerpt, bottom);
    item.append(content);
    return item;
  }

  function render() {
    const term = service.normalize(search.value.trim());
    const selected = category.value;
    empty.textContent = 'Nenhum artigo encontrado. Tente outra busca ou assunto.';
    const matched = posts.filter((post) => {
      return (remoteSearchActive || !term || searchableText(post).includes(term)) && (!selected || service.categories(post).includes(selected));
    });
    const showFeatured = !term && !selected && matched.length > 0;
    featured.hidden = !showFeatured;
    featured.replaceChildren();
    if (showFeatured) featured.append(card(matched[0], true));
    const listed = showFeatured ? matched.slice(1) : matched;
    const fragment = document.createDocumentFragment();
    listed.slice(0, visible).forEach((post) => fragment.append(card(post, false)));
    grid.replaceChildren(fragment);
    count.textContent = matched.length + (matched.length === 1 ? ' artigo encontrado' : ' artigos encontrados');
    empty.hidden = matched.length !== 0;
    more.hidden = listed.length <= visible;
  }

  function updateSearch() {
    visible = 12;
    const term = search.value.trim();
    const request = ++searchRequest;
    window.clearTimeout(searchTimer);

    if (!service.apiEnabled || !term) {
      remoteSearchActive = false;
      posts = allPosts;
      render();
      return;
    }

    count.textContent = 'Buscando artigos…';
    searchTimer = window.setTimeout(() => {
      service.search(term).then((items) => {
        if (request !== searchRequest) return;
        posts = items;
        remoteSearchActive = true;
        render();
      }).catch((error) => {
        if (request !== searchRequest) return;
        console.error('Não foi possível pesquisar os artigos.', error);
        posts = [];
        remoteSearchActive = true;
        render();
        count.textContent = listingChanged(error) ? 'A lista mudou durante a busca.' : 'Busca temporariamente indisponível.';
        empty.textContent = listingChanged(error) ? 'Recarregue a página para consultar a lista atualizada.' : 'Tente novamente mais tarde.';
      });
    }, 250);
  }

  search.addEventListener('input', updateSearch);
  category.addEventListener('change', () => { visible = 12; render(); });
  more.addEventListener('click', () => { visible += 12; render(); });
  service.list().then((items) => {
    allPosts = items;
    posts = allPosts;
    [...new Set(allPosts.flatMap(service.categories))].sort((a, b) => a.localeCompare(b, 'pt-BR')).forEach((name) => {
      const option = document.createElement('option');
      option.value = name;
      option.textContent = name;
      category.append(option);
    });
    if (service.apiEnabled && search.value.trim()) updateSearch();
    else render();
  }).catch((error) => {
    console.error('Não foi possível carregar os artigos.', error);
    count.textContent = listingChanged(error) ? 'A lista mudou durante o carregamento.' : 'Artigos indisponíveis no momento.';
    empty.textContent = listingChanged(error) ? 'Recarregue a página para consultar a lista atualizada.' : 'Tente novamente mais tarde.';
    empty.hidden = false;
  });
})();
