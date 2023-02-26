<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\UI;

use App\Stock\Asset\StockAssetRepository;
use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;
use Ramsey\Uuid\Uuid;

class StockAssetDividendPresenter extends BaseSysadminPresenter
{

	public string $stockAssetId;

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
		private StockAssetDividendGridFactory $stockAssetDividendGridFactory,
		private StockAssetDividendFormFactory $stockAssetDividendFormFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(string $stockAssetId): void
	{
		$stockAsset = $this->stockAssetRepository->getById(Uuid::fromString($stockAssetId));
		$this->template->stockAssetId = $stockAssetId;
		$this->template->heading = 'Dividendy akcie ' . $stockAsset->getName();
	}

	public function renderEdit(string|null $id, string $stockAssetId): void
	{
		$stockAsset = $this->stockAssetRepository->getById(Uuid::fromString($stockAssetId));
		$this->template->stockAssetId = $stockAssetId;
		$this->template->heading = 'Editace dividendy akcie ' . $stockAsset->getName();
	}

	protected function createComponentStockAssetDividendGrid(): Datagrid
	{
		return $this->stockAssetDividendGridFactory->create(
			$this->processParameterRequiredUuid('stockAssetId'),
		);
	}

	protected function createComponentStockAssetDividendForm(): AdminForm
	{
		$stockAssetId = $this->processParameterRequiredUuid('stockAssetId');

		$onSuccess = function () use ($stockAssetId): void {
			$this->flashMessage('Úspešně vytvořeno');
			$this->redirect('default', ['stockAssetId' => $stockAssetId->toString()]);
		};

		return $this->stockAssetDividendFormFactory->create(
			$this->processParameterUuid(),
			$this->processParameterRequiredUuid('stockAssetId'),
			$onSuccess,
		);
	}

}
