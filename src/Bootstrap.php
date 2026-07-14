<?php

declare(strict_types = 1);

namespace App;

use Nette\Bootstrap\Configurator;
use function dirname;
use function getenv;
use function is_file;

class Bootstrap
{

	public static function boot(bool $forceDebugMode = false, bool $enableTracy = true): Configurator
	{
		$configurator = new Configurator();
		$appDir = dirname(__DIR__);

		if ($forceDebugMode) {
			$configurator->setDebugMode($forceDebugMode);
		}

		if ($enableTracy) {
			//$configurator->setDebugMode(true);
			//$configurator->setDebugMode('secret@23.75.345.200'); // enable for your remote IP
			$configurator->enableTracy($appDir . '/log');
		}

		$configurator->setTimeZone('Europe/Prague');
		$configurator->setTempDirectory($appDir . '/temp');

		$configurator->addConfig($appDir . '/config/config.neon');
		$configurator->addConfig($appDir . '/config/forms.neon');
		$configurator->addConfig($appDir . '/config/routing.neon');
		$configurator->addConfig($appDir . '/config/rabbitmq.neon');

		if (is_file($appDir . '/config/config.local.neon')) {
			$configurator->addConfig($appDir . '/config/config.local.neon');
		}

		if (is_file($appDir . '/config/config-docker.local.neon')) {
			$configurator->addConfig($appDir . '/config/config-docker.local.neon');
		}

		$integrationTestLocalConfig = $appDir . '/tests/Integration/integration.test.local.neon';
		if (getenv('MY_PORTFOLIO_TRACKER_CODEX') === '1' && is_file($integrationTestLocalConfig)) {
			$configurator->addConfig($appDir . '/tests/Integration/integration.test.neon');
			$configurator->addConfig($integrationTestLocalConfig);
		}

		return $configurator;
	}

}
