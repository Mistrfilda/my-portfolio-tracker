<?php

declare(strict_types = 1);

namespace App\Test\Unit\Cash\Bank\Kb;

use App\Cash\Bank\Kb\KbCsvCashParser;
use App\Cash\Bank\Kb\KbPdfTransactionParsingErrorException;
use App\Test\UpdatedTestCase;

class KbCsvCashParserTest extends UpdatedTestCase
{

	private KbCsvCashParser $parser;

	protected function setUp(): void
	{
		$this->parser = new KbCsvCashParser();
	}

	public function testParseValidCsvWithExpenses(): void
	{
		$csv = "Datum zauctovani;Datum provedeni;Castka;Typ transakce\n";
		$csv .= "2024-01-15;2024-01-15;-1500;Mobilní platba\n";

		$result = $this->parser->parse($csv);

		$this->assertCount(1, $result->getProcessedTransactions());
		$this->assertCount(0, $result->getUnprocessedTransactions());
		$this->assertCount(0, $result->getIncomingTransactions());

		$transaction = $result->getProcessedTransactions()[0];
		$this->assertSame(-1500.0, $transaction->getAmount());
	}

	public function testParseValidCsvWithIncome(): void
	{
		$csv = "Datum zauctovani;Datum provedeni;Castka;Typ transakce\n";
		$csv .= "2024-01-15;2024-01-15;5000;Příchozí úhrada\n";

		$result = $this->parser->parse($csv);

		$this->assertCount(0, $result->getProcessedTransactions());
		$this->assertCount(0, $result->getUnprocessedTransactions());
		$this->assertCount(1, $result->getIncomingTransactions());

		$transaction = $result->getIncomingTransactions()[0];
		$this->assertSame(5000.0, $transaction->getAmount());
	}

	public function testParseEmptyLinesAreSkipped(): void
	{
		$csv = "Datum zauctovani;Datum provedeni;Castka;Typ transakce\n";
		$csv .= "\n";
		$csv .= "2024-01-15;2024-01-15;-1500;Nákup na internetu\n";
		$csv .= "   \n";

		$result = $this->parser->parse($csv);

		$this->assertCount(1, $result->getProcessedTransactions());
	}

	public function testParseThrowsExceptionForUnknownType(): void
	{
		$csv = "Datum zauctovani;Datum provedeni;Castka;Typ transakce\n";
		$csv .= "2024-01-15;2024-01-15;-1500;Neznámý typ transakce XYZ\n";

		self::assertException(
			fn () => $this->parser->parse($csv),
			KbPdfTransactionParsingErrorException::class,
		);
	}

	public function testParseMixedTransactions(): void
	{
		$csv = "Datum zauctovani;Datum provedeni;Castka;Typ transakce\n";
		$csv .= "2024-01-15;2024-01-15;-1500;Výběr hotovosti z bankomatu\n";
		$csv .= "2024-01-16;2024-01-16;3000;Příchozí úhrada\n";
		$csv .= "2024-01-17;2024-01-17;-500;Mobilní platba\n";

		$result = $this->parser->parse($csv);

		$this->assertCount(2, $result->getProcessedTransactions());
		$this->assertCount(1, $result->getIncomingTransactions());
	}

}
