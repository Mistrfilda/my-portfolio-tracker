<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240312191100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_asset ADD should_download_price TINYINT(1) NOT NULL');
		$this->addSql('UPDATE stock_asset set should_download_price = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_asset DROP should_download_price');
    }
}
