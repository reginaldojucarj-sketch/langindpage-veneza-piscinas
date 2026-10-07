'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');

for (const relativePath of ['assets/data/posts-data.js', 'site/assets/data/posts-data.js']) {
  test(`${relativePath} contains only published articles`, () => {
    const source = fs.readFileSync(path.join(root, relativePath), 'utf8');
    const match = source.match(/^(?:\/\/ Public PP-only snapshot of POST_pena and its article-related tables\.\r?\n)?window\.VENEZA_POSTS\s*=\s*(\[[\s\S]*\]);\s*$/);
    assert.ok(match, 'snapshot format is invalid');
    const posts = JSON.parse(match[1]);
    assert.ok(posts.length > 0, 'snapshot must not be empty');
    assert.ok(posts.every((post) => post && post.status === 'PP'), 'a non-public article entered the public snapshot');
    assert.equal(new Set(posts.map((post) => post.id)).size, posts.length, 'article IDs must be unique');
  });
}
