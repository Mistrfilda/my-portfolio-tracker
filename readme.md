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
- `src/Api` and `src/OAuth2` - REST API and OAuth authorization, including client registration commands

The [OAuth2 server skill](.agents/skills/oauth2-server/SKILL.md) covers maintenance rules, scopes, sensor compatibility, and the follow-up for per-account data isolation. Its [client setup reference](.agents/skills/oauth2-server/references/client-setup.md) describes deployment, registration commands, and mobile/desktop integration.

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
- Node.js 24+ and npm 11.10+ (required for the 5-day `min-release-age` install policy)
- Docker
- MariaDB/MySQL and RabbitMQ via Docker or another local setup

### Useful commands
- Frontend watch mode: `npm run watch-dev`
- Production frontend build: `npm run build-prod`
- Unit tests: `composer test-unit`
- Integration tests: `composer test-integration`
- Structural checks for `AGENTS.md`, skills and their reference links, and Codex TOML syntax: `composer agent-docs`. This does not assess instruction quality or validate every Codex setting against its runtime schema.
- Full project verification: `composer build-all`

### OAuth client registration

Run these commands from the project root in the environment where the client should be registered. The OAuth database migration must already be applied.

Create a server integration using Client Credentials:

```bash
bin/console oauth:client:create "Reporting service" --scope=api:read
```

The command generates a `client_id` and a `client_secret`. Save the secret when it is displayed: only its hash is stored, so it cannot be retrieved later. Add `--json` for machine-readable output.

Create public clients for mobile and desktop applications:

```bash
bin/console oauth:client:create "Portfolio mobile" --public \
	--redirect-uri=com.example.portfolio:/oauth/callback --scope=api:read

bin/console oauth:client:create "Portfolio desktop" --public \
	--redirect-uri=http://127.0.0.1:8000/oauth/callback --scope=api:read
```

Public clients receive a `client_id` without a secret and use Authorization Code with S256 PKCE. Replace the example callback with the application's actual redirect URI. Repeat `--redirect-uri` or `--scope` for multiple values; the default scope is `api:read`, with `home:read` and `home:write` also available.

To replace a server client's secret or disable a client:

```bash
bin/console oauth:client:rotate-secret CLIENT_ID
bin/console oauth:client:disable CLIENT_ID
```

Both operations revoke the client's existing tokens. Before issuing tokens in a newly configured environment, generate the server keys once with `bin/console oauth:keys:generate`; these keys are shared by all clients and are not regenerated for each registration. See the [client setup reference](.agents/skills/oauth2-server/references/client-setup.md) for the complete integration flow.

Keys are stored in `resources/oauth` (`/var/resources/oauth` in Docker), excluded from Git and the Docker build context. Kubernetes manifests in the `kubedev` repository mount the `my-pt-oauth-pvc` volume there and initialize its ownership for `www-data`. Deploy an application image containing this path change together with the updated manifests, then generate keys once as the PHP worker's OS user:

```bash
kubectl -n default exec deployment/php-deployment -c php-container -- \
	su -s /bin/sh www-data -c 'cd /var && bin/console oauth:keys:generate'
```

Keep existing keys when migrating from `var/oauth`; do not regenerate them on every deployment. See the [server provisioning instructions](.agents/skills/oauth2-server/references/client-setup.md#server-provisioning) for migration, permissions, and backup requirements.

### Browser smoke tests

Browser smoke tests use Playwright Test and are stored in `tests/Browser`. They are read-only checks against an already running local application; the test command does not start PHP, Docker, or the database.

Prepare the local environment file from the committed example:

```bash
cp .env.browser-tests.example .env.browser-tests
```

Fill in these values:

- `PLAYWRIGHT_BASE_URL` - URL of the local application, for example `http://localhost:8000`
- `PLAYWRIGHT_LOGIN_USERNAME` - local test user login
- `PLAYWRIGHT_LOGIN_PASSWORD` - local test user password

Before the first run, install the Chromium browser used by Playwright if it is not installed yet:

```bash
npx playwright install chromium
```

Run the smoke tests with:

```bash
npm run test-browser
```

`npm run test-browser` automatically builds frontend assets first via `npm run build-dev`. For interactive authoring/debugging, use:

```bash
npm run test-browser:ui
```

## Notes

- Local overrides belong in `config/config.local.neon` or `config/config-docker.local.neon`
- For the full Kubernetes-based environment, use the linked `kubedev` repository
