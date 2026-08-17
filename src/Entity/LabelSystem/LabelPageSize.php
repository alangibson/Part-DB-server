<?php

declare(strict_types=1);

namespace App\Entity\LabelSystem;

enum LabelPageSize: string
{
    case CUSTOM = 'CUSTOM';
    case A4 = 'A4';
    case LETTER = 'LETTER';
}
