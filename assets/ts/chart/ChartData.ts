export interface ChartDataset {
    label: string,
    data: number[],
    backgroundColors: string[],
    borderColors: string[],
    stepped: boolean,
    tension?: number,
    weeklyValueLabels: boolean,
}

export interface ChartData {
    labels: string[],
    tooltipSuffix: string,
    datasets: ChartDataset[],
}
