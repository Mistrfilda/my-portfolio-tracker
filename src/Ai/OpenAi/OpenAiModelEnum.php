<?php

declare(strict_types = 1);

namespace App\Ai\OpenAi;

use App\Ai\AiModel;

enum OpenAiModelEnum: string implements AiModel
{

	case LUNA = 'gpt-6-luna';

	case SOL = 'gpt-6.1-sol';

	case ASTRA = 'gpt-6-astra';

	public const self DEFAULT = self::LUNA;

}
