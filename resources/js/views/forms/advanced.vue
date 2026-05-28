<script>
import { ref } from 'vue'

import Multiselect from "@vueform/multiselect";

import flatPickr from "vue-flatpickr-component";
import "flatpickr/dist/flatpickr.css";
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect'
import 'flatpickr/dist/plugins/monthSelect/style.css'

import "@vueform/multiselect/themes/default.css";
import Layout from "../../layouts/main.vue";
import PageHeader from "../../components/page-header.vue";
/**
 * Advanced-form component
 */
export default {
  watch: {
  },
  components: {
    flatPickr,
    Multiselect,
    Layout,
    PageHeader,
  },
  data() {
    return {
      picked: ref(new Date()),

      defaultdate: "",
      autoClose: ref(new Date()),
      daterange: ref([]),

      demoMonth: ref(new Date()),
      demoYear: ref(new Date()),
      monthConfig: {
        dateFormat: 'Y-m',
        plugins: [new monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m' })]
      },
      time: ref(""),
      danger: false,
      info: false,
      success: false,
      primary: false,
      secondary: false,
      customColor: false,
      customColor1: false,
      customColor2: false,
      customColor3: false,
      value: null,
      value1: null,
      options: [
        "Alaska",
        "Hawaii",
        "California",
        "Nevada",
        "Oregon",
        "Washington",
        "Arizona",
        "Colorado",
        "Idaho",
        "Montana",
        "Nebraska",
        "New Mexico",
        "North Dakota",
        "Utah",
        "Wyoming",
        "Alabama",
        "Arkansas",
        "Illinois",
        "Iowa",
      ],
      maxlen: "",
      data: "",
      option: "",
      textarea: "",
      defaultNull: null,
      defaultspinval: 1,
    };
  },
};
</script>

<template>
  <Layout>
    <PageHeader title="Form Advanced" pageTitle="Forms" />

    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Select2</BCardTitle>
            <p class="card-title-desc">A mobile and touch friendly input spinner component for Bootstrap</p>
            <BCol sm="8">
              <label>Single Select</label>
              <Multiselect v-model="value" :options="options"></Multiselect>

              <label class="mt-3">Multiple Select</label>
              <Multiselect v-model="value1" :options="options" :mode="'multiple'"></Multiselect>
            </BCol>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Css Switch</BCardTitle>
            <p class="card-title-desc">Here are a few types of switches.</p>

            <div class="d-flex gap-1 mb-4">

              <BFormInput type="checkbox" id="switch1" switch="none" checked="" />
              <label for="switch1" data-on-label="On" data-off-label="Off"></label>

              <BFormInput type="checkbox" id="switch2" switch="default" checked="" />
              <label for="switch2" data-on-label="" data-off-label=""></label>

              <BFormInput type="checkbox" id="switch3" switch="bool" checked="" />
              <label for="switch3" data-on-label="Yes" data-off-label="No"></label>

              <BFormInput type="checkbox" id="switch6" switch="primary" checked="" />
              <label for="switch6" data-on-label="Yes" data-off-label="No"></label>

              <BFormInput type="checkbox" id="switch4" switch="success" checked="" />
              <label for="switch4" data-on-label="Yes" data-off-label="No"></label>

              <BFormInput type="checkbox" id="switch7" switch="info" checked="" />
              <label for="switch7" data-on-label="Yes" data-off-label="No"></label>

              <BFormInput type="checkbox" id="switch5" switch="warning" checked="" />
              <label for="switch5" data-on-label="Yes" data-off-label="No"></label>

              <BFormInput type="checkbox" id="switch8" switch="danger" checked="" />
              <label for="switch8" data-on-label="Yes" data-off-label="No"></label>

              <BFormInput type="checkbox" id="switch9" switch="dark" checked="" />
              <label for="switch9" data-on-label="Yes" data-off-label="No"></label>
            </div>

            <BCol lg="6">
              <div class="mt-4 mt-lg-0">
                <h5 class="font-size-14 mb-3">Square switch</h5>
                <div class="d-flex">
                  <div class="square-switch">
                    <BFormInput type="checkbox" id="square-switch1" switch="none" checked="" />
                    <label for="square-switch1" data-on-label="On" data-off-label="Off"></label>
                  </div>
                  <div class="square-switch">
                    <BFormInput type="checkbox" id="square-switch2" switch="info" checked="" />
                    <label for="square-switch2" data-on-label="Yes" data-off-label="No"></label>
                  </div>
                  <div class="square-switch">
                    <BFormInput type="checkbox" id="square-switch3" switch="bool" checked="" />
                    <label for="square-switch3" data-on-label="Yes" data-off-label="No"></label>
                  </div>
                </div>
              </div>
            </BCol>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Date Picker</BCardTitle>
            <p class="card-title-desc">Examples of bootstrap datepicker.</p>

            <div>
              <div class="mb-3">
                <label>Default Date Picker</label>
                <br />
                <flat-pickr v-model="picked" :config="{ dateFormat: 'Y-m-d' }" class="form-control"></flat-pickr>
              </div>

              <div class="mb-3">
                <label>Auto close</label>
                <br />
                <flat-pickr v-model="autoClose" :config="{ dateFormat: 'Y-m-d', allowInput: true }"
                  class="form-control" />
              </div>

              <div class="mb-3">
                <label>Date Range</label>
                <br />
                <flat-pickr v-model="daterange" :config="{ mode: 'range', dateFormat: 'Y-m-d' }" class="form-control" />
              </div>

              <div class="mb-3">
                <label>Month View</label>
                <br />
                <flat-pickr v-model="demoMonth" :config="monthConfig" class="form-control" placeholder="Select Month" />
              </div>

              <div class="mb-3">
                <label>Year View</label>
                <br />
                <flat-pickr v-model="demoYear" :config="{ dateFormat: 'Y' }" class="form-control"
                  placeholder="Select Year" />
              </div>

              <div class="mb-3">
                <label>Time View</label>
                <br />
                <flat-pickr class="form-control" v-model="time"
                  :config="{ enableTime: true, noCalendar: true, dateFormat: 'H:i:S' }"
                  placeholder="Select Time"></flat-pickr>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Bootstrap MaxLength</BCardTitle>

            <p class="card-title-desc">Uses the HTML5 attribute "maxlength" to work.</p>

            <label class="mb-1 fw-medium">Default usage</label>
            <p class="text-muted font-13">The badge will show up by default when the remaining chars are 10 or less:</p>
            <BFormTextarea v-model="textarea" :maxlength="225" rows="3"
                placeholder="This textarea has a limit of 225 chars."></BFormTextarea>
            <div v-if="maxlen" class="text-center">
              <p class="badge position-absolute" :class="{
                'bg-success': maxlen.length !== 10,
                'bg-danger': maxlen.length === 10
              }">{{ maxlen.length }} / 10</p>
            </div>

            <div>
              <label class="mb-1 mt-3 fw-medium">Threshold value</label>
              <p class="text-muted font-13">
                Do you want the badge to show up when there are 20 chars or less? Use the
                <code>threshold</code>
                option:
              </p>
              <BFormInput v-model="data" type="text" :maxlength="25" />
              <div class="text-center">
                <p v-if="data" class="badge position-absolute" :class="{
                  'bg-success': data.length !== 25,
                  'bg-danger': data.length === 25
                }">{{ data.length }} / 25</p>
              </div>
            </div>

            <div>
              <label class="mb-1 mt-3 fw-medium">All the options</label>
              <p class="text-muted font-13">
                Please note: if the
                <code>alwaysShow</code> option is enabled, the
                <code>threshold</code> option
                is
                ignored.
              </p>
              <BFormInput v-model="option" type="text" :maxlength="25" />
              <div class="text-center">
                <p v-if="option" class="badge position-absolute" :class="{
                  'bg-success': option.length !== 25,
                  'bg-danger': option.length === 25
                }">
                  You typed
                  {{ option.length }} out of 25 chars available.
                </p>
              </div>
            </div>

            <div>
              <label class="mb-1 mt-3 fw-medium">Textarea</label>
              <p class="text-muted font-13">Bootstrap maxlength supports textarea as well as inputs. Even on old IE.</p>
              <BFormTextarea v-model="textarea" :maxlength="225" rows="3" placeholder="This textarea has a limit of 225 chars."></BFormTextarea>
              <div class="text-center">
                <p v-if="textarea" class="badge position-absolute" :class="{
                  'bg-success': textarea.length !== 225,
                  'bg-danger': textarea.length === 225
                }">
                  {{ textarea.length }} /
                  225
                </p>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
