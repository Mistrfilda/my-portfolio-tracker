---
name: api-slim
description: Add or change Slim REST endpoints, middleware, authentication, or OpenAPI contracts in this project.
---

## REST API (Slim inside Nette)

The project exposes a REST API backed by Slim Framework, registered as Nette DI services.

### Core components (`src/Api/`)

- **`SlimAppFactory`** — builds the Slim app; accepts `corsAllowedOrigins` (`%api.corsAllowedOrigins%`) and `debugMode`.
- **`RouterFactory`** — registers routes with OAuth scope enforcement and OpenAPI validation.
- **`ApiAuthenticationMiddlewareFactory`** — creates Bearer authentication middleware for the route's `OAuthScope`. Set `requireUser: true` for personal endpoints; a client-only token then receives 403. Legacy `X-Api-Key` is enabled explicitly only for the existing home record POST and home records GET, using the configured user's ownership.
- **`ApiKeyMiddleware`** (`apiKeyMiddleware`) — preserves sensor authentication against `%api.apiKeys%`. Never enable it for additional endpoints implicitly.
- **`src/OAuth2/`** — League OAuth server, database storage, sessionless browser authorization, token/revocation endpoints, and client CLI commands. Use [oauth2-server](../oauth2-server/SKILL.md) when changing these behaviors or integrating clients.
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
6. Choose and enforce an `OAuthScope`; require user identity for personal data. Add new scopes to the enum, client policy, OpenAPI, and tests. Existing keys are for legacy home routes only.

### Rules

- OpenAPI (`doc/openapi.yaml`) is the single source of truth — spec first, code second. YAML/OpenAPI files use spaces for indentation.
- API responses must be JSON; build them with `Nette\Utils\Json` (no `json_encode` direct). The OAuth browser authorization endpoint returns HTML/redirects; League validates OAuth protocol requests and produces token responses/errors.
- API identity comes from a validated token, never a Nette session. Keep client-only identity distinct from user identity.
- Personal queries and nested serializers must use the authenticated owner. Shared stock/dividend entities can contain multiple owners' positions or records; do not serialize their unfiltered collections. See [OAuth access rules](../oauth2-server/SKILL.md#scopes-and-data-ownership).
- Validate inputs with `App\Utils\TypeValidator` after OpenAPI validation.
- Exception messages and error payloads in English.
- For tests extend `App\Test\Integration\Api\ApiTestCase` — see `testing-conventions` skill.
- Don't use RabbitMQ directly from a controller; enqueue via `JobRequest` — see `job-request`.
