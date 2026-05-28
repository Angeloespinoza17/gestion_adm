<script>
import simplebar from "simplebar-vue";

import Layout from "../../layouts/main.vue";
import PageHeader from "../../components/page-header.vue";

import { Img2, Img6, Img1 } from "../../../images/small/data";
import { avatar1, avatar2 } from "../../../images/users/data";

import { items, visitorData, topVisitorsData, activityData, blogPostData } from "./data-blog"

export default {
  components: {
    Layout,
    PageHeader,
    simplebar,
  },
  data() {
    return {
      title: "Blog",
      avatar1, avatar2, items, visitorData, topVisitorsData, activityData, blogPostData,
      chartData: {
        series: [
          {
            name: "Current",
            data: [18, 21, 45, 36, 65, 47, 51, 32, 40, 28, 31, 26],
          },
          {
            name: "Previous",
            data: [30, 11, 22, 18, 32, 23, 58, 45, 30, 36, 15, 34],
          },
        ],
        chartoptions: {
          chart: {
            height: 350,
            type: "area",
            toolbar: {
              show: false,
            },
          },
          colors: ["#556ee6", "#f1b44c"],
          dataLabels: {
            enabled: false,
          },
          stroke: {
            curve: "smooth",
            width: 2,
          },
          fill: {
            type: "gradient",
            gradient: {
              shadeIntensity: 1,
              inverseColors: false,
              opacityFrom: 0.45,
              opacityTo: 0.05,
              stops: [20, 100, 100, 100],
            },
          },
          xaxis: {
            categories: [
              "Jan",
              "Feb",
              "Mar",
              "Apr",
              "May",
              "Jun",
              "Jul",
              "Aug",
              "Sep",
              "Oct",
              "Nov",
              "Dec",
            ],
          },
          markers: {
            size: 3,
            strokeWidth: 3,

            hover: {
              size: 4,
              sizeOffset: 2,
            },
          },
          legend: {
            position: "top",
            horizontalAlign: "right",
          },
        },
      },
      postData: [
        {
          image: Img2,
          title: "Beautiful Day with Friends",
          date: "10 Nov, 2020",
        },
        {
          image: Img6,
          title: "Drawing a sketch",
          date: "02 Nov, 2020",
        },
        {
          image: Img2,
          title: "Project discussion with team",
          date: "24 Oct, 2020",
        },
        {
          image: Img1,
          title: "Riding bike on road",
          date: "20 Oct, 2020",
        },
      ],
      selection: "all",
    };
  },
  methods: {
    updateData: function (timeline) {
      this.selection = timeline;
      switch (timeline) {
        case "one_month":
          this.chartData.series = [
            {
              name: "Current",
              data: [12, 22, 38, 42, 32, 40, 51, 36, 51, 29, 38, 36],
            },
            {
              name: "Previous",
              data: [22, 31, 36, 26, 47, 56, 42, 64, 61, 52, 42, 31],
            },
          ];
          break;
        case "six_months":
          this.chartData.series = [
            {
              name: "Current",
              data: [31, 40, 28, 51, 42, 40, 51, 36, 40, 39, 31, 36],
            },
            {
              name: "Previous",
              data: [11, 32, 45, 32, 34, 22, 51, 60, 51, 52, 40, 31],
            },
          ];
          break;
        case "one_year":
          this.chartData.series = [
            {
              name: "Current",
              data: [28, 22, 38, 42, 32, 40, 51, 36, 51, 29, 38, 36],
            },
            {
              name: "Previous",
              data: [22, 31, 36, 26, 47, 56, 42, 64, 61, 52, 42, 31],
            },
          ];
          break;
        case "all":
          this.chartData.series = [
            {
              name: "Current",
              data: [18, 21, 45, 36, 65, 47, 51, 32, 40, 28, 31, 26],
            },
            {
              name: "Previous",
              data: [30, 11, 22, 18, 32, 23, 58, 45, 30, 36, 15, 34],
            },
          ];
          break;
        default:
      }
    }
  }
};
</script>

<template>
  <Layout>
    <PageHeader title="Blog" pageTitle="Dashboards" />
    <div>
      <BRow>
        <BCol xl="8">
          <BRow>
            <BCol lg="4" v-for="item in items" :key="item.id">
              <BCard no-body :class="item.cardClass">
                <BCardBody>
                  <div class="d-flex flex-wrap">
                    <div class="me-3">
                      <p class="text-muted mb-2">{{ item.label }}</p>
                      <h5 class="mb-0">{{ item.value }}</h5>
                    </div>

                    <div class="avatar-sm ms-auto">
                      <div class="avatar-title bg-light rounded-circle text-primary font-size-20">
                        <i :class="item.iconClass"></i>
                      </div>
                    </div>
                  </div>
                </BCardBody>
              </BCard>
            </BCol>
          </BRow>

          <BCard no-body>
            <BCardBody>
              <div class="d-flex flex-wrap">
                <BCardTitle class="me-2">Visitors</BCardTitle>
                <div class="ms-auto">
                  <div class="toolbar button-items text-end">
                    <BButton variant="light" class="ms-2" size="sm" @click="updateData('all')"
                      :class="{ active: selection === 'all' }">
                      ALL
                    </BButton>
                    <BButton variant="light" class="ms-2" size="sm" @click="updateData('one_month')"
                      :class="{ active: selection === 'one_month' }">
                      1M
                    </BButton>
                    <BButton variant="light" class="ms-2" size="sm" @click="updateData('six_months')"
                      :class="{ active: selection === 'six_months' }">
                      6M
                    </BButton>
                    <BButton variant="light" class="ms-2" size="sm" @click="updateData('one_year')"
                      :class="{ active: selection === 'one_year' }">
                      1Y
                    </BButton>
                  </div>
                </div>
              </div>

              <BRow class="text-center">
                <BCol lg="4" v-for="item in visitorData" :key="item.id">
                  <div class="mt-4">
                    <p class="text-muted mb-1">{{ item.title }}</p>
                    <h5>
                      {{ item.value }}
                      <span class="text-success font-size-13">{{ item.percentChange }} <i
                          :class="`${item.arrow}`"></i></span>
                    </h5>
                  </div>
                </BCol>
              </BRow>
              <hr class="mb-4" />
              <apexchart class="apex-charts" dir="ltr" height="350" width="1022" :series="chartData.series"
                :options="chartData.chartoptions"></apexchart>
            </BCardBody>
          </BCard>
        </BCol>

        <BCol xl="4">
          <BCard no-body>
            <BCardBody>
              <div class="d-flex">
                <div class="me-3">
                  <img :src="avatar1" alt="" class="avatar-sm rounded-circle img-thumbnail" />
                </div>
                <div class="flex-grow-1">
                  <div class="d-flex">
                    <div class="flex-grow-1">
                      <div class="text-muted">
                        <h5 class="mb-1">Henry wells</h5>
                        <p class="mb-0">UI / UX Designer</p>
                      </div>
                    </div>

                    <BDropdown toggle-class="btn-sm" variant="light" right class="ms-2 p-0"
                      menu-class="dropdown-menu-end">
                      <template #button-content>
                        <i class="bx bxs-cog align-middle me-1"></i> Setting
                      </template>
                      <BDropdownItem class="dropdown-item" href="#">Action</BDropdownItem>
                      <BDropdownItem class="dropdown-item" href="#">Another action</BDropdownItem>
                      <BDropdownItem class="dropdown-item" href="#">Something else</BDropdownItem>
                    </BDropdown>
                  </div>
                  <hr />
                  <BRow>
                    <BCol cols="4">
                      <div>
                        <p class="text-muted text-truncate mb-2">Total Post</p>
                        <h5 class="mb-0">32</h5>
                      </div>
                    </BCol>
                    <BCol cols="4">
                      <div>
                        <p class="text-muted text-truncate mb-2">Subscribes</p>
                        <h5 class="mb-0">10k</h5>
                      </div>
                    </BCol>
                  </BRow>
                </div>
              </div>
            </BCardBody>
          </BCard>

          <BCard no-body>
            <BCardBody>
              <div class="d-flex flex-wrap">
                <BCardTitle class="mb-3 me-2">Subscribes</BCardTitle>
                <BDropdown toggle-class="text-muted font-size-16 p-0" variant="white" right class="ms-auto"
                  menu-class="dropdown-menu-end">
                  <template #button-content>
                    <i class="mdi mdi-dots-horizontal"></i>
                  </template>
                  <BDropdownItem href="#">Action</BDropdownItem>
                  <BDropdownItem href="#">Another action</BDropdownItem>
                  <BDropdownItem href="#">Something else here</BDropdownItem>
                  <BDropdownDivider />
                  <BDropdownItem href="#">Separated link</BDropdownItem>
                </BDropdown>
              </div>

              <div class="d-flex flex-wrap">
                <div>
                  <p class="text-muted mb-1">Total Subscribe</p>
                  <h4 class="mb-3">10,512</h4>
                  <p class="text-success mb-0">
                    <span>0.6 % <i class="mdi mdi-arrow-top-right ms-1"></i></span>
                  </p>
                </div>
                <div class="ms-auto align-self-end">
                  <i class="bx bx-group display-4 text-light"></i>
                </div>
              </div>
            </BCardBody>
          </BCard>

          <BCard no-body>
            <BCardBody class="p-4">
              <div class="text-center">
                <div class="avatar-md mx-auto mb-4">
                  <div class="avatar-title bg-light rounded-circle text-primary h1">
                    <i class="mdi mdi-email-open"></i>
                  </div>
                </div>

                <BRow class="justify-content-center">
                  <BCol xl="10">
                    <h4 class="text-primary">Subscribe !</h4>
                    <p class="text-muted font-size-14 mb-4">
                      Subscribe our newletter and get notification to stay
                      update.
                    </p>

                    <div class="input-group bg-light rounded">
                      <input type="email" class="form-control bg-transparent border-0" placeholder="Enter Email address"
                        aria-label="Recipient's username" aria-describedby="button-addon2" />
                      <div class="input-group-append">
                        <BButton variant="primary" class="rounded" id="button-addon2">
                          <i class="bx bxs-paper-plane"></i>
                        </BButton>
                      </div>
                    </div>
                  </BCol>
                </BRow>
              </div>
            </BCardBody>
          </BCard>
        </BCol>
      </BRow>

      <BRow>
        <BCol xl="4" lg="6">
          <BCard no-body style="position: relative;">
            <BCardHeader class="bg-transparent border-bottom">
              <div class="flex-wrap blog-card">
                <BCardTitle class="mt-2">Posts</BCardTitle>
                <BTabs nav-class="nav-tabs-custom card-header-tabs ms-auto tamp" nav-wrapper-class="nav-item"
                  align="right">
                  <BTab title="Recent" active>
                    <BCardBody class="pb-1 mb-3 mt-2">
                      <simplebar data-simplebar style="max-height: 285px">
                        <ul class="list-group list-group-flush">
                          <li class="list-group-item" v-for="data of postData" :key="data.title">
                            <div class="d-flex">
                              <div class="me-3">
                                <img :src="data.image" alt="" class="avatar-md h-auto d-block rounded" />
                              </div>

                              <div class="align-self-center overflow-hidden me-auto">
                                <div>
                                  <h5 class="font-size-14 text-truncate">
                                    <BLink class="text-dark">{{ data.title }}</BLink>
                                  </h5>
                                  <p class="text-muted mb-0">{{ data.date }}</p>
                                </div>
                              </div>

                              <BDropdown class="ms-2" toggle-class="text-muted font-size-16 p-0" toggle-tag="a"
                                variant="white" menu-class="dropdown-menu-end" right>
                                <template #button-content>
                                  <i class="mdi mdi-dots-horizontal"></i>
                                </template>
                                <BDropdownItem>Action</BDropdownItem>
                                <BDropdownItem>Another Action</BDropdownItem>
                                <BDropdownItem>Third Action</BDropdownItem>
                                <BDropdownItem>Something else here</BDropdownItem>
                              </BDropdown>
                            </div>
                          </li>
                        </ul>
                      </simplebar>
                    </BCardBody>
                  </BTab>
                  <BTab title="Popular">
                    <BCardBody>
                      <simplebar data-simplebar style="max-height: 285px">
                        <ul class="list-group list-group-flush">
                          <li class="list-group-item" v-for="data of postData" :key="data.title">
                            <div class="d-flex">
                              <div class="me-3">
                                <img :src="data.image" alt="" class="avatar-md h-auto d-block rounded" />
                              </div>

                              <div class="align-self-center overflow-hidden me-auto">
                                <div>
                                  <h5 class="font-size-14 text-truncate">
                                    <BLink class="text-dark">{{ data.title }}</BLink>
                                  </h5>
                                  <p class="text-muted mb-0">{{ data.date }}</p>
                                </div>
                              </div>

                              <BDropdown class="ms-2" toggle-class="text-muted font-size-14 p-0" variant="white"
                                menu-class="dropdown-menu-end" right>
                                <template #button-content>
                                  <i class="mdi mdi-dots-horizontal"></i>
                                </template>
                                <BDropdownItem>Action</BDropdownItem>
                                <BDropdownItem>Another Action</BDropdownItem>
                                <BDropdownItem>Third Action</BDropdownItem>
                                <BDropdownItem>Something else here</BDropdownItem>
                              </BDropdown>
                            </div>
                          </li>
                        </ul>
                      </simplebar>
                    </BCardBody>
                  </BTab>
                </BTabs>
              </div>
            </BCardHeader>
          </BCard>
        </BCol>

        <BCol xl="4" lg="6">
          <BCard no-body>
            <BCardBody>
              <div class="d-flex flex-wrap">
                <div class="me-2">
                  <BCardTitle class="mb-3">Comments</BCardTitle>
                </div>
                <BDropdown class="ms-auto" variant="white" menu-class="dropdown-menu-end" toggle-class="font-size-16 p-0"
                  right>
                  <template #button-content>
                    <i class="mdi mdi-dots-horizontal"></i>
                  </template>
                  <BDropdownItem href="#">Action</BDropdownItem>
                  <BDropdownItem href="#">Another action</BDropdownItem>
                  <BDropdownItem href="#">Something else here</BDropdownItem>
                  <BDropdownDivider />
                  <BDropdownItem href="#">Separated link</BDropdownItem>
                </BDropdown>
              </div>

              <simplebar style="max-height: 310px">
                <BListGroup>
                  <BListGroupItem class="py-3">
                    <div class="d-flex">
                      <div class="avatar-xs me-3">
                        <div class="avatar-title rounded-circle bg-light text-primary">
                          <i class="bx bxs-user"></i>
                        </div>
                      </div>
                      <div class="flex-grow-1">
                        <h5 class="font-size-14 mb-1">
                          Delores Williams
                          <small class="text-muted float-end">1 hr Ago</small>
                        </h5>
                        <p class="text-muted">
                          If several languages coalesce, the grammar of the
                          resulting of the individual
                        </p>
                        <div>
                          <BLink href="javascript: void(0);" class="text-success"><i class="mdi mdi-reply me-1"></i> Reply
                          </BLink>
                        </div>
                      </div>
                    </div>
                  </BListGroupItem>

                  <BListGroupItem class="py-3">
                    <div class="d-flex">
                      <div class="avatar-xs me-3">
                        <img :src="avatar2" alt="" class="img-fluid d-block rounded-circle" />
                      </div>
                      <div class="flex-grow-1">
                        <h5 class="font-size-14 mb-1">
                          Clarence Smith
                          <small class="text-muted float-end">2 hrs Ago</small>
                        </h5>
                        <p class="text-muted">
                          Neque porro quisquam est, qui dolorem ipsum quia dolor
                          sit amet
                        </p>
                        <div>
                          <BLink href="javascript: void(0);" class="text-success"><i class="mdi mdi-reply"></i> Reply
                          </BLink>
                        </div>

                        <div class="d-flex">
                          <div class="flex-shrink-0 me-3">
                            <div class="avatar-xs">
                              <div class="avatar-title rounded-circle bg-light text-primary">
                                <i class="bx bxs-user"></i>
                              </div>
                            </div>
                          </div>
                          <div class="flex-grow-1">
                            <h5 class="font-size-14 mb-1">
                              Delores Williams
                              <small class="text-muted float-end">1 hr Ago</small>
                            </h5>
                            <p class="text-muted">
                              If several languages coalesce, the grammar of the
                              resulting of the individual
                            </p>
                            <div>
                              <BLink href="javascript: void(0);" class="text-success"><i class="mdi mdi-reply me-1"></i>
                                Reply
                              </BLink>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </BListGroupItem>

                  <liBListGroupItem class="py-3">
                    <div class="d-flex">
                      <div class="avatar-xs me-3">
                        <div class="avatar-title rounded-circle bg-light text-primary">
                          <i class="bx bxs-user"></i>
                        </div>
                      </div>
                      <div class="flex-grow-1">
                        <h5 class="font-size-14 mb-1">
                          Keith McCoy
                          <small class="text-muted float-end">12 Aug</small>
                        </h5>
                        <p class="text-muted">
                          Donec posuere vulputate arcu. phasellus accumsan
                          cursus velit
                        </p>
                        <div>
                          <BLink href="javascript: void(0);" class="text-success"><i class="mdi mdi-reply"></i> Reply
                          </BLink>
                        </div>
                      </div>
                    </div>
                  </liBListGroupItem>
                </BListGroup>
              </simplebar>
            </BCardBody>
          </BCard>
        </BCol>

        <BCol xl="4">
          <BCard no-body>
            <BCardBody>
              <div class="d-flex flex-wrap">
                <div class="me-2">
                  <BCardTitle class="mb-3">Top Visitors</BCardTitle>
                </div>
                <BDropdown class="ms-auto" toggle-tag="a" menu-class="dropdown-menu-end" toggle-class="font-size-16 p-0"
                  variant="white" right>
                  <template #button-content>
                    <i class="mdi mdi-dots-horizontal"></i>
                  </template>
                  <BDropdownItem href="#">Action</BDropdownItem>
                  <BDropdownItem href="#">Another action</BDropdownItem>
                  <BDropdownItem href="#">Something else here</BDropdownItem>
                  <BDropdownDivider />
                  <BDropdownItem href="#">Separated link</BDropdownItem>
                </BDropdown>
              </div>
              <BRow class="text-center">
                <BCol cols="6">
                  <div class="mt-3">
                    <p class="text-muted mb-1">Today</p>
                    <h5>1024</h5>
                  </div>
                </BCol>

                <BCol cols="6">
                  <div class="mt-3">
                    <p class="text-muted mb-1">This Month</p>
                    <h5>
                      12356
                      <span class="text-success font-size-13">0.2 % <i class="mdi mdi-arrow-up ms-1"></i></span>
                    </h5>
                  </div>
                </BCol>
              </BRow>

              <hr />

              <div>
                <BListGroup class="list-group-flush">
                  <BListGroupItem v-for="item in  topVisitorsData " :key="item.id">
                    <div class="py-2">
                      <h5 class="font-size-14">
                        {{ item.location }} <span class="float-end">{{ item.percentage }}</span>
                      </h5>
                      <BProgress class="animated-progess progress-sm">
                        <BProgressBar :variant="`${item.progressColor}`" role="progressbar"
                          :style="{ width: item.percentage }" :aria-valuenow="item.percentage" aria-valuemin="0"
                          aria-valuemax="100"></BProgressBar>
                      </BProgress>
                    </div>
                  </BListGroupItem>
                </BListGroup>
              </div>
            </BCardBody>
          </BCard>
        </BCol>
      </BRow>

      <BRow>
        <BCol xl="4">
          <BCard no-body>
            <BCardBody>
              <div class="d-flex">
                <div class="me-2">
                  <BCardTitle class="mb-4">Activity</BCardTitle>
                </div>
                <BDropdown toggle-tag="a" toggle-class="text-muted font-size-16 p-0" variant="white"
                  menu-class="dropdown-menu-end" right class="ms-auto">
                  <template #button-content>
                    <i class="mdi mdi-dots-horizontal"></i>
                  </template>
                  <BDropdownItem href="#">Action</BDropdownItem>
                  <BDropdownItem href="#">Another action</BDropdownItem>
                  <BDropdownItem href="#">Something else here</BDropdownItem>
                  <BDropdownDivider />
                  <BDropdownItem href="#">Separated link</BDropdownItem>
                </BDropdown>
              </div>
              <simplebar data-simplebar class="mt-2" style="max-height: 280px">
                <ul class="verti-timeline list-unstyled">
                  <li class="event-list" v-for="item in activityData" :key="item.id">
                    <div class="event-timeline-dot">
                      <i class="bx" :class="item.iconClass"></i>
                    </div>
                    <div class="d-flex">
                      <div class="me-3">
                        <h5 class="font-size-14">
                          {{ item.date }}
                          <i class="bx bx-right-arrow-alt font-size-16 text-primary align-middle ms-2"></i>
                        </h5>
                      </div>
                      <div class="flex-grow-1">
                        <div>
                          {{ item.content }}
                          <BLink :href="item.link"> {{ item.linkText }}</BLink>
                        </div>
                      </div>
                    </div>
                  </li>
                </ul>
              </simplebar>

              <div class="text-center mt-4">
                <BLink class="btn btn-primary waves-effect waves-light btn-sm">View More <i
                    class="mdi mdi-arrow-right ms-1"></i></BLink>
              </div>
            </BCardBody>
          </BCard>
        </BCol>
        <BCol xl="8">
          <BCard no-body>
            <BCardBody>
              <div class="d-flex">
                <div class="me-2">
                  <BCardTitle class="mb-4">Popular post</BCardTitle>
                </div>
                <BDropdown toggle-tag="a" class="ms-auto" menu-class="dropdown-menu-end" toggle-class="font-size-16 p-0"
                  variant="white" right>
                  <template #button-content>
                    <i class="mdi mdi-dots-horizontal"></i>
                  </template>
                  <BDropdownItem href="#">Action</BDropdownItem>
                  <BDropdownItem href="#">Another action</BDropdownItem>
                  <BDropdownItem href="#">Something else here</BDropdownItem>
                  <BDropdownDivider />
                  <BDropdownItem href="#">Separated link</BDropdownItem>
                </BDropdown>
              </div>

              <div class="table-responsive mb-0">
                <BTableSimple class="table-centered table-nowrap mb-0">
                  <BTbody>
                    <BTr>
                      <BTh scope="col" colspan="2">Post</BTh>
                      <BTh scope="col">Likes</BTh>
                      <BTh scope="col">Comments</BTh>
                      <BTh scope="col">Action</BTh>
                    </BTr>
                    <BTr v-for="item in blogPostData" :key="item.id">
                      <BTd style="width: 100px">
                        <img :src="item.imageSrc" alt="" class="avatar-md h-auto d-block rounded" />
                      </BTd>
                      <BTd>
                        <h5 class="font-size-13 text-truncate mb-1">
                          <BLink :href="item.link" class="text-dark">{{ item.title }}</BLink>
                        </h5>
                        <p class="text-muted mb-0">{{ item.date }}</p>
                      </BTd>
                      <BTd class="pt-4"><i class="bx bx-like align-middle me-1"></i> {{ item.likes }}</BTd>
                      <BTd class="pt-4"><i class="bx bx-comment-dots align-middle me-1"></i> {{ item.comments }}</BTd>
                      <BTd>
                        <BDropdown toggle-class="text-muted font-size-16" variant="white" right>
                          <template #button-content>
                            <i class="mdi mdi-dots-horizontal"></i>
                          </template>
                          <BDropdownItem href="#">Action</BDropdownItem>
                          <BDropdownItem href="#">Another action</BDropdownItem>
                          <BDropdownItem href="#">Something else here</BDropdownItem>
                          <BDropdownDivider />
                          <BDropdownItem href="#">Separated link</BDropdownItem>
                        </BDropdown>
                      </BTd>
                    </BTr>
                  </BTbody>
                </BTableSimple>
              </div>
            </BCardBody>
          </BCard>
        </BCol>
      </BRow>
    </div>
  </Layout>
</template>

<style>
.tamp {
  position: absolute;
  right: 18px;
  top: 9px;
}
</style>