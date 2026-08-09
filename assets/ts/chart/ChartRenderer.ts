import {Naja, Payload} from "naja/dist/Naja";
import {ChartData, ChartDataset} from "./ChartData";
import Chart, {ChartOptions, ChartType, Colors, Plugin, TooltipItem} from 'chart.js/auto';
import zoomPlugin from 'chartjs-plugin-zoom';
import {ChartInstance} from "./ChartInstance";

interface LineChartState {
    absoluteData: number[][];
    changeFromStart: boolean;
}


export class ChartRenderer {
    naja: Naja;

    defaultBackgroundColor: string;

    tooltipDefaults: object;

    loadedCharts: Array<ChartInstance>;

    lineChartStates: Map<Chart, LineChartState>;

    constructor(naja: Naja, loadedCharts: Array<ChartInstance>) {
        this.naja = naja;
        this.defaultBackgroundColor = '#111827';
        this.tooltipDefaults = this.getTooltipDefaults();
        this.loadedCharts = loadedCharts;
        this.lineChartStates = new Map<Chart, LineChartState>();

        this.setDefaults();
    }

    setDefaults() {
        Chart.defaults.font.family = 'ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"';
        Chart.defaults.color = '#4b5563';
        Chart.defaults.locale = 'cs-CZ';
        Chart.register(Colors, zoomPlugin);
    }

    isSmallViewport(): boolean {
        return window.matchMedia('(max-width: 639px)').matches;
    }

    formatTickValue(value: string | number): string {
        const numericValue = Number(value);

        if (!Number.isFinite(numericValue)) {
            return String(value);
        }

        return new Intl.NumberFormat('cs-CZ', {
            notation: this.isSmallViewport() ? 'compact' : 'standard',
            maximumFractionDigits: this.isSmallViewport() ? 1 : 0,
        }).format(numericValue);
    }

    getTooltipDefaults() {
        return {
            backgroundColor: 'rgba(255, 255, 255, 0.96)',
            titleColor: this.defaultBackgroundColor,
            titleMarginBottom: 8,
            titleFont: {
                size: 13,
                weight: 600,
            },
            bodyColor: '#4b5563',
            bodyFont: {
                size: 12,
            },
            borderColor: '#e5e7eb',
            borderWidth: 1,
            padding: 12,
            displayColors: true,
            usePointStyle: true,
            boxPadding: 4,
            caretPadding: 8,
        }
    }

    getTooltipOptions(response: ChartData) {
        return {
            ...this.tooltipDefaults,
            callbacks: {
                label: (tooltipItem: TooltipItem<ChartType>): string | string[] | void => {
                    let label = tooltipItem.dataset.label || '';
                    const lineChartState = this.lineChartStates.get(tooltipItem.chart);
                    const absoluteValue = lineChartState?.absoluteData[tooltipItem.datasetIndex]?.[tooltipItem.dataIndex];

                    if (label && lineChartState?.changeFromStart && absoluteValue !== undefined) {
                        const suffix = response.tooltipSuffix.trim();
                        const suffixWithSpace = suffix === '' ? '' : ` ${suffix}`;
                        const change = Number(tooltipItem.raw);

                        return `${label}: ${this.formatNumber(absoluteValue)}${suffixWithSpace} (${this.formatSignedNumber(change)}${suffixWithSpace} od začátku)`;
                    }

                    if (label) {
                        label = label + ' ' + tooltipItem.formattedValue + ' ' + response.tooltipSuffix;
                    }

                    return label;
                }
            }
        };
    }

    formatNumber(value: number): string {
        return new Intl.NumberFormat('cs-CZ', {
            maximumFractionDigits: 2,
        }).format(value);
    }

    formatSignedNumber(value: number): string {
        if (value === 0) {
            return '0';
        }

        return new Intl.NumberFormat('cs-CZ', {
            maximumFractionDigits: 2,
            signDisplay: 'always',
        }).format(value);
    }

    getBaseOptions(response: ChartData): ChartOptions {
        return {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: 4,
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 8,
                        boxHeight: 8,
                        usePointStyle: true,
                        color: '#374151',
                        font: {
                            size: 12,
                            weight: 500,
                        },
                    },
                },
                tooltip: this.getTooltipOptions(response),
            },
        };
    }

    getCartesianOptions(response: ChartData): ChartOptions {
        return {
            ...this.getBaseOptions(response),
            interaction: {
                intersect: false,
                mode: 'index',
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                    },
                    ticks: {
                        color: '#6b7280',
                        maxRotation: 0,
                        autoSkipPadding: 16,
                    },
                    border: {
                        display: false,
                    },
                },
                y: {
                    grid: {
                        color: '#f3f4f6',
                    },
                    ticks: {
                        color: '#6b7280',
                        padding: 8,
                        callback: (value: string | number) => this.formatTickValue(value),
                    },
                    border: {
                        display: false,
                    },
                },
            },
        };
    }

    getLineOptions(response: ChartData): ChartOptions {
        const options = this.getCartesianOptions(response);

        return {
            ...options,
            plugins: {
                ...options.plugins,
                zoom: {
                    pan: {
                        enabled: true,
                        mode: 'x',
                        modifierKey: 'ctrl',
                    },
                    zoom: {
                        drag: {
                            enabled: true,
                            backgroundColor: 'rgba(17, 24, 39, 0.12)',
                            borderColor: 'rgba(17, 24, 39, 0.35)',
                            borderWidth: 1,
                        },
                        mode: 'x',
                    },
                },
            },
        };
    }

    async createLineChart(
        graphCanvasElement: HTMLCanvasElement,
        chartDataUrl: string,
        chartId: string,
        shouldUpdateOnAjaxRequestValue: boolean,
        changeFromStart: boolean = false,
    ): Promise<void> {
        const graphData = this.fetchData(chartDataUrl);

        graphData.then(function (response: ChartData) {
            const absoluteData = this.getAbsoluteData(response.datasets);
            const myChart = new Chart(graphCanvasElement, {
                type: 'line',
                data: {
                    labels: response.labels,
                    datasets: this.getLineChartDatasets(response.datasets, absoluteData, changeFromStart),
                },
                options: {
                    ...this.getLineOptions(response),
                    elements: {
                        line: {
                            borderWidth: 2,
                            tension: 0.32,
                        },
                        point: {
                            radius: 0,
                            hoverRadius: 4,
                            hitRadius: 12,
                        },
                    },
                },
                plugins: [this.getWeeklyValueLabelsPlugin()],
            });

            this.lineChartStates.set(myChart, {
                absoluteData: absoluteData,
                changeFromStart: changeFromStart,
            });
            myChart.draw();

            if (shouldUpdateOnAjaxRequestValue) {
                this.loadedCharts.push({
                    chart: myChart,
                    chartDataUrl: chartDataUrl
                });
            }

            this.removeGraphSpinner(chartId);
        }.bind(this));
    }

    getAbsoluteData(datasets: ChartDataset[]): number[][] {
        return datasets.map((dataset) => dataset.data.map((value) => Number(value)));
    }

    getLineChartDatasets(datasets: ChartDataset[], absoluteData: number[][], changeFromStart: boolean) {
        return datasets.map((dataset, index) => ({
            ...dataset,
            data: changeFromStart
                ? this.getChangeFromStartData(absoluteData[index])
                : [...absoluteData[index]],
        }));
    }

    getChangeFromStartData(data: number[]): number[] {
        const initialValue = data[0];

        if (initialValue === undefined) {
            return [];
        }

        return data.map((value) => value - initialValue);
    }

    getWeeklyValueLabelsPlugin(): Plugin<'line'> {
        return {
            id: 'weeklyValueLabels',
            afterDatasetsDraw: (chart) => this.drawWeeklyValueLabels(chart),
        };
    }

    drawWeeklyValueLabels(chart: Chart<'line'>): void {
        const lineChartState = this.lineChartStates.get(chart);

        if (lineChartState === undefined) {
            return;
        }

        const labels = (chart.data.labels ?? []).map((label) => String(label));
        const weeklyClosingIndices = this.getVisibleWeeklyClosingIndices(
            this.getWeeklyClosingIndices(labels),
            chart.chartArea.right - chart.chartArea.left,
        );

        chart.data.datasets.forEach((dataset, datasetIndex) => {
            const chartDataset = dataset as typeof dataset & ChartDataset;

            if (!chartDataset.weeklyValueLabels || !chart.isDatasetVisible(datasetIndex)) {
                return;
            }

            const absoluteData = lineChartState.absoluteData[datasetIndex] ?? [];
            const datasetMeta = chart.getDatasetMeta(datasetIndex);
            const context = chart.ctx;

            context.save();
            context.font = '600 10px ui-sans-serif, system-ui, sans-serif';
            context.textAlign = 'center';
            context.textBaseline = 'middle';

            weeklyClosingIndices.forEach((dataIndex) => {
                const point = datasetMeta.data[dataIndex];
                const value = absoluteData[dataIndex];

                if (
                    point === undefined
                    || value === undefined
                    || point.x < chart.chartArea.left
                    || point.x > chart.chartArea.right
                    || point.y < chart.chartArea.top
                    || point.y > chart.chartArea.bottom
                ) {
                    return;
                }

                const text = this.formatWeeklyValue(value);
                const labelWidth = Math.ceil(context.measureText(text).width) + 8;
                const labelHeight = 16;
                const x = Math.min(
                    Math.max(point.x, chart.chartArea.left + labelWidth / 2),
                    chart.chartArea.right - labelWidth / 2,
                );
                let y = point.y - 13;

                if (y - labelHeight / 2 < chart.chartArea.top) {
                    y = point.y + 13;
                }

                context.fillStyle = 'rgba(255, 255, 255, 0.9)';
                context.fillRect(x - labelWidth / 2, y - labelHeight / 2, labelWidth, labelHeight);
                context.fillStyle = '#1d4ed8';
                context.fillText(text, x, y);
            });

            context.restore();
        });
    }

    getWeeklyClosingIndices(labels: string[]): number[] {
        const weeklyClosingIndices = new Map<string, number>();

        labels.forEach((label, index) => {
            const dateParts = label.split('-').map((part) => Number(part));

            if (dateParts.length !== 3 || dateParts.some((part) => !Number.isFinite(part))) {
                return;
            }

            const date = new Date(Date.UTC(dateParts[0], dateParts[1] - 1, dateParts[2]));
            const daysSinceMonday = (date.getUTCDay() + 6) % 7;
            date.setUTCDate(date.getUTCDate() - daysSinceMonday);
            weeklyClosingIndices.set(date.toISOString().slice(0, 10), index);
        });

        return Array.from(weeklyClosingIndices.values());
    }

    getVisibleWeeklyClosingIndices(indices: number[], chartWidth: number): number[] {
        const minimumLabelSpacing = 58;
        const maximumLabelCount = Math.max(1, Math.floor(chartWidth / minimumLabelSpacing));
        const interval = Math.max(1, Math.ceil(indices.length / maximumLabelCount));

        return indices.filter((value, index) => index % interval === 0 || value === indices[indices.length - 1]);
    }

    formatWeeklyValue(value: number): string {
        return new Intl.NumberFormat('cs-CZ', {
            notation: 'compact',
            maximumFractionDigits: 2,
        }).format(value);
    }

    setLineChartChangeFromStart(chartId: string, changeFromStart: boolean): void {
        const chart = Chart.getChart(chartId);

        if (chart === undefined) {
            return;
        }

        const lineChartState = this.lineChartStates.get(chart);

        if (lineChartState === undefined || lineChartState.changeFromStart === changeFromStart) {
            return;
        }

        lineChartState.changeFromStart = changeFromStart;
        chart.data.datasets.forEach((dataset, index) => {
            const absoluteData = lineChartState.absoluteData[index] ?? [];
            dataset.data = changeFromStart
                ? this.getChangeFromStartData(absoluteData)
                : [...absoluteData];
        });
        chart.resetZoom();
        chart.update();
    }

    async createBarCharts(graphCanvasElement: HTMLCanvasElement, chartDataUrl: string, chartId: string, shouldUpdateOnAjaxRequestValue: boolean): Promise<void> {
        const graphData = this.fetchData(chartDataUrl);

        graphData.then(function (response: ChartData) {
            const myChart = new Chart(graphCanvasElement, {
                type: 'bar',
                data: {
                    labels: response.labels,
                    datasets: response.datasets
                },
                options: {
                    ...this.getCartesianOptions(response),
                    datasets: {
                        bar: {
                            borderRadius: 6,
                            borderSkipped: false,
                        },
                    },
                }
            });

            if (shouldUpdateOnAjaxRequestValue) {
                this.loadedCharts.push({
                    chart: myChart,
                    chartDataUrl: chartDataUrl
                });
            }

            this.removeGraphSpinner(chartId);
        }.bind(this));
    }


    async createDoughnutCharts(graphCanvasElement: HTMLCanvasElement, chartDataUrl: string, chartId: string, shouldUpdateOnAjaxRequestValue: boolean): Promise<void> {
        const graphData = this.fetchData(chartDataUrl);

        graphData.then(function (response: ChartData) {
            const isSmallViewport = this.isSmallViewport();
            const myChart = new Chart(graphCanvasElement, {
                type: 'doughnut',
                data: {
                    labels: response.labels,
                    datasets: response.datasets
                },
                options: {
                    ...this.getBaseOptions(response),
                    cutout: '62%',
                    plugins: {
                        legend: {
                            position: isSmallViewport ? 'bottom' : 'right',
                            labels: {
                                boxWidth: 8,
                                boxHeight: 8,
                                usePointStyle: true,
                                color: '#374151',
                                font: {
                                    size: 12,
                                    weight: 500,
                                },
                                generateLabels: (chart: Chart) => {
                                    const values = (chart.data.datasets[0]?.data ?? []).map((value) => Number(value));
                                    const total = values.reduce((sum, value) => sum + value, 0);

                                    return (chart.data.labels ?? []).map((label, index) => {
                                        const style = chart.getDatasetMeta(0).controller.getStyle(index, false);
                                        const value = values[index];
                                        const percentage = total > 0 ? (value / total) * 100 : 0;

                                        return {
                                            text: `${String(label)} · ${new Intl.NumberFormat('cs-CZ', { maximumFractionDigits: 1 }).format(percentage)} %`,
                                            fillStyle: style.backgroundColor,
                                            strokeStyle: style.borderColor,
                                            fontColor: '#374151',
                                            lineWidth: style.borderWidth,
                                            pointStyle: 'circle',
                                            hidden: !chart.getDataVisibility(index),
                                            index: index,
                                        };
                                    });
                                },
                            },
                        },
                        tooltip: this.getTooltipOptions(response),
                    },
                },
            });

            if (shouldUpdateOnAjaxRequestValue) {
                this.loadedCharts.push({
                    chart: myChart,
                    chartDataUrl: chartDataUrl
                });
            }

            this.removeGraphSpinner(chartId);
        }.bind(this));
    }

    async updateChart(chart: Chart, chartDataUrl: string): Promise<void> {
        const graphData = this.naja.makeRequest(
            'GET',
            chartDataUrl,
            null,
            {
                history: false,
                responseType: 'json',
                unique: false
            },
        );

        graphData.then(function (response: ChartData) {
            chart.data.labels = response.labels;
            const lineChartState = this.lineChartStates.get(chart);

            if (lineChartState === undefined) {
                chart.data.datasets = response.datasets;
            } else {
                lineChartState.absoluteData = this.getAbsoluteData(response.datasets);
                chart.data.datasets = this.getLineChartDatasets(
                    response.datasets,
                    lineChartState.absoluteData,
                    lineChartState.changeFromStart,
                );
            }

            chart.update();
        }.bind(this));
    }

    resetZoom(chartId: string) {
        const chart = Chart.getChart(chartId);
        chart?.resetZoom();
    }

    fetchData(url: string): Promise<Payload> {
        return this.naja.makeRequest(
            'GET',
            url,
            null,
            {
                history: false,
                responseType: 'json',
                unique: false
            },
        );
    }

    removeGraphSpinner(graphId: string) {
        const div = document.getElementById(graphId + '--spinner');
        div.style.display = 'none';
    }
}
