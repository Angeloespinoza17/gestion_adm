<script>
import Layout from "../../../layouts/main.vue";
import PageHeader from "../../../components/page-header.vue";

import Basic from "./basic.vue";
import Boundary from "./boundary.vue";
import Icons from "./icons.vue";
import Geometry from "./geometry.vue";
import Markers from "./markers.vue";

/**
 * Leaflet-map component
 */
export default {
  components: {
    Layout,
    PageHeader,
    Boundary,
    Icons,
    Basic,
    Geometry,
    Markers
  },
};
</script>

<template>
  <Layout>
    <PageHeader title="Leaflet map" pageTitle="Maps" />
    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Example</BCardTitle>
            <Basic />
          </BCardBody>
        </BCard>
      </BCol>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Fixed Bounds</BCardTitle>
            <Boundary />
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Custom Icon Example</BCardTitle>
            <Icons />
          </BCardBody>
        </BCard>
      </BCol>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Geometry Example</BCardTitle>
            <Geometry />
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
    <BRow>
      <BCol lg="6">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Draggable marker</BCardTitle>
            <Markers />
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
