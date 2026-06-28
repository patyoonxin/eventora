# Eventora 🎟️

> From idea to QR check-in, run your society events end-to-end.

A cross-platform event management and ticketing application designed for student societies. Eventora replaces fragmented processes with a unified digital ecosystem for creating events, managing registrations, and handling check-ins via secure QR codes. 

### 🌟Try here: https://eventora-teal.vercel.app/🌟

## Table of Contents
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Getting Started](#getting-started)
- [Configuration](#configuration)

## Features

### Core Features
- 🔐 **Role-Based Access Control** – Support for Attendees, Event Organizers, and Admin roles
- 📅 **Event Lifecycle Management** – Create, edit, and manage events with full lifecycle control
- 🎫 **QR Code Ticketing** – Generate secure QR codes for instant ticket validation
- 📍 **Check-in System** – Real-time attendee check-in via mobile camera scanner
- 🔥 **Feedback System** – Post-event feedback form with rating and comments, dashboard view of results. 
- 📊 **Analytics & Attendance Reports** – Real-time dashboards with CSV export capabilities
- 🔔 **Notifications** – Automated email and in-app alerts for registrations and reminders

### Premium Features
- 💳 **Real-World Stripe Payment Integration** – Full payment processing for paid events
- 🤖 **AI-Powered Feedback Summarization** – Sentiment analysis and aggregation of attendee feedback using Large Language Models (LLMs)
- 📈 **Advanced Graph Analytics** – Premium visualised analytics dashboard for deeper insights
- 🎖️ **Digital Certificates** – Automated certificate generation for attendees
- 🧠 **Smart Recommendation Feed**: Tailored personalized event suggestions displayed to users based on their past attendance history.

## Tech Stack

### Frontend
- **Vue 3** – Progressive JavaScript framework
- **Pinia** – State management
- **Vue Router** – Client-side routing
- **Capacitor** – Native mobile app framework utilizing device camera permissions
- **Tailwind CSS** – Utility-first CSS framework
- **Vite** – Next-generation build tool
- **Chart.js** - Renders interactive data graphs

### Backend
- **PHP Slim 4** – Lightweight PHP framework
- **MySQL** – Relational database
- **PDO** – Database abstraction layer
- **JWT** – Secure authentication
- **Stripe API** – Payment processing
- **SMTP** – Email notifications
- **Gemeni API** - AI integration

## Project Structure

```
eventora/
├── frontend/              # Vue 3 web & mobile app
│   ├── src/
│   │   ├── components/   # Reusable UI components
│   │   ├── pages/        # Page-level components
│   │   ├── stores/       # Pinia state management
│   │   └── router/       # Route definitions
│   ├── android/          # Capacitor Android build
│   └── package.json
├── backend/              # PHP Slim API
│   ├── src/
│   │   ├── Controllers/  # API endpoints
│   │   ├── Models/       # Data models
│   │   ├── Services/     # Business logic
│   │   └── Middleware/   # Auth & request handlers
│   ├── config/           # Routes & settings
│   ├── public/           # Entry point
│   └── composer.json
└── README.md
```

## Getting Started

### Prerequisites
- PHP 7.4+ with CLI
- Node.js 16+ and npm
- MySQL 5.7+
- Composer (PHP dependency manager)

### Backend Setup

1. Navigate to the backend directory:
   ```bash
   cd backend
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Create `.env` file with database and API credentials:
   ```bash
   cp .env.example .env
   ```

4. Initialize the database:
   ```bash
   mysql -u root -p eventora < database/schema.sql
   ```

5. Start the development server:
   ```bash
   php -S localhost:8000 -t public
   ```

### Frontend Setup

1. Navigate to the frontend directory:
   ```bash
   cd frontend
   ```

2. Install dependencies:
   ```bash
   npm install
   ```

3. Create `.env.local` file:
   ```env
   VITE_API_BASE_URL=http://localhost:8000
   VITE_STRIPE_PUBLISHABLE_KEY=pk_test_...
   ```

4. Start the development server:
   ```bash
   npm run dev
   ```

### Mobile (Android) Build

1. Build the frontend:
   ```bash
   npm run build
   ```

2. Sync Capacitor:
   ```bash
   npx cap sync
   ```

3. Open in Android Studio:
   ```bash
   npx cap open android
   ```
