var gulp         = require('gulp');
var gulpif       = require('gulp-if');
var sass         = require('gulp-sass');
var uglify       = require('gulp-uglify');
var autoprefixer = require('gulp-autoprefixer');
var cssnano      = require('gulp-cssnano');
var include      = require('gulp-include');
var rev          = require('gulp-rev');
var sourcemaps   = require('gulp-sourcemaps');
var runSequence  = require('run-sequence');
var notify       = require('gulp-notify');
var browserSync  = require('browser-sync').create();
var gutil        = require("gulp-util");
var concat       = require("gulp-concat");
var svgstore     = require('gulp-svgstore');
var svgmin       = require('gulp-svgmin');
var rename       = require('gulp-rename');
var argv         = require('minimist')(process.argv.slice(2));
var isProduction = argv.production;

// smash CSS!
gulp.task('styles', function() {
  return gulp.src([
      'web/assets/scss/main.scss'
    ])
    .pipe(gulpif(!isProduction, sourcemaps.init()))
    .pipe(sass())
    .on('error', notify.onError(function(error) {
       return 'Styles error!' + error;
    }))
    .pipe(autoprefixer())
    .pipe(gulpif(isProduction, cssnano()))
    .pipe(gulp.dest('web/assets/dist/css'))
    .pipe(gulpif(!isProduction, sourcemaps.write('maps')))
    .pipe(gulpif(!isProduction, gulp.dest('web/assets/dist/css')))
    .pipe(browserSync.stream({match: '**/*.css'}))
    .pipe(notify({message: 'Styles smashed.', onLast: true}));
});

// smash javascript!
gulp.task('scripts', function() {
  return gulp.src([
      'web/assets/js/libs/*.js',
      'web/assets/js/main.js'
    ])
    .pipe(include())
    .pipe(concat('main.js'))
    .pipe(gulpif(!isProduction, sourcemaps.init()))
    .on('error', notify.onError(function(error) {
       return 'Script error!' + error;
    }))
    .pipe(gulp.dest('web/assets/dist/js'))
    .pipe(gulpif(!isProduction, sourcemaps.write('maps')))
    .pipe(gulpif(!isProduction, gulp.dest('web/assets/dist/js')))
    .pipe(browserSync.reload({stream:true}))
    .pipe(notify({message: 'Scripts smashed.', onLast: true}));
});

// revision files for production assets
gulp.task('rev', function() {
  return gulp.src(['web/assets/dist/**/*.css', 'web/assets/dist/**/*.js', 'web/assets/dist/svgs-defs.svg'])
    .pipe(rev())
    .pipe(gulp.dest('web/assets/dist'))
    .pipe(rev.manifest())
    .pipe(gulp.dest('web/assets/dist'));
});

// folders to watch for changes
gulp.task('watch', ['build'], function() {
  // Init BrowserSync
  browserSync.init({
    proxy: 'aei-craft.localhost',
    notify: false,
    open: false
  });

  gulp.watch('web/assets/scss/**/*.scss', ['styles']);
  gulp.watch('web/assets/js/**/*.js', ['scripts']);
});

// `gulp clean` - Deletes the build folder entirely.
gulp.task('clean', require('del').bind(null, ['web/assets/dist']));

// `gulp build` - Run all the build tasks but don't clean up beforehand.
gulp.task('build', function(callback) {
  if (isProduction) {
    // production gulpin' (with file revisioning)
    runSequence(
      'clean',
      ['styles','scripts','svgs'],
      'rev',
      callback
    );
  } else {
    // dev gulpin'
    runSequence(
      'clean',
      ['styles','scripts','svgs'],
      callback
    );
  }
});

// SVGs to defs
gulp.task('svgs', function() {
  return gulp.src('web/assets/svgs/*.svg')
    .pipe(svgmin({
        plugins: [{
            removeViewBox: false
        }, {
            removeEmptyAttrs: false
        },{
            mergePaths: false
        },{
            cleanupIDs: false
        }]
    }))
    .pipe(gulp.dest('web/assets/dist/svgs/'))
    .pipe(svgstore({ inlineSvg: true }))
    .pipe(rename({ suffix: '-defs' }))
    .pipe(gulp.dest('web/assets/dist/'));
});


// `gulp` - Run a complete build. To compile for production run `gulp --production`.
gulp.task('default', function() {
  gulp.start('build');
});
