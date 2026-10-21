document.addEventListener('alpine:init', () => {
    Alpine.data('formattedPriceInput', (wire, property, live = false) => ({
        wire,
        property,
        live,
        state: wire.entangle(property),
        syncTimer: null,
        lastSyncedRaw: '',

        init() {
            this.lastSyncedRaw = this.normalize(this.state);
            this.$el.value = this.format(this.state);
        },

        normalize(value) {
            return Array.from(String(value ?? ''), (character) => {
                const code = character.codePointAt(0);

                if (code >= 0x06f0 && code <= 0x06f9) return String(code - 0x06f0);
                if (code >= 0x0660 && code <= 0x0669) return String(code - 0x0660);

                return character;
            }).join('').replace(/\D/g, '');
        },

        format(value) {
            return this.normalize(value).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        input(event) {
            const input = event.target;
            const digitsBeforeCaret = this.normalize(input.value.slice(0, input.selectionStart)).length;
            const rawValue = this.normalize(input.value);

            input.value = this.format(rawValue);

            let caret = 0;
            let digitsAtCaret = 0;

            while (caret < input.value.length && digitsAtCaret < digitsBeforeCaret) {
                if (/\d/.test(input.value[caret])) digitsAtCaret++;
                caret++;
            }

            input.setSelectionRange(caret, caret);
            this.lastSyncedRaw = rawValue;
            this.state = rawValue === '' ? null : rawValue;

            if (this.live) {
                clearTimeout(this.syncTimer);
                this.syncTimer = setTimeout(() => this.sync(), 1500);
            }
        },

        sync() {
            clearTimeout(this.syncTimer);
            const rawValue = this.normalize(this.$el.value);
            this.lastSyncedRaw = rawValue;
            this.state = rawValue === '' ? null : rawValue;
            this.wire.set(this.property, rawValue === '' ? null : rawValue, true);
        },

        refresh(value) {
            const rawValue = this.normalize(value);

            if (rawValue === this.lastSyncedRaw) return;

            this.lastSyncedRaw = rawValue;
            this.$el.value = this.format(rawValue);
        },
    }));
});
