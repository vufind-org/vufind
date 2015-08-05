module.exports = function (grunt) {
    grunt.initConfig({
        pkg  : grunt.file.readJSON('package.json'),
        // ADAPT THIS FOR FOUNDATION BASE THEME
        sass : {
            dist: {
                options: {
                    outputStyle: 'expanded' // specify style here
                },
                files: [{
                    expand: true, // allows you to specify directory instead of indiv. files
                    cwd: 'themes/foundation5/scss', // current working directory
                    src: ['**/*.scss'],
                    dest: 'themes/foundation5/css',
                    ext: '.css'
                }]
            }
        },
        watch: {
            css: {
                files: '**/*.scss',
                tasks: ['sass']
            }
        }
    });
    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.registerTask('default', ['watch']);
};