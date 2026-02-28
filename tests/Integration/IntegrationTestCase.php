<?php

declare(strict_types = 1);

namespace App\Test\Integration;

use App\Bootstrap;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Nette\Bootstrap\Configurator;
use Nette\DI\Container;
use PHPUnit\Framework\TestCase;
use const PHP_EOL;

abstract class IntegrationTestCase extends TestCase
{

	private static bool $schemaInitialized = false;

	protected Container $container;

	protected function setUp(): void
	{
		parent::setUp();

		$configurator = Bootstrap::boot(true, false);
		$configurator->addConfig(__DIR__ . '/integration_test.neon');
		$this->configureContainer($configurator);
		$this->container = $configurator->createContainer();

		if (self::$schemaInitialized === false) {
			$this->initializeSchema();
			self::$schemaInitialized = true;
		}
	}

	protected function configureContainer(Configurator $configurator): void
	{
		// Override in subclasses to add additional configuration
	}

	/**
	 * @template T of object
	 * @param class-string<T> $type
	 * @return T
	 */
	protected function getService(string $type): object
	{
		$service = $this->container->getByType($type);
		assert($service instanceof $type);

		return $service;
	}

	private function initializeSchema(): void
	{
		$connection = $this->container->getByType(Connection::class);
		assert($connection instanceof Connection);
		echo 'Initializing schema...' . PHP_EOL;

		$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

		echo 'Dropping all tables...' . PHP_EOL;
		$tables = $connection->createSchemaManager()->listTableNames();
		foreach ($tables as $table) {
			$connection->executeStatement('DROP TABLE IF EXISTS `' . $table . '`');
		}

		echo 'Creating tables...' . PHP_EOL;
		$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

		$dependencyFactory = $this->container->getByType(DependencyFactory::class);
		assert($dependencyFactory instanceof DependencyFactory);

		$dependencyFactory->getMetadataStorage()->ensureInitialized();

		$migrator = $dependencyFactory->getMigrator();
		$planCalculator = $dependencyFactory->getMigrationPlanCalculator();
		$plan = $planCalculator->getPlanUntilVersion(
			$dependencyFactory->getVersionAliasResolver()->resolveVersionAlias('latest'),
		);

		echo 'Migrating database...' . PHP_EOL;
		$migratorConfiguration = new MigratorConfiguration();
		$migrator->migrate($plan, $migratorConfiguration);
		echo 'Schema initialization completed.' . PHP_EOL;
	}

}
