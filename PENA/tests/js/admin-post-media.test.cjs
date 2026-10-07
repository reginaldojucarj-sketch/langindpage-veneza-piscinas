const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const editorSource = fs.readFileSync(path.join(__dirname, '../../public/admin-post-media.js'), 'utf8');
const librarySource = fs.readFileSync(path.join(__dirname, '../../public/admin-media.js'), 'utf8');

function bus() {
  const names = new Map();
  return class BroadcastChannel {
    constructor(name) {
      this.name = name;
      this.listeners = [];
      if (!names.has(name)) names.set(name, new Set());
      names.get(name).add(this);
    }
    addEventListener(type, callback) { if (type === 'message') this.listeners.push(callback); }
    postMessage(data) {
      for (const peer of names.get(this.name)) {
        if (peer !== this) peer.listeners.forEach((listener) => listener({ data }));
      }
    }
    close() { names.get(this.name).delete(this); }
  };
}

function editor(BroadcastChannel) {
  const listeners = {};
  const link = {
    href: 'https://pena.example.test/admin/media',
    addEventListener(type, callback) { listeners[type] = callback; }
  };
  const media = {
    value: '', options: [{ value: '' }],
    append(option) { this.options.push(option); }, dispatchEvent() {}
  };
  const cover = { value: 'https://pena.example.test/capa-anterior.jpg', dispatchEvent() {} };
  const status = { textContent: '' };
  const pageListeners = {};
  const window = {
    location: new URL('https://pena.example.test/admin/posts/create'),
    crypto: { getRandomValues(bytes) { bytes.forEach((_, index) => { bytes[index] = index + 1; }); return bytes; } },
    addEventListener(type, callback) { pageListeners[type] = callback; }
  };
  vm.runInNewContext(editorSource, {
    URL, BroadcastChannel, Event: class Event {}, Uint8Array,
    document: {
      querySelector: (selector) => ({ '[data-open-media-library]': link, '[data-media-pick-status]': status })[selector],
      getElementById: (id) => ({ media_id: media, cover_url: cover })[id],
      createElement: () => ({ value: '', textContent: '' })
    },
    window
  });
  return { link, media, cover, status, pageListeners, click: () => listeners.click({ button: 0 }) };
}

function library(BroadcastChannel, href, reducedMotion = false) {
  const listeners = {};
  const outputElements = {
    '[data-selection-label]': { textContent: '' },
    '[data-selection-url]': { value: '' },
    '[data-selection-status]': { textContent: '' }
  };
  const output = {
    hidden: true,
    scrollOptions: null,
    scrollIntoView(options) { this.scrollOptions = options; },
    querySelector: (selector) => outputElements[selector]
  };
  const root = {
    addEventListener(type, callback) { listeners[type] = callback; },
    querySelector: (selector) => selector === '[data-selection-output]' ? output : outputElements[selector]
  };
  const events = [];
  vm.runInNewContext(librarySource, {
    URL, URLSearchParams, BroadcastChannel,
    document: { querySelector: () => root, dispatchEvent: (event) => events.push(event) },
    CustomEvent: class CustomEvent { constructor(type, options) { this.type = type; this.detail = options.detail; } },
    window: {
      location: new URL(href),
      addEventListener() {},
      matchMedia: () => ({ matches: reducedMotion })
    },
    navigator: { clipboard: { writeText: async () => {} } }
  });
  return {
    events, output, outputElements,
    select: (kind, id, url, alt = '') => listeners.click({
      target: { closest: (selector) => selector === '[data-use-media]' ? {
        dataset: { mediaKind: kind, mediaId: id, mediaUrl: url, mediaAlt: alt }
      } : null }
    })
  };
}

test('newly uploaded media fills the managed-media field without replacing unsaved text or the legacy URL', () => {
  const BroadcastChannel = bus();
  const form = editor(BroadcastChannel);
  form.click();
  const picker = library(BroadcastChannel, form.link.href, true);
  const id = '12345678-1234-1234-1234-123456789abc';
  picker.select('managed', id, `https://pena.example.test/media/${id}`, 'Nova capa');

  assert.equal(form.media.value, id);
  assert.equal(form.media.options.length, 2, 'a recent upload missing from the original select must be added');
  assert.equal(form.cover.value, 'https://pena.example.test/capa-anterior.jpg');
  assert.match(form.status.textContent, /prioridade/);
  assert.match(picker.outputElements['[data-selection-status]'].textContent, /Capa aplicada no editor/);
  assert.equal(picker.output.scrollOptions.behavior, 'auto');
});

test('legacy selection replaces the cover URL and clears a managed selection', () => {
  const BroadcastChannel = bus();
  const form = editor(BroadcastChannel);
  form.media.value = '12345678-1234-1234-1234-123456789abc';
  form.click();
  const picker = library(BroadcastChannel, form.link.href);
  picker.select('legacy', 'legacy-cover-123', 'https://pena.example.test/old-cover.jpg', 'Antiga');

  assert.equal(form.media.value, '');
  assert.equal(form.cover.value, 'https://pena.example.test/old-cover.jpg');
  assert.match(form.status.textContent, /URL da imagem antiga/);
  assert.equal(picker.output.scrollOptions.behavior, 'smooth');
});

test('invalid or unexpected messages cannot replace a cover', () => {
  const BroadcastChannel = bus();
  const form = editor(BroadcastChannel);
  form.click();
  const token = new URL(form.link.href).searchParams.get('picker');
  const peer = new BroadcastChannel(`pena:media-picker:${token}`);
  peer.postMessage({ type: 'pena:media-selected', kind: 'legacy', url: 'javascript:alert(1)' });
  assert.equal(form.cover.value, 'https://pena.example.test/capa-anterior.jpg');
  peer.postMessage({ type: 'pena:media-selected', kind: 'managed', id: 'not-a-uuid' });
  assert.equal(form.media.value, '');
  assert.match(form.status.textContent, /não é válida/);
});

test('without BroadcastChannel the original link remains a manual new-tab fallback', () => {
  const form = editor(undefined);
  form.click();
  assert.equal(form.link.href, 'https://pena.example.test/admin/media');
  assert.equal(form.status.textContent, '');
});
