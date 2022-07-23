<?php

declare(strict_types = 1);

namespace App\UI\Extension\Latte;

use App\UI\Extension\Svg\SvgLatteExtension;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use function assert;

class LatteMacrosExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'svgDir' => Expect::string(),
		])->castTo('array');
	}

	public function beforeCompile(): void
	{
		/** @var array<string, string> $config */
		$config = $this->getConfig();

		$builder = $this->getContainerBuilder();

		$latteFactory = $builder->getDefinitionByType(LatteFactory::class);
		assert($latteFactory instanceof FactoryDefinition);

		$definition = $latteFactory->getResultDefinition();

		$svgMacroExtension = new Statement(SvgLatteExtension::class);
		$svgMacroExtension->arguments = [
			'svgDir' => $config['svgDir'],
		];

		$definition->addSetup('addExtension', [$svgMacroExtension]);
	}

}
