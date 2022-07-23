<?php

declare(strict_types = 1);

namespace App\Login\UI\Form;

use Nette\SmartObject;

class LoginFormDTO
{

	use SmartObject;

	public string $username;

	public string $password;

}
