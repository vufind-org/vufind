module.exports = function(grunt) {
  const fs = require("fs");
  const os = require("node:os");

  // Load dart-sass
  grunt.loadNpmTasks('grunt-dart-sass');

  // Local custom tasks
  if (fs.existsSync("./Gruntfile.local.js")) {
    require("./Gruntfile.local.js")(grunt);
  }

  require('jit-grunt')(grunt); // Just in time library loading

  function getLoadPaths(file) {
    var config;
    var parts = file.split('/');
    parts.pop(); // eliminate filename

    // initialize search path with directory containing the LESS or SCSS file
    var retVal = [];
    retVal.push(parts.join('/'));
    retVal.push(parts.join('/') + '/vendor/');

    var themeBase = parts.slice(0, -1);
    retVal.push(themeBase.join('/') + '/node_modules/');

    // Iterate through theme.config.php files collecting parent themes in search path:
    while (config = fs.readFileSync("themes/" + parts[1] + "/theme.config.php", "UTF-8")) {
      // First identify mixins:
      var mixinMatches = config.match(/["']mixins["']\s*=>\s*\[([^\]]+)\]/);
      if (mixinMatches !== null) {
        var mixinParts = mixinMatches[1].split(',');
        for (var i = 0; i < mixinParts.length; i++) {
          parts[1] = mixinParts[i].trim().replace(/['"]/g, '');
          retVal.push(parts.join('/') + '/');
        }
      }

      // Now move up to parent theme:
      var matches = config.match(/["']extends["']\s*=>\s*['"]([\w\-]+)['"]/);

      // "extends" set to "false" or missing entirely? We've hit the end of the line:
      if (matches === null || matches[1] === 'false') {
        break;
      }

      parts[1] = matches[1];
      retVal.push(parts.join('/') + '/');
      retVal.push(parts.join('/') + '/vendor/');

      var parentThemeBase = parts.slice(0, -1);
      retVal.push(parentThemeBase.join('/') + '/node_modules/');
    }
    return retVal;
  }

  var fontAwesomePath = '"../../bootstrap3/css/fonts"';
  var lessFileSettings = [{
    expand: true,
    src: "themes/*/less/compiled.less",
    rename: function (dest, src) {
      return src.replace('/less/', '/css/').replace('.less', '.css');
    }
  }];

  const gruntConfig = {
    // LESS compilation
    less: {
      compile: {
        files: lessFileSettings,
        options: {
          paths: getLoadPaths,
          compress: true,
          modifyVars: {
            'fa-font-path': fontAwesomePath
          }
        }
      }
    },
    // Less with maps
    lessdev: {
      less: {
      }
    },
    // SASS compilation
    // 'scss' is also mapped to 'scssonly' below
    'scss': {
      'dart-sass': {
        options: {
          outputStyle: 'compressed',
          quietDeps: true
        }
      }
    },
    'check:scss': {
      'dart-sass': {
        options: {
          quietDeps: true
        }
      }
    },

    // Convert LESS to SASS, mostly for development team use
    lessToSass: {
      convert: {
        files: [
          {
            expand: true,
            cwd: 'themes/bootstrap3/less',
            src: ['*.less', 'components/**/*.less', 'mixins/**/*.less'],
            ext: '.scss',
            dest: 'themes/bootstrap3/scss'
          },
          {
            expand: true,
            cwd: 'themes/bootprint3/less',
            src: ['*.less'],
            ext: '.scss',
            dest: 'themes/bootprint3/scss'
          },
          {
            expand: true,
            cwd: 'themes/sandal/less',
            src: ['*.less'],
            ext: '.scss',
            dest: 'themes/sandal/scss'
          }
        ],
        options: {
          excludes: ['important'],
          replacements: [
            // Activate SCSS
            {
              pattern: /\/\* #SCSS>/gi,
              replacement: "/* #SCSS> */",
              order: -1 // Do before anything else
            },
            {
              pattern: /<#SCSS \*\//gi,
              replacement: "/* <#SCSS */",
              order: -1
            },
            // Deactivate LESS
            {
              pattern: /\/\* #LESS> \*\//gi,
              replacement: "/* #LESS>",
              order: -1
            },
            {
              pattern: /\/\* <#LESS \*\//gi,
              replacement: "<#LESS */",
              order: -1
            },
            { // Change separator in @include statements
              pattern: /@include ([^\(]+)\(([^\)]+)\);/gi,
              replacement: function mixinCommas(match, $1, $2) {
                return '@include ' + $1 + '(' + $2.replace(/;/g, ',') + ');';
              },
              order: 4 // after defaults included in less-to-sass
            },
            { // Remove unquote
              pattern: /unquote\("([^"]+)"\)/gi,
              replacement: function ununquote(match, $1) {
                return $1;
              },
              order: 4
            },
            { // Inline &:extends converted
              pattern: /&:extend\(([^\)]+?)( all)?\)/gi,
              replacement: '@extend $1',
              order: 4
            },
            { // Wrap variables in calcs with #{}
              pattern: /calc\([^;]+/gi,
              replacement: function calcVariables(match) {
                return match.replace(/(\$[\w\-]+)/gi, '#{$1}');
              },
              order: 4
            },
            { // Wrap variables set to css variables with #{}
              pattern: /(--[\w-:]+:\s*)((\$|darken\(|lighten\()[^;]+)/gi,
              replacement: '$1#{$2}',
              order: 5
            },
            { // Remove !default from extends (icons.scss)
              pattern: /@extend ([^;}]+) !default;/gi,
              replacement: '@extend $1;',
              order: 6
            }
          ]
        }
      }
    },

    watch: {
      options: {
        atBegin: true
      },
      less: {
        files: 'themes/*/less/**/*.less',
        tasks: ['less']
      },
      lessdev: {
        files: 'themes/*/less/**/*.less',
        tasks: ['lessdev']
      },
      scss: {
        files: 'themes/*/scss/**/*.scss',
        tasks: ['scss']
      }
    }
  };
  // scssonly compiles scss files for themes that don't use less
  gruntConfig.scssonly = gruntConfig.scss;

  grunt.initConfig(gruntConfig);

  grunt.registerMultiTask('lessdev', function lessWithMaps() {
    grunt.config.set('less', {
      dev: {
        files: lessFileSettings,
        options: {
          paths: getLoadPaths,
          sourceMap: true,
          sourceMapFileInline: true,
          modifyVars: {
            'fa-font-path': fontAwesomePath
          }
        }
      }
    });
    grunt.task.run('less');
  });

  grunt.registerMultiTask('scss', function sassScan() {
    grunt.config.set('dart-sass', getSassConfig(this.data.options, false));
    grunt.task.run('dart-sass');
  });
  grunt.registerMultiTask('scssonly', function sassScan() {
    grunt.config.set('dart-sass', getSassConfig(this.data.options, false, false));
    grunt.task.run('dart-sass');
  });

  grunt.registerMultiTask('check:scss', function sassCheck() {
    grunt.config.set('dart-sass', getSassConfig(this.data.options, true, true));
    grunt.task.run('dart-sass');
  });

  grunt.registerTask('default', function help() {
    grunt.log.writeln(`\nHello! Here are your grunt command options:

    - grunt less        = compile and compress all themes' LESS files to css.
    - grunt scss        = compile and map all themes' SASS files to css.
    - grunt lessdev     = compile and map all themes' LESS files to css.
    - grunt check:scss  = check all themes' SASS files.
    - grunt watch:[cmd] = continuous monitor source files and run command when changes are detected.
    - grunt watch:less
    - grunt watch:scss
    - grunt watch:lessdev
    - grunt lessToSass  = transpile all LESS files to SASS.`);
  });

  function getSassConfig(additionalOptions, checkOnly, themesWithLess = null) {
    var sassConfig = {},
      path = require('path'),
      themeList = fs.readdirSync(path.resolve('themes')).filter(function (theme) {
        return fs.existsSync(path.resolve('themes/' + theme + '/scss/compiled.scss'))
          && (null === themesWithLess || themesWithLess === fs.existsSync(path.resolve('themes/' + theme + '/less/compiled.less')));
      });

    for (var i in themeList) {
      if (Object.prototype.hasOwnProperty.call(themeList, i)) {
        var config = {
          options: {},
          files: [{
            expand: true,
            cwd: path.join('themes', themeList[i], 'scss'),
            src: ['compiled.scss'],
            dest: path.join(checkOnly ? os.tmpdir() : 'themes', themeList[i], 'css'),
            ext: '.css'
          }]
        };
        for (var key in additionalOptions) {
          if (Object.prototype.hasOwnProperty.call(additionalOptions, key)) {
            config.options[key] = additionalOptions[key];
          }
        }
        config.options.includePaths = getLoadPaths('themes/' + themeList[i] + '/scss/compiled.scss');
        // This allows loading of styles from composer dependencies:
        config.options.includePaths.push('vendor/');

        sassConfig[themeList[i]] = config;
      }
    }
    return sassConfig;
  }
};
