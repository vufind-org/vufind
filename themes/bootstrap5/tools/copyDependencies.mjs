import { cp } from 'node:fs/promises';
import { copyFile } from 'node:fs/promises';

console.log('Copying dependencies...');

// autocomplete.js
await copyFile('node_modules/autocomplete.js/autocomplete.js', 'js/vendor/autocomplete.js');

// chart.js
await copyFile('node_modules/chart.js/dist/chart.umd.js', 'js/vendor/chart.js');

// simple-keyboard
await copyFile('node_modules/simple-keyboard/build/index.js', 'js/vendor/simple-keyboard/index.js');
await copyFile('node_modules/simple-keyboard/build/css/index.css', 'css/vendor/simple-keyboard/index.css');
await copyFile('node_modules/simple-keyboard-layouts/build/index.js', 'js/vendor/simple-keyboard-layouts/index.js');

// vanilla-cookieconsent
await copyFile('node_modules/vanilla-cookieconsent/dist/cookieconsent.umd.js', 'js/vendor/cookieconsent.umd.js');

// Bootstrap 5
await copyFile('node_modules/bootstrap/dist/js/bootstrap.min.js', 'js/vendor/bootstrap.min.js');
await cp('node_modules/bootstrap/scss/.', 'scss/vendor/bootstrap/', { recursive: true });

// Popper (Bootstrap 5 dependency)
await copyFile('node_modules/@popperjs/core/dist/umd/popper.min.js', 'js/vendor/popper.min.js');

console.log('Done copying dependencies.');
