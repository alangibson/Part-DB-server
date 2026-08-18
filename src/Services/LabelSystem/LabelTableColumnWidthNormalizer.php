<?php

declare(strict_types=1);

namespace App\Services\LabelSystem;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Copies CKEditor's colgroup widths to table cells for Dompdf.
 *
 * Dompdf removes colgroup elements from its frame tree, but respects widths on
 * cells in the first row of a fixed-layout table.
 */
final class LabelTableColumnWidthNormalizer
{
    public function normalize(string $html): string
    {
        if ($html === '' || stripos($html, '<colgroup') === false) {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return $html;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $tables = $xpath->query(
            '//table[contains(concat(" ", normalize-space(@class), " "), " layout-table ")'
            .' or contains(concat(" ", normalize-space(@class), " "), " ck-table-resized ")]'
        );
        if ($tables === false) {
            return $html;
        }
        if ($tables->length === 0) {
            return $html;
        }

        foreach ($tables as $table) {
            if ($table instanceof DOMElement) {
                $this->normalizeTable($table, $xpath);
            }
        }

        return $document->saveHTML() ?: $html;
    }

    private function normalizeTable(DOMElement $table, DOMXPath $xpath): void
    {
        $column_group = $xpath->query('./colgroup[1]', $table)?->item(0);
        if (!$column_group instanceof DOMElement) {
            return;
        }

        $widths = [];
        foreach ($xpath->query('./col', $column_group) ?: [] as $column) {
            if (!$column instanceof DOMElement) {
                continue;
            }

            $width = $this->getStyleProperty($column, 'width');
            $span = max(1, (int) $column->getAttribute('span'));
            for ($index = 0; $index < $span; ++$index) {
                $widths[] = $this->isSupportedWidth($width) ? $width : null;
            }
        }

        if ($widths === [] || array_filter($widths, static fn(?string $width): bool => $width !== null) === []) {
            return;
        }

        $first_row = $xpath->query('./thead/tr[1] | ./tbody/tr[1] | ./tfoot/tr[1] | ./tr[1]', $table)?->item(0);
        if (!$first_row instanceof DOMElement) {
            return;
        }

        $column_index = 0;
        foreach ($xpath->query('./th | ./td', $first_row) ?: [] as $cell) {
            if (!$cell instanceof DOMElement) {
                continue;
            }

            $span = max(1, (int) $cell->getAttribute('colspan'));
            $cell_width = $this->sumWidths(array_slice($widths, $column_index, $span));
            if ($cell_width !== null) {
                $this->setStyleProperty($cell, 'width', $cell_width);
                $cell->setAttribute('width', $cell_width);
            }
            $column_index += $span;
        }

        $this->setStyleProperty($table, 'table-layout', 'fixed');
        // CKEditor can persist resized tables wider than their editing area
        // (for example width:108.46%). An inline width wins over the label
        // stylesheet and pushes the final column beyond the printable label.
        // Labels always use the complete available content width, while the
        // colgroup percentages retain the user's relative column sizing.
        $this->setStyleProperty($table, 'width', '100%');
        $table->setAttribute('width', '100%');
    }

    private function getStyleProperty(DOMElement $element, string $property): ?string
    {
        foreach (explode(';', $element->getAttribute('style')) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, null);
            if ($value !== null && strtolower(trim($name)) === $property) {
                return trim($value);
            }
        }

        return null;
    }

    private function setStyleProperty(DOMElement $element, string $property, string $value): void
    {
        $declarations = [];
        foreach (explode(';', $element->getAttribute('style')) as $declaration) {
            [$name, $existing_value] = array_pad(explode(':', $declaration, 2), 2, null);
            if ($existing_value !== null && strtolower(trim($name)) !== $property) {
                $declarations[] = trim($name).':'.trim($existing_value);
            }
        }
        $declarations[] = $property.':'.$value;
        $element->setAttribute('style', implode(';', $declarations).';');
    }

    private function isSupportedWidth(?string $width): bool
    {
        return $width !== null
            && preg_match('/^(?:\d+(?:\.\d+)?|\.\d+)(?:%|px|pt)$/i', $width) === 1;
    }

    /** @param list<?string> $widths */
    private function sumWidths(array $widths): ?string
    {
        if ($widths === [] || in_array(null, $widths, true)) {
            return null;
        }

        $unit = null;
        $sum = 0.0;
        foreach ($widths as $width) {
            if (preg_match('/^((?:\d+(?:\.\d+)?|\.\d+))(%|px|pt)$/i', (string) $width, $matches) !== 1) {
                return null;
            }
            $unit ??= strtolower($matches[2]);
            if ($unit !== strtolower($matches[2])) {
                return null;
            }
            $sum += (float) $matches[1];
        }

        return rtrim(rtrim(number_format($sum, 6, '.', ''), '0'), '.').$unit;
    }
}
