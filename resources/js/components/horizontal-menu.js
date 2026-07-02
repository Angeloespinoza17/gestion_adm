export const menuItems = [
    {
        id: 1,
        label: 'menuitems.dashboards.text',
        icon: 'bx-home-circle',
        subItems: [
            {
                id: 2,
                label: 'menuitems.dashboards.list.default',
                link: '/inicio',
                parentId: 1
            },
            {
                id: 3,
                label: 'menuitems.dashboards.list.saas',
                link: '/dashboard/saas',
                parentId: 1
            },
            {
                id: 131,
                label: 'test',
                link: '/test',
                parentId: 1
            },
            {
                id: 132,
                label: 'Deploy',
                link: '/deploy',
                parentId: 1
            },
            {
                id: 4,
                label: 'menuitems.dashboards.list.crypto',
                link: '/dashboard/crypto',
                parentId: 1
            },
            {
                id: 5,
                label: 'menuitems.dashboards.list.blog',
                link: '/dashboard/blog',
                parentId: 1
            },
            {
                id: 6,
                label: "menuitems.dashboards.list.jobs.text",
                link: "/dashboard/job",
                parentId: 2,
            }
        ]
    },
    {
        id: 900,
        label: 'Mantención',
        icon: 'bx-wrench',
        subItems: [
            {
                id: 901,
                label: 'Dependencias',
                link: '/maintenance/dependencies',
                parentId: 900
            },
            {
                id: 902,
                label: 'Órdenes de trabajo',
                link: '/maintenance/work-orders',
                parentId: 900
            },
            {
                id: 903,
                label: 'Carga de trabajo',
                link: '/maintenance/workload',
                parentId: 900
            },
            {
                id: 904,
                label: 'Planificación visitas',
                link: '/maintenance/visits',
                parentId: 900
            },
            {
                id: 905,
                label: 'Plan anual mantención',
                link: '/maintenance/annual-plans',
                parentId: 900
            }
        ]
    },
    {
        id: 906,
        label: 'Control de Nochero',
        icon: 'bx-shield-quarter',
        subItems: [
            {
                id: 9061,
                label: 'Panel de rondas',
                link: '/security/dashboard',
                parentId: 906
            },
            {
                id: 9062,
                label: 'Turnos y rondas',
                link: '/security/shifts',
                parentId: 906
            },
            {
                id: 9063,
                label: 'Novedades pendientes',
                link: '/security/incidents',
                parentId: 906
            }
        ]
    },
    {
        id: 9065,
        label: 'Prevención de Riesgos',
        icon: 'bx-shield-alt-2',
        subItems: [
            {
                id: 90651,
                label: 'Dashboard',
                link: '/risk-prevention',
                parentId: 9065
            },
            {
                id: 90652,
                label: 'Extintores',
                link: '/risk-prevention/extinguishers',
                parentId: 9065
            },
            {
                id: 90653,
                label: 'Accidentes',
                link: '/risk-prevention/accidents',
                parentId: 9065
            },
            {
                id: 90654,
                label: 'Emergencias y planes',
                link: '/risk-prevention/emergencies',
                parentId: 9065
            },
            {
                id: 90655,
                label: 'EPP y seguridad',
                link: '/risk-prevention/epp',
                parentId: 9065
            },
            {
                id: 90656,
                label: 'Capacitaciones',
                link: '/risk-prevention/trainings',
                parentId: 9065
            },
            {
                id: 90657,
                label: 'Centro de documentos',
                link: '/risk-prevention/documents',
                parentId: 9065
            },
            {
                id: 90658,
                label: 'Reportes',
                link: '/risk-prevention/reports',
                parentId: 9065
            }
        ]
    },
    {
        id: 9070,
        label: 'Portería',
        icon: 'bx-door-open',
        subItems: [
            {
                id: 9071,
                label: 'Panel de portería',
                link: '/porter/dashboard',
                parentId: 9070
            },
            {
                id: 9072,
                label: 'Buscar estudiante',
                link: '/porter/students',
                parentId: 9070
            },
            {
                id: 9073,
                label: 'Retiros',
                link: '/porter/withdrawals',
                parentId: 9070
            },
            {
                id: 9074,
                label: 'Recepción de objetos',
                link: '/porter/received-items',
                parentId: 9070
            },
            {
                id: 9075,
                label: 'Mercadería',
                link: '/porter/goods',
                parentId: 9070
            },
            {
                id: 90751,
                label: 'Control de visitas',
                link: '/porter/visits',
                parentId: 9070
            },
            {
                id: 90752,
                label: 'Control de proveedores',
                link: '/porter/providers',
                parentId: 9070
            },
            {
                id: 90753,
                label: 'Bitácora diaria',
                link: '/porter/daily-log',
                parentId: 9070
            },
            {
                id: 90754,
                label: 'Control de llaves',
                link: '/porter/keys',
                parentId: 9070
            },
            {
                id: 9076,
                label: 'Reportes de portería',
                link: '/porter/reports',
                parentId: 9070
            }
        ]
    },
    {
        id: 910,
        label: 'Funcionarios',
        icon: 'bx-id-card',
        subItems: [
            {
                id: 911,
                label: 'Listado de funcionarios',
                link: '/staff',
                parentId: 910
            },
            {
                id: 912,
                label: 'Departamentos',
                link: '/staff/departments',
                parentId: 910
            }
        ]
    },
    {
        id: 9150,
        label: 'Permisos',
        icon: 'bx-calendar-minus',
        subItems: [
            {
                id: 913,
                label: 'Dashboard permisos',
                link: '/staff/permissions/dashboard',
                parentId: 9150
            },
            {
                id: 914,
                label: 'Mis permisos',
                link: '/staff/permissions',
                parentId: 9150
            },
            {
                id: 915,
                label: 'Bandeja de permisos',
                link: '/staff/permissions/review',
                parentId: 9150
            },
            {
                id: 916,
                label: 'Reportes de permisos',
                link: '/staff/permissions/reports',
                parentId: 9150
            },
            {
                id: 917,
                label: 'Tipos de permiso',
                link: '/staff/permissions/types',
                parentId: 9150
            },
            {
                id: 918,
                label: 'Quién debe enterarse',
                link: '/staff/permissions/watchers',
                parentId: 9150
            },
            {
                id: 919,
                label: 'Destinatarios por funcionario',
                link: '/staff/permissions/watchers-summary',
                parentId: 9150
            }
        ]
    },
    {
        id: 920,
        label: 'Contratos',
        icon: 'bx-file',
        subItems: [
            {
                id: 921,
                label: 'Listado de contratos',
                link: '/contracts',
                parentId: 920
            },
            {
                id: 922,
                label: 'Plantillas',
                link: '/contracts/templates',
                parentId: 920
            },
            {
                id: 923,
                label: 'Cláusulas',
                link: '/contracts/clauses',
                parentId: 920
            },
            {
                id: 924,
                label: 'Firmas',
                link: '/contracts/signatures',
                parentId: 920
            }
        ]
    },
    {
        id: 9240,
        label: 'Equipo de Apoyo',
        icon: 'bx-heart-circle',
        subItems: [
            {
                id: 9241,
                label: 'Dashboard',
                link: '/apoyo-profesional',
                parentId: 9240
            },
            {
                id: 9242,
                label: 'Atenciones',
                link: '/apoyo-profesional/atenciones',
                parentId: 9240
            },
            {
                id: 9243,
                label: 'Ficha estudiante',
                link: '/apoyo-profesional/historial',
                parentId: 9240
            },
            {
                id: 9244,
                label: 'Derivaciones',
                link: '/apoyo-profesional/derivaciones',
                parentId: 9240
            },
            {
                id: 9245,
                label: 'Seguimientos',
                link: '/apoyo-profesional/seguimientos',
                parentId: 9240
            },
            {
                id: 9246,
                label: 'Planes de apoyo',
                link: '/apoyo-profesional/planes',
                parentId: 9240
            },
            {
                id: 9247,
                label: 'Entrevistas',
                link: '/apoyo-profesional/entrevistas',
                parentId: 9240
            },
            {
                id: 9248,
                label: 'Documentos',
                link: '/apoyo-profesional/documentos',
                parentId: 9240
            },
            {
                id: 9249,
                label: 'Reportes',
                link: '/apoyo-profesional/reportes',
                parentId: 9240
            }
        ]
    },
    {
        id: 925,
        label: 'Calendario y Fechas Relevantes',
        icon: 'bx-calendar-event',
        subItems: [
            {
                id: 9251,
                label: 'Calendario',
                link: '/relevant-calendar',
                parentId: 925
            },
            {
                id: 9252,
                label: 'Tipos de procesos',
                link: '/relevant-calendar/process-types',
                parentId: 925
            },
            {
                id: 9253,
                label: 'Instituciones',
                link: '/relevant-calendar/institutions',
                parentId: 925
            }
        ]
    },
    {
        id: 940,
        label: 'Configuración',
        icon: 'bx-cog',
        subItems: [
            {
                id: 941,
                label: 'Usuarios',
                link: '/admin/users',
                parentId: 940
            },
            {
                id: 942,
                label: 'Roles',
                link: '/admin/roles',
                parentId: 940
            },
            {
                id: 943,
                label: 'Permisos',
                link: '/admin/permissions',
                parentId: 940
            },
            {
                id: 944,
                label: 'Módulos',
                link: '/admin/modules',
                parentId: 940
            },
            {
                id: 945,
                label: 'Cargos',
                link: '/admin/cargos',
                parentId: 940
            },
            {
                id: 946,
                label: 'Organigrama',
                link: '/admin/organigram',
                parentId: 940
            }
        ]
    },
    {
        id: 6,
        label: 'menuitems.uielements.text',
        icon: 'bx-tone',
        isUiElement: true,
        subItems: [
            {
                id: 7,
                label: 'menuitems.uielements.list.alerts',
                link: '/ui/alerts',
                parentId: 6
            },
            {
                id: 8,
                label: 'menuitems.uielements.list.buttons',
                link: '/ui/buttons',
                parentId: 6
            },
            {
                id: 9,
                label: 'menuitems.uielements.list.cards',
                link: '/ui/cards',
                parentId: 6
            },
            {
                id: 10,
                label: 'menuitems.uielements.list.carousel',
                link: '/ui/carousel',
                parentId: 6
            },
            {
                id: 11,
                label: 'menuitems.uielements.list.dropdowns',
                link: '/ui/dropdowns',
                parentId: 6
            },
            {
                id: 12,
                label: 'menuitems.uielements.list.grid',
                link: '/ui/grid',
                parentId: 6
            },
            {
                id: 13,
                label: 'menuitems.uielements.list.images',
                link: '/ui/images',
                parentId: 6
            },
            {
                id: 14,
                label: 'menuitems.uielements.list.modals',
                link: '/ui/modals',
                parentId: 6
            },
            {
                id: 14,
                label: "menuitems.uielements.list.offcanvas",
                link: "/ui/offcanvas",
                parentId: 6
            },
            {
                id: 15,
                label: 'menuitems.uielements.list.rangeslider',
                link: '/ui/rangeslider',
                parentId: 6
            },
            {
                id: 126,
                label: 'menuitems.uielements.list.session-timeout',
                link: '/ui/session-timeout',
                parentId: 6
            },
            {
                id: 16,
                label: 'menuitems.uielements.list.progressbar',
                link: '/ui/progressbars',
                parentId: 6
            },
            {
                id: 16,
                label: 'menuitems.uielements.list.placeholder',
                link: '/ui/placeholder',
                parentId: 6
            },
            {
                id: 17,
                label: 'menuitems.uielements.list.sweetalert',
                link: '/ui/sweet-alert',
                parentId: 6
            },
            {
                id: 18,
                label: 'menuitems.uielements.list.tabs',
                link: '/ui/tabs-accordions',
                parentId: 6
            },
            {
                id: 19,
                label: 'menuitems.uielements.list.typography',
                link: '/ui/typography',
                parentId: 6
            },
            {
                id: 20,
                label: 'menuitems.uielements.list.video',
                link: '/ui/video',
                parentId: 6
            },
            {
                id: 21,
                label: 'menuitems.uielements.list.general',
                link: '/ui/general',
                parentId: 6
            },
            {
                id: 22,
                label: 'menuitems.uielements.list.colors',
                link: '/ui/colors',
                parentId: 6
            },
            {
                id: 23,
                label: "menuitems.uielements.list.lightbox",
                link: "/ui/lightbox",
                parentId: 6
            },
            {
                id: 24,
                label: "menuitems.uielements.list.cropper",
                link: "/ui/image-cropper",
                parentId: 6
            },
            {
                id: 25,
                label: "menuitems.uielements.list.notifications.text",
                link: "/ui/notifications",
                parentId: 6
            },
            {
                id: 25,
                label: "menuitems.uielements.list.utilities.text",
                link: "/ui/utilities",
                parentId: 61
            }
        ]
    },
    {
        id: 25,
        label: 'menuitems.apps.text',
        icon: 'bx-customize',
        subItems: [
            {
                id: 26,
                label: 'menuitems.calendar.text',
                link: '/tui-calendar',
                subItems: [
                    {
                        id: 124,
                        label: "menuitems.calendar.list.tui-calendar",
                        link: "/calendar/tui-calendar",
                        parentId: 26
                    },
                    {
                        id: 125,
                        label: "menuitems.calendar.list.full-calendar",
                        link: "/calendar/full-calendar",
                        parentId: 26
                    },
                ]
            },
            {
                id: 27,
                label: 'menuitems.chat.text',
                link: '/chat',
                parentId: 25
            },
            {
                id: 28,
                label: "menuitems.filemanager.text",
                link: "/file-manager",
                parentId: 25
            },
            {
                id: 29,
                label: 'menuitems.email.text',
                subItems: [
                    {
                        id: 30,
                        label: 'menuitems.email.list.inbox',
                        link: '/email/inbox',
                        parentId: 29
                    },
                    {
                        id: 31,
                        label: 'menuitems.email.list.reademail',
                        link: '/email/reademail/1',
                        parentId: 29
                    },
                    {
                        id: 32,
                        label: "menuitems.email.list.template.text",
                        parentId: 29,
                        subItems: [
                            {
                                id: 33,
                                label: 'menuitems.email.list.template.list.basic',
                                link: '/email/templates/basic',
                                parentId: 32
                            },
                            {
                                id: 34,
                                label: 'menuitems.email.list.template.list.alert',
                                link: '/email/templates/alert',
                                parentId: 32
                            },
                            {
                                id: 35,
                                label: 'menuitems.email.list.template.list.billing',
                                link: '/email/templates/billing',
                                parentId: 32
                            }
                        ]
                    }
                ]
            },
            {
                id: 36,
                label: 'menuitems.ecommerce.text',
                subItems: [
                    {
                        id: 37,
                        label: 'menuitems.ecommerce.list.products',
                        link: '/ecommerce/products',
                        parentId: 36
                    },
                    {
                        id: 38,
                        label: 'menuitems.ecommerce.list.productdetail',
                        link: '/ecommerce/product-detail',
                        parentId: 36
                    },
                    {
                        id: 39,
                        label: 'menuitems.ecommerce.list.orders',
                        link: '/ecommerce/orders',
                        parentId: 36
                    },
                    {
                        id: 40,
                        label: 'menuitems.ecommerce.list.customers',
                        link: '/ecommerce/customers',
                        parentId: 36
                    },
                    {
                        id: 41,
                        label: 'menuitems.ecommerce.list.cart',
                        link: '/ecommerce/cart',
                        parentId: 36
                    },
                    {
                        id: 42,
                        label: 'menuitems.ecommerce.list.checkout',
                        link: '/ecommerce/checkout',
                        parentId: 36
                    },
                    {
                        id: 43,
                        label: 'menuitems.ecommerce.list.shops',
                        link: '/ecommerce/shops',
                        parentId: 36
                    },
                    {
                        id: 44,
                        label: 'menuitems.ecommerce.list.addproduct',
                        link: '/ecommerce/add-product',
                        parentId: 36
                    },
                ]
            },
            {
                id: 45,
                label: 'menuitems.crypto.text',
                icon: 'bx-bitcoin',
                subItems: [
                    {
                        id: 46,
                        label: 'menuitems.crypto.list.wallet',
                        link: '/crypto/wallet',
                        parentId: 45
                    },
                    {
                        id: 47,
                        label: 'menuitems.crypto.list.buy/sell',
                        link: '/crypto/buy-sell',
                        parentId: 45
                    },
                    {
                        id: 48,
                        label: 'menuitems.crypto.list.exchange',
                        link: '/crypto/exchange',
                        parentId: 45
                    },
                    {
                        id: 49,
                        label: 'menuitems.crypto.list.lending',
                        link: '/crypto/lending',
                        parentId: 45
                    },
                    {
                        id: 50,
                        label: 'menuitems.crypto.list.orders',
                        link: '/crypto/orders',
                        parentId: 45
                    },
                    {
                        id: 51,
                        label: 'menuitems.crypto.list.kycapplication',
                        link: '/crypto/kyc-application',
                        parentId: 45
                    },
                    {
                        id: 52,
                        label: 'menuitems.crypto.list.icolanding',
                        link: '/crypto/ico-landing',
                        parentId: 45
                    }
                ]
            },
            {
                id: 53,
                label: 'menuitems.projects.text',
                subItems: [
                    {
                        id: 54,
                        label: 'menuitems.projects.list.grid',
                        link: '/projects/grid',
                        parentId: 53
                    },
                    {
                        id: 55,
                        label: 'menuitems.projects.list.projectlist',
                        link: '/projects/list',
                        parentId: 53
                    },
                    {
                        id: 56,
                        label: 'menuitems.projects.list.overview',
                        link: '/projects/overview',
                        parentId: 53
                    },
                    {
                        id: 57,
                        label: 'menuitems.projects.list.create',
                        link: '/projects/create',
                        parentId: 53
                    }
                ]
            },
            {
                id: 58,
                label: 'menuitems.tasks.text',
                subItems: [
                    {
                        id: 59,
                        label: 'menuitems.tasks.list.tasklist',
                        link: '/tasks/list',
                        parentId: 58
                    },
                    {
                        id: 60,
                        label: 'menuitems.tasks.list.kanban',
                        link: '/tasks/kanban',
                        parentId: 58
                    },
                    {
                        id: 61,
                        label: 'menuitems.tasks.list.createtask',
                        link: '/tasks/create',
                        parentId: 58
                    }
                ]
            },
            {
                id: 62,
                label: 'menuitems.contacts.text',
                icon: 'bxs-user-detail',
                subItems: [
                    {
                        id: 63,
                        label: 'menuitems.contacts.list.usergrid',
                        link: '/contacts/grid',
                        parentId: 62
                    },
                    {
                        id: 64,
                        label: 'menuitems.contacts.list.userlist',
                        link: '/contacts/list',
                        parentId: 61
                    },
                    {
                        id: 65,
                        label: 'menuitems.contacts.list.profile',
                        link: '/contacts/profile',
                        parentId: 61
                    }
                ]
            },
            {
                id: 66,
                label: "menuitems.blog.text",
                icon: "bx-detail",
                subItems: [
                    {
                        id: 67,
                        label: 'menuitems.blog.list.bloglist',
                        link: '/blog/list',
                        parentId: 66
                    },
                    {
                        id: 68,
                        label: 'menuitems.blog.list.grid',
                        link: '/blog/grid',
                        parentId: 66
                    },
                    {
                        id: 69,
                        label: 'menuitems.blog.list.detail',
                        link: '/blog/detail',
                        parentId: 66
                    }
                ]
            },
            {
                id: 111,
                label: "menuitems.jobs.text",
                icon: "bx-briefcase-alt",
                badge: {
                    variant: "success",
                    text: "menuitems.jobs.badge"
                },
                subItems: [
                    {
                        id: 112,
                        label: 'menuitems.jobs.list.job-list',
                        link: '/jobs/list',
                        parentId: 111
                    },
                    {
                        id: 113,
                        label: 'menuitems.jobs.list.job-grid',
                        link: '/jobs/grid',
                        parentId: 111
                    },
                    {
                        id: 114,
                        label: 'menuitems.jobs.list.job-apply',
                        link: '/jobs/apply',
                        parentId: 111
                    },
                    {
                        id: 115,
                        label: 'menuitems.jobs.list.job-details',
                        link: '/jobs/details',
                        parentId: 111
                    },
                    {
                        id: 115,
                        label: 'menuitems.jobs.list.job-categories',
                        link: '/jobs/categories',
                        parentId: 111,
                    },
                    {
                        id: 116,
                        label: 'menuitems.jobs.list.candidate.text',
                        parentId: 111,
                        subItems: [
                            {
                                id: 117,
                                label: 'menuitems.jobs.list.candidate.list',
                                link: '/jobs/candidate/list',
                                parentId: 116
                            },
                            {
                                id: 118,
                                label: 'menuitems.jobs.list.candidate.overview',
                                link: '/jobs/candidate/overview',
                                parentId: 116
                            },
                        ]
                    },
                ]
            },
        ]
    },
    {
        id: 70,
        icon: 'bx-collection',
        label: 'menuitems.components.text',
        subItems: [
            {
                id: 71,
                label: 'menuitems.forms.text',
                subItems: [
                    {
                        id: 72,
                        label: 'menuitems.forms.list.elements',
                        link: '/form/elements',
                        parentId: 71
                    },
                    {
                        id: 73,
                        label: 'menuitems.forms.list.layouts',
                        link: '/form/layouts',
                        parentId: 71
                    },
                    {
                        id: 75,
                        label: 'menuitems.forms.list.advanced',
                        link: '/form/advanced',
                        parentId: 71
                    },
                    {
                        id: 76,
                        label: 'menuitems.forms.list.editor',
                        link: '/form/editor',
                        parentId: 71
                    },
                    {
                        id: 77,
                        label: 'menuitems.forms.list.fileupload',
                        link: '/form/uploads',
                        parentId: 71
                    },
                    {
                        id: 78,
                        label: 'menuitems.forms.list.repeater',
                        link: '/form/repeater',
                        parentId: 71
                    },
                    {
                        id: 79,
                        label: 'menuitems.forms.list.wizard',
                        link: '/form/wizard',
                        parentId: 71
                    },
                    {
                        id: 80,
                        label: 'menuitems.forms.list.mask',
                        link: '/form/mask',
                        parentId: 71
                    }
                ]
            },
            {
                id: 81,
                label: 'menuitems.tables.text',
                subItems: [
                    {
                        id: 82,
                        label: 'menuitems.tables.list.basic',
                        link: '/tables/basic',
                        parentId: 81
                    },
                ]
            },
            {
                id: 84,
                label: 'menuitems.charts.text',
                subItems: [
                    {
                        id: 85,
                        label: 'menuitems.charts.list.apex',
                        link: '/charts/apex',
                        parentId: 84
                    },
                    {
                        id: 86,
                        label: 'menuitems.charts.list.chartjs',
                        link: '/charts/chartjs',
                        parentId: 84
                    },
                    {
                        id: 87,
                        label: 'menuitems.charts.list.chartist',
                        link: '/charts/chartist',
                        parentId: 84
                    }
                ]
            },
            {
                id: 89,
                label: 'menuitems.icons.text',
                subItems: [
                    {
                        id: 90,
                        label: 'menuitems.icons.list.boxicons',
                        link: '/icons/boxicons',
                        parentId: 89
                    },
                    {
                        id: 91,
                        label: 'menuitems.icons.list.materialdesign',
                        link: '/icons/materialdesign',
                        parentId: 89
                    },
                    {
                        id: 92,
                        label: 'menuitems.icons.list.dripicons',
                        link: '/icons/dripicons',
                        parentId: 89
                    },
                    {
                        id: 93,
                        label: 'menuitems.icons.list.fontawesome',
                        link: '/icons/fontawesome',
                        parentId: 89
                    },
                ]
            },
            {
                id: 94,
                label: 'menuitems.maps.text',
                subItems: [
                    {
                        id: 95,
                        label: 'menuitems.maps.list.googlemap',
                        link: '/maps/google',
                        parentId: 94
                    },
                    {
                        id: 96,
                        label: "menuitems.maps.list.leafletmap",
                        link: "/maps/leaflet",
                        parentId: 94
                    },
                ]
            }
        ]
    },
    {
        id: 97,
        label: 'navbar.dropdown.megamenu.extrapages.title',
        icon: 'bx-file',
        subItems: [
            {
                id: 98,
                label: 'menuitems.invoices.text',
                subItems: [
                    {
                        id: 99,
                        label: 'menuitems.invoices.list.invoicelist',
                        link: '/invoices/list',
                        parentId: 98
                    },
                    {
                        id: 100,
                        label: 'menuitems.invoices.list.invoicedetail',
                        link: '/invoices/detail',
                        parentId: 98
                    },
                ]
            },
            {
                id: 101,
                label: 'menuitems.authentication.text',
                subItems: [
                    {
                        id: 102,
                        label: "menuitems.authentication.list.login",
                        link: "/auth/login-1",
                        parentId: 101
                    },
                    {
                        id: 103,
                        label: "menuitems.authentication.list.login-2",
                        link: "/auth/login-2",
                        parentId: 101
                    },
                    {
                        id: 104,
                        label: "menuitems.authentication.list.register",
                        link: "/auth/register-1",
                        parentId: 101
                    },
                    {
                        id: 105,
                        label: "menuitems.authentication.list.register-2",
                        link: "/auth/register-2",
                        parentId: 101
                    },
                    {
                        id: 106,
                        label: "menuitems.authentication.list.recoverpwd",
                        link: "/auth/recoverpw",
                        parentId: 101
                    },
                    {
                        id: 107,
                        label: "menuitems.authentication.list.recoverpwd-2",
                        link: "/auth/recoverpwd-2",
                        parentId: 101
                    },
                    {
                        id: 108,
                        label: "menuitems.authentication.list.lockscreen",
                        link: "/auth/lock-screen",
                        parentId: 101
                    },
                    {
                        id: 109,
                        label: "menuitems.authentication.list.lockscreen-2",
                        link: "/auth/lock-screen-2",
                        parentId: 101
                    },
                    {
                        id: 110,
                        label: "menuitems.authentication.list.confirm-mail",
                        link: "/auth/comfirm-mail",
                        parentId: 101
                    },
                    {
                        id: 111,
                        label: "menuitems.authentication.list.confirm-mail-2",
                        link: "/auth/comfirm-mail-2",
                        parentId: 101
                    },
                    {
                        id: 112,
                        label: "menuitems.authentication.list.verification",
                        link: "/auth/email-verification",
                        parentId: 101
                    },
                    {
                        id: 113,
                        label: "menuitems.authentication.list.verification-2",
                        link: "/auth/email-verification-2",
                        parentId: 101
                    },
                    {
                        id: 114,
                        label: "menuitems.authentication.list.verification-step",
                        link: "/auth/two-step-verification",
                        parentId: 101
                    },
                    {
                        id: 115,
                        label: "menuitems.authentication.list.verification-step-2",
                        link: "/auth/two-step-verification-2",
                        parentId: 101
                    }
                ]
            },
            {
                id: 116,
                label: 'menuitems.utility.text',
                icon: 'bx-file',
                subItems: [
                    {
                        id: 117,
                        label: 'menuitems.utility.list.starter',
                        link: '/pages/starter',
                        parentId: 116
                    },
                    {
                        id: 118,
                        label: 'menuitems.utility.list.maintenance',
                        link: '/pages/maintenance',
                        parentId: 116
                    },
                    {
                        id: 118,
                        label: "menuitems.utility.list.comingsoon",
                        link: "/pages/coming-soon",
                        parentId: 116
                    },
                    {
                        id: 119,
                        label: 'menuitems.utility.list.timeline',
                        link: '/pages/timeline',
                        parentId: 116
                    },
                    {
                        id: 120,
                        label: 'menuitems.utility.list.faqs',
                        link: '/pages/faqs',
                        parentId: 116
                    },
                    {
                        id: 121,
                        label: 'menuitems.utility.list.pricing',
                        link: '/pages/pricing',
                        parentId: 116
                    },
                    {
                        id: 122,
                        label: 'menuitems.utility.list.error404',
                        link: '/pages/404',
                        parentId: 116
                    },
                    {
                        id: 123,
                        label: 'menuitems.utility.list.error500',
                        link: '/pages/500',
                        parentId: 116
                    },
                ]
            },
        ]
    }
];

