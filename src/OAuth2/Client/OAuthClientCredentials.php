<?php

declare(strict_types = 1);

namespace App\OAuth2\Client;

readonly class OAuthClientCredentials
{

	public function __construct(public OAuthClient $client, public string|null $secret)
	{
	}

}
