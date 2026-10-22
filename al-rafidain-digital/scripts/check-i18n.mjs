// Verifies every translatable key in index.html has a Kurdish (ckb) translation and vice versa.
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const html = readFileSync(new URL('../index.html', import.meta.url), 'utf8');
const ctx = { window: {} };
vm.runInNewContext(readFileSync(new URL('../assets/js/i18n.js', import.meta.url), 'utf8'), ctx);
const ckb = ctx.window.RD_I18N.ckb;
const strings = ctx.window.RD_STRINGS;

const keys = new Set([...html.matchAll(/data-i18n="([^"]+)"/g)].map((m) => m[1]));
for (const m of html.matchAll(/data-i18n-attr="([^"]+)"/g)) {
  for (const pair of m[1].split(',')) keys.add(pair.split(':')[1].trim());
}

const missing = [...keys].filter((k) => !(k in ckb));
const unused = Object.keys(ckb).filter((k) => !keys.has(k));
const strMissing = Object.keys(strings.ar).filter((k) => !(k in strings.ckb))
  .concat(Object.keys(strings.ckb).filter((k) => !(k in strings.ar)));

console.log(`${keys.size} DOM keys, ${Object.keys(ckb).length} ckb entries, ${Object.keys(strings.ar).length} runtime strings`);
if (missing.length) console.error('Missing ckb translations:', missing);
if (unused.length) console.error('Unused ckb entries:', unused);
if (strMissing.length) console.error('Runtime strings not in both languages:', strMissing);
process.exit(missing.length || unused.length || strMissing.length ? 1 : 0);
