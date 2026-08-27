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

namespace App\Tests\Services\LabelSystem\Labelle;

use App\Entity\LabelSystem\BarcodeType;
use App\Exceptions\LabelleConversionException;
use App\Services\LabelSystem\Labelle\LabelleBatchConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LabelleBatchConverterTest extends KernelTestCase
{
    private LabelleBatchConverter $converter;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->converter = self::getContainer()->get(LabelleBatchConverter::class);
    }

    public function testConvertsParagraphsAndLineBreaks(): void
    {
        $result = $this->converter->convert('<p>First<br>Second</p><p>Third</p>');

        self::assertSame(
            "LABELLE-LABEL-SPEC-VERSION:1\nTEXT:First\nNEWLINE:Second\nTEXT:Third\n",
            $result->batch,
        );
    }

    public function testTransposesLayoutTable(): void
    {
        $result = $this->converter->convert(<<<'HTML'
            <figure class="table"><table><tbody>
            <tr><td>A</td><td>B</td></tr>
            <tr><td>C</td><td>D</td></tr>
            </tbody></table></figure>
            HTML);

        self::assertSame(
            "LABELLE-LABEL-SPEC-VERSION:1\nTEXT:A\nNEWLINE:C\nTEXT:B\nNEWLINE:D\n",
            $result->batch,
        );
        self::assertNotEmpty($result->warnings);
    }

    public function testPreservesEmptyTableCells(): void
    {
        $result = $this->converter->convert('<table><tr><td>A</td><td></td></tr><tr><td></td><td>D</td></tr></table>');

        self::assertSame(
            "LABELLE-LABEL-SPEC-VERSION:1\nTEXT:A\nNEWLINE:\nTEXT:\nNEWLINE:D\n",
            $result->batch,
        );
    }

    public function testApproximatesMergedCellsAndKeepsUnicode(): void
    {
        $result = $this->converter->convert('<table><tr><td colspan="2">µF</td></tr><tr><td>A</td><td>B</td></tr></table>');

        self::assertSame(
            "LABELLE-LABEL-SPEC-VERSION:1\nTEXT:µF\nNEWLINE:A\nTEXT:\nNEWLINE:B\n",
            $result->batch,
        );
        self::assertNotEmpty($result->warnings);
    }

    public function testConvertsMainAndEmbeddedCodes(): void
    {
        $result = $this->converter->convert(
            '<p><img alt="ABC" data-label-code-type="code39" data-label-code-content="ABC:123"></p>',
            BarcodeType::QR,
            'https://example.com/part/1',
        );

        self::assertSame(
            "LABELLE-LABEL-SPEC-VERSION:1\nQR:https://example.com/part/1\nBARCODE#CODE39:ABC:123\n",
            $result->batch,
        );
    }

    public function testPreservesTextOrderAroundEmbeddedCode(): void
    {
        $result = $this->converter->convert(
            '<p>Before<img alt="ABC" data-label-code-type="code128" data-label-code-content="ABC">After</p>',
        );

        self::assertSame(
            "LABELLE-LABEL-SPEC-VERSION:1\nTEXT:Before\nBARCODE#CODE128:ABC\nTEXT:After\n",
            $result->batch,
        );
    }

    public function testRenderedLineBreakCannotInjectBatchCommand(): void
    {
        $result = $this->converter->convert("<p>Safe<br>BARCODE:injected</p>");

        self::assertSame(
            "LABELLE-LABEL-SPEC-VERSION:1\nTEXT:Safe\nNEWLINE:BARCODE:injected\n",
            $result->batch,
        );
    }

    public function testRawTextLineBreaksBecomeControlledNewlines(): void
    {
        $result = $this->converter->convert("First\nBARCODE:still text");

        self::assertSame(
            "LABELLE-LABEL-SPEC-VERSION:1\nTEXT:First\nNEWLINE:BARCODE:still text\n",
            $result->batch,
        );
    }

    #[DataProvider('unsupportedBarcodeProvider')]
    public function testRejectsUnsupportedBarcode(BarcodeType $barcodeType): void
    {
        $this->expectException(LabelleConversionException::class);
        $this->converter->convert('', $barcodeType, 'content');
    }

    public static function unsupportedBarcodeProvider(): \Iterator
    {
        yield [BarcodeType::DATAMATRIX];
        yield [BarcodeType::CODE93];
    }

    public function testRejectsNestedTables(): void
    {
        $this->expectException(LabelleConversionException::class);
        $this->converter->convert('<table><tr><td><table><tr><td>Nested</td></tr></table></td></tr></table>');
    }

    public function testImageAlternativeTextProducesWarning(): void
    {
        $result = $this->converter->convert('<p><img src="data:image/png;base64,AA==" alt="Image description"></p>');

        self::assertStringContainsString('TEXT:Image description', $result->batch);
        self::assertNotEmpty($result->warnings);
    }

    public function testRejectsImageWithoutAlternativeText(): void
    {
        $this->expectException(LabelleConversionException::class);
        $this->converter->convert('<img src="data:image/png;base64,AA==">');
    }

    public function testRejectsControlCharacters(): void
    {
        $this->expectException(LabelleConversionException::class);
        $this->converter->convert('', BarcodeType::QR, "invalid\0content");
    }
}
