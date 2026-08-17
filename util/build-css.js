/*global Buffer, process, require */
/*eslint-disable no-console -- console needed for CLI output */

// Display functions

/**
 * Display a string as red text.
 * @param {string} str Message to display
 * @returns {string} Formatted string
 */
function red(str) {
  return `\x1b[31m${str}\x1b[0m`;
}

/**
 * Display a string as green text.
 * @param {string} str Message to display
 * @returns {string} Formatted string
 */
function green(str) {
  return `\x1b[32m${str}\x1b[0m`;
}

/**
 * Display a string as yellow text.
 * @param {string} str Message to display
 * @returns {string} Formatted string
 */
function yellow(str) {
  return `\x1b[33m${str}\x1b[0m`;
}

/**
 * Display a string as underlined text.
 * @param {string} str Message to display
 * @returns {string} Formatted string
 */
function underline(str) {
  return `\x1b[4m${str}\x1b[0m`;
}

/**
 * Display a string as heading text.
 * @param {string} str Message to display
 * @returns {string} Formatted string
 */
function heading(str) {
  return yellow(underline(str));
}

// Dependency check:

if (
  !process ||
  !process.env ||
  !process.env.VUFIND_HOME
) {
  console.error(red("Set VUFIND_HOME before running."));
  process.exit(1);
}

const fs = require("node:fs");
const path = require("node:path");

const CleanCSS = require("clean-css");
const { program } = require("commander");
const sass = require("sass");

// Support functions

/**
 * Get the path to a theme (or location within a theme).
 * @param {string} name   Theme name
 * @param {string} subdir Subdirectory within the theme (empty string for root of theme)
 * @returns {string} Theme path
 */
function themePath(name, subdir = "") {
  const subpath = subdir.split("/").filter(Boolean);

  if (subdir.length === 0) {
    return path.join(process.env.VUFIND_HOME, "themes", name);
  }

  return path.join(process.env.VUFIND_HOME, "themes", name, ...subpath);
}

/**
 * Get a list of themes.
 * @returns {Array<string>} Theme list
 */
function getThemeList() {
  const themes = [];

  try {
    const themesDir = path.join(process.env.VUFIND_HOME, "themes");

    const entries = fs.readdirSync(themesDir);
    for (const dir of entries) {
      if (
        fs.existsSync(themePath(dir, "theme.config.php")) &&
        fs.existsSync(themePath(dir, "scss"))
      ) {
        themes.push(dir);
      }
    }
  } catch (err) {
    console.error(red("Error reading directory:", err));
    process.exit(1);
  }

  return themes;
}

/**
 * Get a list of key include paths within the specified theme (including parents, mixins, etc.).
 * @param {string} themeName Theme name
 * @returns {Array<string>} Load path stack
 */
function getLoadPaths(themeName) {
  const paths = [];

  /**
   * Add paths to the path array for a single theme or mixin directory.
   * @param {string} name Theme or mix-in name
   * @returns {void}
   */
  function addSubPaths(name) {
    // Add mixin base
    paths.push(themePath(name));

    // Add subdirs if they exist
    const subpaths = ["scss", "scss/vendor", "css", "css/vendor"];
    for (const subdir of subpaths) {
      const subpath = themePath(name, subdir);
      if (fs.existsSync(subpath)) {
        paths.push(subpath);
      }
    }

    // Add node_modules as a final fallback (for libraries like Bootstrap / Font Awesome)
    paths.push(path.join(process.env.VUFIND_HOME, "node_modules"));
  }

  // Iterate through theme.config.php files collecting parent themes in search path:
  let currName = themeName;
  while (true) {
    const config = fs.readFileSync(
      `themes/${currName}/theme.config.php`,
      "UTF-8"
    );

    if (!config) {
      break;
    }

    addSubPaths(currName);

    // First identify mixins:
    const mixinMatches = config.match(/["']mixins["']\s*=>\s*\[([^\]]+)\]/);
    if (mixinMatches !== null) {
      const mixinNames = mixinMatches[1].split(",");
      for (const mixinName of mixinNames) {
        addSubPaths(mixinName);
      }
    }

    // Now move up to parent theme:
    var matches = config.match(/["']extends["']\s*=>\s*['"]([\w-]+)['"]/);

    // "extends" set to "false" or missing entirely? We've hit the end of the line:
    if (matches === null || matches[1] === "false") {
      break;
    }

    currName = matches[1];
  }

  return paths.map((filePath) => `${filePath}/`);
}

// Arguments

program
  .option(
    "-t, --themes [themes...]",
    "themes to compile - if excluded all themes will be compiled",
    getThemeList()
  )
  .option(
    "-e, --entry <string>",
    "source SCSS entry point in {theme}/scss",
    "compiled.scss"
  )
  .option(
    "-o, --outname <string>",
    "CSS file to output into {theme}/css",
    "compiled.css"
  )
  .option(
    "-c, --check-only",
    "compile but do not write result",
    false
  )
  .option("-M, --no-minify", "do not minify CSS with clean-css (faster compilation)")
  .option(
    "-S, --no-sourcemaps",
    "do not generate sourcemaps (faster compilation)"
  )
  .option("--temp <path>",
    "Override temporary file location",
    "/tmp"
  );

program.parse(process.argv);
const CLI_OPTIONS = program.opts();

// minify CSS, unless told not to with --no-minify (-M)
// i.e., npm run build:scss -- --no-minify
const DO_MINIFY = Boolean(CLI_OPTIONS.minify);

// build source maps, unless told not to with --no-sourcemaps (-S)
// source maps allow compiled CSS to be mapped to the original SCSS source
// i.e., npm run build:scss -- --no-sourcemaps
const DO_SOURCEMAPS = Boolean(CLI_OPTIONS.sourcemaps);

console.log(`CHECK-ONLY: ${String(CLI_OPTIONS.checkOnly)}`);
console.log(`MINIFY: ${String(DO_MINIFY)}`);
console.log(`SOURCEMAPS: ${String(DO_SOURCEMAPS)}`);


/**
 * Determine whether two files are identical
 * @param {string} aPath First file to compare
 * @param {string} bPath Second file to compare
 * @returns {boolean} True if identical, false otherwise
 */
function filesAreIdentical(aPath, bPath) {
  // Check sizes first
  const aStats = fs.statSync(aPath);
  const bStats = fs.statSync(bPath);

  console.log(`  - ${aPath} size: ${aStats.size}`);
  console.log(`  - ${bPath} size: ${bStats.size}`);

  if (aStats.size !== bStats.size) {
    return false;
  }

  // Chunked comparison
  const aHandle = fs.openSync(aPath);
  const bHandle = fs.openSync(bPath);

  const chunkSize = 512;
  const aBuffer = new Uint8Array(chunkSize);
  const bBuffer = new Uint8Array(chunkSize);
  for (let pos = 0; pos < aStats.size; pos += chunkSize) {
    fs.readSync(aHandle, aBuffer, 0, chunkSize, pos);
    fs.readSync(bHandle, bBuffer, 0, chunkSize, pos);

    if (Buffer.compare(aBuffer, bBuffer) !== 0) {
      return false;
    }
  }

  return true;
}

/**
 * Compile a theme.
 * @param {string} themeName Name of theme to compile
 * @returns {void}
 */
function compileTheme(themeName) {
  const start = performance.now();
  console.log(`\n${heading(themeName)}`);

  console.log("- compiling SCSS to CSS");

  // Inspired by bootstrap5 compilation, but adjusted for project needs.
  // @link https://github.com/twbs/bootstrap/blob/main/package.json
  // sass --style expanded --source-map --embed-sources --no-error-css scss/:dist/css/
  const compiled = sass.compile(
    themePath(themeName, `scss/${CLI_OPTIONS.entry}`),
    {
      style: "expanded",
      sourceMap: DO_SOURCEMAPS,
      embedSources: true,
      // needed due to limitations of Bootstrap 5 as of June, 2026:
      quietDeps: true,
      silenceDeprecations: ['import'],
      // no equivalent for no-error-css
      loadPaths: getLoadPaths(themeName),
    }
  );

  let cssContent = compiled.css;
  let sourceMapContent = DO_SOURCEMAPS ? JSON.stringify(compiled.sourceMap) : null;

  if (DO_MINIFY) {
    console.log("- minifying");

    // @link https://github.com/twbs/bootstrap/blob/main/package.json
    // cleancss -O1 --format breakWith=lf --with-rebase --source-map --source-map-inline-sources
    const compressor = new CleanCSS({
      level: 1,
      format: { breakWith: 'lf' },
      // rebasing breaks Font Awesome icons
      sourceMap: DO_SOURCEMAPS,
      sourceMapInlineSources: true,
    });

    const output = compressor.minify(compiled.css, compiled.sourceMap || null);

    cssContent = output.styles;
    sourceMapContent = DO_SOURCEMAPS ? output.sourceMap.toString() : null;

    const reduction = (100 * output.stats.efficiency).toFixed(1);
    console.log(`  - ${output.stats.timeSpent}ms (${reduction.toLocaleString()}% smaller)`);
  }

  // Write files

  if (sourceMapContent) {
    // add sourceMappingURL to CSS
    const sourceMapName = `${CLI_OPTIONS.outname}.map`;
    cssContent = `${cssContent}\n/*# sourceMappingURL=${sourceMapName} */`;

    if (!CLI_OPTIONS.checkOnly) {
      console.log(`- writing source map to theme/${themeName}/css/${sourceMapName}`);
      const sourceMapPath = themePath(themeName, `css/${sourceMapName}`);
      fs.writeFileSync(sourceMapPath, sourceMapContent, "utf8");
    }
  }

  const outCSSPath = themePath(themeName, `css/${CLI_OPTIONS.outname}`);
  if (CLI_OPTIONS.checkOnly) {
    // Check if compiled.css is up-to-date
    if (fs.existsSync(outCSSPath)) {
      const tmpCSSPath = `${CLI_OPTIONS.temp}/scss-check-${themeName}.css`;
      console.log(`- writing ${tmpCSSPath} for comparison`);
      fs.writeFileSync(tmpCSSPath, cssContent, "utf8");

      try {
        if (filesAreIdentical(tmpCSSPath, outCSSPath)) {
          console.log(`  - ${green("SCSS up-to-date")}`);
        } else {
          console.error(`  - ${red("SCSS needs to be recompiled")}`);
          process.exitCode = 1; // CI failure without exiting
        }
      } catch (e) {
        console.error(e);
        process.exit(1);
      }
    }
  } else {
    console.log(`- writing css to theme/${themeName}/css/${CLI_OPTIONS.outname}`);
    fs.writeFileSync(outCSSPath, cssContent, "utf8");
  }

  console.log(`- done (${Math.ceil(performance.now() - start).toLocaleString()}ms)`);
}

// Main

for (const theme of CLI_OPTIONS.themes) {
  try {
    if (fs.existsSync(themePath(theme, "scss"))) {
      compileTheme(theme);
    }
  } catch (e) {
    console.error(red(e));
  }
}
