const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/admin-order.js'), 'utf8');

// Minimal DOM double for ordering/focus logic; not a browser accessibility test.
function panel() {
  let click;
  let focused;
  const list = {
    children: [],
    addEventListener(type, callback) { assert.equal(type, 'click'); click = callback; },
    insertBefore(item, before) {
      this.children.splice(this.children.indexOf(item), 1);
      this.children.splice(this.children.indexOf(before), 0, item);
    }
  };
  for (const id of [1, 2, 3]) {
    const item = {
      id,
      get previousElementSibling() { return list.children[list.children.indexOf(this) - 1]; },
      get nextElementSibling() { return list.children[list.children.indexOf(this) + 1]; },
      querySelector(selector) {
        if (selector.includes(':not')) return Object.values(this.buttons).find((button) => !button.disabled);
        return this.buttons[selector.includes('"up"') ? 'up' : 'down'];
      }
    };
    item.buttons = Object.fromEntries(['up', 'down'].map((move) => [move, {
      dataset: { move }, disabled: false,
      closest(selector) { return selector === 'li' ? item : this; },
      focus() { if (!this.disabled) focused = this; }
    }]));
    list.children.push(item);
  }
  vm.runInNewContext(source, { document: { getElementById: () => list } });
  return { list, click: (button) => click({ target: button }), focused: () => focused };
}

test('initial state disables only boundary movements', () => {
  const { list } = panel();
  assert.equal(list.children[0].buttons.up.disabled, true);
  assert.equal(list.children[2].buttons.down.disabled, true);
  assert.equal(list.children[1].buttons.up.disabled, false);
  assert.equal(list.children[1].buttons.down.disabled, false);
});

test('moving to first position retains focus on an enabled control of the moved item', () => {
  const ui = panel();
  const moved = ui.list.children[1];
  ui.click(moved.buttons.up);
  assert.deepEqual(ui.list.children.map((item) => item.id), [2, 1, 3]);
  assert.equal(ui.focused(), moved.buttons.down);
});

test('moving to last position retains focus and the movement is reversible', () => {
  const ui = panel();
  const moved = ui.list.children[1];
  ui.click(moved.buttons.down);
  assert.deepEqual(ui.list.children.map((item) => item.id), [1, 3, 2]);
  assert.equal(ui.focused(), moved.buttons.up);
  ui.click(moved.buttons.up);
  assert.deepEqual(ui.list.children.map((item) => item.id), [1, 2, 3]);
});

test('pages without an order list do not register listeners or fail', () => {
  assert.doesNotThrow(() => vm.runInNewContext(source, { document: { getElementById: () => null } }));
});
