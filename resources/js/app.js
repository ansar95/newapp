

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('dateInput', (initial = '') => ({
    iso: initial || '',
    text: '',
    init() {
        this.applyIso(this.iso);
        this.$watch('iso', (value) => {
            if (document.activeElement === this.$refs.text) {
                return;
            }

            this.text = this.toDisplay(this.normalize(value));
        });
        this.$el.closest('form')?.addEventListener('submit', () => this.commitText(), true);
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
    openPicker() {
        const picker = this.$refs.picker;

        if (typeof picker.showPicker === 'function') {
            picker.showPicker();

            return;
        }

        picker.focus();
        picker.click();
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
