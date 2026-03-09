<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

enum StockAiAnalysisPortfolioPromptTypeEnum: string
{

	case PORTFOLIO_EVALUATION = 'portfolio_evaluation';
	case DAILY_BRIEF = 'daily_brief';

	public function getLabel(): string
	{
		return match ($this) {
			self::PORTFOLIO_EVALUATION => 'Komplexní zhodnocení portfolia',
			self::DAILY_BRIEF => 'Denní briefing (1 den)',
		};
	}

}
