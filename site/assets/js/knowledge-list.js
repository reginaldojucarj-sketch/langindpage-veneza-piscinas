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
  let posts = [];
  let visible = 12;

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
    const matched = posts.filter((post) => {
      const text = service.normalize([post.title, post.description, post.snippet, post.keywords, post.author, service.plainText(post.html)].join(' '));
      return (!term || text.includes(term)) && (!selected || service.categories(post).includes(selected));
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

  search.addEventListener('input', () => { visible = 12; render(); });
  category.addEventListener('change', () => { visible = 12; render(); });
  more.addEventListener('click', () => { visible += 12; render(); });
  service.list().then((items) => {
    posts = items;
    [...new Set(posts.flatMap(service.categories))].sort((a, b) => a.localeCompare(b, 'pt-BR')).forEach((name) => {
      const option = document.createElement('option');
      option.value = name;
      option.textContent = name;
      category.append(option);
    });
    render();
  }).catch((error) => {
    console.error('Não foi possível carregar os artigos.', error);
    count.textContent = 'Artigos indisponíveis no momento.';
    empty.textContent = 'Tente novamente mais tarde.';
    empty.hidden = false;
  });
})();
