/*global __dirname, process, require */
/*eslint-disable no-console -- console needed for CLI output */

const { copyFileSync, cpSync, statSync } = require('node:fs');
const path = require('node:path');

let buildDepsOnly = false;
process.argv.forEach(arg => {
    if (arg === '--only-build-deps') {
        buildDepsOnly = true;
    }
});

/**
 * Helper function to take from root, to theme
 * @param {string} fromRelPath relative path to source
 * @param {string} toRelPath relative path to destination
 */
function copy(fromRelPath, toRelPath) {
  try {
    const fromPath = path.join(process.env.VUFIND_HOME, fromRelPath);
    const toPath = path.join(__dirname, "..", toRelPath);
    console.log(`> ${toRelPath}`);

    const stats = statSync(fromPath);
    if (stats.isDirectory()) {
      cpSync(fromPath, toPath, { recursive: true });
    } else {
      copyFileSync(fromPath, toPath);
    }
  } catch (e) {
    console.log(`X ${toRelPath} (${e})`);
  }
}

console.log('Copying bootstrap5 dependencies...');

// Bootstrap 5 SCSS
copy('node_modules/bootstrap/scss/.', 'scss/vendor/bootstrap/');

if (buildDepsOnly) {
    console.log('= Done copying build dependencies.');
    process.exit();
}

// Bootstrap 5 JS
copy('node_modules/bootstrap/dist/js/bootstrap.min.js', 'js/vendor/bootstrap.min.js');

// Popper (Bootstrap 5 dependency)
copy('node_modules/@popperjs/core/dist/umd/popper.min.js', 'js/vendor/popper.min.js');

// autocomplete.js
copy('node_modules/autocomplete.js/autocomplete.js', 'js/vendor/autocomplete.js');

// chart.js
copy('node_modules/chart.js/dist/chart.umd.js', 'js/vendor/chart.js');

// jQuery
copy('node_modules/jquery/dist/jquery.min.js', 'js/vendor/jquery.min.js');

// libphonenumber-js
copy('node_modules/libphonenumber-js/bundle/libphonenumber-js.min.js', 'js/vendor/libphonenumber.js');
copy('node_modules/libphonenumber-js/LICENSE', 'js/vendor/libphonenumber-js_LICENSE');

// nouislider
copy('node_modules/nouislider/LICENSE.md', 'js/vendor/nouislider_LICENSE.md');
copy('node_modules/nouislider/dist/nouislider.min.js', 'js/vendor/nouislider.min.js');
copy('node_modules/nouislider/dist/nouislider.min.css', 'css/vendor/nouislider.min.css');

// simple-keyboard
copy('node_modules/simple-keyboard/build/index.js', 'js/vendor/simple-keyboard/index.js');
copy('node_modules/simple-keyboard/build/css/index.css', 'css/vendor/simple-keyboard/index.css');
copy('node_modules/simple-keyboard-layouts/build/index.js', 'js/vendor/simple-keyboard-layouts/index.js');

// vanilla-cookieconsent
copy('node_modules/vanilla-cookieconsent/dist/cookieconsent.umd.js', 'js/vendor/cookieconsent.umd.js');

console.log('= Done copying dependencies.');
