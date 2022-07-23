<?php

declare(strict_types = 1);

namespace App\UI\Control\Form\Input;

use Nette\Forms\Controls\UploadControl;

class CustomFileUpload extends UploadControl
{

	public function setImagePreview(string $fileUrl): self
	{
		$this->setOption('fileUrl', $fileUrl);

		return $this;
	}

	public function setDescription(string $description): self
	{
		$this->setOption('description', $description);

		return $this;
	}

	public function setDeleteButton(string $href): self
	{
		$this->setOption('deleteHref', $href);

		return $this;
	}

}
