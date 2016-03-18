module.exports = function(grunt) {
  require('jit-grunt')(grunt); // Just in time library loading

  grunt.initConfig({
    // less
    less: {
      compile: {
        options: {
          paths: ["themes/bootprint3/less", "themes/bootstrap3/less"],
          compress: true
        },
        files: {
          "themes/bootstrap3/css/compiled.css": "themes/bootstrap3/less/bootstrap.less",
          "themes/bootprint3/css/compiled.css": "themes/bootprint3/less/bootprint.less",
        }
      }
    },
    css: {
      sass: {
        options: {
          themeFolder: 'themes'
        },
        dist: {
          options: {
            style: 'compressed',
            sourcemap: 'none'
          }
        },
        dev: {
          options: {
            style: 'expanded',
            sourcemap: 'none'
          }
        }
      }
    },
    // JS compression
    uglify: {
      options: {
        mangle: false
      },
      vendor_min: {
        files: {
          'themes/bootstrap3/js/vendor.min.js': [
            'themes/bootstrap3/js/vendor/jquery.min.js',
            'themes/bootstrap3/js/vendor/bootstrap.min.js',
            'themes/bootstrap3/js/vendor/*.js',
            '!themes/bootstrap3/js/vendor/bootlint.min.js',     // comment to lint bootstrap syntax
            '!themes/bootstrap3/js/vendor/bootstrap-slider.js', // not "use strict" compatible
            'themes/bootstrap3/js/autocomplete.js'
          ]
        }
      }
    }
  });

  grunt.loadNpmTasks('grunt-contrib-less');
  grunt.loadNpmTasks('grunt-contrib-sass');
  grunt.loadNpmTasks('grunt-contrib-uglify');

  grunt.registerTask('default', ['less', 'uglify']);
  grunt.registerTask('js', ['uglify']);

  grunt.registerMultiTask('css', function (arg1, arg2) {
    var fs = require('fs')
      , path = require('path')
      , options = (arguments.length > 0 && this.data[arg1] && this.data[arg1].options)
        ? this.data[arg1].options
        : this.data.dist.options
      , theme = (arguments.length > 1) ? arg2 : null
      , themeFolder = this.data.options.themeFolder || 'themes'
      , themeList = fs.readdirSync(path.resolve(themeFolder))
      , sassConfig = {}
      ;

    var inheritance = {};
    for (var i in themeList) {
      if (theme && themeList[i] !== theme) {
        continue;
      }
      var config = fs.readFileSync(path.join(themeFolder, themeList[i], 'theme.config.php'), 'UTF-8');
      inheritance[themeList[i]] = config.toLowerCase().replace(/[\s']/g, '').match(/extends=>(\w+)/)[1];
    }

    for (var i in themeList) {
      var sassDir = path.join(themeFolder, themeList[i], 'sass');
      var cssDir = path.join(themeFolder, themeList[i], 'css');
      try {
        fs.statSync(sassDir);
        // Build load path
        var loadPath = [];
        var curr = themeList[i];
        while (inheritance[curr] != 'root') {
          loadPath.unshift(path.join(themeFolder, inheritance[curr], 'sass'));
          curr = inheritance[curr];
        }
        if (loadPath.length > 0) {
          options.loadPath = loadPath;
        }
        // Compile
        var files = {};
        files[path.join(cssDir, 'compiled.css')] = path.join(sassDir, themeList[i].replace(/\d/g, '')+'.scss');
        sassConfig[themeList[i]] = {
          options: options,
          files: files
        };
      } catch (err) {
        // silently suppress thrown errors when no sass sources exist in a theme
      }
    }

    grunt.config.set('sass', sassConfig);
    grunt.task.run('sass');
  });
};