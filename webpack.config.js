var Encore = require('@symfony/webpack-encore');
require('dotenv').config();

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

var publicPath = '/app-starter/www/build/admin';
var prodHost = process.env.PROD_HOST;
if (prodHost !== undefined) {
    publicPath = '/build/admin';
}

Encore
    .setOutputPath('./www/build/admin')
    .setPublicPath(publicPath)
    .addEntry('admin', './assets/app.ts')
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild(['**/*', '!.gitkeep'])
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })
    .enableSassLoader()
    .enablePostCssLoader()
    .enableTypeScriptLoader();

module.exports = Encore.getWebpackConfig();
