<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum Channel: int
{
    use HasLabel;

    case Sms = 1;
    case Email = 2;
}
