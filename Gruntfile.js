module.exports = function(grunt) {
  require('jit-grunt')(grunt); // Just in time library loading

  grunt.initConfig({
    // less
    less: {
      development: {
        options: {
          paths: ["themes/bootstrap3/less", "themes/bootprint3/less"] /* ,
          compress: true //*/
        },
        files: {
          "themes/bootstrap3/css/compiled.css": "themes/bootstrap3/less/compiled.less",
          "themes/bootprint3/css/compiled.css": "themes/bootprint3/less/compiled.less",
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
            'themes/bootstrap3/js/vendor/*.js',
            'themes/bootstrap3/js/autocomplete.js',
            'themes/bootstrap3/js/vendor/bootlint.min.js'
          ]
        }
      }
    }
  });

  grunt.loadNpmTasks('grunt-contrib-less');
  grunt.loadNpmTasks('grunt-contrib-uglify');

  grunt.registerTask('default', ['less']);
  grunt.registerTask('dev', ['less', 'uglify']);
  grunt.registerTask('css', ['less']);
  grunt.registerTask('js', ['uglify']);
};