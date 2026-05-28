<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";

import {
  linewithDataChart,
  dashedLineChart,
  splineAreaChart,
  columnChart,
  dumbbellColumnChart,
  rangeAreaChart,
  dumbleTimelineChart,
  funnelChart,
  columnDatalabelChart,
  barChart,
  mixedChart,
  radialChart,
  pieChart,
  donutChart
} from "./data-apex";

/**
 * Apex-chart component
 */
export default {
  components: { Layout, PageHeader },
  data() {
    return {
      linewithDataChart: JSON.parse(JSON.stringify(linewithDataChart)),
      dashedLineChart: JSON.parse(JSON.stringify(dashedLineChart)),
      splineAreaChart: JSON.parse(JSON.stringify(splineAreaChart)),
      columnChart: JSON.parse(JSON.stringify(columnChart)),
      dumbbellColumnChart: JSON.parse(JSON.stringify(dumbbellColumnChart)),
      rangeAreaChart: JSON.parse(JSON.stringify(rangeAreaChart)),
      dumbleTimelineChart: JSON.parse(JSON.stringify(dumbleTimelineChart)),
      funnelChart: JSON.parse(JSON.stringify(funnelChart)),
      columnDatalabelChart: JSON.parse(JSON.stringify(columnDatalabelChart)),
      barChart: JSON.parse(JSON.stringify(barChart)),
      mixedChart: JSON.parse(JSON.stringify(mixedChart)),
      radialChart: JSON.parse(JSON.stringify(radialChart)),
      pieChart: JSON.parse(JSON.stringify(pieChart)),
      donutChart: JSON.parse(JSON.stringify(donutChart)),
    };
  },
  computed: {
    linewithDataChartOptions() {
      return JSON.parse(JSON.stringify(this.linewithDataChart.chartOptions)); // Create a deep copy for basic column chart options
    },
    linewithDataChartSeries() {
      return JSON.parse(JSON.stringify(this.linewithDataChart.series)); // Create a deep copy for basic column chart series
    },

    dashedLineChartOptions() {
      return JSON.parse(JSON.stringify(this.dashedLineChart.chartOptions)); // Create a deep copy for column data label chart options
    },
    dashedLineChartSeries() {
      return JSON.parse(JSON.stringify(this.dashedLineChart.series)); // Create a deep copy for column data label chart series
    },

    splineAreaChartOptions() {
      return JSON.parse(JSON.stringify(this.splineAreaChart.chartOptions)); // Create a deep copy for stacked columns chart options
    },
    splineAreaChartSeries() {
      return JSON.parse(JSON.stringify(this.splineAreaChart.series)); // Create a deep copy for stacked columns chart series
    },

    columnChartOptions() {
      return JSON.parse(JSON.stringify(this.columnChart.chartOptions));
    },
    columnChartSeries() {
      return JSON.parse(JSON.stringify(this.columnChart.series));
    },

    dumbbellColumnChartOptions() {
      return JSON.parse(JSON.stringify(this.dumbbellColumnChart.chartOptions));
    },
    dumbbellColumnChartSeries() {
      return JSON.parse(JSON.stringify(this.dumbbellColumnChart.series));
    },

    rangeAreaChartOptions() {
      return JSON.parse(JSON.stringify(this.rangeAreaChart.chartOptions));
    },
    rangeAreaChartSeries() {
      return JSON.parse(JSON.stringify(this.rangeAreaChart.series));
    },

    dumbleTimelineChartOptions() {
      return JSON.parse(JSON.stringify(this.dumbleTimelineChart.chartOptions));
    },
    dumbleTimelineChartSeries() {
      return JSON.parse(JSON.stringify(this.dumbleTimelineChart.series));
    },

    funnelChartOptions() {
      return JSON.parse(JSON.stringify(this.funnelChart.chartOptions));
    },
    funnelChartSeries() {
      return JSON.parse(JSON.stringify(this.funnelChart.series));
    },

    columnDatalabelChartOptions() {
      return JSON.parse(JSON.stringify(this.columnDatalabelChart.chartOptions));
    },
    columnDatalabelChartSeries() {
      return JSON.parse(JSON.stringify(this.columnDatalabelChart.series));
    },

    barChartOptions() {
      return JSON.parse(JSON.stringify(this.barChart.chartOptions));
    },
    barChartSeries() {
      return JSON.parse(JSON.stringify(this.barChart.series));
    },

    mixedChartOptions() {
      return JSON.parse(JSON.stringify(this.mixedChart.chartOptions));
    },
    mixedChartSeries() {
      return JSON.parse(JSON.stringify(this.mixedChart.series));
    },

    radialChartOptions() {
      return JSON.parse(JSON.stringify(this.radialChart.chartOptions));
    },
    radialChartSeries() {
      return JSON.parse(JSON.stringify(this.radialChart.series));
    },

    pieChartOptions() {
      return JSON.parse(JSON.stringify(this.pieChart.chartOptions));
    },
    pieChartSeries() {
      return JSON.parse(JSON.stringify(this.pieChart.series));
    },

    donutChartOptions() {
      return JSON.parse(JSON.stringify(this.donutChart.chartOptions));
    },
    donutChartSeries() {
      return JSON.parse(JSON.stringify(this.donutChart.series));
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader title="Apex Charts" pageTitle="Charts" />

    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Line With Data Labels</BCardTitle>
            <apexchart class="apex-charts" height="380" dir="ltr" :series="linewithDataChartSeries"
              :options="linewithDataChartOptions">
            </apexchart>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Dashed Line Chart</BCardTitle>
            <apexchart class="apex-charts" height="380" dir="ltr" :series="dashedLineChartSeries"
              :options="dashedLineChartOptions">
            </apexchart>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Spline Area Chart</BCardTitle>
            <apexchart class="apex-charts" height="350" dir="ltr" :series="splineAreaChartSeries"
              :options="splineAreaChartOptions">
            </apexchart>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Column Chart</BCardTitle>
            <apexchart class="apex-charts" height="350" dir="ltr" :series="columnChartSeries"
              :options="columnChartOptions">
            </apexchart>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Dumbbell Column Chart</BCardTitle>
            <apexchart class="apex-charts" height="350" dir="ltr" :series="dumbbellColumnChartSeries"
              :options="dumbbellColumnChartOptions">
            </apexchart>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Range Area Chart</BCardTitle>
            <apexchart class="apex-charts" height="350" dir="ltr" :series="rangeAreaChartSeries"
              :options="rangeAreaChartOptions">
            </apexchart>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Dumble Timeline Chart (Horizontal)</BCardTitle>
            <apexchart class="apex-charts" height="390" dir="ltr" :series="dumbleTimelineChartSeries"
              :options="dumbleTimelineChartOptions">
            </apexchart>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Funnel Chart</BCardTitle>
            <apexchart class="apex-charts" height="390" dir="ltr" :series="funnelChartSeries"
              :options="funnelChartOptions">
            </apexchart>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Column Chart with Data Labels</BCardTitle>
            <apexchart class="apex-charts" height="350" dir="ltr" :series="columnDatalabelChartSeries"
              :options="columnDatalabelChartOptions">
            </apexchart>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Bar Chart</BCardTitle>
            <apexchart class="apex-charts" height="350" dir="ltr" :series="barChartSeries" :options="barChartOptions">
            </apexchart>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Line, Column & Area Chart</BCardTitle>
            <apexchart class="apex-charts" height="370" dir="ltr" :series="mixedChartSeries"
              :options="mixedChartOptions">
            </apexchart>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Radial Bar Chart</BCardTitle>
            <apexchart class="apex-charts" height="370" dir="ltr" :series="radialChartSeries"
              :options="radialChartOptions">
            </apexchart>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Pie Chart</BCardTitle>
            <apexchart class="apex-charts" height="320" dir="ltr" :series="pieChartSeries" :options="pieChartOptions">
            </apexchart>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Donut Chart</BCardTitle>
            <apexchart class="apex-charts" height="320" dir="ltr" :series="donutChartSeries"
              :options="donutChartOptions">
            </apexchart>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

  </Layout>
</template>
