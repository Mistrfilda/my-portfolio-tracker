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
	)
	{
		parent::__construct();
	}

	public function renderDefault(string $typeGroupEnum = StockValuationTypeGroupEnum::VALUATION->value): void
	{
		$this->template->heading = 'Valuace akcií';
		$this->template->renderableTypeGroups = StockValuationTypeGroupEnum::getRenderableGroups();
		$this->template->currentTypeGroup = $typeGroupEnum;
		$this->template->stockValuations = $this->stockValuationFacade->getStockValuations();
		$this->template->currentTypeGroupEnum = StockValuationTypeGroupEnum::from($typeGroupEnum);
		$this->template->typesForGroup = StockValuationTypeGroupEnum::from($typeGroupEnum)->getTypes();

		if ($this->isAjax()) {
			$this->redrawControl('valuationPage');
		}

		//      dump($this->stockValuationFacade->getStockValuations());
		//      die();
	}

}
