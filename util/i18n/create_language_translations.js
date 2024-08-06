/**
 * Command-line tool to create language translation filess using the
 * all-iso-language-codes NPM package.
 *
 * Javascript
 *
 * Copyright (C) Villanova University 2023.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Utilities
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/automation Wiki
 */

const childProcess = require("child_process");
const codes = require("all-iso-language-codes");
const fs = require("fs");

const args = process.argv.slice(2);
if (args.length < 1) {
    console.info("Usage: node create_language_translations.js [language code]");
    return;
}

const home = process.env.VUFIND_HOME;

args.forEach(lang => {
    const langNative = codes.getNativeName(lang);

    let lines = "";
    codes.getAll639_3().forEach((code) => {
        const translation = codes.getName(code, lang);
        const engTranslation = codes.getName(code, "en");
        const nativeTranslation = codes.getNativeName(code);
        if (translation != null
            // Filter out English translations unless we're generating the English file:
            && (lang == "en" || translation != engTranslation)
            // Filter out native translations unless it's the native translation of the language we're working on:
            && (translation == langNative || translation != nativeTranslation)
        ) {
            lines += `${code} = "${translation.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"\n`;
        }
    });

    const outfile = `${home}/languages/ISO639-3/${lang}.ini`;
    const existing = fs.existsSync(outfile) ? fs.readFileSync(outfile) : "";
    fs.writeFileSync(`${home}/languages/ISO639-3/${lang}.ini`, lines + "\n" + existing);
});

childProcess.execSync(`php ${home}/public/index.php language normalize languages`);
