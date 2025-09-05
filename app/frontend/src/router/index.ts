import { createRouter, createWebHistory } from 'vue-router'
import RecordingView from '../views/RecordingView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'recording',
      component: RecordingView,
    },
    {
      path: '/list',
      name: 'recording-list',
      component: () => import('../views/RecordingListView.vue'),
    },
    {
      path: '/summary',
      name: 'summary',
      component: () => import('../views/SummaryView.vue'),
    },
    {
      path: '/timeline',
      name: 'timeline',
      component: () => import('../views/TimelineView.vue'),
    },
    {
      path: '/leaflet',
      name: 'leaflet-test',
      component: () => import('@/views/LeafletTest.vue')  // alias を使うならこちら
    }

  ],
})

export default router
