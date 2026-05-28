<script>

import Layout from "../../layouts/main.vue";
import PageHeader from "../../components/page-header.vue";

/**
 * General-ui component
 */
export default {
  components: { Layout, PageHeader },
  data() {
    return {
      defaultvalue: 3,
      currentpage: 3
    };
  }
};
</script>

<template>
  <Layout>
    <PageHeader title="General" pageTitle="UI Elements" />
    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardBody>
            <BRow>
              <BCol lg="6">
                <div>
                  <BCardTitle>Badges</BCardTitle>
                  <p class="card-title-desc">
                    Add any of the below mentioned modifier classes to change
                    the appearance of a badge.
                  </p>
                  <div>
                    <BBadge variant="primary">Primary</BBadge>
                    <BBadge variant="success" class="ms-1">Success</BBadge>
                    <BBadge variant="info" class="ms-1">Info</BBadge>
                    <BBadge variant="warning" class="ms-1">Warning</BBadge>
                    <BBadge variant="danger" class="ms-1">Danger</BBadge>
                    <BBadge variant="dark" class="ms-1">Dark</BBadge>
                  </div>
                </div>
                <div class="mt-5">
                  <h5 class="font-size-14">Soft Badge</h5>
                  <div class="mt-1">
                    <span class="badge badge-soft-primary">Primary</span>
                    <span class="badge badge-soft-success">Success</span>
                    <span class="badge badge-soft-info">Info</span>
                    <span class="badge badge-soft-warning">Warning</span>
                    <span class="badge badge-soft-danger">Danger</span>
                    <span class="badge badge-soft-dark text-body">Dark</span>
                  </div>
                </div>
              </BCol>
              <BCol lg="6">
                <div class="mt-4 mt-lg-0">
                  <BCardTitle>Pill Badges</BCardTitle>
                  <p class="card-title-desc">
                    Use the
                    <code>.badge-pill</code> modifier class to make badges more
                    rounded (with a larger
                    <code>border-radius</code>
                    and additional horizontal
                    <code>padding</code>). Useful if you miss the badges from
                    v3.
                  </p>

                  <div>
                    <BBadge variant="primary" pill>Primary</BBadge>
                    <BBadge variant="success" class="ms-1" pill>Success</BBadge>
                    <BBadge variant="info" class="ms-1" pill>Info</BBadge>
                    <BBadge variant="warning" class="ms-1" pill>Warning</BBadge>
                    <BBadge variant="danger" class="ms-1" pill>Danger</BBadge>
                    <BBadge variant="dark" class="ms-1" pill>Dark</BBadge>
                  </div>
                  <div class="mt-5">
                    <h5 class="font-size-14">Soft Badge</h5>
                    <div class="mt-1">
                      <span class="badge rounded-pill badge-soft-primary">Primary</span>
                      <span class="badge rounded-pill badge-soft-success">Success</span>
                      <span class="badge rounded-pill badge-soft-info">Info</span>
                      <span class="badge rounded-pill badge-soft-warning">Warning</span>
                      <span class="badge rounded-pill badge-soft-danger">Danger</span>
                      <span class="badge rounded-pill badge-soft-dark text-body">Dark</span>
                    </div>
                  </div>
                </div>
              </BCol>
            </BRow>

            <hr class="mt-5">
            <BRow>
              <BCol xl="6">
                <div class="mt-4">
                  <BCardTitle>Badges in Buttons</BCardTitle>
                  <p class="card-title-desc">Badges can be used as part of links or buttons to provide a counter.</p>

                  <div class="d-flex flex-wrap gap-2">
                    <BButton type="button" variant="primary">
                      Notifications <BBadge variant="success" class="ms-1">4</BBadge>
                    </BButton>
                    <BButton type="button" variant="success">
                      Messages <BBadge variant="danger" class="ms-1">2</BBadge>
                    </BButton>
                    <BButton type="button" variant="none" class="btn-outline-secondary">
                      Draft <BBadge variant="success" class="ms-1">2</BBadge>
                    </BButton>
                  </div>
                </div>
              </BCol>

              <BCol xl="6">
                <div class="mt-4">
                  <BCardTitle>Badges Position Examples</BCardTitle>
                  <p class="card-title-desc">Example of Badges Position</p>

                  <div class="d-flex flex-wrap gap-3">
                    <BButton type="button" variant="primary" class="position-relative">
                      Mails <BBadge class="position-absolute top-0 start-100 translate-middle rounded-pill"
                        variant="success">+99 <span class="visually-hidden">unread messages</span></BBadge>
                    </BButton>


                    <BButton type="button" variant="light" class="position-relative">
                      Alerts <BBadge
                        class="position-absolute top-0 start-100 translate-middle border border-light rounded-circle p-1"
                        variant="danger"><span class="visually-hidden">unread messages</span></BBadge>
                    </BButton>

                    <BButton type="button" variant="primary" class="position-relative p-0 avatar-xs rounded">
                      <span class="avatar-title bg-transparent">
                        <i class="bx bxs-envelope"></i>
                      </span>
                      <BBadge
                        class="position-absolute top-0 start-100 translate-middle border border-light rounded-circle p-1"
                        variant="danger"><span class="visually-hidden">unread messages</span></BBadge>
                    </BButton>

                    <BButton type="button" variant="light" class="position-relative p-0 avatar-xs rounded-circle">
                      <span class="avatar-title bg-transparent text-reset">
                        <i class="bx bxs-bell"></i>
                      </span>
                    </BButton>

                    <BButton type="button" variant="light" class="position-relative p-0 avatar-xs rounded-circle">
                      <span class="avatar-title bg-transparent text-reset">
                        <i class="bx bx-menu"></i>
                      </span>
                      <BBadge
                        class="position-absolute top-0 start-100 translate-middle border border-light rounded-circle p-1"
                        variant="success"><span class="visually-hidden">unread messages</span></BBadge>
                    </BButton>
                  </div>
                </div>
              </BCol>
            </BRow>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Popovers</BCardTitle>
            <p class="card-title-desc">
              Add small overlay content, like those found in iOS, to any element
              for housing secondary information.
            </p>

            <div class="button-items">
              <BButton class="me-2" v-b-popover.top="'Vivamus sagittis lacus vel augue laoreet rutrum faucibus.'
                " variant="secondary">Popover on top</BButton>
              <BButton class="me-2" v-b-popover.bottom="'Vivamus sagittis lacus vel augue laoreet rutrum faucibus.'
                " variant="secondary">Popover on bottom</BButton>
              <BButton class="me-2" v-b-popover.right="'Vivamus sagittis lacus vel augue laoreet rutrum faucibus.'
                " variant="secondary">Popover on right</BButton>
              <BButton v-b-popover.left="'Vivamus sagittis lacus vel augue laoreet rutrum faucibus.'
                " variant="secondary">Popover on left</BButton>
              <BButton class="me-2 btn-success waves-effect waves-light"
                v-b-popover.hover="'Vivamus sagittis lacus vel augue laoreet rutrum faucibus.'" variant="secondary">
                Dismissible popover</BButton>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol lg="6">
        <div class="card">
          <div class="card-body">

            <h4 class="card-title">Tooltips</h4>
            <p class="card-title-desc">Hover over the links below to see tooltips:</p>

            <div class="d-flex flex-wrap gap-2">
              <button type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top"
                title="Tooltip on top">
                Tooltip on top
              </button>

              <button type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="right"
                title="Tooltip on right">
                Tooltip on right
              </button>

              <button type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="bottom"
                title="Tooltip on bottom">
                Tooltip on bottom
              </button>

              <button type="button" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="left"
                title="Tooltip on left">
                Tooltip on left
              </button>

            </div>

          </div>
        </div>

      </BCol>
    </BRow>

    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Pagination</BCardTitle>
            <BRow>
              <BCol lg="6">
                <h5 class="font-size-14">Default Example</h5>
                <p class="card-title-desc">
                  Pagination links indicate a series of related content exists
                  across multiple pages.
                </p>
                <BPagination v-model="defaultvalue" :total-rows="50" :per-page="10" aria-controls="my-table">
                </BPagination>
              </BCol>

              <BCol lg="6">
                <div class="mt-4">
                  <BCardTitle>Alignment</BCardTitle>
                  <p class="card-title-desc">
                    Change the alignment of pagination components with flexbox
                    utilities.
                  </p>

                  <BPagination :total-rows="50" prev-text="Previous" next-text="Next" align="center"></BPagination>

                  <BPagination :total-rows="50" prev-text="Previous" next-text="Next" align="right"></BPagination>
                </div>
              </BCol>
            </BRow>

            <BRow>
              <BCol lg="6">
                <div class="mt-4">
                  <h5 class="font-size-14">Sizing</h5>
                  <p class="card-title-desc">
                    Fancy larger or smaller pagination? Add
                    <code>.pagination-lg</code> or
                    <code>.pagination-sm</code> for additional sizes.
                  </p>
                  <BPagination size="lg" :total-rows="30" :per-page="10" aria-controls="my-table"></BPagination>

                  <BPagination size="sm" :total-rows="30" :per-page="10" aria-controls="my-table"></BPagination>
                </div>
              </BCol>

              <div class="col-xl-6">
                <div class="mt-4">
                  <h5 class="font-size-14">Alignment</h5>
                  <p class="card-title-desc">Change the alignment of pagination
                    components with flexbox utilities.</p>

                  <nav aria-label="Page navigation example">
                    <ul class="pagination justify-content-center">
                      <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1">Previous</a>
                      </li>
                      <li class="page-item"><a class="page-link" href="#">1</a></li>
                      <li class="page-item"><a class="page-link" href="#">2</a></li>
                      <li class="page-item"><a class="page-link" href="#">3</a></li>
                      <li class="page-item">
                        <a class="page-link" href="#">Next</a>
                      </li>
                    </ul>
                  </nav>

                  <nav aria-label="Page navigation example">
                    <ul class="pagination justify-content-end">
                      <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1">Previous</a>
                      </li>
                      <li class="page-item"><a class="page-link" href="#">1</a></li>
                      <li class="page-item"><a class="page-link" href="#">2</a></li>
                      <li class="page-item"><a class="page-link" href="#">3</a></li>
                      <li class="page-item">
                        <a class="page-link" href="#">Next</a>
                      </li>
                    </ul>
                  </nav>
                </div>
              </div>
            </BRow>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Border spinner</BCardTitle>
            <p class="card-title-desc">
              Use the border spinners for a lightweight loading indicator.
            </p>
            <div>
              <BSpinner class="m-2" variant="primary" role="status"></BSpinner>
              <BSpinner class="m-2" variant="secondary" role="status"></BSpinner>
              <BSpinner class="m-2" variant="success" role="status"></BSpinner>
              <BSpinner class="m-2" variant="info" role="status"></BSpinner>
              <BSpinner class="m-2" variant="warning" role="status"></BSpinner>
              <BSpinner class="m-2" variant="danger" role="status"></BSpinner>
              <BSpinner class="m-2" variant="dark" role="status"></BSpinner>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Growing spinner</BCardTitle>
            <p class="card-title-desc">
              If you don’t fancy a border spinner, switch to the grow spinner.
              While it doesn’t technically spin, it does repeatedly grow!
            </p>
            <div>
              <BSpinner type="grow" class="m-2" variant="primary" role="status"></BSpinner>
              <BSpinner type="grow" class="m-2" variant="secondary" role="status"></BSpinner>
              <BSpinner type="grow" class="m-2" variant="success" role="status"></BSpinner>
              <BSpinner type="grow" class="m-2" variant="info" role="status"></BSpinner>
              <BSpinner type="grow" class="m-2" variant="warning" role="status"></BSpinner>
              <BSpinner type="grow" class="m-2" variant="danger" role="status"></BSpinner>
              <BSpinner type="grow" class="m-2" variant="dark" role="status"></BSpinner>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <div class="row">
      <div class="col-xl-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Close Button</h4>
            <p class="card-title-desc">Provide an option to dismiss or close a component with <code>.btn-close</code>.
              Default styling is limited, but highly customizable. Modify the Sass variables to replace the default
              <code>background-image</code>. <strong>Be sure to include text for screen readers</strong>, as we’ve done
              with <code>aria-label</code>.
            </p>

            <div>
              <button type="button" class="btn-close" aria-label="Close"></button>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xl-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Disable Close Button</h4>
            <p class="card-title-desc">Disabled close buttons change their <code>opacity</code>. We’ve also applied
              <code>pointer-events: none</code> and <code>user-select: none</code> to preventing hover and active states
              from triggering.
            </p>

            <div>
              <button type="button" class="btn-close" disabled aria-label="Close"></button>
            </div>

          </div>
        </div>
      </div>
    </div>
    <!-- end row -->

    <div class="row">
      <div class="col-xl-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Close Button White Variant</h4>
            <p class="card-title-desc">Change the default <code>.btn-close</code> to be white with the
              <code>.btn-close-white</code> class. This class uses the <code>filter</code> property to invert the
              <code>background-image</code>.
            </p>

            <div class="card-body bg-dark">
              <button type="button" class="btn-close btn-close-white" aria-label="Close"></button>
              <button type="button" class="btn-close btn-close-white" disabled="" aria-label="Close"></button>
            </div>

          </div>
        </div>
      </div>
    </div>
    <!-- end row -->

  </Layout>
</template>