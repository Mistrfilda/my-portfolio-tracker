<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

enum StockAiAnalysisProcessingSourceEnum: string
{

	case MANUAL = 'manual';
	case GEMINI = 'gemini';
	case CODEX = 'codex';

	public function getLabel(): string
	{
		return match ($this) {
			self::MANUAL => 'Manual',
			self::GEMINI => 'Gemini',
			self::CODEX => 'Codex',
		};
	}

}
