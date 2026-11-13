class SitePreferences extends HTMLElement {
	constructor() {
		super();
	}

	themeKey() {
		return this.getAttribute("theme-key") ?? "site-permissions__theme";
	}

	fontKey() {
		return this.getAttribute("font-key") ?? "site-permissions__font";
	}

	connectedCallback() {
		const form = document.createElement("form");
		form.setAttribute("popover", "hint");
		form.append(...this.children);

		const button = document.createElement("button");
		button.textContent = "Site Appearance";

		this.append(form);
		this.append(button);

		button.addEventListener("click", () => {
			form.togglePopover();
		});

		form.addEventListener("submit", (e) => {
			form.hidePopover();
			e.preventDefault();
		});

		const themeKey = this.themeKey();
		const fontKey = this.fontKey();
		form.addEventListener("toggle", function savePreferences() {
			const values = new FormData(form);
			console.log(themeKey, fontKey, Array.from(values.keys()));

			localStorage.setItem(themeKey, values.get(themeKey));
			localStorage.setItem(fontKey, values.get(fontKey));

			console.log(`site-theme: ${values.get(themeKey)}`);
			console.log(`site-font: ${values.get(fontKey)}`);
		});

		this.loadPreferences();
	}

	loadPreferences() {
		const currentTheme = localStorage.getItem(this.themeKey()) ?? "light dark";
		this.setColorScheme(currentTheme);
		for (const radio of document.querySelectorAll('[name="site-theme"]')) {
			if (radio.value === currentTheme) {
				radio.checked = true;
			}

			radio.addEventListener("input", () => {
				this.setColorScheme(radio.value);
			});
		}

		const currentFont = localStorage.getItem(this.fontKey()) ?? "system";
		this.setFont(currentFont);
		for (const radio of document.querySelectorAll('[name="site-font"]')) {
			if (radio.value === currentFont) {
				radio.checked = true;
			}

			radio.addEventListener("input", () => {
				this.setFont(radio.value);
			});
		}
	}

	setColorScheme(theme) {
		document.querySelector('[name="color-scheme"]').setAttribute("content", theme);
		localStorage.setItem(this.themeKey(), theme);
	}

	setFont(font) {
		document.body.className = document.body.className.replace(/\s*font-\S+/g, "");
		document.body.classList.add(`font-${font}`);
		localStorage.setItem(this.fontKey(), font);
	}
}

customElements.define("site-preferences", SitePreferences);
