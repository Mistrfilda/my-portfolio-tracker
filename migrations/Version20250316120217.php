<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250316120217 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bank_income ADD bank_account_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE bank_income ADD CONSTRAINT FK_1FCD4DAD12CB990C FOREIGN KEY (bank_account_id) REFERENCES bank_acount (id)');
        $this->addSql('CREATE INDEX IDX_1FCD4DAD12CB990C ON bank_income (bank_account_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bank_income DROP FOREIGN KEY FK_1FCD4DAD12CB990C');
        $this->addSql('DROP INDEX IDX_1FCD4DAD12CB990C ON bank_income');
        $this->addSql('ALTER TABLE bank_income DROP bank_account_id');
    }
}
