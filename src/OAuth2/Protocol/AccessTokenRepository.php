<?php

declare(strict_types = 1);

namespace App\OAuth2\Protocol;

use App\OAuth2\Token\OAuthTokenStore;
use App\OAuth2\Token\OAuthTokenType;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{

	public function __construct(private OAuthTokenStore $store)
	{
	}

	/** @param array<ScopeEntityInterface> $scopes */
	public function getNewToken(
		ClientEntityInterface $clientEntity,
		array $scopes,
		string|null $userIdentifier = null,
	): AccessTokenEntity
	{
		$token = new AccessTokenEntity();
		$token->setClient($clientEntity);
		if ($userIdentifier !== null && $userIdentifier !== '') {
			$token->setUserIdentifier($userIdentifier);
		}

		foreach ($scopes as $scope) {
			$token->addScope($scope);
		}

		return $token;
	}

	public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
	{
		$this->store->persist(
			$accessTokenEntity->getIdentifier(),
			OAuthTokenType::ACCESS,
			$accessTokenEntity->getClient()->getIdentifier(),
			$accessTokenEntity->getUserIdentifier(),
			array_values(
				array_map(
					static fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(),
					$accessTokenEntity->getScopes(),
				),
			),
			$accessTokenEntity->getExpiryDateTime(),
		);
	}

	public function revokeAccessToken(string $tokenId): void
	{
		$this->store->revoke($tokenId, OAuthTokenType::ACCESS);
	}

	public function isAccessTokenRevoked(string $tokenId): bool
	{
		return $this->store->isRevoked($tokenId, OAuthTokenType::ACCESS);
	}

}
