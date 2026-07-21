<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\Codex;

final readonly class StockAiAnalysisCodexBundle
{

	public function __construct(
		public string $filePath,
		public string $downloadName,
	)
	{
	}

}
