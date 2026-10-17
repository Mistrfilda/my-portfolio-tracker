<?php

declare(strict_types = 1);

namespace App\OAuth2\Protocol;

use League\OAuth2\Server\Entities\ScopeEntityInterface;

readonly class ScopeEntity implements ScopeEntityInterface
{

	/** @param non-empty-string $identifier */
	public function __construct(private string $identifier)
	{
	}

	public function getIdentifier(): string
	{
		return $this->identifier;
	}

	public function jsonSerialize(): string
	{
		return $this->identifier;
	}

}
