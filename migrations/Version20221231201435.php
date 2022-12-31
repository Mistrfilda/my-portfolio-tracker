<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221231201435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE portfolio_statistic (id INT AUTO_INCREMENT NOT NULL, portfolio_statistic_record_id INT DEFAULT NULL, dashboard_value_group VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, value VARCHAR(255) NOT NULL, color VARCHAR(255) NOT NULL, svg_icon VARCHAR(255) DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9A33D7B0BE0B0E34 (portfolio_statistic_record_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE portfolio_statistic_record (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE portfolio_statistic ADD CONSTRAINT FK_9A33D7B0BE0B0E34 FOREIGN KEY (portfolio_statistic_record_id) REFERENCES portfolio_statistic_record (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE portfolio_statistic DROP FOREIGN KEY FK_9A33D7B0BE0B0E34');
        $this->addSql('DROP TABLE portfolio_statistic');
        $this->addSql('DROP TABLE portfolio_statistic_record');
    }
}
