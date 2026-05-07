const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, 'PA - Site Principal', 'pages', 'public');
const values = new Set();

function add(value) {
  if (!value) return;
  const trimmed = value.trim();
  if (!trimmed) return;
  if (/^['"\-\s.()]+$/.test(trimmed)) return;
  if (/\$\w+|\.=|\.\s*htmlspecialchars|function\s*\(|document\.|fetch\(|window\.|onclick=|onchange=|onload=|return\b|if\s*\(|else\b|var\s+|let\s+|const\s+|\\n|\\r/.test(trimmed)) return;
  if (/^\s*\{|\}\s*$/.test(trimmed)) return;
  values.add(trimmed);
}

function scanFile(filePath) {
  let content = fs.readFileSync(filePath, 'utf8');
  content = content.replace(/<\?[\s\S]*?\?>/g, '');
  content = content.replace(/<script[\s\S]*?<\/script>/gi, '');
  content = content.replace(/<style[\s\S]*?<\/style>/gi, '');
  content = content.replace(/<!--([\s\S]*?)-->/g, '');

  const tagText = [...content.matchAll(/>([^<>]+)</g)].map(m => m[1]);
  tagText.forEach(add);
  const attrs = [...content.matchAll(/(?:placeholder|aria-label|alt|title)="([^"]+)"/g)].map(m => m[1]);
  attrs.forEach(add);
}

function walk(dir) {
  fs.readdirSync(dir, { withFileTypes: true }).forEach(ent => {
    const full = path.join(dir, ent.name);
    if (ent.isDirectory()) return walk(full);
    if (ent.isFile() && full.endsWith('.php')) scanFile(full);
  });
}

walk(root);
console.log(JSON.stringify(Array.from(values).sort(), null, 2));
