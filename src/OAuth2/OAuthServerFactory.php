<?php

declare(strict_types = 1);

namespace App\OAuth2;

use App\OAuth2\Client\OAuthClientRepository;
use App\OAuth2\Protocol\AccessTokenRepository;
use App\OAuth2\Protocol\AuthCodeRepository;
use App\OAuth2\Protocol\RefreshTokenRepository;
use App\OAuth2\Protocol\ScopeRepository;
use DateInterval;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;

class OAuthServerFactory
{

	public function __construct(
		private OAuthClientRepository $clients,
		private AccessTokenRepository $accessTokens,
		private AuthCodeRepository $codes,
		private RefreshTokenRepository $refreshTokens,
		private ScopeRepository $scopes,
		private OAuthKeys $keys,
	)
	{
	}

	public function createAuthorizationServer(): AuthorizationServer
	{
		$server = new AuthorizationServer(
			$this->clients,
			$this->accessTokens,
			$this->scopes,
			$this->keys->getPrivateKey(),
			$this->keys->getEncryptionKey(),
		);
		$server->setDefaultScope(OAuthScope::API_READ->value);
		$accessTtl = new DateInterval('PT10M');
		$server->enableGrantType(new ClientCredentialsGrant(), $accessTtl);
		$codeGrant = new AuthCodeGrant($this->codes, $this->refreshTokens, new DateInterval('PT2M'));
		$codeGrant->setRefreshTokenTTL(new DateInterval('P30D'));
		$server->enableGrantType($codeGrant, $accessTtl);
		$refreshGrant = new RefreshTokenGrant($this->refreshTokens);
		$refreshGrant->setRefreshTokenTTL(new DateInterval('P30D'));
		$server->enableGrantType($refreshGrant, $accessTtl);
		return $server;
	}

	public function createResourceServer(): ResourceServer
	{
		return new ResourceServer($this->accessTokens, $this->keys->getPublicKey());
	}

}
