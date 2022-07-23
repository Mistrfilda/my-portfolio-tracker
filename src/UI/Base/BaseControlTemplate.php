<?php

declare(strict_types = 1);

namespace App\UI\Base;

use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Security\User;
use stdClass;

abstract class BaseControlTemplate extends Template
{

	public User $user;

	public string $baseUrl;

	public string $basePath;

	/** @var array<stdClass> */
	public array $flashes;

	public Presenter $presenter;

	public BaseControl $control;

}
