import {Naja, Payload} from "naja/dist/Naja";
import {ChartData} from "./ChartData";
import Chart, {TooltipItem, Colors} from 'chart.js/auto';


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

    async createLineChart(graphCanvasElement: HTMLCanvasElement, chartDataUrl: string, chartId: string): Promise<void> {
        let graphData = this.fetchData(chartDataUrl);

        graphData.then(function (response: ChartData) {
            let myChart = new Chart(graphCanvasElement, {
                type: 'line',
                data: {
                    labels: response.labels,
                    datasets: response.datasets
                },
                options: {
                    responsive: true,
                }
            });

            this.removeGraphSpinner(chartId);
        }.bind(this));
    }

    async createBarCharts(graphCanvasElement: HTMLCanvasElement, chartDataUrl: string, chartId: string): Promise<void> {
        let graphData = this.fetchData(chartDataUrl);

        graphData.then(function (response: ChartData) {
            let myChart = new Chart(graphCanvasElement, {
                type: 'bar',
                data: {
                    labels: response.labels,
                    datasets: response.datasets
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
                                    if (label) {
                                        label =  label + ' ' + tooltipItem.formattedValue + ' ' + response.tooltipSuffix;
                                    }

                                    return label;
                                }
                            }
                        }
                    }
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
                    datasets: response.datasets
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
