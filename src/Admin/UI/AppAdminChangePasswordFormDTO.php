<?php

declare(strict_types = 1);

namespace App\Admin\UI;

class AppAdminChangePasswordFormDTO
{

	public string $password;

	public string $oldPassword;

	public string $passwordRepeat;

}
