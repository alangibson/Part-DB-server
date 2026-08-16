<?php

declare(strict_types=1);

namespace App\Services\LabelSystem\PlaceholderProviders;

use App\Entity\Parameters\AbstractParameter;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 30)]
final readonly class ResistorColorCodeProvider implements PlaceholderProviderInterface
{
    private const DIGIT_COLORS = [
        '#111111', '#7b3f00', '#d62828', '#f77f00', '#fcbf49',
        '#2a9d3f', '#277da1', '#7b2cbf', '#808080', '#f5f5f5',
    ];

    private const MULTIPLIER_COLORS = [
        -2 => '#c0c0c0', -1 => '#d4af37', 0 => '#111111', 1 => '#7b3f00',
        2 => '#d62828', 3 => '#f77f00', 4 => '#fcbf49', 5 => '#2a9d3f',
        6 => '#277da1', 7 => '#7b2cbf', 8 => '#808080', 9 => '#f5f5f5',
    ];

    private const TOLERANCE_COLORS = [
        '0.05' => '#808080', '0.1' => '#7b2cbf', '0.25' => '#277da1',
        '0.5' => '#2a9d3f', '1' => '#7b3f00', '2' => '#d62828',
        '3' => '#f77f00', '4' => '#fcbf49',
        '5' => '#d4af37', '10' => '#c0c0c0',
    ];

    public function __construct(private ParameterProvider $parameterProvider)
    {
    }

    public function replace(string $placeholder, object $label_target, array $options = []): ?string
    {
        if (preg_match('/^\[\[RESISTOR_([45])_BAND\((.*)\)\]\]$/i', $placeholder, $matches) !== 1) {
            return null;
        }

        $band_count = (int) $matches[1];
        $arguments = $this->splitArguments($matches[2]);
        if (count($arguments) < 1 || count($arguments) > 2) {
            return '';
        }

        $resistance = $this->resolveNumber($arguments[0], $label_target);
        $tolerance = isset($arguments[1])
            ? $this->resolveNumber($arguments[1], $label_target)
            : ($band_count === 4 ? 5.0 : 1.0);

        if ($resistance === null || $resistance <= 0) {
            return '';
        }

        $colors = $this->calculateColors($resistance, $tolerance, $band_count - 2);
        if ($colors === null) {
            return '';
        }

        return $this->renderImage($colors, $resistance, $tolerance, $band_count);
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

    private function resolveNumber(string $argument, object $target): ?float
    {
        $parameter = $this->parameterProvider->findParameter('[['.$argument.']]', $target);
        if ($parameter instanceof AbstractParameter) {
            return $parameter->getValueTypical() !== null
                ? $this->parseNumber($parameter->getValueTypical().' '.$parameter->getUnit())
                : $this->parseNumber($parameter->getValueText());
        }
        if ($parameter === null) {
            return null;
        }

        return $this->parseNumber(trim($argument, " \t\n\r\0\x0B'\""));
    }

    private function parseNumber(string $value): ?float
    {
        $normalized = str_replace([',', 'Ω', 'Ω', '%', 'ohms', 'ohm'], ['.', '', '', '', '', ''], trim($value));
        if (preg_match('/^([+-]?(?:\d+(?:\.\d+)?|\.\d+))\s*([pnumkKMGT]?)/u', $normalized, $matches) !== 1) {
            return null;
        }

        $factor = match ($matches[2]) {
            'p' => 1e-12, 'n' => 1e-9, 'u' => 1e-6, 'm' => 1e-3,
            'k', 'K' => 1e3, 'M' => 1e6, 'G' => 1e9, 'T' => 1e12,
            default => 1.0,
        };
        return (float) $matches[1] * $factor;
    }

    /** @return list<string>|null */
    private function calculateColors(float $resistance, ?float $tolerance, int $significant_digits): ?array
    {
        $multiplier = (int) floor(log10($resistance)) - ($significant_digits - 1);
        $significand = (int) round($resistance / (10 ** $multiplier));
        if ($significand >= 10 ** $significant_digits) {
            $significand = intdiv($significand, 10);
            ++$multiplier;
        }

        if (!isset(self::MULTIPLIER_COLORS[$multiplier])) {
            return null;
        }

        $digits = str_pad((string) $significand, $significant_digits, '0', STR_PAD_LEFT);
        $colors = [];
        foreach (str_split($digits) as $digit) {
            $colors[] = self::DIGIT_COLORS[(int) $digit];
        }
        $colors[] = self::MULTIPLIER_COLORS[$multiplier];
        if ($tolerance !== null) {
            $tolerance_key = rtrim(rtrim(number_format($tolerance, 2, '.', ''), '0'), '.');
            if (!isset(self::TOLERANCE_COLORS[$tolerance_key])) {
                return null;
            }
            $colors[] = self::TOLERANCE_COLORS[$tolerance_key];
        }
        return $colors;
    }

    /** @param list<string> $colors */
    private function renderImage(array $colors, float $resistance, ?float $tolerance, int $band_count): string
    {
        $positions = $band_count === 4 ? [25, 45, 70, 105] : [18, 36, 54, 76, 107];
        $bands = '';
        foreach ($colors as $index => $color) {
            $bands .= sprintf('<rect x="%d" y="4" width="10" height="36" fill="%s"/>', $positions[$index], $color);
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="44" viewBox="0 0 120 44">'
            .'<rect x="0" y="0" width="120" height="44" fill="none" stroke="#9c8658" stroke-width="2"/>'
            .$bands.'</svg>';
        $alt = $tolerance === null
            ? sprintf('%d-band resistor, %g ohms', $band_count, $resistance)
            : sprintf('%d-band resistor, %g ohms, %g%% tolerance', $band_count, $resistance, $tolerance);

        return sprintf(
            '<img class="resistor-color-code" src="data:image/svg+xml;base64,%s" alt="%s" style="display:block;width:100%%;height:auto">',
            base64_encode($svg),
            htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }
}
