<?php

declare(strict_types = 1);

namespace App\Test\Unit\Cash\Bank\Kb;

use App\Cash\Bank\BankTransactionType;
use App\Cash\Bank\Kb\KbContentParser;
use App\Cash\Bank\Kb\KbPdfCashParser;
use App\Cash\Bank\Kb\KbPdfTransactionParsingErrorException;
use Nette\Utils\FileSystem;
use PHPUnit\Framework\TestCase;

class KbPdfCashParserTest extends TestCase
{

	public function testParsesTransactionsAndIgnoresSummaryPage(): void
	{
		$result = (new KbPdfCashParser(new KbContentParser()))->parse(
			$this->readPdfFixture('kb-statement.pdf.base64'),
		);

		self::assertCount(2, $result->getProcessedTransactions());
		self::assertSame([], $result->getUnprocessedTransactions());
		self::assertSame([], $result->getIncomingTransactions());

		$firstTransaction = $result->getProcessedTransactions()[0];
		self::assertSame(-100.0, $firstTransaction->getAmount());
		self::assertSame('2026-01-01', $firstTransaction->getTransactionDate()?->format('Y-m-d'));
		self::assertSame(BankTransactionType::CARD_PAYMENT, $firstTransaction->getBankTransactionType());
		self::assertStringContainsString(
			'2. 1. 2026 REFERENCE, NOT A NEW TRANSACTION',
			(string) $firstTransaction->getTransactionRawContent(),
		);

		$secondTransaction = $result->getProcessedTransactions()[1];
		self::assertSame(-200.0, $secondTransaction->getAmount());
		self::assertSame('2026-01-03', $secondTransaction->getTransactionDate()?->format('Y-m-d'));
		self::assertSame(BankTransactionType::CARD_PAYMENT, $secondTransaction->getBankTransactionType());
	}

	public function testRejectsPdfWithoutStatementHeader(): void
	{
		$parser = new KbPdfCashParser(new KbContentParser());

		$this->expectException(KbPdfTransactionParsingErrorException::class);
		$this->expectExceptionMessage('Invalid file');

		$parser->parse($this->readPdfFixture('invalid-kb-statement.pdf.base64'));
	}

	private function readPdfFixture(string $file): string
	{
		$encoded = preg_replace(
			'/\s+/',
			'',
			FileSystem::read(__DIR__ . '/fixtures/' . $file),
		);
		self::assertIsString($encoded);
		$contents = base64_decode($encoded, true);
		self::assertIsString($contents);

		return $contents;
	}

}
