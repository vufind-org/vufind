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
    sass: {
      compile: {
        options: {
          loadPath: ["themes/bootprint3/sass", "themes/bootstrap3/sass"],
          sourcemap: 'none',
          style: 'compressed'
        },
        files: {
          'themes/bootstrap3/css/compiled.css': 'themes/bootstrap3/sass/bootstrap.scss',
          'themes/bootprint3/css/compiled.css': 'themes/bootprint3/sass/bootprint.scss'
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
            '!themes/bootstrap3/js/vendor/bootlint.min.js',     // un-comment to lint bootstrap syntax
            '!themes/bootstrap3/js/vendor/bootstrap-slider.js', // not "use strict" compatible
            'themes/bootstrap3/js/autocomplete.js'
          ]
        }
      }
    }
  });

  grunt.loadNpmTasks('grunt-contrib-less');
  grunt.loadNpmTasks('grunt-contrib-uglify');

  grunt.registerTask('default', ['less', 'uglify']);
  grunt.registerTask('js', ['uglify']);
};