<?php

declare(strict_types=1);

namespace App\Tests\Services\LabelSystem;

use App\Services\LabelSystem\LabelTableColumnWidthNormalizer;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;

final class LabelTableColumnWidthNormalizerTest extends TestCase
{
    private LabelTableColumnWidthNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new LabelTableColumnWidthNormalizer();
    }

    public function testCopiesColumnWidthsToFirstRowCells(): void
    {
        $document = $this->normalize('<table class="table layout-table ck-table-resized" style="width:100%"><colgroup>'
            .'<col style="width:10%"><col style="width:73.33%"><col style="width:16.67%">'
            .'</colgroup><tbody><tr><td></td><td style="color:red"></td><td></td></tr></tbody></table>');

        self::assertSame(['10%', '73.33%', '16.67%'], $this->cellAttributes($document, 'width'));
        self::assertSame(['10%', '73.33%', '16.67%'], $this->cellStyleWidths($document));
        self::assertStringContainsString('color:red', $this->cells($document)[1]->getAttribute('style'));
        self::assertStringContainsString('table-layout:fixed', $this->table($document)->getAttribute('style'));
    }

    public function testSupportsColumnAndCellSpans(): void
    {
        $document = $this->normalize('<table class="layout-table"><colgroup>'
            .'<col span="2" style="width:20%"><col style="width:60%">'
            .'</colgroup><tr><td colspan="2"></td><td></td></tr></table>');

        self::assertSame(['40%', '60%'], $this->cellAttributes($document, 'width'));
    }

    public function testNormalizesNestedTablesIndependently(): void
    {
        $document = $this->normalize('<table class="layout-table"><colgroup><col style="width:30%"><col style="width:70%"></colgroup>'
            .'<tr><td><table class="ck-table-resized"><colgroup><col style="width:25%"><col style="width:75%"></colgroup>'
            .'<tr><td></td><td></td></tr></table></td><td></td></tr></table>');

        $xpath = new DOMXPath($document);
        self::assertSame('30%', $xpath->query('((//table)[1]/tbody/tr | (//table)[1]/tr)[1]/td[1]')?->item(0)?->getAttribute('width'));
        self::assertSame('70%', $xpath->query('((//table)[1]/tbody/tr | (//table)[1]/tr)[1]/td[2]')?->item(0)?->getAttribute('width'));
        self::assertSame('25%', $xpath->query('((//table)[2]/tbody/tr | (//table)[2]/tr)[1]/td[1]')?->item(0)?->getAttribute('width'));
        self::assertSame('75%', $xpath->query('((//table)[2]/tbody/tr | (//table)[2]/tr)[1]/td[2]')?->item(0)?->getAttribute('width'));
    }

    public function testLeavesUnrelatedAndMalformedTablesAlone(): void
    {
        $html = '<table><colgroup><col style="width:10%"></colgroup><tr><td></td></tr></table>';
        self::assertSame($html, $this->normalizer->normalize($html));

        $document = $this->normalize('<table class="layout-table"><colgroup><col style="width:auto"></colgroup><tr><td></td></tr></table>');
        self::assertSame('', $this->cells($document)[0]->getAttribute('width'));
    }

    private function normalize(string $html): DOMDocument
    {
        $result = $this->normalizer->normalize('<!DOCTYPE html><html><body>'.$html.'</body></html>');
        $document = new DOMDocument();
        self::assertTrue($document->loadHTML($result));
        return $document;
    }

    /** @return list<DOMElement> */
    private function cells(DOMDocument $document): array
    {
        $nodes = (new DOMXPath($document))->query('((//table)[1]/tbody/tr | (//table)[1]/tr)[1]/td');
        return array_values(array_filter(iterator_to_array($nodes ?: []), static fn($node): bool => $node instanceof DOMElement));
    }

    /** @return list<string> */
    private function cellAttributes(DOMDocument $document, string $attribute): array
    {
        return array_map(static fn(DOMElement $cell): string => $cell->getAttribute($attribute), $this->cells($document));
    }

    /** @return list<string> */
    private function cellStyleWidths(DOMDocument $document): array
    {
        return array_map(static function (DOMElement $cell): string {
            self::assertSame(1, preg_match('/(?:^|;)width:([^;]+)/', $cell->getAttribute('style'), $matches));
            return $matches[1];
        }, $this->cells($document));
    }

    private function table(DOMDocument $document): DOMElement
    {
        $table = (new DOMXPath($document))->query('//table[1]')?->item(0);
        self::assertInstanceOf(DOMElement::class, $table);
        return $table;
    }
}
