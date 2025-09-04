import { copyFile, cp, readFile, writeFile } from 'node:fs/promises';

console.log('Copying dependencies...');

// FontAwesome 6 license
await copyFile(
    'node_modules/@fortawesome/fontawesome-free/LICENSE.txt',
    'css/vendor/fontawesome-free-6/LICENSE.txt'
);

// FontAwesome 6 css
await cp(
    'node_modules/@fortawesome/fontawesome-free/css/.',
    'css/vendor/fontawesome-free-6/css/',
    { recursive: true },
);

// FontAwesome 6 webfonts
await cp(
    'node_modules/@fortawesome/fontawesome-free/webfonts/.',
    'css/vendor/fontawesome-free-6/webfonts/',
    { recursive: true },
);

// FontAwesome 6 VERSION
readFile('node_modules/@fortawesome/fontawesome-free/package.json')
    .then(async (contents) => {
        const pkg = JSON.parse(contents);
        await writeFile('css/vendor/fontawesome-free-6/VERSION.txt', pkg.version);
    });

console.log('Done copying dependencies.');
