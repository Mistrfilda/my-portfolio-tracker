<?php

declare(strict_types = 1);

namespace App\Http\Psr7;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestInterface;

class Psr7RequestFactory
{

	private Psr17Factory $psr17Factory;

	public function __construct()
	{
		$this->psr17Factory = new Psr17Factory();
	}

	public function createGETRequest(string $url): RequestInterface
	{
		return $this->psr17Factory->createRequest('GET', $url);
	}

}
