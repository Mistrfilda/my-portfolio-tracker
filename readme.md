# My Portfolio Tracker

Personal finance and investment portfolio management application focused on long-term portfolio tracking, cash-flow overview, and automation around market data.

This repository contains the application code for my personal portfolio tracker. The app runs best in a Kubernetes environment, and the full infrastructure setup is available in the [kubedev repository](https://github.com/Mistrfilda/kubedev).

## What the application covers

- Tracking of stocks, dividends, cryptocurrencies, and Portu portfolios
- Portfolio valuation, allocation overview, and historical statistics
- Financial goals with automatic progress updates
- Expense and income tracking, including bank statement processing
- Automated downloads for prices, dividends, exchange rates, and other background jobs
- Notifications and monitoring for important events

## Technology Stack

### Backend
- **PHP 8.5** with **Nette Framework 3.1**
- **Doctrine ORM**
- **Symfony Console**
- **RabbitMQ**
- **Monolog**

### Frontend
- **Tailwind CSS 4**
- **TypeScript**
- **AlpineJS**
- **Chart.js**
- **Naja**

### Infrastructure
- **Docker**
- **Kubernetes**

## Main modules

- `src/Stock` - stock assets, positions, dividends, valuations, and AI analysis
- `src/Portu` - Portu robo-advisor portfolios and positions
- `src/Crypto` - cryptocurrency tracking
- `src/Currency` - exchange rates and currency conversions
- `src/Cash` - expenses, bank imports, and income tracking
- `src/Goal` - portfolio and income goals
- `src/JobRequest` and `src/RabbitMQ` - asynchronous processing and queue integration
- `src/UI` - shared presenters, controls, datagrids, forms, filters, and charts

## Core functionality

### Investments
- Stock portfolio management with open and closed positions
- Dividend history, yield tracking, and dividend-related automation
- Cryptocurrency tracking
- Portu portfolio overview and price records
- Multi-currency valuation with exchange-rate support

### Finance overview
- Expense tracking and categorization
- Bank statement import and parsing
- Work income tracking
- Financial goal management and progress monitoring

### Analytics and automation
- Portfolio value, invested amount, profit/loss, and allocation charts
- Historical statistics and visualizations
- Automated downloads for prices, dividends, valuations, and exchange rates
- RabbitMQ-based background processing for heavier tasks
- Monitoring and notifications for selected events

## Project structure

- `src/` - main PHP application code
- `assets/` - frontend assets, TypeScript, CSS, and SVG icons
- `tests/` - PHPUnit tests
- `config/` - Nette and local environment configuration
- `docker/` - Docker-related configuration
- `migrations/` - database migrations
- `puppeter/` - Node.js scripts for scraping and data downloads

## Local development

### Requirements
- PHP 8.5+
- Composer
- Node.js 24+ and npm
- Docker
- MariaDB/MySQL and RabbitMQ via Docker or another local setup

### Useful commands
- Frontend watch mode: `npm run watch-dev`
- Production frontend build: `npm run build-prod`
- Unit tests: `composer test-unit`
- Integration tests: `composer test-integration`
- Full project verification: `composer build-all`

## Notes

- Local overrides belong in `config/config.local.neon` or `config/config-docker.local.neon`
- For the full Kubernetes-based environment, use the linked `kubedev` repository
