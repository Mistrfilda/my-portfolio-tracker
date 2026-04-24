<?php

declare(strict_types = 1);

namespace App\Test\Unit\Dashboard\Support;

use Nette\Application\UI\Presenter;

class DashboardLinkGeneratorPresenter extends Presenter
{

	public function actionDetail(string $id): void
	{
		// Only needed so LinkGenerator can validate the action signature in unit tests.
	}

}
