<?php

namespace App\Enums;

enum LlmsScope: string
{
    case Index = 'index';
    case Full  = 'full';
}
