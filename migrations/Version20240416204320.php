<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240416204320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE expense_tag (id INT AUTO_INCREMENT NOT NULL, expense_category_id INT DEFAULT NULL, parent_tag_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, regexes JSON NOT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_AF79E27F6B2A3179 (expense_category_id), INDEX IDX_AF79E27FF5C1A0D7 (parent_tag_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE expense_tag ADD CONSTRAINT FK_AF79E27F6B2A3179 FOREIGN KEY (expense_category_id) REFERENCES expense_category (id)');
        $this->addSql('ALTER TABLE expense_tag ADD CONSTRAINT FK_AF79E27FF5C1A0D7 FOREIGN KEY (parent_tag_id) REFERENCES expense_tag (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE expense_tag DROP FOREIGN KEY FK_AF79E27F6B2A3179');
        $this->addSql('ALTER TABLE expense_tag DROP FOREIGN KEY FK_AF79E27FF5C1A0D7');
        $this->addSql('DROP TABLE expense_tag');
    }
}
