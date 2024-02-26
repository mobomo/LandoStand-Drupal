/*
* * * * * ==============================
* * * * * ==============================
* * * * * ==============================
* * * * * ==============================
========================================
========================================
========================================
----------------------------------------
USWDS SASS GULPFILE
----------------------------------------
*/
const uswds = require("@uswds/compile");

// Custom variables
const pkg = require('./package.json');
const {series, watch, src} = require("gulp");
const gulp = require('gulp');
const sassLint = require('gulp-sass-lint');
const eslint = require('gulp-eslint');
const rename = require("gulp-rename");
const path = require("path");
let uglify = require('gulp-uglify-es').default;
const del = require("del");

/**
 * USWDS version
 */

uswds.settings.version = 3;

/**
 * Path settings
 * Set as many as you need
 */

// Where to output
uswds.paths.dist.css = './css';

// Custom scss files
uswds.paths.dist.custom = './components/**';

uswds.paths.dist.fonts = './assets/fonts';
uswds.paths.dist.img = './assets/img';
uswds.paths.dist.js = './assets/js';
uswds.paths.dist.scss = './assets/scss';

uswds.paths.src.projectSass = './components/**';

// Custom Functions

function cleanJs() {
  del([pkg.paths.dist.js]);
  return Promise.resolve('js folder deleted.');
}

function cleanCss() {
  del([pkg.paths.dist.css]);
  return Promise.resolve('css folder deleted.');
}

function scssLint() {
  return src(pkg.paths.scss)
      .pipe(sassLint())
      .pipe(sassLint.format())
}

function scssLintCi() {
  return src(pkg.paths.scss)
      .pipe(sassLint())
      .pipe(sassLint.format())
      .pipe(sassLint.failOnError())
}

// After we let uswds compile build the css files we need to minify them.
function minifyCss() {
  return src(pkg.paths.css)
      // .pipe(rename(function (file) {
      //   // this removes the last parent directory of the relative file path
      //   let parts = file.dirname.split("/");
      //   parts = parts.slice(1);
      //   parts = parts.join("/");
      //   file.dirname = path.dirname(parts + "/" + file.basename);
      //   console.log("file.dirname = " + file.dirname);
      // }))
      .pipe(rename({"suffix": '.min'}))
      .pipe(gulp.dest(pkg.paths.dist.css));
}

function buildJs() {
  return src(pkg.paths.js)
      .pipe(rename(function (file) {
        // this removes the last parent directory of the relative file path
        let parts = file.dirname.split("/");
        parts = parts.slice(1);
        parts = parts.join("/");
        file.dirname = path.dirname(parts + "/" + file.basename);
      }))
      .pipe(rename({"suffix": '.min'}))
      .pipe(uglify())
      .pipe(gulp.dest(pkg.paths.dist.js));
}

function jsLint() {
  return src(pkg.paths.js)
      .pipe(eslint())
      .pipe(eslint.format())
}

function jsLintCi() {
  return src(pkg.paths.js)
      .pipe(eslint())
      .pipe(eslint.format())
      .pipe(eslint.failAfterError());
}

/**
 * Exports
 * Add as many as you need
 */

// copyAll + compile
exports.init = uswds.init;

exports.compileSass = series(cleanCss, scssLint, uswds.compileSass, minifyCss);
exports.compileJs = series(cleanJs, jsLint, buildJs);
exports.compileAll = series(this.compileJs, this.compileSass);

exports.lint = series(scssLint, jsLint);

exports.lintCi = series(scssLintCi, jsLintCi);

exports.watch = series(this.compileAll, () => {
  watch(pkg.paths.scss, series([this.compileSass]));
  watch(pkg.paths.js, series([this.compileJs]));
});
exports.updateUswds = uswds.updateUswds;

exports.default = series(this.init, this.compileAll);