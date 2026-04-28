<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\ActionChecklist;

enum StockAiAnalysisActionChecklistPriorityEnum: string
{

	case LOW = 'low';
	case MEDIUM = 'medium';
	case HIGH = 'high';

	public function getLabel(): string
	{
		return match ($this) {
			self::LOW => 'Low',
			self::MEDIUM => 'Medium',
			self::HIGH => 'High',
		};
	}

	public function getBadgeClasses(): string
	{
		return match ($this) {
			self::LOW => 'bg-green-100 text-green-800 ring-green-600/20',
			self::MEDIUM => 'bg-amber-100 text-amber-800 ring-amber-600/20',
			self::HIGH => 'bg-red-100 text-red-800 ring-red-600/20',
		};
	}

}
