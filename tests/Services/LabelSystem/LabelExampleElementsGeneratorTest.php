<?php

declare(strict_types=1);

namespace App\Tests\Services\LabelSystem;

use App\Entity\Parameters\AbstractParameter;
use App\Services\LabelSystem\LabelExampleElementsGenerator;
use PHPUnit\Framework\TestCase;

final class LabelExampleElementsGeneratorTest extends TestCase
{
    public function testExamplePartHasPreviewParameters(): void
    {
        $part = (new LabelExampleElementsGenerator())->getExamplePart();

        $parameters = [];
        foreach ($part->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }

        $this->assertParameter($parameters['Resistance'] ?? null, 100.0, 'kΩ');
        $this->assertParameter($parameters['Power'] ?? null, 0.5, 'W');
        $this->assertParameter($parameters['Tolerance'] ?? null, 1.0, '%');
    }

    public function testExamplePartLotUsesPartWithPreviewParameters(): void
    {
        $lot = (new LabelExampleElementsGenerator())->getExamplePartLot();

        self::assertCount(3, $lot->getPart()->getParameters());
    }

    private function assertParameter(?AbstractParameter $parameter, float $value, string $unit): void
    {
        self::assertInstanceOf(AbstractParameter::class, $parameter);
        self::assertSame($value, $parameter->getValueTypical());
        self::assertSame($unit, $parameter->getUnit());
    }
}
