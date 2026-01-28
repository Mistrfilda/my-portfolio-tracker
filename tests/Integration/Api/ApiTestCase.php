<?php

declare(strict_types = 1);

namespace App\Test\Integration\Api;

use App\Api\SlimAppFactory;
use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\App;
use function assert;

abstract class ApiTestCase extends TestCase
{

	protected App $app;

	protected function setUp(): void
	{
		parent::setUp();

		$configurator = Bootstrap::boot(true);
		$configurator->addConfig(__DIR__ . '/api_test.neon');
		$container = $configurator->createContainer();

		$slimAppFactory = $container->getByType(SlimAppFactory::class);
		assert($slimAppFactory instanceof SlimAppFactory);
		$this->app = $slimAppFactory->create();
	}

}
