import test from 'node:test';
import assert from 'node:assert/strict';
import { bindLabelProfileSheetChange, synchronizeLabelProfileSheet } from '../../assets/controllers/pages/label_profile_sheet_sync.mjs';

function input(value = '') {
    const classes = new Set();
    return {
        value,
        readOnly: false,
        dataset: {},
        attributes: {},
        classList: { toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name) },
        setAttribute(name, attributeValue) { this.attributes[name] = attributeValue; },
        hasClass(name) { return classes.has(name); },
    };
}

test('copies dimensions using the select value even when selectedOptions is stale', () => {
    const defaultOption = { value: '', dataset: {} };
    const a4Option = { value: '7', dataset: { labelWidth: '69', labelHeight: '49' } };
    const select = {
        value: '7',
        options: [defaultOption, a4Option],
        selectedOptions: [defaultOption],
    };
    const width = input('50');
    const height = input('30');
    const labelSize = { querySelectorAll: () => [width, height] };

    synchronizeLabelProfileSheet(select, width, height, labelSize);

    assert.equal(width.value, '69');
    assert.equal(height.value, '49');
    assert.equal(width.readOnly, true);
    assert.equal(height.readOnly, true);
    assert.equal(width.hasClass('bg-body-secondary'), true);
});

test('does not make DYMO dimensions editable when Default sheet is selected', () => {
    const select = { value: '', options: [{ value: '', dataset: {} }] };
    const width = input('40');
    const height = input('20');
    width.dataset.dymoReadOnly = 'true';
    height.dataset.dymoReadOnly = 'true';
    const labelSize = { querySelectorAll: () => [width, height] };

    synchronizeLabelProfileSheet(select, width, height, labelSize);

    assert.equal(width.readOnly, true);
    assert.equal(height.readOnly, true);
    assert.equal(width.hasClass('bg-body-secondary'), true);
});

test('keeps current dimensions editable for Default', () => {
    const select = { value: '', options: [{ value: '', dataset: {} }] };
    const width = input('40');
    const height = input('20');
    const labelSize = { querySelectorAll: () => [width, height] };

    synchronizeLabelProfileSheet(select, width, height, labelSize);

    assert.equal(width.value, '40');
    assert.equal(height.value, '20');
    assert.equal(width.readOnly, false);
    assert.equal(width.hasClass('bg-body-secondary'), false);
});

test('subscribes to the enhanced Tom Select change event', () => {
    let tomSelectCallback;
    let removedCallback;
    const select = {
        addEventListener() {},
        removeEventListener() {},
        tomselect: {
            on(event, callback) { assert.equal(event, 'change'); tomSelectCallback = callback; },
            off(event, callback) { assert.equal(event, 'change'); removedCallback = callback; },
        },
    };
    let calls = 0;
    const callback = () => calls++;
    const disconnect = bindLabelProfileSheetChange(select, callback, (scheduled) => { scheduled(); return 1; }, () => {});

    tomSelectCallback();
    assert.equal(calls, 1);
    disconnect();
    assert.equal(removedCallback, callback);
});
