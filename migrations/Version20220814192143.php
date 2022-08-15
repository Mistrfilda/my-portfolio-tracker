<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220814192143 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_position ADD app_admin_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE stock_position ADD CONSTRAINT FK_9EAB0B76CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
        $this->addSql('CREATE INDEX IDX_9EAB0B76CABE2BD ON stock_position (app_admin_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_position DROP FOREIGN KEY FK_9EAB0B76CABE2BD');
        $this->addSql('DROP INDEX IDX_9EAB0B76CABE2BD ON stock_position');
        $this->addSql('ALTER TABLE stock_position DROP app_admin_id, CHANGE id id INT AUTO_INCREMENT NOT NULL');
    }
}
