<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";

import toastr from 'toastr'
import 'toastr/build/toastr.min.css'

export default {
    components: {
        Layout,
        PageHeader
    },
    data() {
        return {
            title: '',
            message: '',
            toastType: 'success',
            options: {
                closeButton: false,
                addBehaviorOnToastClick: false,
                debug: false,
                progressBar: false,
                preventDuplicates: false,
                addClear: false,
                newestOnTop: false,
                positionClass: 'toast-top-right',
                showEasing: 'swing',
                hideEasing: 'linear',
                showMethod: 'fadeIn',
                hideMethod: 'fadeOut',
                showDuration: 300,
                hideDuration: 1000,
                timeOut: 5000,
                extendedTimeOut: 1000
            },
            commandPreview: '',
            lastToast: null
        }
    },
    methods: {
        showToast() {
            toastr.options = {
                closeButton: this.options.closeButton,
                debug: this.options.debug,
                newestOnTop: this.options.newestOnTop,
                progressBar: this.options.progressBar,
                preventDuplicates: this.options.preventDuplicates,
                positionClass: this.options.positionClass,
                onclick: this.options.addBehaviorOnToastClick ? (() => alert('You can perform some custom action after a toast goes away')) : null
            }
            if (this.options.addClear) {
                toastr.options.tapToDismiss = false
            }
            toastr.options.showDuration = this.options.showDuration
            toastr.options.hideDuration = this.options.hideDuration
            toastr.options.timeOut = this.options.addClear ? 0 : this.options.timeOut
            toastr.options.extendedTimeOut = this.options.addClear ? 0 : this.options.extendedTimeOut
            toastr.options.showEasing = this.options.showEasing
            toastr.options.hideEasing = this.options.hideEasing
            toastr.options.showMethod = this.options.showMethod
            toastr.options.hideMethod = this.options.hideMethod

            const msg = this.message || this._getDefaultMessage()
            const $toast = toastr[this.toastType](msg, this.title)
            this.lastToast = $toast
            this._updateCommandPreview(msg)
        },
        clearToasts() {
            toastr.clear()
        },
        clearLastToast() {
            if (this.lastToast) {
                toastr.clear(this.lastToast)
            }
        },
        _getDefaultMessage() {
            const msgs = [
                'My name is Inigo Montoya. You killed my father. Prepare to die!',
                'Are you the six fingered man?',
                'Inconceivable!',
                'I do not think that means what you think it means.',
                'Have fun storming the castle!'
            ]
            const idx = Math.floor(Math.random() * msgs.length)
            return msgs[idx]
        },
        _updateCommandPreview(msg) {
            const optCopy = { ...toastr.options }
            // remove onclick in preview for readability
            delete optCopy.onclick
            this.commandPreview =
                `Command: toastr["${this.toastType}"]("${msg}"${this.title ? `", "${this.title}"` : ''})\ntoastr.options = ${JSON.stringify(optCopy, null, 2)}`
        }
    }
}
</script>

<template>
    <Layout>
        <PageHeader title="Form Mask" pageTitle="Forms" />
        <b-row>
            <b-col cols="12">
                <b-card>
                    <b-card-body>
                        <h4 class="card-title">Notifications</h4>
                        <p class="card-title-desc">
                            Toasts are lightweight notifications designed to mimic the push notifications
                        </p>
                        <b-row>
                            <b-col xl="4">
                                <!-- Inputs: title, message -->
                                <b-form-group label="Title" class="mb-3">
                                    <b-form-input v-model="title" placeholder="Enter a title ..." />
                                </b-form-group>
                                <b-form-group label="Message" class="mb-3">
                                    <b-form-textarea v-model="message" rows="3" placeholder="Enter a message ..." />
                                </b-form-group>
                                <div class="my-4">
                                    <b-form-checkbox v-model="options.closeButton">Close Button</b-form-checkbox>
                                    <b-form-checkbox v-model="options.addBehaviorOnToastClick">Add behavior on toast
                                        click</b-form-checkbox>
                                    <b-form-checkbox v-model="options.debug">Debug</b-form-checkbox>
                                    <b-form-checkbox v-model="options.progressBar">Progress Bar</b-form-checkbox>
                                    <b-form-checkbox v-model="options.preventDuplicates">Prevent
                                        Duplicates</b-form-checkbox>
                                    <b-form-checkbox v-model="options.addClear">Add button to force clearing a toast,
                                        ignoring
                                        focus</b-form-checkbox>
                                    <b-form-checkbox v-model="options.newestOnTop">Newest on top</b-form-checkbox>
                                </div>
                            </b-col>
                            <b-col xl="2">
                                <b-form-group label="Toast Type" class="mb-4">
                                    <b-form-radio-group v-model="toastType">
                                        <b-form-radio value="success">Success</b-form-radio>
                                        <b-form-radio value="info">Info</b-form-radio>
                                        <b-form-radio value="warning">Warning</b-form-radio>
                                        <b-form-radio value="error">Error</b-form-radio>
                                    </b-form-radio-group>
                                </b-form-group>
                                <b-form-group label="Position" class="mb-4">
                                    <b-form-radio-group v-model="options.positionClass">
                                        <b-form-radio value="toast-top-right">Top Right</b-form-radio>
                                        <b-form-radio value="toast-bottom-right">Bottom Right</b-form-radio>
                                        <b-form-radio value="toast-bottom-left">Bottom Left</b-form-radio>
                                        <b-form-radio value="toast-top-left">Top Left</b-form-radio>
                                        <b-form-radio value="toast-top-full-width">Top Full Width</b-form-radio>
                                        <b-form-radio value="toast-bottom-full-width">Bottom Full Width</b-form-radio>
                                        <b-form-radio value="toast-top-center">Top Center</b-form-radio>
                                        <b-form-radio value="toast-bottom-center">Bottom Center</b-form-radio>
                                    </b-form-radio-group>
                                </b-form-group>
                            </b-col>
                            <b-col xl="3" sm="6">
                                <b-form-group label="Show Easing" class="mb-3">
                                    <b-form-input v-model="options.showEasing" placeholder="swing, linear" />
                                </b-form-group>
                                <b-form-group label="Hide Easing" class="mb-3">
                                    <b-form-input v-model="options.hideEasing" placeholder="swing, linear" />
                                </b-form-group>
                                <b-form-group label="Show Method" class="mb-3">
                                    <b-form-input v-model="options.showMethod" placeholder="fadeIn" />
                                </b-form-group>
                                <b-form-group label="Hide Method" class="mb-3">
                                    <b-form-input v-model="options.hideMethod" placeholder="fadeOut" />
                                </b-form-group>
                            </b-col>
                            <b-col xl="3" sm="6">
                                <b-form-group label="Show Duration" class="mb-3">
                                    <b-form-input v-model.number="options.showDuration" placeholder="ms" />
                                </b-form-group>
                                <b-form-group label="Hide Duration" class="mb-3">
                                    <b-form-input v-model.number="options.hideDuration" placeholder="ms" />
                                </b-form-group>
                                <b-form-group label="Time out" class="mb-3">
                                    <b-form-input v-model.number="options.timeOut" placeholder="ms" />
                                </b-form-group>
                                <b-form-group label="Extended time out" class="mb-3">
                                    <b-form-input v-model.number="options.extendedTimeOut" placeholder="ms" />
                                </b-form-group>
                            </b-col>
                        </b-row>
                        <b-row class="mt-4">
                            <b-col>
                                <b-button variant="primary" @click="showToast">Show Toast</b-button>
                                <b-button variant="danger" class="ms-2" @click="clearToasts">Clear Toasts</b-button>
                                <b-button variant="danger" class="ms-2" @click="clearLastToast">Clear Last
                                    Toast</b-button>
                            </b-col>
                        </b-row>
                        <b-row class="mt-3">
                            <b-col>
                                <pre class="toastr-options">{{ commandPreview }}</pre>
                            </b-col>
                        </b-row>
                    </b-card-body>
                </b-card>
            </b-col>
        </b-row>
    </Layout>
</template>
