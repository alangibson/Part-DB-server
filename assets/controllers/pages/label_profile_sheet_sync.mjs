export function synchronizeLabelProfileSheet(select, labelWidth, labelHeight, labelSize) {
    const readOnly = select.value !== '';
    const selectedSheet = Array.from(select.options).find((option) => option.value === select.value);

    if (readOnly && selectedSheet) {
        labelWidth.value = selectedSheet.dataset.labelWidth;
        labelHeight.value = selectedSheet.dataset.labelHeight;
    }

    labelSize.querySelectorAll('input').forEach((input) => {
        input.readOnly = readOnly;
        input.classList.toggle('bg-body-secondary', readOnly);
        input.setAttribute('aria-readonly', readOnly ? 'true' : 'false');
    });
}

export function bindLabelProfileSheetChange(select, callback, schedule = setTimeout, cancel = clearTimeout) {
    select.addEventListener('change', callback);
    let tomSelect;
    const timer = schedule(() => {
        tomSelect = select.tomselect;
        tomSelect?.on('change', callback);
    }, 0);

    return () => {
        cancel(timer);
        select.removeEventListener('change', callback);
        tomSelect?.off('change', callback);
    };
}
