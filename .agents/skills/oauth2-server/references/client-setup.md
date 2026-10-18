# OAuth server setup and client integration

Use this reference for server provisioning, client registration, and application integration. Paths in HTTP examples are relative to the application's base URL, including any deployment subdirectory. The HTTP contract is maintained in [OpenAPI](../../../../doc/openapi.yaml).

## Server provisioning

When setting up OAuth in the requested environment:

1. Install Composer dependencies and apply the OAuth migration through the project's normal deployment process.
2. Run `bin/console oauth:keys:generate` once as the PHP worker's OS user, with permission to create `resources/oauth`. It creates a signing key pair and a separate encryption key and refuses to overwrite existing keys. Clients can be registered before this step; keys are needed when issuing tokens or rendering authorization.
3. Keep that directory persistent across deployments and shared by application instances; back it up securely. Key files have mode `0600`. The public configuration parameter is `api.oauth.keysDirectory` in `config/config.neon`.
4. Configure daily execution of `bin/console oauth:cleanup`. It retains consumed code/refresh records until their authorization family expires.

In the project's Docker image, the application root is `/var`, the default key directory is `/var/resources/oauth`, and PHP workers run as `www-data`. Infrastructure is maintained in the sibling `kubedev` repository: `volumes/my-portfolio-tracker-oauth-pvc.yaml` defines `my-pt-oauth-pvc`, and `deployment/my-portfolio-tracker-php-deployment.yaml` mounts it at `/var/resources/oauth`. Its init container sets ownership and directory mode on every pod start without generating or replacing keys. Deploy an application image containing this path together with those manifests. After the first rollout, provision an empty volume with:

```sh
kubectl -n default exec deployment/php-deployment -c php-container -- \
	su -s /bin/sh www-data -c 'cd /var && bin/console oauth:keys:generate'
```

Keep key files at `0600` and their directory at `0700`. If keys were generated as root, repair ownership with `chown -R www-data:www-data /var/resources/oauth`. PHP workers also need access through every parent directory; generation creates missing parents with mode `0700` too. An inaccessible parent directory causes HTTP 503 `OAuth is not configured.`; regenerating keys or clients does not fix it.

For existing installations using the previous `var/oauth` default, move the existing key files securely to `resources/oauth` while preserving their contents and permissions, or retain the old path through `api.oauth.keysDirectory`. In Kubernetes, restore existing keys onto the PVC before serving OAuth traffic. Never replace surviving keys during this migration: replacement invalidates issued tokens and pending grants. The new directory is excluded from Git and the Docker build context. Keep a separate secure backup; the MicroK8s hostpath volume is node-local, and deleting its PVC can delete its data.

Both HTTP and HTTPS are accepted. Existing sensors do not require OAuth keys to be initialized. Keep credentials, authorization headers, codes, and token responses out of application and proxy logs; preserve the corresponding Tracy redaction settings.

## Register an application

For a trusted server using client credentials:

```sh
bin/console oauth:client:create "Reporting service" --scope=api:read
```

The command displays `client_id` and `client_secret` once; only the secret hash is saved. `--json` produces machine-readable output.

For a mobile application:

```sh
bin/console oauth:client:create "Portfolio mobile" --public \
	--redirect-uri=com.example.portfolio:/oauth/callback --scope=api:read
```

For a desktop application:

```sh
bin/console oauth:client:create "Portfolio desktop" --public \
	--redirect-uri=http://127.0.0.1:8000/oauth/callback --scope=api:read
```

Public clients receive an ID without a secret. Register platforms/environments separately. Repeat `--redirect-uri` and `--scope` as needed; omitted scopes default to `api:read`. Confidential clients with registered callbacks can also use authorization code and refresh grants.

Callbacks may use HTTPS, a reverse-domain native scheme, or a loopback IP HTTP URI. Wildcards, fragments, user-info, and remote HTTP callbacks are rejected. This callback policy is separate from the server's support for HTTP on the local network. League allows the native loopback port to vary; the other registered callback components must still match.

Client lifecycle commands:

```sh
bin/console oauth:client:rotate-secret CLIENT_ID
bin/console oauth:client:disable CLIENT_ID
```

Rotation replaces the secret and revokes existing tokens. Disabling prevents issuance and invalidates access. Public clients have no secret to rotate.

## Token requests

Send token and revocation POST bodies as `application/x-www-form-urlencoded`. Confidential clients authenticate with HTTP Basic (`client_id:client_secret`) or body fields, using one method per request. Public clients provide `client_id` without a secret.

| Operation | Endpoint | Grant-specific body fields |
| --- | --- | --- |
| Client credentials | `POST /api/oauth/token` | `grant_type=client_credentials`, optional space-separated `scope` |
| Code exchange | `POST /api/oauth/token` | `grant_type=authorization_code`, `code`, original `redirect_uri`, original `code_verifier` |
| Refresh | `POST /api/oauth/token` | `grant_type=refresh_token`, `refresh_token`, optional reduced `scope` |
| Revoke | `POST /api/oauth/revoke` | `token`, optional `token_type_hint` |

Token responses contain `token_type=Bearer`, `expires_in`, and `access_token`; user grants also return `refresh_token`. Send access tokens as `Authorization: Bearer TOKEN`. Client-credentials integrations request a new access token when needed and do not represent a logged-in user. The default requested scope is `api:read`; home-only integrations must explicitly request their allowed home scope.

## Native user authorization

Open `GET /api/oauth/authorize` in the system browser with:

- `response_type=code`
- `client_id` and registered `redirect_uri`
- space-separated `scope`
- unpredictable `state`, retained and checked by the client on return
- `code_challenge=BASE64URL(SHA256(code_verifier))`
- `code_challenge_method=S256`

The server owns the login/consent form. It verifies the existing account password without a Nette login session; accounts requiring a password change must complete it in the web application first. Do not collect the user's password in a native client or send it to the token endpoint.

Approval returns an HTTP 303 callback with `code` and the original `state`; denial returns `error=access_denied`. Exchange the code with the original verifier and redirect URI. `GET /api/v1/me` returns the authorized user's ID, name, and username; client-only tokens receive 403. This is OAuth authorization, not an OpenID Connect ID-token flow.

Store native refresh tokens in the operating system's secure credential storage. Serialize refresh requests and replace the saved refresh token atomically after success. Replay revokes the whole authorization family; if refreshed credentials are lost, return to browser authorization.

Revocation checks client ownership. A recognized token revokes its authorization family; a client-credentials access token is revoked individually. Unknown/already-invalid tokens and tokens owned by another client return 200 without exposing their existence or revoking another client's access.

Bearer authentication failures return 401, insufficient scopes return 403, and rate limits return 429. All OAuth responses are non-cacheable.

### Resource ownership

- User tokens access only their user's portfolio, positions, dividend records, AI prices and home devices. Do not send a user ID to select the account.
- Client-credentials tokens can call `/api/v1/ping` and `/api/v1/stocks`. The stock catalog is shared and `aiAnalysisPrice` is null without a user.
- `/api/v1/me`, `/api/v1/dashboard/values`, `/api/v1/stocks/{id}` and both home endpoints require user authorization and return 403 for client-only tokens, even with the matching scope. Services needing personal data must use an authorized user grant; they cannot impersonate the original portfolio owner.
- Legacy `X-Api-Key` authentication remains available only for the two existing home endpoints, restricted to devices in the configured user's homes. Writing to an unknown or another user's `internalId` returns 404.
- Existing response shapes are preserved. This ownership change intentionally removes the former client-credentials access to shared personal data; register a native public client and use PKCE for desktop/mobile portfolio access.
