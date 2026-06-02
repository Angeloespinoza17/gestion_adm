import { createRouter, createWebHistory } from 'vue-router'
import axios from 'axios'

const routes = [
    {
        path: '/',
        name: 'dashboard',
        meta: {
            title: 'Dashboard',
            authRequired: true,
        },
        component: () => import('../views/home.vue'),
    },
    {
        path: '/dashboard/saas',
        meta: { title: 'Saas', authRequired: true, },
        component: () => import('../views/dashboard/saas.vue'),
    },
    {
        path: '/dashboard/crypto',
        meta: { title: 'Crypto', authRequired: true, },
        component: () => import('../views/dashboard/crypto.vue'),
    },
    {
        path: '/dashboard/blog',
        meta: { title: 'Blog', authRequired: true, },
        component: () => import('../views/dashboard/blog.vue'),
    },
    {
        path: '/dashboard/job',
        meta: { authRequired: true, title: 'Job' },
        component: () => import('../views/dashboard/jobs/index.vue'),
    },
    {
        path: '/test',
        meta: { authRequired: true, title: 'Test' },
        component: () => import('../views/dashboard/test.vue'),
    },
    {
        path: '/deploy',
        meta: { authRequired: true, title: 'Deploy' },
        component: () => import('../views/dashboard/deploy.vue'),
    },
    {
        path: '/maintenance/dependencies',
        meta: { authRequired: true, title: 'Dependencias', permission: 'ver_mantencion' },
        component: () => import('../views/maintenance/dependencies.vue'),
    },
    {
        path: '/maintenance/work-orders',
        meta: { authRequired: true, title: 'Órdenes de trabajo', permission: 'ver_mantencion' },
        component: () => import('../views/maintenance/work-orders.vue'),
    },
    {
        path: '/maintenance/workload',
        meta: { authRequired: true, title: 'Carga de trabajo', permission: 'ver_reportes_mantencion' },
        component: () => import('../views/maintenance/workload.vue'),
    },
    {
        path: '/maintenance/visits',
        meta: { authRequired: true, title: 'Planificación de visitas', permission: 'ver_visitas_mantencion' },
        component: () => import('../views/maintenance/visits.vue'),
    },
    {
        path: '/maintenance/visits/:id/checklist',
        meta: { authRequired: true, title: 'Checklist de visita', permission: 'ver_visitas_mantencion' },
        component: () => import('../views/maintenance/visit-checklist.vue'),
    },
    {
        path: '/maintenance/annual-plans',
        meta: { authRequired: true, title: 'Plan anual de mantención', permission: 'ver_plan_anual_mantencion' },
        component: () => import('../views/maintenance/annual-plan.vue'),
    },
    {
        path: '/admin/users',
        meta: { authRequired: true, title: 'Usuarios', permission: 'administrar_usuarios' },
        component: () => import('../views/admin/users.vue'),
    },
    {
        path: '/admin/roles',
        meta: { authRequired: true, title: 'Roles', permission: 'administrar_roles' },
        component: () => import('../views/admin/roles.vue'),
    },
    {
        path: '/admin/permissions',
        meta: { authRequired: true, title: 'Permisos', permission: 'administrar_permisos' },
        component: () => import('../views/admin/permissions.vue'),
    },
    {
        path: '/admin/modules',
        meta: { authRequired: true, title: 'Módulos', permission: 'administrar_modulos' },
        component: () => import('../views/admin/modules.vue'),
    },
    {
        path: '/admin/cargos',
        meta: { authRequired: true, title: 'Cargos', permission: 'administrar_cargos' },
        component: () => import('../views/admin/cargos.vue'),
    },
    {
        path: '/inventory/items',
        meta: { authRequired: true, title: 'Inventario · Bienes', permission: 'ver_inventario' },
        component: () => import('../views/inventory/items.vue'),
    },
    {
        path: '/inventory/items/:id',
        meta: { authRequired: true, title: 'Inventario · Ficha', permission: 'ver_inventario' },
        component: () => import('../views/inventory/item-show.vue'),
    },
    {
        path: '/inventory/categories',
        meta: { authRequired: true, title: 'Inventario · Categorías', permission: 'administrar_categorias_inventario' },
        component: () => import('../views/inventory/categories.vue'),
    },
    {
        path: '/inventory/suppliers',
        meta: { authRequired: true, title: 'Inventario · Proveedores', permission: 'ver_inventario' },
        component: () => import('../views/inventory/suppliers.vue'),
    },
    {
        path: '/inventory/reports',
        meta: { authRequired: true, title: 'Inventario · Reportes', permission: 'ver_reportes_inventario' },
        component: () => import('../views/inventory/reports.vue'),
    },
    {
        path: '/inventory/labels',
        meta: { authRequired: true, title: 'Inventario · Etiquetas', permission: 'imprimir_etiquetas_inventario' },
        component: () => import('../views/inventory/labels.vue'),
    },
    {
        path: "/calendar/tui-calendar",
        name: "TUICalendar",
        meta: { title: "TUI Calendar", authRequired: true },
        component: () => import("../views/calendar/tui-calendar.vue")
    },
    {
        path: "/calendar/full-calendar",
        name: "Full Calendar",
        meta: { title: "Full Calendar", authRequired: true },
        component: () => import("../views/calendar/full-calendar.vue")
    },
    {
        path: '/chat',
        meta: { authRequired: true, title: 'Chat' },
        component: () => import('../views/chat/chat.vue')
    },
    {
        path: '/file-manager',
        meta: { authRequired: true, title: 'File Manager' },
        component: () => import('../views/file-manager/index.vue')
    },
    {
        path: '/ecommerce/products',
        meta: { authRequired: true, title: 'Products' },
        component: () => import('../views/ecommerce/products.vue')
    },
    {
        path: '/ecommerce/product-detail',
        meta: { authRequired: true, title: 'Product Detail' },
        component: () => import('../views/ecommerce/product-detail.vue')
    },
    {
        path: '/ecommerce/orders',
        meta: { authRequired: true, title: 'Orders' },
        component: () => import('../views/ecommerce/orders.vue')
    },
    {
        path: '/ecommerce/customers',
        meta: { authRequired: true, title: 'Customers' },
        component: () => import('../views/ecommerce/customers.vue')
    },
    {
        path: '/ecommerce/cart',
        meta: { authRequired: true, title: 'Cart' },
        component: () => import('../views/ecommerce/cart.vue')
    },
    {
        path: '/ecommerce/checkout',
        meta: { authRequired: true, title: 'Checkout' },
        component: () => import('../views/ecommerce/checkout.vue')
    },
    {
        path: '/ecommerce/shops',
        meta: { authRequired: true, title: 'Shops' },
        component: () => import('../views/ecommerce/shops.vue')
    },
    {
        path: '/ecommerce/add-product',
        meta: { authRequired: true, title: 'Add Product' },
        component: () => import('../views/ecommerce/add-product.vue')
    },
    {
        path: '/crypto/wallet',
        meta: { authRequired: true, title: 'Wallet' },
        component: () => import('../views/crypto/wallet.vue')
    },
    {
        path: '/crypto/buy-sell',
        meta: { authRequired: true, title: 'Buy/Sell' },
        component: () => import('../views/crypto/buy-sell.vue')
    },
    {
        path: '/crypto/exchange',
        meta: { authRequired: true, title: 'Exchange' },
        component: () => import('../views/crypto/exchange.vue')
    },
    {
        path: '/crypto/lending',
        meta: { authRequired: true, title: 'Lending' },
        component: () => import('../views/crypto/lending.vue')
    },
    {
        path: '/crypto/orders',
        meta: { authRequired: true, title: 'Orders' },
        component: () => import('../views/crypto/orders.vue')
    },
    {
        path: '/crypto/kyc-application',
        meta: { authRequired: true, title: 'KYC Application' },
        component: () => import('../views/crypto/kyc-application.vue')
    },
    {
        path: '/crypto/ico-landing',
        meta: { authRequired: true, title: 'ICO Landing' },
        component: () => import('../views/crypto/ico-landing.vue')
    },
    {
        path: '/email/inbox',
        meta: { authRequired: true, title: 'Inbox' },
        component: () => import('../views/email/inbox.vue')
    },
    {
        path: '/email/reademail/:id',
        meta: { authRequired: true, title: 'Read Email' },
        component: () => import('../views/email/reademail.vue')
    },
    {
        path: '/email/templates/basic',
        meta: { authRequired: true, title: 'Basic Template' },
        component: () => import('../views/email/templates/basic.vue')
    },
    {
        path: '/email/templates/alert',
        meta: { authRequired: true, title: 'Alert Template' },
        component: () => import('../views/email/templates/alert.vue')
    },
    {
        path: '/email/templates/billing',
        meta: { authRequired: true, title: 'Billing Template' },
        component: () => import('../views/email/templates/billing.vue')
    },
    {
        path: '/invoices/list',
        meta: { authRequired: true, title: 'Invoice List' },
        component: () => import('../views/invoices/list.vue')
    },
    {
        path: '/invoices/detail',
        meta: { authRequired: true, title: 'Invoice Detail' },
        component: () => import('../views/invoices/detail.vue')
    },
    {
        path: '/projects/grid',
        meta: { authRequired: true, title: 'Project Grid' },
        component: () => import('../views/projects/projects-grid.vue')
    },
    {
        path: '/projects/list',
        meta: { authRequired: true, title: 'Project List' },
        component: () => import('../views/projects/projects-list.vue')
    },
    {
        path: '/projects/create',
        meta: { authRequired: true, title: 'Project Create' },
        component: () => import('../views/projects/create.vue')
    },
    {
        path: '/projects/overview',
        meta: { authRequired: true, title: 'Project Overview' },
        component: () => import('../views/projects/overview.vue')
    },
    {
        path: '/tasks/list',
        meta: { authRequired: true, title: 'Task List' },
        component: () => import('../views/tasks/task-list.vue')
    },
    {
        path: '/tasks/create',
        meta: { authRequired: true, title: 'Create Task' },
        component: () => import('../views/tasks/task-create.vue')
    },
    {
        path: '/tasks/kanban',
        meta: { authRequired: true, title: 'Kanban Board' },
        component: () => import('../views/tasks/kanbanboard.vue')
    },
    {
        path: '/contacts/grid',
        meta: { authRequired: true, title: 'Contact Grid' },
        component: () => import('../views/contacts/contacts-grid.vue')
    },
    {
        path: '/contacts/list',
        meta: { authRequired: true, title: 'Contact List' },
        component: () => import('../views/contacts/contacts-list.vue')
    },
    {
        path: '/contacts/profile',
        meta: { authRequired: true, title: 'Contact Profile' },
        component: () => import('../views/contacts/contacts-profile.vue')
    },
    {
        path: '/blog/grid',
        meta: { authRequired: true, title: 'Blog Grid' },
        component: () => import('../views/blog/grid.vue')
    },
    {
        path: '/blog/list',
        meta: { authRequired: true, title: 'Blog List' },
        component: () => import('../views/blog/list.vue')
    },
    {
        path: '/blog/detail',
        meta: { authRequired: true, title: 'Blog Detail' },
        component: () => import('../views/blog/detail.vue')
    },
    {
        path: '/jobs/list',
        meta: { authRequired: true, title: 'Job List' },
        component: () => import('../views/jobs/job-list.vue')
    },
    {
        path: '/jobs/grid',
        meta: { authRequired: true, title: 'Job Grid' },
        component: () => import('../views/jobs/job-grid.vue')
    },
    {
        path: '/jobs/apply',
        meta: { authRequired: true, title: 'Job Apply' },
        component: () => import('../views/jobs/job-apply.vue')
    },
    {
        path: '/jobs/details',
        meta: { authRequired: true, title: 'Job Details' },
        component: () => import('../views/jobs/job-details.vue')
    },
    {
        path: '/jobs/categories',
        meta: { authRequired: true, title: 'Job Categories' },
        component: () => import('../views/jobs/job-categories.vue')
    },
    {
        path: '/jobs/candidate/list',
        meta: { authRequired: true, title: 'Candidate List' },
        component: () => import('../views/jobs/candidate/list.vue')
    },
    {
        path: '/jobs/candidate/overview',
        meta: { authRequired: true, title: 'Candidate Overview' },
        component: () => import('../views/jobs/candidate/overview.vue')
    },
    {
        path: '/auth/login-1',
        meta: { authRequired: true, title: 'Login' },
        component: () => import('../views/sample-pages/login-sample.vue')
    },
    {
        path: '/login',
        name: 'login',
        meta: { title: 'Login' },
        component: () => import('../views/account/login.vue')
    },
    {
        path: '/auth/login-2',
        meta: { authRequired: true, title: 'Login' },
        component: () => import('../views/sample-pages/login-2.vue')
    },
    {
        path: '/auth/register',
        name: 'register',
        meta: { title: 'Register' },
        component: () => import('../views/account/register.vue')
    },
    {
        path: '/auth/register-1',
        meta: { title: 'Register' },
        component: () => import('../views/sample-pages/register-sample.vue')
    },
    {
        path: '/auth/register-2',
        meta: { authRequired: true, title: 'Register' },
        component: () => import('../views/sample-pages/register-2.vue')
    },
    {
        path: '/forget-password',
        meta: { title: 'Forget Password' },
        component: () => import('../views/account/forgot-password.vue')
    },
    {
        path: '/reset-password/:token',
        meta: { title: 'Reset Password' },
        component: () => import('../views/account/reset-password.vue')
    },
    {
        path: '/auth/recoverpw',
        meta: { authRequired: true, title: 'Reset Password' },
        component: () => import('../views/sample-pages/recoverpw-sample.vue')
    },
    {
        path: '/auth/recoverpwd-2',
        meta: { authRequired: true, title: 'Reset Password' },
        component: () => import('../views/sample-pages/recoverpwd-2.vue')
    },
    {
        path: '/auth/lock-screen',
        meta: { authRequired: true, title: 'Lock Screen' },
        component: () => import('../views/sample-pages/lockscreen.vue')
    },
    {
        path: '/auth/lock-screen-2',
        meta: { authRequired: true, title: 'Lock Screen' },
        component: () => import('../views/sample-pages/lockscreen-2.vue')
    },
    {
        path: '/auth/confirm-mail',
        meta: { authRequired: true, title: 'Confirm Mail' },
        component: () => import('../views/sample-pages/confirm-mail.vue')
    },
    {
        path: '/auth/confirm-mail-2',
        meta: { authRequired: true, title: 'Confirm Mail' },
        component: () => import('../views/sample-pages/confirm-mail-2.vue')
    },
    {
        path: '/auth/email-verification',
        meta: { authRequired: true, title: 'Email Verification' },
        component: () => import('../views/sample-pages/email-verification.vue')
    },
    {
        path: '/auth/email-verification-2',
        meta: { authRequired: true, title: 'Email Verification' },
        component: () => import('../views/sample-pages/email-verification-2.vue')
    },
    {
        path: '/auth/two-step-verification',
        meta: { authRequired: true, title: 'Two Step Verification' },
        component: () => import('../views/sample-pages/two-step-verification.vue')
    },
    {
        path: '/auth/two-step-verification-2',
        meta: { authRequired: true, title: 'Two Step Verification' },
        component: () => import('../views/sample-pages/two-step-verification-2.vue')
    },
    {
        path: '/pages/starter',
        meta: { authRequired: true, title: 'Starter Page' },
        component: () => import('../views/utility/starter.vue')
    },
    {
        path: '/pages/maintenance',
        meta: { authRequired: true, title: 'Maintenance' },
        component: () => import('../views/utility/maintenance.vue')
    },
    {
        path: '/pages/coming-soon',
        meta: { authRequired: true, title: 'Comming Soon' },
        component: () => import('../views/utility/coming-soon.vue')
    },
    {
        path: '/pages/timeline',
        meta: { authRequired: true, title: 'Timeline' },
        component: () => import('../views/utility/timeline.vue')
    },
    {
        path: '/pages/faqs',
        meta: { authRequired: true, title: 'FaQs' },
        component: () => import('../views/utility/faqs.vue')
    },
    {
        path: '/pages/pricing',
        meta: { authRequired: true, title: 'Pricing' },
        component: () => import('../views/utility/pricing.vue')
    },
    {
        path: '/pages/404',
        meta: { authRequired: true, title: '404' },
        component: () => import('../views/utility/404.vue')
    },
    {
        path: '/pages/500',
        meta: { authRequired: true, title: '500' },
        component: () => import('../views/utility/500.vue')
    },
    {
        path: '/ui/alerts',
        meta: { authRequired: true, title: 'Alerts' },
        component: () => import('../views/ui/alerts.vue')
    },
    {
        path: '/ui/buttons',
        meta: { authRequired: true, title: 'Buttons' },
        component: () => import('../views/ui/buttons.vue')
    },
    {
        path: '/ui/cards',
        meta: { authRequired: true, title: 'Cards' },
        component: () => import('../views/ui/cards.vue')
    },
    {
        path: '/ui/carousel',
        meta: { authRequired: true, title: 'Carousel' },
        component: () => import('../views/ui/carousel.vue')
    },
    {
        path: '/ui/colors',
        meta: { authRequired: true, title: 'Colors' },
        component: () => import('../views/ui/colors.vue')
    },
    {
        path: '/ui/image-cropper',
        meta: { authRequired: true, title: 'Cropper' },
        component: () => import('../views/ui/cropper.vue')
    },
    {
        path: "/ui/notifications",
        name: "Notifications",
        meta: { title: "Notifications", authRequired: true },
        component: () => import("../views/ui/notifications.vue")
    },
    {
        path: '/ui/dropdowns',
        meta: { authRequired: true, title: 'Dropdowns' },
        component: () => import('../views/ui/dropdowns.vue')
    },
    {
        path: '/ui/general',
        meta: { authRequired: true, title: 'General' },
        component: () => import('../views/ui/general.vue')
    },
    {
        path: '/ui/grid',
        meta: { authRequired: true, title: 'Grid' },
        component: () => import('../views/ui/grid.vue')
    },
    {
        path: '/ui/images',
        meta: { authRequired: true, title: 'Images' },
        component: () => import('../views/ui/images.vue')
    },
    {
        path: '/ui/lightbox',
        meta: { authRequired: true, title: 'Lightbox' },
        component: () => import('../views/ui/lightbox.vue')
    },
    {
        path: '/ui/modals',
        meta: { authRequired: true, title: 'Modals' },
        component: () => import('../views/ui/modals.vue')
    },
    {
        path: '/ui/offcanvas',
        meta: { authRequired: true, title: 'Offcanvas' },
        component: () => import('../views/ui/offcanvas.vue')
    },
    {
        path: '/ui/placeholder',
        meta: { authRequired: true, title: 'Placeholder' },
        component: () => import('../views/ui/placeholder.vue')
    },
    {
        path: '/ui/progressbars',
        meta: { authRequired: true, title: 'Progressbars' },
        component: () => import('../views/ui/progressbars.vue')
    },
    {
        path: '/ui/rangeslider',
        meta: { authRequired: true, title: 'Rangeslider' },
        component: () => import('../views/ui/rangeslider.vue')
    },
    {
        path: "/ui/session-timeout",
        name: "session-timeout",
        meta: { title: "Session Timeout", authRequired: true },
        component: () => import("../views/ui/session-timeout.vue")
    },
    {
        path: '/ui/sweet-alert',
        meta: { authRequired: true, title: 'Sweet Alert' },
        component: () => import('../views/ui/sweet-alert.vue')
    },
    {
        path: '/ui/tabs-accordions',
        meta: { authRequired: true, title: 'Tabs Accordions' },
        component: () => import('../views/ui/tabs-accordions.vue')
    },
    {
        path: '/ui/typography',
        meta: { authRequired: true, title: 'Typography' },
        component: () => import('../views/ui/typography.vue')
    },
    {
        path: '/ui/utilities',
        meta: { authRequired: true, title: 'Utilities' },
        component: () => import('../views/ui/utilities.vue')
    },
    {
        path: '/ui/video',
        meta: { authRequired: true, title: 'Video' },
        component: () => import('../views/ui/video.vue')
    },
    {
        path: '/form/elements',
        meta: { authRequired: true, title: 'Form Element' },
        component: () => import('../views/forms/elements.vue')
    },
    {
        path: '/form/layouts',
        meta: { authRequired: true, title: 'Form Layouts' },
        component: () => import('../views/forms/layouts.vue')
    },
    {
        path: '/form/advanced',
        meta: { authRequired: true, title: 'Form Advance' },
        component: () => import('../views/forms/advanced.vue')
    },
    {
        path: '/form/editor',
        meta: { authRequired: true, title: 'Form Editor' },
        component: () => import('../views/forms/ckeditor.vue')
    },
    {
        path: '/form/uploads',
        meta: { authRequired: true, title: 'Form Uploads' },
        component: () => import('../views/forms/uploads.vue')
    },
    {
        path: '/form/repeater',
        meta: { authRequired: true, title: 'Form Repeater' },
        component: () => import('../views/forms/repeater.vue')
    },
    {
        path: '/form/wizard',
        meta: { authRequired: true, title: 'Form Wizard' },
        component: () => import('../views/forms/wizard.vue')
    },
    {
        path: '/form/mask',
        meta: { authRequired: true, title: 'Form Mask' },
        component: () => import('../views/forms/mask.vue')
    },
    {
        path: '/tables/basic',
        meta: { authRequired: true, title: 'Basic Tables' },
        component: () => import('../views/tables/basictable.vue')
    },
    {
        path: '/charts/apex',
        meta: { authRequired: true, title: 'Apexchart' },
        component: () => import('../views/charts/apex.vue')
    },
    {
        path: '/charts/chartjs',
        meta: { authRequired: true, title: 'Chartjs' },
        component: () => import('../views/charts/chartjs/index.vue')
    },
    {
        path: '/icons/boxicons',
        meta: { authRequired: true, title: 'Boxicons' },
        component: () => import('../views/icons/boxicons.vue')
    },
    {
        path: '/icons/materialdesign',
        meta: { authRequired: true, title: 'Material Design' },
        component: () => import('../views/icons/materialdesign.vue')
    },
    {
        path: '/icons/dripicons',
        meta: { authRequired: true, title: 'Drip Icons' },
        component: () => import('../views/icons/dripicons.vue')
    },
    {
        path: '/icons/fontawesome',
        meta: { authRequired: true, title: 'Font Awesome' },
        component: () => import('../views/icons/fontawesome.vue')
    },
    {
        path: '/maps/google',
        meta: { authRequired: true, title: 'Google Maps' },
        component: () => import('../views/maps/google.vue')
    },
    {
        path: '/maps/leaflet',
        meta: { authRequired: true, title: 'Leaflet Maps' },
        component: () => import('../views/maps/leaflet/index.vue')
    },
    {
        path: "/maps/amcharts",
        meta: { authRequired: true, title: "Amcharts Maps" },
        component: () => import('../views/maps/amcharts/index.vue')
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// Before each route evaluates...
router.beforeEach(async (routeTo, routeFrom, next) => {
    // set title name
    if (routeTo.meta.title != undefined) {
        document.title = routeTo.meta.title + " | Skote Laravel 11 + Vue 3 Admin & Dashboard";
    }

    const authRequired = routeTo.matched.some((route) => route.meta.authRequired);
    if (!authRequired) return next();

    const token = localStorage.getItem('token');
    if (!token) {
        return next({ name: 'login', query: { redirectFrom: routeTo.fullPath } });
    }

    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

    const requiredPermission = routeTo.meta.permission;
    if (!requiredPermission) {
        return next();
    }

    let permissions = null;
    try {
        permissions = JSON.parse(localStorage.getItem('permissions') || 'null');
    } catch (e) {
        permissions = null;
    }

    if (!Array.isArray(permissions)) {
        try {
            const response = await axios.get('/api/me/permissions');
            permissions = response.data.data || [];
            localStorage.setItem('permissions', JSON.stringify(permissions));
        } catch (error) {
            permissions = [];
        }
    }

    if (permissions.includes(requiredPermission)) {
        return next();
    }

    return next({ path: '/' });

});

export default router;
