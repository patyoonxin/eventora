import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '../pages/LoginView.vue'
import Home from '../pages/Home.vue'
import MyTickets from '../pages/MyTickets.vue'
import History from '../pages/History.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'Home',
      component: Home
    },
    {
      path: '/tickets',
      name: 'MyTickets',
      component: MyTickets
    },
    {
      path: '/history',
      name: 'History',
      component: History
    },
    {
      path: '/login',
      name: 'Login',
      component: LoginView,
      meta: { hideNav: true }
    }
  ]
})

export default router