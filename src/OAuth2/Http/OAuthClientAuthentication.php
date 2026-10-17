<?php

declare(strict_types = 1);

namespace App\OAuth2\Http;

use App\OAuth2\Client\OAuthClient;
use App\OAuth2\Client\OAuthClientRepository;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;

class OAuthClientAuthentication
{

	public function __construct(private OAuthClientRepository $clients)
	{
	}

	public function authenticate(ServerRequestInterface $request): OAuthClient
	{
		$body = $request->getParsedBody();
		$body = is_array($body) ? $body : [];
		$id = $body['client_id'] ?? null;
		$secret = $body['client_secret'] ?? null;
		if ($request->hasHeader('Authorization')) {
			if (count($request->getHeader('Authorization')) !== 1 || $id !== null || $secret !== null) {
				throw OAuthServerException::invalidRequest('client_id', 'Use a single client authentication method.');
			}

			$header = $request->getHeaderLine('Authorization');
			$decoded = str_starts_with(strtolower($header), 'basic ') ? base64_decode(substr($header, 6), true) : false;
			if ($decoded === false || !str_contains($decoded, ':')) {
				throw OAuthServerException::invalidClient($request);
			}

			[$id, $secret] = explode(':', $decoded, 2);
		}

		if (!is_string($id) || ($secret !== null && !is_string($secret))) {
			throw OAuthServerException::invalidClient($request);
		}

		$client = $this->clients->getClientEntity($id);
		if ($client === null || ($client->isConfidential() && !$this->clients->validateClient($id, $secret, null))) {
			throw OAuthServerException::invalidClient($request);
		}

		if (!$client->isConfidential() && $secret !== null) {
			throw OAuthServerException::invalidClient($request);
		}

		return $client;
	}

}
