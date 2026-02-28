<?php

declare(strict_types = 1);

namespace App\Test\Integration\Api;

use App\Api\SlimAppFactory;
use App\Test\Integration\IntegrationTestCase;
use Nette\Bootstrap\Configurator;
use Slim\App;
use function assert;

abstract class ApiTestCase extends IntegrationTestCase
{

	protected App $app;

	protected function configureContainer(Configurator $configurator): void
	{
		$configurator->addConfig(__DIR__ . '/api_test.neon');
	}

	protected function setUp(): void
	{
		parent::setUp();

		$slimAppFactory = $this->container->getByType(SlimAppFactory::class);
		assert($slimAppFactory instanceof SlimAppFactory);
		$this->app = $slimAppFactory->create();
	}

}
