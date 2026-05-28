<template>
  <Layout>
    <PageHeader title="Create New" pageTitle="Project" />

    <form id="createproject-form" autocomplete="off" class="needs-validation" novalidate>
      <BRow>
        <BCol lg="8">
          <BCard>
            <BCardBody>
              <input type="hidden" class="form-control" id="formAction" name="formAction" value="add" />
              <input type="hidden" class="form-control" id="project-id-input" />

              <div class="mb-3">
                <label for="projectname-input" class="form-label">Project Name</label>
                <input id="projectname-input" name="projectname-input" type="text" class="form-control"
                  placeholder="Enter project name..." required />
                <div class="invalid-feedback">Please enter a project name.</div>
              </div>

              <div class="mb-3">
                <label class="form-label">Project Image</label>
                <div class="text-center">
                  <div class="position-relative d-inline-block">
                    <div class="position-absolute bottom-0 end-0">
                      <label for="project-image-input" class="mb-0" data-bs-toggle="tooltip" data-bs-placement="right"
                        title="Select Image">
                        <div class="avatar-xs">
                          <div
                            class="avatar-title bg-light border rounded-circle text-muted cursor-pointer shadow font-size-16">
                            <i class="bx bxs-image-alt"></i>
                          </div>
                        </div>
                      </label>
                      <input class="form-control d-none" id="project-image-input" type="file"
                        accept="image/png, image/gif, image/jpeg" />
                    </div>
                    <div class="avatar-lg">
                      <div class="avatar-title bg-light rounded-circle">
                        <img src="" id="projectlogo-img" class="avatar-md h-auto rounded-circle" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label for="projectdesc-input" class="form-label">Project Description</label>
                <textarea class="form-control" id="projectdesc-input" rows="3"
                  placeholder="Enter project description..." required></textarea>
                <div class="invalid-feedback">Please enter a project description.</div>
              </div>

              <div class="mb-3 position-relative">
                <label for="task-assign-input" class="form-label">Assigned To</label>
                <div class="avatar-group justify-content-center" id="assignee-member"></div>

                <div class="select-element">
                  <button class="btn btn-light w-100 d-flex justify-content-between" type="button"
                    data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <span>Assigned To <b id="total-assignee" class="mx-1">0</b> Members</span>
                    <i class="mdi mdi-chevron-down"></i>
                  </button>
                  <div class="dropdown-menu w-100">
                    <div data-simplebar style="max-height: 172px">
                      <ul class="list-unstyled mb-0 assignto-list">
                        <li v-for="user in users" :key="user.name">
                          <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="avatar-xs flex-shrink-0 me-2">
                              <img :src="user.avatar" alt="" class="img-fluid rounded-circle" />
                            </div>
                            <div class="flex-grow-1">{{ user.name }}</div>
                          </a>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>

              <div>
                <label class="form-label">Attached Files</label>
                <DropZone @drop.prevent="drop" @change="selectedFile" />
                <span class="file-info">File: {{ dropzoneFile.name }}</span>
              </div>
            </BCardBody>
          </BCard>
        </BCol>

        <BCol lg="4">
          <BCard>
            <BCardBody>
              <h5 class="card-title mb-3">Publish</h5>
              <div class="mb-3">
                <label for="project-status-input" class="form-label">Status</label>
                <select class="form-select" id="project-status-input">
                  <option value="Completed">Completed</option>
                  <option value="Inprogress" selected>Inprogress</option>
                  <option value="Delay">Delay</option>
                </select>
                <div class="invalid-feedback">Please select project status.</div>
              </div>

              <div>
                <label for="project-visibility-input" class="form-label">Visibility</label>
                <select class="form-select" id="project-visibility-input">
                  <option value="Private">Private</option>
                  <option value="Public">Public</option>
                  <option value="Team">Team</option>
                </select>
              </div>
            </BCardBody>
          </BCard>

          <BCard>
            <BCardBody>
              <h5 class="card-title mb-3">Due Date</h5>
              <input type="text" id="duedate-input" class="form-control" placeholder="Select due date"
                data-date-format="dd M, yyyy" data-provide="datepicker" data-date-autoclose="true" required />
              <div class="invalid-feedback">Please select due date.</div>
            </BCardBody>
          </BCard>
        </BCol>

        <BCol lg="8">
          <div class="text-end mb-4">
            <BButton type="submit" variant="primary">Create Project</BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>

<script>
import { ref } from "vue";
import useVuelidate from "@vuelidate/core";
import DropZone from "@/components/widgets/dropZone.vue";
import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";

export default {
  components: {
    DropZone,
    Layout,
    PageHeader,
  },
  setup() {
    let dropzoneFile = ref("");
    const drop = (e) => {
      dropzoneFile.value = e.dataTransfer.files[0];
    };
    const selectedFile = () => {
      dropzoneFile.value = document.querySelector(".dropzoneFile").files[0];
    };
    const users = ref([
      { name: "Tommie Metzler", avatar: "@/assets/images/users/avatar-2.jpg" },
      { name: "Paul Barone", avatar: "@/assets/images/users/avatar-3.jpg" },
      { name: "Chris Lucas", avatar: "@/assets/images/users/avatar-4.jpg" },
      { name: "Shirley North", avatar: "@/assets/images/users/avatar-1.jpg" },
      { name: "Patricia Pierce", avatar: "@/assets/images/users/avatar-5.jpg" },
      { name: "William Max", avatar: "@/assets/images/users/avatar-6.jpg" },
      { name: "Johnnie Walton", avatar: "@/assets/images/users/avatar-7.jpg" },
      { name: "Miriam Crum", avatar: "@/assets/images/users/avatar-8.jpg" },
    ]);

    return { dropzoneFile, drop, selectedFile, users, v$: useVuelidate() };
  },
};
</script>
