<?php

declare(strict_types = 1);

namespace App\Doctrine;

use Doctrine\DBAL\LockMode;

enum LockModeEnum: int
{

	case NONE = 0;
	case OPTIMISTIC = 1;
	case PESSIMISTIC_READ = 2;
	case PESSIMISTIC_WRITE = 4;

	public function toDoctrineLockMode(): LockMode
	{
		return match ($this) {
			self::NONE => LockMode::NONE,
			self::OPTIMISTIC => LockMode::OPTIMISTIC,
			self::PESSIMISTIC_READ => LockMode::PESSIMISTIC_READ,
			self::PESSIMISTIC_WRITE => LockMode::PESSIMISTIC_WRITE,
		};
	}

}
