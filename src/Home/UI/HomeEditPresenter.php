<?php

declare(strict_types = 1);

namespace App\Home\UI;

use App\Home\HomeRepository;
use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;

class HomeEditPresenter extends BaseSysadminPresenter
{

	public function __construct(
		private readonly HomeRepository $homeRepository,
		private readonly HomeFormFactory $homeFormFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(string|null $id): void
	{
		if ($id !== null) {
			$home = $this->homeRepository->getById($this->processParameterRequiredUuid());
			$this->template->heading = sprintf('Úprava domova %s', $home->getName());
		} else {
			$this->template->heading = 'Přidání nového domova';
		}
	}

	protected function createComponentHomeForm(): AdminForm
	{
		$id = $this->getParameter('id');
		assert($id === null || is_string($id));

		$onSuccess = function () use ($id): void {
			if ($id === null) {
				$this->flashMessage('Domov úspěšně vytvořen', FlashMessageType::SUCCESS);
			} else {
				$this->flashMessage('Domov úspěšně upraven', FlashMessageType::SUCCESS);
			}

			$this->redirect('Home:default');
		};

		return $this->homeFormFactory->create($id, $onSuccess);
	}

}
