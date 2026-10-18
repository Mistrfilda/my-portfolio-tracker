<?php

declare(strict_types = 1);

namespace App\Test\Integration\Migrations;

use App\Cash\Bank\Account\BankAccountTypeEnum;
use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\BankTransactionType;
use App\Home\Device\HomeDeviceType;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Exception\AbortMigration;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Ramsey\Uuid\Uuid;

class PersonalDataOwnershipMigrationTest extends IntegrationTestCase
{

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testLegacyBackfillPreservesParentRelationsAndGuardsUnsafeMigration(): void
	{
		$connection = $this->getService(Connection::class);
		$this->applyMigration('down');
		$filip = Uuid::uuid4()->toString();
		$other = Uuid::uuid4()->toString();
		$account = Uuid::uuid4()->toString();
		$home = Uuid::uuid4()->toString();
		$device = Uuid::uuid4()->toString();
		$work = Uuid::uuid4()->toString();
		$expense = Uuid::uuid4()->toString();
		$now = '2026-01-15 00:00:00';
		$this->insertAdmin($other, 'filip2');
		$connection->insert(
			'bank_acount',
			['id' => $account, 'name' => 'Legacy account', 'bank' => 'Bank', 'type' => BankAccountTypeEnum::PERSONAL->value],
		);
		$connection->insert(
			'home',
			['id' => $home, 'name' => 'Legacy home', 'created_at' => $now, 'updated_at' => $now],
		);
		$connection->insert(
			'home_device',
			[
				'id' => $device,
				'home_id' => $home,
				'name' => 'Legacy device',
				'internal_id' => 'legacy-device',
				'type' => HomeDeviceType::TEMPERATURE->value,
				'created_at' => $now,
				'updated_at' => $now,
			],
		);
		$connection->insert(
			'expense_tag',
			['id' => 1, 'name' => 'Legacy tag', 'regexes' => '[]', 'is_tax' => 0, 'created_at' => $now, 'updated_at' => $now],
		);
		$connection->insert(
			'expense_tag',
			[
				'id' => 2,
				'parent_tag_id' => 1,
				'name' => 'Legacy child tag',
				'regexes' => '[]',
				'is_tax' => 0,
				'created_at' => $now,
				'updated_at' => $now,
			],
		);
		$workRow = [
			'id' => $work,
			'year' => 2026,
			'month' => 1,
			'hours' => 10.0,
			'hourly_rate' => 500,
			'currency_enum' => 'CZK',
			'created_at' => $now,
			'updated_at' => $now,
		];
		$connection->insert('work_monthly_income', $workRow);
		$expenseRow = [
			'id' => $expense, 'bank_account_id' => $account, 'identifier' => 'legacy-transaction',
			'source' => BankSourceEnum::KOMERCNI_BANKA->value, 'bank_transaction_type' => BankTransactionType::CARD_PAYMENT->value,
			'amount' => 100.0, 'currency' => 'CZK', 'transaction_raw_content' => 'Legacy transaction',
			'created_at' => $now, 'updated_at' => $now, 'main_tag_id' => 1, 'main_tag_set_manually' => 1, 'other_tag_set_manually' => 0,
		];
		$connection->insert('bank_expense', $expenseRow);
		$this->assertMigrationRejected('up', 'user filip does not exist');
		self::assertArrayNotHasKey('app_admin_id', $connection->createSchemaManager()->listTableColumns('bank_acount'));
		$this->insertAdmin($filip, 'filip');
		$duplicateWork = Uuid::uuid4()->toString();
		$connection->insert('work_monthly_income', array_replace($workRow, ['id' => $duplicateWork]));
		$this->assertMigrationRejected('up', 'duplicate year and month');
		$connection->delete('work_monthly_income', ['id' => $duplicateWork]);
		$this->applyMigration('up');
		foreach (['bank_acount', 'expense_tag', 'home', 'work_monthly_income'] as $table) {
			self::assertSame([$filip], $connection->fetchFirstColumn('SELECT DISTINCT app_admin_id FROM ' . $table));
			self::assertTrue(
				$connection->createSchemaManager()->listTableColumns($table)['app_admin_id']->getNotnull(),
			);
		}

		self::assertSame(
			$account,
			$connection->fetchOne('SELECT bank_account_id FROM bank_expense WHERE id = ?', [$expense]),
		);
		self::assertSame($home, $connection->fetchOne('SELECT home_id FROM home_device WHERE id = ?', [$device]));
		self::assertSame(1, (int) $connection->fetchOne('SELECT parent_tag_id FROM expense_tag WHERE id = 2'));
		self::assertSame(
			10.0,
			(float) $connection->fetchOne('SELECT hours FROM work_monthly_income WHERE id = ?', [$work]),
		);
		$connection->insert(
			'work_monthly_income',
			array_replace($workRow, ['id' => $duplicateWork, 'app_admin_id' => $other]),
		);
		$this->assertMigrationRejected('down', 'another user has personal records');
		$connection->delete('work_monthly_income', ['id' => $duplicateWork]);
		$secondAccount = Uuid::uuid4()->toString();
		$duplicateExpense = Uuid::uuid4()->toString();
		$connection->insert(
			'bank_acount',
			[
				'id' => $secondAccount,
				'app_admin_id' => $filip,
				'name' => 'Second account',
				'bank' => 'Bank',
				'type' => BankAccountTypeEnum::PERSONAL->value,
			],
		);
		$connection->insert(
			'bank_expense',
			array_replace($expenseRow, ['id' => $duplicateExpense, 'bank_account_id' => $secondAccount]),
		);
		$this->assertMigrationRejected('down', 'identifiers are shared by multiple bank accounts');
		$connection->delete('bank_expense', ['id' => $duplicateExpense]);
		$connection->delete('bank_acount', ['id' => $secondAccount]);
		$this->applyMigration('down');
		self::assertArrayNotHasKey('app_admin_id', $connection->createSchemaManager()->listTableColumns('home'));
		$this->applyMigration('up');
		self::assertSame(
			$filip,
			$connection->fetchOne('SELECT app_admin_id FROM work_monthly_income WHERE id = ?', [$work]),
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
		return $factory->getMigrationFactory()->createVersion('Migrations\\Version20261018104607');
	}

}
