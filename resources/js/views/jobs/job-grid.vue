<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "../../components/page-header.vue";

import flatPickr from "vue-flatpickr-component";
import "flatpickr/dist/flatpickr.css";

import { jobData } from "@/views/jobs/jobgridData";
import WorkingPagination from "../../components/common/working-pagination.vue";

/**
 * Starter component
 */
export default {
    components: {
        Layout,
        PageHeader,
        flatPickr,
        WorkingPagination,
    },
    data() {
        return {
            jobData,
            searchQuery: "",

            defaultDateConfig: {
                dateFormat: "d M, Y",
                defaultDate: "25 Dec, 2021",

            },
            page: 1,
            perPage: 8,

            // adobe, adobephoto, airbnb, amazon, flutter, mailchimp, line, spotify, wordpress, upwork, sass, reddit,
        };
    },
    computed: {
        filteredJobs() {
            const query = this.searchQuery.toLowerCase();
            return this.jobData.filter((job) => {
                return (
                    job.title.toLowerCase().includes(query) ||
                    job.company.toLowerCase().includes(query) ||
                    job.location.toLowerCase().includes(query)
                );
            });
        },
        paginatedJobs() {
            const start = (this.page - 1) * this.perPage;
            const end = this.page * this.perPage;
            return this.filteredJobs.slice(start, end);
        },
        totalPages() {
            return Math.ceil(this.filteredJobs.length / this.perPage);
        },
    },
    methods: {
        changePage(p) {
            this.page = p;
        },
    },
};
</script>

<template>
    <Layout>
        <PageHeader title="Jobs Grid" pageTitle="Jobs" />

        <!-- FILTER -->
        <BRow>
            <BCol lg="12">
                <BCard no-body class="job-filter">
                    <BCardBody class="p-3">
                        <BForm @submit.prevent>
                            <BRow class="g-3">
                                <BCol xxl="4" lg="4">
                                    <BFormInput v-model="searchQuery" placeholder="Search your job"
                                        autocomplete="off" />
                                </BCol>

                                <BCol xxl="2" lg="4">
                                    <BFormInput placeholder="San Francisco, LA" autocomplete="off" />
                                </BCol>

                                <BCol xxl="2" lg="4">
                                    <BFormInput placeholder="Job Categories" autocomplete="off" />
                                </BCol>

                                <BCol xxl="2" lg="6">
                                    <flat-pickr v-model="date6" :config="defaultDateConfig" class="form-control"
                                        placeholder="Select date" />
                                </BCol>

                                <BCol xxl="2" lg="6">
                                    <div class="position-relative h-100 hstack gap-3">
                                        <BButton variant="primary" type="submit" class="h-100 w-100">
                                            <i class="bx bx-search-alt align-middle" /> Find Jobs
                                        </BButton>
                                        <BButton v-b-toggle.collapseExample variant="secondary" class="h-100 w-100">
                                            <i class="bx bx-filter-alt align-middle" /> Advance
                                        </BButton>
                                    </div>
                                </BCol>

                                <BCollapse id="collapseExample" class="mt-4">
                                    <div class="pt-4">
                                        <BRow class="g-3">
                                            <BCol xxl="4" lg="6">
                                                <label class="form-label d-flex">Experience</label>
                                                <BFormCheckboxGroup>
                                                    <BFormCheckbox>All</BFormCheckbox>
                                                    <BFormCheckbox>Fresher</BFormCheckbox>
                                                    <BFormCheckbox>1-2</BFormCheckbox>
                                                    <BFormCheckbox>2-3</BFormCheckbox>
                                                    <BFormCheckbox>4+</BFormCheckbox>
                                                </BFormCheckboxGroup>
                                            </BCol>

                                            <BCol xxl="4" lg="6">
                                                <label class="form-label d-flex">Job Type</label>
                                                <BFormCheckboxGroup>
                                                    <BFormCheckbox>Full Time</BFormCheckbox>
                                                    <BFormCheckbox>Part Time</BFormCheckbox>
                                                    <BFormCheckbox>Freelance</BFormCheckbox>
                                                    <BFormCheckbox>Internship</BFormCheckbox>
                                                </BFormCheckboxGroup>
                                            </BCol>

                                            <BCol xxl="4" lg="4">
                                                <label class="form-label">Qualification</label>
                                                <input type="text" class="form-control" placeholder="Qualification" />
                                            </BCol>
                                        </BRow>
                                    </div>
                                </BCollapse>
                            </BRow>
                        </BForm>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>

        <!-- JOB GRID -->
        <BRow>
            <BCol xl="3" md="6" v-for="job in paginatedJobs" :key="job.id">
                <BCard no-body>
                    <BCardBody>
                        <!-- Job details using data from the JSON object -->
                        <div class="favorite-icon">
                            <BLink href="javascript:void(0)"><i class="uil uil-heart-alt fs-18"></i></BLink>
                        </div>
                        <img :src="job.imageUrl" alt="" height="50" class="mb-3" />
                        <h5 class="fs-17 mb-2">
                            <BLink href="#" class="text-dark">{{ job.title }}</BLink>
                            <small class="text-muted fw-normal">{{ job.experience }}</small>
                        </h5>
                        <ul class="list-inline mb-0">
                            <li class="list-inline-item">
                                <p class="text-muted fs-14 mb-1">{{ job.company }}</p>
                            </li>
                            <li class="list-inline-item">
                                <p class="text-muted fs-14 mb-0">
                                    <i class="mdi mdi-map-marker"></i> {{ job.location }}
                                </p>
                            </li>
                            <li class="list-inline-item">
                                <p class="text-muted fs-14 mb-0">
                                    <i class="uil uil-wallet"></i> {{ job.salary }}
                                </p>
                            </li>
                        </ul>
                        <div class="mt-3 hstack gap-2">
                            <span class="badge rounded-1 badge-soft-success">{{ job.employmentType }}</span>
                            <span class="badge rounded-1 badge-soft-warning">{{ job.urgency }}</span>
                            <span class="badge rounded-1 badge-soft-info">{{ job.privacy }}</span>
                        </div>
                        <div class="mt-4 hstack gap-2">
                            <BLink href="#" class="btn btn-soft-success w-100">View Profile</BLink>
                            <BLink href="#" class="btn btn-soft-primary w-100">Apply Now</BLink>
                        </div>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>
        <!-- End JOB GRID -->

        <!-- PAGINATION -->
        <WorkingPagination :total-pages="totalPages" :current-page="page" @page-changed="changePage" />

    </Layout>
</template>