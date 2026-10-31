<?php

declare(strict_types = 1);

namespace App\Test\Unit\Goal\Api;

use App\Admin\AppAdmin;
use App\Goal\Api\PortfolioGoalSerializer;
use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalRepeatableEnum;
use App\Goal\PortfolioGoalTypeEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class PortfolioGoalSerializerTest extends TestCase
{

	#[TestWith([600.0, 50.0, 400.0])]
	#[TestWith([1100.0, 112.5, -100.0])]
	#[TestWith([100.0, -12.5, 900.0])]
	public function testProgressUsesTheStartingValueAndPreservesExceededOrNegativeProgress(
		float $current,
		float $percentage,
		float $remaining,
	): void
	{
		$now = new ImmutableDateTime('2026-10-25T12:00:00+01:00');
		$goal = new PortfolioGoal(
			$this->createStub(AppAdmin::class),
			new ImmutableDateTime('2026-10-01'),
			new ImmutableDateTime('2026-10-31'),
			PortfolioGoalTypeEnum::TOTAL_INVESTED_AMOUNT,
			1000,
			PortfolioGoalRepeatableEnum::MONTHLY,
			$now,
		);
		$goal->start(200, $current, $now);
		$data = new PortfolioGoalSerializer()->serialize($goal);
		self::assertSame([
			'id' => $goal->getId()->toString(),
			'type' => 'total_invested_amount',
			'currency' => 'CZK',
			'startDate' => '2026-10-01',
			'endDate' => '2026-10-31',
			'repeatable' => 'monthly',
			'valueAtStart' => 200.0,
			'currentValue' => $current,
			'targetValue' => 1000.0,
			'completionPercent' => $percentage,
			'remainingAmount' => $remaining,
			'updatedAt' => '2026-10-25T12:00:00+01:00',
		], $data);
	}

}
