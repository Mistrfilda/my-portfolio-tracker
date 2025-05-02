<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250413102053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE app_admin CHANGE id id CHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE bank_acount CHANGE id id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE bank_expense CHANGE id id CHAR(36) NOT NULL, CHANGE settlement_date settlement_date DATETIME DEFAULT NULL, CHANGE transaction_date transaction_date DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE bank_account_id bank_account_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE bank_expense_expense_tag CHANGE bank_expense_id bank_expense_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE bank_income CHANGE id id CHAR(36) NOT NULL, CHANGE settlement_date settlement_date DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE bank_account_id bank_account_id CHAR(36) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE currency_conversion CHANGE for_date for_date DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expense_category CHANGE created_at created_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expense_tag CHANGE regexes regexes JSON NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification CHANGE id id CHAR(36) NOT NULL, CHANGE notification_channels notification_channels JSON NOT NULL, CHANGE created_at created_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE portfolio_goal CHANGE id id CHAR(36) NOT NULL, CHANGE start_date start_date DATETIME NOT NULL, CHANGE end_date end_date DATETIME NOT NULL, CHANGE statistics statistics JSON NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE portfolio_statistic CHANGE created_at created_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE portfolio_statistic_record CHANGE created_at created_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE portu_asset CHANGE id id CHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE portu_asset_price_record CHANGE portu_position_id portu_position_id CHAR(36) NOT NULL, CHANGE date date DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE portu_position CHANGE id id CHAR(36) NOT NULL, CHANGE portu_asset_id portu_asset_id CHAR(36) NOT NULL, CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL, CHANGE start_date start_date DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_asset CHANGE id id CHAR(36) NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE price_downloaded_at price_downloaded_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_asset_dividend CHANGE id id CHAR(36) NOT NULL, CHANGE stock_asset_id stock_asset_id CHAR(36) NOT NULL, CHANGE ex_date ex_date DATETIME NOT NULL, CHANGE payment_date payment_date DATETIME DEFAULT NULL, CHANGE declaration_date declaration_date DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_asset_dividend_record CHANGE id id CHAR(36) NOT NULL, CHANGE stock_asset_dividend_id stock_asset_dividend_id CHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_asset_price_record CHANGE stock_asset_id stock_asset_id CHAR(36) NOT NULL, CHANGE date date DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_closed_position CHANGE id id CHAR(36) NOT NULL, CHANGE order_date order_date DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_position CHANGE id id CHAR(36) NOT NULL, CHANGE stock_asset_id stock_asset_id CHAR(36) NOT NULL, CHANGE order_date order_date DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL, CHANGE stock_closed_position_id stock_closed_position_id CHAR(36) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE system_value CHANGE datetime_value datetime_value DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE work_monthly_income CHANGE id id CHAR(36) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE notification CHANGE notification_channels notification_channels JSON NOT NULL COMMENT '(DC2Type:json)', CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE system_value CHANGE datetime_value datetime_value DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_asset_price_record CHANGE date date DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE stock_asset_id stock_asset_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_asset CHANGE price_downloaded_at price_downloaded_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE portfolio_statistic CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expense_tag CHANGE regexes regexes JSON NOT NULL COMMENT '(DC2Type:json)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE portu_position CHANGE start_date start_date DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE portu_asset_id portu_asset_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_closed_position CHANGE order_date order_date DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_asset_dividend CHANGE ex_date ex_date DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE payment_date payment_date DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE declaration_date declaration_date DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE stock_asset_id stock_asset_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE bank_acount CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE currency_conversion CHANGE for_date for_date DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE work_monthly_income CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE portu_asset_price_record CHANGE date date DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE portu_position_id portu_position_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE bank_income CHANGE settlement_date settlement_date DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE bank_account_id bank_account_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expense_category CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE portu_asset CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE portfolio_statistic_record CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE bank_expense CHANGE settlement_date settlement_date DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE transaction_date transaction_date DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE bank_account_id bank_account_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE portfolio_goal CHANGE start_date start_date DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE end_date end_date DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE statistics statistics JSON NOT NULL COMMENT '(DC2Type:json)', CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_asset_dividend_record CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE stock_asset_dividend_id stock_asset_dividend_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE bank_expense_expense_tag CHANGE bank_expense_id bank_expense_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE app_admin CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stock_position CHANGE order_date order_date DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE stock_asset_id stock_asset_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)', CHANGE stock_closed_position_id stock_closed_position_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)'
        SQL);
    }
}
