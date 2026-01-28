<?php

declare(strict_types = 1);

namespace App\Utils\Nette;

use Nette\DI\Container;
use Psr\Container\ContainerInterface;

class NettePsrContainerAdapter implements ContainerInterface
{

	public function __construct(private Container $container)
	{
	}

	public function get(string $id): mixed
	{
		return $this->container->getService($id);
	}

	public function has(string $id): bool
	{
		return $this->container->hasService($id);
	}

}
