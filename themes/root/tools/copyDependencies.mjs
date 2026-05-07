import { copyFile, mkdir } from 'node:fs/promises';

await mkdir('js/vendor', { recursive: true });

let buildDepsOnly = false;
process.argv.forEach(arg => {
    if (arg === '--only-build-deps') {
        buildDepsOnly = true;
    }
});

console.log('Copying dependencies...');

if (buildDepsOnly) {
    console.log('Done copying build dependencies.');
    process.exit();
}

// Altcha
await copyFile('node_modules/altcha/LICENSE.txt', 'js/vendor/altcha-LICENSE.txt');
await copyFile('node_modules/altcha/dist/altcha.umd.cjs', 'js/vendor/altcha.js');
await copyFile('node_modules/altcha/dist_i18n/all.umd.cjs', 'js/vendor/altcha-i18n.js');

console.log('Done copying dependencies.');
