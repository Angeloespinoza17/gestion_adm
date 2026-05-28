<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "../../components/page-header.vue";
import Pagination from "@/components/common/pagination.vue";
import { userGridData as initialUsers } from "./data-user";
import dummyImage from "@/assets/images/users/user-dummy-img.jpg";

/**
 * Contacts-list component
 */
export default {
  components: { Layout, PageHeader, Pagination },
  data() {
    return {
      userGridData: userGridData,
      userGridData: [...initialUsers],
      showAddModal: false,
      showDeleteModal: false,
      selectedImage: null,
      previewImage: null,
      dummyImage,
      form: {
        id: null,
        name: "",
        email: "",
        designation: "",
        tags: [],
        image: null,
      },
      deleteUserId: null,
    };
  },
  methods: {
    resetForm() {
      this.form = {
        id: null,
        name: "",
        email: "",
        designation: "",
        tags: [],
        image: null,
      };
      this.previewImage = null;
      this.selectedImage = null;
    },
    openAddModal() {
      this.resetForm();
      this.showAddModal = true;
    },
    handleImageUpload(e) {
      const file = e.target.files[0];
      if (file) {
        this.selectedImage = file;
        this.form.image = URL.createObjectURL(file);
        this.previewImage = URL.createObjectURL(file);
      }
    },
    submitContact() {
      const newContact = {
        ...this.form,
        id: this.form.id || Date.now(),
        image: this.previewImage || null,
        projects: this.form.tags,
      };
      const exists = this.userGridData.findIndex(u => u.id === newContact.id);
      if (exists !== -1) this.userGridData.splice(exists, 1, newContact);
      else this.userGridData.unshift(newContact);
      this.showAddModal = false;
    },
    confirmDelete(userId) {
      this.deleteUserId = userId;
      this.showDeleteModal = true;
    },
    deleteContact() {
      this.userGridData = this.userGridData.filter(u => u.id !== this.deleteUserId);
      this.showDeleteModal = false;
    }
  }
};
</script>

<template>
  <Layout>
    <PageHeader title="Users List" pageTitle="Contacts" />

    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardBody>
            <BRow class="mb-2">
              <BCol sm="4">
                <div class="search-box me-2 mb-2 d-inline-block">
                  <div class="position-relative">
                    <input type="text" class="form-control" id="searchTableList" placeholder="Search..." />
                    <i class="bx bx-search-alt search-icon"></i>
                  </div>
                </div>
              </BCol>
              <BCol sm="8">
                <div class="text-sm-end">
                  <BButton variant="success" class="mb-2" @click="openAddModal">
                    <i class="mdi mdi-plus me-1"></i> New Contact
                  </BButton>
                </div>
              </BCol>
            </BRow>

            <div class="table-responsive">
              <BTableSimple class="table table-nowrap align-middle">
                <BThead class="table-light">
                  <BTr>
                    <BTh>#</BTh>
                    <BTh>Name</BTh>
                    <BTh>Email</BTh>
                    <BTh>Tags</BTh>
                    <BTh>Projects</BTh>
                    <BTh>Action</BTh>
                  </BTr>
                </BThead>
                <BTbody>
                  <BTr v-for="user in userGridData" :key="user.id">
                    <BTd>
                      <img v-if="user.image" :src="user.image" class="rounded-circle avatar-xs" />
                      <div v-else class="avatar-xs">
                        <span class="avatar-title rounded-circle">{{ user.name.charAt(0) }}</span>
                      </div>
                    </BTd>
                    <BTd>
                      <h5 class="mb-0">{{ user.name }}</h5>
                      <small class="text-muted">{{ user.designation }}</small>
                    </BTd>
                    <BTd>{{ list.email }}</BTd>
                    <BTd>
                      <span v-for="(tag, index) in user.projects" :key="index" class="badge bg-primary m-1">{{ tag
                      }}</span>
                    </BTd>
                    <BTd>{{ user.projects.length }}</BTd>
                    <BTd>
                      <BButton variant="soft-danger" size="sm" @click="confirmDelete(user.id)">
                        <i class="mdi mdi-trash-can-outline"></i>
                      </BButton>
                    </BTd>
                  </BTr>
                </BTbody>
              </BTableSimple>
            </div>
            <BRow>
              <BCol>
                <Pagination />
              </BCol>
            </BRow>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <!-- Add/Edit Modal -->
    <BModal v-model="showAddModal" title="Add Contact" centered hide-footer>
      <BForm @submit.prevent="submitContact">
        <div class="text-center mb-3">
          <div class="position-relative d-inline-block">
            <div class="avatar-lg">
              <img :src="previewImage || dummyImage" class="rounded-circle avatar-md" />
              <label class="position-absolute bottom-0 end-0">
                <BFormFile class="d-none" @change="handleImageUpload" />
                <div class="avatar-xs">
                  <div class="avatar-title bg-light border rounded-circle text-muted cursor-pointer">
                    <i class="bx bxs-image-alt"></i>
                  </div>
                </div>
              </label>
            </div>
          </div>
        </div>

        <BFormGroup label="Name" label-for="name">
          <BFormInput id="name" v-model="form.name" required />
        </BFormGroup>

        <BFormGroup label="Designation" label-for="designation">
          <BFormInput id="designation" v-model="form.designation" required />
        </BFormGroup>

        <BFormGroup label="Email" label-for="email">
          <BFormInput type="email" id="email" v-model="form.email" required />
        </BFormGroup>

        <BFormGroup label="Tags">
          <BFormTags v-model="form.tags" placeholder="Add tags..." separator=" ,;" />
        </BFormGroup>

        <div class="text-end">
          <BButton variant="secondary" @click="showAddModal = false">Cancel</BButton>
          <BButton type="submit" variant="success" class="ms-2">Save</BButton>
        </div>
      </BForm>
    </BModal>

    <!-- Delete Confirmation Modal -->
    <BModal v-model="showDeleteModal" title="Confirm Delete" centered hide-footer>
      <div class="text-center">
        <div class="avatar-sm mb-3 mx-auto">
          <div class="avatar-title bg-light rounded-circle text-danger">
            <i class="mdi mdi-trash-can-outline fs-3"></i>
          </div>
        </div>
        <p>Are you sure you want to delete this contact?</p>
        <div class="d-flex justify-content-center gap-2">
          <BButton variant="danger" @click="deleteContact">Remove</BButton>
          <BButton variant="secondary" @click="showDeleteModal = false">Cancel</BButton>
        </div>
      </div>
    </BModal>
    
  </Layout>
</template>
