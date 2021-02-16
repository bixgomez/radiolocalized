'use strict';

var gulp        = require('gulp'),
    sass        = require('gulp-sass'),
    sourcemaps  = require('gulp-sourcemaps'),
    prefix      = require('gulp-autoprefixer'),
    livereload  = require('gulp-livereload'),
    sassGlob    = require('gulp-sass-glob');

// Directory for storing sass and css files
var sassFiles          = './sass/**/*.scss';
var cssDir             = './css';

gulp.task('sass', function () {

    return gulp

    // Find all the .scss files.
    .src(sassFiles)

    // Enable globbing.
    .pipe(sassGlob())

    // Initialize sourcemaps.
    .pipe(sourcemaps.init())
    
    // Run Sass
    .pipe(sass({
      includePaths: [
        './node_modules/breakpoint-sass/stylesheets/',
        './node_modules/@fortawesome/fontawesome-free/scss'
      ],
      errLogToConsole: true,
      outputStyle: 'expanded'
      
    }).on('error', sass.logError))

    // Run autoprefixer. Supports ie9 and above
    .pipe(prefix({
      browsers: ['last 2 versions'],
      cascade: false
    }))

    // Write sourcemaps.
    .pipe(sourcemaps.write())

    // Write the resulting CSS in the output folder.
    .pipe(gulp.dest(cssDir))

    // Reload it.
    .pipe(livereload());

});

// Keep an eye on Sass files for changes...
gulp.task('watch', function () {
  livereload.listen();
  gulp.watch(sassFiles, gulp.series('sass'));
});

// What tasks does running gulp trigger?
gulp.task('default', gulp.series('sass', 'watch'));
