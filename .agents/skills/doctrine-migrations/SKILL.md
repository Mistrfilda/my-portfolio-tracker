---
name: doctrine-migrations
description: Maintain Doctrine entities, repositories, mappings, and migrations. Use for persistence changes; read-only live database inspection belongs to phpstorm-database.
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

### Testing

- Use integration tests when verifying Doctrine query behavior or persistence mappings; keep entity logic covered by unit tests where possible.
- Follow [testing-conventions](../testing-conventions/SKILL.md) for test database setup and base classes, and the applicable [AGENTS.md validation](../../../AGENTS.md#validation-matrix).
