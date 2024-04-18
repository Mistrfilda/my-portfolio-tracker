<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240417202412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bank_expense_expense_tag (bank_expense_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', expense_tag_id INT NOT NULL, INDEX IDX_2FB62F3D7A351DCD (bank_expense_id), INDEX IDX_2FB62F3D7C7DB204 (expense_tag_id), PRIMARY KEY(bank_expense_id, expense_tag_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE bank_expense_expense_tag ADD CONSTRAINT FK_2FB62F3D7A351DCD FOREIGN KEY (bank_expense_id) REFERENCES bank_expense (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bank_expense_expense_tag ADD CONSTRAINT FK_2FB62F3D7C7DB204 FOREIGN KEY (expense_tag_id) REFERENCES expense_tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bank_expense ADD main_tag_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE bank_expense ADD CONSTRAINT FK_3AEE50825CEDB07 FOREIGN KEY (main_tag_id) REFERENCES expense_tag (id)');
        $this->addSql('CREATE INDEX IDX_3AEE50825CEDB07 ON bank_expense (main_tag_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bank_expense_expense_tag DROP FOREIGN KEY FK_2FB62F3D7A351DCD');
        $this->addSql('ALTER TABLE bank_expense_expense_tag DROP FOREIGN KEY FK_2FB62F3D7C7DB204');
        $this->addSql('DROP TABLE bank_expense_expense_tag');
        $this->addSql('ALTER TABLE bank_expense DROP FOREIGN KEY FK_3AEE50825CEDB07');
        $this->addSql('DROP INDEX IDX_3AEE50825CEDB07 ON bank_expense');
        $this->addSql('ALTER TABLE bank_expense DROP main_tag_id');
    }
}
