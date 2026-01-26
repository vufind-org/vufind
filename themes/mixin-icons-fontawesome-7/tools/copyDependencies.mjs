import { copyFile, cp, mkdir, readFile, writeFile } from 'node:fs/promises';

console.log('Copying dependencies...');
await mkdir('css/vendor/fontawesome-free-6', { recursive: true });

// FontAwesome 7 css
await cp(
    'node_modules/@fortawesome/fontawesome-free/css/.',
    'css/vendor/fontawesome-free-7/css/',
    { recursive: true },
);

// FontAwesome 7 webfonts
await cp(
    'node_modules/@fortawesome/fontawesome-free/webfonts/.',
    'css/vendor/fontawesome-free-7/webfonts/',
    { recursive: true },
);

// FontAwesome 7 license
await copyFile(
    'node_modules/@fortawesome/fontawesome-free/LICENSE.txt',
    'css/vendor/fontawesome-free-7/LICENSE.txt'
);

// FontAwesome 7 VERSION
readFile('node_modules/@fortawesome/fontawesome-free/package.json')
    .then(async (contents) => {
        const pkg = JSON.parse(contents);
        await writeFile('css/vendor/fontawesome-free-7/VERSION.txt', pkg.version);
    });

console.log('Done copying dependencies.');
