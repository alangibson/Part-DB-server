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
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Entity\LabelSystem;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Embeddable]
class LabelOptions
{
    #[Groups(["extended", "full", "import"])]
    protected LabelUnit $unit = LabelUnit::MILLIMETER;

    #[Groups(["extended", "full", "import"])]
    protected LabelPageSize $page_size = LabelPageSize::A4;

    /**
     * @var float The page size of the label in mm
     */
    #[Assert\Positive]
    #[ORM\Column(type: Types::FLOAT)]
    #[Groups(["extended", "full", "import"])]
    protected float $width = 50.0;

    /**
     * @var float The page size of the label in mm
     */
    #[Assert\Positive]
    #[ORM\Column(type: Types::FLOAT)]
    #[Groups(["extended", "full", "import"])]
    protected float $height = 30.0;

    #[Assert\Positive]
    #[Groups(["extended", "full", "import"])]
    protected float $sheet_width = 210.0;

    #[Assert\Positive]
    #[Groups(["extended", "full", "import"])]
    protected float $sheet_height = 297.0;

    #[Assert\PositiveOrZero]
    #[Groups(["extended", "full", "import"])]
    protected float $sheet_margin_left = 0.0;

    #[Assert\PositiveOrZero]
    #[Groups(["extended", "full", "import"])]
    protected float $sheet_margin_top = 0.0;

    #[Assert\PositiveOrZero]
    #[Groups(["extended", "full", "import"])]
    protected float $sheet_gutter_width = 0.0;

    #[Assert\PositiveOrZero]
    #[Groups(["extended", "full", "import"])]
    protected float $sheet_gutter_height = 0.0;

    #[Assert\Positive]
    #[Groups(["extended", "full", "import"])]
    protected int $sheet_columns = 1;

    #[Assert\Positive]
    #[Groups(["extended", "full", "import"])]
    protected int $sheet_rows = 1;

    #[Assert\PositiveOrZero]
    #[Groups(["extended", "full", "import"])]
    protected float $sticker_corner_radius = 0.0;

    /**
     * @var BarcodeType The type of the barcode that should be used in the label (e.g. 'qr')
     */
    #[ORM\Column(type: Types::STRING, enumType: BarcodeType::class)]
    #[Groups(["extended", "full", "import"])]
    protected BarcodeType $barcode_type = BarcodeType::NONE;

    /**
     * @var LabelPictureType What image should be shown along the label
     */
    #[ORM\Column(type: Types::STRING, enumType: LabelPictureType::class)]
    #[Groups(["extended", "full", "import"])]
    protected LabelPictureType $picture_type = LabelPictureType::NONE;

    #[ORM\Column(type: Types::STRING, enumType: LabelSupportedElement::class)]
    #[Groups(["extended", "full", "import"])]
    protected LabelSupportedElement $supported_element = LabelSupportedElement::PART;

    /**
     * @var string any additional CSS for the label
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Groups([ "full", "import"])]
    protected string $additional_css = '';

    /** @var LabelProcessMode The mode that will be used to interpret the lines
     */
    #[ORM\Column(name: 'lines_mode', type: Types::STRING, enumType: LabelProcessMode::class)]
    #[Groups(["extended", "full", "import"])]
    protected LabelProcessMode $process_mode = LabelProcessMode::PLACEHOLDER;

    /**
     * @var string
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Groups(["extended", "full", "import"])]
    protected string $lines = '';

    public function __construct()
    {
        $this->applyDefaultLabelSheet();
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function applyLabelSheet(LabelSheet $sheet): self
    {
        $this->width = $sheet->getLabelWidth();
        $this->height = $sheet->getLabelHeight();
        $this->unit = $sheet->getUnit();
        $this->page_size = $sheet->getPageSize();
        $this->sheet_width = $sheet->getWidth();
        $this->sheet_height = $sheet->getHeight();
        $this->sheet_margin_left = $sheet->getMarginLeft();
        $this->sheet_margin_top = $sheet->getMarginTop();
        $this->sheet_gutter_width = $sheet->getGutterWidth();
        $this->sheet_gutter_height = $sheet->getGutterHeight();
        $this->sheet_columns = $sheet->getColumns();
        $this->sheet_rows = $sheet->getRows();
        $this->sticker_corner_radius = $sheet->getCornerRadius();

        return $this;
    }

    public function applyDefaultLabelSheet(): self
    {
        $this->unit = LabelUnit::MILLIMETER;
        $this->page_size = LabelPageSize::CUSTOM;
        $this->sheet_width = $this->width;
        $this->sheet_height = $this->height;
        $this->sheet_margin_left = 0.0;
        $this->sheet_margin_top = 0.0;
        $this->sheet_gutter_width = 0.0;
        $this->sheet_gutter_height = 0.0;
        $this->sheet_columns = 1;
        $this->sheet_rows = 1;
        $this->sticker_corner_radius = 0.0;

        return $this;
    }

    public function getUnit(): LabelUnit
    {
        return $this->unit;
    }

    public function setUnit(LabelUnit $value): self
    {
        $this->unit = $value;
        $this->applyPageSizePreset();
        return $this;
    }

    public function getPageSize(): LabelPageSize
    {
        return $this->page_size;
    }

    public function setPageSize(LabelPageSize $value): self
    {
        $this->page_size = $value;
        $this->unit = match ($value) {
            LabelPageSize::A4 => LabelUnit::MILLIMETER,
            LabelPageSize::LETTER => LabelUnit::INCH,
            LabelPageSize::CUSTOM => LabelUnit::MILLIMETER,
        };
        $this->applyPageSizePreset();
        return $this;
    }

    public function getCssUnit(): string
    {
        return $this->unit->cssUnit();
    }

    public function toMillimeters(float $value): float
    {
        return $this->unit->toMillimeters($value);
    }

    public function setWidth(float $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function setHeight(float $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getSheetWidth(): float
    {
        return $this->sheet_width;
    }

    public function getPaperWidth(): float
    {
        return $this->sheet_width;
    }

    public function setSheetWidth(float $value): self
    {
        $this->sheet_width = $value;
        return $this;
    }

    public function getSheetHeight(): float
    {
        return $this->sheet_height;
    }

    public function getPaperHeight(): float
    {
        return $this->sheet_height;
    }

    public function setSheetHeight(float $value): self
    {
        $this->sheet_height = $value;
        return $this;
    }

    public function getSheetMarginLeft(): float
    {
        return $this->sheet_margin_left;
    }

    public function setSheetMarginLeft(float $value): self
    {
        $this->sheet_margin_left = $value;
        return $this;
    }

    public function getSheetMarginTop(): float
    {
        return $this->sheet_margin_top;
    }

    public function setSheetMarginTop(float $value): self
    {
        $this->sheet_margin_top = $value;
        return $this;
    }

    public function getSheetGutterWidth(): float
    {
        return $this->sheet_gutter_width;
    }

    public function setSheetGutterWidth(float $value): self
    {
        $this->sheet_gutter_width = $value;
        return $this;
    }

    public function getSheetGutterHeight(): float
    {
        return $this->sheet_gutter_height;
    }

    public function setSheetGutterHeight(float $value): self
    {
        $this->sheet_gutter_height = $value;
        return $this;
    }

    public function getSheetColumns(): int
    {
        return $this->sheet_columns;
    }

    public function setSheetColumns(int $value): self
    {
        $this->sheet_columns = $value;
        return $this;
    }

    public function getSheetRows(): int
    {
        return $this->sheet_rows;
    }

    public function setSheetRows(int $value): self
    {
        $this->sheet_rows = $value;
        return $this;
    }

    public function getSheetCapacity(): int
    {
        return $this->sheet_columns * $this->sheet_rows;
    }

    public function getStickerCornerRadius(): float
    {
        return $this->sticker_corner_radius;
    }

    public function setStickerCornerRadius(float $value): self
    {
        $this->sticker_corner_radius = $value;
        return $this;
    }

    private function applyPageSizePreset(): void
    {
        match ($this->page_size) {
            LabelPageSize::CUSTOM => null,
            LabelPageSize::A4 => [$this->sheet_width, $this->sheet_height] = [
                $this->unit->fromMillimeters(210.0),
                $this->unit->fromMillimeters(297.0),
            ],
            LabelPageSize::LETTER => [$this->sheet_width, $this->sheet_height] = [
                $this->unit->fromInches(8.5),
                $this->unit->fromInches(11.0),
            ],
        };
    }

    public function getBarcodeType(): BarcodeType
    {
        return $this->barcode_type;
    }

    public function setBarcodeType(BarcodeType $barcode_type): self
    {
        $this->barcode_type = $barcode_type;

        return $this;
    }

    public function getPictureType(): LabelPictureType
    {
        return $this->picture_type;
    }

    public function setPictureType(LabelPictureType $picture_type): self
    {
        $this->picture_type = $picture_type;

        return $this;
    }

    public function getSupportedElement(): LabelSupportedElement
    {
        return $this->supported_element;
    }

    public function setSupportedElement(LabelSupportedElement $supported_element): self
    {
        $this->supported_element = $supported_element;

        return $this;
    }

    public function getLines(): string
    {
        return $this->lines;
    }

    public function setLines(string $lines): self
    {
        $this->lines = $lines;

        return $this;
    }

    /**
     * Gets additional CSS (it will simply be attended to base CSS).
     */
    public function getAdditionalCss(): string
    {
        return $this->additional_css;
    }

    public function setAdditionalCss(string $additional_css): self
    {
        $this->additional_css = $additional_css;

        return $this;
    }

    public function getProcessMode(): LabelProcessMode
    {
        return $this->process_mode;
    }

    public function setProcessMode(LabelProcessMode $process_mode): self
    {
        $this->process_mode = $process_mode;

        return $this;
    }
}
