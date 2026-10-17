<?php

declare(strict_types = 1);

namespace App\OAuth2\Http;

use App\Utils\TypeValidator;
use Doctrine\DBAL\Connection;
use Mistrfilda\Datetime\DatetimeFactory;

class OAuthRateLimiter
{

	public function __construct(private Connection $connection, private DatetimeFactory $datetimeFactory)
	{
	}

	public function allow(string $bucket, int $limit, int $windowSeconds = 60): bool
	{
		$now = $this->datetimeFactory->createNow()->getTimestamp();
		$id = hash('sha256', $bucket);
		$this->connection->executeStatement(
			'INSERT INTO oauth_rate_limit (id, attempts, reset_at) VALUES (?, 1, ?)
			 ON DUPLICATE KEY UPDATE attempts = IF(reset_at <= ?, 1, attempts + 1), reset_at = IF(reset_at <= ?, ?, reset_at)',
			[$id, $now + $windowSeconds, $now, $now, $now + $windowSeconds],
		);
		return TypeValidator::validateInt(
			$this->connection->fetchOne('SELECT attempts FROM oauth_rate_limit WHERE id = ?', [$id]),
		) <= $limit;
	}

	public function cleanup(): void
	{
		$this->connection->executeStatement(
			'DELETE FROM oauth_rate_limit WHERE reset_at < ?',
			[$this->datetimeFactory->createNow()->getTimestamp()],
		);
	}

}
