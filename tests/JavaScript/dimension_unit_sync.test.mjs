import test from 'node:test';
import assert from 'node:assert/strict';
import {
    convertDimensionInputs,
    convertDimensionValue,
    unitSymbol,
    updateDimensionUnitSuffixes,
} from '../../assets/controllers/pages/dimension_unit_sync.mjs';

test('returns the display symbol for each label unit', () => {
    assert.equal(unitSymbol('mm'), 'mm');
    assert.equal(unitSymbol('inch'), 'in');
});

test('sets the unit on every dimension field', () => {
    const sheetSizeUnit = { textContent: '' };
    const labelSizeUnit = { textContent: '' };

    updateDimensionUnitSuffixes([sheetSizeUnit, labelSizeUnit], 'inch');

    assert.equal(sheetSizeUnit.textContent, 'in');
    assert.equal(labelSizeUnit.textContent, 'in');
});

test('converts dimension values in both directions', () => {
    assert.equal(convertDimensionValue('25.4', 'mm', 'inch'), '1');
    assert.equal(convertDimensionValue('2.625', 'inch', 'mm'), '66.675');
    assert.equal(convertDimensionValue('', 'mm', 'inch'), '');
});

test('converts every physical dimension input when the unit changes', () => {
    let events = 0;
    const input = (value) => ({
        value,
        dispatchEvent(event) {
            assert.equal(event.type, 'input');
            events++;
        },
    });
    const sheetWidth = input('210');
    const labelWidth = input('69');
    const gutterWidth = input('3');

    convertDimensionInputs([sheetWidth, labelWidth, gutterWidth], 'mm', 'inch');

    assert.equal(sheetWidth.value, '8.2677');
    assert.equal(labelWidth.value, '2.7165');
    assert.equal(gutterWidth.value, '0.1181');
    assert.equal(events, 3);
});
