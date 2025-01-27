<?php

declare(strict_types = 1);

namespace App\Http\Psr7;

use Nette\Utils\Json;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestInterface;

class Psr7RequestFactory
{

	private Psr17Factory $psr17Factory;

	public function __construct()
	{
		$this->psr17Factory = new Psr17Factory();
	}

	/**
	 * @param array<string, string> $headers
	 */
	public function createGETRequest(string $url, array $headers = []): RequestInterface
	{
		$request = $this->psr17Factory->createRequest('GET', $url);
		foreach ($headers as $key => $value) {
			$request = $request->withHeader($key, $value);
		}

		return $request;
	}

	/**
	 * @param array<mixed> $jsonBody
	 * @param array<string, string> $headers
	 */
	public function createPOSTRequest(
		string $url,
		array $jsonBody = [],
		array $headers = [],
	): RequestInterface
	{
		$request = $this->psr17Factory->createRequest('POST', $url);
		foreach ($headers as $key => $value) {
			$request = $request->withHeader($key, $value);
		}

		return $request->withBody($this->psr17Factory->createStream(Json::encode($jsonBody)));
	}

}
