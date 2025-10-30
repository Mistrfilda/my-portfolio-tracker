<?php

declare(strict_types = 1);

namespace App\Doctrine\EventListener;

use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand;
use Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

class SchemaFilterListener
{

	private string $configurationTableName;

	private bool $enabled = false;

	public function __construct(private EntityManagerInterface $entityManager)
	{
		$this->configurationTableName = 'doctrine_migrations';
	}

	public function onConsoleCommand(ConsoleCommandEvent $event): void
	{
		$command = $event->getCommand();

		if (!$command instanceof ValidateSchemaCommand && !$command instanceof UpdateCommand) {
			return;
		}

		$this->enabled = true;
		$this->entityManager->getConnection()
			->getConfiguration()
			->setSchemaAssetsFilter($this);
	}

	/** @param AbstractAsset<OptionallyQualifiedName>|string $asset */
	public function __invoke(AbstractAsset|string $asset): bool
	{
		if (!$this->enabled) {
			return true;
		}

		if ($asset instanceof AbstractAsset) {
			$asset = $asset->getName();
		}

		return $asset !== $this->configurationTableName;
	}

}
