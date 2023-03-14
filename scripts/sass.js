/// <reference types="node" />

// #TODO: exclude/ignore SCSS files

const fs = require("node:fs/promises");
const { readdirSync } = require("node:fs");
const path = require("node:path");
const { performance } = require("node:perf_hooks");
const commander = require("commander");
const sass = require("sass");

commander
  .version("9.1", "-v, --version")
  .usage("[OPTIONS]...")
  .option(
    "-m, --mode <mode>",
    "Compilation mode (production/development).",
    "development"
  )
  .argument("[themes...]")
  .action((_themes) => {
    const themes =
      _themes.length === 0 ? readdirSync(path.resolve("themes")) : _themes;

    Promise.all(themes.map(compileTheme)).catch((error) => {
      console.error(error);
    });
  })
  .parse(process.argv);

/**
 * [getLoadPaths description]
 * @param  {string} theme path name of theme (themes/THISPART/js/...)
 * @return {Promise<Array<string>>}
 */
async function getLoadPaths(theme) {
  // initialize search path with directory containing LESS file
  let loadPaths = new Set();
  let queue = [theme];

  // Iterate through theme.config.php files collecting parent themes in search path:
  while (queue.length > 0) {
    const theme = queue.pop();
    let config = await fs.readFile(`themes/${theme}/theme.config.php`, {
      encoding: "utf8",
    });

    if (!config) {
      config = await fs.readFile(`themes/${theme}/mixin.config.php`, {
        encoding: "utf8",
      });
    }

    // First identify mixins:
    const mixinMatches = config.match(/['"]mixins['"]\s*=>\s*\[([^\]]+)\]/);
    if (mixinMatches !== null) {
      const mixinParts = mixinMatches[1].split(",");
      for (let i = 0; i < mixinParts.length; i++) {
        const mixin = mixinParts[i].trim().replace(/['"]/g, "");
        loadPaths.add(`themes/${mixin}/scss/`);
        queue.push(mixin);
      }
    }

    // Now move up to parent theme:
    const matches = config.match(/['"]extends['"]\s*=>\s*['"](\w+)['"]/);

    // "extends" set to "false" or missing entirely? We"ve hit the end of the line:
    if (matches === null || matches[1] === "false") {
      break;
    }

    const parent = matches[1];
    loadPaths.add(`themes/${parent}/scss/`);
    queue.push(parent);
  }

  return Array.from(loadPaths);
}

/**
 * [timestamp description]
 * @param  {string} theme [description]
 * @return {(string) => void}
 */
function timestamp(theme) {
  const start = performance.now();
  let mark = performance.now();

  return (task) => {
    console.log(
      `${theme} ${task}: ${Math.floor(
        performance.now() - mark
      )}ms (${Math.floor(performance.now() - start)}ms)`
    );

    mark = performance.now();
  };
}

/**
 * [getLoadPaths description]
 * @param  {string} theme path name of theme (themes/THISPART/js/...)
 * @return {Promise<void>}
 */
async function compileTheme(theme) {
  try {
    // @type {string}
    const mode = commander.opts().mode;

    const mark = timestamp(theme);
    await fs.access(path.resolve(`themes/${theme}/scss/compiled.scss`));
    mark("read scss");

    let options = {
      loadPaths: await getLoadPaths(theme),
      outputStyle: mode == "production" ? "compressed" : "expanded",
      sourceMap: mode == "development",
      logger: sass.Logger.silent,
    };
    mark("get paths");

    const result = sass.compile(`themes/${theme}/scss/compiled.scss`, options);
    mark("compile");

    await fs.writeFile(`themes/${theme}/css/compiled.css`, result.css);
    mark("write");
  } catch (error) {
    if (error instanceof sass.Exception) {
      console.error(error);
    } else {
      throw error;
    }
  }
}
