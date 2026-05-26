import { createRouter, createWebHistory } from 'vue-router'

// 1. Import Public/General Pages
import LoginView from '../pages/LoginView.vue'
import Home from '../pages/Home.vue'
import MyTickets from '../pages/MyTickets.vue'
import History from '../pages/History.vue'

// 2. Import Society Role Pages
import S_Home from '../pages/Society/S_Home.vue'
import S_AddEvent from '../pages/Society/S_AddEvent.vue'
import S_History from '../pages/Society/S_History.vue'

// 3. Import Admin Role Pages
import A_AllEvent from '../pages/Admin/A_AllEvent.vue'
import A_UserManage from '../pages/Admin/A_UserManage.vue'
import A_Home from '../pages/Admin/A_Home.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'Home',
      component: Home,
      meta: { role: 'student' }
    },
    {
      path: '/tickets',
      name: 'MyTickets',
      component: MyTickets,
      meta: { role: 'student' }
    },
    {
      path: '/history',
      name: 'History',
      component: History,
      meta: { role: 'student' }
    },
    {
      path: '/login',
      name: 'Login',
      component: LoginView,
      meta: { role: 'guest' }
    },
    {
      path: '/society/home',
      name: 'S_Home',
      component: S_Home,
      meta: { role: 'society' }
    },
    {
      path: '/society/add-event',
      name: 'S_AddEvent',
      component: S_AddEvent,
      meta: { role: 'society' }
    },
    {
      path: '/society/history',
      name: 'S_History',
      component: S_History,
      meta: { role: 'society' }
    },
    {
      path: '/admin/home',
      name: 'A_Home',
      component: A_Home,
      meta: { role: 'admin' }
    },
    {
      path: '/admin/all-events',
      name: 'A_AllEvent',
      component: A_AllEvent,
      meta: { role: 'admin' }
    },
    {
      path: '/admin/users',
      name: 'A_UserManage',
      component: A_UserManage,
      meta: { role: 'admin' }
    }
  ]
})

export default router