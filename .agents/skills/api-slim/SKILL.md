---
name: api-slim
description: Add or change Slim REST endpoints, middleware, authentication, or OpenAPI contracts in this project.
---

## REST API (Slim inside Nette)

The project exposes a REST API backed by Slim Framework, registered as Nette DI services.

### Core components (`src/Api/`)

- **`SlimAppFactory`** — builds the Slim app; accepts `corsAllowedOrigins` (`%api.corsAllowedOrigins%`) and `debugMode`.
- **`RouterFactory`** — registers routes and adds API-key and OpenAPI validation middleware to the `/api/v1` route group.
- **`ApiKeyMiddleware`** (`apiKeyMiddleware`) — validates `X-Api-Key` against `%api.apiKeys%`.
- **`RequestValidationMiddleware`** — validates requests and responses against `doc/openapi.yaml`; currently skips response validation when the response status is not declared. Keep expected response schemas in the spec.
- **Controllers** — plain classes with `__invoke(Request, Response): Response` or named action methods.
- **`Slim\CallableResolver`** (from `@psr11.container`) and **`ErrorMiddleware`** for error handling.
- PSR-7/18 helpers: `App\Http\Psr7\Psr7RequestFactory`, `App\Http\Psr18\Psr18ClientFactory`.

### Configuration (`config/config.neon`)

```
api:
	corsAllowedOrigins: []
	apiKeys: []
```

Real API keys live in `config/config.local.neon`.

### Existing controllers

- `App\Api\Controller\PingController` — health check.
- `App\Stock\Asset\Api\StockAssetController` + `StockAssetSerializer` — stock asset endpoints.
- `App\Dashboard\Api\DashboardValueController` — dashboard value endpoint.
- `App\Home\Device\Record\Api\HomeDeviceRecordController` — home device ingest endpoint.

### Adding a new endpoint

1. Create the controller under `src/<Module>/Api/` (or `src/Api/Controller/` for cross-cutting endpoints).
2. Define the endpoint in `doc/openapi.yaml` — **required**, `RequestValidationMiddleware` rejects unlisted paths.
3. Register the controller as a Nette service in `config/config.neon` under `services:`.
4. Add the route in `App\Api\RouterFactory` (or a module-specific router if extracted).
5. If the controller needs serialization, add a dedicated `<Entity>Serializer` service.
6. API keys: rotate via `%api.apiKeys%`; never hardcode.

### Rules

- OpenAPI (`doc/openapi.yaml`) is the single source of truth — spec first, code second. YAML/OpenAPI files use spaces for indentation.
- All responses must be JSON; build them with `Nette\Utils\Json` (no `json_encode` direct).
- Validate inputs with `App\Utils\TypeValidator` after OpenAPI validation.
- Exception messages and error payloads in English.
- For tests extend `App\Test\Integration\Api\ApiTestCase` — see `testing-conventions` skill.
- Don't use RabbitMQ directly from a controller; enqueue via `JobRequest` — see `job-request`.
