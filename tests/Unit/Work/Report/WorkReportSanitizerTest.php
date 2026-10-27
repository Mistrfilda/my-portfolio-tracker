<?php

declare(strict_types = 1);

namespace App\Test\Unit\Work\Report;

use App\Work\Report\WorkReportSanitizer;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WorkReportSanitizerTest extends TestCase
{

	#[DataProvider('credentialAssignments')]
	public function testRedactsCredentialAssignmentsInNestedSnapshotAndRemainsIdempotent(
		string $source,
		string $expected,
	): void
	{
		$sanitizer = new WorkReportSanitizer('synthetic-harvest', null);
		$snapshot = ['notes' => $source, 'items' => [['description' => $source, 'notes' => [['body' => $source]]]], 'count' => 2];
		$clean = $sanitizer->sanitizeData($snapshot);
		self::assertSame($expected, $clean['notes']);
		self::assertSame($expected, $clean['items'][0]['description']);
		self::assertSame($expected, $clean['items'][0]['notes'][0]['body']);
		self::assertStringNotContainsString('synthetic-only-secret', Json::encode($clean));
		self::assertSame($clean, $sanitizer->sanitizeData($clean));
		self::assertSame(2, $clean['count']);
		self::assertSame($source, $snapshot['notes']);
	}

	/** @return iterable<string, array{string, string}> */
	public static function credentialAssignments(): iterable
	{
		yield 'Czech colon' => ['heslo: synthetic-only-secret', 'heslo: [REDACTED]'];
		yield 'Czech equals' => ['heslo = synthetic-only-secret', 'heslo = [REDACTED]'];
		yield 'Markdown colon inside label' => ['**password:** synthetic-only-secret', '**password:** [REDACTED]'];
		yield 'PHP array value' => ["'password' => 'synthetic-only-secret'", "'password' => [REDACTED]"];
		yield 'PHP without spaces' => ["'password'=>'synthetic-only-secret'", "'password'=>[REDACTED]"];
		yield 'Markdown colon outside label' => ['**password**: "synthetic-only-secret with spaces"', '**password**: [REDACTED]'];
		yield 'Czech Markdown label' => ['**heslo:** "synthetic-only-secret with spaces"', '**heslo:** [REDACTED]'];
		yield 'underscored label' => ['__password:__ synthetic-only-secret', '__password:__ [REDACTED]'];
		yield 'inline code label and value' => ['`password` = `synthetic-only-secret with spaces`', '`password` = [REDACTED]'];
		yield 'JSON key' => ['{"password":"synthetic-only-secret"}', '{"password":[REDACTED]}'];
		yield 'case insensitive' => ['HESLO: synthetic-only-secret', 'HESLO: [REDACTED]'];
		yield 'prefixed variable' => ['APP_PASSWORD => "synthetic-only-secret"', 'APP_PASSWORD => [REDACTED]'];
		yield 'quoted API key' => ["'api_key' => 'synthetic-only-secret'", "'api_key' => [REDACTED]"];
		yield 'escaped quote in value' => ['password="synthetic-only-secret\\" continued".', 'password=[REDACTED].'];
		yield 'multiline' => ["heslo:\nsynthetic-only-secret\nKontrola dokončena.", "heslo:\n[REDACTED]\nKontrola dokončena."];
		yield 'formatted value' => ['password: **synthetic-only-secret with spaces**', 'password: [REDACTED]'];
		yield 'multiple assignments' => [
			"heslo: synthetic-only-secret, 'password' => 'synthetic-only-secret with spaces'; Kontrola dokončena.",
			"heslo: [REDACTED], 'password' => [REDACTED]; Kontrola dokončena.",
		];
	}

	public function testRemovesPasswordShareLinksAndCredentialsWrittenInSentences(): void
	{
		$sanitizer = new WorkReportSanitizer('synthetic-harvest', null);
		$source = "Opraven přístup. Heslo je \"synthetic passphrase\". Token is synthetic-token.\n"
			. "Testovací token je Abc123%2BFakeSecret%3D.\n"
			. 'Přihlašovací údaje: https://share.1password.com/s/synthetic-access#synthetic-fragment';
		$clean = $sanitizer->sanitize($source);
		foreach (['synthetic passphrase', 'synthetic-token', 'Abc123%2BFakeSecret%3D', 'synthetic-access', 'synthetic-fragment'] as $secret) {
			self::assertStringNotContainsString($secret, $clean);
		}

		self::assertStringContainsString('Opraven přístup.', $clean);
		self::assertStringContainsString('[REDACTED PASSWORD SHARE]', $clean);
		self::assertSame($clean, $sanitizer->sanitize($clean));
	}

	public function testKeepsEncodedTestIdentifiersWithoutCredentialContext(): void
	{
		$sanitizer = new WorkReportSanitizer('synthetic', null);
		foreach ([
			'CIDC z testovacího prostředí je Abc123%2BFakeIdentifier%3D.',
			'CIDC: Abc123%2BFakeIdentifier%3D',
			"'cidc' => 'Abc123%2BFakeIdentifier%3D'",
			'Kontrola přihlášení a password reset: dokončeno.',
		] as $text) {
			self::assertSame($text, $sanitizer->sanitize($text));
		}
	}

	public function testRemovesCredentialsFromNestedReportTextAndKeepsUsefulNotes(): void
	{
		$sanitizer = new WorkReportSanitizer('configured-harvest-token', 'configured-gitlab-token');
		$clean = $sanitizer->sanitizeData([
			'notes' => 'Fixed login. password="a complicated password" and api_key=private-key',
			'items' => [[
				'title' => 'configured-harvest-token / configured-gitlab-token',
				'body' => 'Bearer private-bearer-token; https://person:private-password@example.com?token=private-query',
				'count' => 2,
			]],
			'key' => "-----BEGIN RSA PRIVATE KEY-----\nprivate-key-body\n-----END RSA PRIVATE KEY-----",
			'gitlab' => 'glpat-abcdefghijklmnop123456',
			'environment' => 'DB_PASSWORD=database-password APP_API_KEY=service-key AWS_SECRET_ACCESS_KEY=aws-secret',
			'camelCase' => 'dbPassword="camel-case-password" privateKey=private-key-value',
			'databaseUrl' => 'postgresql://developer:connection-password@database.example.com/database',
		]);
		$serialized = Json::encode($clean);
		self::assertStringContainsString('Fixed login.', $serialized);
		foreach ([
			'a complicated password', 'private-key', 'configured-harvest-token', 'configured-gitlab-token',
			'private-bearer-token', 'private-password', 'private-query', 'abcdefghijklmnop123456',
			'database-password', 'service-key', 'aws-secret', 'camel-case-password', 'private-key-value', 'connection-password',
		] as $secret) {
			self::assertStringNotContainsString($secret, $serialized);
		}

		self::assertSame(2, $clean['items'][0]['count']);
	}

}
