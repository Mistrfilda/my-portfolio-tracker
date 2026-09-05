---
name: doctrine-migrations
description: Invoke before changing Doctrine entities, repositories, mappings, or migrations. Covers repository DI and the schema-change workflow. For read-only inspection of the live database through PhpStorm MCP, use phpstorm-database.
---

## Doctrine ORM & Migrations

Apply entity and repository conventions when editing persistence code. Run the schema workflow only when mapped fields, relations, indexes, or other schema metadata change; method-only and query-only changes do not require a migration.

### Schema change workflow

1. **Clear cache** so Doctrine picks up the updated metadata:
   ```
   composer clear
   ```
2. **Inspect generated SQL** (do not let migrations:diff generate blindly):
   ```
   bin/console orm:schema-tool:update --dump-sql
   ```
   Review the SQL. If it does unexpected things (drops columns, renames that should be data migrations, wrong types), fix the entity first.
3. **Generate a migration** only once the SQL looks correct:
   ```
   bin/console migrations:diff
   ```
4. **Review the generated migration**, including its rollback SQL and any unrelated schema drift. Apply it to the intended local development database when that is part of the authorized task:
   ```
   bin/console migrations:migrate
   ```

Represent schema changes in migration files under `migrations/`; do not substitute direct DDL or `orm:schema-tool:update --force`. A schema change request is not authorization to migrate production or apply unrelated pending migrations. Read-only database inspection uses `phpstorm-database` and does not trigger this workflow.

### Repository access rules

- Always inject repositories via **constructor DI** from the container.
- **Never** call `EntityManager::getRepository()` in application code to obtain a repository.
- Custom repositories live next to the entity (or under `src/Doctrine/`) and are registered as services; rely on autowiring.

### Entity conventions

- Use **constructor property promotion** and typed properties.
- Tabs for indentation, PSR-12 otherwise.
- Exception messages and all comments in English.
- Use `App\Utils\TypeValidator` for scalar type validation where applicable.
- For JSON columns / serialization, use `Nette\Utils\Json`.

### Testing

- Prefer unit tests over integration tests.
- Integration tests that touch the DB extend `App\Test\Integration\IntegrationTestCase`; only Slim API tests extend `App\Test\Integration\Api\ApiTestCase`.
- Never use real RabbitMQ queues in tests; for DB, use the test database set up by the test base class.
