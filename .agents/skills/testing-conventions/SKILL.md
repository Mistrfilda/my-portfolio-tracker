---
name: testing-conventions
description: Invoke before writing or modifying any PHPUnit test. Provides the project's test layout, commands, base classes (`PHPUnit\Framework\TestCase`, `App\Test\Integration\IntegrationTestCase`, and `App\Test\Integration\Api\ApiTestCase`), naming, mocking conventions, and rules against real queues or external HTTP calls.
---

## Testing Conventions

### Directory layout

- `tests/Unit/` — unit tests. Fast, no DB, no network, no queues. **Preferred.**
- `tests/Integration/` — integration tests (DB, HTTP, full container). Use only when the behavior cannot be covered by a unit test.

### Commands

- All checks: `composer build-all`
- Unit only: `composer test-unit`
- Integration only: `composer test-integration`
- Direct PHPUnit filtering is allowed for a focused test while iterating.

### Base classes

- Unit tests → extend `PHPUnit\Framework\TestCase`.
- Database/container integration tests → extend `App\Test\Integration\IntegrationTestCase`.
- Slim REST API integration tests → extend `App\Test\Integration\Api\ApiTestCase`, which already extends `IntegrationTestCase`.

### Naming

- File and class name must end with `Test` and the file name must be `<ClassName>Test.php`.
- Namespace mirrors the tested code: `App\Test\Unit\<Module>\...` or `App\Test\Integration\<Module>\...`.
- Methods start with `test` and describe the behavior: `testConvertsGbpPenceToPounds`.

### Example (unit)

```php
<?php

declare(strict_types = 1);

namespace App\Test\Unit\Utils;

use App\Utils\TypeValidator;
use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{

	public function testSomething(): void
	{
		$this->assertSame('test', TypeValidator::validateString('test'));
	}

}
```

### Rules

- **Prefer unit tests.** Move logic into pure services/facades so it can be unit-tested.
- **Never use real RabbitMQ queues in tests** — call the target facade (e.g. `JobRequestProcessor`) directly with a synthetic message, or mock the producer.
- Avoid shared PHPUnit mocks in `setUp()` when most tests do not assert calls on them. Use `createStub()` for default dependencies, and create a local `createMock()` only in tests that define explicit `expects()` assertions. This prevents PHPUnit warnings about mocks with no expectations.
- Never call external HTTP APIs (Twelve Data, CNB, ECB, Discord) from tests — mock the client or the facade.
- Use `App\Utils\TypeValidator` for scalar validation, `Nette\Utils\Json` for JSON.
- Tabs for indentation, PSR-12, strict_types, English messages.
- Mark tests that talk to the DB with their natural place in `tests/Integration/`; for API, `ApiTestCase` handles bootstrapping.
- For a bug fix, add a regression test that fails for the observed behavior before implementing the fix when practical.
- For a new feature, test the requested behavior alongside the implementation; do not leave intentionally failing tests in the final change.
