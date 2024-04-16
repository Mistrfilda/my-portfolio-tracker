<?php

declare(strict_types=1);

namespace Migrations;

use App\Cash\Expense\Category\ExpenseCategoryEnum;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240416210306 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
	    $this->addSql(sprintf(
		    'INSERT into expense_category (`id`, `name`, `enum_name`, `created_at`) values ("%s", "%s", "%s", now())',
		    8, ExpenseCategoryEnum::OTHER_BANK_TRANSFER->format(),  ExpenseCategoryEnum::OTHER_BANK_TRANSFER->value
	    ));

	    $this->addSql(sprintf(
		    'INSERT into expense_category (`id`, `name`, `enum_name`, `created_at`) values ("%s", "%s", "%s", now())',
		    9, ExpenseCategoryEnum::REVOLUT->format(),  ExpenseCategoryEnum::REVOLUT->value
	    ));
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
