<?php

declare(strict_types = 1);

namespace App\UI\Extension\Webpack\DI;

use App\UI\Extension\Webpack\WebpackAssetsFactory;
use App\UI\Extension\Webpack\WebpackLatteExtension;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use function assert;

class WebpackAssetExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'assetsDirs' => Expect::arrayOf(Expect::string()),
		])->castTo('array');
	}

	public function loadConfiguration(): void
	{
		/** @var array<string, string> $config */
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('webpackAssetFactory'))
			->setType(WebpackAssetsFactory::class)
			->setArguments([
				'assetsDirs' => $config['assetsDirs'],
			]);
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$latteFactory = $builder->getDefinitionByType(LatteFactory::class);
		assert($latteFactory instanceof FactoryDefinition);

		$definition = $latteFactory->getResultDefinition();

		$definition->addSetup('addExtension', [new Statement(WebpackLatteExtension::class)]);
	}

}
