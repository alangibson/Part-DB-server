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

namespace App\Services\LabelSystem\Labelle;

use App\Entity\LabelSystem\BarcodeType;
use App\Exceptions\LabelleConversionException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Converts the intentionally limited HTML subset produced by the label editor into Labelle batch input.
 */
final class LabelleBatchConverter
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /** @var string[] */
    private array $errors = [];

    /** @var string[] */
    private array $warnings = [];

    /**
     * @return array<int, array{type: 'text', lines: string[]}|array{type: 'qr'|'barcode', content: string, barcode_type?: string}>
     */
    private function parse(string $html): array
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><div id="partdb-labelle-root">'.$html.'</div>',
                \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            throw new LabelleConversionException([$this->translator->trans('labelle.error.parse_html')]);
        }

        $root = $document->getElementById('partdb-labelle-root');
        if (!$root instanceof \DOMElement) {
            throw new LabelleConversionException([$this->translator->trans('labelle.error.parse_html')]);
        }

        $xpath = new \DOMXPath($document);
        if ($xpath->query('.//*[@style or self::font or self::b or self::strong or self::i or self::em or self::u]', $root)?->length > 0) {
            $this->warn($this->translator->trans('labelle.warning.rich_text'));
        }

        $blocks = [];
        $this->parseFlow($root, $blocks);

        return $blocks;
    }

    public function convert(string $html, BarcodeType $mainBarcodeType = BarcodeType::NONE, ?string $mainBarcodeContent = null): LabelleConversionResult
    {
        $this->errors = [];
        $this->warnings = [];

        $blocks = [];
        if ($mainBarcodeType !== BarcodeType::NONE && $mainBarcodeContent !== null) {
            $this->appendCodeBlock($blocks, $mainBarcodeType, $mainBarcodeContent);
        }

        array_push($blocks, ...$this->parse($html));

        if ($blocks === []) {
            $this->errors[] = $this->translator->trans('labelle.error.no_content');
        }

        if ($this->errors !== []) {
            throw new LabelleConversionException($this->errors);
        }

        $lines = ['LABELLE-LABEL-SPEC-VERSION:1'];
        foreach ($blocks as $block) {
            if ($block['type'] === 'text') {
                $textLines = $block['lines'];
                if ($textLines === []) {
                    continue;
                }

                $lines[] = 'TEXT:'.$this->sanitizeValue(array_shift($textLines));
                foreach ($textLines as $textLine) {
                    $lines[] = 'NEWLINE:'.$this->sanitizeValue($textLine);
                }
                continue;
            }

            if ($block['type'] === 'qr') {
                $lines[] = 'QR:'.$this->sanitizeValue($block['content']);
                continue;
            }

            $lines[] = 'BARCODE#'.$block['barcode_type'].':'.$this->sanitizeValue($block['content']);
        }

        return new LabelleConversionResult(implode("\n", $lines)."\n", $this->warnings);
    }

    /**
     * @param array<int, array{type: 'text', lines: string[]}|array{type: 'qr'|'barcode', content: string, barcode_type?: string}> $blocks
     */
    private function parseFlow(\DOMNode $container, array &$blocks): void
    {
        $looseLines = [''];

        foreach ($container->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $looseLines[array_key_last($looseLines)] .= $child->nodeValue ?? '';
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if ($tag === 'table') {
                $this->flushText($looseLines, $blocks);
                $this->parseTable($child, $blocks);
                $looseLines = [''];
                continue;
            }

            if ($tag === 'img') {
                $this->flushText($looseLines, $blocks);
                $this->parseImage($child, $blocks);
                $looseLines = [''];
                continue;
            }

            if ($tag === 'br') {
                $looseLines[] = '';
                continue;
            }

            if ($this->isBlockElement($tag)) {
                $this->flushText($looseLines, $blocks);
                $looseLines = [''];

                if ($tag === 'figure' || $this->containsTable($child)) {
                    $this->parseFlow($child, $blocks);
                    continue;
                }

                $blockLines = [''];
                $inlineBlocks = [];
                $this->parseInline($child, $blockLines, $inlineBlocks);
                $this->flushText($blockLines, $inlineBlocks);
                array_push($blocks, ...$inlineBlocks);
                continue;
            }

            $inlineBlocks = [];
            $this->parseInline($child, $looseLines, $inlineBlocks);
            if ($inlineBlocks !== []) {
                $this->flushText($looseLines, $inlineBlocks);
                array_push($blocks, ...$inlineBlocks);
                $looseLines = [''];
            }
        }

        $this->flushText($looseLines, $blocks);
    }

    /**
     * @param string[] $lines
     * @param array<int, array{type: 'text', lines: string[]}|array{type: 'qr'|'barcode', content: string, barcode_type?: string}> $blocks
     */
    private function parseInline(\DOMNode $node, array &$lines, array &$blocks): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $lines[array_key_last($lines)] .= $child->nodeValue ?? '';
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if ($tag === 'br') {
                $lines[] = '';
                continue;
            }

            if ($tag === 'img') {
                $this->flushText($lines, $blocks);
                $this->parseImage($child, $blocks);
                $lines = [''];
                continue;
            }

            if ($tag === 'table') {
                $this->errors[] = $this->translator->trans('labelle.error.nested_content_table');
                continue;
            }

            if ($this->isBlockElement($tag) && trim($lines[array_key_last($lines)]) !== '') {
                $lines[] = '';
            }
            $this->parseInline($child, $lines, $blocks);
            if ($this->isBlockElement($tag)) {
                $lines[] = '';
            }
        }
    }

    /**
     * @param array<int, array{type: 'text', lines: string[]}|array{type: 'qr'|'barcode', content: string, barcode_type?: string}> $blocks
     */
    private function parseTable(\DOMElement $table, array &$blocks): void
    {
        if ($this->containsNestedTable($table)) {
            $this->errors[] = $this->translator->trans('labelle.error.nested_table');

            return;
        }

        $rows = $this->getTableRows($table);
        if ($rows === []) {
            return;
        }

        $grid = [];
        $maxColumns = 0;
        foreach ($rows as $rowIndex => $row) {
            $columnIndex = 0;
            foreach ($row->childNodes as $cell) {
                if (!$cell instanceof \DOMElement || !in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                    continue;
                }

                while (array_key_exists($columnIndex, $grid[$rowIndex] ?? [])) {
                    ++$columnIndex;
                }

                if ($cell->getElementsByTagName('img')->length > 0) {
                    $this->errors[] = $this->translator->trans('labelle.error.table_visual');

                    return;
                }

                $colspan = max(1, (int) ($cell->getAttribute('colspan') ?: 1));
                $rowspan = max(1, (int) ($cell->getAttribute('rowspan') ?: 1));
                if ($colspan > 1 || $rowspan > 1) {
                    $this->warn($this->translator->trans('labelle.warning.merged_cells'));
                }

                $value = $this->normalizeCellText($cell);
                for ($rowOffset = 0; $rowOffset < $rowspan; ++$rowOffset) {
                    for ($columnOffset = 0; $columnOffset < $colspan; ++$columnOffset) {
                        $grid[$rowIndex + $rowOffset][$columnIndex + $columnOffset] = ($rowOffset === 0 && $columnOffset === 0) ? $value : '';
                    }
                }

                $columnIndex += $colspan;
                $maxColumns = max($maxColumns, $columnIndex);
            }
        }

        for ($column = 0; $column < $maxColumns; ++$column) {
            $columnLines = [];
            for ($row = 0, $rowCount = count($rows); $row < $rowCount; ++$row) {
                $columnLines[] = $grid[$row][$column] ?? '';
            }
            $blocks[] = ['type' => 'text', 'lines' => $columnLines];
        }

        $this->warn($this->translator->trans('labelle.warning.table_style'));
    }

    /**
     * @param array<int, array{type: 'text', lines: string[]}|array{type: 'qr'|'barcode', content: string, barcode_type?: string}> $blocks
     */
    private function parseImage(\DOMElement $image, array &$blocks): void
    {
        $type = $image->getAttribute('data-label-code-type');
        $content = $image->getAttribute('data-label-code-content');
        if ($type !== '' && $content !== '') {
            $barcodeType = BarcodeType::tryFrom($type);
            if ($barcodeType === null) {
                $this->errors[] = $this->translator->trans('labelle.error.unsupported_barcode', ['%type%' => $type]);

                return;
            }
            $this->appendCodeBlock($blocks, $barcodeType, $content);

            return;
        }

        $alternativeText = trim($image->getAttribute('alt'));
        if ($alternativeText !== '') {
            $blocks[] = ['type' => 'text', 'lines' => [$alternativeText]];
            $this->warn($this->translator->trans('labelle.warning.image_alt'));

            return;
        }

        $this->errors[] = $this->translator->trans('labelle.error.image_no_alt');
    }

    /**
     * @param array<int, array{type: 'text', lines: string[]}|array{type: 'qr'|'barcode', content: string, barcode_type?: string}> $blocks
     */
    private function appendCodeBlock(array &$blocks, BarcodeType $type, string $content): void
    {
        if (str_contains($content, "\n") || str_contains($content, "\r")) {
            $this->errors[] = $this->translator->trans('labelle.error.multiline_code');

            return;
        }

        match ($type) {
            BarcodeType::QR => $blocks[] = ['type' => 'qr', 'content' => $content],
            BarcodeType::CODE128 => $blocks[] = ['type' => 'barcode', 'barcode_type' => 'CODE128', 'content' => $content],
            BarcodeType::CODE39 => $blocks[] = ['type' => 'barcode', 'barcode_type' => 'CODE39', 'content' => $content],
            BarcodeType::NONE => null,
            BarcodeType::CODE93, BarcodeType::DATAMATRIX => $this->errors[] = $this->translator->trans('labelle.error.unsupported_barcode', ['%type%' => $type->value]),
        };
    }

    /** @return \DOMElement[] */
    private function getTableRows(\DOMElement $table): array
    {
        $rows = [];
        foreach ($table->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if ($tag === 'tr') {
                $rows[] = $child;
                continue;
            }
            if (in_array($tag, ['thead', 'tbody', 'tfoot'], true)) {
                foreach ($child->childNodes as $row) {
                    if ($row instanceof \DOMElement && strtolower($row->tagName) === 'tr') {
                        $rows[] = $row;
                    }
                }
            }
        }

        return $rows;
    }

    private function containsNestedTable(\DOMElement $table): bool
    {
        foreach ($table->getElementsByTagName('table') as $candidate) {
            if (!$candidate->isSameNode($table)) {
                return true;
            }
        }

        return false;
    }

    private function containsTable(\DOMElement $element): bool
    {
        return $element->getElementsByTagName('table')->length > 0;
    }

    private function normalizeCellText(\DOMElement $cell): string
    {
        $text = $cell->textContent;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param string[] $lines
     * @param array<int, array{type: 'text', lines: string[]}|array{type: 'qr'|'barcode', content: string, barcode_type?: string}> $blocks
     */
    private function flushText(array &$lines, array &$blocks): void
    {
        $expanded = [];
        foreach ($lines as $line) {
            $line = str_replace(["\r\n", "\r"], "\n", $line);
            array_push($expanded, ...explode("\n", $line));
        }

        $normalized = array_map(static function (string $line): string {
            $line = preg_replace('/[\t ]+/u', ' ', $line) ?? $line;

            return trim($line);
        }, $expanded);

        while ($normalized !== [] && $normalized[0] === '') {
            array_shift($normalized);
        }
        while ($normalized !== [] && $normalized[array_key_last($normalized)] === '') {
            array_pop($normalized);
        }

        if ($normalized !== []) {
            $blocks[] = ['type' => 'text', 'lines' => $normalized];
        }
        $lines = [''];
    }

    private function sanitizeValue(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        if (str_contains($value, "\n")) {
            throw new LabelleConversionException([$this->translator->trans('labelle.error.unexpected_linebreak')]);
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
            throw new LabelleConversionException([$this->translator->trans('labelle.error.control_chars')]);
        }

        return $value;
    }

    private function isBlockElement(string $tag): bool
    {
        return in_array($tag, ['address', 'article', 'aside', 'blockquote', 'div', 'figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'ul'], true);
    }

    private function warn(string $warning): void
    {
        if (!in_array($warning, $this->warnings, true)) {
            $this->warnings[] = $warning;
        }
    }
}
