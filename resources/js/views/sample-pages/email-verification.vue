<script>

import logoDark from '../../../images/logo-dark.png';
import logoLight from '../../../images/logo-light.png'

export default {
  data() {
    return {
      logoDark, logoLight
    };
  },
};
</script>

<template>
  <div class="account-pages my-5 pt-sm-5">
    <BContainer>
      <BRow>
        <BCol lg="12">
          <div class="text-center mb-5 text-muted">
            <router-link to="/" class="d-block auth-logo">
              <img :src="logoDark" alt="" height="20" class="auth-logo-dark mx-auto" />
              <img :src="logoLight" alt="" height="20" class="auth-logo-light mx-auto" />
            </router-link>
            <p class="mt-3">Responsive Bootstrap 5 Admin Dashboard</p>
          </div>
        </BCol>
      </BRow>

      <BRow class="justify-content-center">
        <BCol md="8" lg="6" xl="5">
          <BCard no-body>
            <BCardBody>
              <div class="p-2">
                <div class="text-center">
                  <div class="avatar-md mx-auto">
                    <div class="avatar-title rounded-circle bg-light">
                      <i class="bx bxs-envelope h1 mb-0 text-primary"></i>
                    </div>
                  </div>
                  <div class="p-2 mt-4">
                    <h4>Verify your email</h4>
                    <p>
                      We have sent you verification email
                      <span class="fw-semibold">example@abc.com</span>,
                      Please check it
                    </p>
                    <div class="mt-4">
                      <router-link to="/" class="btn btn-success w-md">Verify email</router-link>
                    </div>
                  </div>
                </div>
              </div>
            </BCardBody>
          </BCard>
          <div class="mt-5 text-center">
            <p>
              Didn't receive an email ?
              <BLink href="#" class="fw-medium text-primary"> Resend </BLink>
            </p>
            <p>
              ©
              {{ new Date().getFullYear() }} Skote. Crafted with
              <i class="mdi mdi-heart text-danger"></i> by Themesbrand
            </p>
          </div>
        </BCol>
      </BRow>
    </BContainer>
  </div>
</template>