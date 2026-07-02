<script>
import HorizontalTopbar from "../components/horizontal-topbar.vue";
import HorizontalNav from "../components/horizontal-nav.vue";
import RightBar from "../components/right-bar.vue";

/**
 * Horizontal-layout
 */
export default {
    components: {
        HorizontalTopbar,
        HorizontalNav,
        RightBar
    },
    data() {
        return {};
    },
    created: () => {
        document.body.setAttribute("data-layout", "horizontal");
        document.body.removeAttribute("data-sidebar", "dark");
        document.body.removeAttribute("data-layout-size", "boxed");
    },
    methods: {
        toggleRightSidebar() {
            document.body.classList.toggle("right-bar-enabled");
        },
        hideRightSidebar() {
            document.body.classList.remove("right-bar-enabled");
        }
    }
};
</script>

<template>
    <div>
        <!-- Begin page -->
        <div id="layout-wrapper" class="premium-admin-shell">
            <HorizontalTopbar />
            <HorizontalNav />
            <!-- ============================================================== -->
            <!-- Start right Content here -->
            <!-- ============================================================== -->
            <div class="main-content premium-main-content">
                <div class="page-content premium-page-content">
                    <BContainer fluid class="premium-content-grid">
                        <slot />
                    </BContainer>
                    <!-- container-fluid -->
                </div>
                <!-- End Page-content -->
            </div>
            <!-- end main content-->
        </div>
        <!-- END layout-wrapper -->
        <RightBar />
    </div>
</template>
