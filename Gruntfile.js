module.exports = function(grunt) {
  require('jit-grunt')(grunt); // Just in time library loading

  var fs = require('fs');

  function getLoadPaths(file) {
    var config;
    var parts = file.split('/');
    parts.pop(); // eliminate filename

    // initialize search path with directory containing LESS file
    var retVal = [];
    retVal.push(parts.join('/'));

    // Iterate through theme.config.php files collecting parent themes in search path:
    while (config = fs.readFileSync("themes/" + parts[1] + "/theme.config.php", "UTF-8")) {
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

  var fontAwesomePath = '"../../bootstrap3/css/fonts"';

  grunt.initConfig({
    // LESS compilation
    less: {
      compile: {
        options: {
          paths: getLoadPaths,
          compress: true,
          modifyVars: {
            'fa-font-path': fontAwesomePath
          }
        },
        files: [{
          expand: true,
          src: "themes/*/less/compiled.less",
          rename: function (dest, src) {
            return src.replace('/less/', '/css/').replace('.less', '.css');
          }
        }]
      }
    },
    // SASS compilation
    scss: {
      sass: {
        options: {
          style: 'compress'
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
            src: ['*.less', 'components/*.less'],
            ext: '.scss',
            dest: 'themes/bootstrap3/scss'
          },
          {
            expand: true,
            cwd: 'themes/bootprint3/less',
            src: ['*.less'],
            ext: '.scss',
            dest: 'themes/bootprint3/scss'
          }
        ],
        options: {
          replacements: [
            { // Replace ; in include with ,
              pattern: /(\s+)@include ([^\(]+)\(([^\)]+)\);/gi,
              replacement: function mixinCommas(match, space, $1, $2) {
                return space + '@include ' + $1 + '(' + $2.replace(/;/g, ',') + ');';
              },
              order: 3
            },
            { // Inline &:extends converted
              pattern: /&:extend\(([^\)]+)\)/gi,
              replacement: '@extend $1',
              order: 3
            },
            { // Inline variables not default
              pattern: / !default; }/gi,
              replacement: '; }',
              order: 3
            },
            {  // VuFind: Correct paths
              pattern: 'vendor/bootstrap/bootstrap',
              replacement: 'vendor/bootstrap',
              order: 4
            },
            {
              pattern: '$fa-font-path: "../../../fonts" !default;\n',
              replacement: '',
              order: 4
            },
            {
              pattern: '@import "vendor/font-awesome/font-awesome";',
              replacement: '$fa-font-path: ' + fontAwesomePath + ';\n@import "vendor/font-awesome/font-awesome";',
              order: 4
            },
            { // VuFind: Bootprint fixes
              pattern: '@import "bootstrap";\n@import "variables";',
              replacement: '@import "variables", "bootstrap";',
              order: 4
            },
            {
              pattern: '$brand-primary: #619144 !default;',
              replacement: '$brand-primary: #619144;',
              order: 4
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
      scss: {
        files: 'themes/*/scss/**/*.scss',
        tasks: ['scss']
      }
    }
  });
  grunt.registerMultiTask('scss', function sassScan() {
    var sassConfig = {},
      path = require('path'),
      themeList = fs.readdirSync(path.resolve('themes')).filter(function (theme) {
        return fs.existsSync(path.resolve('themes/' + theme + '/scss/compiled.scss'));
      });

    for (var i in themeList) {
      var config = {
        options: {
          outputStyle: 'compressed'
        },
        files: [{
          expand: true,
          cwd: path.join('themes', themeList[i], 'scss'),
          src: ['compiled.scss'],
          dest: path.join('themes', themeList[i], 'css'),
          ext: '.css'
        }]
      };
      for (var key in this.data.options) {
        config.options[key] = this.data.options[key] + '';
      }
      config.options.includePaths = getLoadPaths('themes/' + themeList[i] + '/scss/compiled.scss');

      sassConfig[themeList[i]] = config;
    }

    grunt.config.set('sass', sassConfig);
    grunt.task.run('sass');
  });
};
