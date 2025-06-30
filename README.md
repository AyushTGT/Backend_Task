# Task & User Management System

A robust and secure User & Task Management tool built using the [Lumen](https://lumen.laravel.com) PHP micro-framework and MySQL. This application allows efficient management of users and tasks, complete with JWT-based authorization, real-time notifications via Pusher, and queued email sending.

---

## Features

- **User Management:**  
  Create, update, delete, and filter users; export user details as CSV.
- **Task Management:**  
  Assign, update, and track tasks for each user; retrieve task metrics.
- **Authentication & Security:**  
  JWT-based authorization for secure API access.
- **Real-Time Notifications:**  
  Live updates with [Pusher](https://pusher.com/).
- **Email Notifications:**  
  Asynchronous email sending via queues.

---

## Dependencies

- `php: ^8.1`
- `illuminate/mail: ^10.48`
- `illuminate/queue: ^10.48`
- `laravel/lumen-framework: ^10.0`
- `nordsoftware/lumen-cors: ^3.1`
- `pusher/pusher-php-server: ^7.2`
- `tymon/jwt-auth: ^2.2`

---

## Installation

1. **Clone the Repository**
    ```sh
    git clone https://github.com/your-username/your-repo.git
    cd your-repo
    ```

2. **Install Dependencies**
    ```sh
    composer install
    ```

3. **Environment Setup**
    - Copy `.env.example` to `.env`:
    - Set your database credentials and other settings in `.env`.

4. **Queue and Email Setup**
    - Set your queue driver in `.env`.
    - Add you Pusher Credentials
    - Add your smtp Credientials

5. **Run the Application**
    ```sh
    php -S localhost:8000 -t public
    ```

---

## Documentation

See the [Lumen documentation](https://lumen.laravel.com/docs) for general framework usage.
