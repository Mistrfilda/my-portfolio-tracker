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

class NotificationRecipientMigrationTest extends IntegrationTestCase
{

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testHistoricalNotificationsKeepContentAndStateAndBelongOnlyToFilip(): void
	{
		$connection = $this->getService(Connection::class);
		$this->applyMigration('down');
		$otherId = $this->insertAdmin('filip2');
		$notifications = [];
		foreach (['new_dividend' => 'created', 'asset_trends' => 'sent', 'goals_updates' => 'send_in_progress'] as $type => $state) {
			$row = [
				'id' => Uuid::uuid4()->toString(), 'notification_type_enum' => $type,
				'notification_channels' => '["discord"]', 'message' => 'Legacy message',
				'notification_state_enum' => $state, 'created_at' => '2026-10-01 00:00:00',
				'parameters' => '{"trend_days_threshold":7}', 'data' => '{"legacy":"snapshot"}',
			];
			$connection->insert('notification', $row);
			$notifications[] = $row;
		}

		$this->assertMigrationRejected('up', 'user filip does not exist');
		self::assertArrayNotHasKey(
			'recipient_id',
			$connection->createSchemaManager()->listTableColumns('notification'),
		);
		$filipId = $this->insertAdmin('filip');
		$this->applyMigration('up');
		foreach ($notifications as $original) {
			$row = $connection->fetchAssociative(
				'SELECT id, notification_type_enum, notification_channels, message, notification_state_enum,'
				. ' created_at, parameters, data, recipient_id FROM notification WHERE id = ?',
				[$original['id']],
			);
			self::assertSame($original + ['recipient_id' => $filipId], $row);
		}

		$connection->update('notification', ['recipient_id' => $otherId], ['id' => $notifications[0]['id']]);
		$this->assertMigrationRejected('down', 'another user has notifications');
		$connection->update('notification', ['recipient_id' => $filipId], ['id' => $notifications[0]['id']]);
		$this->applyMigration('down');
		self::assertArrayNotHasKey(
			'recipient_id',
			$connection->createSchemaManager()->listTableColumns('notification'),
		);
		$this->applyMigration('up');
		self::assertSame(
			3,
			(int) $connection->fetchOne('SELECT COUNT(*) FROM notification WHERE recipient_id = ?', [$filipId]),
		);
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
		return $factory->getMigrationFactory()->createVersion('Migrations\\Version20261018112629');
	}

}
