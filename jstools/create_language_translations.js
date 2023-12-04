const codes = require("all-iso-language-codes");
const fs = require("fs");

const args = process.argv.slice(2);
if (args.length < 1) {
    console.info("Usage: node create_language_translations.js [language code]");
    return;
}
const lang = args[0];
const langNative = codes.getNativeName(lang);

let lines = "";
codes.getAll639_3().forEach((code) => {
    const translation = codes.getName(code, lang);
    const engTranslation = codes.getName(code, "en");
    const nativeTranslation = codes.getNativeName(code);
    if (translation != null
        // Filter out English translations unless we're generating the English file:
        && (lang == "en" || translation != engTranslation)
        // Translate out native translations unless it's the native translation of the language we're working on:
        && (translation == langNative || translation != nativeTranslation)
    ) {
        lines += `${code} = "${translation.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"\n`;
    }
});

const home = process.env.VUFIND_HOME;
fs.writeFileSync(`${home}/languages/ISO639-3/${lang}.ini`, lines);
