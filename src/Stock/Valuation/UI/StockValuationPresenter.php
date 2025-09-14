<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\UI;

use App\Stock\Valuation\StockValuationFacade;
use App\Stock\Valuation\StockValuationTypeGroupEnum;
use App\UI\Base\BaseAdminPresenter;

/**
 * @property-read StockValuationTemplate $template
 */
class StockValuationPresenter extends BaseAdminPresenter
{

	public function __construct(
		private StockValuationFacade $stockValuationFacade,
		private StockValuationSortService $stockValuationSortService,
	)
	{
		parent::__construct();
	}

	public function renderDefault(
		string $typeGroupEnum = StockValuationTypeGroupEnum::VALUATION->value,
		string|null $sortBy = null,
		string $sortDirection = 'asc',
	): void
	{
		$this->template->heading = 'Valuace akcií';
		$this->template->renderableTypeGroups = StockValuationTypeGroupEnum::getRenderableGroups();
		$this->template->currentTypeGroup = $typeGroupEnum;
		$this->template->currentTypeGroupEnum = StockValuationTypeGroupEnum::from($typeGroupEnum);
		$this->template->typesForGroup = StockValuationTypeGroupEnum::from($typeGroupEnum)->getTypes();
		$this->template->currentSortBy = $sortBy;
		$this->template->currentSortDirection = $sortDirection;

		// Načtení a sortování dat
		$stockValuations = $this->stockValuationFacade->getStockValuations();

		if ($sortBy !== null) {
			$stockValuations = $this->stockValuationSortService->sortStockValuations(
				$stockValuations,
				$sortBy,
				$sortDirection,
			);
		}

		$this->template->stockValuations = $stockValuations;

		if ($this->isAjax()) {
			$this->redrawControl('valuationPage');
		}
	}

}
