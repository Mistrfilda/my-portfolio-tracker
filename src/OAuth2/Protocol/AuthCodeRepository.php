<?php

declare(strict_types = 1);

namespace App\OAuth2\Protocol;

use App\OAuth2\Token\OAuthTokenContext;
use App\OAuth2\Token\OAuthTokenStore;
use App\OAuth2\Token\OAuthTokenType;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Mistrfilda\Datetime\DatetimeFactory;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{

	public function __construct(
		private OAuthTokenStore $store,
		private OAuthTokenContext $context,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function getNewAuthCode(): AuthCodeEntity
	{
		return new AuthCodeEntity();
	}

	public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
	{
		$this->context->familyId = bin2hex(random_bytes(32));
		$this->context->familyExpiresAt = $this->datetimeFactory->createNow()->modify('+30 days');
		$this->store->persist(
			$authCodeEntity->getIdentifier(),
			OAuthTokenType::CODE,
			$authCodeEntity->getClient()->getIdentifier(),
			$authCodeEntity->getUserIdentifier(),
			array_values(
				array_map(
					static fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(),
					$authCodeEntity->getScopes(),
				),
			),
			$authCodeEntity->getExpiryDateTime(),
		);
	}

	public function revokeAuthCode(string $codeId): void
	{
		$this->store->revoke($codeId, OAuthTokenType::CODE);
	}

	public function isAuthCodeRevoked(string $codeId): bool
	{
		return $this->store->isRevoked($codeId, OAuthTokenType::CODE);
	}

}
