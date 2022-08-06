<?php

declare(strict_types = 1);

namespace App\Asset\Portfolio;

use App\Admin\AppAdmin;
use App\Asset\Position\AssetPosition;

interface AssetPortfolio
{

	public function getAppAdmin(): AppAdmin;

	/**
	 * @return array<AssetPosition>
	 */
	public function getPositions(): array;

	public function getName(): string;

}
