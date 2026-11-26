<?php

declare(strict_types = 1);

namespace App\Test\Unit\OAuth2\Http;

use App\OAuth2\Client\OAuthClient;
use App\OAuth2\Client\OAuthClientFacade;
use App\OAuth2\Client\OAuthClientRepository;
use App\OAuth2\Http\OAuthDynamicRegistrationController;
use App\OAuth2\Http\OAuthRateLimiter;
use App\OAuth2\Http\OAuthResponses;
use App\OAuth2\Token\OAuthTokenStore;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class OAuthDynamicRegistrationControllerTest extends TestCase
{

	public function testRegistersLoopbackPublicMcpClient(): void
	{
		$savedClients = [];
		$clientRepository = $this->createMock(OAuthClientRepository::class);
		$clientRepository->expects(self::once())
			->method('save')
			->with(self::callback(static function (OAuthClient $client) use (&$savedClients): bool {
				$savedClients[] = $client;
				return !$client->isConfidential()
					&& $client->getScopes() === ['mcp:read']
					&& $client->getRedirectUri() === ['http://localhost:54321/callback'];
			}));

		$now = new ImmutableDateTime('2026-05-01 10:00:00');
		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$facade = new OAuthClientFacade(
			$clientRepository,
			self::createStub(OAuthTokenStore::class),
			$datetimeFactory,
		);

		$rateLimiter = $this->createMock(OAuthRateLimiter::class);
		$rateLimiter->expects(self::once())
			->method('allow')
			->with('register:192.168.1.50', 10)
			->willReturn(true);

		$controller = new OAuthDynamicRegistrationController(
			$facade,
			$rateLimiter,
			new OAuthResponses(),
			enabled: true,
		);

		$request = $this->createJsonRequest([
			'client_name' => 'Claude Code',
			'redirect_uris' => ['http://localhost:54321/callback'],
			'grant_types' => ['authorization_code', 'refresh_token'],
			'response_types' => ['code'],
			'token_endpoint_auth_method' => 'none',
			'scope' => 'mcp:read',
		]);

		$response = $controller->register($request, new Response());

		self::assertSame(201, $response->getStatusCode());
		self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));

		$body = Json::decode((string) $response->getBody(), forceArrays: true);
		self::assertIsArray($body);
		self::assertCount(1, $savedClients);
		self::assertSame($savedClients[0]->getIdentifier(), $body['client_id']);
		self::assertSame('Claude Code', $body['client_name']);
		self::assertSame(['http://localhost:54321/callback'], $body['redirect_uris']);
		self::assertSame(['authorization_code', 'refresh_token'], $body['grant_types']);
		self::assertSame(['code'], $body['response_types']);
		self::assertSame('none', $body['token_endpoint_auth_method']);
		self::assertSame('mcp:read', $body['scope']);
		self::assertSame($now->getTimestamp(), $body['client_id_issued_at']);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	#[DataProvider('provideInvalidPayloads')]
	public function testRejectsUnsafeOrInvalidMetadata(array $payload, string $expectedError): void
	{
		$clientRepository = $this->createMock(OAuthClientRepository::class);
		$clientRepository->expects(self::never())->method('save');
		$facade = new OAuthClientFacade(
			$clientRepository,
			self::createStub(OAuthTokenStore::class),
			self::createStub(DatetimeFactory::class),
		);
		$rateLimiter = self::createStub(OAuthRateLimiter::class);
		$rateLimiter->method('allow')->willReturn(true);

		$controller = new OAuthDynamicRegistrationController(
			$facade,
			$rateLimiter,
			new OAuthResponses(),
			enabled: true,
		);
		$response = $controller->register($this->createJsonRequest($payload), new Response());

		self::assertSame(400, $response->getStatusCode());
		$body = Json::decode((string) $response->getBody(), forceArrays: true);
		self::assertIsArray($body);
		self::assertSame($expectedError, $body['error']);
	}

	public function testRejectsWhenDisabled(): void
	{
		$rateLimiter = $this->createMock(OAuthRateLimiter::class);
		$rateLimiter->expects(self::never())->method('allow');

		$controller = new OAuthDynamicRegistrationController(
			self::createStub(OAuthClientFacade::class),
			$rateLimiter,
			new OAuthResponses(),
			enabled: false,
		);

		$response = $controller->register(
			$this->createJsonRequest([
				'client_name' => 'Claude Code',
				'redirect_uris' => ['http://localhost:54321/callback'],
			]),
			new Response(),
		);

		self::assertSame(403, $response->getStatusCode());
	}

	public function testReturnsTooManyRequestsWhenRateLimited(): void
	{
		$rateLimiter = self::createStub(OAuthRateLimiter::class);
		$rateLimiter->method('allow')->willReturn(false);

		$controller = new OAuthDynamicRegistrationController(
			self::createStub(OAuthClientFacade::class),
			$rateLimiter,
			new OAuthResponses(),
			enabled: true,
		);

		$response = $controller->register(
			$this->createJsonRequest([
				'client_name' => 'Claude Code',
				'redirect_uris' => ['http://localhost:54321/callback'],
			]),
			new Response(),
		);

		self::assertSame(429, $response->getStatusCode());
	}

	/**
	 * @return iterable<string, array{array<string, mixed>, string}>
	 */
	public static function provideInvalidPayloads(): iterable
	{
		yield 'public https redirect uri' => [
			[
				'client_name' => 'Evil',
				'redirect_uris' => ['https://evil.example/cb'],
			],
			'invalid_redirect_uri',
		];

		yield 'confidential auth method' => [
			[
				'client_name' => 'Client',
				'redirect_uris' => ['http://localhost:54321/callback'],
				'token_endpoint_auth_method' => 'client_secret_post',
			],
			'invalid_client_metadata',
		];

		yield 'elevated scope home:write' => [
			[
				'client_name' => 'Client',
				'redirect_uris' => ['http://localhost:54321/callback'],
				'scope' => 'home:write',
			],
			'invalid_client_metadata',
		];

		yield 'elevated scope portfolio:read' => [
			[
				'client_name' => 'Client',
				'redirect_uris' => ['http://localhost:54321/callback'],
				'scope' => 'mcp:read portfolio:read',
			],
			'invalid_client_metadata',
		];

		yield 'client_credentials grant type' => [
			[
				'client_name' => 'Client',
				'redirect_uris' => ['http://localhost:54321/callback'],
				'grant_types' => ['authorization_code', 'client_credentials'],
			],
			'invalid_client_metadata',
		];

		yield 'missing authorization_code grant type' => [
			[
				'client_name' => 'Client',
				'redirect_uris' => ['http://localhost:54321/callback'],
				'grant_types' => ['refresh_token'],
			],
			'invalid_client_metadata',
		];

		yield 'invalid response type' => [
			[
				'client_name' => 'Client',
				'redirect_uris' => ['http://localhost:54321/callback'],
				'response_types' => ['token'],
			],
			'invalid_client_metadata',
		];

		yield 'empty client name' => [
			[
				'client_name' => '   ',
				'redirect_uris' => ['http://localhost:54321/callback'],
			],
			'invalid_client_metadata',
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function createJsonRequest(array $payload): ServerRequestInterface
	{
		$request = (new ServerRequestFactory())->createServerRequest(
			'POST',
			'http://portfolio.lan/api/oauth/register',
			['REMOTE_ADDR' => '192.168.1.50'],
		)->withHeader('Content-Type', 'application/json');
		$request->getBody()->write(Json::encode($payload));
		$request->getBody()->rewind();

		return $request;
	}

}
