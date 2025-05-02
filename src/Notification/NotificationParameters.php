<?php

declare(strict_types = 1);

namespace App\Notification;

class NotificationParameters
{

	/** @var array<string, string|int> */
	private array $parameters;

	public function __construct()
	{
		$this->parameters = [];
	}

	public function addParameter(NotificationParameterEnum $key, string|int $value): void
	{
		$this->parameters[$key->value] = $value;
	}

	/** @return  array<string, string|int> */
	public function getParameters(): array
	{
		return $this->parameters;
	}

}
