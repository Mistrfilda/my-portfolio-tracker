<?php

declare(strict_types = 1);

namespace App\OAuth2\Client;

use App\Doctrine\BaseAppAdminRepository;
use Doctrine\ORM\QueryBuilder;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

/** @extends BaseAppAdminRepository<OAuthClient> */
class OAuthClientRepository extends BaseAppAdminRepository implements ClientRepositoryInterface
{

	public function find(string $id): OAuthClient|null
	{
		$client = $this->doctrineRepository->find($id);
		if ($client !== null) {
			// Re-check revocation even when the entity was loaded earlier in this process.
			$this->entityManager->refresh($client);
		}

		return $client;
	}

	public function getClientEntity(string $clientIdentifier): OAuthClient|null
	{
		$client = $this->find($clientIdentifier);
		return $client !== null && $client->isActive() ? $client : null;
	}

	public function validateClient(string $clientIdentifier, string|null $clientSecret, string|null $grantType): bool
	{
		$client = $this->getClientEntity($clientIdentifier);
		return $client !== null
			&& ($grantType === null || $client->supportsGrantType($grantType))
			&& $client->isConfidential()
			&& $clientSecret !== null
			&& $client->getSecretHash() !== null
			&& password_verify($clientSecret, $client->getSecretHash());
	}

	public function save(OAuthClient $client): void
	{
		$this->entityManager->persist($client);
		$this->entityManager->flush();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('client');
	}

}
