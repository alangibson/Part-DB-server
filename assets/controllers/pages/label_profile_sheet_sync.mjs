export function synchronizeLabelProfileSheet(select, labelWidth, labelHeight, labelSize) {
    const readOnly = select.value !== '';
    const selectedSheet = Array.from(select.options).find((option) => option.value === select.value);

    if (readOnly && selectedSheet) {
        labelWidth.value = selectedSheet.dataset.labelWidth;
        labelHeight.value = selectedSheet.dataset.labelHeight;
    }

    labelSize.querySelectorAll('input').forEach((input) => {
        input.dataset ??= {};
        input.dataset.labelSheetReadOnly = readOnly.toString();
        const combinedReadOnly = readOnly || input.dataset.dymoReadOnly === 'true';
        input.readOnly = combinedReadOnly;
        input.classList.toggle('bg-body-secondary', combinedReadOnly);
        input.setAttribute('aria-readonly', combinedReadOnly.toString());
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
