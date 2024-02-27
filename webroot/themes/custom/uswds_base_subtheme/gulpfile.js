/* gulpfile.js */
const { src, dest, series, parallel, watch } = require("gulp");

// eslint-disable-next-line import/no-unresolved
const uswds = require('@uswds/compile');

const sassLint = require('gulp-sass-lint');
const esLint = require('gulp-eslint');

/**
 * USWDS version
 */

uswds.settings.version = 3;

/**
 * Path settings
 * Set as many as you need
 */

uswds.paths.dist.css = './assets/css';
uswds.paths.dist.theme = './components';
uswds.paths.dist.fonts = './assets/fonts';
uswds.paths.dist.img = './assets/img';
uswds.paths.dist.js = './assets/js';
uswds.paths.dist.scss = './components';

function scssLintCi() {
  return src(uswds.paths.dist.scss)
      .pipe(sassLint())
      .pipe(sassLint.format())
      .pipe(sassLint.failOnError())
}


function jsLintCi() {
  return src(uswds.paths.dist.js)
      .pipe(esLint())
      .pipe(esLint.format())
      .pipe(esLint.failAfterError());
}


/**
 * Exports
 * Add as many as you need
 */

exports.init = uswds.init;
exports.compile = series(uswds.copyAssets, uswds.compile);
exports.watch = uswds.watch;
exports.default = exports.compile;

exports.lintCi = series(scssLintCi, jsLintCi);