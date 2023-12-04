const codes = require("all-iso-language-codes");
const fs = require("fs");

const args = process.argv.slice(2);
if (args.length < 1) {
    console.info("Usage: node create_language_translations.js [language code]");
    return;
}
const lang = args[0];

let lines = "";
codes.getAll639_3().forEach((code) => {
    const translation = codes.getName(code, lang);
    if (translation != null) {
        lines += `${code} = "${translation.replace(/"/g, '\\"')}"\n`;
    }
});

const home = process.env.VUFIND_HOME;
fs.writeFileSync(`${home}/languages/ISO639-3/${lang}.ini`, lines);
