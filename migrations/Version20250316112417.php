<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250316112417 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bank_expense ADD bank_account_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE bank_expense ADD CONSTRAINT FK_3AEE50812CB990C FOREIGN KEY (bank_account_id) REFERENCES bank_acount (id)');
        $this->addSql('CREATE INDEX IDX_3AEE50812CB990C ON bank_expense (bank_account_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bank_expense DROP FOREIGN KEY FK_3AEE50812CB990C');
        $this->addSql('DROP INDEX IDX_3AEE50812CB990C ON bank_expense');
        $this->addSql('ALTER TABLE bank_expense DROP bank_account_id');
    }
}
