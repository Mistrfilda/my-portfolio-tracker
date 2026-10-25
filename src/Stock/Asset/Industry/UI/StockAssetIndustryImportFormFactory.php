<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Industry\UI;

use App\Stock\Asset\Industry\StockAssetIndustryDownloadFacade;
use App\Stock\Asset\Industry\StockAssetIndustryImportException;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Utils\ArrayHash;

class StockAssetIndustryImportFormFactory
{

	private const int MAX_FILE_SIZE = 2 * 1024 * 1024;

	public function __construct(
		private readonly AdminFormFactory $adminFormFactory,
		private readonly StockAssetIndustryDownloadFacade $stockAssetIndustryDownloadFacade,
	)
	{
	}

	public function create(callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();
		$form->addUpload('htmlFile', 'HTML soubor s valuacemi odvětví')
			->setRequired('Vyberte HTML soubor s tabulkou valuací odvětví.')
			->setHtmlAttribute('accept', '.html,.htm,text/html')
			->addRule(Form::MaxFileSize, 'HTML soubor může mít nejvýše 2 MiB.', self::MAX_FILE_SIZE);
		$form->addSubmit('submit', 'Importovat valuace odvětví');

		$form->onSuccess[] = function (AdminForm $form) use ($onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);
			$file = $values->htmlFile;
			assert($file instanceof FileUpload);
			$contents = $file->getContents();
			if ($contents === null) {
				$form->addError('Nahraný HTML soubor se nepodařilo přečíst.');
				return;
			}

			try {
				$updatedCount = $this->stockAssetIndustryDownloadFacade->processHtml($contents);
			} catch (StockAssetIndustryImportException) {
				$form->addError(
					'Import se nezdařil. Nahrajte HTML s úplnou tabulkou valuací odvětví. '
					. 'Názvy odvětví musí odpovídat názvům pro mapování v aplikaci.',
				);
				return;
			}

			$onSuccess($updatedCount);
		};

		return $form;
	}

}
