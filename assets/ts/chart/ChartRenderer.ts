import {Naja, Payload} from "naja/dist/Naja";
import {ChartData} from "./ChartData";
import Chart, {TooltipItem, Colors} from 'chart.js/auto';
import {ChartInstance} from "./ChartInstance";


export class ChartRenderer {
    naja: Naja;

    defaultBackgroundColor: string;

    tooltipDefaults: object;

    loadedCharts: Array<ChartInstance>;

    constructor(naja: Naja, loadedCharts: Array<ChartInstance>) {
        this.naja = naja;
        this.setDefaults();

        this.defaultBackgroundColor = '#111827';
        this.tooltipDefaults = this.getTooltipDefaults();
        this.loadedCharts = loadedCharts;
    }

    setDefaults() {
        Chart.defaults.font.family = 'ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"';
        // Chart.defaults.font.defaultFontColor = '#858796';
        Chart.defaults.color = '#111827';
        Chart.register(Colors);
    }

    getTooltipDefaults() {
        return {
            titleMarginBottom: 10,
            titleFontColor: '#6e707e',
            titleFontSize: 14,
            backgroundColor: "rgb(255,255,255)",
            bodyFontColor: "#858796",
            borderColor: '#dddfeb',
            borderWidth: 1,
            xPadding: 15,
            yPadding: 15,
            displayColors: false,
            caretPadding: 10,
        }
    }

    async createLineChart(graphCanvasElement: HTMLCanvasElement, chartDataUrl: string, chartId: string, shouldUpdateOnAjaxRequestValue: boolean): Promise<void> {
        let graphData = this.fetchData(chartDataUrl);

        graphData.then(function (response: ChartData) {
            let myChart = new Chart(graphCanvasElement, {
                type: 'line',
                data: {
                    labels: response.labels,
                    datasets: response.datasets
                },
                options: {
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label(tooltipItem: TooltipItem<any>): string | string[] | void {
                                    let label = tooltipItem.dataset.label || '';
                                    if (label) {
                                        label = label + ' ' + tooltipItem.formattedValue + ' ' + response.tooltipSuffix;
                                    }

                                    return label;
                                }
                            }
                        }
                    }
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
        let graphData = this.fetchData(chartDataUrl);

        graphData.then(function (response: ChartData) {
            let myChart = new Chart(graphCanvasElement, {
                type: 'bar',
                data: {
                    labels: response.labels,
                    datasets: response.datasets
                },
                options: {
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label(tooltipItem: TooltipItem<any>): string | string[] | void {
                                    let label = tooltipItem.dataset.label || '';
                                    if (label) {
                                        label = label + ' ' + tooltipItem.formattedValue + ' ' + response.tooltipSuffix;
                                    }

                                    return label;
                                }
                            }
                        }
                    }
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
        let graphData = this.fetchData(chartDataUrl);

        graphData.then(function (response: ChartData) {
            let myChart = new Chart(graphCanvasElement, {
                type: 'doughnut',
                data: {
                    labels: response.labels,
                    datasets: response.datasets
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label(tooltipItem: TooltipItem<any>): string | string[] | void {
                                    let label = tooltipItem.dataset.label || '';
                                    if (label) {
                                        label = label + ' ' + tooltipItem.formattedValue + ' ' + response.tooltipSuffix;
                                    }

                                    return label;
                                }
                            }
                        }
                    }
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
        let graphData = this.naja.makeRequest(
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
