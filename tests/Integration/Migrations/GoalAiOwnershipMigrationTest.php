<?php

declare(strict_types = 1);

namespace App\Test\Integration\Migrations;

use App\Admin\AppAdmin;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyEnum;
use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalRepeatableEnum;
use App\Goal\PortfolioGoalTypeEnum;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlan;
use App\Stock\AiAnalysis\StockAiAnalysisFollowUpQuestion;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisSettings;
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

class GoalAiOwnershipMigrationTest extends IntegrationTestCase
{

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testBackfillPreservesGoalsSnapshotsAndChildrenAndGuardsRollback(): void
	{
		$em = $this->getService(EntityManagerInterface::class);
		$db = $this->getService(Connection::class);
		$now = new ImmutableDateTime('2026-06-01');
		$owner = new AppAdmin('Filip', 'filip', 'filip@example.test', 'unused', $now, false, false);
		$goal = new PortfolioGoal(
			$owner,
			$now,
			$now->modify('+1 month'),
			PortfolioGoalTypeEnum::TOTAL_INCOME,
			5000,
			PortfolioGoalRepeatableEnum::MONTHLY,
			$now,
		);
		$goal->start(100, 300, $now);
		$run = new StockAiAnalysisRun(
			$owner,
			'Original private prompt',
			true,
			false,
			false,
			null,
			$now,
			inputSnapshot: ['private' => 'Original snapshot'],
		);
		$question = new StockAiAnalysisFollowUpQuestion($run, 'Private question', 'Original follow-up prompt', $now);
		$question->setResponse('Original response', $now);
		$settings = new StockAiAnalysisSettings($owner, 'Original investor instructions', $now);
		$plan = new StockAiInvestmentPlan(
			$run,
			new AssetPriceEmbeddable(1000, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(1000, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(
				5000,
				CurrencyEnum::CZK,
			),
			20,
			16.67,
			'Original plan prompt',
			['private' => 'Original plan snapshot'],
			$now,
		);
		foreach ([$owner, $goal, $run, $question, $settings, $plan] as $entity) {
			$em->persist($entity);
		}

		$em->flush();
		$em->clear();
		$db->insert('stock_ai_analysis_stock_result', [
			'id' => Uuid::uuid4()->toString(), 'stock_ai_analysis_run_id' => $run->getId()->toString(),
			'type' => 'portfolio', 'reasoning' => 'Original private result',
			'created_at' => '2026-06-01 00:00:00', 'updated_at' => '2026-06-01 00:00:00',
		]);
		$this->applyMigration('down');
		$db->update('app_admin', ['username' => 'filip2'], ['id' => $owner->getId()->toString()]);
		$originals = [];
		$roots = ['portfolio_goal', 'stock_ai_analysis_run', 'stock_ai_analysis_settings'];
		foreach ([...$roots, 'stock_ai_analysis_stock_result', 'stock_ai_analysis_follow_up_question', 'stock_ai_investment_plan'] as $table) {
			$originals[$table] = $db->fetchAssociative('SELECT * FROM ' . $table);
		}

		$this->assertMigrationRejected('up', 'user filip does not exist');
		foreach ($roots as $table) {
			self::assertArrayNotHasKey('app_admin_id', $db->createSchemaManager()->listTableColumns($table));
		}

		$filip = new AppAdmin('New Filip', 'filip', 'new-filip@example.test', 'unused', $now, false, false);
		$em->persist($filip);
		$em->flush();
		$filipId = $filip->getId()->toString();
		$duplicate = $originals['stock_ai_analysis_settings'];
		$duplicate['id'] = 2;
		$db->insert('stock_ai_analysis_settings', $duplicate);
		$this->assertMigrationRejected('up', 'more than one legacy settings');
		$db->delete('stock_ai_analysis_settings', ['id' => 2]);
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
			$this->assertMigrationRejected('down', 'another user has personal records');
			$db->update($table, ['app_admin_id' => $filipId], ['id' => $originals[$table]['id']]);
		}

		$duplicate['app_admin_id'] = $filipId;
		try {
			$db->insert('stock_ai_analysis_settings', $duplicate);
			self::fail('An owner must have at most one settings record.');
		} catch (UniqueConstraintViolationException) {
			self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM stock_ai_analysis_settings'));
		}

		$secondSettings = new StockAiAnalysisSettings(
			$em->getReference(AppAdmin::class, $owner->getId()),
			'Second owner',
			$now,
		);
		$em->persist($secondSettings);
		$em->flush();
		self::assertGreaterThan(1, $secondSettings->getId());
		$this->assertMigrationRejected('down', 'another user has personal records');
		$em->remove($secondSettings);
		$em->flush();
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
		return $factory->getMigrationFactory()->createVersion('Migrations\\Version20261018120200');
	}

}
