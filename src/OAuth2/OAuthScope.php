<?php

declare(strict_types = 1);

namespace App\OAuth2;

enum OAuthScope: string
{

	case API_READ = 'api:read';
	case HOME_READ = 'home:read';
	case HOME_WRITE = 'home:write';

	public function getLabel(): string
	{
		return match ($this) {
			self::API_READ => 'Čtení společného portfolia a akciových dat',
			self::HOME_READ => 'Čtení domácích zařízení a měření',
			self::HOME_WRITE => 'Zápis měření domácích zařízení',
		};
	}

}
