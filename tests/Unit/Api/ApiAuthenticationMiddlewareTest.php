<?php

declare(strict_types = 1);

namespace App\Test\Unit\Api;

use App\Admin\CurrentAppAdminGetter;
use App\Api\ApiAuthenticationMiddleware;
use App\OAuth2\Authorization\OAuthUserAuthenticator;
use App\OAuth2\Client\OAuthClientRepository;
use App\OAuth2\Http\OAuthPublicOriginResolver;
use App\OAuth2\Http\OAuthResponses;
use App\OAuth2\OAuthScope;
use App\OAuth2\OAuthServerFactory;
use App\OAuth2\Token\OAuthTokenStore;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

class ApiAuthenticationMiddlewareTest extends TestCase
{

	public function testUnauthorizedIncludesResourceMetadataAndScopeWhenConfigured(): void
	{
		$currentUser = $this->createMock(CurrentAppAdminGetter::class);
		$currentUser->expects(self::once())->method('beginApiRequest');
		$currentUser->expects(self::once())->method('endApiRequest');

		$handler = $this->createMock(RequestHandlerInterface::class);
		$handler->expects(self::never())->method('handle');

		$middleware = new ApiAuthenticationMiddleware(
			OAuthScope::MCP_READ,
			false,
			self::createStub(MiddlewareInterface::class),
			self::createStub(OAuthServerFactory::class),
			self::createStub(OAuthTokenStore::class),
			self::createStub(OAuthClientRepository::class),
			self::createStub(OAuthUserAuthenticator::class),
			$currentUser,
			new OAuthResponses(),
			requireUser: true,
			resourceMetadataPath: '/.well-known/oauth-protected-resource/api/mcp',
			originResolver: new OAuthPublicOriginResolver(),
		);

		$request = (new ServerRequestFactory())
			->createServerRequest('POST', 'http://internal/api/mcp')
			->withHeader('X-Forwarded-Proto', 'https')
			->withHeader('X-Forwarded-Host', 'portfolio.lan');

		$response = $middleware->process($request, $handler);

		self::assertSame(401, $response->getStatusCode());
		self::assertSame(
			'Bearer error="invalid_token", resource_metadata="https://portfolio.lan/.well-known/oauth-protected-resource/api/mcp", scope="mcp:read"',
			$response->getHeaderLine('WWW-Authenticate'),
		);
	}

	public function testUnauthorizedWithoutResourceMetadataPreservesLegacyHeader(): void
	{
		$middleware = new ApiAuthenticationMiddleware(
			OAuthScope::API_READ,
			false,
			self::createStub(MiddlewareInterface::class),
			self::createStub(OAuthServerFactory::class),
			self::createStub(OAuthTokenStore::class),
			self::createStub(OAuthClientRepository::class),
			self::createStub(OAuthUserAuthenticator::class),
			self::createStub(CurrentAppAdminGetter::class),
			new OAuthResponses(),
		);

		$request = (new ServerRequestFactory())->createServerRequest('GET', 'http://portfolio.lan/api/v1/ping');
		$response = $middleware->process($request, self::createStub(RequestHandlerInterface::class));

		self::assertSame(401, $response->getStatusCode());
		self::assertSame('Bearer error="invalid_token"', $response->getHeaderLine('WWW-Authenticate'));
	}

}
