<?php

declare(strict_types = 1);

namespace App\UI\Extension\PresenterFactory;

use Nette\Application\PresenterFactory;
use function array_key_exists;
use function array_search;

class CustomPresenterFactory extends PresenterFactory
{

	/**
	 * @param array<string, string> $customMapping
	 */
	public function __construct(private array $customMapping, callable|null $factory = null)
	{
		parent::__construct($factory);
	}

	public function formatPresenterClass(string $presenter): string
	{
		if (array_key_exists($presenter, $this->customMapping)) {
			return $this->customMapping[$presenter];
		}

		return parent::formatPresenterClass($presenter);
	}

	public function unformatPresenterClass(string $class): string|null
	{
		if (($search = array_search($class, $this->customMapping, true)) !== false) {
			return $search;
		}

		return parent::unformatPresenterClass($class);
	}

}
