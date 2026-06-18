import { execSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

// Cross-compiles push.js into self-contained binaries for Windows + macOS.
// Requires Bun (https://bun.sh). Run from anywhere:  node tools/overlay-push/build.mjs
const here = dirname(fileURLToPath(import.meta.url));
mkdirSync(join(here, 'dist'), { recursive: true });

const targets = [
  ['bun-windows-x64', 'overlay-push-win.exe'],
  ['bun-darwin-arm64', 'overlay-push-mac-arm'],
  ['bun-darwin-x64', 'overlay-push-mac-intel'],
];

for (const [target, out] of targets) {
  console.log(`Building ${out} (${target})…`);
  execSync(`bun build ./push.js --compile --target=${target} --outfile ./dist/${out}`, {
    cwd: here, stdio: 'inherit',
  });
}
console.log('Built into', join(here, 'dist'));
