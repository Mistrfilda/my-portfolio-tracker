<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251006135746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_asset_industry (name VARCHAR(255) NOT NULL, mapping_name VARCHAR(255) NOT NULL, current_peratio DOUBLE PRECISION DEFAULT NULL, id CHAR(36) NOT NULL, updated_at DATETIME NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_asset ADD industry_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_asset ADD CONSTRAINT FK_9EBF46AD2B19A734 FOREIGN KEY (industry_id) REFERENCES stock_asset_industry (id)');
        $this->addSql('CREATE INDEX IDX_9EBF46AD2B19A734 ON stock_asset (industry_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE stock_asset_industry');
        $this->addSql('ALTER TABLE stock_asset DROP FOREIGN KEY FK_9EBF46AD2B19A734');
        $this->addSql('DROP INDEX IDX_9EBF46AD2B19A734 ON stock_asset');
        $this->addSql('ALTER TABLE stock_asset DROP industry_id');
    }
}
