<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Downloader;

use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Mistrfilda\Datetime\DatetimeFactory;
use Psr\Log\LoggerInterface;

class WebStockAssetDividendDownloaderFacade implements StockAssetDividendDownloader
{

	public function __construct(
		private string $url,
		private string $requestHost,
		private string $cookie,
		private readonly Psr7RequestFactory $psr7RequestFactory,
		private readonly Psr18ClientFactory $psr18ClientFactory,
		private StockAssetRepository $stockAssetRepository,
		private StockAssetDividendRepository $stockAssetDividendRepository,
		private DatetimeFactory $datetimeFactory,
		private EntityManagerInterface $entityManager,
		private LoggerInterface $logger,
	)
	{
	}

	public function downloadDividendRecords(): void
	{
		$stockAssets = $this->stockAssetRepository->findByStockAssetDividendSource(
			StockAssetDividendSourceEnum::WEB,
		);

		$now = $this->datetimeFactory->createNow();
		foreach ($stockAssets as $stockAsset) {
			$this->logger->debug(
				sprintf('Processing dividend payer %s', $stockAsset->getName()),
			);

			$request = $this->psr7RequestFactory->createGETRequest(
				sprintf(
					$this->url,
					$stockAsset->getTicker(),
					$this->datetimeFactory->createToday()->deductDaysFromDatetime(1)->getTimestamp(),
					'capitalGain%7Cdiv%7Csplit',
				),
			);

			$request = $request->withHeader(
				'User-Agent',
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
			);
			$request = $request->withHeader('Host', $this->requestHost);
			$request = $request->withHeader('Cookie', $this->cookie);

			$response = $this->psr18ClientFactory->getClient()->sendRequest(
				$request,
			);

			$values = [];
			$contents = $response->getBody()->getContents();

			$domDocument = new DOMDocument();
			@$domDocument->loadHTML($contents);

			$domXpath = new DOMXPath($domDocument);
			$trNodes = $domXpath->query('//div[contains(@class, table-container)]/table/tbody/tr');
			assert($trNodes instanceof DOMNodeList);

			$this->logger->debug(
				sprintf(
					'Processing dividend payer %s - processing %s dividend records',
					$stockAsset->getName(),
					count($trNodes),
				),
			);

			foreach ($trNodes as $node) {
				$tdNodes = $domXpath->query('.//td', $node);

				assert($tdNodes instanceof DOMNodeList);
				assert($tdNodes->count() === 2);
				assert($tdNodes->item(0) instanceof DOMNode);
				assert($tdNodes->item(1) instanceof DOMNode);

				$nodeDateValue = (string) $tdNodes->item(0)->nodeValue;
				$nodePriceValue = (float) preg_replace('/[^0-9.]/', '', (string) $tdNodes->item(1)->nodeValue);

				$nodePriceValue = $stockAsset->getCurrency()->processFromWeb($nodePriceValue);

				$date = DatetimeFactory::createFromFormat(
					$nodeDateValue,
					'M d, Y',
				)->setTime(0, 0);

				$values[] = new StockAssetDividendDownloaderDTO(
					$date,
					null,
					$date,
					$stockAsset->getCurrency(),
					$nodePriceValue,
				);
			}

			foreach ($values as $value) {
				if ($this->stockAssetDividendRepository->findOneByStockAssetExDate(
					$stockAsset,
					$value->getExDate(),
				) !== null) {
					continue;
				}

				$this->entityManager->persist(
					new StockAssetDividend(
						$stockAsset,
						$value->getExDate(),
						$value->getPaymentDate(),
						$value->getDeclarationDate(),
						$stockAsset->getCurrency(),
						$value->getAmount(),
						$now,
					),
				);
			}

			$this->entityManager->flush();
			sleep(5);
		}
	}

}
