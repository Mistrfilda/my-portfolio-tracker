<?php

declare(strict_types = 1);

namespace App;

use Nette\Bootstrap\Configurator;
use function dirname;
use function is_file;

class Bootstrap
{

	public static function boot(): Configurator
	{
		$configurator = new Configurator();
		$appDir = dirname(__DIR__);

		//$configurator->setDebugMode('secret@23.75.345.200'); // enable for your remote IP
		$configurator->enableTracy($appDir . '/log');

		$configurator->setTimeZone('Europe/Prague');
		$configurator->setTempDirectory($appDir . '/temp');

		$configurator->createRobotLoader()
			->addDirectory(__DIR__)
			->register();

		$configurator->addConfig($appDir . '/config/config.neon');

		if (is_file($appDir . '/config/config.local.neon')) {
			$configurator->addConfig($appDir . '/config/config.local.neon');
		}

		return $configurator;
	}

}
