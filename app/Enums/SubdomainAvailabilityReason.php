<?php

declare(strict_types=1);

namespace App\Enums;

enum SubdomainAvailabilityReason: string
{
    case Taken = 'taken';
    case Reserved = 'reserved';
    case InvalidFormat = 'invalid_format';
}
