---
name: testing-conventions
description: Invoke before writing or modifying any PHPUnit test. Provides the project's testing conventions – directory split (`tests/Unit`, `tests/Integration`), base classes (`PHPUnit\Framework\TestCase`, `App\Test\Integration\Api\ApiTestCase`), naming (`*Test.php`), and the rule to prefer unit tests and never use real RabbitMQ.
---

## Testing Conventions

### Directory layout

- `tests/Unit/` — unit tests. Fast, no DB, no network, no queues. **Preferred.**
- `tests/Integration/` — integration tests (DB, HTTP, full container). Use only when the behavior cannot be covered by a unit test.

### Commands

- All tests: `vendor/bin/phpunit`
- Unit only: `vendor/bin/phpunit tests/Unit`
- Integration only: `vendor/bin/phpunit tests/Integration`

### Base classes

- Unit tests → extend `PHPUnit\Framework\TestCase`.
- Integration / REST API tests → extend `App\Test\Integration\Api\ApiTestCase`.

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
- Never call external HTTP APIs (Twelve Data, CNB, ECB, Discord) from tests — mock the client or the facade.
- Use `App\Utils\TypeValidator` for scalar validation, `Nette\Utils\Json` for JSON.
- Tabs for indentation, PSR-12, strict_types, English messages.
- Mark tests that talk to the DB with their natural place in `tests/Integration/`; for API, `ApiTestCase` handles bootstrapping.
- Add a failing reproducer test before fixing a bug (expected by `[CODE]` mode workflow).
