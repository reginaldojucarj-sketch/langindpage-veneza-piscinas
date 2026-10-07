(function () {
  'use strict';

  const config = window.VENEZA_CONTENT_CONFIG || {};
  let localPostsPromise;
  const parser = new DOMParser();
  const legacyBase = config.legacyMediaBaseUrl || 'https://pena.venezapiscinas.com.br/';
  const apiBase = String(config.apiBaseUrl || '').replace(/\/+$/, '');
  const friendlyBase = String(config.friendlyArticleBase || '');
  const allowedTags = new Set(['p', 'div', 'section', 'br', 'h2', 'h3', 'h4', 'h5', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 'blockquote', 'figure', 'figcaption', 'small', 'sup', 'sub', 'hr', 'pre', 'code']);
  const blockedTags = new Set(['script', 'style', 'form', 'input', 'button', 'object', 'embed', 'link', 'meta', 'svg', 'math', 'textarea', 'select', 'video', 'audio']);

  function plainText(html) {
    return parser.parseFromString(String(html || ''), 'text/html').body.textContent.replace(/\s+/g, ' ').trim();
  }

  function normalize(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  }

  function safeUrl(value) {
    if (!value) return null;
    try {
      const url = new URL(value, legacyBase);
      return url.protocol === 'https:' || url.protocol === 'http:' ? url.href : null;
    } catch (_) { return null; }
  }

  function categories(post) {
    return [...new Set([post.category, ...String(post.categories || '').split(',')]
      .map((item) => String(item || '').trim())
      .filter((item) => item && item.toUpperCase() !== 'ALL')
      .map((item) => item.replace(/^\+/, '').toLocaleLowerCase('pt-BR'))
      .map((item) => item.charAt(0).toLocaleUpperCase('pt-BR') + item.slice(1)))];
  }

  function summary(post) {
    const value = plainText(post.description || post.snippet || post.html);
    return value.length > 180 ? value.slice(0, 180).trimEnd() + '…' : value;
  }

  function dateValue(post) {
    const value = post.published_at || post.created_at || '';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? 0 : date.getTime();
  }

  function dateLabel(post) {
    const value = dateValue(post);
    return value ? new Intl.DateTimeFormat('pt-BR', { day: 'numeric', month: 'long', year: 'numeric' }).format(value) : '';
  }

  function ordered(posts) {
    return posts.filter((post) => post && post.id != null && post.status === 'PP')
      .sort((a, b) => {
        const aOrder = a.sort_order == null || !Number.isFinite(Number(a.sort_order)) ? Infinity : Number(a.sort_order);
        const bOrder = b.sort_order == null || !Number.isFinite(Number(b.sort_order)) ? Infinity : Number(b.sort_order);
        return aOrder - bOrder || dateValue(b) - dateValue(a) || Number(b.id) - Number(a.id);
      });
  }

  function unpack(payload) {
    return payload && Object.prototype.hasOwnProperty.call(payload, 'data') ? payload.data : payload;
  }

  async function requestApi(path) {
    const response = await fetch(apiBase + path, { headers: { Accept: 'application/json' } });
    if (response.status === 404 || response.status === 410) return null;
    if (response.status === 409) throw new Error('A lista de artigos mudou durante o carregamento. Recarregue e tente novamente.');
    if (!response.ok) throw new Error('API de artigos: HTTP ' + response.status);
    return response.json();
  }

  function localPosts() {
    if (Array.isArray(window.VENEZA_POSTS)) return Promise.resolve(window.VENEZA_POSTS);
    if (localPostsPromise) return localPostsPromise;
    localPostsPromise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'assets/data/posts-data.js';
      script.onload = () => Array.isArray(window.VENEZA_POSTS)
        ? resolve(window.VENEZA_POSTS) : reject(new Error('Cópia local de artigos inválida.'));
      script.onerror = () => reject(new Error('Cópia local de artigos indisponível.'));
      document.head.append(script);
    });
    return localPostsPromise;
  }

  async function fromApi(path) {
    if (!apiBase) return null;
    const payload = await requestApi(path);
    return payload === null ? null : unpack(payload);
  }

  async function fromApiPages(searchTerm = '') {
    const pageSize = 100;
    const searchQuery = searchTerm ? '&q=' + encodeURIComponent(searchTerm) : '';
    const firstPayload = await requestApi('/api/public/posts?per_page=' + pageSize + searchQuery);
    const firstPage = unpack(firstPayload);
    if (!Array.isArray(firstPage)) throw new Error('Resposta inválida da API de artigos.');

    const meta = firstPayload && firstPayload.meta;
    if (!meta) return ordered(firstPage);
    const lastPage = Number(meta.last_page);
    if (!Number.isSafeInteger(lastPage) || lastPage < 1 || Number(meta.current_page) !== 1) {
      throw new Error('Paginação inválida na API de artigos.');
    }
    const snapshot = String(meta.snapshot || '');
    if (!/^[a-f0-9]{64}$/.test(snapshot)) throw new Error('Versão da listagem inválida na API de artigos.');

    const remote = [...firstPage];
    for (let page = 2; page <= lastPage; page += 1) {
      const payload = await requestApi('/api/public/posts?page=' + page + '&per_page=' + pageSize + searchQuery);
      const items = unpack(payload);
      if (!Array.isArray(items) || !payload.meta || payload.meta.snapshot !== snapshot || Number(payload.meta.current_page) !== page) {
        throw new Error('A lista de artigos mudou durante o carregamento. Atualize a página e tente novamente.');
      }
      remote.push(...items);
    }

    return ordered(remote);
  }

  async function list() {
    if (!apiBase) return ordered(await localPosts());
    return fromApiPages();
  }

  async function search(term) {
    const query = String(term || '').trim().slice(0, 150);
    if (!apiBase) return ordered(await localPosts());
    return fromApiPages(query);
  }

  async function one(id) {
    if (!apiBase) return (await localPosts()).find((post) => String(post.id) === String(id) && post.status === 'PP') || null;
    const remote = await fromApi('/api/public/posts/' + encodeURIComponent(id));
    return remote && String(remote.id) === String(id) && remote.status === 'PP' ? remote : null;
  }

  function articleUrl(post) {
    if (apiBase && friendlyBase && typeof post.slug === 'string' && post.slug.length <= 200
      && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(post.slug)) return friendlyBase + post.slug;
    return 'artigo.html?id=' + encodeURIComponent(post.id);
  }

  function image(url, alt) {
    const element = document.createElement('img');
    element.src = url;
    element.alt = alt || '';
    element.loading = 'lazy';
    element.decoding = 'async';
    return element;
  }

  // O HTML legado e o retornado pela API são conteúdo não confiável.
  function cleanNode(node) {
    if (node.nodeType === Node.TEXT_NODE) return document.createTextNode(node.textContent);
    const fragment = document.createDocumentFragment();
    if (node.nodeType !== Node.ELEMENT_NODE) return fragment;
    const tag = node.tagName.toLowerCase();
    if (blockedTags.has(tag)) return fragment;
    if (tag === 'img') {
      const url = safeUrl(node.getAttribute('src'));
      if (!url) return fragment;
      const element = image(url, node.getAttribute('alt') || 'Imagem do artigo');
      element.addEventListener('error', () => element.remove());
      return element;
    }
    if (tag === 'iframe') {
      const url = safeUrl(node.getAttribute('src'));
      if (!url) return fragment;
      const parsed = new URL(url);
      if (['www.youtube.com', 'www.youtube-nocookie.com'].includes(parsed.hostname) && parsed.pathname.startsWith('/embed/')) {
        const frame = document.createElement('div');
        frame.className = 'content-media-frame';
        const element = document.createElement('iframe');
        element.src = url;
        element.title = node.getAttribute('title') || 'Vídeo do artigo';
        element.loading = 'lazy';
        element.referrerPolicy = 'strict-origin-when-cross-origin';
        element.allowFullscreen = true;
        frame.append(element);
        return frame;
      }
      const link = document.createElement('a');
      link.href = url;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      link.textContent = 'Abrir mídia do artigo ↗';
      return link;
    }
    const element = tag === 'a' ? document.createElement('a') : allowedTags.has(tag) ? document.createElement(tag) : fragment;
    if (tag === 'a') {
      const url = safeUrl(node.getAttribute('href'));
      if (url) {
        element.href = url;
        element.target = '_blank';
        element.rel = 'noopener noreferrer';
      }
    }
    node.childNodes.forEach((child) => element.append(cleanNode(child)));
    return element;
  }

  function renderBody(html, target) {
    const source = parser.parseFromString(String(html || ''), 'text/html');
    const fragment = document.createDocumentFragment();
    source.body.childNodes.forEach((child) => fragment.append(cleanNode(child)));
    target.replaceChildren(fragment);
    if (!target.textContent.trim() && !target.querySelector('img, iframe')) target.textContent = 'Este artigo não possui texto disponível.';
  }

  window.VENEZA_CONTENT = { list, search, apiEnabled: Boolean(apiBase), one, categories, summary, normalize, safeUrl, dateLabel, articleUrl, image, renderBody, plainText };
})();
