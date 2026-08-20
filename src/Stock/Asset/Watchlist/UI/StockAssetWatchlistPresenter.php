<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Watchlist\UI;

use App\Stock\Asset\Watchlist\StockAssetWatchlistFacade;
use App\Stock\Asset\Watchlist\StockAssetWatchlistRepository;
use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;
use Ramsey\Uuid\Uuid;

/**
 * @property-read StockAssetWatchlistTemplate $template
 */
class StockAssetWatchlistPresenter extends BaseSysadminPresenter
{

	public function __construct(
		private readonly StockAssetWatchlistGridFactory $stockAssetWatchlistGridFactory,
		private readonly StockAssetWatchlistFormFactory $stockAssetWatchlistFormFactory,
		private readonly StockAssetWatchlistRepository $stockAssetWatchlistRepository,
		private readonly StockAssetWatchlistFacade $stockAssetWatchlistFacade,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Jednoduchý akciový watchlist';
	}

	public function renderEdit(string|null $id): void
	{
		if ($id === null) {
			$this->template->heading = 'Přidání akcie na jednoduchý watchlist';
			return;
		}

		$stockAssetWatchlist = $this->stockAssetWatchlistRepository->getById(
			$this->processParameterRequiredUuid(),
		);
		$this->template->heading = sprintf('Úprava %s na jednoduchém watchlistu', $stockAssetWatchlist->getTicker());
	}

	public function handleDelete(string $id): void
	{
		$this->stockAssetWatchlistFacade->delete(Uuid::fromString($id));
		$this->flashMessage('Položka byla z jednoduchého watchlistu odstraněna.', FlashMessageType::SUCCESS);
		$this->redirect('StockAssetWatchlist:default');
	}

	protected function createComponentStockAssetWatchlistGrid(): Datagrid
	{
		return $this->stockAssetWatchlistGridFactory->create();
	}

	protected function createComponentStockAssetWatchlistForm(): AdminForm
	{
		$id = $this->processParameterUuid();

		return $this->stockAssetWatchlistFormFactory->create($id, function () use ($id): void {
			$this->flashMessage(
				$id === null
					? 'Akcie byla přidána na jednoduchý watchlist.'
					: 'Položka jednoduchého watchlistu byla upravena.',
				FlashMessageType::SUCCESS,
			);
			$this->redirect('StockAssetWatchlist:default');
		});
	}

}
