<?php

declare(strict_types = 1);

namespace App\Test\Integration\Migrations;

use App\Admin\AppAdmin;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Dividend\Forecast\StockAssetDividendForecast;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecord;
use App\Stock\Dividend\Forecast\StockAssetDividendTrendEnum;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Exception\AbortMigration;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Ramsey\Uuid\Uuid;

class DividendOwnershipMigrationTest extends IntegrationTestCase
{

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testBackfillPreservesHistoryAndCustomForecastValuesAndEnforcesOwnership(): void
	{
		$em = $this->getService(EntityManagerInterface::class);
		$connection = $this->getService(Connection::class);
		$now = new ImmutableDateTime('2026-03-01');
		$oldOwner = new AppAdmin('Filip', 'filip', 'old@example.test', 'unused', $now, false, false);
		$stock = new StockAsset(
			'Legacy dividend',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			'LEGACY-DIV',
			StockAssetExchange::NYSE,
			CurrencyEnum::CZK,
			$now,
			null,
			StockAssetDividendSourceEnum::WEB,
			15.0,
			null,
			false,
			false,
			false,
			null,
		);
		$event = new StockAssetDividend($stock, $now, $now, null, CurrencyEnum::CZK, 3.0, $now);
		$record = new StockAssetDividendRecord(
			$event,
			10,
			30.0,
			CurrencyEnum::CZK,
			30.0,
			CurrencyEnum::CZK,
			$now,
			$oldOwner,
		);
		$forecast = new StockAssetDividendForecast(2026, StockAssetDividendTrendEnum::NEUTRAL, $now, $oldOwner);
		$forecast->defaultForYear();
		$forecast->recalculated($now);
		$item = new StockAssetDividendForecastRecord(
			$forecast,
			$stock,
			CurrencyEnum::CZK,
			[3, 9],
			[3],
			2.55,
			3.0,
			10,
			2.55,
			3.0,
			2.55,
			3.0,
			2.55,
			3.0,
			4.25,
			5.0,
			null,
			null,
			$now,
		);
		$item->setCustomValues(4.25, 5.0, 0.85, 1.0);
		foreach ([$oldOwner, $stock, $event, $record, $forecast, $item] as $entity) {
			$em->persist($entity);
		}

		$em->flush();
		$em->clear();
		$this->applyMigration('down');
		$connection->update('app_admin', ['username' => 'filip2'], ['id' => $oldOwner->getId()->toString()]);
		$originals = [];
		foreach (['stock_asset_dividend_record', 'stock_asset_dividend_forecast', 'stock_asset_dividend_forecast_record'] as $table) {
			$originals[$table] = $connection->fetchAssociative('SELECT * FROM ' . $table);
		}

		$this->assertMigrationRejected('up', 'user filip does not exist');
		self::assertArrayNotHasKey(
			'app_admin_id',
			$connection->createSchemaManager()->listTableColumns('stock_asset_dividend_record'),
		);
		$filipId = $this->insertAdmin('filip');
		foreach ([
			'stock_asset_dividend_record' => 'duplicate dividend events',
			'stock_asset_dividend_forecast_record' => 'duplicate forecast assets',
		] as $table => $message) {
			$duplicate = $originals[$table];
			$duplicate['id'] = Uuid::uuid4()->toString();
			$connection->insert($table, $duplicate);
			$this->assertMigrationRejected('up', $message);
			$connection->delete($table, ['id' => $duplicate['id']]);
		}

		$this->applyMigration('up');
		foreach ($originals as $table => $original) {
			$expected = $table === 'stock_asset_dividend_forecast_record'
				? $original
				: $original + ['app_admin_id' => $filipId];
			self::assertSame($expected, $connection->fetchAssociative('SELECT * FROM ' . $table));
		}

		self::assertSame($filipId, $connection->fetchOne(
			'SELECT f.app_admin_id FROM stock_asset_dividend_forecast_record r'
			. ' JOIN stock_asset_dividend_forecast f ON f.id = r.stock_asset_dividend_forecast_id WHERE r.id = ?',
			[$item->getId()->toString()],
		));
		foreach (['stock_asset_dividend_record', 'stock_asset_dividend_forecast'] as $table) {
			self::assertTrue(
				$connection->createSchemaManager()->listTableColumns($table)['app_admin_id']->getNotnull(),
			);
			$connection->update(
				$table,
				['app_admin_id' => $oldOwner->getId()->toString()],
				['id' => $originals[$table]['id']],
			);
			$this->assertMigrationRejected('down', 'another user has personal records');
			$connection->update($table, ['app_admin_id' => $filipId], ['id' => $originals[$table]['id']]);
		}

		$anotherRecord = $originals['stock_asset_dividend_record'];
		$anotherRecord['id'] = Uuid::uuid4()->toString();
		$anotherRecord['app_admin_id'] = $oldOwner->getId()->toString();
		$connection->insert('stock_asset_dividend_record', $anotherRecord);
		self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM stock_asset_dividend_record'));
		foreach (['stock_asset_dividend_record', 'stock_asset_dividend_forecast_record'] as $table) {
			$duplicate = $connection->fetchAssociative(
				'SELECT * FROM ' . $table . ' WHERE id = ?',
				[$originals[$table]['id']],
			);
			$duplicate['id'] = Uuid::uuid4()->toString();
			try {
				$connection->insert($table, $duplicate);
				self::fail('Duplicate owner/event or forecast/asset must be rejected.');
			} catch (UniqueConstraintViolationException) {
				self::assertSame(
					1,
					(int) $connection->fetchOne(
						'SELECT COUNT(*) FROM ' . $table . ' WHERE id = ?',
						[$originals[$table]['id']],
					),
				);
			}
		}

		$connection->delete('stock_asset_dividend_record', ['id' => $anotherRecord['id']]);
		$this->applyMigration('down');
		foreach ($originals as $table => $original) {
			self::assertSame($original, $connection->fetchAssociative('SELECT * FROM ' . $table));
		}

		$this->applyMigration('up');
	}

	private function insertAdmin(string $username): string
	{
		$id = Uuid::uuid4()->toString();
		$this->getService(Connection::class)->insert('app_admin', [
			'id' => $id, 'name' => $username, 'username' => $username, 'email' => $username . '@example.test',
			'password' => 'unused-test-password', 'force_new_password' => 0, 'sys_admin' => 0,
			'created_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-10-01 00:00:00',
		]);
		return $id;
	}

	private function applyMigration(string $direction): void
	{
		$migration = $this->createMigration();
		$migration->$direction(new Schema());
		foreach ($migration->getSql() as $query) {
			$this->getService(Connection::class)->executeStatement(
				$query->getStatement(),
				$query->getParameters(),
				$query->getTypes(),
			);
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
		return $factory->getMigrationFactory()->createVersion('Migrations\\Version20261018113749');
	}

}
