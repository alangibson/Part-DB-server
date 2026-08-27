<?php

declare(strict_types=1);

namespace App\Tests\Services\LabelSystem\PlaceholderProviders;

use App\Entity\Parameters\PartParameter;
use App\Entity\Parameters\StorageLocationParameter;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Services\LabelSystem\PlaceholderProviders\ParameterProvider;
use PHPUnit\Framework\TestCase;

final class ParameterProviderTest extends TestCase
{
    private ParameterProvider $provider;
    private Part $part;
    private StorageLocation $location;
    private PartLot $lot;

    protected function setUp(): void
    {
        $this->provider = new ParameterProvider();

        $resistance = new PartParameter();
        $resistance->setName('Resistance');
        $resistance->setSymbol('R');
        $resistance->setValueMin(900.0);
        $resistance->setValueTypical(1000.0);
        $resistance->setValueMax(1100.0);
        $resistance->setUnit('Ω');
        $resistance->setValueText('<nominal>');
        $resistance->setGroup('Electrical');

        $this->part = new Part();
        $this->part->addParameter($resistance);

        $quoted_name = new PartParameter();
        $quoted_name->setName("Collector's voltage");
        $quoted_name->setValueTypical(5.0);
        $quoted_name->setUnit('V');
        $this->part->addParameter($quoted_name);

        $description = new PartParameter();
        $description->setName('Dielectric');
        $description->setValueText('X7R');
        $this->part->addParameter($description);

        $zero = new PartParameter();
        $zero->setName('Offset');
        $zero->setValueTypical(0.0);
        $zero->setValueText('fallback must not be used');
        $this->part->addParameter($zero);

        $aisle = new StorageLocationParameter();
        $aisle->setName('Aisle');
        $aisle->setValueText('<A-1>');

        $this->location = new StorageLocation();
        $this->location->addParameter($aisle);

        $this->lot = new PartLot();
        $this->lot->setPart($this->part);
        $this->lot->setStorageLocation($this->location);
    }

    public function testCurrentTargetParameter(): void
    {
        self::assertSame('1000 Ω (900 Ω ... 1100 Ω) [&lt;nominal&gt;]', $this->provider->replace("[[PARAMETERS['Resistance']]]", $this->part));
        self::assertSame('5 V', $this->provider->replace("[[parameters['Collector\\'s voltage']]]", $this->part));
        self::assertSame('&lt;A-1&gt;', $this->provider->replace('[[parameters["Aisle"]]]', $this->location));
    }

    public function testPartLotScopes(): void
    {
        self::assertSame('1000 Ω (900 Ω ... 1100 Ω) [&lt;nominal&gt;]', $this->provider->replace("[[parameters['Resistance']]]", $this->lot));
        self::assertSame('1000 Ω (900 Ω ... 1100 Ω) [&lt;nominal&gt;]', $this->provider->replace("[[part.parameters['Resistance']]]", $this->lot));
        self::assertSame('&lt;A-1&gt;', $this->provider->replace("[[storage_location.parameters['Aisle']]]", $this->lot));
    }

    public function testStructuredParameterFields(): void
    {
        self::assertSame('Resistance', $this->provider->replace("[[PARAM['Resistance'].NAME]]", $this->part));
        self::assertSame('R', $this->provider->replace("[[PARAM['Resistance'].SYMBOL]]", $this->part));
        self::assertSame('900', $this->provider->replace("[[PARAM['Resistance'].MIN]]", $this->part));
        self::assertSame('1000', $this->provider->replace("[[PARAM['Resistance'].TYPICAL]]", $this->part));
        self::assertSame('1100', $this->provider->replace("[[PARAM['Resistance'].MAX]]", $this->part));
        self::assertSame('Ω', $this->provider->replace("[[PARAM['Resistance'].UNIT]]", $this->part));
        self::assertSame('&lt;nominal&gt;', $this->provider->replace("[[PARAM['Resistance'].TEXT]]", $this->part));
        self::assertSame('Electrical', $this->provider->replace("[[PARAM['Resistance'].GROUP]]", $this->part));
        self::assertSame('1000', $this->provider->replace("[[PARAM['Resistance'].VALUE]]", $this->part));
        self::assertSame('1000 Ω (900 Ω ... 1100 Ω) [&lt;nominal&gt;]', $this->provider->replace("[[PARAM['Resistance'].FORMATTED]]", $this->part));
    }

    public function testValueFallsBackToTextOnlyWhenTypicalIsNull(): void
    {
        self::assertSame('X7R', $this->provider->replace("[[PARAM['Dielectric'].VALUE]]", $this->part));
        self::assertSame('0', $this->provider->replace("[[PARAM['Offset'].VALUE]]", $this->part));
    }

    public function testStructuredPartLotScopes(): void
    {
        self::assertSame('1000', $this->provider->replace("[[PART.PARAM['Resistance'].VALUE]]", $this->lot));
        self::assertSame('&lt;A-1&gt;', $this->provider->replace("[[STORAGE_LOCATION.PARAM['Aisle'].VALUE]]", $this->lot));
    }

    public function testMissingAndUnsupportedParameters(): void
    {
        self::assertSame('', $this->provider->replace("[[parameters['Missing']]]", $this->part));
        self::assertSame('', $this->provider->replace("[[PARAM['Missing'].VALUE]]", $this->part));
        self::assertSame('', $this->provider->replace("[[PARAM['Resistance'].UNKNOWN]]", $this->part));
        self::assertNull($this->provider->replace('[[NAME]]', $this->part));
    }
}
