//ALPINE
//@ts-ignore
import Alpine from 'alpinejs';

// @ts-ignore
import {ChartRenderer} from "../chart/ChartRenderer";
import {ChartType} from "../chart/ChartType";
import {ChartInstance} from "../chart/ChartInstance";

import naja from 'naja';
import {registerExtensions} from "../naja/extension";
import {CompleteEvent} from "naja/dist/Naja";

naja.initialize();
registerExtensions(naja);

let loadedCharts: Array<ChartInstance> = [];
let chartRenderer = new ChartRenderer(naja, loadedCharts);

naja.addEventListener(
    'complete',
    (event: CompleteEvent) => {
        if (event.detail.request.url.includes('getChartData')) {
            return;
        }

        let startUrl = new URL(event.detail.request.url);

        // Získáme parametry z původní URL
        let params = new URLSearchParams(startUrl.search);

        for (let i = 0; i < loadedCharts.length; i++) {
            // URL adresa, kam chcete odeslat GET požadavek
            let targetUrl = new URL(loadedCharts[i].chartDataUrl, startUrl.origin);

            // Přidáme další parametry dotazu
            for(let pair of params.entries()) {
                let name = 'originalRequest' + pair[0];
                targetUrl.searchParams.append(name, pair[1]);
            }

            // console.log(targetUrl);
            chartRenderer.updateChart(loadedCharts[i].chart, targetUrl.toString()).then(() => {
                console.log('what?');
            });
        }
    }
);

Alpine.data('frontMenu', () => ({
    show: false,
    click() {
        this.show = !this.show
    },
    isOpen() {
        return this.show;
    }
}));

Alpine.data('dropdown', () => ({
    open: false,

    toggle() {
        this.open = !this.open
    }
}));

Alpine.data('flashMessage', () => ({
    open: true,

    close() {
        this.open = false;
    }
}));


Alpine.data('select', (config: any) => ({
    data: config.data,

    emptyOptionsMessage: config.emptyOptionsMessage ?? 'Nebyl nalezen žádný výsledek',

    // @ts-ignore
    focusedOptionIndex: null,

    name: config.name,

    open: false,

    options: {},

    placeholder: config.placeholder ?? '-- vyberte --',

    search: '',

    value: config.value,

    closeListbox: function () {
        this.open = false

        this.focusedOptionIndex = null

        this.search = ''
    },

    focusNextOption: function () {
        if (this.focusedOptionIndex === null) return this.focusedOptionIndex = Object.keys(this.options).length - 1

        if (this.focusedOptionIndex + 1 >= Object.keys(this.options).length) return

        this.focusedOptionIndex++

        this.$refs.listbox.children[this.focusedOptionIndex].scrollIntoView({
            block: "center",
        })
    },

    focusPreviousOption: function () {
        if (this.focusedOptionIndex === null) return this.focusedOptionIndex = 0

        if (this.focusedOptionIndex <= 0) return

        this.focusedOptionIndex--

        this.$refs.listbox.children[this.focusedOptionIndex].scrollIntoView({
            block: "center",
        })
    },

    init: function () {
        this.options = this.data

        if (!(this.value in this.options)) this.value = null

        this.$watch('search', ((value: string) => {
            if (!this.open || !value) return this.options = this.data

            this.options = Object.keys(this.data)
                .filter((key) => this.data[key].toLowerCase().includes(value.toLowerCase()))
                .reduce((options, key) => {
                    // @ts-ignore
                    options[key] = this.data[key]
                    return options
                }, {})
        }))
    },

    selectOption: function () {
        if (!this.open) {
            return this.toggleListboxVisibility();
        }

        this.value = Object.keys(this.options)[this.focusedOptionIndex];

        this.closeListbox();
    },

    toggleListboxVisibility: function () {
        if (this.open) return this.closeListbox()

        this.focusedOptionIndex = Object.keys(this.options).indexOf(this.value)

        if (this.focusedOptionIndex < 0) this.focusedOptionIndex = 0

        this.open = true

        this.$nextTick(() => {
            this.$refs.search.focus()
        })
    }
}));

Alpine.data('datagridFilter', () => ({
    show: false,

    click() {
        this.show = !this.show;
    }
}));

Alpine.data('modal', () => ({
    modalOpen: true,

    closeModal() {
        this.modalOpen = false;
    },
    openModal() {
      this.modalOpen = true;
    }
}));

Alpine.data('loadChart', () => ({
    show: true,
    loadGraph(chartId: any, chartDataUrl: string, type: ChartType, shouldUpdateOnAjaxRequest: number): boolean {
        let chartCanvasElement = <HTMLCanvasElement>document.getElementById(chartId);
        let shouldUpdateOnAjaxRequestValue = Boolean(Number(shouldUpdateOnAjaxRequest));

        if (type.valueOf() === ChartType.LINE.valueOf()) {
            chartRenderer.createLineChart(chartCanvasElement, chartDataUrl, chartId, shouldUpdateOnAjaxRequestValue)
            return true;
        }

        if (type.valueOf() === ChartType.DOUGHNUT.valueOf()) {
            chartRenderer.createDoughnutCharts(chartCanvasElement, chartDataUrl, chartId, shouldUpdateOnAjaxRequestValue)
            return true;
        }

        if (type.valueOf() === ChartType.BAR.valueOf()) {
            chartRenderer.createBarCharts(chartCanvasElement, chartDataUrl, chartId, shouldUpdateOnAjaxRequestValue)
            return true;
        }

        throw new Error('Invalid chart type passed');
    }
}));

Alpine.data('expenseMainTag', (expenseId: string, handleLink: string) => ({
    mainTagId: '',

    init() {
      console.log(expenseId);
    },
    click() {
        if (this.mainTagId === '') {
            return;
        }

        handleLink = handleLink.replace("replaceTagId", this.mainTagId);
        handleLink = handleLink.replace("replaceExpenseId", expenseId);

        naja.makeRequest(
            'GET',
            handleLink,
            null,
            {
                history: false,
                responseType: 'json',
                unique: false
            },
        );
    }
}));

Alpine.data('addExpenseOtherTag', (expenseId: string, handleLink: string) => ({
    mainTagId: '',

    init() {
        console.log(expenseId);
    },
    click() {
        if (this.mainTagId === '') {
            return;
        }

        handleLink = handleLink.replace("replaceTagId", this.mainTagId);
        handleLink = handleLink.replace("replaceExpenseId", expenseId);

        naja.makeRequest(
            'GET',
            handleLink,
            null,
            {
                history: false,
                responseType: 'json',
                unique: false
            },
        );
    }
}));

Alpine.data('removeOtherTag', (expenseId: string, handleLink: string) => ({
    click(tagId: string) {
        if (tagId === '') {
            return;
        }

        handleLink = handleLink.replace("replaceTagId", tagId);
        handleLink = handleLink.replace("replaceExpenseId", expenseId);

        naja.makeRequest(
            'GET',
            handleLink,
            null,
            {
                history: false,
                responseType: 'json',
                unique: false
            },
        );
    }
}));

Alpine.data('currencyConvert', (handleLink: string) => ({
    fromCurrency: 'CZK',
    amount: 0,

    click() {
        handleLink = handleLink.replace("replaceFromCurrency", this.fromCurrency);
        handleLink = handleLink.replace("replaceAmount", this.amount);

        naja.makeRequest(
            'GET',
            handleLink,
            null,
            {
                history: false,
                responseType: 'json',
                unique: false
            },
        );
    }
}));

window.Alpine = Alpine;
Alpine.start();
