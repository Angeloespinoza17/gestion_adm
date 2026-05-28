<script>
import simplebar from "simplebar-vue";

import Layout from "../../layouts/main.vue";
import PageHeader from "../../components/page-header.vue";
import { avatar1 } from "../../../images/users/data";
import { widgetData, DonutChart, productsData, tasksData, ChatData } from "./data-saas";
import profileimg from '../../../images/profile-img.png';
import Sellingchart from "./sellingchart.vue";

export default {
    components: {
        Layout,
        PageHeader,
        simplebar,
        Sellingchart
    },
    data() {
        return {
            avatar1, profileimg, widgetData, productsData, tasksData, ChatData,
            title: "Saas",
            DonutChart,
            earnings: [
                {
                    name: "This month",
                    amount: "$2453.35",
                    revenue: "0.2",
                    time: "From previous period",
                    month: "Last month",
                    previousamount: "$2281.04",
                    series: [
                        {
                            name: "series1",
                            data: [
                                31,
                                40,
                                36,
                                51,
                                49,
                                72,
                                69,
                                56,
                                68,
                                82,
                                68,
                                76
                            ]
                        }
                    ]
                }
            ],
            chartOptions: {
                chart: {
                    height: 320,
                    type: "line",
                    toolbar: "false",
                    dropShadow: {
                        enabled: true,
                        color: "#000",
                        top: 18,
                        left: 7,
                        blur: 8,
                        opacity: 0.2
                    }
                },
                dataLabels: {
                    enabled: false
                },
                colors: ["#556ee6"],
                stroke: {
                    curve: "smooth",
                    width: 3
                }
            },
            products: [
                {
                    title: "Product A",
                    amount: "$ 6385",
                    revenue: "0.6",
                    list: [
                        {
                            name: "Product A",
                            text: "Neque quis est",
                            sales: 37,
                            color: "#556ee6",
                        },
                        {
                            name: "Product B",
                            text: "Quis autem iure",
                            sales: 72,
                            color: "#34c38f",
                        },
                        {
                            name: "Product C",
                            text: "Sed aliquam mauris.",
                            sales: 54,
                            color: "#f46a6a",
                        }
                    ]
                }
            ],
        };
    },
    mounted() {
        var container = document.querySelector(
            "#containerElement .simplebar-content-wrapper"
        );
        container.scrollTo({ top: 500, behavior: "smooth" });
    },
    methods: {
        monthlyEarning(event) {
            switch (event.target.value) {
                case "december":
                    this.earnings = [
                        {
                            name: "This month",
                            amount: "$2453.35",
                            revenue: "0.4",
                            time: "From previous period",
                            month: "Last month",
                            previousamount: "$2281.04",
                            series: [
                                {
                                    name: "series1",
                                    data: [
                                        31,
                                        22,
                                        12,
                                        41,
                                        20,
                                        12,
                                        89,
                                        46,
                                        78,
                                        62,
                                        28,
                                        56
                                    ]
                                }
                            ]
                        }
                    ];
                    break;
                case "november":
                    this.earnings = [
                        {
                            name: "This month",
                            amount: "$2887.35",
                            revenue: "0.4",
                            time: "From previous period",
                            month: "Last month",
                            previousamount: "$684.04",
                            series: [
                                {
                                    name: "series1",
                                    data: [
                                        28,
                                        30,
                                        48,
                                        50,
                                        47,
                                        40,
                                        35,
                                        48,
                                        56,
                                        42,
                                        65,
                                        41
                                    ]
                                }
                            ]
                        }
                    ];
                    break;

                case "october":
                    this.earnings = [
                        {
                            name: "This month",
                            amount: "$2100.35",
                            revenue: "0.4",
                            time: "From previous period",
                            month: "Last month",
                            previousamount: "$674.04",
                            series: [
                                {
                                    name: "series1",
                                    data: [
                                        28,
                                        48,
                                        39,
                                        47,
                                        58,
                                        46,
                                        38,
                                        47,
                                        30,
                                        40,
                                        70,
                                        42
                                    ]
                                }
                            ]
                        }
                    ];
                    break;

                case "january":
                    this.earnings = [
                        {
                            name: "This month",
                            amount: "$2453.35",
                            revenue: "0.4",
                            time: "From previous period",
                            month: "Last month",
                            previousamount: "$2281.04",
                            series: [
                                {
                                    name: "series1",
                                    data: [
                                        31,
                                        40,
                                        36,
                                        51,
                                        49,
                                        72,
                                        69,
                                        56,
                                        68,
                                        82,
                                        68,
                                        76
                                    ]
                                }
                            ]
                        }
                    ];
                    break;

                default:
                    this.earnings = [
                        {
                            name: "This month",
                            amount: "$2453.35",
                            revenue: "0.4",
                            time: "From previous period",
                            month: "Last month",
                            previousamount: "$2281.04",
                            series: [
                                {
                                    name: "series1",
                                    data: [
                                        31,
                                        40,
                                        36,
                                        51,
                                        49,
                                        72,
                                        69,
                                        56,
                                        68,
                                        82,
                                        68,
                                        76
                                    ]
                                }
                            ]
                        }
                    ];
            }
        },
        SellProductChange(event) {
            switch (event.target.value) {
                case "december":
                    this.products = [
                        {
                            title: "Product B",
                            amount: "$ 7842",
                            revenue: "0.4",
                            list: [
                                {
                                    name: "Product D",
                                    text: "Neque quis est",
                                    sales: 41
                                },
                                {
                                    name: "Product E",
                                    text: "Quis autem iure",
                                    sales: 14
                                },
                                {
                                    name: "Product F",
                                    text: "Sed aliquam mauris.",
                                    sales: 85
                                }
                            ]
                        }
                    ];
                    break;

                case "january":
                    this.products = [
                        {
                            title: "Product A",
                            amount: "$ 6385",
                            revenue: "0.6",
                            list: [
                                {
                                    name: "Product A",
                                    text: "Neque quis est",
                                    sales: 37
                                },
                                {
                                    name: "Product B",
                                    text: "Quis autem iure",
                                    sales: 72
                                },
                                {
                                    name: "Product C",
                                    text: "Sed aliquam mauris.",
                                    sales: 54
                                }
                            ]
                        }
                    ];
                    break;
                case "november":
                    this.products = [
                        {
                            title: "Product C",
                            amount: "$ 4745",
                            revenue: "0.8",
                            list: [
                                {
                                    name: "Product G",
                                    text: "Neque quis est",
                                    sales: 37
                                },
                                {
                                    name: "Product H",
                                    text: "Quis autem iure",
                                    sales: 42
                                },
                                {
                                    name: "Product I",
                                    text: "Sed aliquam mauris.",
                                    sales: 63
                                }
                            ]
                        }
                    ];
                    break;
                case "october":
                    this.products = [
                        {
                            title: "Product D",
                            amount: "$ 8745",
                            revenue: "0.4",
                            list: [
                                {
                                    name: "Product J",
                                    text: "Neque quis est",
                                    sales: 41
                                },
                                {
                                    name: "Product K",
                                    text: "Quis autem iure",
                                    sales: 74
                                },
                                {
                                    name: "Product L",
                                    text: "Sed aliquam mauris.",
                                    sales: 52
                                }
                            ]
                        }
                    ];

                default:
                    this.products = [
                        {
                            title: "Product D",
                            amount: "$ 8745",
                            revenue: "0.4",
                            list: [
                                {
                                    name: "Product J",
                                    text: "Neque quis est",
                                    sales: 41
                                },
                                {
                                    name: "Product K",
                                    text: "Quis autem iure",
                                    sales: 74
                                },
                                {
                                    name: "Product L",
                                    text: "Sed aliquam mauris.",
                                    sales: 52
                                }
                            ]
                        }
                    ];
                    break;
            }
        },
        productSelling(event) {
      switch (event.target.value) {
        case "december":
          this.products = [
            {
              title: "Product B",
              amount: "$ 7842",
              revenue: "0.4",
              list: [
                {
                  name: "Product D",
                  text: "Neque quis est",
                  sales: 41,
                  color: "#f46a6a",
                },
                {
                  name: "Product E",
                  text: "Quis autem iure",
                  sales: 14,
                  color: "#556ee6",
                },
                {
                  name: "Product F",
                  text: "Sed aliquam mauris.",
                  sales: 85,
                  color: "#34c38f",
                },
              ],
            },
          ];
          break;
        case "january":
          this.products = [
            {
              title: "Product A",
              amount: "$ 6385",
              revenue: "0.6",
              list: [
                {
                  name: "Product A",
                  text: "Neque quis est",
                  sales: 37,
                  color: "#556ee6",
                },
                {
                  name: "Product B",
                  text: "Quis autem iure",
                  sales: 72,
                  color: "#f46a6a",
                },
                {
                  name: "Product C",
                  text: "Sed aliquam mauris.",
                  sales: 54,
                  color: "#34c38f",
                },
              ],
            },
          ];
          break;
        case "november":
          this.products = [
            {
              title: "Product C",
              amount: "$ 4745",
              revenue: "0.8",
              list: [
                {
                  name: "Product G",
                  text: "Neque quis est",
                  sales: 31,
                  color: "#34c38f",
                },
                {
                  name: "Product H",
                  text: "Quis autem iure",
                  sales: 20,
                  color: "#556ee6",
                },
                {
                  name: "Product I",
                  text: "Sed aliquam mauris.",
                  sales: 40,
                  color: "#f46a6a",
                },
              ],
            },
          ];
          break;
        case "october":
          this.products = [
            {
              title: "Product D",
              amount: "$ 8745",
              revenue: "0.4",
              list: [
                {
                  name: "Product J",
                  text: "Neque quis est",
                  sales: 41,
                  color: "#f46a6a",
                },
                {
                  name: "Product K",
                  text: "Quis autem iure",
                  sales: 74,
                  color: "#34c38f",
                },
                {
                  name: "Product L",
                  text: "Sed aliquam mauris.",
                  sales: 52,
                  color: "#556ee6",
                },
              ],
            },
          ];
          break;
        default:
          this.products = [
            {
              title: "Product A",
              amount: "$ 6385",
              revenue: "0.6",
              list: [
                {
                  name: "Product A",
                  text: "Neque quis est",
                  sales: 37,
                },
                {
                  name: "Product B",
                  text: "Quis autem iure",
                  sales: 72,
                },
                {
                  name: "Product C",
                  text: "Sed aliquam mauris.",
                  sales: 54,
                },
              ],
            },
          ];
          break;
      }
    },
    }
};
</script>
<template>
    <Layout>

        <PageHeader title="Saas" pageTitle="Dashboards" />

        <BRow>
            <BCol lg="12">
                <BCard no-body>
                    <BCardBody>
                        <BRow>
                            <BCol lg="4">
                                <div class="d-flex">
                                    <div class="me-3">
                                        <img :src="avatar1" alt="" class="avatar-md rounded-circle img-thumbnail" />
                                    </div>
                                    <div class="flex-grow-1 align-self-center">
                                        <div class="text-muted">
                                            <p class="mb-2">
                                                Welcome to Skote Dashboard
                                            </p>
                                            <h5 class="mb-1">angelo espinoza</h5>
                                            <p class="mb-0">UI / UX Designer</p>
                                        </div>
                                    </div>
                                </div>
                            </BCol>

                            <BCol lg="4" class="align-self-center">
                                <div class="text-lg-center mt-4 mt-lg-0">
                                    <BRow>
                                        <BCol cols="4">
                                            <div>
                                                <p class="text-muted text-truncate mb-2">
                                                    Total Projects
                                                </p>
                                                <h5 class="mb-0">48</h5>
                                            </div>
                                        </BCol>
                                        <BCol cols="4">
                                            <div>
                                                <p class="text-muted text-truncate mb-2">Projects</p>
                                                <h5 class="mb-0">40</h5>
                                            </div>
                                        </BCol>
                                        <BCol cols="4">
                                            <div>
                                                <p class="text-muted text-truncate mb-2">Clients</p>
                                                <h5 class="mb-0">18</h5>
                                            </div>
                                        </BCol>
                                    </BRow>
                                </div>
                            </BCol>

                            <BCol lg="4" class="d-none d-lg-block">
                                <div class="clearfix mt-4 mt-lg-0">
                                    <BDropdown class="float-end" right variant="primary" menu-class="dropdown-menu-end">
                                        <template v-slot:button-content>
                                            <i class="bx bxs-cog align-middle me-1"></i> Setting
                                        </template>
                                        <BDropdownItem>Action</BDropdownItem>
                                        <BDropdownItem>Another action</BDropdownItem>
                                        <BDropdownItem>Something else</BDropdownItem>
                                    </BDropdown>
                                </div>
                            </BCol>
                        </BRow>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>

        <BRow>
            <BCol xl="4">
                <BCard no-body class="bg-primary-subtle">
                    <div>
                        <BRow>
                            <BCol cols="7">
                                <div class="text-primary p-3">
                                    <h5 class="text-primary">Welcome Back !</h5>
                                    <p>Skote Saas Dashboard</p>

                                    <ul class="ps-3 mb-0">
                                        <li class="py-1">7 + Layouts</li>
                                        <li class="py-1">Multiple apps</li>
                                    </ul>
                                </div>
                            </BCol>
                            <BCol cols="5" class="align-self-end">
                                <img :src="profileimg" alt="" class="img-fluid" />
                            </BCol>
                        </BRow>
                    </div>
                </BCard>
            </BCol>
            <BCol xl="8">
                <BRow>
                    <BCol v-for="(item, index) in widgetData" :key="index" sm="4">
                        <BCard no-body>
                            <BCardBody>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar-xs me-3">
                                        <span class="
                        avatar-title
                        rounded-circle
                        bg-primary-subtle
                        text-primary
                        font-size-18
                      ">
                                            <i :class="`bx ${item.iconClass}`"></i>
                                        </span>
                                    </div>
                                    <h5 class="font-size-14 mb-0">{{ item.title }}</h5>
                                </div>
                                <div class="text-muted mt-4">
                                    <h4>
                                        {{ item.value }}
                                        <i class="mdi mdi-chevron-up ms-1 text-success"></i>
                                    </h4>
                                    <div class="d-flex">
                                        <span :class="`badge badge-${item.colorClass} font-size-12`">{{
                                            item.percentageChange }}</span>
                                        <span class="ms-2 text-truncate">From previous period</span>
                                    </div>
                                </div>
                            </BCardBody>
                        </BCard>
                    </BCol>
                </BRow>
            </BCol>
        </BRow>

        <BRow>
            <BCol xl="8">
                <BCard no-body>
                    <BCardBody>
                        <div class="clearfix">
                            <div class="float-end">
                                <div class="input-group input-group-sm">
                                    <select class="form-select form-select-sm" @change="monthlyEarning($event)">
                                        <option value="january" selected>Jan</option>
                                        <option value="december">Dec</option>
                                        <option value="november">Nov</option>
                                        <option value="october">Oct</option>
                                    </select>
                                    <label class="input-group-text">Month</label>
                                </div>
                            </div>
                            <h4 class="card-title mb-4">Earning</h4>
                        </div>

                        <BRow v-for="(data, index) of earnings" :key="index">
                            <BCol lg="4">
                                <div class="text-muted">
                                    <div class="mb-4">
                                        <p>{{ data.name }}</p>
                                        <h4>{{ data.amount }}</h4>
                                        <div>
                                            <span class="badge badge-soft-success font-size-12 me-1">+ {{ data.revenue
                                            }}%</span>
                                            {{ data.time }}
                                        </div>
                                    </div>

                                    <div>
                                        <BLink href="javascript: void(0);" class="btn btn-primary btn-sm">
                                            View Details
                                            <i class="mdi mdi-chevron-right ml-1 ms-1"></i>
                                        </BLink>
                                    </div>

                                    <div class="mt-4">
                                        <p class="mb-2">{{ data.month }}</p>
                                        <h5>{{ data.previousamount }}</h5>
                                    </div>
                                </div>
                            </BCol>
                            <BCol lg="8">
                                <div id="line-chart"></div>
                                <apexchart class="apex-charts" dir="ltr" height="300" :series="data.series"
                                    :options="chartOptions"></apexchart>
                            </BCol>
                        </BRow>
                    </BCardBody>
                </BCard>
            </BCol>

            <BCol xl="4">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle class="mb-4">Sales Analytics</BCardTitle>

                        <div>
                            <apexchart class="apex-charts" :options="DonutChart.chartOptions" :series="DonutChart.series"
                                dir="ltr" height="240" />
                        </div>

                        <div class="text-center text-muted">
                            <BRow>
                                <BCol v-for="(product, index) in productsData" :key="index" cols="4">
                                    <div class="mt-4">
                                        <p class="mb-2 text-truncate">
                                            <i :class="`mdi mdi-circle ${product.colorClass} me-1`"></i>
                                            {{ product.name }}
                                        </p>
                                        <h5>{{ product.price }}</h5>
                                    </div>
                                </BCol>
                            </BRow>
                        </div>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>

        <BRow>
            <BCol xl="4">
                <BCard no-body>
                    <BCardBody>
                        <div class="clearfix">
                            <div class="float-end">
                                <div class="input-group input-group-sm">
                                    <select class="form-select form-select-sm" @change="SellProductChange($event)">
                                        <option value="january" selected>Jan</option>
                                        <option value="december">Dec</option>
                                        <option value="november">Nov</option>
                                        <option value="october">Oct</option>
                                    </select>
                                    <div class="input-group-append">
                                        <label class="input-group-text">Month</label>
                                    </div>
                                </div>
                            </div>
                            <BCardTitle class="mb-4">Top Selling product</BCardTitle>
                        </div>
                        <div v-for="(data, index) of products" :key="index">
                            <div class="text-muted text-center">
                                <p class="mb-2">{{ data.title }}</p>
                                <h4>{{ data.amount }}</h4>
                                <p class="mt-4 mb-0">
                                    <span class="badge badge-soft-success font-size-11 me-2">
                                        {{ data.revenue }}%
                                        <i class="mdi mdi-arrow-up"></i>
                                    </span>
                                    From previous period
                                </p>
                            </div>

                            <div class="table-responsive mt-4">
                                <BTableSimple class="align-middle mb-0">
                                    <BTbody>
                                        <BTr v-for="(item, index) of data.list" :key="index">
                                            <BTd>
                                                <h5 class="font-size-14 mb-1">
                                                    {{ item.name }}
                                                </h5>
                                                <p class="text-muted mb-0">
                                                    {{ item.text }}
                                                </p>
                                            </BTd>
                                            <BTd>
                                                <Sellingchart :seriesvalue="item.sales" :Chartcolor="item.color" />
                                            </BTd>
                                            <BTd>
                                                <p class="text-muted mb-1">
                                                    Sales
                                                </p>
                                                <h5 class="mb-0">
                                                    {{ item.sales }} %
                                                </h5>
                                            </BTd>
                                        </BTr>
                                    </BTbody>
                                </BTableSimple>
                            </div>
                        </div>
                    </BCardBody>
                </BCard>
            </BCol>

            <BCol xl="4">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle class="mb-4">Tasks</BCardTitle>

                        <BTabs pills nav-class="bg-light rounded" content-class="mt-4">
                            <BTab title="In Process" active>
                                <simplebar style="max-height: 250px">
                                    <div class="table-responsive">
                                        <BTableSimple class="table-nowrap align-mid table-hover mb-0">
                                            <BTbody>
                                                <BTr v-for="task in tasksData" :key="task.id">
                                                    <BTd style="width: 50px">
                                                        <div class="form-check">
                                                            <input class="form-check-input" :id="task.id" type="checkbox" />
                                                            <label class="form-check-label" :for="task.id"></label>
                                                        </div>
                                                    </BTd>
                                                    <BTd>
                                                        <h5 class="text-truncate font-size-14 mb-1">
                                                            <BLink href="javascript: void(0);" class="text-dark">{{
                                                                task.title }}</BLink>
                                                        </h5>
                                                        <p class="text-muted mb-0">Assigned to {{ task.assignedTo }}</p>
                                                    </BTd>
                                                    <BTd style="width: 90px">
                                                        <div>
                                                            <ul class="list-inline mb-0 font-size-16">
                                                                <li class="list-inline-item">
                                                                    <BLink href="javascript: void(0);"
                                                                        class="text-success p-1">
                                                                        <i class="bx bxs-edit-alt"></i>
                                                                    </BLink>
                                                                </li>
                                                                <li class="list-inline-item">
                                                                    <BLink href="javascript: void(0);"
                                                                        class="text-danger p-1">
                                                                        <i class="bx bxs-trash"></i>
                                                                    </BLink>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </BTd>
                                                </BTr>
                                            </BTbody>
                                        </BTableSimple>
                                    </div>
                                </simplebar>
                            </BTab>
                            <BTab title="Upcoming">
                                <simplebar style="max-height: 250px">
                                    <div class="table-responsive">
                                        <BTableSimple class="table-nowrap align-mid table-hover mb-0">
                                            <BTbody>
                                                <BTr v-for="task in tasksData" :key="task.id">
                                                    <BTd style="width: 50px">
                                                        <div class="form-check">
                                                            <input class="form-check-input" :id="task.id" type="checkbox" />
                                                            <label class="form-check-label" :for="task.id"></label>
                                                        </div>
                                                    </BTd>
                                                    <BTd>
                                                        <h5 class="text-truncate font-size-14 mb-1">
                                                            <BLink href="javascript: void(0);" class="text-dark">{{
                                                                task.title }}</BLink>
                                                        </h5>
                                                        <p class="text-muted mb-0">Assigned to {{ task.assignedTo }}</p>
                                                    </BTd>
                                                    <BTd style="width: 90px">
                                                        <div>
                                                            <ul class="list-inline mb-0 font-size-16">
                                                                <li class="list-inline-item">
                                                                    <BLink href="javascript: void(0);"
                                                                        class="text-success p-1">
                                                                        <i class="bx bxs-edit-alt"></i>
                                                                    </BLink>
                                                                </li>
                                                                <li class="list-inline-item">
                                                                    <BLink href="javascript: void(0);"
                                                                        class="text-danger p-1">
                                                                        <i class="bx bxs-trash"></i>
                                                                    </BLink>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </BTd>
                                                </BTr>
                                            </BTbody>
                                        </BTableSimple>
                                    </div>
                                </simplebar>
                            </BTab>
                        </BTabs>
                    </BCardBody>

                    <BCardFooter class="bg-transparent border-top">
                        <div class="text-center">
                            <BLink href="javascript: void(0);" class="btn btn-primary waves-effect waves-light">
                                Add new Task</BLink>
                        </div>
                    </BCardFooter>
                </BCard>
            </BCol>

            <BCol xl="4">
                <BCard no-body>
                    <BCardBody class="border-bottom">
                        <BRow>
                            <BCol md="4" cols="9">
                                <h5 class="font-size-15 mb-1">Steven Franklin</h5>
                                <p class="text-muted mb-0">
                                    <i class="mdi mdi-circle text-success align-middle me-1"></i>
                                    Active now
                                </p>
                            </BCol>
                            <BCol md="8" cols="3">
                                <ul class="list-inline user-chat-nav text-end mb-0">
                                    <li class="list-inline-item d-none d-sm-inline-block">
                                        <BDropdown right menu-class="dropdown-menu-end py-0 dropdown-menu-md"
                                            variant="white" toggle-class="p-0 nav-btn">
                                            <template v-slot:button-content>
                                                <i class="bx bx-search-alt-2"></i>
                                            </template>
                                            <form class="p-3">
                                                <div class="form-group m-0">
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" placeholder="Search ..."
                                                            aria-label="Recipient's username" />
                                                        <div class="input-group-append">
                                                            <BButton variant="primary" type="submit">
                                                                <i class="mdi mdi-magnify"></i>
                                                            </BButton>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </BDropdown>
                                    </li>
                                    <li class="list-inline-item d-none d-sm-inline-block">
                                        <BDropdown toggle-class="nav-btn" menu-class="dropdown-menu-end" right
                                            variant="white">
                                            <template v-slot:button-content>
                                                <i class="bx bx-cog"></i>
                                            </template>
                                            <BDropdownItem>View Profile</BDropdownItem>
                                            <BDropdownItem>Clear chat</BDropdownItem>
                                            <BDropdownItem>Muted</BDropdownItem>
                                            <BDropdownItem>Delete</BDropdownItem>
                                        </BDropdown>
                                    </li>

                                    <li class="list-inline-item">
                                        <BDropdown toggle-class="nav-btn" menu-class="dropdown-menu-end" right
                                            variant="white">
                                            <template v-slot:button-content>
                                                <i class="bx bx-dots-horizontal-rounded"></i>
                                            </template>
                                            <BDropdownItem>Action</BDropdownItem>
                                            <BDropdownItem>Another action</BDropdownItem>
                                            <BDropdownItem>Something else</BDropdownItem>
                                        </BDropdown>
                                    </li>
                                </ul>
                            </BCol>
                        </BRow>
                    </BCardBody>
                    <BCardBody class="pb-0">
                        <div>
                            <div class="chat-conversation">
                                <ul class="list-unstyled">
                                    <simplebar style="max-height: 260px" ref="current" id="containerElement">
                                        <li>
                                            <div class="chat-day-title">
                                                <span class="title">Today</span>
                                            </div>
                                        </li>
                                        <li v-for="data of ChatData" :key="data.id"
                                            :class="{ right: `${data.align}` === 'right' }">
                                            <div class="conversation-list">
                                                <BDropdown variant="white" menu-class="dropdown-menu-end"
                                                    class="chat-dropdown">
                                                    <template v-slot:button-content>
                                                        <i class="bx bx-dots-vertical-rounded"></i>
                                                    </template>
                                                    <BDropdownItem>Copy</BDropdownItem>
                                                    <BDropdownItem>Save</BDropdownItem>
                                                    <BDropdownItem>Forward</BDropdownItem>
                                                    <BDropdownItem>Delete</BDropdownItem>
                                                </BDropdown>
                                                <div class="ctext-wrap">
                                                    <div class="conversation-name">{{ data.name }}</div>
                                                    <p>{{ data.message }}</p>
                                                    <p class="chat-time mb-0">
                                                        <i class="bx bx-time-five align-middle me-1"></i>
                                                        {{ data.time }}
                                                    </p>
                                                </div>
                                            </div>
                                        </li>
                                    </simplebar>
                                </ul>
                            </div>
                        </div>
                    </BCardBody>

                    <div class="p-3 chat-input-section">
                        <BRow>
                            <BCol>
                                <div class="position-relative">
                                    <input type="text" class="form-control rounded chat-input"
                                        placeholder="Enter Message..." />
                                    <div class="chat-input-links">
                                        <ul class="list-inline mb-0">
                                            <li class="list-inline-item">
                                                <BLink href="javascript: void(0);" placement="top" v-b-tooltip.hover
                                                    title="Emoji">
                                                    <i class="mdi mdi-emoticon-happy-outline"></i>
                                                </BLink>
                                            </li>
                                            <li class="list-inline-item">
                                                <BLink href="javascript: void(0);" placement="top" v-b-tooltip.hover
                                                    title="Images">
                                                    <i class="mdi mdi-file-image-outline"></i>
                                                </BLink>
                                            </li>
                                            <li class="list-inline-item">
                                                <BLink href="javascript: void(0);" placement="top" v-b-tooltip.hover
                                                    title="Add Files">
                                                    <i class="mdi mdi-file-document-outline"></i>
                                                </BLink>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </BCol>
                            <div class="col-auto">
                                <BButton variant="primary" type="submit" class="chat-send w-md">
                                    <span class="d-none d-sm-inline-block me-2">Send</span>
                                    <i class="mdi mdi-send"></i>
                                </BButton>
                            </div>
                        </BRow>
                    </div>
                </BCard>
            </BCol>
        </BRow>
    </Layout>
</template>
