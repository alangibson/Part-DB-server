/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['format', 'labelSize'];
    static values = {dymoFormat: String};

    connect() {
        this.update();
    }

    update() {
        const dymoSelected = this.formatTarget.value === this.dymoFormatValue;

        this.labelSizeTarget.classList.toggle('opacity-50', dymoSelected);
        this.labelSizeTarget.setAttribute('aria-disabled', dymoSelected.toString());

        for (const input of this.labelSizeTarget.querySelectorAll('input')) {
            input.dataset.dymoReadOnly = dymoSelected.toString();
            const readOnly = dymoSelected || input.dataset.labelSheetReadOnly === 'true';
            input.readOnly = readOnly;
            input.classList.toggle('bg-body-secondary', readOnly);
            input.setAttribute('aria-readonly', readOnly.toString());
        }
    }
}
