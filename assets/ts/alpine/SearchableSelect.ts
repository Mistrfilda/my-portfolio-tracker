interface SelectOption {
	value: string;
	label: string;
}

interface AutocompleteElement extends HTMLElement {
	filterOptions(): void;
}

interface SearchableSelectState {
	options: SelectOption[];
	value: string;
	label: string;
	disabled: boolean;
	required: boolean;
	invalid: boolean;
	description: string | null;
	empty: boolean;
	form: HTMLFormElement | null;
	observer: MutationObserver | null;
	resetHandler: (() => void) | null;
	init(): void;
	destroy(): void;
	refresh(): void;
	syncValue(): void;
	startSearch(): void;
	search(event: Event): void;
	choose(value: string): void;
	chooseActive(): void;
	restore(): void;
}

type SearchableSelect = SearchableSelectState & {
	$refs: {
		autocomplete: AutocompleteElement;
		control: HTMLSelectElement;
		input: HTMLInputElement;
		options: HTMLElement;
	};
	$nextTick(callback: () => void): void;
};

export default function searchableSelect(): SearchableSelectState {
	return {
		options: [] as SelectOption[],
		value: '',
		label: '',
		disabled: false,
		required: false,
		invalid: false,
		description: null,
		empty: false,
		form: null,
		observer: null as MutationObserver | null,
		resetHandler: null as (() => void) | null,

		init(this: SearchableSelect): void {
			this.$nextTick(() => {
				this.refresh();
				this.observer = new MutationObserver(() => this.refresh());
				this.observer.observe(this.$refs.control, {
					attributes: true,
					attributeFilter: ['disabled', 'required', 'class', 'selected', 'label', 'value', 'aria-describedby', 'data-lfv-message-id'],
					childList: true,
					subtree: true,
				});
				this.resetHandler = () => queueMicrotask(() => this.refresh());
				this.form = this.$refs.control.form;
				this.form?.addEventListener('reset', this.resetHandler);
			});
		},

		destroy(this: SearchableSelect): void {
			this.observer?.disconnect();
			if (this.resetHandler) {
				this.form?.removeEventListener('reset', this.resetHandler);
			}
		},

		refresh(this: SearchableSelect): void {
			const control = this.$refs.control;
			this.options = Array.from(control.options)
				.filter((option) => !option.disabled && !option.hidden
					&& !(option.parentElement instanceof HTMLOptGroupElement && option.parentElement.disabled))
				.map((option) => ({ value: option.value, label: option.label || '-- vyberte --' }));
			this.disabled = control.disabled;
			this.required = control.required;
			this.invalid = control.classList.contains('form-input-error');
			this.description = control.getAttribute('aria-describedby') ?? control.getAttribute('data-lfv-message-id');
			this.syncValue();
		},

		syncValue(this: SearchableSelect): void {
			this.value = this.$refs.control.value;
			this.label = this.$refs.control.selectedOptions[0]?.label ?? '';
			this.restore();
		},

		startSearch(this: SearchableSelect): void {
			if (this.disabled || this.$refs.options.hasAttribute('open')) {
				return;
			}
			this.$refs.input.value = '';
			this.empty = this.options.length === 0;
			this.$refs.autocomplete.filterOptions?.();
		},

		search(this: SearchableSelect, event: Event): void {
			if (event.target !== this.$refs.input || this.disabled) {
				return;
			}
			this.empty = !this.$refs.options.querySelector('el-option:not([hidden])');
			if (this.empty) {
				this.$refs.options.showPopover();
			}
		},

		choose(this: SearchableSelect, value: string): void {
			if (this.disabled || !this.options.some((option) => option.value === value)) {
				return;
			}
			const control = this.$refs.control;
			const changed = control.value !== value;
			control.value = value;
			this.syncValue();
			if (changed) {
				control.dispatchEvent(new Event('input', { bubbles: true }));
				control.dispatchEvent(new Event('change', { bubbles: true }));
			}
		},

		chooseActive(this: SearchableSelect): void {
			const id = this.$refs.input.getAttribute('aria-activedescendant');
			const option = id ? document.getElementById(id) : null;
			if (option && this.$refs.options.contains(option) && option.dataset.value !== undefined) {
				this.choose(option.dataset.value);
			}
		},

		restore(this: SearchableSelect): void {
			this.$refs.input.value = this.label;
			this.empty = false;
		},
	};
}
