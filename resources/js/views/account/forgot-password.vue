<script>
import profileImg from '../../../images/profile-img.png';
import logo from '../../../images/logo.svg';
import axios from 'axios';
/**
 * Forgot Password component
 */
export default {
    data() {
        return {
            email: "",
            error: "",
            status: "",
            isResetError: false,
            tryingToReset: false,
            processing: false,
            profileImg, logo
        };
    },
    mounted() {
        this.isResetError = !!this.error;
        this.tryingToReset = !!this.status;
    },
    methods: {
        async forget() {
            this.processing = true
            await axios.post('/api/forget-password',
                { email: this.email }
            )
                .then(({ data }) => {
                    if (data.success == true && data.message == 'success') {
                        this.status = "Password reset link send in your email.";
                        this.tryingToReset = true;
                    } else {
                        if (data.data == 400) {
                            this.error = data.message;
                            this.isResetError = true;
                        }
                    }
                }).catch(({ response: { data } }) => {
                    console.log(data)
                }).finally(() => {
                    this.processing = false
                })
        },
    }

};
</script>

<template>
    <div class="account-pages my-5 pt-5">
        <BContainer>
            <BRow class="justify-content-center">
                <BCol md="8" lg="6" xl="5">
                    <BCard no-body class="overflow-hidden">
                        <div class="bg-primary-subtle">
                            <BRow>
                                <BCol cols="7">
                                    <div class="text-primary p-4">
                                        <h5 class="text-primary">Reset Password</h5>
                                        <p>Re-Password with Skote.</p>
                                    </div>
                                </BCol>
                                <BCol cols="5" class="align-self-end">
                                    <img :src="profileImg" alt class="img-fluid" />
                                </BCol>
                            </BRow>
                        </div>
                        <BCardBody class="pt-0">
                            <div>
                                <router-link to="/">
                                    <div class="avatar-md profile-user-wid mb-4">
                                        <span class="avatar-title rounded-circle bg-light">
                                            <img :src="logo" alt height="34" />
                                        </span>
                                    </div>
                                </router-link>
                            </div>
                            <div class="p-2">
                                <BAlert v-model="isResetError" class="mb-4" variant="danger" dismissible>{{ error
                                }}</BAlert>
                                <BAlert v-model="tryingToReset" class="mb-4" variant="success" dismissible>{{ status
                                }}</BAlert>
                                <BForm action="javascript:void(0)" method="POST">
                                    <slot />
                                    <div class="mb-3">
                                        <label for="useremail">Email</label>
                                        <input type="email" name="email" v-model="email" class="form-control" id="useremail" placeholder="Enter email" />
                                    </div>
                                    <BRow class="form-group mb-0">
                                        <BCol cols="12" class="text-end">
                                            <BButton variant="primary" type="submit" :disabled="processing" @click="forget()" class="btn-block">
                                                {{ processing ? "Please wait" : "Forget Password" }}
                                            </BButton>
                                        </BCol>
                                    </BRow>
                                </BForm>
                            </div>
                        </BCardBody>
                    </BCard>

                    <div class="mt-5 text-center">
                        <p>
                            Remember It ?
                            <router-link to="/login" class="fw-medium text-primary">Sign In here</router-link>
                        </p>
                        <p>
                            © {{ new Date().getFullYear() }} Skote. Crafted with
                            <i class="mdi mdi-heart text-danger"></i> by Themesbrand
                        </p>
                    </div>
                </BCol>
            </BRow>
        </BContainer>
    </div>
</template>
