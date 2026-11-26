<?php

declare(strict_types = 1);

namespace App\Test\Unit\OAuth2;

use App\OAuth2\Client\OAuthClient;
use App\OAuth2\Client\OAuthClientFacade;
use App\OAuth2\Client\OAuthClientRepository;
use App\OAuth2\OAuthScope;
use App\OAuth2\Token\OAuthTokenStore;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OAuthClientFacadeTest extends TestCase
{

	/**
	 * @param list<string> $scopes
	 * @param list<string> $redirects
	 */
	#[DataProvider('invalidClients')]
	public function testRejectsInvalidRegistration(bool $confidential, array $scopes, array $redirects): void
	{
		$clients = $this->createMock(OAuthClientRepository::class);
		$clients->expects(self::never())->method('save');
		$facade = new OAuthClientFacade(
			$clients,
			self::createStub(OAuthTokenStore::class),
			self::createStub(DatetimeFactory::class),
		);
		$this->expectException(InvalidArgumentException::class);
		$facade->create('Application', $confidential, $scopes, $redirects);
	}

	/**
	 * @param list<string> $redirects
	 */
	#[DataProvider('validLoopbackRedirects')]
	public function testAcceptsLoopbackRedirectAndMcpScope(array $redirects): void
	{
		$clients = $this->createMock(OAuthClientRepository::class);
		$clients->expects(self::once())->method('save')->with(self::isInstanceOf(OAuthClient::class));
		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn(new ImmutableDateTime('2026-05-01 12:00:00'));

		$facade = new OAuthClientFacade(
			$clients,
			self::createStub(OAuthTokenStore::class),
			$datetimeFactory,
		);

		$credentials = $facade->create('MCP CLI', false, [OAuthScope::MCP_READ->value], $redirects);

		self::assertNull($credentials->secret);
		self::assertFalse($credentials->client->isConfidential());
		self::assertSame([OAuthScope::MCP_READ->value], $credentials->client->getScopes());
		self::assertSame($redirects, $credentials->client->getRedirectUri());
		foreach ($redirects as $redirect) {
			self::assertTrue($facade->isLoopbackRedirectUri($redirect));
		}
	}

	public function testIsLoopbackRedirectUriRejectsNonLoopbackUris(): void
	{
		$facade = new OAuthClientFacade(
			self::createStub(OAuthClientRepository::class),
			self::createStub(OAuthTokenStore::class),
			self::createStub(DatetimeFactory::class),
		);

		self::assertFalse($facade->isLoopbackRedirectUri('https://localhost:54321/callback'));
		self::assertFalse($facade->isLoopbackRedirectUri('https://example.com/callback'));
		self::assertFalse($facade->isLoopbackRedirectUri('http://192.168.1.10/callback'));
		self::assertFalse($facade->isLoopbackRedirectUri('http://localhost/callback#frag'));
		self::assertFalse($facade->isLoopbackRedirectUri('com.example.app:/callback'));
	}

	/** @return iterable<string, array{list<string>}> */
	public static function validLoopbackRedirects(): iterable
	{
		yield 'localhost with port' => [['http://localhost:54321/callback']];
		yield '127.0.0.1 with port' => [['http://127.0.0.1:8787/oauth/callback']];
		yield 'ipv6 loopback' => [['http://[::1]:8787/callback']];
	}

	/** @return iterable<string, array{bool, list<string>, list<string>}> */
	public static function invalidClients(): iterable
	{
		yield 'unknown scope' => [true, ['admin:everything'], []];
		yield 'empty scopes' => [true, [], []];
		yield 'native missing redirect' => [false, ['api:read'], []];
		yield 'remote HTTP' => [false, ['api:read'], ['http://example.com/callback']];
		yield 'wildcard' => [false, ['api:read'], ['https://example.com/*']];
		yield 'fragment' => [false, ['api:read'], ['https://example.com/callback#token']];
		yield 'userinfo' => [false, ['api:read'], ['https://user:pass@example.com/callback']];
		yield 'javascript' => [false, ['api:read'], ['javascript:alert(1)']];
		yield 'ambiguous custom scheme' => [false, ['api:read'], ['portfolio:/callback']];
		yield 'newline' => [false, ['api:read'], ["https://example.com/\r\ncallback"]];
	}

}
