---
name: doctrine-migrations
description: Invoke before creating or modifying Doctrine ORM entities, repositories, or database schema. Provides the required workflow for schema changes and migrations (clear cache, dump SQL, diff, migrate) and repository access rules. Use when adding/changing an `#[ORM\Entity]` class, adding fields/relations, writing a new repository, generating a migration with `migrations:diff`, or resolving schema drift. Also trigger when the user mentions Doctrine, entity, repository, migration, schema, or `bin/console migrations:*` / `orm:schema-tool:*`.
---

## Doctrine ORM & Migrations

Workflow that must be followed whenever a Doctrine entity is added or changed.

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
4. **Apply the migration**:
   ```
   bin/console migrations:migrate
   ```

Never hand-edit the schema or skip the migration step — the production DB is synchronized exclusively through migration files in `migrations/`.

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
- Integration tests that touch the DB extend `App\Test\Integration\Api\ApiTestCase`.
- Never use real RabbitMQ queues in tests; for DB, use the test database set up by the test base class.
