export function unitSymbol(unit) {
    return unit === 'inch' ? 'in' : 'mm';
}

export function updateDimensionUnitSuffixes(suffixes, unit) {
    const symbol = unitSymbol(unit);
    suffixes.forEach((suffix) => {
        suffix.textContent = symbol;
    });
}

export function convertDimensionValue(value, fromUnit, toUnit) {
    if (value === '' || fromUnit === toUnit) {
        return value;
    }

    const numericValue = Number(value);
    if (!Number.isFinite(numericValue)) {
        return value;
    }

    const converted = fromUnit === 'inch' && toUnit === 'mm'
        ? numericValue * 25.4
        : numericValue / 25.4;

    return Number(converted.toFixed(4)).toString();
}

export function convertDimensionInputs(inputs, fromUnit, toUnit) {
    if (fromUnit === toUnit) {
        return;
    }

    inputs.forEach((input) => {
        input.value = convertDimensionValue(input.value, fromUnit, toUnit);
        input.dispatchEvent(new Event('input', {bubbles: true}));
    });
}
