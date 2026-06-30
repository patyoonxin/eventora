import { createRouter, createWebHistory } from "vue-router";

// 1. Import Public/General Pages
import Register from "../pages/Register.vue";
import LoginView from "../pages/LoginView.vue";
import ForgotPassword from "../pages/ForgotPassword.vue";
import Home from "../pages/Home.vue";
import MyTickets from "../pages/MyTickets.vue";
import History from "../pages/History.vue";
import Profile from "../pages/Profile.vue";



// 2. Import Society Role Pages
import S_Home from "../pages/Society/S_Home.vue";
import S_History from "../pages/Society/S_History.vue";
import S_ScanQR from "../pages/Society/S_ScanQR.vue";
import S_Analytics from "../pages/Society/S_Analytics.vue";

// 3. Import Admin Role Pages
import A_AllEvent from "../pages/Admin/A_AllEvent.vue";
import A_UserManage from "../pages/Admin/A_UserManage.vue";
import A_SocietyManage from "../pages/Admin/A_SocietyManage.vue";
import A_Home from "../pages/Admin/A_Home.vue";
import A_SocietyAnalytics from "../pages/Admin/A_SocietyAnalytics.vue";

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      redirect: '/login'
    },
    {
      path: "/home",
      name: "Home",
      component: Home,
      meta: { role: "student" },
    },
    {
      path: "/notifications",
      name: "Notifications",
      component: () => import("@/components/NotificationsView.vue"),
    },
    {
      path: "/events/:id",
      name: "EventDetails",
      component: () => import("@/components/Student/EventDetails.vue"),
      props: true, // Allows passing the id directly as a prop to the component
    },
    {
      path: "/tickets",
      name: "MyTickets",
      component: MyTickets,
      meta: { role: "student" },
    },
    {
      path: "/tickets/:id",
      name: "TicketDetail",
      component: () => import("@/pages/TicketDetail.vue"),
      meta: { role: "student" },
      props: true,
    },
    {
      path: "/history",
      name: "History",
      component: History,
      meta: { role: "student" },
    },
    {
      path: "/past-events/:id",
      name: "PastEventDetails",
      component: () => import("@/components/Student/PastEventDetails.vue"),
      props: true,
    },
    {
      path: "/student/events/:id/feedback",
      name: "SubmitFeedback",
      component: () => import("@/components/Student/SubmitFeedback.vue"),
      props: true,
    },
    {
      path: '/register',
      name: 'Register',
      component: Register
    },
    {
      path: "/login",
      name: "Login",
      component: LoginView,
      meta: { role: "guest" },
    },
    {
      path: "/forgot-password",
      name: "ForgotPassword",
      component: ForgotPassword,
      meta: { role: "guest" },
    },
    {
      path: '/reset-password',
      name: 'ResetPassword',
      component: () => import('@/pages/ResetPassword.vue')
    },
    {
      path: '/profile',
      name: 'Profile',
      component: Profile
    },
    {
      path: "/society/home",
      name: "S_Home",
      component: S_Home,
      meta: { role: "society" },
    },
    {
      path: "/society/events/:id",
      name: "SocietyEventDetails",
      component: () => import("@/components/Society/SocietyEventDetails.vue"),
      props: true,
    },
    {
      path: '/society/events/:id/participants',
      name: 'EventParticipants',
      component: () => import('@/components/Society/EventParticipantsView.vue'),
      props: true
    },
    {
      path: "/society/events/:id/edit",
      name: "SocietyEventEdit",
      component: () => import("@/components/Society/SocietyEventEdit.vue"),
      props: true,
    },
    {
      path: "/society/events/create",
      name: "SocietyEventCreate",
      component: () => import("@/components/Society/SocietyEventEdit.vue"),
      props: true,
    },
    {
      path: "/society/scan-qr",
      name: "S_ScanQR",
      component: S_ScanQR,
      meta: { role: "society" },
    },
    {
      path: "/society/history",
      name: "S_History",
      component: S_History,
      meta: { role: "society" },
    },
    {
      path: "/society/past-events/:id",
      name: "SocietyPastEventDetails",
      component: () => import("@/components/Society/SocietyPastEventDetails.vue"),
      props: true,
    },
    {
      path: "/society/past-events/:id/feedback",
      name: "SocietyEventFeedback",
      component: () => import("@/components/Society/SocietyEventFeedbackView.vue"),
      props: true,
    },
    {
      path: "/society/analytics",
      name: "S_Analytics",
      component: S_Analytics,
      meta: { role: "society" },
    },
    {
      path: "/admin/home",
      name: "A_Home",
      component: A_Home,
      meta: { role: "admin" },
    },
    {
      path: "/admin/events/:id", 
      name: "EventReviewDetails", 
      component: () => import("@/components/Admin/EventReviewDetails.vue"), 
      props: true, 
    },
    {
      path: "/admin/all-events",
      name: "A_AllEvent",
      component: A_AllEvent,
      meta: { role: "admin" },
    },
    {
      path: "/admin/analytics",
      name: "A_SocietyAnalytics",
      component: A_SocietyAnalytics,
      meta: { role: "admin" },
    },
    {
      path: "/admin/users",
      name: "A_UserManage",
      component: A_UserManage,
      meta: { role: "admin" },
    },
    {
      path: "/admin/societies",
      name: "A_SocietyManage",
      component: A_SocietyManage,
      meta: { role: "admin" },
    },
    {
      path: "/checkout-success",
      name: "CheckoutSuccess",
      component: () => import("@/pages/CheckoutSuccess.vue"),
    },
  ],
});

export default router;
