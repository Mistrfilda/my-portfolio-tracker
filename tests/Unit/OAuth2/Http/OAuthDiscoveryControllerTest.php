<?php

declare(strict_types = 1);

namespace App\Test\Unit\OAuth2\Http;

use App\OAuth2\Http\OAuthDiscoveryController;
use App\OAuth2\Http\OAuthPublicOriginResolver;
use App\OAuth2\Http\OAuthResponses;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class OAuthDiscoveryControllerTest extends TestCase
{

	public function testProtectedResourceMetadataUsesRequestOrigin(): void
	{
		$controller = new OAuthDiscoveryController(
			new OAuthPublicOriginResolver(),
			new OAuthResponses(),
			dynamicClientRegistrationEnabled: true,
		);

		$request = (new ServerRequestFactory())->createServerRequest(
			'GET',
			'http://portfolio.lan/.well-known/oauth-protected-resource/api/mcp',
		);

		$response = $controller->protectedResource($request, new Response());

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));

		$body = Json::decode((string) $response->getBody(), forceArrays: true);
		self::assertSame([
			'resource' => 'http://portfolio.lan/api/mcp',
			'authorization_servers' => ['http://portfolio.lan'],
			'scopes_supported' => ['mcp:read'],
			'bearer_methods_supported' => ['header'],
		], $body);
	}

	public function testAuthorizationServerMetadataIncludesRegistrationEndpointAndRespectsProxyHeaders(): void
	{
		$controller = new OAuthDiscoveryController(
			new OAuthPublicOriginResolver(),
			new OAuthResponses(),
			dynamicClientRegistrationEnabled: true,
		);

		$request = (new ServerRequestFactory())
			->createServerRequest('GET', 'http://internal-nginx/.well-known/oauth-authorization-server')
			->withHeader('X-Forwarded-Proto', 'https')
			->withHeader('X-Forwarded-Host', 'portfolio.home.arpa')
			->withHeader('X-Forwarded-Port', '443');

		$response = $controller->authorizationServer($request, new Response());

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));

		$body = Json::decode((string) $response->getBody(), forceArrays: true);
		self::assertSame([
			'issuer' => 'https://portfolio.home.arpa',
			'authorization_endpoint' => 'https://portfolio.home.arpa/api/oauth/authorize',
			'token_endpoint' => 'https://portfolio.home.arpa/api/oauth/token',
			'revocation_endpoint' => 'https://portfolio.home.arpa/api/oauth/revoke',
			'response_types_supported' => ['code'],
			'grant_types_supported' => ['authorization_code', 'refresh_token'],
			'code_challenge_methods_supported' => ['S256'],
			'token_endpoint_auth_methods_supported' => ['none', 'client_secret_basic', 'client_secret_post'],
			'scopes_supported' => ['mcp:read'],
			'registration_endpoint' => 'https://portfolio.home.arpa/api/oauth/register',
		], $body);
	}

	public function testAuthorizationServerMetadataOmitsRegistrationEndpointWhenDisabled(): void
	{
		$controller = new OAuthDiscoveryController(
			new OAuthPublicOriginResolver('https://portfolio.local/'),
			new OAuthResponses(),
			dynamicClientRegistrationEnabled: false,
		);

		$request = (new ServerRequestFactory())
			->createServerRequest('GET', 'http://127.0.0.1/.well-known/oauth-authorization-server');

		$response = $controller->authorizationServer($request, new Response());
		$body = Json::decode((string) $response->getBody(), forceArrays: true);

		self::assertIsArray($body);
		self::assertSame('https://portfolio.local', $body['issuer']);
		self::assertArrayNotHasKey('registration_endpoint', $body);
	}

}
