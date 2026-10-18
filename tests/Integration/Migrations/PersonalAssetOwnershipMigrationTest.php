<?php

declare(strict_types = 1);

namespace App\Test\Integration\Migrations;

use App\Test\Integration\IntegrationTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Exception\AbortMigration;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Ramsey\Uuid\Uuid;

class PersonalAssetOwnershipMigrationTest extends IntegrationTestCase
{

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testLegacyDataBackfillAndMigrationGuards(): void
	{
		// A separate process rebuilds the test schema before exercising the legacy layout.
		$connection = $this->getService(Connection::class);
		$this->applyMigration('down');
		$filipId = Uuid::uuid4()->toString();
		$testUserId = Uuid::uuid4()->toString();
		$portfolioId = Uuid::uuid4()->toString();
		$positionId = Uuid::uuid4()->toString();
		$watchlistId = Uuid::uuid4()->toString();
		$now = '2026-01-15 00:00:00';

		$this->insertAdmin($testUserId, 'filip2');
		$connection->insert('portu_asset', [
			'id' => $portfolioId, 'name' => 'Legacy portfolio', 'currency' => 'CZK',
			'created_at' => $now, 'updated_at' => $now,
		]);
		$connection->insert('stock_asset_watchlist', [
			'id' => $watchlistId, 'name' => 'Legacy watchlist', 'ticker' => 'AAPL',
			'recommended_entry_price' => 150.0, 'currency' => 'USD',
			'created_at' => $now, 'updated_at' => $now,
		]);

		$this->assertMigrationRejected('up', 'user filip does not exist');
		self::assertArrayNotHasKey('app_admin_id', $connection->createSchemaManager()->listTableColumns('portu_asset'));

		$this->insertAdmin($filipId, 'filip');
		$connection->insert('portu_position', [
			'id' => $positionId, 'portu_asset_id' => $portfolioId, 'app_admin_id' => $testUserId,
			'start_date' => $now, 'created_at' => $now, 'updated_at' => $now,
			'start_investment_price' => 1000.0, 'start_investment_currency' => 'CZK',
			'monthly_increase_price' => 100.0, 'monthly_increase_currency' => 'CZK',
			'current_value_price' => 1200.0, 'current_value_currency' => 'CZK',
			'total_invested_to_this_date_price' => 1100.0, 'total_invested_to_this_date_currency' => 'CZK',
		]);
		$this->assertMigrationRejected('up', 'positions belong to another user');
		$connection->update('portu_position', ['app_admin_id' => $filipId], ['id' => $positionId]);

		$this->applyMigration('up');

		self::assertSame(
			$filipId,
			$connection->fetchOne('SELECT app_admin_id FROM portu_asset WHERE id = ?', [$portfolioId]),
		);
		self::assertSame(
			$filipId,
			$connection->fetchOne('SELECT app_admin_id FROM stock_asset_watchlist WHERE id = ?', [$watchlistId]),
		);
		self::assertSame(
			$filipId,
			$connection->fetchOne('SELECT app_admin_id FROM portu_position WHERE id = ?', [$positionId]),
		);
		self::assertSame(
			'Legacy portfolio',
			$connection->fetchOne('SELECT name FROM portu_asset WHERE id = ?', [$portfolioId]),
		);
		self::assertEquals(
			150.0,
			$connection->fetchOne(
				'SELECT recommended_entry_price FROM stock_asset_watchlist WHERE id = ?',
				[$watchlistId],
			),
		);
		self::assertTrue(
			$connection->createSchemaManager()->listTableColumns('portu_asset')['app_admin_id']->getNotnull(),
		);
		self::assertTrue(
			$connection->createSchemaManager()->listTableColumns('stock_asset_watchlist')['app_admin_id']->getNotnull(),
		);

		$otherWatchlistId = Uuid::uuid4()->toString();
		$connection->insert('stock_asset_watchlist', [
			'id' => $otherWatchlistId, 'app_admin_id' => $testUserId, 'name' => 'Other watchlist', 'ticker' => 'AAPL',
			'recommended_entry_price' => null, 'currency' => null,
			'created_at' => $now, 'updated_at' => $now,
		]);
		self::assertSame(
			2,
			(int) $connection->fetchOne("SELECT COUNT(*) FROM stock_asset_watchlist WHERE ticker = 'AAPL'"),
		);
		$this->assertMigrationRejected('down', 'another user has Portu portfolios or watchlist entries');

		$connection->delete('stock_asset_watchlist', ['id' => $otherWatchlistId]);
		$this->applyMigration('down');
		self::assertArrayNotHasKey(
			'app_admin_id',
			$connection->createSchemaManager()->listTableColumns('stock_asset_watchlist'),
		);
		$this->applyMigration('up');
		self::assertSame(
			$filipId,
			$connection->fetchOne('SELECT app_admin_id FROM portu_asset WHERE id = ?', [$portfolioId]),
		);
	}

	private function insertAdmin(string $id, string $username): void
	{
		$this->getService(Connection::class)->insert('app_admin', [
			'id' => $id, 'name' => $username, 'username' => $username, 'email' => $username . '@example.test',
			'password' => 'unused-test-password', 'force_new_password' => 0, 'sys_admin' => 0,
			'created_at' => '2026-01-15 00:00:00', 'updated_at' => '2026-01-15 00:00:00',
		]);
	}

	private function applyMigration(string $direction): void
	{
		$connection = $this->getService(Connection::class);
		$migration = $this->createMigration();
		$migration->$direction(new Schema());
		foreach ($migration->getSql() as $query) {
			$connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
		}
	}

	private function assertMigrationRejected(string $direction, string $message): void
	{
		$migration = $this->createMigration();
		try {
			$migration->$direction(new Schema());
		} catch (AbortMigration $exception) {
			self::assertStringContainsString($message, $exception->getMessage());
			self::assertSame([], $migration->getSql());
			return;
		}

		self::fail('Migration should stop before modifying the schema.');
	}

	private function createMigration(): AbstractMigration
	{
		$factory = $this->getService(DependencyFactory::class);
		$factory->getMigrationRepository()->getMigrations();
		return $factory->getMigrationFactory()->createVersion('Migrations\\Version20261018102336');
	}

}
