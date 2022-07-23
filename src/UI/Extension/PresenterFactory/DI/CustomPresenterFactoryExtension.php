<?php

declare(strict_types = 1);

namespace App\UI\Extension\PresenterFactory\DI;

use App\UI\Extension\PresenterFactory\CustomPresenterFactory;
use Nette\Application\IPresenterFactory;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use function assert;

class CustomPresenterFactoryExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'presenterDir' => Expect::string(),
			'customMapping' => Expect::arrayOf(Expect::string()),
		])->castTo('array');
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$nettePresenterFactory = $builder->getDefinitionByType(IPresenterFactory::class);
		assert($nettePresenterFactory instanceof ServiceDefinition);
		$arguments = $nettePresenterFactory->getFactory()->arguments;

		/** @var array<string, string> $config */
		$config = $this->config;

		$parameters = [
			'customMapping' => $config['customMapping'],
			'factory' => $arguments[0],
		];
		$nettePresenterFactory->setFactory(CustomPresenterFactory::class, $parameters);
	}

}
