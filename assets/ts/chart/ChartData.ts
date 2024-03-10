export interface ChartData {
    labels: string[],
    tooltipSuffix: string,
    datasets: {
        label: string,
        data: number[],
        backgroundColors: string[],
        borderColors: string[]
    }
}