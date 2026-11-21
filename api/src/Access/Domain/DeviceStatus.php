<?php

declare(strict_types=1);

namespace App\Access\Domain;

enum DeviceStatus: string
{
    case Active = 'active';
    case SignedOut = 'signed_out';
}
