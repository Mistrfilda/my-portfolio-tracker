<?php

declare(strict_types = 1);

namespace App\Home\Device\UI;

use App\Home\Device\HomeDeviceFacade;
use App\Home\Device\HomeDeviceRepository;
use App\Home\Device\HomeDeviceType;
use App\Home\Home;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Ramsey\Uuid\Uuid;

class HomeDeviceFormFactory
{

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private HomeDeviceFacade $homeDeviceFacade,
		private HomeDeviceRepository $homeDeviceRepository,
	)
	{
	}

	public function create(Home $home, string|null $id, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addText('name', 'Název')
			->setRequired();

		$form->addText('internalId', 'Interní ID')
			->setRequired();

		$typeOptions = [];
		foreach (HomeDeviceType::cases() as $type) {
			$typeOptions[$type->value] = $type->format();
		}

		$form->addSelect('type', 'Typ', $typeOptions)
			->setRequired();

		$form->addSubmit('submit', 'Uložit');

		$homeDevice = null;
		if ($id !== null) {
			$homeDevice = $this->homeDeviceRepository->getById(Uuid::fromString($id));
			$form->setDefaults([
				'name' => $homeDevice->getName(),
				'internalId' => $homeDevice->getInternalId(),
				'type' => $homeDevice->getType()->value,
			]);
		}

		$form->onSuccess[] = function (AdminForm $form) use ($home, $homeDevice, $onSuccess): void {
			$values = $form->getValues();
			assert(is_string($values->name));
			assert(is_string($values->internalId));
			assert(is_string($values->type));

			if ($homeDevice !== null) {
				$this->homeDeviceFacade->update(
					$homeDevice->getId(),
					$values->internalId,
					$values->name,
					HomeDeviceType::from($values->type),
				);
			} else {
				$this->homeDeviceFacade->create(
					$home->getId(),
					$values->internalId,
					$values->name,
					HomeDeviceType::from($values->type),
				);
			}

			$onSuccess();
		};

		return $form;
	}

}
