<?php

namespace App\Test\Unit\Cash\Bank\Kb;

use App\Cash\Bank\BankTransactionType;
use App\Cash\Bank\Kb\KbContentParser;
use App\Cash\Bank\Kb\KbTransaction;
use PHPUnit\Framework\TestCase;


/**
 * KbContentParserTest class is used to test the KbContentParser class.
 *
 * This class focuses on the testing of the function `processRawKbTransactions`.
 * `processRawKbTransactions` is expected to return a KbContentParserResult object.
 */
class KbContentParserTest extends TestCase
{
	/**
	 * Test the processRawKbTransactions with valid transactions
	 */
	public function testProcessRawKbTransactionsWithValidContent(): void
	{
		$kbContentParser = new KbContentParser();

		$transaction = new KbTransaction();
		$transaction->setTransactionRawContent("TRANSAKCE PLATEBNÍ KARTOU
Mobilní platba
-93,20");

		$transactions = [$transaction];

		$result = $kbContentParser->processRawKbTransactions($transactions);

		$this->assertIsObject($result);
		$this->assertNotEmpty($result->getProcessedTransactions());
		$this->assertEquals(-93, $result->getProcessedTransactions()[0]->getAmount());
		$this->assertEquals(BankTransactionType::CARD_PAYMENT, $result->getProcessedTransactions()[0]->getBankTransactionType());
	}

	/**
	 * Test the processRawKbTransactions on transaction with invalid content
	 */
	public function testProcessRawKbTransactionsWithInvalidContent(): void
	{
		$kbContentParser = new KbContentParser();

		$transaction = new KbTransaction();
		$transaction->setTransactionRawContent('');
		$transactions = [$transaction];

		$result = $kbContentParser->processRawKbTransactions($transactions);

		$this->assertNotEmpty($result->getUnprocessedTransactions());
		$this->assertEquals('Invalid amount, cant parse amount', $result->getUnprocessedTransactions()[0]->getUnprocessedReason());
	}

	/**
	 * Test the processRawKbTransactions on transaction with zero amount
	 */
	public function testProcessRawKbTransactionsWithZeroAmount(): void
	{
		$kbContentParser = new KbContentParser();

		$transaction = new KbTransaction();
		$transaction->setTransactionRawContent("Sorry, no valid amount match\n0.00");
		$transactions = [$transaction];

		$result = $kbContentParser->processRawKbTransactions($transactions);

		$this->assertNotEmpty($result->getUnprocessedTransactions());
		$this->assertEquals('Invalid amount, cant parse amount', $result->getUnprocessedTransactions()[0]->getUnprocessedReason());
	}
}
