<?php

declare(strict_types = 1);

namespace App\Ai\Gemini;

use App\Ai\AiModel;

enum GeminiModelEnum: string implements AiModel
{

	case FLASH = 'gemini-3.8-flash';

	public const self DEFAULT = self::FLASH;

}
