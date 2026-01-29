<?php

declare(strict_types = 1);

namespace App\Home\UI;

use App\Home\HomeFacade;
use App\Home\HomeRepository;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Ramsey\Uuid\Uuid;

class HomeFormFactory
{

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private HomeFacade $homeFacade,
		private HomeRepository $homeRepository,
	)
	{
	}

	public function create(string|null $id, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addText('name', 'Název')
			->setRequired();

		$form->addSubmit('submit', 'Uložit');

		$home = null;
		if ($id !== null) {
			$home = $this->homeRepository->getById(Uuid::fromString($id));
			$form->setDefaults([
				'name' => $home->getName(),
			]);
		}

		$form->onSuccess[] = function (AdminForm $form) use ($home, $onSuccess): void {
			$values = $form->getValues();
			assert(is_string($values->name));

			if ($home !== null) {
				$this->homeFacade->update(
					$home->getId(),
					$values->name,
				);
			} else {
				$this->homeFacade->create($values->name);
			}

			$onSuccess();
		};

		return $form;
	}

}
