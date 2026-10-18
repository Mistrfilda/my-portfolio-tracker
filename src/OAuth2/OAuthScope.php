<?php

declare(strict_types = 1);

namespace App\OAuth2;

enum OAuthScope: string
{

	case API_READ = 'api:read';
	case PORTFOLIO_READ = 'portfolio:read';
	case MARKET_READ = 'market:read';
	case HOME_READ = 'home:read';
	case HOME_WRITE = 'home:write';

	public function getLabel(): string
	{
		return match ($this) {
			self::API_READ => 'Čtení vlastního portfolia a společných akciových dat',
			self::PORTFOLIO_READ => 'Čtení vlastního portfolia, dividend a identity uživatele',
			self::MARKET_READ => 'Čtení měnových kurzů a dividend společností',
			self::HOME_READ => 'Čtení vlastních domácích zařízení a měření',
			self::HOME_WRITE => 'Zápis měření vlastních domácích zařízení',
		};
	}

}
