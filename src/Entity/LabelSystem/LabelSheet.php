<?php

declare(strict_types=1);

namespace App\Entity\LabelSystem;

use App\Entity\Base\AbstractNamedDBElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'label_sheets')]
class LabelSheet extends AbstractNamedDBElement
{
    #[ORM\Column(type: Types::STRING, enumType: LabelUnit::class)]
    protected LabelUnit $unit = LabelUnit::MILLIMETER;

    #[ORM\Column(type: Types::STRING, enumType: LabelPageSize::class)]
    protected LabelPageSize $page_size = LabelPageSize::A4;

    #[Assert\Positive]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $width = 210.0;

    #[Assert\Positive]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $height = 297.0;

    #[Assert\Positive]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $label_width = 50.0;

    #[Assert\Positive]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $label_height = 30.0;

    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $margin_left = 0.0;

    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $margin_top = 0.0;

    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $gutter_width = 0.0;

    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $gutter_height = 0.0;

    #[Assert\Positive]
    #[ORM\Column(type: Types::INTEGER)]
    protected int $columns = 1;

    #[Assert\Positive]
    #[ORM\Column(type: Types::INTEGER)]
    protected int $rows = 1;

    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $corner_radius = 0.0;

    public function getUnit(): LabelUnit { return $this->unit; }
    public function setUnit(LabelUnit $unit): self { $this->unit = $unit; $this->applyPageSizePreset(); return $this; }
    public function getPageSize(): LabelPageSize { return $this->page_size; }
    public function setPageSize(LabelPageSize $pageSize): self
    {
        $this->page_size = $pageSize;
        $this->unit = match ($pageSize) {
            LabelPageSize::A4 => LabelUnit::MILLIMETER,
            LabelPageSize::LETTER => LabelUnit::INCH,
            LabelPageSize::CUSTOM => LabelUnit::MILLIMETER,
        };
        $this->applyPageSizePreset();
        return $this;
    }
    public function getWidth(): float { return $this->width; }
    public function setWidth(float $width): self { $this->width = $width; return $this; }
    public function getHeight(): float { return $this->height; }
    public function setHeight(float $height): self { $this->height = $height; return $this; }
    public function getLabelWidth(): float { return $this->label_width; }
    public function setLabelWidth(float $labelWidth): self { $this->label_width = $labelWidth; return $this; }
    public function getLabelHeight(): float { return $this->label_height; }
    public function setLabelHeight(float $labelHeight): self { $this->label_height = $labelHeight; return $this; }
    public function getMarginLeft(): float { return $this->margin_left; }
    public function setMarginLeft(float $marginLeft): self { $this->margin_left = $marginLeft; return $this; }
    public function getMarginTop(): float { return $this->margin_top; }
    public function setMarginTop(float $marginTop): self { $this->margin_top = $marginTop; return $this; }
    public function getGutterWidth(): float { return $this->gutter_width; }
    public function setGutterWidth(float $gutterWidth): self { $this->gutter_width = $gutterWidth; return $this; }
    public function getGutterHeight(): float { return $this->gutter_height; }
    public function setGutterHeight(float $gutterHeight): self { $this->gutter_height = $gutterHeight; return $this; }
    public function getColumns(): int { return $this->columns; }
    public function setColumns(int $columns): self { $this->columns = $columns; return $this; }
    public function getRows(): int { return $this->rows; }
    public function setRows(int $rows): self { $this->rows = $rows; return $this; }
    public function getCornerRadius(): float { return $this->corner_radius; }
    public function setCornerRadius(float $cornerRadius): self { $this->corner_radius = $cornerRadius; return $this; }

    #[Assert\Callback]
    public function validateGridFitsOnSheet(ExecutionContextInterface $context): void
    {
        $requiredWidth = $this->margin_left
            + ($this->columns * $this->label_width)
            + (($this->columns - 1) * $this->gutter_width);
        $requiredHeight = $this->margin_top
            + ($this->rows * $this->label_height)
            + (($this->rows - 1) * $this->gutter_height);

        if ($requiredWidth > $this->width + 0.01 || $requiredHeight > $this->height + 0.01) {
            $context->buildViolation('The configured label grid does not fit on the sheet. It requires {{ required_width }} × {{ required_height }} {{ unit }}, but the sheet is {{ sheet_width }} × {{ sheet_height }} {{ unit }}.')
                ->setParameters([
                    '{{ required_width }}' => (string) round($requiredWidth, 4),
                    '{{ required_height }}' => (string) round($requiredHeight, 4),
                    '{{ sheet_width }}' => (string) $this->width,
                    '{{ sheet_height }}' => (string) $this->height,
                    '{{ unit }}' => $this->unit->value,
                ])
                ->atPath('columns')
                ->addViolation();
        }
    }

    private function applyPageSizePreset(): void
    {
        match ($this->page_size) {
            LabelPageSize::CUSTOM => null,
            LabelPageSize::A4 => [$this->width, $this->height] = [$this->unit->fromMillimeters(210.0), $this->unit->fromMillimeters(297.0)],
            LabelPageSize::LETTER => [$this->width, $this->height] = [$this->unit->fromInches(8.5), $this->unit->fromInches(11.0)],
        };
    }
}
