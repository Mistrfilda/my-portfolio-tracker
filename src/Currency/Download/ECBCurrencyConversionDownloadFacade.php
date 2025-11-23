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
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\Arrays;
use SimpleXMLElement;

class ECBCurrencyConversionDownloadFacade implements CurrencyConversionDownloadFacade
{

	private const ECB_RATES_XML = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

	public const RATES_TO_BE_DOWNLOADED = [
		'USD' => CurrencyEnum::USD,
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
		$ecbResponseXml = $this->psr18ClientFactory->getClient()->sendRequest(
			$this->psr7RequestFactory->createGETRequest(self::ECB_RATES_XML),
		);

		$xml = new SimpleXMLElement($ecbResponseXml->getBody()->getContents());
		$xml->registerXPathNamespace('ecb', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');

		foreach (self::RATES_TO_BE_DOWNLOADED as $rateToBeDownloaded => $matchedEnum) {
			$xpathResult = $xml->xpath('//ecb:Cube[@currency="' . $rateToBeDownloaded . '"]');
			if ($xpathResult === null || $xpathResult === false) {
				continue;
			}

			$xpathResult = Arrays::first($xpathResult);

			if ($xpathResult === null || $xpathResult->attributes() instanceof SimpleXMLElement === false) {
				continue;
			}

			$parsedRate = (float) $xpathResult->attributes()['rate'];

			$rate = $this->currencyConversionRepository->findCurrencyPairConversionForDate(
				CurrencyEnum::EUR,
				$matchedEnum,
				$today,
			);

			if ($rate !== null) {
				$rate->update($parsedRate, $now);
			} else {
				$rate = new CurrencyConversion(
					CurrencyEnum::EUR,
					$matchedEnum,
					$parsedRate,
					CurrencySourceEnum::ECB,
					$now,
					$today,
				);
				$this->entityManager->persist($rate);
			}

			$downloadedRates[] = $rate;

			$inversedRate = $this->currencyConversionRepository->findCurrencyPairConversionForDate(
				$matchedEnum,
				CurrencyEnum::EUR,
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
			SystemValueEnum::ECB_CURRENCY_DOWNLOADED_COUNT,
			intValue: count($downloadedRates),
		);

		return $downloadedRates;
	}

	public function getConsoleDescription(): string
	{
		return 'ECB - EUROPEAN CENTRAL BANK';
	}

}
