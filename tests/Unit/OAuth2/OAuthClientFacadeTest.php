<?php

declare(strict_types = 1);

namespace App\Test\Unit\OAuth2;

use App\OAuth2\Client\OAuthClientFacade;
use App\OAuth2\Client\OAuthClientRepository;
use App\OAuth2\Token\OAuthTokenStore;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
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
