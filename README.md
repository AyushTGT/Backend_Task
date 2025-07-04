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
    git clone https://github.com/AyushTGT/task-user-management-backend.git
    cd task-user-management-backend
    ```

2. **Install Dependencies**
    ```sh
    composer install
    ```

3. **Environment Setup**
    ```sh
    cp .env.example .env
    ```
    Set your database credentials and other settings in `.env`.

4. **Database Setup**
    ```sh
    php artisan migrate
    ```

5. **Generate JWT Secret**
    ```sh
    php artisan jwt:secret
    ```

6. **Run the Application**
    ```sh
    php -S localhost:8000 -t public
    ```

---

## 📧 Email Queue Setup

### Configuration
Add to your `.env` file:
```env
# Queue Configuration
QUEUE_CONNECTION=database
QUEUE_FAILED_TABLE=failed_jobs

# SMTP Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Task Management System"
```

### Queue Commands
```sh
# Create queue tables
php artisan queue:table
php artisan migrate

# Process queued jobs
php artisan queue:work

# Process specific queue
php artisan queue:work --queue=emails


---

## 🚀 Pusher Setup

### Step 1: Get Pusher Credentials
1. Create account at [Pusher.com](https://pusher.com)
2. Create new Channels app
3. Get your credentials from "App Keys" section

### Step 2: Configure Environment
```env
# Pusher Configuration
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=ap2
```

---

## ⏰ Cron Jobs Setup

### Available Commands
```sh
# Send scheduled notifications
php artisan notify:users

# Clean expired tokens
php artisan auth:cleanup

# Generate daily reports
php artisan reports:daily

# Process pending tasks
php artisan tasks:process
```

### Setup Cron Jobs
Add to your crontab (`crontab -e`):
```cron
# Run Laravel scheduler every minute
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1

```

---
## Project Structure

```
app/
├── Console/
│   ├── Commands/
│   │   ├── sendEmails.php          # Custom email sending command
│   │   └── .gitkeep
│   └── Kernel.php                  # Console kernel configuration
├── Events/
│   ├── Event.php                   # Base event class
│   └── NotificationCreated.php     # Notification event
├── Exceptions/
│   └── Handler.php                 # Global exception handler
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php          # Base controller
│   │   ├── NotificationController.php  # Notification management
│   │   ├── TaskController.php      # Task CRUD operations
│   │   └── UserController.php      # User management
│   └── Middleware/
│       ├── Authenticate.php        # Authentication middleware
│       ├── CorsMiddleware.php      # CORS handling
│       └── ExampleMiddleware.php   # Example middleware
├── Jobs/
│   ├── Job.php                     # Base job class
│   ├── SendEmailJob.php           # Email sending job
│   └── ExampleJob.php             # Example job
├── Listeners/
│   └── ExampleListener.php        # Event listener example
├── Models/
│   ├── Email.php                   # Email model
│   ├── Notification.php           # Notification model
│   ├── Task.php                    # Task model
│   └── User.php                    # User model
├── Providers/
│   ├── AppServiceProvider.php     # Application service provider
│   ├── AuthServiceProvider.php    # Authentication service provider
│   └── EventServiceProvider.php   # Event service provider
└── Services/
    ├── AuthService.php            # Authentication logic
    └── UserService.php            # User business logic
```
---

## 🐳 Docker Setup

### Development with Docker

1. **Create Dockerfile**
    ```dockerfile
    FROM php:8.1-fpm
    
    # Install dependencies
    RUN apt-get update && apt-get install -y \
        git \
        curl \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        zip \
        unzip
    
    # Install PHP extensions
    RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd
    
    # Install Composer
    COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
    
    # Set working directory
    WORKDIR /var/www
    
    # Copy application files
    COPY . .
    
    # Install dependencies
    RUN composer install --optimize-autoloader --no-dev
    
    # Set permissions
    RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
    
    EXPOSE 8000
    CMD php -S 0.0.0.0:8000 -t public
    ```

2. **Create docker-compose.yml**
    ```yaml
    version: '3.8'
    services:
      app:
        build: .
        ports:
          - "8000:8000"
        volumes:
          - .:/var/www
          - ./storage:/var/www/storage
        environment:
          - DB_HOST=mysql
          - QUEUE_CONNECTION=redis
          - REDIS_HOST=redis
        depends_on:
          - mysql
          - redis
    
      mysql:
        image: mysql:8.0
        environment:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: task_management
          MYSQL_USER: lumen
          MYSQL_PASSWORD: secret
        ports:
          - "3306:3306"
        volumes:
          - mysql_data:/var/lib/mysql
    
      redis:
        image: redis:alpine
        ports:
          - "6379:6379"
    
      queue-worker:
        build: .
        command: php artisan queue:work --sleep=3 --tries=3
        volumes:
          - .:/var/www
        depends_on:
          - mysql
          - redis
        environment:
          - DB_HOST=mysql
          - REDIS_HOST=redis
    
    volumes:
      mysql_data:
    ```


---


## 🤝 Contributing

1. **Fork the repository**
2. **Create a feature branch**
    ```sh
    git checkout -b feature/your-feature-name
    ```
3. **Make your changes and test**
    ```sh
    ./vendor/bin/phpunit        # Run tests
    composer run-script format  # Format code
    ```
4. **Commit and push**
    ```sh
    git commit -m "feat: add your feature"
    git push origin feature/your-feature-name
    ```
5. **Create a Pull Request**

---


## API Documentation

For detailed API documentation with request/response examples, authentication, and testing:

**📋 [View Full API Documentation on Postman](https://ayushtomar-7107187.postman.co/workspace/Ayush-Tomar's-Workspace~8f0e35b8-0305-44b9-834b-1e3931c3a1ec/collection/45605324-1ef0c207-f9c4-487a-9cdf-ca5e4655e59e?action=share&creator=45605324)**

All protected endpoints require JWT token in the Authorization header.

---

## 👨‍💻 Author

**AyushTGT**
- GitHub: [@AyushTGT](https://github.com/AyushTGT)
- Email: ayushtomar.iis@gmail.com

