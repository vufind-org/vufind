import fs from "node:fs";

const jsAppendsRe = new RegExp("appendScriptLink\\('([^']+?)'", "g");
const jsTranslateRe = new RegExp(
	"VuFind\\.translate\\(['\"]([^,\\)]+?)['\"](,|\\))",
	"g",
);
const phpTranslationsRe = new RegExp(
	"\\$this->jsTranslations\\(\\)->addStrings\\(\\s*\\[([^\\]]+?)\\]",
	"gm",
);

function getJsAppends(templateContents) {
	const matches = templateContents.matchAll(jsAppendsRe);
	if (!matches) {
		return [];
	}
	const scripts = [];
	for (const script of matches) {
		const path = script[1];
		if (path.startsWith("vendor") || path.startsWith("http")) {
			continue;
		}
		scripts.push(path);
	}
	return new Set(scripts);
}

function getPhpTranslations(templateContents) {
	const matches = templateContents.matchAll(phpTranslationsRe);
	if (!matches) {
		return new Set([]);
	}
	const strings = [];
	for (const script of matches) {
		const keyMatches = script[0].matchAll(/'([^']+?)' =>/g);
		for (const key of keyMatches) {
			strings.push(key[1]);
		}
	}
	return new Set(strings);
}

function getJsTranslations(contents) {
	const matches = contents.matchAll(jsTranslateRe);
	if (!matches) {
		return new Set([]);
	}
	const strings = [];
	for (const script of matches) {
		strings.push(script[1]);
	}
	return new Set(strings);
}

// main

try {
	let globalPhpStrings = null;
	let templateList = [];
	for (const path of fs.globSync("./**/*.phtml")) {
		const templateContents = fs.readFileSync(path, "utf8");

		const phpStrings = getPhpTranslations(templateContents);
		if (path == "templates/layout/js-translations.phtml") {
			globalPhpStrings = new Set(phpStrings);
			continue;
		}

		const jsFiles = getJsAppends(templateContents);
		let jsStrings = getJsTranslations(templateContents);
		for (const append of jsFiles) {
			const jsPath = `./js/${append}`;
			const jsContents = fs.readFileSync(jsPath, "utf8");
			jsStrings = jsStrings.union(getJsTranslations(jsContents));
		}

		if (jsStrings.size > 0 || phpStrings.size > 0) {
			templateList.push({
				path,
				jsFiles,
				jsStrings,
				phpStrings,
			});
		}
	}

	templateList.sort((a, b) => a.path.localeCompare(b.path));

	function joinSet(set) {
		if (set.size === 0) {
			return "[]";
		}
		return Array.from(set).toSorted().join(", ");
	}

	console.log(
		`global JS strings: ${JSON.stringify(Array.from(globalPhpStrings).toSorted(), null, "\t")}.`,
	);

	const red = (str) => `\x1b[31m${str}\x1b[0m`;
	const blue = (str) => `\x1b[34m${str}\x1b[0m`;

	for (const template of templateList) {
		const neededJsStrings = template.jsStrings.difference(globalPhpStrings);
		const missingPhpStrings = neededJsStrings.difference(
			template.phpStrings,
		);
		const extraPhpStrings = template.phpStrings.difference(neededJsStrings);

		if (missingPhpStrings.size === 0 && extraPhpStrings.size === 0) {
			continue;
		}

		console.log(`\n${template.path}`);
		console.log(`- ${blue("JS files:")} ${joinSet(template.jsFiles)}.`);
		console.log(`- ${blue("JS uses:")} ${joinSet(neededJsStrings)}.`);
		console.log(`- ${blue("PHP-to-JS:")} ${joinSet(template.phpStrings)}.`);
		if (missingPhpStrings.size > 0) {
			console.log(
				`- ${red("Missing PHP strings:")} ${joinSet(missingPhpStrings)}.`,
			);
		}
		if (extraPhpStrings.size > 0) {
			console.log(
				`- ${red("Extra PHP strings:")} ${joinSet(extraPhpStrings)}.`,
			);
		}
	}
} catch (err) {
	console.error("Error globbing synchronously:", err);
}
