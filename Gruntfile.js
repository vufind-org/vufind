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

    // initialize search path with directory containing the SCSS file
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

  const gruntConfig = {
    // SASS compilation
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

    watch: {
      options: {
        atBegin: true
      },
      scss: {
        files: 'themes/*/scss/**/*.scss',
        tasks: ['scss']
      }
    }
  };

  grunt.initConfig(gruntConfig);

  grunt.registerMultiTask('scss', function sassScan() {
    grunt.config.set('dart-sass', getSassConfig(this.data.options, false));
    grunt.task.run('dart-sass');
  });

  grunt.registerMultiTask('check:scss', function sassCheck() {
    grunt.config.set('dart-sass', getSassConfig(this.data.options, true, true));
    grunt.task.run('dart-sass');
  });

  grunt.registerTask('default', function help() {
    grunt.log.writeln(`\nHello! Here are your grunt command options:

    - grunt scss        = compile and map all themes' SASS files to css.
    - grunt check:scss  = check all themes' SASS files.
    - grunt watch:[cmd] = continuous monitor source files and run command when changes are detected.
    - grunt watch:scss`);
  });

  function getSassConfig(additionalOptions, checkOnly) {
    var sassConfig = {},
      path = require('path'),
      themeList = fs.readdirSync(path.resolve('themes')).filter(function (theme) {
        return fs.existsSync(path.resolve('themes/' + theme + '/scss/compiled.scss'));
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
