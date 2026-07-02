<script>
import SideBar from "../components/side-bar.vue";
import RightBar from "../components/right-bar.vue";

export default {
    components: { SideBar, RightBar },
    data() {
        return {
            isMenuCondensed: false
        };
    },
    created() {
        document.body.removeAttribute("data-layout", "horizontal");
        document.body.removeAttribute("data-topbar", "dark");
    },
    mounted() {
        window.addEventListener("resize", this.handleViewportResize);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.handleViewportResize);
        document.body.classList.remove("sidebar-enable");
    },
    watch: {
        $route() {
            if (window.innerWidth < 992) {
                this.closeMobileMenu();
            }
        },
    },
    methods: {
        toggleMenu() {
            if (window.innerWidth < 992) {
                const willOpen = !document.body.classList.contains("sidebar-enable");
                document.body.classList.toggle("sidebar-enable", willOpen);
                document.body.classList.remove("vertical-collpsed");
                this.isMenuCondensed = false;
                return;
            }

            const willOpen = !document.body.classList.contains("sidebar-enable");
            document.body.classList.toggle("sidebar-enable", willOpen);
            document.body.classList.toggle("vertical-collpsed");
            this.isMenuCondensed = !this.isMenuCondensed;
        },
        closeMobileMenu() {
            document.body.classList.remove("sidebar-enable");
            document.body.classList.remove("vertical-collpsed");
            this.isMenuCondensed = false;
        },
        handleViewportResize() {
            if (window.innerWidth < 992) {
                document.body.classList.remove("vertical-collpsed");
                this.isMenuCondensed = false;
                return;
            }

            document.body.classList.remove("sidebar-enable");
        },
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
        <div id="layout-wrapper" class="premium-admin-shell premium-admin-shell--sidebar-only">
            <SideBar :is-condensed="isMenuCondensed" @toggle-menu="toggleMenu" />
            <!-- ============================================================== -->
            <!-- Start Page Content here -->
            <!-- ============================================================== -->

            <div class="main-content premium-main-content premium-main-content--sidebar-only">
                <div class="page-content premium-page-content premium-page-content--sidebar-only">
                    <BButton class="workspace-mobile-toggle d-lg-none" @click="toggleMenu">
                        <i class="fa fa-fw fa-bars"></i>
                    </BButton>
                    <!-- Start Content-->
                    <BContainer fluid class="premium-content-grid premium-content-grid--sidebar-only">
                        <slot />
                    </BContainer>
                </div>
            </div>
            <div class="premium-sidebar-backdrop d-lg-none" @click="closeMobileMenu"></div>
            <RightBar />
        </div>
    </div>
</template>
