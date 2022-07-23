<?php

declare(strict_types = 1);

namespace App\UI\Base;

use App\Admin\CurrentAppAdminGetter;
use App\UI\Menu\MenuBuilder;

/**
 * Control is renderable Presenter component.
 *
 * @property-read BaseAdminPresenterTemplate $template
 */
abstract class BaseAdminPresenter extends BasePresenter
{

	protected CurrentAppAdminGetter $currentAppAdminGetter;

	public function injectCurrentAppAdminGetter(CurrentAppAdminGetter $currentAppAdminGetter): void
	{
		$this->currentAppAdminGetter = $currentAppAdminGetter;
	}

	public function startup(): void
	{
		parent::startup();
		if ($this->currentAppAdminGetter->isLoggedIn() === false) {
			$this->redirect('Login:default', ['backlink' => $this->storeRequest()]);
		}

		if (
			$this->presenter->name !== 'Admin:AppAdminChangePassword'
			&& $this->currentAppAdminGetter->getAppAdmin()->isNewPasswordForced()
		) {
			$this->redirect('AppAdminChangePassword:default');
		}

		$this->template->pageTitle = $this->basePresenterParameters->getPageTitle();
		$this->template->currentAppAdmin = $this->currentAppAdminGetter->getAppAdmin();
		$this->template->menuItems = (new MenuBuilder())->buildMenu();
		$this->template->includeBody = true;
	}

	public function handleLogout(): void
	{
		$this->currentAppAdminGetter->logout();
		$this->redirect('this');
	}

	/**
	 * @return array<string>
	 */
	public function formatLayoutTemplateFiles(): array
	{
		return array_merge([__DIR__ . '/templates/@layout.latte'], parent::formatLayoutTemplateFiles());
	}

}
