const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, 'PA - Site Principal', 'pages', 'public');
const text = new Set();
const placeholders = new Set();
const attrValues = new Set();

function add(value, set) {
  if (!value) return;
  const trimmed = value.trim();
  if (!trimmed) return;
  if (/^\s*$/.test(trimmed)) return;
  if (trimmed.length > 300) return;
  if (/^\$\w+/.test(trimmed)) return;
  set.add(trimmed);
}

function scanFile(filePath) {
  const content = fs.readFileSync(filePath, 'utf8');
  const noPhp = content
    .replace(/<\?[\s\S]*?\?>/g, '')
    .replace(/<script[\s\S]*?<\/script>/gi, '')
    .replace(/<style[\s\S]*?<\/style>/gi, '')
    .replace(/<!--([\s\S]*?)-->/g, '');

  const tagText = [...noPhp.matchAll(/>([^<>]+)</g)].map(m => m[1]);
  tagText.forEach(t => {
    if (/(?:<\?|\?>|\$\w+|\.=|\.\s*htmlspecialchars|function\s*\(|document\.|fetch\(|window\.|\{\s*\$|\$\w+|onclick=|onchange=|onmouseover=|onload=|return\b|if\s*\(|else\b|var\s+|let\s+|const\s+|\[\]|\{\}|;)/.test(t)) return;
    add(t, text);
  });
  const placeholdersFound = [...noPhp.matchAll(/placeholder="([^"]+)"/g)].map(m => m[1]);
  placeholdersFound.forEach(t => {
    if (/(?:<\?|\?>|\$\w+)/.test(t)) return;
    add(t, placeholders);
  });
  const attrMatches = [...noPhp.matchAll(/(?:aria-label|alt|title)="([^"]+)"/g)].map(m => m[1]);
  attrMatches.forEach(t => {
    if (/(?:<\?|\?>|\$\w+)/.test(t)) return;
    add(t, attrValues);
  });
}

function walk(dir) {
  fs.readdirSync(dir, { withFileTypes: true }).forEach(ent => {
    const full = path.join(dir, ent.name);
    if (ent.isDirectory()) return walk(full);
    if (ent.isFile() && full.endsWith('.php')) scanFile(full);
  });
}

walk(root);
const all = Array.from(new Set([...text, ...placeholders, ...attrValues])).sort();
console.log(JSON.stringify(all, null, 2));
