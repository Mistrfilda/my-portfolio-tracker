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
- `src/` - main PHP application code
  - `Stock/` - stocks, dividends, valuations
  - `Crypto/` - cryptocurrencies
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

## Coding Standards
- Follow PSR-12 for PHP
- Use Czech comments where appropriate
- Name tests `*Test.php`
- Configuration in `.neon` files

## Important Notes
- Sensitive data (API keys, passwords) are in `docker/config-docker.local.neon` - DO NOT COMMIT
- Use Docker Compose for local development
- RabbitMQ for asynchronous task processing
- use TABS whenever possible (except OpenAPI spec where spaces are allowed)
- use Constructor property promotion whenever possible
- at the end of proccess run composer cs-fix && composer build-all to check if everything is OK

## Testing
```bash
vendor/bin/phpunit
```

## Common Tasks
- Stock price updates: Yahoo Finance scraper
- Dividends: automatic downloading and notifications
- Exchange rates: CNB and ECB
