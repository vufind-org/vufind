module.exports = function (grunt) {
    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-contrib-watch');

    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        // ADAPT THIS FOR FOUNDATION BASE THEME
        foundation: {
            sass: {
                dist: {
                    options: {
                        outputStyle: 'compressed' // specify style here
                    }
                },
                tmp: {
                    options: {
                        outputStyle: 'expanded'
                    }
                },
                options: {
                    themeFolder: 'themes'
                }
            }
        },
        watch: {
            options: {
                atBegin: true
            },
            css: {
                files: '**/*.scss',
                tasks: ['foundation:sass:dev']
            }
        }
    });
    grunt.registerMultiTask('foundation', function (arg1, arg2) {
        var fs = require('fs')
            , path = require('path')
            , options = (arguments.length > 0 && this.data[arg1] && this.data[arg1].options) ? this.data[arg1].options : this.data.dist.options
            , theme = (arguments.length > 1) ? arg2 : null
            , themeFolder = this.data.options.themeFolder || 'themes'
            , themeList = fs.readdirSync(path.resolve(themeFolder))
            , sassConfig = {}
            ;

        for (var i in themeList) {
            if (theme && themeList[i] !== theme) {
                continue;
            }
            var sassDir = path.join(themeFolder, themeList[i], 'scss');
            var cssDir = path.join(themeFolder, themeList[i], 'css');

            try {
                fs.statSync(sassDir);
                sassConfig[themeList[i]] = {
                    options: options,
                    files: [{
                        expand: true,
                        cwd: sassDir,
                        src: ['**/*.scss'],
                        dest: cssDir,
                        ext: '.css'
                    }]
                };
            } catch (err) {
                // silently suppress thrown errors when no sass sources exist in a theme
            }
        }

        grunt.config.set('sass', sassConfig);
        grunt.task.run('sass');
    });

    grunt.registerTask('default', ['foundation']);
};