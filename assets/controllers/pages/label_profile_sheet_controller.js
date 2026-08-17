import { Controller } from '@hotwired/stimulus';
import { bindLabelProfileSheetChange, synchronizeLabelProfileSheet } from './label_profile_sheet_sync.mjs';

export default class extends Controller {
    static targets = ['sheet', 'labelSize', 'labelWidth', 'labelHeight'];

    connect() {
        this.boundToggle = this.toggle.bind(this);
        this.unbindChange = bindLabelProfileSheetChange(this.sheetTarget, this.boundToggle);
        this.toggle();
    }

    disconnect() {
        this.unbindChange?.();
    }

    toggle() {
        synchronizeLabelProfileSheet(
            this.sheetTarget,
            this.labelWidthTarget,
            this.labelHeightTarget,
            this.labelSizeTarget,
        );
    }
}
