<?php

declare(strict_types = 1);

namespace App\Test\Integration\Migrations;

use App\Admin\AppAdmin;
use App\Dashboard\DashboardValueGroupEnum;
use App\Statistic\Performance\PortfolioPerformanceCalculator;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatistic;
use App\Statistic\PortfolioStatistic;
use App\Statistic\PortfolioStatisticControlTypeEnum;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortolioStatisticType;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Exception\AbortMigration;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class StatisticOwnershipMigrationTest extends IntegrationTestCase
{

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testBackfillPreservesHistoryAndReportPayloadsAndGuardsRollback(): void
	{
		$em = $this->getService(EntityManagerInterface::class);
		$db = $this->getService(Connection::class);
		$start = new ImmutableDateTime('2026-01-01');
		$end = new ImmutableDateTime('2026-01-31');
		$owner = new AppAdmin('Filip', 'filip', 'filip@example.test', 'unused', $end, false, false);
		$record = new PortfolioStatisticRecord($owner, $end);
		$value = new PortfolioStatistic(
			$record,
			$end,
			DashboardValueGroupEnum::TOTAL_VALUES,
			'Original value',
			'1 300 CZK',
			'blue',
			null,
			null,
			PortolioStatisticType::TOTAL_VALUE_IN_CZK,
			PortfolioStatisticControlTypeEnum::SIMPLE_VALUE,
			null,
		);
		$month = new PortfolioPerformanceCalculator()->calculateMonth(
			$owner,
			$start,
			$end,
			1000,
			1000,
			1000,
			1300,
			100,
			20,
			0,
			$end,
		);
		$report = new PortfolioPeriodStatistic($owner, $start, $end, $end);
		$report->markProcessing($end);
		$report->markFailed('Original processing error', $end);
		foreach ([$owner, $record, $value, $month, $report] as $entity) {
			$em->persist($entity);
		}

		$em->flush();
		$em->clear();
		$db->update('portfolio_period_statistic', [
			'summary_json' => Json::encode(['original' => 'summary']),
			'asset_section_json' => Json::encode(['original' => 'assets']),
			'dividend_section_json' => Json::encode(['original' => 'dividends']),
			'chart_section_json' => Json::encode(['original' => 'charts']),
			'effective_start_at' => '2026-01-01 00:00:00',
			'effective_end_at' => '2026-01-31 00:00:00',
		], ['id' => $report->getId()->toString()]);
		$this->applyMigration('down');
		$db->update('app_admin', ['username' => 'filip2'], ['id' => $owner->getId()->toString()]);
		$roots = ['portfolio_statistic_record', 'portfolio_performance_month', 'portfolio_period_statistic'];
		$originals = [];
		foreach ([...$roots, 'portfolio_statistic'] as $table) {
			$originals[$table] = $db->fetchAssociative('SELECT * FROM ' . $table);
		}

		$this->assertMigrationRejected('up', 'user filip does not exist');
		foreach ($roots as $table) {
			self::assertArrayNotHasKey('app_admin_id', $db->createSchemaManager()->listTableColumns($table));
		}

		$filip = new AppAdmin('New Filip', 'filip', 'new-filip@example.test', 'unused', $end, false, false);
		$em->persist($filip);
		$em->flush();
		$filipId = $filip->getId()->toString();
		$this->applyMigration('up');
		foreach ($originals as $table => $original) {
			self::assertSame(
				in_array($table, $roots, true) ? $original + ['app_admin_id' => $filipId] : $original,
				$db->fetchAssociative('SELECT * FROM ' . $table),
			);
		}

		foreach ($roots as $table) {
			self::assertTrue($db->createSchemaManager()->listTableColumns($table)['app_admin_id']->getNotnull());
			$db->update($table, ['app_admin_id' => $owner->getId()->toString()], ['id' => $originals[$table]['id']]);
			$this->assertMigrationRejected('down', 'another user has personal history');
			$db->update($table, ['app_admin_id' => $filipId], ['id' => $originals[$table]['id']]);
		}

		$duplicate = $originals['portfolio_performance_month'];
		unset($duplicate['id']);
		$duplicate['app_admin_id'] = $filipId;
		try {
			$db->insert('portfolio_performance_month', $duplicate);
			self::fail('An owner must have at most one result for a month.');
		} catch (UniqueConstraintViolationException) {
			self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM portfolio_performance_month'));
		}

		$duplicate['app_admin_id'] = $owner->getId()->toString();
		$db->insert('portfolio_performance_month', $duplicate);
		self::assertSame(2, (int) $db->fetchOne('SELECT COUNT(*) FROM portfolio_performance_month'));
		$this->assertMigrationRejected('down', 'another user has personal history');
		$db->delete('portfolio_performance_month', ['app_admin_id' => $owner->getId()->toString()]);
		$this->applyMigration('down');
		foreach ($originals as $table => $original) {
			self::assertSame($original, $db->fetchAssociative('SELECT * FROM ' . $table));
		}

		$this->applyMigration('up');
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
		return $factory->getMigrationFactory()->createVersion('Migrations\\Version20261018122526');
	}

}
