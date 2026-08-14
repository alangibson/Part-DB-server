<?php

declare(strict_types=1);

namespace App\Entity\LabelSystem;

enum LabelUnit: string
{
    case MILLIMETER = 'mm';
    case INCH = 'inch';

    public function cssUnit(): string
    {
        return match ($this) {
            self::MILLIMETER => 'mm',
            self::INCH => 'in',
        };
    }

    public function toMillimeters(float $value): float
    {
        return match ($this) {
            self::MILLIMETER => $value,
            self::INCH => $value * 25.4,
        };
    }

    public function fromMillimeters(float $value): float
    {
        return match ($this) {
            self::MILLIMETER => $value,
            self::INCH => $value / 25.4,
        };
    }

    public function fromInches(float $value): float
    {
        return match ($this) {
            self::MILLIMETER => $value * 25.4,
            self::INCH => $value,
        };
    }
}
