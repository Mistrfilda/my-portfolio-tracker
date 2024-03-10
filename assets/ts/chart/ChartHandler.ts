import {Naja} from "naja/dist/Naja";
import {ChartRenderer} from "./ChartRenderer";
import {ChartType} from "./ChartType";

export class ChartHandler {
    private naja: Naja;

    private chartRenderer: ChartRenderer;

    public constructor(naja: Naja) {
        this.naja = naja;
        this.chartRenderer = new ChartRenderer(naja);
    }

    public initListener() {
        const chartRenderer = this.chartRenderer;
        window.loadChart = function(): object {
            return {
                show: true,
                loadGraph(chartId: any, chartDataUrl: string, type: ChartType): boolean {
                    let chartCanvasElement = <HTMLCanvasElement> document.getElementById(chartId);

                    if (type.valueOf() === ChartType.LINE.valueOf()) {
                        chartRenderer.createLineChart(chartCanvasElement, chartDataUrl)
                        return true;
                    }

                    if (type.valueOf() === ChartType.DOUGHNUT.valueOf()) {
                        chartRenderer.createDoughnutCharts(chartCanvasElement, chartDataUrl)
                        return true;
                    }

                    if (type.valueOf() === ChartType.BAR.valueOf()) {
                        chartRenderer.createBarCharts(chartCanvasElement, chartDataUrl)
                        return true;
                    }

                    throw new Error('Invalid chart type passed');
                }
            }
        }
    }
}
