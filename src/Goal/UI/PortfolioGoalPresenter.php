<?php

declare(strict_types = 1);

namespace App\Goal\UI;

use App\Goal\PortfolioGoalRepository;
use App\Goal\PortfolioGoalUpdateFacade;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;

class PortfolioGoalPresenter extends BaseAdminPresenter
{

	public function __construct(
		private PortfolioGoalGridFactory $portfolioGoalGridFactory,
		private PortfolioGoalRepository $portfolioGoalRepository,
		private PortfolioGoalFormFactory $portfolioGoalFormFactory,
		private PortfolioGoalUpdateFacade $portfolioGoalUpdateFacade,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Cíle portfolia';
	}

	public function handleStartGoal(string $id): void
	{
		$this->flashMessage('Cíl byl zahájen');
		$this->portfolioGoalUpdateFacade->startGoal($this->processParameterRequiredUuid());
	}

	public function handleEndGoal(string $id): void
	{
		$this->flashMessage('Cíl byl ukončen');
		$this->portfolioGoalUpdateFacade->endGoal($this->processParameterRequiredUuid());
	}

	public function renderEdit(string|null $id): void
	{
		if ($id !== null) {
			$portfolioGoal = $this->portfolioGoalRepository->getById($this->processParameterRequiredUuid());
			$this->template->heading = sprintf(
				'Úprava cíle %s',
				$portfolioGoal->getType()->format(),
			);
		} else {
			$this->template->heading = 'Přidání nového cíle';
		}
	}

	protected function createComponentPortfolioGoalForm(): AdminForm
	{
		$id = $this->processParameterUuid();

		$onSuccess = function () use ($id): void {
			if ($id === null) {
				$this->flashMessage('Cíl úspěšně vytvořen', FlashMessageType::SUCCESS);
			} else {
				$this->flashMessage('Cíl úspěšně upraven', FlashMessageType::SUCCESS);
			}

			$this->redirect('PortfolioGoal:default');
		};

		return $this->portfolioGoalFormFactory->create($id, $onSuccess);
	}

	protected function createComponentPortfolioGoalGrid(): Datagrid
	{
		return $this->portfolioGoalGridFactory->create();
	}

}
