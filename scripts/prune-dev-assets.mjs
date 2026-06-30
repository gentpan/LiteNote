import { existsSync, readdirSync, statSync, unlinkSync } from 'node:fs';
import { join } from 'node:path';

const roots = ['themes', 'admin/assets', 'plugins'];

function walk(dir, files = []) {
  if (!existsSync(dir)) {
    return files;
  }
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      walk(full, files);
    } else {
      files.push(full);
    }
  }
  return files;
}

function shouldPrune(file) {
  if (!/\.(css|js)$/i.test(file)) {
    return false;
  }
  if (/\.min\.(css|js)$/i.test(file)) {
    return false;
  }
  if (file.includes('/vendor/')) {
    return false;
  }
  const min = file.replace(/\.(css|js)$/i, '.min.$1');
  return existsSync(min);
}

let removed = 0;
for (const root of roots) {
  for (const file of walk(root)) {
    if (!shouldPrune(file)) {
      continue;
    }
    unlinkSync(file);
    removed += 1;
    console.log(`removed ${file}`);
  }
}

console.log(`pruned ${removed} source asset(s)`);
