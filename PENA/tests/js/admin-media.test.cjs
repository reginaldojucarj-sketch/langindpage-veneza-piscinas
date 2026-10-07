const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/admin-media.js'), 'utf8');

function library() {
  const listeners = {};
  const output = {
    hidden: true,
    values: {},
    scrollIntoView() {},
    querySelector(selector) {
      const map = {
        '[data-selection-label]': 'label',
        '[data-selection-url]': 'url',
        '[data-selection-status]': 'status'
      };
      const key = map[selector];
      return key ? elements[key] : null;
    }
  };
  const elements = {
    label: { textContent: '' },
    url: { value: '' },
    status: { textContent: '' }
  };
  const root = {
    addEventListener(type, callback) { listeners[type] = callback; },
    querySelector(selector) {
      if (selector === '[data-media-upload]') return null;
      if (selector === '[data-selection-output]') return output;
      if (selector === '[data-selection-url]') return elements.url;
      if (selector === '[data-selection-status]') return elements.status;
      return null;
    }
  };
  const events = [];
  vm.runInNewContext(source, {
    URL,
    URLSearchParams,
    document: {
      querySelector: () => root,
      dispatchEvent: (event) => events.push(event)
    },
    CustomEvent: class CustomEvent { constructor(type, options) { this.type = type; this.detail = options.detail; } },
    window: { confirm: () => true, location: new URL('https://pena.example.test/admin/media'), matchMedia: () => ({ matches: false }), addEventListener() {} },
    navigator: { clipboard: { writeText: async () => {} } }
  });
  return { root, output, elements, events, click: listeners.click };
}

test('media selection emits a reusable event and exposes only the selected URL and alt text', () => {
  const ui = library();
  const button = { dataset: { mediaKind: 'legacy', mediaId: 'legacy-cover-123', mediaUrl: 'https://pena.example.test/media/123', mediaAlt: 'Piscina pronta' } };
  ui.click({ target: { closest: (selector) => selector === '[data-use-media]' ? button : null } });

  assert.equal(ui.events.length, 1);
  assert.equal(ui.events[0].type, 'pena:media-selected');
  assert.equal(ui.events[0].detail.kind, 'legacy');
  assert.equal(ui.events[0].detail.id, 'legacy-cover-123');
  assert.equal(ui.events[0].detail.url, 'https://pena.example.test/media/123');
  assert.equal(ui.events[0].detail.alt, 'Piscina pronta');
  assert.equal(ui.output.hidden, false);
  assert.equal(ui.elements.url.value, 'https://pena.example.test/media/123');
  assert.equal(ui.elements.label.textContent, 'Piscina pronta');
});

test('canceling media deactivation keeps the confirmation form from submitting', () => {
  const listeners = {};
  const form = {};
  const root = {
    addEventListener(type, callback) { listeners[type] = callback; },
    querySelector: () => null
  };
  vm.runInNewContext(source, {
    URLSearchParams,
    document: { querySelector: () => root, dispatchEvent() {} },
    CustomEvent: class {},
    window: { confirm: () => false, location: new URL('https://pena.example.test/admin/media'), addEventListener() {} },
    navigator: { clipboard: { writeText: async () => {} } }
  });
  let prevented = false;
  listeners.click({
    target: { closest: (selector) => selector === '[data-confirm-deactivate]' ? form : null },
    preventDefault: () => { prevented = true; }
  });
  assert.equal(prevented, true);
});

test('pages without the media library do not throw', () => {
  assert.doesNotThrow(() => vm.runInNewContext(source, {
    URLSearchParams,
    document: { querySelector: () => null },
    CustomEvent: class {},
    window: {},
    navigator: {}
  }));
});
