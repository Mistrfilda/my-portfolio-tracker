<?php

declare(strict_types = 1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101120000 extends AbstractMigration
{

	public function getDescription(): string
	{
		return 'Add charity and leisure categories and clarify general expense category names';
	}

	public function up(Schema $schema): void
	{
		foreach ([19 => ['charity', 'Charita'], 20 => ['leisure', 'Volný čas']] as $id => [$enum, $name]) {
			$this->abortIf(
				(int) $this->connection->fetchOne(
					'SELECT COUNT(*) FROM expense_category WHERE (id = ? AND enum_name <> ?) OR (enum_name = ? AND id <> ?)',
					[$id, $enum, $enum, $id],
				) > 0,
				'Expense category ID conflicts with the fixed enum mapping.',
			);
			// Fresh installations already insert these values through the original category migration.
			$this->addSql(
				'INSERT INTO expense_category (id, name, enum_name, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE name = VALUES(name)',
				[$id, $name, $enum],
			);
		}

		$this->addSql("UPDATE expense_category SET name = 'Ostatní nákupy' WHERE id = 4 AND enum_name = 'eshop_other'");
		$this->addSql("UPDATE expense_category SET name = 'Nezařazené platby' WHERE id = 8 AND enum_name = 'other_bank_transfer'");
	}

	public function down(Schema $schema): void
	{
		$this->abortIf(
			(int) $this->connection->fetchOne('SELECT COUNT(*) FROM expense_tag WHERE expense_category_id IN (19, 20)') > 0,
			'Reassign tags before removing charity and leisure categories.',
		);
		$this->addSql("DELETE FROM expense_category WHERE (id = 19 AND enum_name = 'charity') OR (id = 20 AND enum_name = 'leisure')");
		$this->addSql("UPDATE expense_category SET name = 'Eshop - jiné' WHERE id = 4 AND enum_name = 'eshop_other'");
		$this->addSql("UPDATE expense_category SET name = 'Převod do jiné banky' WHERE id = 8 AND enum_name = 'other_bank_transfer'");
	}

}
