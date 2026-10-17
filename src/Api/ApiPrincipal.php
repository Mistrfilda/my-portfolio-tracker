<?php

declare(strict_types = 1);

namespace App\Api;

use App\Admin\AppAdmin;

readonly class ApiPrincipal
{

	/** @param list<string> $scopes */
	public function __construct(public string $clientId, public AppAdmin|null $user, public array $scopes)
	{
	}

}
