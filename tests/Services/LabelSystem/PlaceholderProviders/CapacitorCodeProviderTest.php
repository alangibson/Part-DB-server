<?php

declare(strict_types=1);

namespace App\Tests\Services\LabelSystem\PlaceholderProviders;

use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Part;
use App\Services\LabelSystem\PlaceholderProviders\CapacitorCodeProvider;
use App\Services\LabelSystem\PlaceholderProviders\ParameterProvider;
use PHPUnit\Framework\TestCase;

final class CapacitorCodeProviderTest extends TestCase
{
    private CapacitorCodeProvider $provider;
    private Part $part;

    protected function setUp(): void
    {
        $this->provider = new CapacitorCodeProvider(new ParameterProvider());
        $this->part = new Part();

        $capacitance = new PartParameter();
        $capacitance->setName('Capacitance');
        $capacitance->setValueTypical(47.0);
        $capacitance->setUnit('nF');
        $this->part->addParameter($capacitance);

        $tolerance = new PartParameter();
        $tolerance->setName('Tolerance');
        $tolerance->setValueTypical(5.0);
        $tolerance->setUnit('%');
        $this->part->addParameter($tolerance);
    }

    public function testThreeCharacterCode(): void
    {
        self::assertSame('473', $this->render("CAPACITOR_IEC_3(PARAMETERS['Capacitance'])"));
        self::assertSame(
            '473J',
            $this->render("CAPACITOR_IEC_3(PARAMETERS['Capacitance'], PARAMETERS['Tolerance'])")
        );
        self::assertSame('100', $this->render("CAPACITOR_IEC_3('10pF')"));
        self::assertSame('101', $this->render("CAPACITOR_IEC_3('100pF')"));
        self::assertSame('102', $this->render("CAPACITOR_IEC_3('1nF')"));
        self::assertSame('104K', $this->render("CAPACITOR_IEC_3('100nF', '10%')"));
        self::assertSame('4R7C', $this->render("CAPACITOR_IEC_3('4.7pF', '0.25pF')"));
    }

    public function testCompactCode(): void
    {
        self::assertSame('S4', $this->render("CAPACITOR_IEC_2(PARAMETERS['Capacitance'])"));
        self::assertSame('A1', $this->render("CAPACITOR_IEC_2('10pF')"));
        self::assertSame('J5', $this->render("CAPACITOR_IEC_2('0.22uF')"));
        self::assertSame('S9', $this->render("CAPACITOR_IEC_2('0.47pF')"));
        self::assertSame('a2', $this->render("CAPACITOR_IEC_2('250pF')"));
    }

    public function testInvalidAndUnrepresentableValuesReturnEmptyString(): void
    {
        self::assertSame('', $this->render("CAPACITOR_IEC_3('12.3pF')"));
        self::assertSame('', $this->render("CAPACITOR_IEC_3('4.7pF', '5%')"));
        self::assertSame('', $this->render("CAPACITOR_IEC_3('1mF')"));
        self::assertSame('', $this->render("CAPACITOR_IEC_2('12.3pF')"));
        self::assertSame('', $this->render("CAPACITOR_IEC_2('10pF', '5%')"));
        self::assertSame('', $this->render("CAPACITOR_IEC_3(PARAMETERS['Missing'])"));
        self::assertSame('104', $this->render("CAPACITOR_IEC_3('100nF', PARAMETERS['Missing'])"));
        self::assertNull($this->provider->replace('[[NAME]]', $this->part));
    }

    public function testMissingOptionalToleranceParameterOmitsTolerance(): void
    {
        self::assertSame(
            '473',
            $this->render("CAPACITOR_IEC_3(PARAMETERS['Capacitance'], PARAMETERS['MissingTolerance'])")
        );
        self::assertSame('473', $this->render("CAPACITOR_IEC_3(PARAMETERS['Capacitance'])"));
    }

    private function render(string $expression): string
    {
        return $this->provider->replace('[['.$expression.']]', $this->part) ?? '';
    }
}
