<?php

declare(strict_types=1);

namespace App\Services\LabelSystem\PlaceholderProviders;

use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Resolves named parameters in labels, for example [[parameters['Resistance']]].
 */
#[AsTaggedItem(priority: 20)]
final class ParameterProvider implements PlaceholderProviderInterface
{
    public function replace(string $placeholder, object $label_target, array $options = []): ?string
    {
        $parameter = $this->findParameter($placeholder, $label_target);
        if ($parameter !== false) {
            return $parameter instanceof AbstractParameter ? htmlspecialchars($parameter->getFormattedValue()) : '';
        }

        if (preg_match('/^\[\[(?:(part|storage_location)\.)?param\[\s*([\'\"])((?:\\\\.|(?!\2).)*)\2\s*\]\.([a-z_]+)\]\]$/i', $placeholder, $matches) !== 1) {
            return null;
        }

        $parameter = $this->findParameterByName(
            stripcslashes($matches[3]),
            $label_target,
            strtolower($matches[1])
        );
        if (!$parameter instanceof AbstractParameter) {
            return '';
        }

        $value = match (strtoupper($matches[4])) {
            'ID' => $parameter->getID(),
            'NAME' => $parameter->getName(),
            'SYMBOL' => $parameter->getSymbol(),
            'MIN' => $parameter->getValueMin(),
            'TYPICAL' => $parameter->getValueTypical(),
            'MAX' => $parameter->getValueMax(),
            'UNIT' => $parameter->getUnit(),
            'TEXT' => $parameter->getValueText(),
            'GROUP' => $parameter->getGroup(),
            'EDA_VISIBILITY' => $parameter->isEdaVisibility(),
            'EDA_SYMBOL_VISIBILITY' => $parameter->isEdaSymbolVisibility(),
            'FORMATTED' => $parameter->getFormattedValue(),
            // VALUE deliberately checks for null, so a typical value of 0.0 is retained.
            'VALUE' => $parameter->getValueTypical() ?? $parameter->getValueText(),
            default => null,
        };

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        return htmlspecialchars((string) $value);
    }

    /**
     * @return AbstractParameter|null|false The parameter, null when it does not exist, or false for invalid syntax.
     */
    public function findParameter(string $placeholder, object $label_target): AbstractParameter|null|false
    {
        if (preg_match('/^\[\[(?:(part|storage_location)\.)?parameters\[\s*([\'\"])((?:\\\\.|(?!\2).)*)\2\s*\]\]\]$/i', $placeholder, $matches) !== 1) {
            return false;
        }

        $scope = strtolower($matches[1]);
        $parameter_name = stripcslashes($matches[3]);

        return $this->findParameterByName($parameter_name, $label_target, $scope);
    }

    private function findParameterByName(string $parameter_name, object $label_target, string $scope): ?AbstractParameter
    {
        $element = $this->resolveElement($label_target, $scope);

        if (!$element instanceof Part && !$element instanceof StorageLocation) {
            return null;
        }

        foreach ($element->getParameters() as $parameter) {
            if ($parameter instanceof AbstractParameter && $parameter->getName() === $parameter_name) {
                return $parameter;
            }
        }

        return null;
    }

    private function resolveElement(object $target, string $scope): Part|StorageLocation|null
    {
        if ($scope === 'part') {
            return match (true) {
                $target instanceof Part => $target,
                $target instanceof PartLot => $target->getPart(),
                default => null,
            };
        }

        if ($scope === 'storage_location') {
            return match (true) {
                $target instanceof StorageLocation => $target,
                $target instanceof PartLot => $target->getStorageLocation(),
                default => null,
            };
        }

        return match (true) {
            $target instanceof Part, $target instanceof StorageLocation => $target,
            $target instanceof PartLot => $target->getPart(),
            default => null,
        };
    }
}
