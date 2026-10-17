<?php

declare(strict_types = 1);

namespace App\OAuth2\Http;

readonly class AuthorizationPage
{

	/** @param list<string> $scopeLabels */
	public function __construct(
		public string $clientName,
		public array $scopeLabels,
		public string $transaction,
		public string $action,
		public string|null $error = null,
	)
	{
	}

}
