<script>
import axios from "axios";
import DropZone from "../../components/widgets/dropZone.vue";
import { ref } from "vue";
import useVuelidate from "@vuelidate/core";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";

import Layout from "../../layouts/main.vue";
import PageHeader from "../../components/page-header.vue";

/**
 * Add-product component
 */
export default {
    components: {
        Multiselect,
        Layout,
        PageHeader,
        DropZone
    },
    data() {
        return {
            formData: new FormData(),
            product: {
                name: "",
                manufacture_name: "",
                manufacture_brand: "",
                price: null
            },
            image: "",
            file: "",
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
                "Iowa"
            ],
            submitted: false,
            formData: {
                name: null,
                manufacture_name: null,
                manufacture_brand: null,
                price: null,
                image: null
            },
            avatar: null,
            avatarName: null,
            showForm: true,
            user: null,
            errors: null
        };
    },
    setup() {
        let dropzoneFile = ref("");
        const drop = (e) => {
            dropzoneFile.value = e.dataTransfer.files[0];
        };
        const selectedFile = () => {
            dropzoneFile.value = document.querySelector(".dropzoneFile").files[0];
        };
        return { dropzoneFile, drop, selectedFile, v$: useVuelidate() };
    },
    methods: {
        onAccept(file) {
            this.image = file.name;
            this.file = file;
        },
        fileAdded(file) {
            this.avatar = file;
            this.avatarName = file.name;
        },
        submit() {
            this.submitted = true;
            // stop here if form is invalid
            this.$v.$touch();
            if (this.$v.$invalid) {
                return;
            } else {
                this.errors = null;
                let formData = new FormData();
                formData.append("image", this.avatar, this.avatarName);
                _.each(this.formData, (value, key) => {
                    formData.append(key, value);
                });
                axios
                    .post("/api/products", formData, {
                        headers: {
                            "Content-Type": "multipart/form-data"
                        }
                    })
                    .then(response => {
                        this.showForm = false;
                        this.user = response.data.data;
                    })
                    .catch(err => {
                        if (err.response.status === 422) {
                            this.errors = [];
                            _.each(err.response.data.errors, error => {
                                _.each(error, e => {
                                    this.errors.push(e);
                                });
                            });
                        }
                    });
            }
        },
        productAdd() {
            this.submitted = true;
            // stop here if form is invalid
            this.$v.$touch();

            if (this.$v.$invalid) {
                return;
            } else {
                let formData = new FormData();
                formData.append("name", this.product.name);
                formData.append(
                    "manufacture_name",
                    this.product.manufacture_name
                );
                formData.append(
                    "manufacture_brand",
                    this.product.manufacture_brand
                );
                formData.append("price", this.product.price);
                formData.append("image", this.file, this.image);

                //** Add product in api using post method *//
                axios
                    .post(`http://localhost:8000/api/products`, formData)
                    .then(res => {
                        return res;
                    })
                    .catch(err => {
                        return err;
                    });
            }
        }
    }
};
</script>

<template>
    <Layout>
        <PageHeader title="Add Product" pageTitle="Ecommerce" />
        <BRow>
            <BCol cols="12">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Basic Information</BCardTitle>
                        <p class="card-title-desc">
                            Fill all information below
                        </p>

                        <!-- THIS SECTION IS FOR ERRORS THAT WOULD COME FROM API -->
                        <div v-if="errors">
                            <div v-for="(error, index) in errors" :key="index" class="alert alert-danger">
                                {{ error }}
                            </div>
                        </div>

                        <BForm @submit.prevent="productAdd">
                            <BRow>
                                <BCol sm="6">
                                    <div class="mb-3">
                                        <label for="productname">Product Name</label>
                                        <input id="productname" v-model="product.name" name="name" type="text" class="form-control" placeholder="Product Name" :class="{
                                            'is-invalid':
                                                submitted &&
                                                $v.product.name.$error
                                        }" />
                                        <div v-if="submitted &&
                                            !$v.product.name.required
                                            " class="invalid-feedback">
                                            Product name is required.
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="manufacturername">Manufacturer Name</label>
                                        <input id="manufacturername" v-model="product.manufacture_name" name="manufacture_name" type="text" placeholder="Manufacturer Name" class="form-control" :class="{
                                            'is-invalid':
                                                submitted &&
                                                $v.product.manufacture_name
                                                    .$error
                                        }" />
                                        <div v-if="submitted &&
                                            !$v.product.manufacture_name
                                                .required
                                            " class="invalid-feedback">
                                            Product manufacture_name is
                                            required.
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="manufacturerbrand">Manufacturer Brand</label>
                                        <input id="manufacturerbrand" v-model="product.manufacture_brand" name="manufacture_brand" type="text" class="form-control" placeholder="Manufacturer Brand" :class="{
                                            'is-invalid':
                                                submitted &&
                                                $v.product.manufacture_brand
                                                    .$error
                                        }" />
                                        <div v-if="submitted &&
                                            !$v.product
                                                .manufacture_brand
                                                .required
                                            " class="invalid-feedback">
                                            Product manufacture_brand is
                                            required.
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="price">Price</label>
                                        <input id="price" name="price" v-model="product.price" placeholder="Price" :class="{
                                            'is-invalid':
                                                submitted &&
                                                $v.product.price.$error
                                        }" type="text" class="form-control" />
                                        <div v-if="submitted &&
                                            !$v.product.price.required
                                            " class="invalid-feedback">
                                            Product price is required.
                                        </div>
                                    </div>
                                </BCol>

                                <BCol sm="6">
                                    <div class="mb-3">
                                        <label class="control-label">Category</label>
                                        <multiselect v-model="value" :options="options" class="checkout-multiselect-form" placeholder="Select"></multiselect>
                                    </div>
                                    <div class="mb-3">
                                        <label class="control-label">Features</label>
                                        <multiselect v-model="value1" :options="options" :multiple="true" class="checkout-multiselect-form" placeholder="Choose..."></multiselect>
                                    </div>
                                    <div class="mb-3">
                                        <label for="productdesc">Product Description</label>
                                        <textarea id="productdesc" class="form-control" placeholder="Product Description" rows="5"></textarea>
                                    </div>
                                </BCol>
                            </BRow>
                            <div class="d-flex flex-wrap gap-2">
                                <BButton variant="primary" class="me-1">
                                    Save Changes
                                </BButton>
                                <BButton variant="secondary">Cancel</BButton>
                            </div>
                        </BForm>
                    </BCardBody>
                </BCard>

                <BCard no-body>
                    <BCardBody>
                        <BCardTitle class="mb-3">Product Images</BCardTitle>
                        <DropZone @drop.prevent="drop" @change="selectedFile" />
                        <span class="file-info">{{ dropzoneFile.name }}</span>
                    </BCardBody>
                </BCard>


                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Meta Data</BCardTitle>
                        <p class="card-title-desc">
                            Fill all information below
                        </p>

                        <BForm>
                            <BRow>
                                <BCol sm="6">
                                    <div class="mb-3">
                                        <label for="metatitle">Meta title</label>
                                        <input id="metatitle" name="productname" type="text" class="form-control" placeholder="Meta Title" />
                                    </div>
                                    <div class="mb-3">
                                        <label for="metakeywords">Meta Keywords</label>
                                        <input id="metakeywords" name="manufacturername" type="text" class="form-control" placeholder="Meta Keywords" />
                                    </div>
                                </BCol>

                                <BCol sm="6">
                                    <div class="mb-3">
                                        <label for="metadescription">Meta Description</label>
                                        <BFormTextarea id="metadescription" class="form-control" rows="5" placeholder="Meta Description"></BFormTextarea>
                                    </div>
                                </BCol>
                            </BRow>
                            <div class="d-flex flex-wrap gap-2">
                                <BButton variant="primary" class="me-1">
                                    Save Changes
                                </BButton>
                                <BButton variant="secondary">Cancel</BButton>
                            </div>
                        </BForm>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>
    </Layout>
</template>
