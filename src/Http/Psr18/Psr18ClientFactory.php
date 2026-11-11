<?php

declare(strict_types = 1);

namespace App\Http\Psr18;

use GuzzleHttp\Client;
use Psr\Http\Client\ClientInterface;

class Psr18ClientFactory
{

	/**
	 * @param array{connect_timeout?: int|float, timeout?: int|float, verify?: bool|string}|null $options
	 */
	public function getClient(array|null $options = null): ClientInterface
	{
		if ($options !== null) {
			return new Client($options);
		}

		return new Client();
	}

}
