---
name: phpstorm-database
description: Inspect local database structure, selected data, or query plans through PhpStorm MCP. Use doctrine-migrations for schema changes.
---

# PhpStorm Database Inspection

Use the existing PhpStorm data source for the requested database investigation. Default to read-only queries; an inspection request does not authorize changes to data, schema, or connection settings.

## Discover the target

1. Discover `mcp__phpstorm__list_database_connections` if the tools are not initially visible. Use the exposed tool schemas as the authority for parameters; they can differ from online documentation.
2. Pass the absolute project root as `projectPath` on every call. Obtain it from the current workspace rather than another open IDE project.
3. List connections and select the one matching the requested project and environment. This project's local source is currently named `my-portfolio-tracker@localhost` and uses MariaDB. Discover its ID each session; do not hardcode it.
4. Call `list_database_schemas` and preserve the returned `databaseName` and `schemaName` exactly. Here, MariaDB returns `databaseName: ""` and `schemaName: "my-portfolio-tracker"`; do not put the schema name into both arguments.
5. If the target is ambiguous, ask which connection is intended. Otherwise continue with the identified local source. Do not create or edit a connection as a fallback.

Connection metadata may include a JDBC URL. Do not print it or extract credentials. Do not read `.idea` data-source files, credential stores, or local secret configuration to connect; let PhpStorm use its configured connection.

## Inspect structure first

- Use `list_schema_objects` to find application tables, then `get_database_object_description` for columns, types, foreign keys, and indexes. Pass the returned object kind, normally `table`.
- Call `introspect_schema` only if `isIntrospected` is false or metadata appears stale.
- Stay within the selected application schema. The Database window's Server Objects and virtual `sessions` view are not application tables.
- A source with `isDDL: true` describes SQL files, not a live database. Use metadata tools there; do not test the connection, execute SQL, or preview data.
- Use `test_database_connection` when verifying connectivity or diagnosing a failed connection, not before every query.

## Read selected data

All tool names below have the `mcp__phpstorm__` prefix.

- `execute_sql_query`: selected columns or a query plan; pass `connectionId`, `databaseName`, `schemaName`, and `queryText`.
- `preview_table_data`: a small preview after confirming all returned columns are safe to expose; pass `tableName` and a small positive `maxRowCount`.
- `fetch_query_result`: more rows from the same cached result; pass the returned `resultSetId` and zero-based `offset`.

- Prefer explicit columns, a relevant `WHERE`, deterministic `ORDER BY`, and a small SQL `LIMIT`. A result-page limit alone does not bound database work.
- Inspect column names before reading rows. Avoid `SELECT *`, session/authentication data, secrets, and full stored AI prompts/responses; retrieve only what the task needs. Use an aggregate when individual records are unnecessary.
- Use plain `EXPLAIN SELECT ...` for a query plan. Avoid executing stored routines or statements with side effects during an inspection.
- Check `isError` and `errorMessage`; a nonempty `errorMessage` means failure even if result text is present.
- Results are CSV text with a header; observe the returned delimiter. Cached result pagination does not rerun the SQL or prove data is still current.
- The data source's `readOnly` flag describes IDE configuration, not proof of database privileges. Continue to issue only read-only queries even when that flag is false.

After discovering the schema and confirming the columns, a minimal check is:

```sql
SELECT 1 AS connection_ok, DATABASE() AS active_schema;
```

A small application-data check is:

```sql
SELECT ticker, currency FROM stock_asset ORDER BY ticker LIMIT 5;
```

## Connect findings to code

- Compare the live table description with the owning entity's Doctrine mapping and relevant migration files. Do not change mappings solely to silence an unexplained difference.
- Use PhpStorm symbol navigation for the owning entity/repository and `doctrine-migrations` before changing persistence code.
- Report the connection/schema, the bounded query or inspected objects, the finding, and any stale-metadata or connectivity limitation. Do not save database dumps or query results in the repository unless requested.
- If MCP or the connection is unavailable, continue source/migration inspection and clearly distinguish it from a live database check.
