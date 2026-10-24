

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('dateInput', (initial = '') => ({
    iso: initial || '',
    text: '',
    open: false,
    cursor: '',
    panelStyle: '',
    calendarDays: [],
    weekdayNames: ['Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb', 'Mg'],
    monthNames: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
    init() {
        this.applyIso(this.iso);
        this.cursor = (this.iso || this.todayIso()).slice(0, 7);
        this.refreshDays();
        this.$watch('iso', (value) => {
            if (document.activeElement === this.$refs.text) {
                return;
            }

            this.text = this.toDisplay(this.normalize(value));
        });
        this.$el.closest('form')?.addEventListener('submit', () => this.commitText(), true);
        this._reposition = () => {
            if (this.open) {
                this.place();
            }
        };
        window.addEventListener('scroll', this._reposition, true);
        window.addEventListener('resize', this._reposition);
    },
    destroy() {
        window.removeEventListener('scroll', this._reposition, true);
        window.removeEventListener('resize', this._reposition);
    },
    mask() {
        if (this.text.includes('/')) {
            return;
        }

        const digits = this.text.replace(/\D/g, '').slice(0, 8);

        if (digits.length <= 2) {
            this.text = digits;
        } else if (digits.length <= 4) {
            this.text = `${digits.slice(0, 2)}/${digits.slice(2)}`;
        } else {
            this.text = `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
        }
    },
    commitText() {
        const raw = this.text.trim();

        if (raw === '') {
            this.iso = '';
            this.$refs.text.setCustomValidity('');

            return;
        }

        const iso = this.parseDisplay(raw);

        if (iso === null) {
            this.iso = '';
            this.$refs.text.setCustomValidity('Gunakan format dd/mm/yyyy.');

            return;
        }

        this.iso = iso;
        this.text = this.toDisplay(iso);
        this.$refs.text.setCustomValidity('');
    },
    applyIso(value) {
        this.iso = this.normalize(value);
        this.text = this.toDisplay(this.iso);
        this.$refs.text?.setCustomValidity('');
    },
    toggle() {
        if (this.open) {
            this.open = false;

            return;
        }

        this.cursor = (this.normalize(this.iso) || this.todayIso()).slice(0, 7);
        this.refreshDays();
        this.place();
        this.open = true;
    },
    place() {
        const rect = this.$refs.opener.getBoundingClientRect();
        const width = 288;
        const height = 320;
        const left = Math.min(Math.max(8, rect.right - width), window.innerWidth - width - 8);
        let top = rect.bottom + 4;

        if (top + height > window.innerHeight) {
            top = Math.max(8, rect.top - height - 4);
        }

        this.panelStyle = `top:${top}px;left:${left}px`;
    },
    shiftMonth(delta) {
        const [year, month] = this.cursor.split('-').map(Number);
        const next = new Date(year, month - 1 + delta, 1);

        this.cursor = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`;
        this.refreshDays();
    },
    heading() {
        const [year, month] = this.cursor.split('-').map(Number);

        return `${this.monthNames[month - 1] ?? ''} ${year}`;
    },
    refreshDays() {
        this.calendarDays = this.buildDays();
    },
    buildDays() {
        if (! /^\d{4}-\d{2}$/.test(this.cursor)) {
            return [];
        }

        const [year, month] = this.cursor.split('-').map(Number);
        const first = new Date(year, month - 1, 1);
        const offset = (first.getDay() + 6) % 7;
        const start = new Date(year, month - 1, 1 - offset);
        const today = this.todayIso();
        const days = [];

        for (let index = 0; index < 42; index++) {
            const date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + index);
            const iso = this.isoFromDate(date);

            days.push({
                iso,
                label: date.getDate(),
                inMonth: date.getMonth() === month - 1,
                disabled: this.isDisabled(iso),
                selected: iso === this.iso,
                today: iso === today,
            });
        }

        return days;
    },
    choose(iso) {
        if (this.isDisabled(iso)) {
            return;
        }

        this.applyIso(iso);
        this.open = false;
    },
    minimum() {
        return this.normalize(this.$refs.opener?.getAttribute('min') ?? '');
    },
    isDisabled(iso) {
        const min = this.minimum();

        return min !== '' && iso < min;
    },
    todayIso() {
        return this.isoFromDate(new Date());
    },
    isoFromDate(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    },
    normalize(value) {
        const raw = String(value ?? '').trim();

        if (/^\d{4}-\d{2}-\d{2}/.test(raw)) {
            return raw.slice(0, 10);
        }

        return this.parseDisplay(raw) ?? '';
    },
    parseDisplay(value) {
        const match = String(value).trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);

        if (! match) {
            return null;
        }

        const day = Number(match[1]);
        const month = Number(match[2]);
        const year = Number(match[3]);
        const probe = new Date(year, month - 1, day);

        if (probe.getFullYear() !== year || probe.getMonth() !== month - 1 || probe.getDate() !== day) {
            return null;
        }

        return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    },
    toDisplay(iso) {
        if (! /^\d{4}-\d{2}-\d{2}$/.test(iso)) {
            return '';
        }

        const [year, month, day] = iso.split('-');

        return `${day}/${month}/${year}`;
    },
}));

Alpine.start();
