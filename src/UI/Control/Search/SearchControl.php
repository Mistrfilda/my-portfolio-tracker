<?php

declare(strict_types = 1);

namespace App\UI\Control\Search;

use App\Crypto\Asset\CryptoAssetRepository;
use App\Stock\Asset\StockAssetRepository;
use App\UI\Base\BaseControl;
use App\UI\Menu\MenuBuilder;
use App\UI\Menu\MenuGroup;
use App\UI\Menu\MenuItem;
use Nette\Application\LinkGenerator;

class SearchControl extends BaseControl
{

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
		private CryptoAssetRepository $cryptoAssetRepository,
		private LinkGenerator $linkGenerator,
		private MenuBuilder $menuBuilder,
	)
	{
	}

	public function render(): void
	{
		$groups = [];
		$stockAssets = [];
		foreach ($this->stockAssetRepository->findAll() as $stockAsset) {
			$stockAssets[] = new SearchGroupItem(
				$stockAsset->getName(),
				$this->linkGenerator->link(
					'Admin:StockAssetDetail:detail',
					['id' => $stockAsset->getId()->toString()],
				) ?? '#',
			);
		}

		foreach ($this->cryptoAssetRepository->findAll() as $cryptoAsset) {
			$stockAssets[] = new SearchGroupItem(
				$cryptoAsset->getName(),
				$this->linkGenerator->link(
					'Admin:CryptoAssetDetail:detail',
					['id' => $cryptoAsset->getId()->toString()],
				) ?? '#',
			);
		}

		$menuItems = [];
		foreach ($this->menuBuilder->buildMenu() as $menuItem) {
			if ($menuItem instanceof MenuItem) {
				$menuItems[] = new SearchGroupItem(
					$menuItem->getLabel(),
					$this->linkGenerator->link(
						'Admin:' . $menuItem->getLink(),
					) ?? '#',
				);
			}

			if ($menuItem instanceof MenuGroup) {
				foreach ($menuItem->getItems() as $subMenuItem) {
					$menuItems[] = new SearchGroupItem(
						$subMenuItem->getLabel(),
						$this->linkGenerator->link(
							'Admin:' . $subMenuItem->getLink(),
						) ?? '#',
					);
				}
			}
		}

		$menuItems[] = new SearchGroupItem(
			'Systémové informace',
			$this->linkGenerator->link('Admin:SystemValue:default') ?? '#',
		);

		$groups[] = new SearchGroup('Akcie', $stockAssets);
		$groups[] = new SearchGroup('Stránky', $menuItems);
		$template = $this->createTemplate(SearchControlTemplate::class);
		$template->searchGroups = $groups;
		$template->setFile(__DIR__ . '/SearchControl.latte');
		$template->render();
	}

}
