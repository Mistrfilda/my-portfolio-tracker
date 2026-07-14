<?php

declare(strict_types = 1);

namespace App\Notification\Discord;

use App\Currency\CurrencyEnum;
use App\Notification\Notification;
use App\Notification\NotificationParameterEnum;
use App\Notification\NotificationTypeEnum;
use App\UI\Filter\CurrencyFilter;
use App\UI\Filter\PercentageFilter;
use App\Utils\TypeValidator;
use Mistrfilda\Datetime\DatetimeFactory;
use const STR_PAD_LEFT;

class DiscordMessageService
{

	private const COLOR_GREEN = 3066993;

	private const COLOR_RED = 15158332;

	private const COLOR_BLUE = 3447003;

	private const MAX_ASSET_NAME_WIDTH = 24;

	public function __construct(private DatetimeFactory $datetimeFactory)
	{

	}

	/**
	 * @return array<mixed>
	 */
	public function getMessage(Notification $notification): array
	{
		$timestamp = $this->datetimeFactory->createNow()->format('Y-m-d\TH:i:s.u\Z');
		if ($notification->getNotificationTypeEnum() === NotificationTypeEnum::ASSET_TRENDS) {
			return $this->getAssetTrendsMessage($notification, $timestamp);
		}

		return [
			'embeds' => [
				[
					'title' => $this->getTitle($notification->getNotificationTypeEnum()),
					'description' => $notification->getMessage(),
					'color' => $this->getColor($notification->getNotificationTypeEnum()),
					'timestamp' => $timestamp,
				],
			],
		];
	}

	/**
	 * @return array<mixed>
	 */
	private function getAssetTrendsMessage(Notification $notification, string $timestamp): array
	{
		$numberOfDaysToCompare = TypeValidator::validateInt(
			$notification->getParameter(NotificationParameterEnum::TREND_DAYS_THRESHOLD),
		);
		$daysUnit = match (true) {
			$numberOfDaysToCompare === 1 => 'den',
			$numberOfDaysToCompare >= 2 && $numberOfDaysToCompare <= 4 => 'dny',
			default => 'dní',
		};

		/** @var array<array{string, string, string, float}> $increasingTrendRows */
		$increasingTrendRows = [];
		/** @var array<array{string, string, string, float}> $decreasingTrendRows */
		$decreasingTrendRows = [];
		$trends = TypeValidator::validateArray($notification->getData()['trends'] ?? null);
		foreach ($trends as $trendData) {
			$trendData = TypeValidator::validateArray($trendData);
			$trend = TypeValidator::validateFloat($trendData['trend'] ?? null);
			$trendRow = [
				mb_strimwidth(
					TypeValidator::validateString($trendData['name'] ?? null),
					0,
					self::MAX_ASSET_NAME_WIDTH,
					'…',
				),
				CurrencyFilter::format(
					TypeValidator::validateFloat($trendData['currentPrice'] ?? null),
					CurrencyEnum::from(TypeValidator::validateString($trendData['currency'] ?? null)),
				),
				sprintf(
					'%s %s%s',
					$trend > 0 ? '▲' : '▼',
					$trend > 0 ? '+' : '',
					PercentageFilter::format($trend),
				),
				$trend,
			];

			if ($trend > 0) {
				$increasingTrendRows[] = $trendRow;
			} else {
				$decreasingTrendRows[] = $trendRow;
			}
		}

		usort($increasingTrendRows, self::compareTrendRows(...));
		usort($decreasingTrendRows, self::compareTrendRows(...));

		$embeds = [
			[
				'title' => $this->getTitle(NotificationTypeEnum::ASSET_TRENDS),
				'description' => sprintf('Časové okno: **%d %s**', $numberOfDaysToCompare, $daysUnit),
				'color' => self::COLOR_BLUE,
				'timestamp' => $timestamp,
			],
		];
		$sections = [
			['title' => '📈 Růst', 'color' => self::COLOR_GREEN, 'rows' => $increasingTrendRows],
			['title' => '📉 Pokles', 'color' => self::COLOR_RED, 'rows' => $decreasingTrendRows],
		];
		foreach ($sections as $section) {
			if ($section['rows'] === []) {
				continue;
			}

			$embeds[] = [
				'title' => sprintf('%s · %d', $section['title'], count($section['rows'])),
				'color' => $section['color'],
				'description' => $this->formatTrendTable($section['rows']),
			];
		}

		return ['embeds' => $embeds];
	}

	/**
	 * @param array{string, string, string, float} $left
	 * @param array{string, string, string, float} $right
	 */
	private static function compareTrendRows(array $left, array $right): int
	{
		return abs($right[3]) <=> abs($left[3]);
	}

	/**
	 * @param array<array{string, string, string, float}> $trendRows
	 */
	private function formatTrendTable(array $trendRows): string
	{
		$headers = ['Aktivum', 'Cena', 'Změna'];
		$columnWidths = array_map('mb_strlen', $headers);
		foreach ($trendRows as $trendRow) {
			foreach (array_slice($trendRow, 0, 3) as $column => $value) {
				$columnWidths[$column] = max($columnWidths[$column], mb_strlen($value));
			}
		}

		$tableLines = [
			sprintf(
				'%s  %s  %s',
				mb_str_pad($headers[0], $columnWidths[0]),
				mb_str_pad($headers[1], $columnWidths[1], ' ', STR_PAD_LEFT),
				mb_str_pad($headers[2], $columnWidths[2], ' ', STR_PAD_LEFT),
			),
			sprintf(
				'%s  %s  %s',
				str_repeat('─', $columnWidths[0]),
				str_repeat('─', $columnWidths[1]),
				str_repeat('─', $columnWidths[2]),
			),
		];

		foreach ($trendRows as $trendRow) {
			$tableLines[] = sprintf(
				'%s  %s  %s',
				mb_str_pad($trendRow[0], $columnWidths[0]),
				mb_str_pad($trendRow[1], $columnWidths[1], ' ', STR_PAD_LEFT),
				mb_str_pad($trendRow[2], $columnWidths[2], ' ', STR_PAD_LEFT),
			);
		}

		return sprintf("```text\n%s\n```", implode("\n", $tableLines));
	}

	private function getTitle(NotificationTypeEnum $type): string
	{
		return match ($type) {
			NotificationTypeEnum::NEW_DIVIDEND => 'Nová dividenda',
			NotificationTypeEnum::PRICE_ALERT_UP => '📈 Price alert up',
			NotificationTypeEnum::PRICE_ALERT_DOWN => '📉 Price alert down',
			NotificationTypeEnum::ASSET_TRENDS => '📊 Přehled trendů aktiv',
			NotificationTypeEnum::GOALS_UPDATES => 'Aktualizace cíle portfolia',
		};
	}

	private function getColor(NotificationTypeEnum $type): int
	{
		return match ($type) {
			NotificationTypeEnum::NEW_DIVIDEND,
			NotificationTypeEnum::PRICE_ALERT_UP,
			NotificationTypeEnum::GOALS_UPDATES => self::COLOR_GREEN,
			NotificationTypeEnum::PRICE_ALERT_DOWN => self::COLOR_RED,
			NotificationTypeEnum::ASSET_TRENDS => self::COLOR_BLUE,
		};
	}

}
