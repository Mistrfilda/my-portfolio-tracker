<?php

declare(strict_types = 1);

namespace App\UI\Base;

use App\Admin\AppAdmin;
use App\UI\Menu\MenuItem;
use Nette\Security\User;

abstract class BaseAdminPresenterTemplate extends BasePresenterTemplate
{

	public User $user;

	public AppAdmin $currentAppAdmin;

	public string|null $heading;

	/** @var array<MenuItem> */
	public array $menuItems;

	public BaseAdminPresenter $presenter;

}
