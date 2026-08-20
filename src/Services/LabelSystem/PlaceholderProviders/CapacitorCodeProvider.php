<?php

declare(strict_types=1);

namespace App\Services\LabelSystem\PlaceholderProviders;

use App\Entity\Parameters\AbstractParameter;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 30)]
final readonly class CapacitorCodeProvider implements PlaceholderProviderInterface
{
    /**
     * IEC 60062 Annex B / EIA-198 significant-value codes. The lowercase
     * entries are EIA-198 extensions for values outside the E24 series.
     */
    private const COMPACT_VALUES = [
        'A' => 1.0, 'B' => 1.1, 'C' => 1.2, 'D' => 1.3, 'E' => 1.5,
        'F' => 1.6, 'G' => 1.8, 'H' => 2.0, 'J' => 2.2, 'K' => 2.4,
        'L' => 2.7, 'M' => 3.0, 'N' => 3.3, 'P' => 3.6, 'Q' => 3.9,
        'R' => 4.3, 'S' => 4.7, 'T' => 5.1, 'U' => 5.6, 'V' => 6.2,
        'W' => 6.8, 'X' => 7.5, 'Y' => 8.2, 'Z' => 9.1,
        'a' => 2.5, 'b' => 3.5, 'd' => 4.0, 'e' => 4.5, 'f' => 5.0,
        'm' => 6.0, 'n' => 7.0, 't' => 8.0, 'y' => 9.0,
    ];

    private const RELATIVE_TOLERANCES = [
        '0.005' => 'E', '0.01' => 'L', '0.02' => 'P', '0.05' => 'W',
        '0.1' => 'B', '0.25' => 'C', '0.5' => 'D', '1' => 'F',
        '2' => 'G', '3' => 'H', '5' => 'J', '10' => 'K',
        '20' => 'M', '30' => 'N',
    ];

    private const ABSOLUTE_TOLERANCES = [
        '0.1' => 'B', '0.25' => 'C', '0.5' => 'D', '1' => 'F', '2' => 'G',
    ];

    public function __construct(private ParameterProvider $parameterProvider)
    {
    }

    public function replace(string $placeholder, object $label_target, array $options = []): ?string
    {
        if (preg_match('/^\[\[CAPACITOR_IEC_(2|3)\((.*)\)\]\]$/i', $placeholder, $matches) !== 1) {
            return null;
        }

        $arguments = $this->splitArguments($matches[2]);
        $maximum_arguments = $matches[1] === '3' ? 2 : 1;
        if (count($arguments) < 1 || count($arguments) > $maximum_arguments) {
            return '';
        }

        $capacitance = $this->resolveCapacitance($arguments[0], $label_target);
        if ($capacitance === null || $capacitance <= 0) {
            return '';
        }

        if ($matches[1] === '2') {
            return $this->encodeCompactCode($capacitance);
        }

        $code = $this->encodeThreeCharacterCode($capacitance);
        if ($code === '' || !isset($arguments[1])) {
            return $code;
        }

        $tolerance = $this->resolveArgument($arguments[1], $label_target);
        if ($tolerance === null) {
            return $code;
        }

        $tolerance_letter = $this->encodeTolerance($tolerance, $capacitance * 1e12);
        return $tolerance_letter === null ? '' : $code.$tolerance_letter;
    }

    private function encodeThreeCharacterCode(float $capacitance): string
    {
        $picofarads = $capacitance * 1e12;
        if ($picofarads < 0.1) {
            return '';
        }

        if ($picofarads < 10) {
            $tenths = (int) round($picofarads * 10);
            if (!$this->approximatelyEqual($picofarads, $tenths / 10)) {
                return '';
            }

            return sprintf('%dR%d', intdiv($tenths, 10), $tenths % 10);
        }

        $multiplier = (int) floor(log10($picofarads)) - 1;
        $significand = (int) round($picofarads / (10 ** $multiplier));
        if ($significand === 100) {
            $significand = 10;
            ++$multiplier;
        }

        // IEC 60062 defines the pF-based three-character range through 910 uF.
        if ($multiplier < 0 || $multiplier > 7 || $significand < 10 || $significand > 99) {
            return '';
        }

        $encoded_value = $significand * (10 ** $multiplier);
        if (!$this->approximatelyEqual($picofarads, $encoded_value)) {
            return '';
        }

        return sprintf('%02d%d', $significand, $multiplier);
    }

    private function encodeCompactCode(float $capacitance): string
    {
        $picofarads = $capacitance * 1e12;
        foreach (range(-2, 7) as $multiplier) {
            foreach (self::COMPACT_VALUES as $letter => $significand) {
                if ($this->approximatelyEqual($picofarads, $significand * (10 ** $multiplier))) {
                    $multiplier_code = match ($multiplier) {
                        -2 => '8',
                        -1 => '9',
                        default => (string) $multiplier,
                    };

                    return $letter.$multiplier_code;
                }
            }
        }

        return '';
    }

    private function encodeTolerance(string $tolerance, float $picofarads): ?string
    {
        $normalized = str_replace(['−', '+', ' ', ','], ['-', '', '', '.'], trim($tolerance));
        if (preg_match('/^([+-]?(?:\d+(?:\.\d+)?|\.\d+))%?$/', $normalized, $matches) === 1) {
            if ($picofarads < 10 && str_contains($tolerance, '%')) {
                return null;
            }

            $key = $this->numberKey((float) $matches[1]);
            return $picofarads < 10
                ? self::ABSOLUTE_TOLERANCES[$key] ?? null
                : self::RELATIVE_TOLERANCES[$key] ?? null;
        }

        if (preg_match('/^([+-]?(?:\d+(?:\.\d+)?|\.\d+))pF$/i', $normalized, $matches) === 1) {
            if ($picofarads >= 10) {
                return null;
            }

            return self::ABSOLUTE_TOLERANCES[$this->numberKey((float) $matches[1])] ?? null;
        }

        return match ($normalized) {
            '-10/30%' => 'Q',
            '-10/50%' => 'T',
            '-20/50%' => 'S',
            '-20/80%' => 'Z',
            default => null,
        };
    }

    /** @return list<string> */
    private function splitArguments(string $input): array
    {
        $arguments = [];
        $current = '';
        $quote = null;
        $escaped = false;

        foreach (mb_str_split($input) as $character) {
            if ($escaped) {
                $current .= $character;
                $escaped = false;
                continue;
            }
            if ($character === '\\') {
                $current .= $character;
                $escaped = true;
                continue;
            }
            if ($quote !== null) {
                $current .= $character;
                if ($character === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($character === "'" || $character === '"') {
                $quote = $character;
                $current .= $character;
                continue;
            }
            if ($character === ',') {
                $arguments[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $character;
        }

        $arguments[] = trim($current);
        return $arguments;
    }

    private function resolveCapacitance(string $argument, object $target): ?float
    {
        $value = $this->resolveArgument($argument, $target);
        if ($value === null) {
            return null;
        }

        $normalized = str_replace([',', 'µ', 'μ'], ['.', 'u', 'u'], trim($value));
        if (preg_match('/^([+-]?(?:\d+(?:\.\d+)?|\.\d+))\s*([pPnum]?)\s*[fF]?$/u', $normalized, $matches) !== 1) {
            return null;
        }

        $factor = match ($matches[2]) {
            'p', 'P' => 1e-12,
            'n' => 1e-9,
            'u' => 1e-6,
            'm' => 1e-3,
            default => 1.0,
        };

        return (float) $matches[1] * $factor;
    }

    private function resolveArgument(string $argument, object $target): ?string
    {
        $parameter = $this->parameterProvider->findParameter('[['.$argument.']]', $target);
        if ($parameter instanceof AbstractParameter) {
            return $parameter->getValueTypical() !== null
                ? $parameter->getValueTypical().' '.$parameter->getUnit()
                : $parameter->getValueText();
        }
        if ($parameter === null) {
            return null;
        }

        return trim($argument, " \t\n\r\0\x0B'\"");
    }

    private function numberKey(float $number): string
    {
        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    }

    private function approximatelyEqual(float $left, float $right): bool
    {
        return abs($left - $right) <= max(1e-9, abs($right) * 1e-9);
    }
}
