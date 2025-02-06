module.exports = function(grunt) {
  const fs = require("fs");
  const os = require("node:os");

  const style = grunt.option('style') ?? 'expanded';

  grunt.registerTask("finna:scss", function finnaScssFunc() {
    const config = getFinnaSassConfig({
      outputStyle: style,
      quietDeps: true,
      silenceDeprecations: [
        'import',
        'color-functions',
        'global-builtin',
        'legacy-js-api'
      ]
    }, false);
    grunt.config.set('dart-sass', config);
    grunt.task.run('dart-sass');
  });

  grunt.registerTask('finna:check:scss', function sassCheck() {
    const config = getFinnaSassConfig({
      quietDeps: true
    }, true);
    grunt.config.set('dart-sass', config);
    grunt.task.run('dart-sass');
  });

  function getLoadPaths(file) {
    var config;
    var parts = file.split('/');
    parts.pop(); // eliminate filename

    // initialize search path with directory containing LESS file
    var retVal = [];
    retVal.push(parts.join('/'));

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
      var matches = config.match(/["']extends["']\s*=>\s*['"](\w+)['"]/);

      // "extends" set to "false" or missing entirely? We've hit the end of the line:
      if (matches === null || matches[1] === 'false') {
        break;
      }

      parts[1] = matches[1];
      retVal.push(parts.join('/') + '/');
    }
    return retVal;
  }

  function getFinnaSassConfig(additionalOptions, checkOnly) {
    var sassConfig = {},
      path = require('path'),
      themeList = fs.readdirSync(path.resolve('themes')).filter(function (theme) {
        return fs.existsSync(path.resolve('themes/' + theme + '/scss/finna.scss'));
      });

    for (var i in themeList) {
      if (Object.prototype.hasOwnProperty.call(themeList, i)) {
        var config = {
          options: {},
          files: [{
            expand: true,
            cwd: path.join('themes', themeList[i], 'scss'),
            src: ['finna.scss'],
            dest: path.join(checkOnly ? os.tmpdir() : 'themes', themeList[i], 'css'),
            ext: '.css'
          }]
        };
        for (var key in additionalOptions) {
          if (Object.prototype.hasOwnProperty.call(additionalOptions, key)) {
            config.options[key] = additionalOptions[key];
          }
        }
        config.options.includePaths = getLoadPaths('themes/' + themeList[i] + '/scss/finna.scss');
        config.options.includePaths.push('vendor/');
        config.options.includePaths.push(path.resolve('themes/bootstrap5/scss/vendor'));
        config.options.includePaths.push(path.resolve('themes/bootstrap5/node_modules'));

        sassConfig[themeList[i]] = config;
      }
    }
    return sassConfig;
  }
};
