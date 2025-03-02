<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Account;

use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table('bank_acount')]
class BankAccount implements Entity
{

	use Identifier;

}
