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

namespace App\Tests\Services\LabelSystem\Output;

use App\Entity\LabelSystem\LabelOptions;
use App\Entity\LabelSystem\LabelOutputFormat;
use App\Entity\LabelSystem\LabelSupportedElement;
use App\Entity\Parts\Part;
use App\Exceptions\LabelleConversionException;
use App\Services\LabelSystem\Output\LabelOutputManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LabelOutputManagerTest extends KernelTestCase
{
    private LabelOutputManager $manager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->manager = self::getContainer()->get(LabelOutputManager::class);
    }

    public function testGeneratesLabelleDownload(): void
    {
        $options = new LabelOptions();
        $options->setSupportedElement(LabelSupportedElement::PART);
        $options->setLines('<p>Test</p>');
        $options->setAdditionalCss('.test { color: red; }');

        $file = $this->manager->generate(LabelOutputFormat::LABELLE, $options, [new Part()], 'label_part');

        self::assertSame('label_part.labelle', $file->filename);
        self::assertSame('text/plain; charset=UTF-8', $file->mimeType);
        self::assertSame("LABELLE-LABEL-SPEC-VERSION:1\nTEXT:Test\n", $file->content);
        self::assertNotEmpty($file->warnings);
    }

    public function testGeneratesPdfByExistingPath(): void
    {
        $options = new LabelOptions();
        $options->setSupportedElement(LabelSupportedElement::PART);
        $options->setLines('<p>Test</p>');

        $file = $this->manager->generate(LabelOutputFormat::PDF, $options, [new Part()], 'label_part');

        self::assertSame('label_part.pdf', $file->filename);
        self::assertSame('application/pdf', $file->mimeType);
        self::assertStringStartsWith('%PDF-', $file->content);
    }

    public function testLabelleRejectsMultipleTargets(): void
    {
        $options = new LabelOptions();
        $options->setSupportedElement(LabelSupportedElement::PART);
        $options->setLines('<p>Test</p>');

        $this->expectException(LabelleConversionException::class);
        $this->manager->generate(LabelOutputFormat::LABELLE, $options, [new Part(), new Part()], 'labels');
    }
}
