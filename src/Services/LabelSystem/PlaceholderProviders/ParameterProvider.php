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
        if ($parameter === false) {
            return null;
        }

        return $parameter instanceof AbstractParameter ? htmlspecialchars($parameter->getFormattedValue()) : '';
    }

    /**
     * @return AbstractParameter|null|false The parameter, null when it does not exist, or false for invalid syntax.
     */
    public function findParameter(string $placeholder, object $label_target): AbstractParameter|null|false
    {
        if (preg_match('/^\[\[(?:(part|storage_location)\.)?parameters\[\s*([\'\"])((?:\\\\.|(?!\2).)*)\2\s*\]\]\]$/i', $placeholder, $matches) !== 1) {
            return false;
        }

        $scope = strtolower($matches[1] ?? '');
        $parameter_name = stripcslashes($matches[3]);
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
