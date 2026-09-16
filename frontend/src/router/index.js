import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { tokenStorage } from '../api/tokenStorage'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/LoginView.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('../views/RegisterView.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/',
    name: 'dashboard',
    component: () => import('../views/DashboardView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/loan-applications',
    name: 'loan-applications',
    component: () => import('../views/LoanApplicationsView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/loan-applications/new',
    name: 'loan-application-new',
    component: () => import('../views/NewLoanApplicationView.vue'),
    meta: { requiresAuth: true, customerOnly: true },
  },
  {
    path: '/loan-applications/:id',
    name: 'loan-application-detail',
    component: () => import('../views/LoanApplicationDetailView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/loans',
    name: 'loans',
    component: () => import('../views/LoansView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/loans/:id',
    name: 'loan-detail',
    component: () => import('../views/LoanDetailView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/loan-products',
    name: 'loan-products',
    component: () => import('../views/LoanProductsView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/admin/users',
    name: 'admin-users',
    component: () => import('../views/AdminUsersView.vue'),
    meta: { requiresAuth: true, adminOnly: true },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('../views/NotFoundView.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // Rehydrate the session on first navigation if a token exists but the
  // store hasn't loaded the user yet (e.g. a hard page refresh).
  if (!auth.user && tokenStorage.getAccessToken()) {
    await auth.fetchCurrentUser()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.meta.adminOnly && !auth.isAdmin) {
    return { name: 'dashboard' }
  }

  if (to.meta.customerOnly && !auth.isCustomer) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
