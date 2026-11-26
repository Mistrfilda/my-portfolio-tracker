<?php

declare(strict_types = 1);

namespace App\OAuth2\Http;

use App\OAuth2\OAuthScope;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class OAuthDiscoveryController
{

	public function __construct(
		private OAuthPublicOriginResolver $originResolver,
		private OAuthResponses $responses,
		private bool $dynamicClientRegistrationEnabled = true,
	)
	{
	}

	public function protectedResource(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$origin = $this->originResolver->resolve($request);
		$payload = [
			'resource' => $origin . '/api/mcp',
			'authorization_servers' => [$origin],
			'scopes_supported' => [OAuthScope::MCP_READ->value],
			'bearer_methods_supported' => ['header'],
		];

		$response->getBody()->write(Json::encode($payload));
		return $this->responses->noStore(
			$response->withStatus(200)->withHeader('Content-Type', 'application/json'),
		);
	}

	public function authorizationServer(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$origin = $this->originResolver->resolve($request);
		$payload = [
			'issuer' => $origin,
			'authorization_endpoint' => $origin . '/api/oauth/authorize',
			'token_endpoint' => $origin . '/api/oauth/token',
			'revocation_endpoint' => $origin . '/api/oauth/revoke',
			'response_types_supported' => ['code'],
			'grant_types_supported' => ['authorization_code', 'refresh_token'],
			'code_challenge_methods_supported' => ['S256'],
			'token_endpoint_auth_methods_supported' => ['none', 'client_secret_basic', 'client_secret_post'],
			'scopes_supported' => [OAuthScope::MCP_READ->value],
		];

		if ($this->dynamicClientRegistrationEnabled) {
			$payload['registration_endpoint'] = $origin . '/api/oauth/register';
		}

		$response->getBody()->write(Json::encode($payload));
		return $this->responses->noStore(
			$response->withStatus(200)->withHeader('Content-Type', 'application/json'),
		);
	}

}
