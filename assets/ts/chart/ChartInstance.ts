import Chart from 'chart.js/auto';

export interface ChartInstance {
    chart: Chart,
    chartDataUrl: string,
    parametersToProcess: Array<string>
}
