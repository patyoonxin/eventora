# Eventora 🎟️

> From idea to QR check-in, run your society events end-to-end.

A cross-platform event management and ticketing application designed for student societies. Eventora replaces fragmented processes with a unified digital ecosystem for creating events, managing registrations, and handling check-ins via secure QR codes.

## Table of Contents
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Getting Started](#getting-started)
- [Configuration](#configuration)

## Features

### Core Features
- 🔐 **Role-Based Access Control** – Support for Attendees, Event Organizers, and Admin roles
- 📅 **Event Management** – Create, edit, and manage events with full lifecycle control
- 🎫 **QR Code Ticketing** – Generate secure QR codes for instant ticket validation
- 📍 **Check-in System** – Real-time attendee check-in via mobile camera scanner
- 📊 **Analytics & Reports** – Real-time dashboards with CSV export capabilities
- 🔔 **Notifications** – Automated email and in-app alerts for registrations and reminders

### Premium Features
- 💳 **Stripe Payment Integration** – Full payment processing for paid events
- 🤖 **AI-Powered Feedback** – Sentiment analysis and aggregation of attendee feedback
- 📈 **Advanced Analytics** – Premium reporting dashboard for deeper insights
- 🎖️ **Digital Certificates** – Automated certificate generation for attendees

## Tech Stack

### Frontend
- **Vue 3** – Progressive JavaScript framework
- **Pinia** – State management
- **Vue Router** – Client-side routing
- **Capacitor** – Native mobile app framework
- **Tailwind CSS** – Utility-first CSS framework
- **Vite** – Next-generation build tool

### Backend
- **PHP Slim 4** – Lightweight PHP framework
- **MySQL** – Relational database
- **PDO** – Database abstraction layer
- **JWT** – Secure authentication
- **Stripe API** – Payment processing
- **SMTP** – Email notifications

## Project Structure

``
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
``

## Getting Started

### Prerequisites
- PHP 7.4+ with CLI
- Node.js 16+ and npm
- MySQL 5.7+
- Composer (PHP dependency manager)

### Backend Setup

1. Navigate to the backend directory:
   ``ash
   cd backend
   ``

2. Install dependencies:
   ``ash
   composer install
   ``

3. Create .env file with database and API credentials:
   ``ash
   cp .env.example .env
   ``

4. Initialize the database:
   ``ash
   mysql -u root -p eventora < database/schema.sql
   ``

5. Start the development server:
   ``ash
   php -S localhost:8000 -t public
   ``

### Frontend Setup

1. Navigate to the frontend directory:
   ``ash
   cd frontend
   ``

2. Install dependencies:
   ``ash
   npm install
   ``

3. Create .env.local file:
   ``env
   VITE_API_BASE_URL=http://localhost:8000
   VITE_STRIPE_PUBLISHABLE_KEY=pk_test_...
   ``

4. Start the development server:
   ``ash
   npm run dev
   ``

### Mobile (Android) Build

1. Build the frontend:
   ``ash
   npm run build
   ``

2. Sync Capacitor:
   ``ash
   npx cap sync
   ``

3. Open in Android Studio:
   ``ash
   npx cap open android
   ``

## Configuration

### Backend Environment Variables

Create a .env file in the ackend/ directory:

``env
# Database
DB_HOST=localhost
DB_NAME=eventora
DB_USER=root
DB_PASS=your_password

# Authentication
JWT_SECRET=your_super_secure_jwt_key

# Payment Processing
STRIPE_SECRET_KEY=sk_test_...

# Email
SMTP_HOST=your_smtp_server
SMTP_USER=your_email
SMTP_PASS=your_email_password

# AI Services
AI_API_KEY=your_ai_service_key
``

### Frontend Environment Variables

Create a .env.local file in the rontend/ directory:

``env
VITE_API_BASE_URL=http://localhost:8000
VITE_STRIPE_PUBLISHABLE_KEY=pk_test_...
``

## Development

### Available Scripts

**Backend:**
- \php -S localhost:8000 -t public\ – Start development server

**Frontend:**
- \
pm run dev\ – Start Vite dev server
- \
pm run build\ – Build for production
- \
pm run preview\ – Preview production build locally

## License

This project was developed as part of the SCSM2223 - Cross-Platform Application Development course at Universiti Teknologi Malaysia (UTM).