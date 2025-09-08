if (!(process.env.VUFIND_HOME ?? false)) {
  console.error("Set VUFIND_HOME before running.");
  process.exit(1);
}

const fs = require("node:fs");
const path = require("node:path");

const CleanCSS = require("clean-css");
const { program } = require("commander");
const sass = require("sass");

// Arguments

program
  .option("-m, --minify", "minify CSS with clean-css")
  .option(
    "-t, --themes [themes...]",
    "themes to compile - if excluded all themes will be compiled",
    getThemeList()
  )
  .option(
    "-e, --entry <string>",
    "SCSS to compile in theme/scss",
    "compiled.scss"
  )
  .option(
    "-o, --outname <string>",
    "CSS file to output in theme/css",
    "compiled.css"
  )
  .option(
    "-S, --no-sourcemaps",
    "do not generate sourcemaps (faster compilation)"
  )
  .option(
    "-c, --check-only",
    "compile but do not write result"
  );

program.parse(process.argv);
const CLI_OPTIONS = program.opts();

const DO_MINIFY = CLI_OPTIONS.minify ? true : CLI_OPTIONS.checkOnly !== true;
const DO_SOURCEMAPS = CLI_OPTIONS.checkOnly ? false : CLI_OPTIONS.sourcemaps ?? CLI_OPTIONS.minify;

console.log(`CHECK-ONLY: ${String(CLI_OPTIONS.checkOnly ?? false)}`);
console.log(`MINIFY: ${String(DO_MINIFY)}`);
console.log(`SOURCEMAPS: ${String(DO_SOURCEMAPS)}`);

// Main

for (const theme of CLI_OPTIONS.themes) {
  try {
    if (fs.existsSync(themePath(theme, "scss"))) {
      compileTheme(theme);
    }
  } catch (e) {
    console.error(e);
  }
}

function compileTheme(themeName) {
  const start = performance.now();
  console.log(`\n${underline(themeName)}`);

  console.log("- compiling SCSS to CSS...");

  // @link https://github.com/twbs/bootstrap/blob/main/package.json
  // sass --style expanded --source-map --embed-sources --no-error-css scss/:dist/css/
  const compiled = sass.compile(
    themePath(themeName, `scss/${CLI_OPTIONS.entry}`),
    {
      loadPaths: getLoadPaths(themeName),
      style: "expanded",
      sourceMap: DO_SOURCEMAPS,
      embedSources: true,
      quietDeps: true,
    }
  );

  let cssContent = compiled.css;
  let sourceMapContent = null;

  if (DO_SOURCEMAPS) {
    sourceMapContent = JSON.stringify(compiled.sourceMap);
  }

  if (DO_MINIFY) {
    console.log("- minifying...");

    // @link https://github.com/twbs/bootstrap/blob/main/package.json
    // cleancss -O1 --format breakWith=lf --with-rebase --source-map --source-map-inline-sources
    // rebasing breaks Font Awesome icons
    const compressor = new CleanCSS({
      level: 1,
      format: { breakWith: 'lf' },
      sourceMap: DO_SOURCEMAPS,
      sourceMapInlineSources: true,
    });

    const output = compressor.minify(compiled.css, compiled.sourceMap ?? null);

    cssContent = output.styles;
    sourceMapContent = DO_SOURCEMAPS ? output.sourceMap.toString() : null;

    const reduction = (100 * output.stats.efficiency).toFixed(1);
    console.log(`  - ${output.stats.timeSpent}ms (${reduction}% smaller)`);
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

  if (!CLI_OPTIONS.checkOnly) {
    console.log(`- writing css to theme/${themeName}/css/${CLI_OPTIONS.outname}`);
    const outCSSPath = themePath(themeName, `css/${CLI_OPTIONS.outname}`);
    fs.writeFileSync(outCSSPath, cssContent, "utf8");
  }

  console.log(`- done (${Math.ceil(performance.now() - start)}ms)`);
}

// Functions

function underline(str) {
  return `\x1b[4m${str}\x1b[0m`;
}

function themePath(name, subdir = "") {
  const subpath = subdir.split("/").filter(Boolean);

  if (subdir.length === 0) {
    return path.join(process.env.VUFIND_HOME, "themes", name);
  }

  return path.join(process.env.VUFIND_HOME, "themes", name, ...subpath);
}

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
    console.error("Error reading directory:", err);
  }

  return themes;
}

function getLoadPaths(themeName) {
  const paths = [];

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
    var matches = config.match(/["']extends["']\s*=>\s*['"]([\w\-]+)['"]/);

    // "extends" set to "false" or missing entirely? We've hit the end of the line:
    if (matches === null || matches[1] === "false") {
      break;
    }

    currName = matches[1];
  }

  return paths.map((path) => `${path}/`);
}
