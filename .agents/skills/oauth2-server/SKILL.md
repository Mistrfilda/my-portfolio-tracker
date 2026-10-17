---
name: oauth2-server
description: Maintain this project's League OAuth2 server, grants, scopes, token persistence, and client CLI commands, or integrate mobile, desktop, and server clients with its API.
---

## OAuth2 Server

OAuth lives in `src/OAuth2/`, uses `league/oauth2-server`, and persists protocol state in Doctrine-managed tables. Read [client setup and integration](references/client-setup.md) when provisioning the server, registering applications, or implementing a client.

### Locate the change

- `src/OAuth2/OAuthServerFactory.php` configures grants and token lifetimes; `OAuthKeys.php` loads persistent signing/encryption keys lazily.
- `src/OAuth2/Client/` owns client registration, secret hashes, redirect allowlists, and permitted grants/scopes. `src/OAuth2/Command/` exposes their Symfony Console commands.
- `src/OAuth2/Protocol/` adapts League's entities and repository contracts. `src/OAuth2/Token/` owns persistence, transactions, revocation, and authorization families.
- `src/OAuth2/Authorization/` verifies existing account passwords and stores temporary browser authorization requests. `src/OAuth2/Http/` owns token, authorization, revocation, and rate-limit handling.
- `src/Api/ApiAuthenticationMiddleware.php` authenticates resource requests. `src/Api/RouterFactory.php` assigns scopes and the two legacy exceptions. Use [api-slim](../api-slim/SKILL.md) when changing routing or endpoint contracts.
- `doc/openapi.yaml` defines HTTP contracts; `config/config.neon` wires services and `api.oauth.keysDirectory`.

### Preserve authentication behavior

- Supported grants are `client_credentials`, `authorization_code` with mandatory S256 PKCE, and `refresh_token`. Password and implicit grants are disabled. This server does not implement OpenID Connect.
- Native mobile/desktop applications are public clients without a secret. Client credentials represent a confidential application, have no user identity, and receive no refresh token.
- API authentication never falls back to a Nette session. Browser authorization verifies the account password directly without `Nette\Security\User::login()`. Preserve the existing web administration's separate login behavior.
- Keep the ten-minute, single-use authorization transaction bound to its HttpOnly, SameSite=Strict CSRF cookie. Preserve Origin/cross-site checks, framing protection, stored client/scope/redirect parameters, and HTTP 303 callbacks. Each authorization requires credentials and consent; persistent browser SSO is not implemented.
- HTTP and HTTPS are supported, including HTTP on the local network. Mark the CSRF cookie `Secure` only for HTTPS requests.
- Accept `X-Api-Key` only on `POST /api/v1/home/device/record` and `GET /api/v1/home/device/records`, preserving sensor payloads and configured user identity. Both operations also accept their respective OAuth home scopes. New routes require OAuth unless explicitly requested otherwise.
- Reject simultaneous Bearer and API-key authentication; an invalid Bearer token must not fall back to an API key.
- Use persisted `user_id` to distinguish user and client identity: League uses the client ID as JWT `sub` for client credentials. `/api/v1/me` requires a user token. Deleted users and accounts requiring a password change cannot use user tokens.

### Scopes and shared data

| Scope | Current access |
| --- | --- |
| `api:read` | Ping, stocks including portfolio details, dashboard, and `/me` |
| `home:read` | Devices and measurements |
| `home:write` | Create measurements |

Keep `src/OAuth2/OAuthScope.php`, route middleware, OpenAPI, and tests aligned when extending scopes. Enforce the client allowlist, granted token scopes, and endpoint requirements separately. Refresh can preserve or reduce permission; new scopes are not silently granted to existing clients or tokens.

**Follow-up: implement per-account data isolation separately.** Existing endpoints intentionally return shared data. Stock details include positions and dividends; dashboard and home queries are not generally account-filtered. `api:read` therefore includes shared financial data. Preserve these outputs during OAuth work. The account-isolation task must add ownership filtering and object-level authorization across repositories, aggregates, and serializers, with cross-account tests; scopes alone do not provide that isolation.

### Persistence and token lifecycle

- Use explicit private properties with mapping attributes, constructor assignments, and getters for Doctrine entities. Use [doctrine-migrations](../doctrine-migrations/SKILL.md) when changing persistence mappings or repositories.
- Access tokens expire after 10 minutes and require signature plus database revocation checks. Authorization codes expire after 2 minutes and are single use. Refresh rotates tokens within a fixed 30-day authorization family; refresh must not extend that deadline.
- Preserve database transactions and client/token row locks during issuance, rotation, and client lifecycle changes. Replaying a consumed code or refresh token revokes its family, including tokens issued later. Persist that revocation after the failed issuance transaction has rolled back.
- Keep consumed code/refresh records until the family expires so cleanup cannot remove replay detection prematurely. Scope reductions and client ownership checks must survive refresh and revocation.
- Store client secrets only as hashes. Keep signing/encryption keys outside the web root and application cache, persistent across deployments. Lazy key loading lets existing sensors work before OAuth is initialized.
- Preserve no-store responses and secret redaction. Rate limits use shared database state and direct peer IPs: token/revoke each allow 60 requests/minute; authorization GET allows 30 and POST 10. Account for proxy topology when changing limits.

### Verification

Use [testing-conventions](../testing-conventions/SKILL.md). Relevant suites are `tests/Unit/OAuth2/` and `tests/Integration/OAuth2/`; sensor regression coverage is in `tests/Integration/Home/Device/Record/Api/HomeDeviceRecordApiTest.php`.

Cover the affected grant, scope, identity, expiry, CSRF/PKCE, rotation/replay, revocation, or CLI behavior with isolated test clients and keys. Preserve local HTTP flow coverage and real legacy sensor middleware coverage. Follow the [AGENTS.md validation matrix](../../../AGENTS.md#validation-matrix); integration suites rebuild their test database and must not run concurrently with other integration suites or migrations against it.
