<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "../../components/page-header.vue";

/**
 * Tabs & accordions component
 */
export default {
  components: { Layout, PageHeader },
  data() {
    return {
      text: `
         Raw denim you probably haven't heard of them jean shorts Austin. Nesciunt tofu stumptown aliqua, retro synth master cleanse. Mustache cliche tempor, williamsburg carles vegan helvetica. Reprehenderit butcher retro keffiyeh dreamcatcher synth. Cosby sweater eu banh mi, qui irure terry richardson ex squid. Aliquip placeat salvia cillum iphone. Seitan aliquip quis cardigan american apparel, butcher voluptate nisi qui.
        `,
      content: `Food truck fixie locavore, accusamus mcsweeney's marfa nulla single-origin coffee squid. Exercitation +1 labore velit, blog sartorial PBR leggings next level wes anderson artisan four loko farm-to-table craft beer twee. Qui photo booth letterpress, commodo enim craft beer mlkshk aliquip jean shorts ullamco ad vinyl cillum PBR. Homo nostrud organic, assumenda labore aesthetic magna delectus.`
    };
  }
};
</script>

<template>
  <Layout>
    <PageHeader title="Tabs & Accordions" pageTitle="UI Elements" />

    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Default Tabs</BCardTitle>
            <p class="card-title-desc">
              Use the tab JavaScript plugin—include
              it individually or through the compiled
              <code class="highlighter-rouge">bootstrap.js</code>
              file—to extend our navigational tabs and pills to create tabbable panes
              of local content, even via dropdown menus.
            </p>
            <BTabs class="default-tabs" content-class="p-3 text-muted">
              <BTab active class="border-0">
                <template v-slot:title>
                  <span class="d-inline-block d-sm-none">
                    <i class="fas fa-home"></i>
                  </span>
                  <span class="d-none d-sm-inline-block">Home</span>
                </template>
                {{ text }}
              </BTab>
              <BTab>
                <template v-slot:title>
                  <span class="d-inline-block d-sm-none">
                    <i class="far fa-user"></i>
                  </span>
                  <span class="d-none d-sm-inline-block">Profile</span>
                </template>
                {{ content }}
              </BTab>
              <BTab>
                <template v-slot:title>
                  <span class="d-inline-block d-sm-none">
                    <i class="far fa-envelope"></i>
                  </span>
                  <span class="d-none d-sm-inline-block">Messages</span>
                </template>
                {{ text }}
              </BTab>
              <BTab>
                <template v-slot:title>
                  <span class="d-inline-block d-sm-none">
                    <i class="fas fa-cog"></i>
                  </span>
                  <span class="d-none d-sm-inline-block">Settings</span>
                </template>
                {{ content }}
              </BTab>
            </BTabs>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Justify Tabs</BCardTitle>
            <p class="card-title-desc">
              Use the tab JavaScript plugin—include
              it individually or through the compiled
              <code class="highlighter-rouge">bootstrap.js</code>
              file—to extend our navigational tabs and pills to create tabbable panes
              of local content, even via dropdown menus.
            </p>
            <BTabs pills justified content-class="p-3 text-muted">
              <BTab active class="border-0">
                <template v-slot:title>
                  <span class="d-inline-block d-sm-none">
                    <i class="fas fa-home"></i>
                  </span>
                  <span class="d-none d-sm-inline-block">Home</span>
                </template>
                {{ text }}
              </BTab>
              <BTab>
                <template v-slot:title>
                  <span class="d-inline-block d-sm-none">
                    <i class="far fa-user"></i>
                  </span>
                  <span class="d-none d-sm-inline-block">Profile</span>
                </template>
                {{ content }}
              </BTab>
              <BTab>
                <template v-slot:title>
                  <span class="d-inline-block d-sm-none">
                    <i class="far fa-envelope"></i>
                  </span>
                  <span class="d-none d-sm-inline-block">Messages</span>
                </template>
                {{ text }}
              </BTab>
              <BTab>
                <template v-slot:title>
                  <span class="d-inline-block d-sm-none">
                    <i class="fas fa-cog"></i>
                  </span>
                  <span class="d-none d-sm-inline-block">Settings</span>
                </template>
                {{ content }}
              </BTab>
            </BTabs>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
    <BRow>
      <BCol xl="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Vertical Nav Tabs</BCardTitle>
            <p class="card-title-desc">Example of Vertical nav tabs</p>
            <BTabs pills vertical class="vertical-pills" nav-class="p-0" nav-wrapper-class="col-md-3"
              content-class="pt-0 px-2 text-muted">
              <BTab title="Home" active title-item-class="mb-2">
                <BCardText>{{ text }}</BCardText>
              </BTab>
              <BTab title="Profile" title-item-class="mb-2">
                <BCardText>{{ content }}</BCardText>
              </BTab>
              <BTab title="Messages" title-item-class="mb-2">
                <BCardText>{{ text }}</BCardText>
              </BTab>
              <BTab title="Settings">
                <BCardText>{{ content }}</BCardText>
              </BTab>
            </BTabs>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle>Custom Tabs</BCardTitle>
            <p class="card-title-desc">Example of custom tabs</p>
            <BTabs justified nav-class="nav-tabs-custom" content-class="p-3 text-muted">
              <BTab active>
                <template v-slot:title>
                  <span class="d-inline-block d-sm-none">
                    <i class="fas fa-home"></i>
                  </span>
                  <span class="d-none d-sm-inline-block">Home</span>
                </template>
                {{ text }}
              </BTab>
              <BTab>
                <template v-slot:title>
                  <span class="d-inline-block d-sm-none">
                    <i class="far fa-user"></i>
                  </span>
                  <span class="d-none d-sm-inline-block">Profile</span>
                </template>
                {{ content }}
              </BTab>
              <BTab>
                <template v-slot:title>
                  <span class="d-inline-block d-sm-none">
                    <i class="far fa-envelope"></i>
                  </span>
                  <span class="d-none d-sm-inline-block">Messages</span>
                </template>
                {{ text }}
              </BTab>
              <BTab>
                <template v-slot:title>
                  <span class="d-inline-block d-sm-none">
                    <i class="fas fa-cog"></i>
                  </span>
                  <span class="d-none d-sm-inline-block">Settings</span>
                </template>
                {{ content }}
              </BTab>
            </BTabs>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-0">Accordion</BCardTitle>

            <BRow>
              <!-- Left Column: Default Accordion -->
              <BCol xl="6">
                <div class="mt-4">
                  <h5 class="font-size-14">Example</h5>
                  <p class="card-title-desc">
                    Click the accordions below to expand/collapse the accordion content.
                  </p>

                  <div class="accordion" id="accordionExample">
                    <div class="accordion-item">
                      <h2 class="accordion-header" id="headingOne">
                        <button class="accordion-button fw-medium" type="button" data-bs-toggle="collapse"
                          data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                          Accordion Item #1
                        </button>
                      </h2>
                      <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne"
                        data-bs-parent="#accordionExample">
                        <div class="accordion-body">
                          <div class="text-muted">
                            <strong class="text-body-emphasis">This is the first item's accordion body.</strong> It is
                            hidden by default, until the collapse plugin adds the appropriate classes that we use to
                            style
                            each element. These classes control the overall appearance, as well as the showing and
                            hiding
                            via CSS transitions. You can modify any of this with custom CSS or overriding our default
                            variables. It's also worth noting that just about any HTML can go within the
                            <code>.accordion-body</code>, though the transition does limit overflow.
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="accordion-item">
                      <h2 class="accordion-header" id="headingTwo">
                        <button class="accordion-button fw-medium collapsed" type="button" data-bs-toggle="collapse"
                          data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                          Accordion Item #2
                        </button>
                      </h2>
                      <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo"
                        data-bs-parent="#accordionExample">
                        <div class="accordion-body">
                          <div class="text-muted">
                            <strong class="text-body-emphasis">This is the first item's accordion body.</strong> It is
                            hidden by default, until the collapse plugin adds the appropriate classes that we use to
                            style
                            each element. These classes control the overall appearance, as well as the showing and
                            hiding
                            via CSS transitions. You can modify any of this with custom CSS or overriding our default
                            variables. It's also worth noting that just about any HTML can go within the
                            <code>.accordion-body</code>, though the transition does limit overflow.
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="accordion-item">
                      <h2 class="accordion-header" id="headingThree">
                        <button class="accordion-button fw-medium collapsed" type="button" data-bs-toggle="collapse"
                          data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                          Accordion Item #3
                        </button>
                      </h2>
                      <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree"
                        data-bs-parent="#accordionExample">
                        <div class="accordion-body">
                          <div class="text-muted">
                            <strong class="text-body-emphasis">This is the first item's accordion body.</strong> It is
                            hidden by default, until the collapse plugin adds the appropriate classes that we use to
                            style
                            each element. These classes control the overall appearance, as well as the showing and
                            hiding
                            via CSS transitions. You can modify any of this with custom CSS or overriding our default
                            variables. It's also worth noting that just about any HTML can go within the
                            <code>.accordion-body</code>, though the transition does limit overflow.
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </BCol>

              <!-- Right Column: Flush Accordion -->
              <BCol xl="6">
                <div class="mt-4">
                  <h5 class="font-size-14">Flush</h5>
                  <p class="card-title-desc">
                    Add <code>.accordion-flush</code> to remove the default <code>background-color</code>, some borders,
                    and
                    some rounded corners to render accordions edge-to-edge with their parent container.</p>

                  <div class="accordion accordion-flush" id="accordionFlushExample">
                    <div class="accordion-item">
                      <h2 class="accordion-header" id="flush-headingOne">
                        <button class="accordion-button fw-medium" type="button" data-bs-toggle="collapse"
                          data-bs-target="#flush-collapseOne" aria-expanded="true" aria-controls="flush-collapseOne">
                          Accordion Item #1
                        </button>
                      </h2>
                      <div id="flush-collapseOne" class="accordion-collapse collapse show"
                        aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body text-muted">
                          Anim pariatur cliche reprehenderit, enim eiusmod high life accusamus terry richardson ad
                          squid. 3 wolf moon officia aute, non cupidatat skateboard dolor brunch. Food truck quinoa
                          nesciunt
                          laborum eiusmod. Brunch 3 wolf moon tempor, sunt aliqua put a bird on it squid single-origin
                          coffee nulla assumenda shoreditch et. Nihil anim keffiyeh helvetica, craft beer labore wes
                          anderson cred nesciunt sapiente ea proident. Ad vegan excepteur butcher vice.
                        </div>
                      </div>
                    </div>

                    <div class="accordion-item">
                      <h2 class="accordion-header" id="flush-headingTwo">
                        <button class="accordion-button fw-medium collapsed" type="button" data-bs-toggle="collapse"
                          data-bs-target="#flush-collapseTwo" aria-expanded="false" aria-controls="flush-collapseTwo">
                          Accordion Item #2
                        </button>
                      </h2>
                      <div id="flush-collapseTwo" class="accordion-collapse collapse" aria-labelledby="flush-headingTwo"
                        data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body text-muted">
                          Anim pariatur cliche reprehenderit, enim eiusmod high life accusamus terry richardson ad
                          squid. 3 wolf moon officia aute, non cupidatat skateboard dolor brunch. Food truck quinoa
                          nesciunt
                          laborum eiusmod. Brunch 3 wolf moon tempor, sunt aliqua put a bird on it squid single-origin
                          coffee nulla assumenda shoreditch et. Nihil anim keffiyeh helvetica, craft beer labore wes
                          anderson cred nesciunt sapiente ea proident. Ad vegan excepteur butcher vice.
                        </div>
                      </div>
                    </div>

                    <div class="accordion-item">
                      <h2 class="accordion-header" id="flush-headingThree">
                        <button class="accordion-button fw-medium collapsed" type="button" data-bs-toggle="collapse"
                          data-bs-target="#flush-collapseThree" aria-expanded="false"
                          aria-controls="flush-collapseThree">
                          Accordion Item #3
                        </button>
                      </h2>
                      <div id="flush-collapseThree" class="accordion-collapse collapse"
                        aria-labelledby="flush-headingThree" data-bs-parent="#accordionFlushExample">
                        <div class="accordion-body text-muted">
                          Anim pariatur cliche reprehenderit, enim eiusmod high life accusamus terry richardson ad
                          squid. 3 wolf moon officia aute, non cupidatat skateboard dolor brunch. Food truck quinoa
                          nesciunt
                          laborum eiusmod. Brunch 3 wolf moon tempor, sunt aliqua put a bird on it squid single-origin
                          coffee nulla assumenda shoreditch et. Nihil anim keffiyeh helvetica, craft beer labore wes
                          anderson cred nesciunt sapiente ea proident. Ad vegan excepteur butcher vice.
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </BCol>
            </BRow>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol cols="12">
        <BCard no-body>
          <BCardBody>
            <h4 class="mb-0">Collapse</h4>
            <BRow>
              <BCol xl="6" class="mt-4">
                <h5 class="font-size-14">Example</h5>
                <p class="card-title-desc">
                  You can use a link with the <code>href</code> attribute, or a button with the
                  <code>data-bs-target</code> attribute.
                </p>

                <div class="d-flex gap-2 flex-wrap mb-3">
                  <a class="btn btn-primary" data-bs-toggle="collapse" href="#collapseExample" aria-expanded="false"
                    aria-controls="collapseExample">
                    Link with href
                  </a>
                  <button class="btn btn-primary" type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapseExample" aria-expanded="false" aria-controls="collapseExample">
                    Button with data-bs-target
                  </button>
                </div>

                <div class="collapse show" id="collapseExample">
                  <BCard no-body class="border shadow-none text-muted mb-0">
                    <BCardBody>
                      Anim pariatur cliche reprehenderit, enim eiusmod high life accusamus terry richardson ad squid.
                    </BCardBody>
                  </BCard>
                </div>
              </BCol>

              <BCol xl="6" class="mt-4">
                <h5 class="font-size-14">Multiple targets</h5>
                <p class="card-title-desc">
                  You can show/hide multiple elements using a selector in <code>href</code> or
                  <code>data-bs-target</code>.
                </p>

                <div class="d-flex gap-2 flex-wrap mb-3">
                  <a class="btn btn-primary" data-bs-toggle="collapse" href="#multiCollapseExample1"
                    aria-expanded="false" aria-controls="multiCollapseExample1">
                    Toggle first element
                  </a>
                  <button class="btn btn-primary" type="button" data-bs-toggle="collapse"
                    data-bs-target="#multiCollapseExample2" aria-expanded="false" aria-controls="multiCollapseExample2">
                    Toggle second element
                  </button>
                  <button class="btn btn-primary" type="button" data-bs-toggle="collapse"
                    data-bs-target=".multi-collapse" aria-expanded="false"
                    aria-controls="multiCollapseExample1 multiCollapseExample2">
                    Toggle both elements
                  </button>
                </div>

                <BRow>
                  <BCol>
                    <div class="collapse multi-collapse show" id="multiCollapseExample1">
                      <BCard no-body class="border shadow-none text-muted mb-0">
                        <BCardBody>
                          Nihil anim keffiyeh helvetica, craft beer labore wes anderson cred nesciunt sapiente ea
                          proident.
                        </BCardBody>
                      </BCard>
                    </div>
                  </BCol>
                  <BCol>
                    <div class="collapse multi-collapse show" id="multiCollapseExample2">
                      <BCard class="border shadow-none text-muted mb-0">
                        <BCardBody>
                          Nihil anim keffiyeh helvetica, craft beer labore wes anderson cred nesciunt sapiente ea
                          proident.
                        </BCardBody>
                      </BCard>
                    </div>
                  </BCol>
                </BRow>
              </BCol>
            </BRow>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <!-- Horizontal Collapse -->
    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <h4>Horizontal Collapse</h4>
            <p class="card-title-desc">
              Use <code>.collapse-horizontal</code> to collapse width instead of height.
            </p>

            <p>
              <button class="btn btn-primary" type="button" data-bs-toggle="collapse"
                data-bs-target="#collapseWidthExample" aria-expanded="false" aria-controls="collapseWidthExample">
                Toggle width collapse
              </button>
            </p>

            <div style="min-height: 110px;">
              <div class="collapse collapse-horizontal" id="collapseWidthExample">
                <BCard no-body class="border shadow-none text-muted mb-0" style="width: 300px;">
                  <BCardBody>
                    This is some placeholder content for a horizontal collapse. It’s hidden by default and shown when
                    triggered.
                  </BCardBody>
                </BCard>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

  </Layout>
</template>
