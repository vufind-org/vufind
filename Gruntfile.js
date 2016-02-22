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
    }
  });

  grunt.loadNpmTasks('grunt-contrib-less');

  grunt.registerTask('default', ['less']);
  grunt.registerTask('css', ['less']);
};