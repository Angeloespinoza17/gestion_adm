<template>
  <Layout>
    <PageHeader title="TUI Calendar" pageTitle="Calendar" />

    <BRow>
      <div class="col-12">
        <BRow>
          <!-- Sidebar -->
          <div class="col-xl-3">
            <div class="card">
              <div class="card-body">
                <div class="d-flex gap-2">
                  <div class="flex-grow-1">
                    <select id="locale-selector" class="form-select" v-model="selectedLocale" @change="changeLocale">
                      <option v-for="locale in availableLocales" :key="locale" :value="locale">
                        {{ locale }}
                      </option>
                    </select>
                  </div>
                  <button class="btn font-16 btn-primary" @click="openNewEventModal">
                    <i class="mdi mdi-plus-circle-outline"></i> Create New Event
                  </button>
                </div>

                <div id="external-events" class="mt-2">
                  <br>
                  <p class="text-muted">Drag and drop your event or click in the calendar</p>
                  <div v-for="event in externalEvents" :key="event.title"
                    :class="`external-event fc-event ${event.className}`" :data-class="event.className"
                    draggable="true">
                    <i class="mdi mdi-checkbox-blank-circle font-size-11 me-2"></i>{{ event.title }}
                  </div>
                </div>

                <div class="row justify-content-center mt-5">
                  <img src="../../../images/verification-img.png" alt="" class="img-fluid d-block">
                </div>
              </div>
            </div>
          </div>

          <!-- Calendar -->
          <div class="col-xl-9">
            <div class="card">
              <div class="card-body">
                <div ref="calendarEl"></div>
              </div>
            </div>
          </div>
        </BRow>

        <div style="clear:both"></div>

        <!-- Event Modal -->
        <div class="modal fade" :class="{ show: showModal }" :style="{ display: showModal ? 'block' : 'none' }"
          tabindex="-1">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header py-3 px-4 border-bottom-0">
                <h5 class="modal-title">{{ modalTitle }}</h5>
                <button type="button" class="btn-close" @click="closeModal" aria-hidden="true"></button>
              </div>
              <div class="modal-body p-4">
                <form @submit.prevent="saveEvent" :class="{ 'was-validated': formValidated }">
                  <div class="row">
                    <div class="col-12">
                      <div class="mb-3">
                        <label class="form-label">Event Name</label>
                        <input class="form-control" placeholder="Insert Event Name" type="text"
                          v-model="eventForm.title" required />
                        <div class="invalid-feedback">Please provide a valid event name</div>
                      </div>
                    </div>
                    <div class="col-12">
                      <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-control form-select" v-model="eventForm.category" required>
                          <option value=""> --Select-- </option>
                          <option value="bg-danger">Danger</option>
                          <option value="bg-success">Success</option>
                          <option value="bg-primary">Primary</option>
                          <option value="bg-info">Info</option>
                          <option value="bg-dark">Dark</option>
                          <option value="bg-warning">Warning</option>
                        </select>
                        <div class="invalid-feedback">Please select a valid event category</div>
                      </div>
                    </div>
                  </div>
                  <div class="row mt-2">
                    <div class="col-6">
                      <button type="button" class="btn btn-danger" @click="deleteEvent" v-show="selectedEvent">
                        Delete
                      </button>
                    </div>
                    <div class="col-6 text-end">
                      <button type="button" class="btn btn-light me-1" @click="closeModal">Close</button>
                      <button type="submit" class="btn btn-success">Save</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal backdrop -->
        <div v-if="showModal" class="modal-backdrop fade show" @click="closeModal"></div>
      </div>
    </BRow>
  </Layout>
</template>

<script>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import { Calendar } from '@fullcalendar/core'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import listPlugin from '@fullcalendar/list'
import interactionPlugin, { Draggable } from '@fullcalendar/interaction'
import bootstrapPlugin from '@fullcalendar/bootstrap5'
import Layout from "../../layouts/main.vue"
import PageHeader from "@/components/page-header.vue"

export default {
  setup() {

    // Reactive data
    const calendarEl = ref(null)
    const calendar = ref(null)
    const showModal = ref(false)
    const modalTitle = ref('Event')
    const selectedEvent = ref(null)
    const newEventData = ref(null)
    const formValidated = ref(false)
    const selectedLocale = ref('en')
    const availableLocales = ref(['en'])

    const eventForm = reactive({
      title: '',
      category: ''
    })

    const externalEvents = ref([
      { title: 'New Event Planning', className: 'bg-success' },
      { title: 'Meeting', className: 'bg-info' },
      { title: 'Generating Reports', className: 'bg-warning' },
      { title: 'Create New theme', className: 'bg-danger' }
    ])

    const defaultEvents = ref([
      {
        title: 'All Day Event',
        start: new Date(new Date().getFullYear(), new Date().getMonth(), 1)
      },
      {
        title: 'Long Event',
        start: new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate() - 5),
        end: new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate() - 2),
        className: 'bg-warning'
      },
      {
        id: 999,
        title: 'Repeating Event',
        start: new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate() - 3, 16, 0),
        allDay: false,
        className: 'bg-info'
      },
      {
        id: 999,
        title: 'Repeating Event',
        start: new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate() + 4, 16, 0),
        allDay: false,
        className: 'bg-primary'
      },
      {
        title: 'Meeting',
        start: new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate(), 10, 30),
        allDay: false,
        className: 'bg-success'
      },
      {
        title: 'Lunch',
        start: new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate(), 12, 0),
        end: new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate(), 14, 0),
        allDay: false,
        className: 'bg-danger'
      },
      {
        title: 'Birthday Party',
        start: new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate() + 1, 19, 0),
        end: new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate() + 1, 22, 30),
        allDay: false,
        className: 'bg-success'
      },
      {
        title: 'Click for Google',
        start: new Date(new Date().getFullYear(), new Date().getMonth(), 28),
        end: new Date(new Date().getFullYear(), new Date().getMonth(), 29),
        url: 'http://google.com/',
        className: 'bg-dark'
      }
    ])

    // Methods
    const initCalendar = () => {
      const externalEventContainerEl = document.getElementById('external-events')

      // Initialize draggable
      new Draggable(externalEventContainerEl, {
        itemSelector: '.external-event',
        eventData: function (eventEl) {
          return {
            title: eventEl.innerText.trim(),
            className: eventEl.getAttribute('data-class')
          }
        }
      })

      calendar.value = new Calendar(calendarEl.value, {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin, bootstrapPlugin],
        editable: true,
        droppable: true,
        selectable: true,
        initialView: 'dayGridMonth',
        themeSystem: 'bootstrap5',
        weekNumbers: true,
        locale: selectedLocale.value,
        headerToolbar: {
          left: 'prev,next today',
          right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth',
          center: 'title',
        },
        dayMaxEventRows: true,
        views: {
          timeGrid: {
            dayMaxEventRows: 5
          }
        },
        eventClick: function (info) {
          openEditEventModal(info.event)
        },
        dateClick: function (info) {
          openNewEventModal(info)
        },
        events: defaultEvents.value
      })

      calendar.value.render()

      // Get available locales
      availableLocales.value = calendar.value.getAvailableLocaleCodes()
    }

    const openNewEventModal = (info = null) => {
      showModal.value = true
      modalTitle.value = 'Add Event'
      selectedEvent.value = null
      newEventData.value = info || { date: new Date(), allDay: true }
      resetForm()
    }

    const openEditEventModal = (event) => {
      showModal.value = true
      modalTitle.value = 'Edit Event'
      selectedEvent.value = event
      newEventData.value = null
      eventForm.title = event.title
      eventForm.category = event.classNames[0] || ''
      formValidated.value = false
    }

    const closeModal = () => {
      showModal.value = false
      resetForm()
    }

    const resetForm = () => {
      eventForm.title = ''
      eventForm.category = ''
      formValidated.value = false
    }

    const saveEvent = () => {
      if (!eventForm.title.trim() || !eventForm.category) {
        formValidated.value = true
        return
      }

      if (selectedEvent.value) {
        // Update existing event
        selectedEvent.value.setProp('title', eventForm.title)
        selectedEvent.value.setProp('classNames', [eventForm.category])
      } else {
        // Add new event
        const newEvent = {
          title: eventForm.title,
          start: newEventData.value.date,
          allDay: newEventData.value.allDay,
          className: eventForm.category
        }
        calendar.value.addEvent(newEvent)
      }

      closeModal()
    }

    const deleteEvent = () => {
      if (selectedEvent.value) {
        selectedEvent.value.remove()
        selectedEvent.value = null
        closeModal()
      }
    }

    const changeLocale = () => {
      if (calendar.value && selectedLocale.value) {
        calendar.value.setOption('locale', selectedLocale.value)
      }
    }

    // Lifecycle
    onMounted(() => {
      initCalendar()
    })

    onUnmounted(() => {
      if (calendar.value) {
        calendar.value.destroy()
      }
    })

    return {
      calendarEl,
      showModal,
      modalTitle,
      selectedEvent,
      eventForm,
      formValidated,
      selectedLocale,
      availableLocales,
      externalEvents,
      openNewEventModal,
      closeModal,
      saveEvent,
      deleteEvent,
      changeLocale
    }
  },
  components: {
    Layout,
    PageHeader
  }
}
</script>
