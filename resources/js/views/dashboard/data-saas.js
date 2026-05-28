const LineChart = {
    series: [
        {
            name: "series1",
            data: [31, 40, 36, 51, 49, 72, 69, 56, 68, 82, 68, 76]
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
    }
};
const DonutChart = {
    series: [56, 38, 26],
    chartOptions: {
        chart: {
            type: "donut",
            height: 240
        },
        labels: ["Series A", "Series B", "Series C"],
        colors: ["#556ee6", "#34c38f", "#f46a6a"],
        legend: {
            show: false
        },
        plotOptions: {
            pie: {
                donut: {
                    size: "70%"
                }
            }
        }
    }
};
const RadialChart1 = {
    series: [37],
    chartOptions: {
        chart: {
            type: "radialBar",
            width: 60,
            height: 60,
            sparkline: {
                enabled: true
            }
        },
        dataLabels: {
            enabled: false
        },
        colors: ["#556ee6"],
        plotOptions: {
            radialBar: {
                hollow: {
                    margin: 0,
                    size: "60%"
                },
                track: {
                    margin: 0
                },
                dataLabels: {
                    show: false
                }
            }
        }
    }
};
const RadialChart2 = {
    series: [72],
    chartOptions: {
        chart: {
            type: "radialBar",
            width: 60,
            height: 60,
            sparkline: {
                enabled: true
            }
        },
        dataLabels: {
            enabled: false
        },
        colors: ["#34c38f"],
        plotOptions: {
            radialBar: {
                hollow: {
                    margin: 0,
                    size: "60%"
                },
                track: {
                    margin: 0
                },
                dataLabels: {
                    show: false
                }
            }
        }
    }
};
const RadialChart3 = {
    series: [54],
    chartOptions: {
        chart: {
            type: "radialBar",
            width: 60,
            height: 60,
            sparkline: {
                enabled: true
            }
        },
        dataLabels: {
            enabled: false
        },
        colors: ["#f46a6a"],
        plotOptions: {
            radialBar: {
                hollow: {
                    margin: 0,
                    size: "60%"
                },
                track: {
                    margin: 0
                },
                dataLabels: {
                    show: false
                }
            }
        }
    }
};

const widgetData = [
    {
        title: "Orders",
        value: "1,452",
        percentageChange: "+ 0.2%",
        iconClass: "bx-copy-alt",
        extraInfo: "From previous period",
        colorClass: "soft-success"
    },
    {
        title: "Revenue",
        value: "$ 28,452",
        percentageChange: "+ 0.2%",
        iconClass: "bx-archive-in",
        extraInfo: "From previous period",
        colorClass: "soft-success"
    },
    {
        title: "Average Price",
        value: "$ 16.2",
        percentageChange: "0%",
        iconClass: "bx-purchase-tag-alt",
        extraInfo: "From previous period",
        colorClass: "soft-warning"
    }
];

const productsData = [
    {
        name: "Product A",
        price: "$2,132",
        colorClass: "text-primary"
    },
    {
        name: "Product B",
        price: "$1,763",
        colorClass: "text-success"
    },
    {
        name: "Product C",
        price: "$973",
        colorClass: "text-danger"
    }
];

const tasksData = [
    {
        id: 'tasklistCheck01',
        title: 'Skote Saas Dashboard',
        assignedTo: 'Mark',
    },
    {
        id: 'tasklistCheck02',
        title: 'New Landing UI',
        assignedTo: 'Team A',
    },
    {
        id: 'tasklistCheck03',
        title: ' Brand logo design',
        assignedTo: 'Janis',
    },
    {
        id: 'tasklistCheck04',
        title: ' Blog Template UI',
        assignedTo: 'Dianna',
    },
    {
        id: 'tasklistCheck05',
        title: 'Multipurpose Landing',
        assignedTo: 'Team B',
    },
    {
        id: 'tasklistCheck06',
        title: 'Redesign - Landing page',
        assignedTo: 'Jerry',
    },
    {
        id: 'tasklistCheck07',
        title: 'Skote Crypto Dashboard',
        assignedTo: 'Eric',
    },
];

const ChatData = [
    {
        id: 1,
        name: 'Steven Franklin',
        message: 'Hello!',
        time: '10:00',
    },
    {
        id: 2,
        align: 'right',
        name: 'angelo espinoza',
        message: 'Hi, How are you? What about our next meeting?',
        time: '10:02'
    },
    {
        id: 3,
        name: 'Steven Franklin',
        message: 'Yeah everything is fine',
        time: '10:06'
    },
    {
        id: 4,
        name: 'Steven Franklin',
        message: '& Next meeting tomorrow 10.00AM',
        time: '10:06'
    },
    {
        id: 5,
        align: 'right',
        name: 'angelo espinoza',
        message: "Wow that's great",
        time: '10:02'
    },
]
export { LineChart, DonutChart, RadialChart1, RadialChart2, RadialChart3, widgetData, productsData, tasksData, ChatData };
