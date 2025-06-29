# BLC Shipping Simulation

A comprehensive web-based simulation system for maritime container logistics management.

## Overview

BLC Shipping is an interactive educational platform designed to simulate container logistics operations in shipping. The application transforms a previously manual simulation into a digital experience, enabling participants to learn about stowage planning, revenue optimization, and logistics decision-making in a realistic yet engaging environment.

## Documentation

Before trying the application, we recommend reviewing the comprehensive manual book that covers all features and functionality:

[![Manual Book](https://img.shields.io/badge/Manual_Book-PDF-blue?style=for-the-badge&logo=googledrive)](https://drive.google.com/file/d/1pgHojlFPUHrjyinyZLxF57wJvdyGX4A4/view?usp=sharing)

The manual book includes:
- Detailed explanations of the simulation concept
- Step-by-step guides for both admin and participant roles
- Instructions for setting up and managing simulation rooms
- Tutorial for container stowage planning
- Guide to understanding weekly performance metrics
- Troubleshooting common issues

**Note**: We strongly recommend reading through the manual book before attempting to deploy or use the application for the first time.

## Features

- **Interactive Container Management**: Drag-and-drop interface for stowage planning with real-time feedback
- **Multi-User Simultaneous Simulations**: Support for multiple concurrent simulation rooms
- **Gamified Learning Approach**: Score tracking, leaderboards, and competitive elements
- **Real-Time Updates**: WebSocket implementation for live interactions between participants
- **3D Visualization**: Three.js powered ship layout visualization for enhanced understanding
- **Performance Analytics**: Weekly performance tracking with detailed metrics
- **Role-Based Access**: Separate interfaces for administrators and participants

## Technology Stack

### Frontend
- React.js with Vite
- TailwindCSS for styling
- React Query for data fetching
- DnD Kit for drag-and-drop functionality
- Three.js for 3D visualizations
- Socket.IO client for real-time communication

### Backend
- Laravel 10 PHP framework
- MySQL database
- Redis for caching and performance optimization
- Laravel Sanctum for authentication
- WebSocket server with Socket.IO

### Deployment
- Docker containerization
- Cloudflare Tunnel for secure public access

## Installation

### Prerequisites
- Docker and Docker Compose
- Node.js and npm/yarn (for development)
- Composer (for development)

### Setup

1. Clone the repository
   ```bash
   git clone https://github.com/yourusername/blc-shipping.git
   cd blc-shipping
   ```
2. Configure environment variables
   ```cp .env.example .env
   cp frontend-blc-shipping/.env.example frontend-blc-shipping/.env
   cp blc-shipping-backend/.env.example blc-shipping-backend/.env
   cp websockets-blc-shipping/.env.example websockets-blc-shipping/.env
   ```
3. Update environment variables with your specific settings
4. Start the Docker containers
   ```
   docker-compose up -d
   ```
5. Set up the database
   ```
   docker-compose exec backend php artisan migrate --seed
   ```
6. Generate application key
   ```
   docker-compose exec backend php artisan key:generate
   ```
