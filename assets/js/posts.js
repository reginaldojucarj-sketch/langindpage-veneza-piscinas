(() => {
  'use strict';

  const posts = Array.isArray(window.VENEZA_POSTS) ? window.VENEZA_POSTS : [];
  const legacyBase = 'https://pena.venezapiscinas.com.br/';
  const grid = document.getElementById('post-grid');
  const search = document.getElementById('search');
  const category = document.getElementById('category');
  const count = document.getElementById('result-count');
  const empty = document.getElementById('empty');
  const reader = document.getElementById('reader');
  const body = document.getElementById('article-body');
  const cover = document.getElementById('reader-cover');
  const textParser = new DOMParser();

  const plainText = (html) => textParser.parseFromString(html || '', 'text/html').body.textContent.replace(/\s+/g, ' ').trim();
  const normalize = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const dateLabel = (value) => {
    if (!value) return '';
    const date = new Date(value.replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(date);
  };
  const safeURL = (value) => {
    if (!value) return null;
    try {
      const url = new URL(value, legacyBase);
      return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
    } catch { return null; }
  };
  const summary = (post) => plainText(post.description || post.snippet || post.html).slice(0, 185);
  const categoryNames = (post) => [...new Set([post.category, ...(post.categories || '').split(',')].map((name) => name && name.trim()).filter(Boolean))];
  const indexed = posts.map((post) => ({
    post,
    summary: summary(post),
    searchText: normalize([post.title, post.description, post.snippet, post.keywords, post.author, post.category, post.categories, plainText(post.html)].join(' '))
  }));

  [...new Set(posts.flatMap(categoryNames))].sort((a, b) => a.localeCompare(b, 'pt-BR')).forEach((name) => {
    const option = document.createElement('option');
    option.value = name;
    option.textContent = name;
    category.append(option);
  });

  function makeImage(url, alt, className) {
    const image = document.createElement('img');
    image.src = url;
    image.alt = alt;
    image.loading = 'lazy';
    image.decoding = 'async';
    if (className) image.className = className;
    return image;
  }

  function cardFor({ post, summary: excerpt }) {
    const card = document.createElement('article');
    card.className = 'card';
    const imageURL = safeURL(post.image);
    if (imageURL) {
      const media = document.createElement('div');
      media.className = 'card-media';
      const image = makeImage(imageURL, post.image_name || post.title);
      image.addEventListener('error', () => { media.hidden = true; });
      media.append(image);
      card.append(media);
    }

    const content = document.createElement('div');
    content.className = 'card-content';
    const meta = document.createElement('span');
    meta.className = 'card-meta';
    meta.textContent = categoryNames(post).filter((name) => name !== 'ALL').join(' · ') || post.category || 'Artigo';
    const heading = document.createElement('h2');
    const titleLink = document.createElement('a');
    titleLink.href = `#artigo-${post.id}`;
    titleLink.textContent = post.title || `Artigo ${post.id}`;
    heading.append(titleLink);
    const description = document.createElement('p');
    description.textContent = excerpt.length === 185 ? `${excerpt}…` : excerpt;
    const bottom = document.createElement('div');
    bottom.className = 'card-bottom';
    const date = document.createElement('span');
    date.textContent = dateLabel(post.published_at || post.created_at);
    const read = document.createElement('a');
    read.className = 'read-link';
    read.href = titleLink.href;
    read.textContent = 'Ler artigo →';
    bottom.append(date, read);
    content.append(meta, heading, description, bottom);
    card.append(content);
    return card;
  }

  function renderList() {
    const term = normalize(search.value.trim());
    const selectedCategory = category.value;
    const filtered = indexed.filter(({ post, searchText }) =>
      (!term || searchText.includes(term)) && (!selectedCategory || categoryNames(post).includes(selectedCategory))
    );
    const fragment = document.createDocumentFragment();
    filtered.forEach((item) => fragment.append(cardFor(item)));
    grid.replaceChildren(fragment);
    count.textContent = `${filtered.length} ${filtered.length === 1 ? 'artigo encontrado' : 'artigos encontrados'} de ${posts.length}`;
    empty.hidden = filtered.length !== 0;
  }

  const allowedTags = new Set(['p','div','section','br','h2','h3','h4','h5','ul','ol','li','strong','b','em','i','u','blockquote','figure','figcaption','small','sup','sub','hr','pre','code']);
  const blockedTags = new Set(['script','style','form','input','button','object','embed','link','meta','svg','math','textarea','select']);

  function cleanNode(node) {
    if (node.nodeType === Node.TEXT_NODE) return document.createTextNode(node.textContent);
    const result = document.createDocumentFragment();
    if (node.nodeType !== Node.ELEMENT_NODE) return result;
    const tag = node.tagName.toLowerCase();
    if (blockedTags.has(tag)) return result;

    if (tag === 'img') {
      const url = safeURL(node.getAttribute('src'));
      if (!url) return result;
      const image = makeImage(url, node.getAttribute('alt') || 'Imagem do artigo');
      image.addEventListener('error', () => image.remove());
      return image;
    }
    if (tag === 'iframe') {
      const url = safeURL(node.getAttribute('src'));
      if (!url) return result;
      const parsed = new URL(url);
      if ((parsed.hostname === 'www.youtube.com' || parsed.hostname === 'www.youtube-nocookie.com') && parsed.pathname.startsWith('/embed/')) {
        const frame = document.createElement('div');
        frame.className = 'media-frame';
        const iframe = document.createElement('iframe');
        iframe.src = url;
        iframe.title = node.getAttribute('title') || 'Vídeo do artigo';
        iframe.loading = 'lazy';
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        iframe.allowFullscreen = true;
        frame.append(iframe);
        return frame;
      }
      const link = document.createElement('a');
      link.className = 'media-link';
      link.href = url;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      link.textContent = 'Abrir mídia do artigo ↗';
      return link;
    }

    const element = tag === 'a' ? document.createElement('a') : allowedTags.has(tag) ? document.createElement(tag) : result;
    if (tag === 'a') {
      const url = safeURL(node.getAttribute('href'));
      if (url) {
        element.href = url;
        element.target = '_blank';
        element.rel = 'noopener noreferrer';
      }
    }
    node.childNodes.forEach((child) => element.append(cleanNode(child)));
    return element;
  }

  function openPost(post) {
    document.getElementById('reader-title').textContent = post.title || `Artigo ${post.id}`;
    const meta = document.getElementById('reader-meta');
    meta.replaceChildren();
    const parts = [categoryNames(post).filter((name) => name !== 'ALL').join(' · ') || post.category, dateLabel(post.published_at || post.created_at), post.author].filter(Boolean);
    parts.forEach((part) => {
      const span = document.createElement('span');
      span.textContent = part;
      meta.append(span);
    });
    const description = document.getElementById('reader-summary');
    description.textContent = plainText(post.description || post.snippet);
    description.hidden = !description.textContent;

    const imageURL = safeURL(post.image);
    cover.hidden = !imageURL;
    cover.removeAttribute('src');
    if (imageURL) {
      cover.src = imageURL;
      cover.alt = post.image_name || post.title;
    }
    const source = textParser.parseFromString(post.html || '', 'text/html');
    const fragment = document.createDocumentFragment();
    source.body.childNodes.forEach((child) => fragment.append(cleanNode(child)));
    body.replaceChildren(fragment);
    if (!body.textContent.trim() && !body.querySelector('img,iframe')) body.textContent = 'Este registro não possui texto disponível.';
    if (!reader.open) reader.showModal();
    reader.scrollTop = 0;
  }

  function syncReader() {
    const match = /^#artigo-(\d+)$/.exec(window.location.hash);
    const post = match && posts.find((item) => Number(item.id) === Number(match[1]));
    if (post) openPost(post);
    else if (reader.open) reader.close();
  }

  function closeReader() {
    if (reader.open) reader.close();
    if (window.location.hash.startsWith('#artigo-')) history.replaceState(null, '', window.location.pathname + window.location.search);
  }

  search.addEventListener('input', renderList);
  category.addEventListener('change', renderList);
  window.addEventListener('hashchange', syncReader);
  document.getElementById('close-reader').addEventListener('click', closeReader);
  reader.addEventListener('close', closeReader);
  reader.addEventListener('click', (event) => { if (event.target === reader) closeReader(); });
  cover.addEventListener('error', () => { cover.hidden = true; });

  renderList();
  syncReader();
})();
