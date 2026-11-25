/* global __dirname, process, require */
/* eslint no-console: "off" -- this is a cli script */

const fs = require("node:fs");
const path = require("node:path");

const themeRoot = path.resolve(__dirname, "..");

const jsAppendsRe = /appendScriptLink\('([^']+?)'/g;
const jsTranslateRe = /VuFind\.(translate|loading)\(['"]([^,)]+?)['"](,|\))/g;
const phpTranslationsRe = /\$this->jsTranslations\(\)->(addStrings|getJSONFromArray)\(\s*\[([^\]]+?)\]/gm;

const verbose = process.argv.includes('--verbose');

let retVal = 0;

/**
 * @param {string} templateContents - PHTML from template
 * @returns {Set<string>} translations used in file
 */
function getJsAppends(templateContents) {
  const matches = templateContents.matchAll(jsAppendsRe);
  if (!matches) {
    return [];
  }
  const scripts = [];
  for (const script of matches) {
    const scriptPath = script[1];
    if (scriptPath.startsWith("vendor") || scriptPath.startsWith("http")) {
      continue;
    }
    scripts.push(scriptPath);
  }
  return new Set(scripts);
}

/**
 * @param {string} templateContents - PHTML from template
 * @returns {Set<string>} translations used in file
 */
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

/**
 * @param {string} contents - JS from js file
 * @returns {Set<string>} translations used in file
 */
function getJsTranslations(contents) {
  const matches = contents.matchAll(jsTranslateRe);
  if (!matches) {
    return new Set([]);
  }
  const strings = [];
  for (const script of matches) {
    strings.push(script[2]);
  }
  return new Set(strings);
}

// Set polyfills

/**
 * @param {Set<string>} a - Set one
 * @param {Set<string>} b - Set two (subtracted from one)
 * @returns {Set<string>} items in a not in b
 */
function setDifference(a, b) {
  const union = new Set(a);
  for (const item of b) {
    union.delete(item);
  }
  return union;
}

/**
 * @param {Set<string>} set Set of strings
 * @param {string?} sep -
 * @returns {string} pretty string of items in Set
 */
function setJoin(set, sep = ", ") {
  if (set.size === 0) {
    return "[]";
  }

  return Array.from(set).toSorted().join(sep);
}

/**
 * @param {Set<string>} a - Set one
 * @param {Set<string>} b - Set two
 * @returns {Set<string>} combined set
 */
function setUnion(a, b) {
  const union = new Set(a);
  for (const item of b) {
    union.add(item);
  }
  return union;
}

// main

try {
  let globalPhpStrings = null;
  let templateList = [];
  const dirContents = fs.readdirSync(themeRoot, { recursive: true, withFileTypes: true });
  for (const entry of dirContents) {
    if (entry.isDirectory() || !entry.name.endsWith("phtml")) {
      continue;
    }

    const entryPath = path.join(entry.parentPath || entry.path, entry.name);
    const templateContents = fs.readFileSync(entryPath, "utf8");

    const phpStrings = getPhpTranslations(templateContents);
    if (entryPath.endsWith("templates/layout/js-translations.phtml")) {
      globalPhpStrings = new Set(phpStrings);
      continue;
    }

    const jsFiles = getJsAppends(templateContents);
    let jsStrings = getJsTranslations(templateContents);
    for (const append of jsFiles) {
      const jsPath = `./js/${append}`;
      const jsContents = fs.readFileSync(path.join(themeRoot, jsPath), "utf8");
      jsStrings = setUnion(jsStrings, getJsTranslations(jsContents));
    }

    if (jsStrings.size > 0 || phpStrings.size > 0) {
      templateList.push({
        path: entryPath,
        jsFiles,
        jsStrings,
        phpStrings,
      });
    }
  }

  // show global translations
  const globalPhpStringsJSON = JSON.stringify(Array.from(globalPhpStrings).toSorted(), null, "\t");
  if (verbose) {
    console.log(`global JS strings: ${globalPhpStringsJSON}.`);
  }

  const red = (str) => `\x1b[31m${str}\x1b[0m`;
  const blue = (str) => `\x1b[34m${str}\x1b[0m`;
  const green = (str) => `\x1b[32m${str}\x1b[0m`;

  // sort for easier finding
  templateList.sort((a, b) => a.path.localeCompare(b.path));

  // compare available and needed strings
  for (const template of templateList) {
    const neededJsStrings = setDifference(template.jsStrings, globalPhpStrings);
    const missingPhpStrings = setDifference(neededJsStrings, template.phpStrings);
    const extraPhpStrings = setDifference(template.phpStrings, neededJsStrings);

    if (missingPhpStrings.size === 0 && extraPhpStrings.size === 0) {
      continue;
    }

    retVal = 1;

    console.log(`\n${template.path}`);
    console.log(`- ${blue("JS files:")} ${setJoin(template.jsFiles)}.`);
    console.log(`- ${blue("JS uses:")} ${setJoin(neededJsStrings)}.`);
    console.log(`- ${blue("PHP-to-JS:")} ${setJoin(template.phpStrings)}.`);
    if (missingPhpStrings.size > 0) {
      console.log(`- ${red("Missing PHP strings:")} ${setJoin(missingPhpStrings)}.`);
    }
    if (extraPhpStrings.size > 0) {
      console.log(`- ${red("Extra PHP strings:")} ${setJoin(extraPhpStrings)}.`);
    }
  }

  if (retVal === 0) {
    console.log(green("No issues found."));
  }
} catch (err) {
  console.error("Error globbing synchronously:", err);
  retVal = 1;
}

process.exit(retVal);
