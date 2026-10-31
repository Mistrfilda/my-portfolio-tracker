import Encore from '@symfony/webpack-encore';
import dotenv from 'dotenv';

dotenv.config({ quiet: true });

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
    .configureBabel((config) => {
		config.plugins.push(['babel-plugin-polyfill-corejs3', {
			method: 'usage-global',
			version: '3.51',
		}]);
    })
    .enablePostCssLoader()
    .enableTypeScriptLoader();

export default await Encore.getWebpackConfig();
