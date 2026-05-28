<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "../../components/page-header.vue";

/**
 * Progressbar component
 */
export default {
  components: { Layout, PageHeader },
  data() {
    return {
      customList: [
        { value: 84, variant: 'danger', iconClass: 'bx bxl-html5 text-danger font-size-18' },
        { value: 75, variant: 'primary', iconClass: 'bx bxl-css3 text-primary font-size-18' },
        { value: 62, variant: 'info', iconClass: 'bx bxl-jquery text-info font-size-18' }
      ],
      steps: [
        { label: 1, variant: 'primary', position: 'start-0' },
        { label: 2, variant: 'primary', position: 'start-50' },
        { label: 3, variant: 'secondary', position: 'start-100' }
      ]
    }
  }
};
</script>

<template>
  <Layout>
    <PageHeader title="Progress Bars" pageTitle="UI Elements" />
    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Default Examples</BCardTitle>
            <p class="card-title-desc">
              Progress components are built with two
              HTML elements, some CSS to set the width, and a few attributes.
            </p>
            <BProgress :value="25" :max="100" class="mb-4"></BProgress>
            <BProgress :value="50" :max="100" class="mb-4"></BProgress>
            <BProgress :value="75" :max="100" class="mb-4"></BProgress>
            <BProgress :value="100"></BProgress>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Backgrounds</BCardTitle>
            <p class="card-title-desc">
              Use background utility classes to
              change the appearance of individual progress bars.
            </p>
            <BProgress :value="25" :max="100" class="mb-4" variant="success"></BProgress>
            <BProgress :value="50" :max="100" class="mb-4" variant="info"></BProgress>
            <BProgress :value="75" :max="100" class="mb-4" variant="warning"></BProgress>
            <BProgress :value="100" variant="danger"></BProgress>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Labels Example</BCardTitle>
            <p class="card-title-desc">
              Add labels to your progress bars by placing text within the
              <code class="highlighter-rouge">.progress-bar</code>.
            </p>
            <BProgress :value="25" :max="100" show-value></BProgress>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Multiple bars</BCardTitle>
            <p class="card-title-desc">Include multiple progress bars in a progress component if you need.</p>
            <BProgress class="mt-2" :max="100">
              <BProgressBar :value="45 * (6 / 10)"></BProgressBar>
              <BProgressBar :value="45 * (2.5 / 10)" variant="success"></BProgressBar>
              <BProgressBar :value="45 * (1.5 / 10)" variant="info"></BProgressBar>
            </BProgress>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol xl="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Height</BCardTitle>
            <p class="card-title-desc">
              We only set a
              <code class="highlighter-rouge">height</code> value on the
              <code class="highlighter-rouge">.progress-bar</code>, so if you change
              that value the outer
              <code class="highlighter-rouge">.progress</code>
              will automatically resize accordingly.
            </p>

            <div>
              <div class="mb-4">
                <h5 class="font-size-13">Progress sm</h5>
                <BProgress :value="25" class="progress-sm" />
              </div>

              <div class="mb-4">
                <h5 class="font-size-13">Progress md</h5>
                <BProgress :value="40" variant="success" class="progress-md" />
              </div>

              <div class="mb-4">
                <h5 class="font-size-13">Progress lg</h5>
                <BProgress :value="50" variant="warning" class="progress-lg" />
              </div>

              <div>
                <h5 class="font-size-13">Progress xl</h5>
                <BProgress :value="70" variant="danger" class="progress-xl" />
              </div>
            </div>

          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="6">
        <BRow>
          <BCol lg="12">
            <BCard no-body>
              <BCardBody>
                <BCardTitle>Striped</BCardTitle>
                <p class="card-title-desc">Add <code>.progress-bar-striped</code>
                  to any <code>.progress-bar</code> to apply a
                  stripe via CSS gradient over the progress bar’s background color.</p>
                <BProgress class="mb-3" :value="30" :max="100" striped></BProgress>
                <BProgress variant="success" :value="60" :max="100" striped></BProgress>
              </BCardBody>
            </BCard>
          </BCol>
        </BRow>

        <BRow>
          <BCol lg="12">
            <BCard no-body>
              <BCardBody>
                <BCardTitle>Animated stripes</BCardTitle>
                <p class="card-title-desc">
                  The striped gradient can also be
                  animated. Add
                  <code class="highlighter-rouge">.progress-bar-animated</code> to
                  <code class="highlighter-rouge">.progress-bar</code> to animate the
                  stripes right to left via CSS3 animations.
                </p>
                <BProgress :value="75" :max="100" animated></BProgress>
              </BCardBody>
            </BCard>
          </BCol>
        </BRow>
      </BCol>
    </BRow>

    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody class="pb-0">
            <BCardTitle>Striped</BCardTitle>
            <p class="card-title-desc">
              Add
              <code class="highlighter-rouge">.progress-bar-striped</code>
              to any
              <code class="highlighter-rouge">.progress-bar</code> to apply a
              stripe via CSS gradient over the progress bar’s background color.
            </p>
            <BProgress :value="10" :max="100" class="mb-4" animated></BProgress>
            <BProgress :value="25" :max="100" class="mb-4" variant="success" animated></BProgress>
            <BProgress :value="50" :max="100" class="mb-4" variant="info" animated></BProgress>
            <BProgress :value="75" :max="100" class="mb-4" variant="warning" animated></BProgress>
            <BProgress :value="100" :max="100" class="mb-4" variant="danger" animated></BProgress>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Custom progress</BCardTitle>
            <p class="card-title-desc mb-4">Example of Custom progress</p>

            <div class="pt-2">
              <div class="custom-progess mb-5">
                <BProgress :value="84" :max="100" class="mb-4 progress-sm" variant="danger"></BProgress>
                <div class="avatar-xs progress-icon">
                  <span class="avatar-title rounded-circle border border-danger">
                    <i class="bx bxl-html5 text-danger font-size-18"></i>
                  </span>
                </div>
              </div>

              <div class="custom-progess mb-5">
                <BProgress :value="75" :max="100" class="mb-4 progress-sm"></BProgress>
                <div class="avatar-xs progress-icon">
                  <span class="avatar-title rounded-circle border border-primary">
                    <i class="bx bxl-css3 text-primary font-size-18"></i>
                  </span>
                </div>
              </div>
              <div class="custom-progess mb-4">
                <BProgress :value="62" :max="100" class="mb-4 progress-sm" variant="info"></BProgress>
                <div class="avatar-xs progress-icon">
                  <span class="avatar-title rounded-circle border border-info">
                    <i class="bx bxl-jquery text-info font-size-18"></i>
                  </span>
                </div>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol xl="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Progress Example</BCardTitle>
            <p class="card-title-desc">You can use these classes with existing components to create new ones.</p>

            <div class="mt-5">
              <div class="position-relative m-4">
                <BProgress :max="100" style="height: 1px;">
                  <BProgress-bar variant="primary" style="width: 50%;"></BProgress-bar>
                </BProgress>
                <BButton variant="primary" type="button"
                  class="position-absolute top-0 start-0 translate-middle btn btn-sm rounded-pill"
                  style="width: 2rem; height:2rem;">1</BButton>
                <BButton variant="primary" type="button"
                  class="position-absolute top-0 start-50 translate-middle btn btn-sm rounded-pill"
                  style="width: 2rem; height:2rem;">2</BButton>
                <BButton variant="secondary" type="button"
                  class="position-absolute top-0 start-100 translate-middle btn btn-sm rounded-pill"
                  style="width: 2rem; height:2rem;">3</BButton>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

  </Layout>
</template>