/* gulpfile.js */
const { src, dest, series, parallel, watch } = require("gulp");

// eslint-disable-next-line import/no-unresolved
const uswds = require('@uswds/compile');

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

/**
 * Exports
 * Add as many as you need
 */

exports.init = uswds.init;
exports.compile = series(uswds.copyAssets, uswds.compile);
exports.watch = uswds.watch;
exports.default = exports.compile;
