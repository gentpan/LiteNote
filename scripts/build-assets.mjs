import * as esbuild from 'esbuild';
import { existsSync, readdirSync, statSync } from 'node:fs';
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

function shouldMinify(file) {
  if (!/\.(css|js)$/i.test(file)) {
    return false;
  }
  if (/\.min\.(css|js)$/i.test(file)) {
    return false;
  }
  if (file.includes('/vendor/')) {
    return false;
  }
  return true;
}

const inputs = [];
for (const root of roots) {
  for (const file of walk(root)) {
    if (shouldMinify(file)) {
      inputs.push(file);
    }
  }
}

if (inputs.length === 0) {
  console.warn('no assets to minify');
  process.exit(0);
}

for (const input of inputs) {
  const outfile = input.replace(/\.(css|js)$/i, '.min.$1');
  const isCss = /\.css$/i.test(input);
  const options = {
    entryPoints: [input],
    outfile,
    minify: true,
    bundle: false,
    legalComments: 'none',
  };
  if (!isCss) {
    options.target = ['es2018'];
  }
  await esbuild.build(options);
  console.log(`built ${outfile}`);
}
