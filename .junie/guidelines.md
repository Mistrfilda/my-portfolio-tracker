# Junie Guidelines for My Portfolio Tracker

## Project Overview
Investment portfolio tracking application built on PHP (Nette framework) with Doctrine ORM.

## Technologies
- **Backend:** PHP 8.5, Nette Framework, Doctrine ORM
- **Frontend:** Webpack, Tailwind CSS, TypeScript
- **Scraping:** Puppeteer (Node.js) for Yahoo Finance, PSE
- **Message Queue:** RabbitMQ
- **Database:** MySQL/MariaDB
- **Notifications:** Discord webhooks

## Project Structure
- `assets` - typescript, javascript and css assets
  	- `svg` - svg icons folder
- `src/` - main PHP application code
	- `Stock/` - stocks, dividends, valuations
	- `Crypto/` - cryptocurrencies
    - `Home` - home devices and sensors
	- `Currency/` - currencies (CNB, ECB)
	- `Asset/` - asset management
	- `Notification/` - notification system
	- `RabbitMQ/` - message queue consumers/producers
	- `Doctrine/` - entities and repositories
- `puppeter/` - Node.js scripts for web scraping
- `config/` - Nette configuration (.neon files)
- `docker/` - Docker configuration
- `tests/` - PHPUnit tests
- `migrations/` - database migrations

## Build & Configuration
1. **Prerequisites**: PHP 8.5+, Node.js (v24 recommended), Docker, RabbitMQ, MariaDB/MySQL.
2. **Environment**:
	- Configuration is in `config/*.neon`.
	- Local overrides go into `config/config.local.neon` or `config/config-docker.local.neon`.
3. **Setup**:
	- Run `docker-compose up -d` to start infrastructure (Database, RabbitMQ).
	- Run `composer install` to install PHP dependencies.
	- Run `npm install` to install Node.js dependencies.
	- Apply migrations: `bin/console migrations:migrate`.
	- Declare RabbitMQ queues: `bin/console-rabbit rabbitmq:declareQueuesAndExchanges`.
4. **Assets**:
	- Development: `npm run watch-dev`
	- Production: `npm run build-prod`

## Coding Standards
- **Indentation**: Use **TABS** whenever possible (except in OpenAPI spec files where spaces are allowed).
- **PHP Style**: Follow PSR-12.
- **Modern PHP**: Use Constructor property promotion where applicable.
- **Naming**: Tests must be named `*Test.php`.
- **Comments**: Use Czech comments where appropriate.
- **JSON**: Always use `Nette\Utils\Json` for serialization/deserialization.
- **Validation**: Use `App\Utils\TypeValidator` for scalar type validation.
- **Nette controls and presenter** - When using nette controls and presenter, always use Template classes for parameters, base template classes are `src/UI/Base/BaseControlTemplate.php`  (for controls), `src/UI/Base/BasePresenterTemplate.php` (for presenters).

## Doctrine & Migrations
- If you change anything in Doctrine ORM entities:
	1. Clear cache with: `composer clear`
	2. Check generated SQL: `bin/console orm:schema-tool:update --dump-sql`.
	3. If OK, create migration: `bin/console migrations:diff`.
	4. Apply migration: `bin/console migrations:migrate`.

## Testing
- **Execution**:
	- Run all tests: `vendor/bin/phpunit`
	- Unit only: `vendor/bin/phpunit tests/Unit`
	- Integration only: `vendor/bin/phpunit tests/Integration`
- **Adding Tests**:
	- Unit tests should extend `PHPUnit\Framework\TestCase`.
	- Integration/API tests should extend `App\Test\Integration\Api\ApiTestCase`.
- **Example Test**:
```php
<?php

declare(strict_types = 1);

namespace App\Test\Unit\Utils;

use App\Utils\TypeValidator;
use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{

	public function testSomething(): void
	{
		$this->assertSame('test', TypeValidator::validateString('test'));
	}

}
```

## SVG icons
- Please, do not use `<svg>` tag directly, use nette latte macro renderSvg - example: {renderSvg App\UI\Icon\SvgIcon::INCOME_SIGN->value, ['class' => 'w-5 h-5 text-green-600']}
- Always use and update `App\UI\Icon\SvgIcon` for SVG icons
- Always use and update `assets/svg` folder for SVG icons

## Important Notes
- **Security**: Sensitive data (API keys, passwords) are in `docker/config-docker.local.neon` or `config/config.local.neon`. **DO NOT COMMIT THESE FILES.**
- **Development Flow**: At the end of process, always run `composer cs-fix && composer build-all` to check if everything is OK.
- **RabbitMQ**: Used for asynchronous task processing (e.g., price updates, notifications).
- **Scraping**: Puppeteer scripts in `puppeter/` for Yahoo Finance, PSE.

## Common Tasks
- Stock price updates: Finance scraper
- Dividends: automatic downloading and notifications
- Exchange rates: CNB and ECB
