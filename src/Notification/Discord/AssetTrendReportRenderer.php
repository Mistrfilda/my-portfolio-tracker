<?php

declare(strict_types = 1);

namespace App\Notification\Discord;

use App\Currency\CurrencyEnum;
use App\Notification\Notification;
use App\Notification\NotificationParameterEnum;
use App\UI\Filter\CurrencyFilter;
use App\UI\Filter\PercentageFilter;
use App\Utils\TypeValidator;
use Latte\Engine;

class AssetTrendReportRenderer
{

	private Engine $latte;

	public function __construct()
	{
		$this->latte = new Engine();
	}

	public function render(Notification $notification): string
	{
		$numberOfDaysToCompare = TypeValidator::validateInt(
			$notification->getParameter(NotificationParameterEnum::TREND_DAYS_THRESHOLD),
		);
		[$increasingTrendRows, $decreasingTrendRows] = $this->getTrendRows($notification);

		return $this->latte->renderToString(
			__DIR__ . '/assetTrendReport.latte',
			[
				'numberOfDaysToCompare' => $numberOfDaysToCompare,
				'daysUnit' => self::getDaysUnit($numberOfDaysToCompare),
				'increasingTrendRows' => $increasingTrendRows,
				'decreasingTrendRows' => $decreasingTrendRows,
			],
		);
	}

	/**
	 * @return array{
	 *     array<array{name: string, currentPrice: string, change: string, trend: float}>,
	 *     array<array{name: string, currentPrice: string, change: string, trend: float}>
	 * }
	 */
	private function getTrendRows(Notification $notification): array
	{
		/** @var array<array{name: string, currentPrice: string, change: string, trend: float}> $increasingTrendRows */
		$increasingTrendRows = [];
		/** @var array<array{name: string, currentPrice: string, change: string, trend: float}> $decreasingTrendRows */
		$decreasingTrendRows = [];
		$trends = TypeValidator::validateArray($notification->getData()['trends'] ?? null);
		foreach ($trends as $trendData) {
			$trendData = TypeValidator::validateArray($trendData);
			$trend = TypeValidator::validateFloat($trendData['trend'] ?? null);
			$trendRow = [
				'name' => TypeValidator::validateString($trendData['name'] ?? null),
				'currentPrice' => CurrencyFilter::format(
					TypeValidator::validateFloat($trendData['currentPrice'] ?? null),
					CurrencyEnum::from(TypeValidator::validateString($trendData['currency'] ?? null)),
				),
				'change' => sprintf(
					'%s%s',
					$trend > 0 ? '+' : '',
					PercentageFilter::format($trend),
				),
				'trend' => $trend,
			];

			if ($trend > 0) {
				$increasingTrendRows[] = $trendRow;
			} else {
				$decreasingTrendRows[] = $trendRow;
			}
		}

		usort($increasingTrendRows, self::compareTrendRows(...));
		usort($decreasingTrendRows, self::compareTrendRows(...));

		return [$increasingTrendRows, $decreasingTrendRows];
	}

	/**
	 * @param array{name: string, currentPrice: string, change: string, trend: float} $left
	 * @param array{name: string, currentPrice: string, change: string, trend: float} $right
	 */
	private static function compareTrendRows(array $left, array $right): int
	{
		return abs($right['trend']) <=> abs($left['trend']);
	}

	private static function getDaysUnit(int $numberOfDaysToCompare): string
	{
		return match (true) {
			$numberOfDaysToCompare === 1 => 'den',
			$numberOfDaysToCompare >= 2 && $numberOfDaysToCompare <= 4 => 'dny',
			default => 'dní',
		};
	}

}
