import { createRouter, createWebHistory } from 'vue-router'
import { guestOnly, requireAdmin, requireAuth } from './guards'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: () => import('@/views/Home.vue') },
    { path: '/browse', name: 'browse', component: () => import('@/views/Browse.vue') },
    { path: '/search', name: 'search', component: () => import('@/views/SearchResults.vue') },
    { path: '/books/:id', name: 'book-detail', component: () => import('@/views/BookDetail.vue') },
    {
      path: '/read/:id',
      name: 'reader',
      component: () => import('@/views/Reader.vue'),
      // Full-window app view: no footer, and the reader is the only thing that scrolls.
      meta: { immersive: true },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/Login.vue'),
      beforeEnter: guestOnly,
    },
    {
      path: '/signup',
      name: 'signup',
      component: () => import('@/views/Signup.vue'),
      beforeEnter: guestOnly,
    },
    { path: '/auth/callback', name: 'auth-callback', component: () => import('@/views/AuthCallback.vue') },
    {
      path: '/profile',
      name: 'profile',
      component: () => import('@/views/Profile.vue'),
      beforeEnter: requireAuth,
    },
    {
      path: '/admin',
      name: 'admin-dashboard',
      component: () => import('@/views/admin/Dashboard.vue'),
      beforeEnter: requireAdmin,
    },
    {
      path: '/admin/upload',
      name: 'admin-upload',
      component: () => import('@/views/admin/UploadPdf.vue'),
      beforeEnter: requireAdmin,
    },
    {
      path: '/admin/loans',
      name: 'admin-loans',
      component: () => import('@/views/admin/Loans.vue'),
      beforeEnter: requireAdmin,
    },
    {
      path: '/admin/logs',
      name: 'admin-logs',
      component: () => import('@/views/admin/Logs.vue'),
      beforeEnter: requireAdmin,
    },
    { path: '/docs', name: 'docs', component: () => import('@/views/Docs.vue') },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFound.vue') },
  ],
})

export default router
