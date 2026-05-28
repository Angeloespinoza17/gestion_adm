<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";
import { productData as initialProducts, columns } from "./data";
import { reactive, computed } from "vue";

export default {
  components: { Layout, PageHeader },
  setup() {
    const products = reactive(initialProducts.map(p => ({ ...p })));

    const subtotal = computed(() =>
      products.reduce((sum, item) => sum + item.price * item.quantity, 0)
    );

    const discount = 157;
    const shipping = 25;
    const taxRate = 0.125;
    const tax = computed(() => subtotal.value * taxRate);

    const total = computed(() =>
      subtotal.value - discount + shipping + tax.value
    );

    const dynamicOrderSummary = computed(() => [
      { id: "cart-subtotal", label: "Grand Total :", value: `$ ${subtotal.value.toFixed(2)}` },
      { id: "cart-discount", label: "Discount :", value: `- $ ${discount.toFixed(2)}` },
      { id: "cart-shipping", label: "Shipping Charge :", value: `$ ${shipping.toFixed(2)}` },
      { id: "cart-tax", label: "Estimated Tax (12.5%) :", value: `$ ${tax.value.toFixed(2)}` },
      { id: "cart-total", label: "Total :", value: `$ ${total.value.toFixed(2)}` }
    ]);

    const updateQuantity = (index, event) => {
      const qty = parseInt(event.target.value, 10);
      if (!isNaN(qty) && qty > 0) products[index].quantity = qty;
    };

    const removeProduct = (index) => {
      products.splice(index, 1);
    };

    return {
      products,
      columns,
      dynamicOrderSummary,
      updateQuantity,
      removeProduct
    };
  }
};
</script>

<template>
  <Layout>
    <PageHeader title="Cart" pageTitle="Ecommerce" />

    <BRow>
      <BCol xl="8">
        <BCard no-body>
          <BCardBody>
            <div class="table-responsive">
              <BTableSimple class="align-middle mb-0 table-nowrap">
                <BThead class="table-light">
                  <BTr>
                    <BTh>Product</BTh>
                    <BTh>Product Desc</BTh>
                    <BTh>Price</BTh>
                    <BTh>Quantity</BTh>
                    <BTh colspan="2">Total</BTh>
                  </BTr>
                </BThead>
                <BTbody>
                  <BTr v-for="(product, index) in products" :key="index">
                    <BTd>
                      <img :src="product.image" alt="product-img" class="avatar-md" />
                    </BTd>
                    <BTd>
                      <h5 class="font-size-14 text-truncate">
                        <router-link to="/ecommerce/product-detail/1" class="text-dark">
                          {{ product.name }}
                        </router-link>
                      </h5>
                      <p class="mb-0">Color: <span class="fw-medium">{{ product.color }}</span></p>
                    </BTd>
                    <BTd>$ {{ product.price.toFixed(2) }}</BTd>
                    <BTd>
                      <input type="number" min="1" :value="product.quantity" class="form-control" style="width: 100px"
                        @input="updateQuantity(index, $event)" />
                    </BTd>
                    <BTd>$ {{ (product.price * product.quantity).toFixed(2) }}</BTd>
                    <BTd>
                      <BLink href="javascript:void(0);" @click.prevent="removeProduct(index)">
                        <i class="mdi mdi-trash-can font-size-18 text-danger"></i>
                      </BLink>
                    </BTd>
                  </BTr>
                </BTbody>
              </BTableSimple>
            </div>

            <BRow class="mt-4">
              <BCol sm="6">
                <router-link to="/ecommerce/product-detail/1" class="btn btn-secondary">
                  <i class="mdi mdi-arrow-left me-1"></i> Continue Shopping
                </router-link>
              </BCol>
              <BCol sm="6">
                <div class="text-sm-end mt-2 mt-sm-0">
                  <router-link to="/ecommerce/checkout" class="btn btn-success">
                    <i class="mdi mdi-cart-arrow-right me-1"></i> Checkout
                  </router-link>
                </div>
              </BCol>
            </BRow>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol xl="4">
        <BCard no-body>
          <BCardBody>
            <BCardTitle class="mb-4">Card Details</BCardTitle>
            <BCard class="bg-primary text-white visa-card mb-0" no-body>
              <BCardBody>
                <div>
                  <i class="bx bxl-visa visa-pattern"></i>
                  <div class="float-end">
                    <i class="bx bxl-visa visa-logo display-3"></i>
                  </div>
                  <div><i class="bx bx-chip h1 text-warning"></i></div>
                </div>
                <BRow class="mt-5">
                  <BCol cols="4" v-for="column in columns" :key="column.id">
                    <p>
                      <i v-for="star in column.stars" :key="star" class="fas fa-star-of-life m-1"></i>
                    </p>
                  </BCol>
                </BRow>
                <div class="mt-5">
                  <h5 class="text-white float-end mb-0">12/22</h5>
                  <h5 class="text-white mb-0">Fredrick Taylor</h5>
                </div>
              </BCardBody>
            </BCard>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardBody class="pb-0">
            <BCardTitle class="mb-3">Order Summary</BCardTitle>
            <div class="table-responsive">
              <BTableSimple class="mb-0">
                <BTbody>
                  <BTr v-for="item in dynamicOrderSummary" :key="item.id">
                    <BTd>{{ item.label }}</BTd>
                    <BTd :id="item.id">{{ item.value }}</BTd>
                  </BTr>
                </BTbody>
              </BTableSimple>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
