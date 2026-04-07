<?php

namespace App\Enums;

enum AddressLabel: string
{
    case Home   = 'home';
    case Office = 'office';
    case Other  = 'other';
}
