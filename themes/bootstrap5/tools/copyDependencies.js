const { copyFile, cp, stat } = require('node:fs/promises');
const path = require('node:path');

async function copy(fromRelPath, toRelPath) {
	try {
		const fromPath = path.join(process.env.VUFIND_HOME, fromRelPath);
		const toPath = path.join(__dirname, "..", toRelPath);
		console.log(`> ${toRelPath}`);

		const stats = await stat(fromPath);
		if (stats.isDirectory()) {
			await cp(fromPath, toPath, { recursive: true });
		} else {
			await copyFile(fromPath, toPath);
		}
	} catch (e) {
		console.log(`X ${toRelPath} (${e})`);
	}
}

console.log('Copying bootstrap5 dependencies...');

Promise.all([
	// Bootstrap 5
	copy('node_modules/bootstrap/scss/.', 'scss/vendor/bootstrap/scss/'),
	copy('node_modules/bootstrap/dist/js/bootstrap.min.js', 'js/vendor/bootstrap.min.js'),

	// Popper (Bootstrap 5 dependency)
	copy('node_modules/@popperjs/core/dist/umd/popper.min.js', 'js/vendor/popper.min.js'),

	// autocomplete.js
	copy('node_modules/autocomplete.js/autocomplete.js', 'js/vendor/autocomplete.js'),

	// chart.js
	copy('node_modules/chart.js/dist/chart.umd.js', 'js/vendor/chart.js'),

	// jQuery
	copy('node_modules/jquery/dist/jquery.min.js', 'js/vendor/jquery.min.js'),

	// libphonenumber-js
	copy('node_modules/libphonenumber-js/bundle/libphonenumber-js.min.js', 'js/vendor/libphonenumber.js'),
	copy('node_modules/libphonenumber-js/LICENSE', 'js/vendor/libphonenumber-js_LICENSE'),

	// nouislider
	copy('node_modules/nouislider/LICENSE.md', 'js/vendor/nouislider_LICENSE.md'),
	copy('node_modules/nouislider/dist/nouislider.min.js', 'js/vendor/nouislider.min.js'),
	copy('node_modules/nouislider/dist/nouislider.min.css', 'css/vendor/nouislider.min.css'),

	// simple-keyboard
	copy('node_modules/simple-keyboard/build/index.js', 'js/vendor/simple-keyboard/index.js'),
	copy('node_modules/simple-keyboard/build/css/index.css', 'css/vendor/simple-keyboard/index.css'),
	copy('node_modules/simple-keyboard-layouts/build/index.js', 'js/vendor/simple-keyboard-layouts/index.js'),

	// vanilla-cookieconsent
	copy('node_modules/vanilla-cookieconsent/dist/cookieconsent.umd.js', 'js/vendor/cookieconsent.umd.js'),
]).then(() => {
	console.log('= Done copying dependencies.');
});
