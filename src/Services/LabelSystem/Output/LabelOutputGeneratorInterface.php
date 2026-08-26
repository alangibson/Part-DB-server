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
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.label_output_generator')]
interface LabelOutputGeneratorInterface
{
    public function supports(LabelOutputFormat $format): bool;

    /** @param object[] $elements */
    public function generate(LabelOptions $options, array $elements, string $filenameBase): GeneratedLabelFile;
}
