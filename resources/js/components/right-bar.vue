<script>
import simplebar from 'simplebar-vue';

import layout1 from '../../images/layouts/layout-1.jpg';
import layout2 from '../../images/layouts/layout-2.jpg';
import layout3 from '../../images/layouts/layout-3.jpg';


/**
 * Right sidebar component
 */
export default {
    components: { simplebar },
    data() {
        return {
            layout1, layout2, layout3,
            config: {
                handler: this.handleRightBarClick,
                middleware: this.middleware,
                events: ["click"]
            },
            layoutType: this.$root.layout.type,
            width: this.$root.layout.width,
            sidebarType: this.$root.layout.sidebar,
            topbar: this.$root.layout.topbar,
            loader: this.$root.layout.loader,
            mode: this.$root.layout.mode,
        };
    },
    methods: {
        hide() {
            this.$parent.toggleRightSidebar();
        },
        // eslint-disable-next-line no-unused-vars
        handleRightBarClick(e, el) {
            this.$parent.hideRightSidebar();
        },
        // eslint-disable-next-line no-unused-vars
        middleware(event, el) {
            return !event.target.classList.contains("toggle-right");
        },
        changeLayout(layout) {
            if (layout == "horizontal") {
                this.topbar = "dark";
                this.$root.changeTopbar("dark");
                this.$root.changeLayout({ type: layout, topbar: "dark" });

            } else {
                this.sidebarType = "dark";
                this.$root.changeSidebar("dark");
                this.$root.changeLayout({ type: layout, sidebar: "dark", topbar: "light" });
            }

            this.$root.loadRightCollapse();
        },
        changeType(type) {
            this.sidebarType = type;
            this.$root.changeSidebar(type);
            return this.$root.changeLayout({ sidebar: type });
        },
        changeLayoutMode(mode) {
            this.mode = mode;
            this.$root.changeMode(mode);
            return this.$root.changeLayout({ mode: mode });
        },
        changeLayoutWidth(width) {
            this.width = width;
            this.$root.changeWidth(width);
            return this.$root.changeLayout({ width: width });
        },
        changeTopbartype(value) {
            this.topbar = value;
            this.$root.changeTopbar(value);
            return this.$root.changeLayout({ topbar: value });
        },
        changeloader(loader) {
            this.loader = loader;
            return this.$root.changeLayout({ loader: loader });
        }
    },
};
</script>

<template>
    <div>
        <div v-click-outside="config" class="right-bar">
            <simplebar class="h-100">
                <div class="rightbar-title px-3 py-4">
                    <BLink href="javascript:void(0);" class="right-bar-toggle float-end" @click="hide">
                        <i class="mdi mdi-close noti-icon"></i>
                    </BLink>
                    <h5 class="m-0">Settings</h5>
                </div>
                <div class="p-3">
                    <h6 class="mb-0">Layout</h6>
                    <hr class="mt-1" />
                    <div class="d-flex">
                        <BFormRadio class="mb-2" v-model="layoutType" name="layoutSelect" value="vertical"
                            @input="changeLayout($event)">Vertical</BFormRadio>
                        <BFormRadio class="mb-2 ms-2" v-model="layoutType" name="layoutSelect" value="horizontal"
                            @input="changeLayout($event)"><span class="ms-1">Horizontal</span></BFormRadio>
                    </div>

                    <h6 class="mt-3">Theme Modes</h6>
                    <hr class="mt-1" />

                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="mode" id="mode-radio1" value="light"
                            @click="changeLayoutMode('light')" v-model="mode" />
                        <label class="form-check-label" for="mode-radio1">Light</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="mode" id="mode-radio2" value="dark"
                            @click="changeLayoutMode('dark')" v-model="mode" />
                        <label class="form-check-label" for="mode-radio2">Dark</label>
                    </div>

                    <!-- Width -->
                    <h6 class="mt-3">Width</h6>
                    <hr class="mt-1" />

                    <BFormRadio class="mb-2" v-model="width" name="widthSelect" value="fluid"
                        @input="changeLayoutWidth($event)">Fluid</BFormRadio>
                    <BFormRadio class="mb-2" v-model="width" name="widthSelect" value="boxed"
                        @input="changeLayoutWidth($event)">Boxed</BFormRadio>

                    <!-- Sidebar -->
                    <div v-if="layoutType === 'vertical'">
                        <h6 class="mt-3">Sidebar</h6>

                        <hr class="mt-1" />
                        <BFormRadio class="mb-2" v-model="sidebarType" name="sidebarSelect" value="dark"
                            @input="changeType($event)">Dark</BFormRadio>
                        <BFormRadio class="mb-2" v-model="sidebarType" name="sidebarSelect" value="light"
                            @input="changeType($event)">Light</BFormRadio>
                        <BFormRadio class="mb-2" v-model="sidebarType" name="sidebarSelect" value="compact"
                            @input="changeType($event)">Compact</BFormRadio>
                        <BFormRadio class="mb-2" v-model="sidebarType" name="sidebarSelect" value="icon"
                            @input="changeType($event)">Icon</BFormRadio>
                        <BFormRadio class="mb-2" v-model="sidebarType" name="sidebarSelect" value="colored"
                            @input="changeType($event)">Colored</BFormRadio>
                        <BFormRadio class="mb-2" v-model="sidebarType" name="sidebarSelect" value="scrollable"
                            @input="changeType($event)">Scrollable</BFormRadio>

                    </div>

                    <!-- Topbar -->
                    <div v-if="layoutType === 'horizontal'">
                        <h6 class="mt-3">Topbar</h6>
                        <hr class="mt-1" />

                        <BFormRadio class="mb-2" v-model="topbar" name="topbarbarSelect" value="dark"
                            @input="changeTopbartype($event)">Dark</BFormRadio>
                        <BFormRadio class="mb-2" v-model="topbar" name="topbarbarSelect" value="light"
                            @input="changeTopbartype($event)">Light</BFormRadio>
                        <BFormRadio class="mb-2" v-model="topbar" name="topbarbarSelect" value="colored"
                            @input="changeTopbartype($event)">Colored</BFormRadio>
                        <BFormRadio class="mb-2" v-model="topbar" name="topbarbarSelect" value="scrollable"
                            @input="changeTopbartype($event)">Scrollable</BFormRadio>

                    </div>

                    <h6 class="mt-3">Preloader</h6>
                    <hr class="mt-1" />
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is-loader" v-model="loader" @click="changeloader(loader)" />
                        <label class="form-check-label" for="is-loader">Preloader</label>
                    </div>
                </div>
                <!-- Settings -->
                <h6 class="text-center mb-0">Choose Mode</h6>

                <div class="p-4">
                    <div class="mb-2">
                        <BLink href="javascript: void(0);" @click="changeLayoutMode('light')">
                            <img :src="layout1" class="img-fluid img-thumbnail" alt />
                        </BLink>
                    </div>

                    <div class="mb-2">
                        <BLink href="javascript: void(0);" @click="changeLayoutMode('dark')">
                            <img :src="layout2" class="img-fluid img-thumbnail" alt />
                        </BLink>
                    </div>

                </div>
            </simplebar>
        </div>

        <!-- Right bar overlay-->
        <div class="rightbar-overlay"></div>
    </div>
</template>

<style lang="scss"></style>
