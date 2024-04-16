<?php

declare(strict_types=1);

namespace Migrations;

use App\Cash\Expense\Category\ExpenseCategoryEnum;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240416195230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE expense_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, enum_name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');

		foreach (ExpenseCategoryEnum::getIds() as $enum => $id) {
			$this->addSql(sprintf(
				'INSERT into expense_category (`id`, `name`, `enum_name`, `created_at`) values ("%s", "%s", "%s", now())',
				$id, ExpenseCategoryEnum::from($enum)->format(), $enum
			));
		}
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE expense_category');
    }
}
