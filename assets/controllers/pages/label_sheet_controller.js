/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {Controller} from "@hotwired/stimulus";
import {convertDimensionInputs, updateDimensionUnitSuffixes} from "./dimension_unit_sync.mjs";

/* stimulusFetch: 'lazy' */

export default class extends Controller {
    static targets = ["unit", "pageSize", "width", "height", "dimension", "unitSuffix"];

    connect() {
        this.previousUnit = this.unitTarget.value;
        this.updateUnitSuffix();
    }

    applyPreset(event) {
        const size = this.pageSizeTarget.value;
        const pageSizeChanged = event?.currentTarget === this.pageSizeTarget;
        if (pageSizeChanged) {
            this.unitTarget.value = size === "LETTER" ? "inch" : "mm";
        }
        const unit = this.unitTarget.value;
        convertDimensionInputs(this.dimensionTargets, this.previousUnit, unit);
        this.previousUnit = unit;
        this.updateUnitSuffix();
        let dimensions = null;

        if (size === "A4") {
            dimensions = unit === "inch" ? [210 / 25.4, 297 / 25.4] : [210, 297];
        } else if (size === "LETTER") {
            dimensions = unit === "inch" ? [8.5, 11] : [8.5 * 25.4, 11 * 25.4];
        }

        if (!dimensions) return;

        this.widthTarget.value = this.format(dimensions[0]);
        this.heightTarget.value = this.format(dimensions[1]);
        this.widthTarget.dispatchEvent(new Event("input", {bubbles: true}));
        this.heightTarget.dispatchEvent(new Event("input", {bubbles: true}));
    }

    format(value) {
        return Number(value.toFixed(4)).toString();
    }

    updateUnitSuffix() {
        updateDimensionUnitSuffixes(this.unitSuffixTargets, this.unitTarget.value);
    }
}
