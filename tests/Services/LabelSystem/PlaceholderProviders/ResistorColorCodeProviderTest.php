<?php

declare(strict_types=1);

namespace App\Tests\Services\LabelSystem\PlaceholderProviders;

use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Part;
use App\Services\LabelSystem\PlaceholderProviders\ParameterProvider;
use App\Services\LabelSystem\PlaceholderProviders\ResistorColorCodeProvider;
use PHPUnit\Framework\TestCase;

final class ResistorColorCodeProviderTest extends TestCase
{
    private ResistorColorCodeProvider $provider;
    private Part $part;

    protected function setUp(): void
    {
        $this->provider = new ResistorColorCodeProvider(new ParameterProvider());
        $this->part = new Part();

        $resistance = new PartParameter();
        $resistance->setName('Resistance');
        $resistance->setValueTypical(4.7);
        $resistance->setUnit('kΩ');
        $this->part->addParameter($resistance);

        $tolerance = new PartParameter();
        $tolerance->setName('Tolerance');
        $tolerance->setValueTypical(1.0);
        $tolerance->setUnit('%');
        $this->part->addParameter($tolerance);
    }

    public function testFourBandWithDefaultTolerance(): void
    {
        $html = $this->provider->replace("[[RESISTOR_4_BAND(PARAMETERS['Resistance'])]]", $this->part);
        self::assertIsString($html);
        self::assertStringContainsString('4-band resistor, 4700 ohms, 5% tolerance', $html);
        self::assertStringContainsString('class="resistor-color-code"', $html);
        self::assertStringContainsString(
            'style="display:block;max-width:100%;max-height:100%"',
            $html
        );

        $svg = $this->extractSvg($html);
        self::assertStringNotContainsString('<line', $svg);
        self::assertStringNotContainsString(' rx=', $svg);
        self::assertStringContainsString('fill="none"', $svg);
        self::assertStringNotContainsString('#e8d5a7', $svg);
        self::assertStringContainsString('width="120" height="44" viewBox="0 0 120 44"', $svg);
        self::assertStringContainsString('preserveAspectRatio="none"', $svg);
        // Keep the centered stroke entirely inside the SVG viewport so neither edge is clipped.
        self::assertStringContainsString('<rect x="1" y="1" width="118" height="42"', $svg);
        self::assertStringContainsString('fill="#fcbf49"', $svg); // 4
        self::assertStringContainsString('fill="#7b2cbf"', $svg); // 7
        self::assertStringContainsString('fill="#d62828"', $svg); // x100
        self::assertStringContainsString('fill="#d4af37"', $svg); // 5%
    }

    public function testFiveBandWithParameterTolerance(): void
    {
        $html = $this->provider->replace(
            "[[RESISTOR_5_BAND(PARAMETERS['Resistance'], PARAMETERS['Tolerance'])]]",
            $this->part
        );
        self::assertIsString($html);
        self::assertStringContainsString('5-band resistor, 4700 ohms, 1% tolerance', $html);

        $svg = $this->extractSvg($html);
        self::assertStringContainsString('fill="#111111"', $svg); // Third digit: 0
        self::assertSame(2, substr_count($svg, 'fill="#7b3f00"')); // x10 and 1%
    }

    public function testLiteralToleranceAndInvalidInput(): void
    {
        self::assertStringContainsString(
            '0.1% tolerance',
            $this->provider->replace("[[RESISTOR_5_BAND(PARAMETERS['Resistance'], '0.1%')]]", $this->part)
        );
        self::assertSame('', $this->provider->replace("[[RESISTOR_4_BAND(PARAMETERS['Missing'])]]", $this->part));
        self::assertNull($this->provider->replace('[[NAME]]', $this->part));
    }

    public function testMissingToleranceParameterOmitsToleranceBand(): void
    {
        $four_band = $this->provider->replace(
            "[[RESISTOR_4_BAND(PARAMETERS['Resistance'], PARAMETERS['MissingTolerance'])]]",
            $this->part
        );
        self::assertIsString($four_band);
        self::assertStringContainsString('4-band resistor, 4700 ohms', $four_band);
        self::assertStringNotContainsString('tolerance', $four_band);
        self::assertCount(3, $this->extractColors($four_band));

        $five_band = $this->provider->replace(
            "[[RESISTOR_5_BAND(PARAMETERS['Resistance'], PARAMETERS['MissingTolerance'])]]",
            $this->part
        );
        self::assertIsString($five_band);
        self::assertStringContainsString('5-band resistor, 4700 ohms', $five_band);
        self::assertStringNotContainsString('tolerance', $five_band);
        self::assertCount(4, $this->extractColors($five_band));
    }

    public function testReferenceFourBandExamples(): void
    {
        self::assertSame(
            ['#d62828', '#d62828', '#7b3f00', '#d4af37'],
            $this->renderLiteralResistance(220, 5)
        );
        self::assertSame(
            ['#7b3f00', '#111111', '#f77f00', '#d4af37'],
            $this->renderLiteralResistance(10_000, 5)
        );
    }

    public function testFractionalMultipliersAndDocumentedTolerances(): void
    {
        self::assertSame(
            ['#fcbf49', '#7b2cbf', '#d4af37', '#f77f00'],
            $this->renderLiteralResistance(4.7, 3)
        );
        self::assertSame(
            ['#fcbf49', '#7b2cbf', '#c0c0c0', '#fcbf49'],
            $this->renderLiteralResistance(0.47, 4)
        );
    }

    public function testThreeDigitSmdCodes(): void
    {
        self::assertSame('103', $this->renderCode("RESISTOR_EIA_3('10000')"));
        self::assertSame('103J', $this->renderCode("RESISTOR_EIA_3('10000', '5%')"));
        self::assertSame('472J', $this->renderCode("RESISTOR_EIA_3('4700', '5%')"));
        self::assertSame('560J', $this->renderCode("RESISTOR_EIA_3('56', '5%')"));
        self::assertSame('4R7J', $this->renderCode("RESISTOR_EIA_3('4.7', '5%')"));
        self::assertSame('R22J', $this->renderCode("RESISTOR_EIA_3('0.22', '5%')"));
        self::assertSame('000', $this->renderCode("RESISTOR_EIA_3('0', '5%')"));
    }

    public function testFourDigitSmdCodes(): void
    {
        self::assertSame('2201', $this->renderCode("RESISTOR_EIA_4('2200')"));
        self::assertSame('2201F', $this->renderCode("RESISTOR_EIA_4('2200', '1%')"));
        self::assertSame('1001F', $this->renderCode("RESISTOR_EIA_4('1000', '1%')"));
        self::assertSame('4992F', $this->renderCode("RESISTOR_EIA_4('49900', '1%')"));
        self::assertSame('7500F', $this->renderCode("RESISTOR_EIA_4('750', '1%')"));
        self::assertSame('4R70F', $this->renderCode("RESISTOR_EIA_4('4.7', '1%')"));
        self::assertSame('R102F', $this->renderCode("RESISTOR_EIA_4('0.102', '1%')"));
        self::assertSame('0000', $this->renderCode("RESISTOR_EIA_4('0', '1%')"));
    }

    public function testEia96Codes(): void
    {
        self::assertSame('01C', $this->renderCode("RESISTOR_EIA_96('10000')"));
        self::assertSame('01C', $this->renderCode("RESISTOR_EIA_96('10000', '1%')"));
        self::assertSame('10A', $this->renderCode("RESISTOR_EIA_96('124', '1%')"));
        self::assertSame('66B', $this->renderCode("RESISTOR_EIA_96('4750', '1%')"));
        self::assertSame('96Y', $this->renderCode("RESISTOR_EIA_96('9.76', '1%')"));
        self::assertSame('', $this->renderCode("RESISTOR_EIA_96('4700', '1%')"));
        self::assertSame('', $this->renderCode("RESISTOR_EIA_96('10000', '5%')"));
    }

    public function testSmdCodeParameterArgumentsAndToleranceLetters(): void
    {
        self::assertSame(
            '4701F',
            $this->provider->replace(
                "[[RESISTOR_EIA_4(PARAMETERS['Resistance'], PARAMETERS['Tolerance'])]]",
                $this->part
            )
        );
        self::assertSame('100G', $this->renderCode("RESISTOR_EIA_3('10', '2%')"));
        self::assertSame('100K', $this->renderCode("RESISTOR_EIA_3('10', '10%')"));
        self::assertSame('100M', $this->renderCode("RESISTOR_EIA_3('10', '20%')"));
        self::assertSame('', $this->renderCode("RESISTOR_EIA_3('10', '0.5%')"));
    }

    private function renderCode(string $expression): string
    {
        return $this->provider->replace('[['.$expression.']]', $this->part) ?? '';
    }

    /** @return list<string> */
    private function renderLiteralResistance(float $resistance, float $tolerance): array
    {
        $html = $this->provider->replace(
            sprintf("[[RESISTOR_4_BAND('%s', '%s%%')]]", $resistance, $tolerance),
            $this->part
        );
        self::assertIsString($html);
        $svg = $this->extractSvg($html);
        self::assertGreaterThan(0, preg_match_all('/<rect[^>]+fill="(#[0-9a-f]+)"/', $svg, $matches));
        return $matches[1];
    }

    private function extractSvg(string $html): string
    {
        self::assertSame(1, preg_match('/base64,([^\"]+)/', $html, $matches));
        $svg = base64_decode($matches[1], true);
        self::assertIsString($svg);
        return $svg;
    }

    /** @return list<string> */
    private function extractColors(string $html): array
    {
        self::assertGreaterThan(
            0,
            preg_match_all('/<rect[^>]+fill="(#[0-9a-f]+)"/', $this->extractSvg($html), $matches)
        );
        return $matches[1];
    }
}
