---
name: api-slim
description: Invoke before adding or modifying a REST API endpoint. Provides the `src/Api/` layer built on Slim Framework inside Nette – `SlimAppFactory`, `RouterFactory`, `ApiKeyMiddleware`, `RequestValidationMiddleware` with OpenAPI (`doc/openapi.yaml`). Use when adding a controller under `src/Api/` or a module's `Api/`, wiring a new route, or changing API authentication / CORS.
---

## REST API (Slim inside Nette)

The project exposes a REST API backed by Slim Framework, registered as Nette DI services.

### Core components (`src/Api/`)

- **`SlimAppFactory`** — builds the Slim app; accepts `corsAllowedOrigins` (`%api.corsAllowedOrigins%`) and `debugMode`.
- **`RouterFactory`** — registers all routes; injects the `apiKeyMiddleware` global middleware.
- **`ApiKeyMiddleware`** (`apiKeyMiddleware`) — validates `X-Api-Key` against `%api.apiKeys%`.
- **`RequestValidationMiddleware`** — validates requests against `doc/openapi.yaml` (OpenAPI spec is the contract).
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
- `App\Home\Device\Record\Api\HomeDeviceRecordController` — home device ingest endpoint.

### Adding a new endpoint

1. Create the controller under `src/<Module>/Api/` (or `src/Api/Controller/` for cross-cutting endpoints).
2. Define the endpoint in `doc/openapi.yaml` — **required**, `RequestValidationMiddleware` rejects unlisted paths.
3. Register the controller as a Nette service in `config/config.neon` under `services:`.
4. Add the route in `App\Api\RouterFactory` (or a module-specific router if extracted).
5. If the controller needs serialization, add a dedicated `<Entity>Serializer` service.
6. API keys: rotate via `%api.apiKeys%`; never hardcode.

### Rules

- OpenAPI (`doc/openapi.yaml`) is the single source of truth — spec first, code second. Indentation in YAML/OpenAPI files uses spaces (the only exception to the project-wide tabs rule).
- All responses must be JSON; build them with `Nette\Utils\Json` (no `json_encode` direct).
- Validate inputs with `App\Utils\TypeValidator` after OpenAPI validation.
- Exception messages and error payloads in English.
- For tests extend `App\Test\Integration\Api\ApiTestCase` — see `testing-conventions` skill.
- Don't use RabbitMQ directly from a controller; enqueue via `JobRequest` — see `job-request`.
