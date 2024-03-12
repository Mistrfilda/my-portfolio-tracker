import {Naja, Payload} from "naja/dist/Naja";
import {ChartData} from "./ChartData";
import Chart, {TooltipItem} from 'chart.js/auto';


export class ChartRenderer {
    naja: Naja;

    defaultBackgroundColor: string;

    tooltipDefaults: object;

    constructor(naja: Naja) {
        this.naja = naja;
        this.setDefaults();

        this.defaultBackgroundColor = '#111827';
        this.tooltipDefaults = this.getTooltipDefaults();
    }

    setDefaults() {
        Chart.defaults.font.family = 'ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"';
        // Chart.defaults.font.defaultFontColor = '#858796';
        Chart.defaults.color = '#111827';
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

    async createLineChart(graphCanvasElement: HTMLCanvasElement, chartDataUrl: string, chartId: string): Promise<void> {
        let graphData = this.fetchData(chartDataUrl);

        graphData.then(function (response: ChartData) {
            let tooltipDefaults = this.getTooltipDefaults();
            tooltipDefaults.callbacks = {
                // label: function (tooltipItem: ChartTooltipItem, chart: Chart) {
                //     return tooltipItem.yLabel + ' ' + response.tooltipSuffix;
                // }
            };

            let myChart = new Chart(graphCanvasElement, {
                type: 'line',
                data: {
                    labels: response.labels,
                    datasets: [{
                        label: response.datasets.label,
                        data: response.datasets.data,
                        borderWidth: 1,
                        fill: false,
                        backgroundColor: function(context) {
                            var index = context.dataIndex;
                            var value = context.dataset.data[index];
                            return value < 0 ? 'red' : 'green';
                        },
                        borderColor: this.defaultBackgroundColor,
                        tension: 0.1
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    // tooltips: tooltipDefaults
                }
            });

            this.removeGraphSpinner(chartId);
        }.bind(this));
    }

    async createBarCharts(graphCanvasElement: HTMLCanvasElement, chartDataUrl: string, chartId: string): Promise<void> {
        let graphData = this.fetchData(chartDataUrl);

        graphData.then(function (response: ChartData) {
            let tooltipDefaults = this.getTooltipDefaults();
            tooltipDefaults.callbacks = {
                // label: function (tooltipItem: ChartTooltipItem, chart: Chart) {
                //     return tooltipItem.yLabel + ' ' + response.tooltipSuffix;
                // }
            };
            let myChart = new Chart(graphCanvasElement, {
                type: 'bar',
                data: {
                    labels: response.labels,
                    datasets: [{
                        label: response.datasets.label,
                        data: response.datasets.data,
                        backgroundColor: response.datasets.backgroundColors,
                        borderColor: response.datasets.borderColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label(tooltipItem: TooltipItem<any>): string | string[] | void {
                                    let label = tooltipItem.dataset.label || '';
                                    console.log(tooltipItem);
                                    console.log(label);

                                    if (label) {
                                        label =  label + ' ' + tooltipItem.formattedValue + ' ' + response.tooltipSuffix;
                                    }

                                    return label;
                                }
                            }
                        }
                    }
                    // tooltips: tooltipDefaults
                }
            });

            this.removeGraphSpinner(chartId);
        }.bind(this));
    }


    async createDoughnutCharts(graphCanvasElement: HTMLCanvasElement, chartDataUrl: string, chartId: string): Promise<void> {
        let graphData = this.fetchData(chartDataUrl);

        graphData.then(function (response: ChartData) {
            let myChart = new Chart(graphCanvasElement, {
                type: 'doughnut',
                data: {
                    labels: response.labels,
                    datasets: [{
                        label: response.datasets.label,
                        data: response.datasets.data,
                        borderWidth: 1,
                        backgroundColor: response.datasets.backgroundColors,
                        borderColor: response.datasets.borderColors,
                    }]
                },
                options: {
                    // tooltips: this.tooltipDefaults
                }
            });

            this.removeGraphSpinner(chartId);
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
