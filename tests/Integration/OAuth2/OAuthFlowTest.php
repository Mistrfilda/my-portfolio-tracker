<?php

declare(strict_types = 1);

namespace App\Test\Integration\OAuth2;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\OAuth2\Client\OAuthClientCredentials;
use App\OAuth2\Client\OAuthClientFacade;
use App\OAuth2\Token\OAuthTokenStore;
use App\Test\Integration\Api\ApiTestCase;
use App\Utils\TypeValidator;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use const PASSWORD_DEFAULT;
use const PHP_URL_QUERY;

class OAuthFlowTest extends ApiTestCase
{

	private const string REDIRECT = 'com.example.portfolio:/oauth/callback';

	private const string PASSWORD = 'OAuth test password 123!';

	private string $remoteAddress;

	private string $baseUrl = 'https://localhost';

	protected function setUp(): void
	{
		parent::setUp();
		$this->remoteAddress = bin2hex(random_bytes(12));
	}

	public function testClientCredentialsAndScopeEnforcement(): void
	{
		$client = $this->client(true);
		$response = $this->clientToken($client);
		self::assertSame(200, $response->getStatusCode());
		$data = $this->data($response);
		self::assertSame('Bearer', $data['token_type']);
		self::assertSame(600, $data['expires_in']);
		self::assertArrayNotHasKey('refresh_token', $data);
		self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
		$token = TypeValidator::validateString($data['access_token']);
		self::assertSame(200, $this->api('/ping', $token)->getStatusCode());
		self::assertSame(403, $this->api('/home/device/records', $token)->getStatusCode());
		self::assertSame(403, $this->api('/me', $token)->getStatusCode());
	}

	public function testBasicClientAuthentication(): void
	{
		$client = $this->client(true);
		$request = $this->request('POST', '/api/oauth/token', ['grant_type' => 'client_credentials'])
			->withHeader(
				'Authorization',
				'Basic ' . base64_encode($client->client->getIdentifier() . ':' . $client->secret),
			);
		self::assertSame(200, $this->app->handle($request)->getStatusCode());
		$request = $this->request(
			'POST',
			'/api/oauth/token',
			['grant_type' => 'client_credentials', 'client_id' => $client->client->getIdentifier()],
		)
			->withHeader(
				'Authorization',
				'Basic ' . base64_encode($client->client->getIdentifier() . ':' . $client->secret),
			);
		self::assertSame(400, $this->app->handle($request)->getStatusCode());
	}

	public function testRejectsInvalidSecretAndUnregisteredScope(): void
	{
		$client = $this->client(true);
		self::assertSame(401, $this->clientToken($client, ['client_secret' => 'incorrect'])->getStatusCode());
		self::assertSame(400, $this->clientToken($client, ['scope' => 'home:write'])->getStatusCode());
		self::assertSame(400, $this->clientToken($client, ['scope' => 'unknown:scope'])->getStatusCode());
		self::assertSame(
			0,
			TypeValidator::validateInt(
				$this->getService(Connection::class)->fetchOne(
					'SELECT COUNT(*) FROM oauth_token WHERE client_id = ?',
					[$client->client->getIdentifier()],
				),
			),
		);
	}

	public function testPublicClientCannotUseClientCredentialsAndPasswordGrantIsDisabled(): void
	{
		$public = $this->client(false);
		self::assertSame(400, $this->clientToken($public)->getStatusCode());
		$confidential = $this->client(true);
		$response = $this->clientToken(
			$confidential,
			['grant_type' => 'password', 'username' => 'test', 'password' => self::PASSWORD],
		);
		self::assertSame(400, $response->getStatusCode());
		self::assertSame('unsupported_grant_type', $this->data($response)['error']);
	}

	public function testLegacyKeysOnlyWorkOnExistingHomeEndpointsAndCannotDowngradeBearer(): void
	{
		foreach (['/ping', '/stocks', '/stocks/00000000-0000-0000-0000-000000000000', '/dashboard/values', '/me'] as $path) {
			$request = $this->request('GET', '/api/v1' . $path)->withHeader('X-Api-Key', 'test-api-key');
			self::assertSame(401, $this->app->handle($request)->getStatusCode());
		}

		$home = $this->request('GET', '/api/v1/home/device/records')->withHeader('X-Api-Key', 'test-api-key');
		self::assertSame(200, $this->app->handle($home)->getStatusCode());
		self::assertSame(401, $this->app->handle($home->withHeader('X-Api-Key', 'wrong'))->getStatusCode());
		self::assertSame(
			400,
			$this->app->handle($home->withHeader('Authorization', 'Bearer invalid'))->getStatusCode(),
		);
		self::assertSame(
			401,
			$this->app->handle(
				$home->withoutHeader('X-Api-Key')->withHeader('Authorization', 'Bearer invalid'),
			)->getStatusCode(),
		);
		$client = $this->client(true, ['home:read']);
		$data = $this->data($this->clientToken($client, ['scope' => 'home:read']));
		self::assertSame(
			200,
			$this->api('/home/device/records', TypeValidator::validateString($data['access_token']))->getStatusCode(),
		);
	}

	public function testPkceLoginAndRefreshRotationRevokeReplayedFamily(): void
	{
		$client = $this->client(false);
		$verifier = bin2hex(random_bytes(32));
		[$code, $user] = $this->code($client, $verifier);
		$response = $this->exchange($client, $code, $verifier);
		self::assertSame(200, $response->getStatusCode());
		$data = $this->data($response);
		$access = TypeValidator::validateString($data['access_token']);
		$refresh = TypeValidator::validateString($data['refresh_token']);
		$me = $this->api('/me', $access);
		self::assertSame(200, $me->getStatusCode());
		self::assertSame($user->getId()->toString(), $this->data($me)['id']);
		self::assertFalse($this->getService(CurrentAppAdminGetter::class)->isLoggedIn());

		$renewed = $this->refresh($client, $refresh);
		self::assertSame(200, $renewed->getStatusCode());
		$new = $this->data($renewed);
		self::assertNotSame($refresh, $new['refresh_token']);
		self::assertSame(401, $this->api('/ping', $access)->getStatusCode());
		self::assertSame(
			200,
			$this->api('/ping', TypeValidator::validateString($new['access_token']))->getStatusCode(),
		);
		self::assertSame(400, $this->refresh($client, $refresh)->getStatusCode());
		self::assertSame(
			401,
			$this->api('/ping', TypeValidator::validateString($new['access_token']))->getStatusCode(),
		);
		self::assertSame(
			400,
			$this->refresh($client, TypeValidator::validateString($new['refresh_token']))->getStatusCode(),
		);
	}

	public function testWrongVerifierDoesNotConsumeCodeButCodeReuseRevokesTokens(): void
	{
		$client = $this->client(false);
		$verifier = bin2hex(random_bytes(32));
		[$code] = $this->code($client, $verifier);
		self::assertSame(400, $this->exchange($client, $code, bin2hex(random_bytes(32)))->getStatusCode());
		$response = $this->exchange($client, $code, $verifier);
		self::assertSame(200, $response->getStatusCode());
		$token = TypeValidator::validateString($this->data($response)['access_token']);
		self::assertSame(400, $this->exchange($client, $code, $verifier)->getStatusCode());
		self::assertSame(401, $this->api('/ping', $token)->getStatusCode());
	}

	public function testCodeAndRefreshAreBoundToClientAndRedirectUri(): void
	{
		$client = $this->client(false);
		$other = $this->client(false);
		$verifier = bin2hex(random_bytes(32));
		[$code] = $this->code($client, $verifier);
		self::assertSame(400, $this->exchange($other, $code, $verifier)->getStatusCode());
		self::assertSame(
			400,
			$this->exchange(
				$client,
				$code,
				$verifier,
				['redirect_uri' => 'com.example.other:/callback'],
			)->getStatusCode(),
		);
		$response = $this->exchange($client, $code, $verifier);
		self::assertSame(200, $response->getStatusCode());
		$data = $this->data($response);
		self::assertSame(400, $this->exchange($other, $code, $verifier)->getStatusCode());
		self::assertSame(
			400,
			$this->refresh($other, TypeValidator::validateString($data['refresh_token']))->getStatusCode(),
		);
		self::assertSame(
			200,
			$this->api('/ping', TypeValidator::validateString($data['access_token']))->getStatusCode(),
		);
	}

	public function testRefreshCannotEscalateScopesOrExtendAbsoluteLifetime(): void
	{
		$client = $this->client(false, ['api:read', 'home:read']);
		$verifier = bin2hex(random_bytes(32));
		[$code] = $this->code($client, $verifier);
		$data = $this->data($this->exchange($client, $code, $verifier));
		$refresh = TypeValidator::validateString($data['refresh_token']);
		self::assertSame(400, $this->refresh($client, $refresh, ['scope' => 'home:read'])->getStatusCode());
		$connection = $this->getService(Connection::class);
		$expires = $this->createDatetime()->addHoursToDatetime(1)->format('Y-m-d H:i:s');
		$connection->update(
			'oauth_token',
			['family_expires_at' => $expires],
			['client_id' => $client->client->getIdentifier()],
		);
		self::assertSame(200, $this->refresh($client, $refresh)->getStatusCode());
		self::assertSame(
			$expires,
			$connection->fetchOne(
				'SELECT expires_at FROM oauth_token WHERE client_id = ? AND type = ? AND revoked = 0',
				[$client->client->getIdentifier(), 'refresh'],
			),
		);
		$connection->update(
			'oauth_token',
			['family_expires_at' => '2000-01-01 00:00:00'],
			['client_id' => $client->client->getIdentifier()],
		);
		self::assertSame(
			401,
			$this->api('/ping', TypeValidator::validateString($data['access_token']))->getStatusCode(),
		);
	}

	public function testRefreshCanReduceScopesButCannotRestoreDroppedScopes(): void
	{
		$client = $this->client(false, ['api:read', 'home:read']);
		$verifier = bin2hex(random_bytes(32));
		[$code] = $this->code($client, $verifier, ['scope' => 'api:read home:read']);
		$first = $this->data($this->exchange($client, $code, $verifier));
		$response = $this->refresh(
			$client,
			TypeValidator::validateString($first['refresh_token']),
			['scope' => 'home:read'],
		);
		self::assertSame(200, $response->getStatusCode());
		$reduced = $this->data($response);
		$access = TypeValidator::validateString($reduced['access_token']);
		self::assertSame(403, $this->api('/ping', $access)->getStatusCode());
		self::assertSame(200, $this->api('/home/device/records', $access)->getStatusCode());
		self::assertSame(
			400,
			$this->refresh(
				$client,
				TypeValidator::validateString($reduced['refresh_token']),
				['scope' => 'api:read home:read'],
			)->getStatusCode(),
		);
	}

	public function testConfidentialAuthorizationCodeAndRefreshRequireClientSecret(): void
	{
		$client = $this->getService(OAuthClientFacade::class)->create(
			'Confidential browser client',
			true,
			['api:read'],
			[self::REDIRECT],
		);
		$verifier = bin2hex(random_bytes(32));
		[$code] = $this->code($client, $verifier);
		self::assertSame(401, $this->exchange($client, $code, $verifier)->getStatusCode());
		self::assertSame(
			401,
			$this->exchange($client, $code, $verifier, ['client_secret' => 'wrong'])->getStatusCode(),
		);
		$secret = ['client_secret' => TypeValidator::validateString($client->secret)];
		$response = $this->exchange($client, $code, $verifier, $secret);
		self::assertSame(200, $response->getStatusCode());
		$refresh = TypeValidator::validateString($this->data($response)['refresh_token']);
		self::assertSame(401, $this->refresh($client, $refresh)->getStatusCode());
		self::assertSame(200, $this->refresh($client, $refresh, $secret)->getStatusCode());
	}

	public function testPasswordResetAndDeletedUserPreventFurtherAccessAndRefresh(): void
	{
		$client = $this->client(false);
		$verifier = bin2hex(random_bytes(32));
		[$code, $user] = $this->code($client, $verifier);
		$data = $this->data($this->exchange($client, $code, $verifier));
		$user->forceNewPassword();
		$entityManager = $this->getService(EntityManagerInterface::class);
		$entityManager->flush();
		self::assertSame(
			401,
			$this->api('/ping', TypeValidator::validateString($data['access_token']))->getStatusCode(),
		);
		self::assertSame(
			400,
			$this->refresh($client, TypeValidator::validateString($data['refresh_token']))->getStatusCode(),
		);
		$entityManager->remove($user);
		$entityManager->flush();
		self::assertSame(
			401,
			$this->api('/ping', TypeValidator::validateString($data['access_token']))->getStatusCode(),
		);
		self::assertSame(
			400,
			$this->refresh($client, TypeValidator::validateString($data['refresh_token']))->getStatusCode(),
		);
	}

	public function testDesktopLoopbackRedirectAllowsDynamicPortButPreservesHostAndPath(): void
	{
		$client = $this->getService(OAuthClientFacade::class)->create(
			'Desktop',
			false,
			['api:read'],
			['http://127.0.0.1:8000/callback'],
		);
		$params = $this->authorizeParameters($client, bin2hex(random_bytes(32)));
		$callbacks = [
			'http://127.0.0.1:49152/callback' => 200,
			'http://127.0.0.1:49152/other' => 401,
			'http://localhost:49152/callback' => 401,
		];
		foreach ($callbacks as $uri => $status) {
			$params['redirect_uri'] = $uri;
			self::assertSame(
				$status,
				$this->app->handle($this->request('GET', '/api/oauth/authorize', $params))->getStatusCode(),
			);
		}
	}

	/** @param array<string, string|null> $overrides */
	#[DataProvider('invalidAuthorizationRequests')]
	public function testRejectsUnsafeAuthorizationRequests(array $overrides, int $expectedStatus = 400): void
	{
		$client = $this->client(false);
		$params = array_replace($this->authorizeParameters($client, bin2hex(random_bytes(32))), $overrides);
		$params = array_filter($params, static fn (string|null $value): bool => $value !== null);
		$response = $this->app->handle($this->request('GET', '/api/oauth/authorize', $params));
		self::assertSame($expectedStatus, $response->getStatusCode());
		self::assertFalse($response->hasHeader('Location'));
	}

	/** @return iterable<string, array{0: array<string, string|null>, 1?: int}> */
	public static function invalidAuthorizationRequests(): iterable
	{
		yield 'missing PKCE' => [['code_challenge' => null]];
		yield 'plain PKCE' => [['code_challenge_method' => 'plain']];
		yield 'missing state' => [['state' => null]];
		yield 'unregistered redirect' => [['redirect_uri' => 'https://attacker.example/callback'], 401];
		yield 'forbidden scope' => [['scope' => 'home:write']];
		yield 'implicit grant' => [['response_type' => 'token']];
	}

	public function testAuthorizationRequiresCsrfAndIsOneTimeWithoutNetteSession(): void
	{
		$client = $this->client(false);
		[$transaction, $cookie, $page] = $this->authorizationForm($client, bin2hex(random_bytes(32)));
		self::assertStringContainsString('SameSite=Strict', $page->getHeaderLine('Set-Cookie'));
		self::assertStringContainsString('HttpOnly', $page->getHeaderLine('Set-Cookie'));
		self::assertStringContainsString('Secure', $page->getHeaderLine('Set-Cookie'));
		self::assertCount(1, $page->getHeader('Set-Cookie'));
		self::assertStringContainsString("frame-ancestors 'none'", $page->getHeaderLine('Content-Security-Policy'));
		self::assertSame('same-origin', $page->getHeaderLine('Referrer-Policy'));
		$request = $this->request(
			'POST',
			'/api/oauth/authorize',
			['transaction' => $transaction, 'decision' => 'deny'],
		)->withHeader('Origin', $this->baseUrl);
		self::assertSame(403, $this->app->handle($request)->getStatusCode());
		$request = $request->withCookieParams(['oauth_authorization' => $cookie]);
		self::assertSame(
			403,
			$this->app->handle($request->withHeader('Origin', 'https://attacker.example'))->getStatusCode(),
		);
		self::assertSame(403, $this->app->handle($request->withHeader('Origin', 'null'))->getStatusCode());
		self::assertSame(
			403,
			$this->app->handle($request->withHeader('Sec-Fetch-Site', 'cross-site'))->getStatusCode(),
		);
		$denied = $this->app->handle($request);
		self::assertSame(303, $denied->getStatusCode());
		self::assertSame('no-referrer', $denied->getHeaderLine('Referrer-Policy'));
		self::assertStringContainsString('error=access_denied', $denied->getHeaderLine('Location'));
		self::assertStringContainsString('state=expected-state', $denied->getHeaderLine('Location'));
		self::assertSame(400, $this->app->handle($request)->getStatusCode());
	}

	public function testInvalidPasswordAndForcedPasswordChangeCannotIssueCode(): void
	{
		$client = $this->client(false);
		$user = $this->user();
		foreach ([false, true] as $force) {
			if ($force) {
				$user->forceNewPassword();
				$this->getService(EntityManagerInterface::class)->flush();
			}

			[$transaction, $cookie] = $this->authorizationForm($client, bin2hex(random_bytes(32)));
			$request = $this->request('POST', '/api/oauth/authorize', [
				'transaction' => $transaction, 'decision' => 'approve', 'username' => $user->getUsername(),
				'password' => $force ? self::PASSWORD : 'wrong',
			])->withCookieParams(['oauth_authorization' => $cookie]);
			$response = $this->app->handle($request);
			self::assertSame(401, $response->getStatusCode());
			self::assertSame('same-origin', $response->getHeaderLine('Referrer-Policy'));
			self::assertFalse($response->hasHeader('Location'));
		}
	}

	public function testRevocationChecksClientOwnershipAndRevokesFamily(): void
	{
		$client = $this->client(false);
		$other = $this->client(false);
		$verifier = bin2hex(random_bytes(32));
		[$code] = $this->code($client, $verifier);
		$data = $this->data($this->exchange($client, $code, $verifier));
		$access = TypeValidator::validateString($data['access_token']);
		$refresh = TypeValidator::validateString($data['refresh_token']);
		$revoke = fn (OAuthClientCredentials $owner, string $token): ResponseInterface => $this->app->handle(
			$this->request(
				'POST',
				'/api/oauth/revoke',
				['client_id' => $owner->client->getIdentifier(), 'token' => $token],
			),
		);
		self::assertSame(200, $revoke($other, $refresh)->getStatusCode());
		self::assertSame(200, $this->api('/ping', $access)->getStatusCode());
		self::assertSame(200, $revoke($client, $refresh)->getStatusCode());
		self::assertSame(401, $this->api('/ping', $access)->getStatusCode());
		self::assertSame(400, $this->refresh($client, $refresh)->getStatusCode());
		self::assertSame(200, $revoke($client, 'unknown-token')->getStatusCode());
		self::assertSame(200, $revoke($client, $refresh)->getStatusCode());
	}

	public function testClientRotationAndDisablingImmediatelyInvalidateAccess(): void
	{
		$client = $this->client(true);
		$first = $this->data($this->clientToken($client));
		$rotated = $this->getService(OAuthClientFacade::class)->rotateSecret($client->client->getIdentifier());
		self::assertSame(401, $this->clientToken($client)->getStatusCode());
		self::assertSame(
			401,
			$this->api('/ping', TypeValidator::validateString($first['access_token']))->getStatusCode(),
		);
		$next = $this->data($this->clientToken($rotated));
		$this->getService(OAuthClientFacade::class)->disable($client->client->getIdentifier());
		self::assertSame(401, $this->clientToken($rotated)->getStatusCode());
		self::assertSame(
			401,
			$this->api('/ping', TypeValidator::validateString($next['access_token']))->getStatusCode(),
		);
	}

	public function testExpiredAndTamperedTokensAreRejected(): void
	{
		$client = $this->client(true);
		$token = TypeValidator::validateString($this->data($this->clientToken($client))['access_token']);
		$parts = explode('.', $token);
		$parts[1] = rtrim(strtr(base64_encode('{"sub":"different-user"}'), '+/', '-_'), '=');
		self::assertSame(401, $this->api('/ping', implode('.', $parts))->getStatusCode());
		$this->getService(Connection::class)->update(
			'oauth_token',
			['expires_at' => '2000-01-01 00:00:00'],
			['client_id' => $client->client->getIdentifier()],
		);
		self::assertSame(401, $this->api('/ping', $token)->getStatusCode());
	}

	public function testExpiredAuthorizationTransactionAndCodeAreRejected(): void
	{
		$client = $this->client(false);
		$verifier = bin2hex(random_bytes(32));
		[$transaction, $cookie] = $this->authorizationForm($client, $verifier);
		$this->getService(Connection::class)->update(
			'oauth_authorization_request',
			['expires_at' => '2000-01-01 00:00:00'],
			['id' => $transaction],
		);
		$request = $this->request(
			'POST',
			'/api/oauth/authorize',
			['transaction' => $transaction, 'decision' => 'deny'],
		)->withCookieParams(
			['oauth_authorization' => $cookie],
		);
		self::assertSame(400, $this->app->handle($request)->getStatusCode());
		[$code] = $this->code($client, $verifier);
		$this->getService(Connection::class)->update(
			'oauth_token',
			['expires_at' => '2000-01-01 00:00:00'],
			['client_id' => $client->client->getIdentifier(), 'type' => 'code'],
		);
		self::assertSame(400, $this->exchange($client, $code, $verifier)->getStatusCode());
	}

	public function testCleanupKeepsReplayRecordsUntilFamilyExpires(): void
	{
		$client = $this->client(false);
		$verifier = bin2hex(random_bytes(32));
		[$code] = $this->code($client, $verifier);
		self::assertSame(200, $this->exchange($client, $code, $verifier)->getStatusCode());
		$connection = $this->getService(Connection::class);
		$connection->update(
			'oauth_token',
			['expires_at' => '2000-01-01 00:00:00'],
			['client_id' => $client->client->getIdentifier(), 'type' => 'code'],
		);
		$this->getService(OAuthTokenStore::class)->cleanup();
		self::assertSame(
			1,
			TypeValidator::validateInt(
				$connection->fetchOne(
					'SELECT COUNT(*) FROM oauth_token WHERE client_id = ? AND type = ?',
					[$client->client->getIdentifier(), 'code'],
				),
			),
		);
	}

	public function testAuthorizationPostCannotChangeRequestedClientRedirectOrScopes(): void
	{
		$client = $this->client(false);
		$verifier = bin2hex(random_bytes(32));
		[$transaction, $cookie] = $this->authorizationForm($client, $verifier);
		$user = $this->user();
		$response = $this->app->handle($this->request('POST', '/api/oauth/authorize', [
			'transaction' => $transaction, 'decision' => 'approve', 'username' => $user->getUsername(), 'password' => self::PASSWORD,
			'client_id' => 'attacker', 'redirect_uri' => 'https://attacker.example', 'scope' => 'home:write',
		])->withCookieParams(['oauth_authorization' => $cookie]));
		self::assertSame(303, $response->getStatusCode());
		self::assertStringStartsWith(self::REDIRECT . '?', $response->getHeaderLine('Location'));
		self::assertSame(
			'["api:read"]',
			$this->getService(Connection::class)->fetchOne(
				'SELECT scopes FROM oauth_token WHERE client_id = ? AND type = ?',
				[$client->client->getIdentifier(), 'code'],
			),
		);
	}

	public function testAuthorizationRateLimitIsEnforced(): void
	{
		$client = $this->client(false);
		$params = $this->authorizeParameters($client, bin2hex(random_bytes(32)));
		for ($i = 0; $i < 30; $i++) {
			self::assertSame(
				200,
				$this->app->handle($this->request('GET', '/api/oauth/authorize', $params))->getStatusCode(),
			);
		}

		self::assertSame(
			429,
			$this->app->handle($this->request('GET', '/api/oauth/authorize', $params))->getStatusCode(),
		);
	}

	public function testLocalHttpSupportsClientCredentialsAuthorizationRefreshAndRevocation(): void
	{
		$this->baseUrl = 'http://192.168.1.10';
		$serverClient = $this->client(true);
		$serverResponse = $this->clientToken($serverClient);
		self::assertSame(200, $serverResponse->getStatusCode());
		$serverToken = TypeValidator::validateString($this->data($serverResponse)['access_token']);
		self::assertSame(200, $this->api('/ping', $serverToken)->getStatusCode());

		$client = $this->client(false);
		$verifier = bin2hex(random_bytes(32));
		[, , $page] = $this->authorizationForm($client, $verifier);
		self::assertStringNotContainsString('; Secure', $page->getHeaderLine('Set-Cookie'));
		self::assertSame('same-origin', $page->getHeaderLine('Referrer-Policy'));
		[$code] = $this->code($client, $verifier);
		$exchange = $this->exchange($client, $code, $verifier);
		self::assertSame(200, $exchange->getStatusCode());
		$refresh = $this->refresh($client, TypeValidator::validateString($this->data($exchange)['refresh_token']));
		self::assertSame(200, $refresh->getStatusCode());
		$tokens = $this->data($refresh);
		$access = TypeValidator::validateString($tokens['access_token']);
		self::assertSame(200, $this->api('/me', $access)->getStatusCode());
		$revoke = $this->app->handle($this->request('POST', '/api/oauth/revoke', [
			'client_id' => $client->client->getIdentifier(),
			'token' => TypeValidator::validateString($tokens['refresh_token']),
		]));
		self::assertSame(200, $revoke->getStatusCode());
		self::assertSame(401, $this->api('/me', $access)->getStatusCode());
	}

	/** @param list<string> $scopes */
	private function client(bool $confidential, array $scopes = ['api:read']): OAuthClientCredentials
	{
		return $this->getService(OAuthClientFacade::class)->create(
			'OAuth flow test',
			$confidential,
			$scopes,
			$confidential ? [] : [self::REDIRECT],
		);
	}

	/** @param array<string, string> $overrides */
	private function clientToken(OAuthClientCredentials $client, array $overrides = []): ResponseInterface
	{
		$params = ['grant_type' => 'client_credentials', 'client_id' => $client->client->getIdentifier()];
		if ($client->secret !== null) {
			$params['client_secret'] = $client->secret;
		}

		return $this->app->handle($this->request('POST', '/api/oauth/token', array_replace($params, $overrides)));
	}

	/** @param array<string, string> $overrides */
	private function exchange(
		OAuthClientCredentials $client,
		string $code,
		string $verifier,
		array $overrides = [],
	): ResponseInterface
	{
		return $this->app->handle($this->request('POST', '/api/oauth/token', array_replace([
			'grant_type' => 'authorization_code', 'client_id' => $client->client->getIdentifier(),
			'code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => self::REDIRECT,
		], $overrides)));
	}

	/** @param array<string, string> $overrides */
	private function refresh(OAuthClientCredentials $client, string $refresh, array $overrides = []): ResponseInterface
	{
		return $this->app->handle($this->request('POST', '/api/oauth/token', array_replace([
			'grant_type' => 'refresh_token', 'client_id' => $client->client->getIdentifier(), 'refresh_token' => $refresh,
		], $overrides)));
	}

	private function api(string $path, string $token): ResponseInterface
	{
		return $this->app->handle(
			$this->request('GET', '/api/v1' . $path)->withHeader('Authorization', 'Bearer ' . $token),
		);
	}

	/**
	 * @param array<string, string> $overrides
	 * @return array{string, AppAdmin}
	 */
	private function code(OAuthClientCredentials $client, string $verifier, array $overrides = []): array
	{
		[$transaction, $cookie] = $this->authorizationForm($client, $verifier, $overrides);
		$user = $this->user();
		$response = $this->app->handle($this->request('POST', '/api/oauth/authorize', [
			'transaction' => $transaction, 'decision' => 'approve', 'username' => $user->getUsername(), 'password' => self::PASSWORD,
		])->withCookieParams(['oauth_authorization' => $cookie])->withHeader('Origin', $this->baseUrl));
		self::assertSame(303, $response->getStatusCode());
		self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
		parse_str(
			TypeValidator::validateString(parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY)),
			$query,
		);
		self::assertSame('expected-state', $query['state']);
		return [TypeValidator::validateString($query['code']), $user];
	}

	/**
	 * @param array<string, string> $overrides
	 * @return array{string, string, ResponseInterface}
	 */
	private function authorizationForm(OAuthClientCredentials $client, string $verifier, array $overrides = []): array
	{
		$response = $this->app->handle(
			$this->request(
				'GET',
				'/api/oauth/authorize',
				array_replace($this->authorizeParameters($client, $verifier), $overrides),
			),
		);
		self::assertSame(200, $response->getStatusCode());
		self::assertSame(1, preg_match('/name="transaction" value="([^"]+)"/', (string) $response->getBody(), $match));
		self::assertSame(
			1,
			preg_match('/^oauth_authorization=([^;]+)/', $response->getHeaderLine('Set-Cookie'), $cookie),
		);
		return [$match[1], $cookie[1], $response];
	}

	/** @return array<string, string> */
	private function authorizeParameters(OAuthClientCredentials $client, string $verifier): array
	{
		return [
			'response_type' => 'code', 'client_id' => $client->client->getIdentifier(), 'redirect_uri' => self::REDIRECT,
			'scope' => 'api:read', 'state' => 'expected-state', 'code_challenge_method' => 'S256',
			'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
		];
	}

	private function user(): AppAdmin
	{
		$name = 'oauth-' . bin2hex(random_bytes(10));
		$user = new AppAdmin(
			$name,
			$name,
			$name . '@example.test',
			password_hash(self::PASSWORD, PASSWORD_DEFAULT),
			$this->createDatetime(),
			false,
			false,
		);
		$em = $this->getService(EntityManagerInterface::class);
		$em->persist($user);
		$em->flush();
		return $user;
	}

	/** @param array<string, string> $parameters */
	private function request(string $method, string $path, array $parameters = []): ServerRequestInterface
	{
		$request = (new ServerRequestFactory())->createServerRequest(
			$method,
			$this->baseUrl . $path,
			['REMOTE_ADDR' => $this->remoteAddress],
		);
		if ($method === 'GET') {
			return $request->withQueryParams($parameters);
		}

		$request->getBody()->write(http_build_query($parameters));
		return $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');
	}

	/** @return array<mixed> */
	private function data(ResponseInterface $response): array
	{
		return TypeValidator::validateArray(Json::decode((string) $response->getBody(), true));
	}

}
