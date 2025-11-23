<?php

declare(strict_types = 1);

namespace App\Currency\Download;

use App\Currency\CurrencyConversion;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use App\Utils\TypeValidator;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\Strings;

class CNBCurrencyConversionDownloadFacade implements CurrencyConversionDownloadFacade
{

	private const CNB_RATES_URL = 'https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.txt';

	public const RATES_TO_BE_DOWNLOADED = [
		'USD' => CurrencyEnum::USD,
		'EUR' => CurrencyEnum::EUR,
		'GBP' => CurrencyEnum::GBP,
		'PLN' => CurrencyEnum::PLN,
		'NOK' => CurrencyEnum::NOK,
	];

	public function __construct(
		private readonly Psr7RequestFactory $psr7RequestFactory,
		private readonly Psr18ClientFactory $psr18ClientFactory,
		private readonly CurrencyConversionRepository $currencyConversionRepository,
		private readonly CurrencyConversionDownloadInverseRateHelper $currencyConversionDownloadInverseRateHelper,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly EntityManagerInterface $entityManager,
		private readonly SystemValueFacade $systemValueFacade,
	)
	{

	}

	/**
	 * @return array<CurrencyConversion>
	 */
	public function downloadNewRates(): array
	{
		$today = $this->datetimeFactory->createToday();
		$now = $this->datetimeFactory->createNow();

		$downloadedRates = [];
		$cnbRateResponse = $this->psr18ClientFactory->getClient()->sendRequest(
			$this->psr7RequestFactory->createGETRequest(self::CNB_RATES_URL),
		);

		$responseContents = Strings::split($cnbRateResponse->getBody()->getContents(), '~\n~');
		foreach ($responseContents as $responseContent) {
			$parsedLine = Strings::split(TypeValidator::validateString($responseContent), '~\|~');
			if (count($parsedLine) !== 5) {
				continue;
			}

			assert(is_int($parsedLine[3]) || is_string($parsedLine[3]));
			if (array_key_exists($parsedLine[3], self::RATES_TO_BE_DOWNLOADED) === false) {
				continue;
			}

			$rate = $this->currencyConversionRepository->findCurrencyPairConversionForDate(
				self::RATES_TO_BE_DOWNLOADED[$parsedLine[3]],
				CurrencyEnum::CZK,
				$today,
			);

			$parsedRate = (float) Strings::replace(TypeValidator::validateString($parsedLine[4]), '~,~', '.');

			if ($rate !== null) {
				$rate->update($parsedRate, $now);
			} else {
				$rate = new CurrencyConversion(
					self::RATES_TO_BE_DOWNLOADED[$parsedLine[3]],
					CurrencyEnum::CZK,
					$parsedRate,
					CurrencySourceEnum::CNB,
					$now,
					$today,
				);

				$this->entityManager->persist($rate);
			}

			$downloadedRates[] = $rate;

			$inversedRate = $this->currencyConversionRepository->findCurrencyPairConversionForDate(
				CurrencyEnum::CZK,
				self::RATES_TO_BE_DOWNLOADED[$parsedLine[3]],
				$today,
			);

			if ($inversedRate !== null) {
				$this->currencyConversionDownloadInverseRateHelper->updateExistingInversedRate(
					$rate,
					$inversedRate,
					$now,
				);
			} else {
				$inversedRate = $this->currencyConversionDownloadInverseRateHelper->getNewInversedRate($rate);
				$this->entityManager->persist($inversedRate);
			}

			$downloadedRates[] = $inversedRate;
		}

		$this->entityManager->flush();

		$this->systemValueFacade->updateValue(
			SystemValueEnum::CNB_CURRENCY_DOWNLOADED_COUNT,
			intValue: count($downloadedRates),
		);

		return $downloadedRates;
	}

	public function getConsoleDescription(): string
	{
		return 'CNB - CESKA NARODNI BANKA RATES';
	}

}
