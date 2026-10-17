<?php

declare(strict_types = 1);

namespace App\OAuth2\Protocol;

use App\OAuth2\Token\OAuthTokenContext;
use App\OAuth2\Token\OAuthTokenStore;
use App\OAuth2\Token\OAuthTokenType;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{

	public function __construct(private OAuthTokenStore $store, private OAuthTokenContext $context)
	{
	}

	public function getNewRefreshToken(): RefreshTokenEntity
	{
		return new RefreshTokenEntity();
	}

	public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
	{
		if (
			$this->context->familyExpiresAt !== null
			&& $refreshTokenEntity->getExpiryDateTime() > $this->context->familyExpiresAt
		) {
			$refreshTokenEntity->setExpiryDateTime($this->context->familyExpiresAt);
		}

		$accessToken = $refreshTokenEntity->getAccessToken();
		$this->store->persist(
			$refreshTokenEntity->getIdentifier(),
			OAuthTokenType::REFRESH,
			$accessToken->getClient()->getIdentifier(),
			$accessToken->getUserIdentifier(),
			array_values(
				array_map(
					static fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(),
					$accessToken->getScopes(),
				),
			),
			$refreshTokenEntity->getExpiryDateTime(),
			$accessToken->getIdentifier(),
		);
	}

	public function revokeRefreshToken(string $tokenId): void
	{
		$this->store->revoke($tokenId, OAuthTokenType::REFRESH);
	}

	public function isRefreshTokenRevoked(string $tokenId): bool
	{
		return $this->store->isRevoked($tokenId, OAuthTokenType::REFRESH);
	}

}
