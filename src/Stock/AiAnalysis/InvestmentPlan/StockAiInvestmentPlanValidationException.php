<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan;

use RuntimeException;

class StockAiInvestmentPlanValidationException extends RuntimeException
{

	/** @param array<int, string> $errors */
	public function __construct(private readonly array $errors)
	{
		parent::__construct("Investment plan response is invalid:\n- " . implode("\n- ", $errors));
	}

	/** @return array<int, string> */
	public function getErrors(): array
	{
		return $this->errors;
	}

}
