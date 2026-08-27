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
use App\Services\LabelSystem\LabelHTMLGenerator;
use App\Services\LabelSystem\Labelle\LabelleBatchConverter;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class LabelleFileOutputGenerator implements LabelOutputGeneratorInterface
{
    public function __construct(
        private LabelHTMLGenerator $htmlGenerator,
        private LabelleBatchConverter $converter,
        private TranslatorInterface $translator,
    ) {
    }

    public function supports(LabelOutputFormat $format): bool
    {
        return $format === LabelOutputFormat::LABELLE;
    }

    public function generate(LabelOptions $options, array $elements, string $filenameBase, int $copies = 1, int $startSlot = 1): GeneratedLabelFile
    {
        $conversions = [];
        foreach ($this->htmlGenerator->getRenderedElements($options, $elements, $copies) as $rendered) {
            $conversions[] = $this->converter->convert(
                $rendered['lines'],
                $options->getBarcodeType(),
                $rendered['barcode_content'],
            );
        }
        $conversion = $this->converter->combine($conversions);

        $warnings = $conversion->warnings;
        if (trim($options->getAdditionalCss()) !== '') {
            $warnings[] = $this->translator->trans('labelle.warning.additional_css');
        }

        return new GeneratedLabelFile(
            $conversion->batch,
            'text/plain; charset=UTF-8',
            $filenameBase.'.labelle',
            array_values(array_unique($warnings)),
        );
    }
}
