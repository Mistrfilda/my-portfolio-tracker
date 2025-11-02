<?php

declare(strict_types = 1);

namespace App\UI\Control\Search;

use App\Stock\Asset\StockAssetRepository;
use App\UI\Base\BaseControl;
use App\UI\Menu\MenuBuilder;
use Nette\Application\LinkGenerator;

class SearchControl extends BaseControl
{

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
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

		$menuItems = [];
		foreach ($this->menuBuilder->buildMenu() as $menuItem) {
			if ($menuItem->getLink() !== null) {
				$menuItems[] = new SearchGroupItem(
					$menuItem->getLabel(),
					$this->linkGenerator->link(
						'Admin:' . $menuItem->getLink(),
					) ?? '#',
				);
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
