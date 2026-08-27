<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace App\Services\LabelSystem\Output;

use App\Entity\LabelSystem\LabelOptions;
use App\Entity\LabelSystem\LabelOutputFormat;
use App\Services\LabelSystem\LabelGenerator;

final readonly class PdfLabelOutputGenerator implements LabelOutputGeneratorInterface
{
    public function __construct(private LabelGenerator $labelGenerator)
    {
    }

    public function supports(LabelOutputFormat $format): bool
    {
        return $format === LabelOutputFormat::PDF;
    }

    public function generate(LabelOptions $options, array $elements, string $filenameBase, int $copies = 1, int $startSlot = 1): GeneratedLabelFile
    {
        return new GeneratedLabelFile(
            $this->labelGenerator->generateLabel($options, $elements, $copies, $startSlot),
            'application/pdf',
            $filenameBase.'.pdf',
        );
    }
}
