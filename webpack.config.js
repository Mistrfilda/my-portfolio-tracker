var Encore = require('@symfony/webpack-encore');
require('dotenv').config();

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

var publicPath = '/my-portfolio-tracker/www/build/admin';
var prodHost = process.env.PROD_HOST;
var outputPath = './www/build/admin';
if (prodHost !== undefined) {
    publicPath = '/build/admin';
	outputPath = './www/prod-build/build/admin'
}

Encore
    .setOutputPath(outputPath)
    .setPublicPath(publicPath)
    .addEntry('admin', './assets/app.ts')
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })
    .enablePostCssLoader()
    .enableTypeScriptLoader();

module.exports = Encore.getWebpackConfig();
