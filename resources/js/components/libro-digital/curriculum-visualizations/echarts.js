import { use } from "echarts/core";
import {
    GraphChart,
    SankeyChart,
    SunburstChart,
    TreeChart,
    TreemapChart,
} from "echarts/charts";
import {
    AriaComponent,
    LegendComponent,
    TitleComponent,
    TooltipComponent,
} from "echarts/components";
import { CanvasRenderer } from "echarts/renderers";
import VChart from "vue-echarts";

use([
    TreemapChart,
    SunburstChart,
    SankeyChart,
    TreeChart,
    GraphChart,
    TooltipComponent,
    LegendComponent,
    TitleComponent,
    AriaComponent,
    CanvasRenderer,
]);

export default VChart;
