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
  );

program.parse(process.argv);
const cliOptions = program.opts();
console.log(cliOptions);

// Main

for (const theme of cliOptions.themes) {
  try {
    if (fs.existsSync(themePath(theme, "scss"))) {
      compileTheme(theme);
    }
  } catch (e) {
    console.error(e);
  }
}

function compileTheme(themeName) {
  console.log(`\n${themeName}:`);

  console.log("- compiling SCSS to CSS...");

  // @link https://github.com/twbs/bootstrap/blob/main/package.json
  // css-compile: sass --style expanded --source-map --embed-sources --no-error-css scss/:dist/css/
  const generateSourceMaps = cliOptions.sourcemaps ?? cliOptions.minify;
  const compiled = sass.compile(
    themePath(themeName, `scss/${cliOptions.entry}`),
    {
      loadPaths: getLoadPaths(themeName),
      style: "expanded",
      sourceMap: generateSourceMaps,
      embedSources: true,
      quietDeps: true,
    }
  );

  let cssContent = compiled.css;
  let sourceMapContent = null;

  if (generateSourceMaps) {
    sourceMapContent = JSON.stringify(compiled.sourceMap);
  }

  if (cliOptions.minify) {
    console.log("- minifying...");

    // @link https://github.com/twbs/bootstrap/blob/main/package.json
    // css-minify-main: cleancss -O1 --format breakWith=lf --with-rebase --source-map --source-map-inline-sources
    // rebasing breaks Font Awesome icons
    const compressor = new CleanCSS({
      level: 1,
      format: { breakWith: 'lf' },
      sourceMap: generateSourceMaps,
      sourceMapInlineSources: true,
    });

    const output = compressor.minify(compiled.css, compiled.sourceMap ?? null);

    cssContent = output.styles;
    sourceMapContent = generateSourceMaps ? output.sourceMap.toString() : null;

    const reduction = (100 * output.stats.efficiency).toFixed(1);
    console.log(`  - ${output.stats.timeSpent}ms (${reduction}% smaller)`);
  }

  // Write files

  if (sourceMapContent) {
    // add sourceMappingURL to CSS
    const sourceMapName = `${cliOptions.outname}.map`;
    cssContent = `${cssContent}\n/*# sourceMappingURL=${sourceMapName} */`;

    console.log(`- writing source map to theme/${themeName}/css/${sourceMapName}`);
    const sourceMapPath = themePath(themeName, `css/${sourceMapName}`);
    fs.writeFileSync(sourceMapPath, sourceMapContent, "utf8");
  }

  console.log(`- writing css to theme/${themeName}/css/${cliOptions.outname}`);
  const outCSSPath = themePath(themeName, `css/${cliOptions.outname}`);
  fs.writeFileSync(outCSSPath, cssContent, "utf8");
}

// Functions

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
