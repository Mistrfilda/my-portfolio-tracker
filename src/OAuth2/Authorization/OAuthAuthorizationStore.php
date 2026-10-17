<?php

declare(strict_types = 1);

namespace App\OAuth2\Authorization;

use App\Utils\TypeValidator;
use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Exception\OAuthServerException;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\Json;

class OAuthAuthorizationStore
{

	public function __construct(private Connection $connection, private DatetimeFactory $datetimeFactory)
	{
	}

	/** @param array<string, string> $parameters */
	public function create(array $parameters, string $csrf): string
	{
		$id = bin2hex(random_bytes(32));
		$this->connection->insert('oauth_authorization_request', [
			'id' => $id,
			'csrf_hash' => hash('sha256', $csrf),
			'parameters' => Json::encode($parameters),
			'expires_at' => $this->datetimeFactory->createNow()->addMinutesToDatetime(10)->format('Y-m-d H:i:s'),
			'consumed' => 0,
		]);
		return $id;
	}

	/** @return array<string, string> */
	public function consume(string $id, string $csrf): array
	{
		$row = $this->connection->fetchAssociative('SELECT * FROM oauth_authorization_request WHERE id = ?', [$id]);
		if ($row === false) {
			throw OAuthServerException::invalidRequest('transaction', 'Invalid authorization transaction.');
		}

		if ($csrf === '' || !hash_equals(TypeValidator::validateString($row['csrf_hash']), hash('sha256', $csrf))) {
			throw new OAuthServerException('Invalid CSRF proof.', 0, 'access_denied', 403);
		}

		$consumed = $this->connection->executeStatement(
			'UPDATE oauth_authorization_request SET consumed = 1 WHERE id = ? AND consumed = 0 AND expires_at > ?',
			[$id, $this->datetimeFactory->createNow()->format('Y-m-d H:i:s')],
		);
		if ($consumed !== 1) {
			throw OAuthServerException::invalidRequest(
				'transaction',
				'Authorization transaction expired or already used.',
			);
		}

		$parameters = TypeValidator::validateArray(
			Json::decode(TypeValidator::validateString($row['parameters']), true),
		);
		$result = [];
		foreach ($parameters as $key => $value) {
			$result[TypeValidator::validateString($key)] = TypeValidator::validateString($value);
		}

		return $result;
	}

	public function cleanup(): void
	{
		$this->connection->executeStatement(
			'DELETE FROM oauth_authorization_request WHERE expires_at < ?',
			[$this->datetimeFactory->createNow()->format('Y-m-d H:i:s')],
		);
	}

}
