<?php

namespace App\Enums;

enum RedirectType: int
{
    case Permanent = 301;
    case Temporary = 302;
}
