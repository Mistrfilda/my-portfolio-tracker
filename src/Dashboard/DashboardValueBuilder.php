<?php

declare(strict_types = 1);

namespace App\Dashboard;

interface DashboardValueBuilder
{

	/**
	 * @return array<int, DashboardValueGroup>
	 */
	public function buildValues(): array;

}
