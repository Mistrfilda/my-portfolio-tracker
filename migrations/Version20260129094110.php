<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129094110 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE home (name VARCHAR(255) NOT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE home_device (internal_id VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, home_id CHAR(36) NOT NULL, INDEX IDX_F92BDF3328CDC89C (home_id), INDEX internal_id_idx (internal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE home_device_record (string_value VARCHAR(255) DEFAULT NULL, float_value DOUBLE PRECISION DEFAULT NULL, unit VARCHAR(255) DEFAULT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, home_device_id CHAR(36) NOT NULL, created_by_id CHAR(36) DEFAULT NULL, INDEX IDX_559B2E1FEA70BE90 (home_device_id), INDEX IDX_559B2E1FB03A8386 (created_by_id), INDEX created_at_idx (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE home_device ADD CONSTRAINT FK_F92BDF3328CDC89C FOREIGN KEY (home_id) REFERENCES home (id)');
        $this->addSql('ALTER TABLE home_device_record ADD CONSTRAINT FK_559B2E1FEA70BE90 FOREIGN KEY (home_device_id) REFERENCES home_device (id)');
        $this->addSql('ALTER TABLE home_device_record ADD CONSTRAINT FK_559B2E1FB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_admin (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE home_device DROP FOREIGN KEY FK_F92BDF3328CDC89C');
        $this->addSql('ALTER TABLE home_device_record DROP FOREIGN KEY FK_559B2E1FEA70BE90');
        $this->addSql('ALTER TABLE home_device_record DROP FOREIGN KEY FK_559B2E1FB03A8386');
        $this->addSql('DROP TABLE home');
        $this->addSql('DROP TABLE home_device');
        $this->addSql('DROP TABLE home_device_record');
    }
}
