<?php

declare(strict_types = 1);

namespace App\OAuth2\Protocol;

use App\OAuth2\Authorization\OAuthUserAuthenticator;
use App\OAuth2\Client\OAuthClient;
use App\OAuth2\OAuthScope;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

class ScopeRepository implements ScopeRepositoryInterface
{

	public function __construct(private OAuthUserAuthenticator $users)
	{
	}

	public function getScopeEntityByIdentifier(string $identifier): ScopeEntity|null
	{
		$scope = OAuthScope::tryFrom($identifier);
		return $scope === null ? null : new ScopeEntity($scope->value);
	}

	/**
	 * @param array<ScopeEntityInterface> $scopes
	 * @return array<ScopeEntityInterface>
	 */
	public function finalizeScopes(
		array $scopes,
		string $grantType,
		ClientEntityInterface $clientEntity,
		string|null $userIdentifier = null,
		string|null $authCodeId = null,
	): array
	{
		assert($clientEntity instanceof OAuthClient);
		if ($userIdentifier !== null && $this->users->findActive($userIdentifier) === null) {
			throw OAuthServerException::invalidGrant('The user is no longer permitted to authorize access.');
		}

		foreach ($scopes as $scope) {
			if (!in_array($scope->getIdentifier(), $clientEntity->getScopes(), true)) {
				throw OAuthServerException::invalidScope($scope->getIdentifier());
			}
		}

		return $scopes;
	}

}
