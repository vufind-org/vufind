module.exports = function(grunt) {
  require('jit-grunt')(grunt); // Just in time library loading

  grunt.initConfig({
    // LESS compilation
    less: {
      compile: {
        options: {
          paths: ["themes/bootprint3/less", "themes/bootstrap3/less"],
          compress: true,
          modifyVars: {
            'fa-font-path': '"fonts"',
            'img-path': '"../images"',
          }
        },
        files: {
          "themes/bootstrap3/css/compiled.css": "themes/bootstrap3/less/bootstrap.less",
          "themes/bootprint3/css/compiled.css": "themes/bootprint3/less/bootprint.less",
        }
      }
    },
    // SASS compilation
    sass: {
      compile: {
        options: {
          loadPath: ["themes/bootprint3/sass", "themes/bootstrap3/sass"],
          style: 'compress'
        },
        files: {
          "themes/bootstrap3/css/compiled.css": "themes/bootstrap3/sass/bootstrap.scss",
          "themes/bootprint3/css/compiled.css": "themes/bootprint3/sass/bootprint.scss"
        }
      }
    },
    // Convert LESS to SASS
    lessToSass: {
      convert: {
        files: [
          {
            expand: true,
            cwd: 'themes/bootstrap3/less',
            src: ['*.less', 'components/*.less'],
            ext: '.scss',
            dest: 'themes/bootstrap3/sass'
          },
          {
            expand: true,
            cwd: 'themes/bootprint3/less',
            src: ['*.less'],
            ext: '.scss',
            dest: 'themes/bootprint3/sass'
          }
        ],
        options: {
          replacements: [
            { // Replace ; in include with ,
              pattern: /(\s+)@include ([^\(]+)\(([^\)]+)\);/gi,
              replacement: function (match, space, $1, $2) {
                return space+'@include '+$1+'('+$2.replace(/;/g, ',')+');';
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
              pattern: '$fa-font-path: "../../../fonts" !default;',
              replacement: '$fa-font-path: "fonts";',
              order: 4
            },
            {
              pattern: '$img-path: "../../images" !default;',
              replacement: '$img-path: "../images";',
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
    // JS compression
    uglify: {
      options: {
        mangle: false
      },
      vendor_min: { // after running uglify:vendor_min, change your theme.config.php
        files: {    // to only load vendor.min.js instead of all the js/vendor files
          'themes/bootstrap3/js/vendor.min.js': [
            'themes/bootstrap3/js/vendor/jquery.min.js',       // these two need to go first
            'themes/bootstrap3/js/vendor/bootstrap.min.js',
            'themes/bootstrap3/js/vendor/*.js',
            'themes/bootstrap3/js/autocomplete.js',
            '!themes/bootstrap3/js/vendor/bootstrap-slider.js' // skip, not "use strict" compatible
          ]
        }
      }
    }
  });

  grunt.loadNpmTasks('grunt-contrib-less');
  grunt.loadNpmTasks('grunt-contrib-sass');
  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-less-to-sass');

  grunt.registerTask('default', ['less', 'uglify']);
  grunt.registerTask('js', ['uglify']);
};