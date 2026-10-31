//ALPINE
// @ts-expect-error alpinejs does not provide TypeScript declarations.
import Alpine from 'alpinejs';

import {ChartRenderer} from "../chart/ChartRenderer";
import {ChartType} from "../chart/ChartType";
import {ChartInstance} from "../chart/ChartInstance";

import naja from 'naja';
import {registerExtensions} from "../naja/extension";
import type {CompleteEvent} from "naja";
import dragScroll from "./DragScroll";
import datagrid from "./Datagrid";
import searchableSelect from "./SearchableSelect";
import expenseTagAction from "./ExpenseTagAction";

naja.initialize();
registerExtensions(naja);

const loadedCharts: Array<ChartInstance> = [];
const chartRenderer = new ChartRenderer(naja, loadedCharts);

// ChartControl keeps chart canvases outside AJAX snippets; only their data is refreshed here.
naja.addEventListener(
    'complete',
    (event: CompleteEvent) => {
        if (event.detail.request.url.includes('getChartData')) {
            return;
        }

        const startUrl = new URL(event.detail.request.url);

        // Získáme parametry z původní URL
        const params = new URLSearchParams(startUrl.search);
        console.log(params);

        for (let i = 0; i < loadedCharts.length; i++) {
            // URL adresa, kam chcete odeslat GET požadavek
            const targetUrl = new URL(loadedCharts[i].chartDataUrl, startUrl.origin);

            // Přidáme další parametry dotazu
            for(const pair of params.entries()) {
                const name = 'originalRequest' + pair[0];
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


Alpine.data('searchableSelect', searchableSelect);

Alpine.data('datagrid', datagrid);

Alpine.data('stockAiAnalysisDetail', () => ({
	init() {
		this.openLinkedAnalysis();
	},
	openLinkedAnalysis(hash: string = window.location.hash) {
		if (!hash.startsWith('#analysis-')) return;
		const detail = document.getElementById(hash.slice(1));
		if (detail instanceof HTMLDetailsElement) {
			detail.open = true;
			detail.querySelector('summary')?.focus({preventScroll: true});
			detail.scrollIntoView({block: 'start'});
		}
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

Alpine.data('loadChart', (changeFromStartEnabled: number = 0) => ({
    show: true,
    changeFromStart: Boolean(Number(changeFromStartEnabled)),
    resetGraphZoom(chartId: string): void {
        chartRenderer.resetZoom(chartId);
    },
    setGraphChangeFromStart(chartId: string, changeFromStart: boolean): void {
        this.changeFromStart = changeFromStart;
        chartRenderer.setLineChartChangeFromStart(chartId, changeFromStart);
    },
    loadGraph(chartId: string, chartDataUrl: string, type: ChartType, shouldUpdateOnAjaxRequest: number): boolean {
        const chartCanvasElement = <HTMLCanvasElement>document.getElementById(chartId);
        const shouldUpdateOnAjaxRequestValue = Boolean(Number(shouldUpdateOnAjaxRequest));

        if (type.valueOf() === ChartType.LINE.valueOf()) {
            chartRenderer.createLineChart(
                chartCanvasElement,
                chartDataUrl,
                chartId,
                shouldUpdateOnAjaxRequestValue,
                this.changeFromStart,
            );
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

Alpine.data('expenseMainTag', expenseTagAction);
Alpine.data('addExpenseOtherTag', expenseTagAction);
Alpine.data('removeOtherTag', expenseTagAction);

Alpine.data('currencyConvert', (handleLink: string, amount: string, fromCurrency: string) => ({
    fromCurrency: fromCurrency,
    amount: amount,

    isValidAmount(): boolean {
        const normalizedAmount = String(this.amount).trim();
        if (normalizedAmount === '') {
            return false;
        }

        const parsedAmount = Number(normalizedAmount);

        return Number.isFinite(parsedAmount) && parsedAmount >= 0;
    },

    submit() {
        if (!this.isValidAmount()) {
            return;
        }

        const requestLink = handleLink
            .replace("replaceFromCurrency", encodeURIComponent(this.fromCurrency))
            .replace("replaceAmount", encodeURIComponent(String(this.amount)));

        naja.makeRequest(
            'GET',
            requestLink,
            null,
            {
                history: false,
                responseType: 'json',
                unique: false
            },
        );
    }
}));

Alpine.data('currencyQuickConversions', (initialCurrency: string) => ({
    activeCurrency: initialCurrency,

    isActive(currency: string): boolean {
        return this.activeCurrency === currency;
    },

    selectCurrency(currency: string): void {
        this.activeCurrency = currency;
    }
}));

Alpine.data('valuationTable', () => ({
    query: '',
    currencyMode: 'original',
    statusFilter: 'all',
    matrixOpen: false,

    matches(value: string, status: string = ''): boolean {
        const normalizedQuery = this.query.trim().toLocaleLowerCase();
        const matchesQuery = normalizedQuery === '' || value.toLocaleLowerCase().includes(normalizedQuery);
        const matchesStatus = this.statusFilter === 'all' || status === this.statusFilter;

        return matchesQuery && matchesStatus;
    }
}));

Alpine.data('stockValuationModelData', (totalColumns: number) => ({
    open: false,
    hiddenColumns: [] as number[],
    totalColumns: totalColumns,
    toggle() {
        if (this.open) {
            return this.close()
        }
        this.$refs.button.focus()
        this.open = true
    },
    close(focusAfter?: HTMLElement) {
        if (!this.open) return
        this.open = false
        if (focusAfter) {
            focusAfter.focus()
        }
    },
    toggleColumn(index: number) {
        if (this.hiddenColumns.includes(index)) {
            const idx = this.hiddenColumns.indexOf(index);
            this.hiddenColumns.splice(idx, 1);
        } else {
            this.hiddenColumns.push(index);
        }
    },
    isHidden(index: number): boolean {
        return this.hiddenColumns.includes(index);
    },
    showAll() {
        this.hiddenColumns = [];
    },
    hideAll() {
        this.hiddenColumns = Array.from({length: this.totalColumns - 1}, (_, i) => i + 1);
    }
}));

Alpine.data('dragScroll', dragScroll);

window.Alpine = Alpine;
Alpine.start();
