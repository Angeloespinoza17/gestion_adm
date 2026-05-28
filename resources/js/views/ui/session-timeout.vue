<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";
import { onMounted } from "vue";
import { onBeforeUnmount } from "vue";
import { ref } from "vue";

/**
 * Session-timeout component
 */
export default {
    components: { Layout, PageHeader },
    setup() {
        const showModal = ref(false);
        const countdown = ref(10);
        let idleTimer = null;
        let countdownTimer = null;

        const warnAfter = 3000; // 3 sec
        const redirAfter = 30000; // 30 sec

        const resetIdleTimer = () => {
            clearTimeout(idleTimer);
            idleTimer = setTimeout(() => {
                showModal.value = true;
                startCountdown();
            }, warnAfter);
        };

        const startCountdown = () => {
            countdown.value = (redirAfter) / 1000;
            countdownTimer = setInterval(() => {
                countdown.value--;
                if (countdown.value <= 0) logout();
            }, 1000);
        };

        const stayConnected = () => {
            showModal.value = false;
            clearInterval(countdownTimer);
            resetIdleTimer();
        };

        const logout = () => {
            clearInterval(countdownTimer);
            window.location.href = '/login'; // or use router.push('/login')
        };

        const handleClose = () => {
            clearInterval(countdownTimer);
        };

        const activityEvents = ['mousemove', 'keydown', 'click', 'touchstart'];

        const setupListeners = () => {
            activityEvents.forEach(evt =>
                window.addEventListener(evt, resetIdleTimer)
            );
        };

        const removeListeners = () => {
            activityEvents.forEach(evt =>
                window.removeEventListener(evt, resetIdleTimer)
            );
        };

        onMounted(() => {
            resetIdleTimer();
            setupListeners();
        });

        onBeforeUnmount(() => {
            clearTimeout(idleTimer);
            clearInterval(countdownTimer);
            removeListeners();
        });

        return { showModal, countdown, stayConnected, logout, handleClose };
    },
};
</script>

<template>
    <Layout>
        <PageHeader title="Session Timeout" pageTitle="UI Elements" />

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h5 class="header-title">Bootstrap-session-timeout</h5>
                        <p class="sub-header">Session timeout and keep-alive control
                            with a nice Bootstrap warning dialog.</p>

                        <div>
                            <p>After a set amount of idle time, a Bootstrap warning dialog is shown
                                to the user with the option to either log out, or stay connected. If
                                "Logout" button is selected, the page is redirected to a logout URL.
                                If "Stay Connected" is selected the dialog closes and the session is
                                kept alive. If no option is selected after another set amount of
                                idle time, the page is automatically redirected to a set timeout
                                URL.</p>

                            <p>
                                Idle time is defined as no mouse, keyboard or touch event activity registered by the
                                browser.
                            </p>

                            <p class="mb-0">
                                As long as the user is active, the (optional) keep-alive URL keeps
                                getting pinged and the session stays alive. If you have no need to
                                keep the server-side session alive via the keep-alive URL, you can
                                also use this plugin as a simple lock mechanism that redirects to
                                your lock-session or log-out URL after a set amount of idle time.
                            </p>
                        </div>
                    </div>
                </div>
            </div> <!-- end col -->
        </div> <!-- end row -->

        <BModal v-model="showModal" title="Session Timeout" @ok="stayConnected" @hide="handleClose">
            <p>You have been idle for a while. You will be logged out soon.</p>
            <p class="text-muted">Redirecting in {{ countdown }} seconds.</p>
            <template #footer>
                <BButton variant="secondary" @click="stayConnected">Stay Connected</BButton>
                <BButton variant="danger" @click="logout">Logout</BButton>
            </template>
        </BModal>
    </Layout>
</template>

<style src="@vueform/slider/themes/default.css"></style>
