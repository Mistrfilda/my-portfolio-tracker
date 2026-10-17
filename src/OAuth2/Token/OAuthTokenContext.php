<?php

declare(strict_types = 1);

namespace App\OAuth2\Token;

use Mistrfilda\Datetime\Types\ImmutableDateTime;

class OAuthTokenContext
{

	public string|null $clientId = null;

	public string|null $familyId = null;

	public ImmutableDateTime|null $familyExpiresAt = null;

	public string|null $compromisedFamily = null;

	public function reset(): void
	{
		$this->clientId = null;
		$this->familyId = null;
		$this->familyExpiresAt = null;
		$this->compromisedFamily = null;
	}

}
