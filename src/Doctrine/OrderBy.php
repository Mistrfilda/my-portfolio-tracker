<?php

declare(strict_types = 1);

namespace App\Doctrine;

enum OrderBy: string
{

	case ASC = 'ASC';

	case DESC = 'DESC';

}
