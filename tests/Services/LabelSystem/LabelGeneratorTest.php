<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Tests\Services\LabelSystem;

use PHPUnit\Framework\Attributes\DataProvider;
use App\Entity\Base\AbstractDBElement;
use App\Entity\LabelSystem\BarcodeType;
use App\Entity\LabelSystem\LabelOptions;
use App\Entity\LabelSystem\LabelSupportedElement;
use App\Entity\LabelSystem\LabelUnit;
use App\Entity\LabelSystem\LabelPageSize;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\LabelSystem\LabelSheet;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Services\LabelSystem\LabelGenerator;
use App\Services\LabelSystem\LabelHTMLGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LabelGeneratorTest extends WebTestCase
{
    /**
     * @var LabelGenerator
     */
    protected $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(LabelGenerator::class);
    }

    public static function supportsDataProvider(): \Iterator
    {
        yield [LabelSupportedElement::PART, Part::class];
        yield [LabelSupportedElement::PART_LOT, PartLot::class];
        yield [LabelSupportedElement::STORELOCATION, StorageLocation::class];
    }

    #[DataProvider('supportsDataProvider')]
    public function testSupports(LabelSupportedElement $type, string $class): void
    {
        $options = new LabelOptions();
        $options->setSupportedElement($type);

        //Ensure that the given class is supported
        $this->assertTrue($this->service->supports($options, new $class()));

        //Ensure that another class is not supported
        $not_supported = new class() extends AbstractDBElement {
        };

        $this->assertFalse($this->service->supports($options, $not_supported));
    }

    public function testMmToPointsArray(): void
    {
        $this->assertSame(
            [0.0, 0.0, 141.7325, 85.0395],
            $this->service->mmToPointsArray(50.0, 30.0)
        );
    }

    public function testGenerateLabel(): void
    {
        $part = new Part();
        $options = new LabelOptions();
        $options->setLines('Test');
        $options->setSupportedElement(LabelSupportedElement::PART);

        //Test for a single passed element:
        $pdf = $this->service->generateLabel($options, $part);
        //Just a simple check if a PDF is returned
        $this->assertStringStartsWith('%PDF-', $pdf);

        //Test for an array of elements
        $pdf = $this->service->generateLabel($options, [$part, $part]);
        //Just a simple check if a PDF is returned
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(2, preg_match_all('~/Type\s*/Page\b~', $pdf));
    }

    public function testGenerateLabelSheet(): void
    {
        $part = new Part();
        $options = (new LabelOptions())
            ->setLines('Sheet test')
            ->setSupportedElement(LabelSupportedElement::PART)
            ->setWidth(50.0)
            ->setHeight(30.0)
            ->setSheetWidth(210.0)
            ->setSheetHeight(297.0)
            ->setSheetColumns(3)
            ->setSheetRows(8)
            ->setSheetGutterWidth(10.0)
            ->setSheetGutterHeight(4.0);

        $pdf = $this->service->generateLabel($options, [$part, $part], copies: 2, startSlot: 3);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function testRejectsSheetThatDoesNotFit(): void
    {
        $options = (new LabelOptions())
            ->setSupportedElement(LabelSupportedElement::PART)
            ->setSheetWidth(210.0)
            ->setSheetColumns(10);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->generateLabel($options, new Part());
    }

    public function testSheetPaginationHonorsStartingSlot(): void
    {
        $options = (new LabelOptions())
            ->setLines('Pagination test')
            ->setSupportedElement(LabelSupportedElement::PART)
            ->setWidth(50.0)
            ->setHeight(30.0)
            ->setSheetWidth(210.0)
            ->setSheetHeight(297.0)
            ->setSheetColumns(3)
            ->setSheetRows(8)
            ->setSheetGutterWidth(10.0)
            ->setSheetGutterHeight(4.0);

        // Slot 24 leaves one position on the first page; the other label must be on page two.
        $pdf = $this->service->generateLabel($options, new Part(), copies: 2, startSlot: 24);
        $this->assertSame(2, preg_match_all('~/Type\s*/Page\b~', $pdf));
    }

    public function testSheetUsesConfiguredSlotCoordinates(): void
    {
        $options = (new LabelOptions())
            ->setLines('Position test')
            ->setSupportedElement(LabelSupportedElement::PART)
            ->setWidth(50.0)
            ->setHeight(30.0)
            ->setSheetWidth(210.0)
            ->setSheetHeight(297.0)
            ->setSheetColumns(3)
            ->setSheetRows(8)
            ->setSheetMarginLeft(7.0)
            ->setSheetMarginTop(13.0)
            ->setSheetGutterWidth(10.0)
            ->setSheetGutterHeight(4.0);

        $htmlGenerator = self::getContainer()->get(LabelHTMLGenerator::class);
        $html = $htmlGenerator->getLabelHTML($options, [new Part()], startSlot: 5);

        // Slot five is column two, row two in the default three-column, row-major layout.
        $this->assertStringContainsString('left: 67mm; top: 47mm', $html);
    }

    public function testAvery5260PropertiesAreRepresentedAndRenderedInInches(): void
    {
        $options = (new LabelOptions())
            ->setUnit(LabelUnit::INCH)
            ->setPageSize(LabelPageSize::LETTER)
            ->setWidth(2.625)
            ->setHeight(1.0)
            ->setStickerCornerRadius(0.1)
            ->setSheetMarginLeft(0.1875)
            ->setSheetMarginTop(0.5)
            ->setSheetGutterWidth(0.125)
            ->setSheetGutterHeight(0.0)
            ->setSheetColumns(3)
            ->setSheetRows(10)
            ->setLines('Avery test')
            ->setSupportedElement(LabelSupportedElement::PART);

        $this->assertSame(LabelUnit::INCH, $options->getUnit());
        $this->assertSame(LabelPageSize::LETTER, $options->getPageSize());
        $this->assertSame(8.5, $options->getPaperWidth());
        $this->assertSame(11.0, $options->getPaperHeight());
        $this->assertSame(30, $options->getSheetCapacity());

        $htmlGenerator = self::getContainer()->get(LabelHTMLGenerator::class);
        $html = $htmlGenerator->getLabelHTML($options, [new Part()]);
        $this->assertStringContainsString('left: 0.1875in; top: 0.5in; width: 2.625in; height: 1in', $html);
        $this->assertStringContainsString('border-radius: 0.1in', $html);

        $pdf = $this->service->generateLabel($options, new Part());
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function testNamedPageSizesPresetButDoNotHideDimensions(): void
    {
        $options = (new LabelOptions())
            ->setUnit(LabelUnit::INCH)
            ->setPageSize(LabelPageSize::A4);

        $this->assertSame(LabelUnit::MILLIMETER, $options->getUnit());
        $this->assertSame(210.0, $options->getSheetWidth());
        $this->assertSame(297.0, $options->getSheetHeight());

        // Presets initialize the ordinary dimensions; users can still fine-tune them.
        $options->setSheetWidth(8.3)->setSheetHeight(11.6);
        $this->assertSame(8.3, $options->getPaperWidth());
        $this->assertSame(11.6, $options->getPaperHeight());
    }

    public function testNewOptionsUseLegacySingleLabelDefault(): void
    {
        $options = (new LabelOptions())->setWidth(62.0)->setHeight(31.0)->applyDefaultLabelSheet();

        $this->assertSame(LabelPageSize::CUSTOM, $options->getPageSize());
        $this->assertSame(LabelUnit::MILLIMETER, $options->getUnit());
        $this->assertSame(62.0, $options->getSheetWidth());
        $this->assertSame(31.0, $options->getSheetHeight());
        $this->assertSame(1, $options->getSheetColumns());
        $this->assertSame(1, $options->getSheetRows());
        $this->assertSame(0.0, $options->getSheetMarginLeft());
        $this->assertSame(0.0, $options->getSheetMarginTop());
    }

    public function testProfileWithoutStoredSheetUsesVirtualDefault(): void
    {
        $profile = new LabelProfile();
        $profile->getOptions()->setWidth(40.0)->setHeight(20.0);

        $options = $profile->getOptions();
        $this->assertNull($profile->getLabelSheet());
        $this->assertSame(LabelPageSize::CUSTOM, $options->getPageSize());
        $this->assertSame(40.0, $options->getSheetWidth());
        $this->assertSame(20.0, $options->getSheetHeight());
        $this->assertSame(1, $options->getSheetCapacity());
    }

    public function testPersistedSheetSuppliesLabelSize(): void
    {
        $sheet = (new LabelSheet())->setLabelWidth(69.0)->setLabelHeight(49.0);
        $profile = (new LabelProfile())->setLabelSheet($sheet);

        $this->assertSame(69.0, $profile->getOptions()->getWidth());
        $this->assertSame(49.0, $profile->getOptions()->getHeight());
    }

    public function testLabelSheetValidatesThatGridFits(): void
    {
        $sheet = (new LabelSheet())
            ->setName('A4 69 × 49')
            ->setWidth(210.0)->setHeight(297.0)
            ->setLabelWidth(69.0)->setLabelHeight(49.0)
            ->setColumns(3)->setRows(6)
            ->setMarginLeft(0.0)->setMarginTop(0.0)
            ->setGutterWidth(0.0)->setGutterHeight(0.0);
        $validator = self::getContainer()->get(ValidatorInterface::class);

        $this->assertCount(0, $validator->validate($sheet));

        $sheet->setMarginTop(1.0)->setGutterHeight(0.5);
        $violations = $validator->validate($sheet);
        $this->assertCount(1, $violations);
        $this->assertSame('columns', $violations[0]->getPropertyPath());
        $this->assertStringContainsString('207 × 297.5 mm', $violations[0]->getMessage());
    }

    public function testSettingPageSizeResetsUnitButUnitCanBeChangedAfterward(): void
    {
        $options = (new LabelOptions())->setUnit(LabelUnit::INCH);

        $options->setPageSize(LabelPageSize::CUSTOM);
        $this->assertSame(LabelUnit::MILLIMETER, $options->getUnit());

        $options->setPageSize(LabelPageSize::LETTER);
        $this->assertSame(LabelUnit::INCH, $options->getUnit());

        $options->setPageSize(LabelPageSize::A4);
        $this->assertSame(LabelUnit::MILLIMETER, $options->getUnit());

        $options->setUnit(LabelUnit::INCH);
        $this->assertSame(LabelUnit::INCH, $options->getUnit());
        $this->assertEqualsWithDelta(210 / 25.4, $options->getSheetWidth(), 0.00001);
        $this->assertEqualsWithDelta(297 / 25.4, $options->getSheetHeight(), 0.00001);
    }

    public function testFullSheetPreviewContainsOneLabelPerGridCell(): void
    {
        $options = (new LabelOptions())
            ->setLines('Preview test')
            ->setSupportedElement(LabelSupportedElement::PART)
            ->setSheetColumns(3)
            ->setSheetRows(10)
            ->setWidth(60.0)
            ->setHeight(25.0)
            ->setSheetMarginLeft(10.0)
            ->setSheetMarginTop(10.0)
            ->setSheetGutterWidth(5.0)
            ->setSheetGutterHeight(2.0);

        $htmlGenerator = self::getContainer()->get(LabelHTMLGenerator::class);
        $html = $htmlGenerator->getLabelHTML(
            $options,
            [new Part()],
            copies: $options->getSheetCapacity(),
        );

        $this->assertSame(30, substr_count($html, 'class="sheet-label"'));
        $this->assertSame(1, substr_count($html, 'class="sheet-page"'));
    }

    public function testLinearBarcodeIsPositionedInsideEverySheetLabel(): void
    {
        $options = (new LabelOptions())
            ->setBarcodeType(BarcodeType::CODE39)
            ->setLines('Barcode sheet test')
            ->setSupportedElement(LabelSupportedElement::PART)
            ->setWidth(60.0)
            ->setHeight(40.0)
            ->setSheetWidth(210.0)
            ->setSheetHeight(297.0)
            ->setSheetColumns(2)
            ->setSheetRows(2);

        $htmlGenerator = self::getContainer()->get(LabelHTMLGenerator::class);
        $html = $htmlGenerator->getLabelHTML($options, [new Part()], copies: 4);

        $this->assertSame(4, substr_count($html, '<img class="C39"'));
        $this->assertMatchesRegularExpression('/\.C39-container \{.*?position: absolute;/s', $html);
        $this->assertDoesNotMatchRegularExpression('/\.C39-container \{.*?position: fixed;/s', $html);

        $pdf = $this->service->generateLabel($options, new Part(), copies: 4);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
