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
        $resistance->setValueTypical(1000.0);
        $resistance->setUnit('Ω');

        $this->part = new Part();
        $this->part->addParameter($resistance);

        $quoted_name = new PartParameter();
        $quoted_name->setName("Collector's voltage");
        $quoted_name->setValueTypical(5.0);
        $quoted_name->setUnit('V');
        $this->part->addParameter($quoted_name);

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
        self::assertSame('1000 Ω', $this->provider->replace("[[PARAMETERS['Resistance']]]", $this->part));
        self::assertSame('5 V', $this->provider->replace("[[parameters['Collector\\'s voltage']]]", $this->part));
        self::assertSame('&lt;A-1&gt;', $this->provider->replace('[[parameters["Aisle"]]]', $this->location));
    }

    public function testPartLotScopes(): void
    {
        self::assertSame('1000 Ω', $this->provider->replace("[[parameters['Resistance']]]", $this->lot));
        self::assertSame('1000 Ω', $this->provider->replace("[[part.parameters['Resistance']]]", $this->lot));
        self::assertSame('&lt;A-1&gt;', $this->provider->replace("[[storage_location.parameters['Aisle']]]", $this->lot));
    }

    public function testMissingAndUnsupportedParameters(): void
    {
        self::assertSame('', $this->provider->replace("[[parameters['Missing']]]", $this->part));
        self::assertNull($this->provider->replace('[[NAME]]', $this->part));
    }
}
