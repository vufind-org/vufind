import { copyFile } from 'node:fs/promises';

console.log('Copying dependencies...');

// autocomplete.js
await copyFile('node_modules/autocomplete.js/autocomplete.js', 'js/vendor/autocomplete.js');

// chart.js
await copyFile('node_modules/chart.js/dist/chart.umd.js', 'js/vendor/chart.js');

// jQuery
await copyFile('node_modules/jquery/dist/jquery.min.js', 'js/vendor/jquery.min.js');

// simple-keyboard
await copyFile('node_modules/simple-keyboard/build/index.js', 'js/vendor/simple-keyboard/index.js');
await copyFile('node_modules/simple-keyboard/build/css/index.css', 'css/vendor/simple-keyboard/index.css');
await copyFile('node_modules/simple-keyboard-layouts/build/index.js', 'js/vendor/simple-keyboard-layouts/index.js');

// splide
await copyFile('node_modules/@splidejs/splide/dist/js/splide.min.js', 'js/vendor/splide.min.js');
await copyFile('node_modules/@splidejs/splide/dist/css/splide.min.css', 'css/vendor/splide.min.css');
await copyFile('node_modules/@splidejs/splide/dist/js/splide.min.js.map', 'js/vendor/splide.min.js.map');

// vanilla-cookieconsent
await copyFile('node_modules/vanilla-cookieconsent/dist/cookieconsent.umd.js', 'js/vendor/cookieconsent.umd.js');

console.log('Done copying dependencies.');
