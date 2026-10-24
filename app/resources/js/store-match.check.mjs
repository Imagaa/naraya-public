// Self-check for store-match.js: node resources/js/store-match.check.mjs
import assert from 'node:assert/strict';
import { storeMatcher } from './store-match.js';

const hits = (q, text) => storeMatcher(q).score(text) > 0;

assert.ok(hits('sejah', 'Kios Sejahtera Bu Wati'), 'prefix');
assert.ok(hits('jahtera', 'Kios Sejahtera'), 'inside a word');
assert.ok(hits('sejatera', 'Kios Sejahtera'), 'one letter missing');
assert.ok(hits('sejahtrea', 'Kios Sejahtera'), 'two letters swapped');
assert.ok(hits('wrung', 'Warung Rejeki'), 'typo in a short word');
assert.ok(hits('kios salak', 'Kios Barokah Jl. Salak No. 1'), 'every word, any field');
assert.ok(hits('jl', 'Jl. Salak'), 'short exact prefix');
assert.ok(!hits('kios mawar', 'Kios Barokah Jl. Salak'), 'a word with no match drops the store');
assert.ok(!hits('xyz', 'Kios Barokah'), 'a short word far from every word');
assert.ok(hits('bu wti', 'Warung Rejeki Bu Wati'), 'a 3-letter word with one typo, against a whole word');
assert.ok(!hits('kis', 'Toko Barokah'), 'a 3-letter word is not matched against word starts with typos');
assert.ok(hits('cafe', 'Café Kopi'), 'accents ignored');

const m = storeMatcher('sejah');
assert.ok(m.score('Toko Sejahtera') > storeMatcher('sejatera').score('Toko Sejahtera'), 'exact ranks above typo');
assert.ok(m.hit('Sejahtera') && !m.hit('Toko'), 'highlights only matching words');

console.log('store-match: ok');
