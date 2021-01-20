const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

// Create React files
mix.react('resources/js/onboarding.jsx', 'public/js').version();
mix.react('resources/js/onboardingv2.jsx', 'public/js').version();
mix.react('resources/js/usercp.jsx', 'public/js').version();
mix.react('resources/js/recoverpw.jsx', 'public/js').version();
