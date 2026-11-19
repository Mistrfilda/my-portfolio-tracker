import Encore from '@symfony/webpack-encore';
import dotenv from 'dotenv';

const dockerDev = process.env.MY_PORTFOLIO_TRACKER_DOCKER_DEV === '1';
if (!dockerDev) {
	dotenv.config({ quiet: true });
}

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

const productionBuild = process.env.PROD_HOST !== undefined;
const publicPath = dockerDev || productionBuild ? '/build/admin' : '/my-portfolio-tracker/www/build/admin';
const outputPath = productionBuild ? './www/prod-build/build/admin' : './www/build/admin';

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
