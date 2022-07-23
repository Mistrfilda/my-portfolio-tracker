<?php

declare(strict_types = 1);

namespace App\Utils\Sortable;

use Ramsey\Uuid\UuidInterface;

class SortQueryParameters
{

	/** @var array<string, int|string|UuidInterface> */
	private array $parameters;

	/**
	 * @param array<string, int|string|UuidInterface> $parameters
	 */
	public function __construct(array $parameters = [])
	{
		$this->parameters = $parameters;
	}

	public function getParameter(string $key): int|string|UuidInterface
	{
		if (!$this->hasParameter($key)) {
			throw new SortException(sprintf('Missing parameter %s', $key));
		}

		return $this->parameters[$key];
	}

	public function hasParameter(string $key): bool
	{
		return array_key_exists($key, $this->parameters);
	}

}
