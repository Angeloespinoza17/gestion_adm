<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "../../components/page-header.vue";

/**
 * Dropdowns component
 */
export default {
    components: { Layout, PageHeader },
    methods: {
        onClick() {
            // Close the menu and (by passing true) return focus to the toggle button
            this.$refs.dropdown.hide(true)
        }
    }
};
</script>

<template>
    <Layout>
        <PageHeader title="Dropdowns" pageTitle="UI Elements" />

        <BRow>
            <BCol lg="6">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Single button dropdowns</BCardTitle>
                        <p class="card-title-desc">
                            Any single
                            <code class="highlighter-rouge">.btn</code> can be turned into a
                            dropdown toggle with some markup changes. Here’s how you can put
                            them to work with either
                            <code class="highlighter-rouge">&lt;button&gt;</code>
                            elements:
                        </p>

                        <BRow>
                            <BCol sm="6">
                                <BDropdown>
                                    <template v-slot:button-content>
                                        Dropdown button
                                        <i class="mdi mdi-chevron-down"></i>
                                    </template>
                                    <BDropdownItem>Action</BDropdownItem>
                                    <BDropdownItem>Another Action</BDropdownItem>
                                    <BDropdownItem>Something else here</BDropdownItem>
                                </BDropdown>
                            </BCol>
                        </BRow>
                    </BCardBody>
                </BCard>
            </BCol>

            <BCol lg="6">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Variant</BCardTitle>
                        <p class="card-title-desc mb-3">
                            The best part is you can do this with any button variant, too:
                        </p>

                        <div>
                            <BButtonGroup class="me-2 mt-2">
                                <BDropdown variant="primary">
                                    <template v-slot:button-content>
                                        Primary

                                        <i class="mdi mdi-chevron-down"></i>
                                    </template>
                                    <BDropdownItem href="javascript: void(0);">Action</BDropdownItem>
                                    <BDropdownItem href="javascript: void(0);">Another action</BDropdownItem>
                                    <BDropdownItem href="javascript: void(0);">Something else here</BDropdownItem>
                                    <BDropdownDivider></BDropdownDivider>
                                    <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                                </BDropdown>
                            </BButtonGroup>

                            <BButtonGroup class="me-2 mt-2">
                                <BDropdown variant="secondary">
                                    <template v-slot:button-content>
                                        Secondary

                                        <i class="mdi mdi-chevron-down"></i>
                                    </template>
                                    <BDropdownItem href="javascript: void(0);">Action</BDropdownItem>
                                    <BDropdownItem href="javascript: void(0);">Another action</BDropdownItem>
                                    <BDropdownItem href="javascript: void(0);">Something else here</BDropdownItem>
                                    <BDropdownDivider></BDropdownDivider>
                                    <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                                </BDropdown>
                            </BButtonGroup>

                            <BButtonGroup class="me-2 mt-2">
                                <BDropdown variant="success">
                                    <template v-slot:button-content>
                                        Success

                                        <i class="mdi mdi-chevron-down"></i>
                                    </template>
                                    <BDropdownItem href="javascript: void(0);">Action</BDropdownItem>
                                    <BDropdownItem href="javascript: void(0);">Another action</BDropdownItem>
                                    <BDropdownItem href="javascript: void(0);">Something else here</BDropdownItem>
                                    <BDropdownDivider></BDropdownDivider>
                                    <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                                </BDropdown>
                            </BButtonGroup>

                            <BButtonGroup class="me-2 mt-2">
                                <BDropdown variant="info">
                                    <template v-slot:button-content>
                                        Info

                                        <i class="mdi mdi-chevron-down"></i>
                                    </template>
                                    <BDropdownItem href="javascript: void(0);">Action</BDropdownItem>
                                    <BDropdownItem href="javascript: void(0);">Another action</BDropdownItem>
                                    <BDropdownItem href="javascript: void(0);">Something else here</BDropdownItem>
                                    <BDropdownDivider></BDropdownDivider>
                                    <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                                </BDropdown>
                            </BButtonGroup>

                            <BButtonGroup class="me-2 mt-2">
                                <BDropdown variant="warning">
                                    <template v-slot:button-content>
                                        Warning

                                        <i class="mdi mdi-chevron-down"></i>
                                    </template>
                                    <BDropdownItem href="javascript: void(0);">Action</BDropdownItem>
                                    <BDropdownItem href="javascript: void(0);">Another action</BDropdownItem>
                                    <BDropdownItem href="javascript: void(0);">Something else here</BDropdownItem>
                                    <BDropdownDivider></BDropdownDivider>
                                    <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                                </BDropdown>
                            </BButtonGroup>

                            <BButtonGroup class="me-2 mt-2">
                                <BDropdown variant="danger">
                                    <template v-slot:button-content>
                                        Danger

                                        <i class="mdi mdi-chevron-down"></i>
                                    </template>
                                    <BDropdownItem href="javascript: void(0);">Action</BDropdownItem>
                                    <BDropdownItem href="javascript: void(0);">Another action</BDropdownItem>
                                    <BDropdownItem href="javascript: void(0);">Something else here</BDropdownItem>
                                    <BDropdownDivider></BDropdownDivider>
                                    <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                                </BDropdown>
                            </BButtonGroup>
                        </div>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>

        <BRow>
            <BCol lg="6">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Split button dropdowns</BCardTitle>
                        <p class="card-title-desc mb-3">
                            The best part is you can do this with any button variant, too:
                        </p>
                        <BDropdown split text="Primary" class="m-2" variant="primary">
                            <BDropdownItem href="#">Action</BDropdownItem>
                            <BDropdownItem href="#">Another action</BDropdownItem>
                            <BDropdownItem href="#">Something else here...</BDropdownItem>
                            <BDropdownDivider></BDropdownDivider>
                            <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                        </BDropdown>

                        <BDropdown split text="Secondary" class="m-2" variant="secondary">
                            <BDropdownItem href="#">Action</BDropdownItem>
                            <BDropdownItem href="#">Another action</BDropdownItem>
                            <BDropdownItem href="#">Something else here...</BDropdownItem>
                            <BDropdownDivider></BDropdownDivider>
                            <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                        </BDropdown>

                        <BDropdown split text="Success" class="m-2" variant="success">
                            <BDropdownItem href="#">Action</BDropdownItem>
                            <BDropdownItem href="#">Another action</BDropdownItem>
                            <BDropdownItem href="#">Something else here...</BDropdownItem>
                            <BDropdownDivider></BDropdownDivider>
                            <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                        </BDropdown>

                        <BDropdown split text="Info" class="m-2" variant="info">
                            <BDropdownItem href="#">Action</BDropdownItem>
                            <BDropdownItem href="#">Another action</BDropdownItem>
                            <BDropdownItem href="#">Something else here...</BDropdownItem>
                            <BDropdownDivider></BDropdownDivider>
                            <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                        </BDropdown>

                        <BDropdown split text="Warning" class="m-2" variant="warning">
                            <BDropdownItem href="#">Action</BDropdownItem>
                            <BDropdownItem href="#">Another action</BDropdownItem>
                            <BDropdownItem href="#">Something else here...</BDropdownItem>
                            <BDropdownDivider></BDropdownDivider>
                            <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                        </BDropdown>

                        <BDropdown split text="Danger" class="m-2" variant="danger">
                            <BDropdownItem href="#">Action</BDropdownItem>
                            <BDropdownItem href="#">Another action</BDropdownItem>
                            <BDropdownItem href="#">Something else here...</BDropdownItem>
                            <BDropdownDivider></BDropdownDivider>
                            <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                        </BDropdown>
                    </BCardBody>
                </BCard>
            </BCol>
            <BCol lg="6">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Sizing</BCardTitle>
                        <p class="card-title-desc mb-3">
                            Button dropdowns work with buttons of all sizes, including default
                            and split dropdown buttons.
                        </p>

                        <div>
                            <div class="me-1 mt-2">
                                <BDropdown size="lg" class="m-2" variant="primary">
                                    <template v-slot:button-content>
                                        Large button
                                        <i class="mdi mdi-chevron-down"></i>
                                    </template>
                                    <BDropdownItemButton>Action</BDropdownItemButton>
                                    <BDropdownItemButton>Another action</BDropdownItemButton>
                                    <BDropdownItemButton>Something else here</BDropdownItemButton>
                                    <BDropdownDivider></BDropdownDivider>
                                    <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                                </BDropdown>

                                <BDropdown size="lg" class="m-2" split text="Large button">
                                    <BDropdownItemButton>Action</BDropdownItemButton>
                                    <BDropdownItemButton>Another action</BDropdownItemButton>
                                    <BDropdownItemButton>Something else here</BDropdownItemButton>
                                    <BDropdownDivider></BDropdownDivider>
                                    <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                                </BDropdown>

                                <BDropdown size="sm" class="m-2" variant="info">
                                    <template v-slot:button-content>
                                        Small button
                                        <i class="mdi mdi-chevron-down"></i>
                                    </template>
                                    <BDropdownItemButton>Action</BDropdownItemButton>
                                    <BDropdownItemButton>Another action</BDropdownItemButton>
                                    <BDropdownItemButton>Something else here</BDropdownItemButton>
                                    <BDropdownDivider></BDropdownDivider>
                                    <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                                </BDropdown>

                                <BDropdown split text="Small Split button" size="sm" class="m-2">
                                    <BDropdownItemButton>Action</BDropdownItemButton>
                                    <BDropdownItemButton>Another action</BDropdownItemButton>
                                    <BDropdownItemButton>Something else here</BDropdownItemButton>
                                    <BDropdownDivider></BDropdownDivider>
                                    <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                                </BDropdown>
                            </div>
                        </div>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>

        <div class="row">
            <div class="col-xl-6">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Menu Content</BCardTitle>
                        <p class="card-title-desc">Example of dropdown menu Headers, Text, Forms content</p>

                        <div class="d-flex flex-wrap gap-2">
                            <!-- Header -->
                            <BDropdown variant="primary" class="me-2">
                                <template v-slot:button-content>
                                    Header <i class="mdi mdi-chevron-down"></i>
                                </template>
                                <div class="dropdown-header noti-title px-3 py-2">
                                    <h5 class="font-size-13 text-muted text-truncate mn-0">Welcome Jessie!</h5>
                                </div>
                                <BDropdownItem href="#">Action</BDropdownItem>
                                <BDropdownItem href="#">Another action</BDropdownItem>
                                <BDropdownItem href="#">Something else here</BDropdownItem>
                                <BDropdownDivider />
                                <BDropdownItem href="#">Separated link</BDropdownItem>
                            </BDropdown>

                            <!-- Text -->
                            <BDropdown variant="success" class="me-2">
                                <template v-slot:button-content>
                                    Text <i class="mdi mdi-chevron-down"></i>
                                </template>
                                <div class="dropdown-menu-md p-3 show">
                                    <p>Some example text that's free-flowing within the dropdown menu.</p>
                                    <p class="mb-0">And this is more example text.</p>
                                </div>
                            </BDropdown>

                            <!-- Forms -->
                            <BDropdown variant="light" class="me-2">
                                <template v-slot:button-content>
                                    Forms <i class="mdi mdi-chevron-down"></i>
                                </template>
                                <div class="dropdown-menu-md p-4 show">
                                    <form @submit.prevent>
                                        <div class="mb-2">
                                            <label class="form-label" for="exampleDropdownFormEmail">Email
                                                address</label>
                                            <input type="email" class="form-control" id="exampleDropdownFormEmail"
                                                placeholder="email@example.com" />
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label" for="exampleDropdownFormPassword">Password</label>
                                            <input type="password" class="form-control" id="exampleDropdownFormPassword"
                                                placeholder="Password" />
                                        </div>
                                        <div class="mb-2">
                                            <div class="form-check custom-checkbox">
                                                <input type="checkbox" class="form-check-input"
                                                    id="rememberdropdownCheck" />
                                                <label class="form-check-label" for="rememberdropdownCheck">Remember
                                                    me</label>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Sign in</button>
                                    </form>
                                </div>
                            </BDropdown>
                        </div>
                    </BCardBody>
                </BCard>
            </div>

            <BCol xl="6">
                <BCard>
                    <BCardBody>
                        <BCardTitle>Dropdown Menu Dark</BCardTitle>
                        <p class="card-title-desc">
                            Opt into darker dropdowns by adding
                            <code>.dropdown-menu-dark</code> onto the dropdown menu.
                        </p>

                        <BDropdown variant="secondary" menu-class="text-light dropdown-menu-dark">
                            <template #button-content>
                                Menu is right-aligned <i class="mdi mdi-chevron-down"></i>
                            </template>
                            <BDropdownItem href="#">Action</BDropdownItem>
                            <BDropdownItem href="#">Another action</BDropdownItem>
                            <BDropdownItem href="#">Something else here</BDropdownItem>
                        </BDropdown>
                    </BCardBody>
                </BCard>
            </BCol>
        </div> <!-- end row -->

        <BRow>
            <BCol xl="6">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Menu Content</BCardTitle>
                        <p class="card-title-desc">Example of dropdown menu Headers, Text, Forms content</p>

                        <div class="d-flex flex-wrap gap-2">
                            <!-- Header -->
                            <BButtonGroup>
                                <BDropdown variant="primary">
                                    <template v-slot:button-content>
                                        Header
                                        <i class="mdi mdi-chevron-down"></i>
                                    </template>
                                    <b-dropdown-header class="noti-title">
                                        <h5 class="font-size-13 text-muted text-truncate mn-0">Welcome Jessie!</h5>
                                    </b-dropdown-header>
                                    <BDropdownItem href="javascript: void(0);">Action</BDropdownItem>
                                    <BDropdownItem href="javascript: void(0);">Another action</BDropdownItem>
                                    <BDropdownItem href="javascript: void(0);">Something else here</BDropdownItem>
                                    <BDropdownDivider></BDropdownDivider>
                                    <BDropdownItem href="javascript: void(0);">Separated link</BDropdownItem>
                                </BDropdown>
                            </BButtonGroup>

                            <!-- Text -->
                            <BButtonGroup>
                                <BDropdown variant="success" menu-class="dropdown-menu-md p-3">
                                    <template v-slot:button-content>
                                        Text
                                        <i class="mdi mdi-chevron-down"></i>
                                    </template>
                                    <p>
                                        Some example text that's free-flowing within the dropdown menu.
                                    </p>
                                    <p class="mb-0">
                                        And this is more example text.
                                    </p>
                                </BDropdown>
                            </BButtonGroup>

                            <!-- Forms -->
                            <BButtonGroup>
                                <BDropdown id="dropdown-form" ref="dropdown" menu-class="dropdown-menu-md">
                                    <template v-slot:button-content>
                                        Forms
                                        <i class="mdi mdi-chevron-down"></i>
                                    </template>
                                    <BDropdownForm>
                                        <BFormGroup label="Email" label-for="dropdown-form-email" @submit.stop.prevent>
                                            <BFormInput id="dropdown-form-email" size=""
                                                placeholder="email@example.com">
                                            </BFormInput>
                                        </BFormGroup>

                                        <BFormGroup label="Password" label-for="dropdown-form-password">
                                            <BFormInput id="dropdown-form-password" type="password" size=""
                                                placeholder="Password"></BFormInput>
                                        </BFormGroup>

                                        <BFormCheckbox class="mb-3">Remember me</BFormCheckbox>
                                        <b-button variant="primary" @click="onClick">Sign In</b-button>
                                    </BDropdownForm>
                                </BDropdown>
                            </BButtonGroup>
                        </div>
                    </BCardBody>
                </BCard>
            </BCol>

            <BCol xl="6">
                <BCard>
                    <BCardBody>
                        <BCardTitle>Dropdown Menu Dark</BCardTitle>
                        <p class="card-title-desc">Opt into darker dropdowns to match a dark navbar or custom style by
                            adding <code>.dropdown-menu-dark</code> onto an existing <code>.dropdown-menu</code>. No
                            changes
                            are required to the dropdown items.</p>

                        <BButtonGroup>
                            <BDropdown variant="secondary" menu-class="dropdown-menu-dark">
                                <template v-slot:button-content>
                                    Menu is right-aligned
                                    <i class="mdi mdi-chevron-down"></i>
                                </template>
                                <BDropdownItem href="javascript: void(0);">Action</BDropdownItem>
                                <BDropdownItem href="javascript: void(0);">Another action</BDropdownItem>
                                <BDropdownItem href="javascript: void(0);">Something else here</BDropdownItem>
                            </BDropdown>
                        </BButtonGroup>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>


        <BRow>
            <BCol lg="6">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Menu alignment</BCardTitle>

                        <p class="card-title-desc">
                            Add <code class="highlighter-rouge">.dropdown-menu-right</code> to
                            a <code class="highlighter-rouge">.dropdown-menu</code> to right
                            align the dropdown menu.
                        </p>

                        <div class="d-flex flex-wrap gap-3">
                            <div class="btn-group">
                                <BDropdown right variant="secondary">
                                    <template #button-content>
                                        Menu is right-aligned <i class="mdi mdi-chevron-down"></i>
                                    </template>
                                    <BDropdownItem href="#">Action</BDropdownItem>
                                    <BDropdownItem href="#">Another action</BDropdownItem>
                                    <BDropdownItem href="#">Something else here</BDropdownItem>
                                </BDropdown>
                            </div>

                            <div class="btn-group">
                                <BDropdown id="dropdown-dropright" left>
                                    <template v-slot:button-content>
                                        Left-aligned menu Example
                                        <i class="mdi mdi-chevron-down"></i>
                                    </template>
                                    <BDropdownItem href="#">Action</BDropdownItem>
                                    <BDropdownItem href="#">Another action</BDropdownItem>
                                    <BDropdownItem href="#">Something else here</BDropdownItem>
                                </BDropdown>
                            </div>
                        </div>
                    </BCardBody>
                </BCard>
            </BCol>

            <BCol lg="6">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Auto Close Behavior</BCardTitle>
                        <p class="card-title-desc">
                            By default, the dropdown menu is closed when clicking inside or outside the dropdown menu.
                            You can
                            use the
                            <code>autoClose</code> option to change this behavior of the dropdown.
                        </p>

                        <div class="d-flex gap-2 flex-wrap">
                            <!-- Default -->
                            <BDropdown auto-close="true" variant="secondary">
                                <template #button-content>
                                    Default dropdown <i class="mdi mdi-chevron-down"></i>
                                </template>
                                <BDropdownItem>Menu item</BDropdownItem>
                                <BDropdownItem>Menu item</BDropdownItem>
                                <BDropdownItem>Menu item</BDropdownItem>
                            </BDropdown>

                            <!-- Clickable outside -->
                            <BDropdown auto-close="inside" variant="secondary">
                                <template #button-content>
                                    Clickable outside <i class="mdi mdi-chevron-down"></i>
                                </template>
                                <BDropdownItem>Menu item</BDropdownItem>
                                <BDropdownItem>Menu item</BDropdownItem>
                                <BDropdownItem>Menu item</BDropdownItem>
                            </BDropdown>

                            <!-- Clickable inside -->
                            <BDropdown auto-close="outside" variant="secondary">
                                <template #button-content>
                                    Clickable inside <i class="mdi mdi-chevron-down"></i>
                                </template>
                                <BDropdownItem>Menu item</BDropdownItem>
                                <BDropdownItem>Menu item</BDropdownItem>
                                <BDropdownItem>Menu item</BDropdownItem>
                            </BDropdown>

                            <!-- Manual close -->
                            <BDropdown auto-close="false" variant="secondary">
                                <template #button-content>
                                    Manual close <i class="mdi mdi-chevron-down"></i>
                                </template>
                                <BDropdownItem>Menu item</BDropdownItem>
                                <BDropdownItem>Menu item</BDropdownItem>
                                <BDropdownItem>Menu item</BDropdownItem>
                            </BDropdown>
                        </div>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>

        <BRow>
            <BCol lg="6">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Dropright variation</BCardTitle>
                        <p class="card-title-desc mb-3">
                            Trigger dropdown menus at the right of the elements by adding
                            <code>dropright</code> to the parent element.
                        </p>

                        <div class="dropdown mt-1">
                            <BDropdown dropright variant="info" class="m-2">
                                <template v-slot:button-content>
                                    Dropright

                                    <i class="mdi mdi-chevron-right"></i>
                                </template>
                                <BDropdownItemButton>Action</BDropdownItemButton>
                                <BDropdownItemButton>Another action</BDropdownItemButton>
                                <BDropdownItemButton>Another action</BDropdownItemButton>
                            </BDropdown>

                            <BDropdown split text="Split dropright" dropright variant="info" class="m-2">
                                <BDropdownItemButton>Action</BDropdownItemButton>
                                <BDropdownItemButton>Another action</BDropdownItemButton>
                                <BDropdownItemButton>Something else here</BDropdownItemButton>
                            </BDropdown>
                        </div>
                    </BCardBody>
                </BCard>
            </BCol>

            <BCol lg="6">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Dropleft variation</BCardTitle>
                        <p class="card-title-desc mb-3">
                            Trigger dropdown menus at the right of the elements by adding
                            <code>dropleft</code> to the parent element.
                        </p>
                        <BDropdown dropleft variant="info" class="m-2">
                            <template v-slot:button-content>
                                <i class="mdi mdi-chevron-left"></i>

                                Dropleft
                            </template>
                            <BDropdownItemButton>Action</BDropdownItemButton>
                            <BDropdownItemButton>Another action</BDropdownItemButton>
                            <BDropdownItemButton>Something else here</BDropdownItemButton>
                        </BDropdown>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>
    </Layout>
</template>