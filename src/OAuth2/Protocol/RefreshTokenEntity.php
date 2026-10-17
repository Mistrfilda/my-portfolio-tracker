<?php

declare(strict_types = 1);

namespace App\OAuth2\Protocol;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;

class RefreshTokenEntity implements RefreshTokenEntityInterface
{

	use EntityTrait;
	use RefreshTokenTrait;

}
