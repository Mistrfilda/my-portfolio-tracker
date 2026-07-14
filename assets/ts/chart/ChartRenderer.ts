import {Naja, Payload} from "naja/dist/Naja";
import {ChartData} from "./ChartData";
import Chart, {ChartOptions, Colors, TooltipItem} from 'chart.js/auto';
import zoomPlugin from 'chartjs-plugin-zoom';
import {ChartInstance} from "./ChartInstance";


export class ChartRenderer {
    naja: Naja;

    defaultBackgroundColor: string;

    tooltipDefaults: object;

    loadedCharts: Array<ChartInstance>;

    constructor(naja: Naja, loadedCharts: Array<ChartInstance>) {
        this.naja = naja;
        this.defaultBackgroundColor = '#111827';
        this.tooltipDefaults = this.getTooltipDefaults();
        this.loadedCharts = loadedCharts;

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
                label(tooltipItem: TooltipItem<any>): string | string[] | void {
                    let label = tooltipItem.dataset.label || '';
                    if (label) {
                        label = label + ' ' + tooltipItem.formattedValue + ' ' + response.tooltipSuffix;
                    }

                    return label;
                }
            }
        };
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

    async createLineChart(graphCanvasElement: HTMLCanvasElement, chartDataUrl: string, chartId: string, shouldUpdateOnAjaxRequestValue: boolean): Promise<void> {
        const graphData = this.fetchData(chartDataUrl);

        graphData.then(function (response: ChartData) {
            const myChart = new Chart(graphCanvasElement, {
                type: 'line',
                data: {
                    labels: response.labels,
                    datasets: response.datasets
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
            chart.data.datasets = response.datasets;
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
