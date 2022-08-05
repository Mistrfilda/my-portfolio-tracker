<?php

declare(strict_types = 1);

namespace App\Http\Psr18;

use GuzzleHttp\Client;
use Psr\Http\Client\ClientInterface;

class Psr18ClientFactory
{

	public function getClient(): ClientInterface
	{
		return new Client();
	}

}
