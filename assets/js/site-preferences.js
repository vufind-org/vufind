/*global THEME_KEY */

function setSiteTheme(theme) {
	document
		.querySelector('[name="color-scheme"]')
		.setAttribute("content", theme);
	localStorage.setItem(THEME_KEY, theme);
}

function setSiteFont(font) {
	document.body.className = document.body.className.replace(/\s*font-\S+/g, "");
	document.body.classList.add(`font-${font}`);
	localStorage.setItem(FONT_KEY, font);
}

document.addEventListener("DOMContentLoaded", () => {
	const currentTheme = localStorage.getItem(THEME_KEY) ?? "light dark";
	setSiteTheme(currentTheme);
	for (const radio of document.querySelectorAll('[name="site-theme"]')) {
		if (radio.value === currentTheme) {
			radio.checked = true;
		}

		radio.addEventListener("input", () => {
			setSiteTheme(radio.value);
		});
	}

	const currentFont = localStorage.getItem(FONT_KEY) ?? "system";
	setSiteFont(currentFont);
	for (const radio of document.querySelectorAll('[name="site-font"]')) {
		if (radio.value === currentFont) {
			radio.checked = true;
		}

		radio.addEventListener("input", () => {
			setSiteFont(radio.value);
		});
	}
});

/**
 * Form capture
 */
const themeMenu = document.getElementById("theme-menu");
const themeForm = document.getElementById("theme-form");
themeMenu.addEventListener("close", () => {
	const values = new FormData(themeForm);

	localStorage.setItem(THEME_KEY, values.get("site-theme"));
	console.log(`site-theme: ${values.get("site-theme")}`);

	localStorage.setItem(FONT_KEY, values.get("site-font"));
	console.log(`site-font: ${values.get("site-font")}`);
});
