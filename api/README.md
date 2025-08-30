# SchoolLive API

A comprehensive PHP REST API for multi-school management system with JWT authentication.

## Features

- 🏫 Multi-school support
- 🔐 JWT-based authentication
- 👥 Role-based access control (RBAC)
- 👨‍🎓 Student management
- 👨‍🏫 Teacher/Employee management
- 📊 Attendance tracking
- 📚 Class and section management
- 🗄️ MySQL database with optimized schema

## Tech Stack

- **PHP**: 7.4+
- **MySQL**: 8.0+
- **Composer**: Dependency management
- **JWT**: Firebase JWT library
- **Architecture**: MVC pattern with PSR-4 autoloading

## Quick Start

### 1. Installation

```bash
# Clone the repository
git clone https://github.com/developer-Deepak1/SchoolLive.git
cd SchoolLive/api

# Install dependencies
composer install
```

### 2. Database Setup

```bash
# Initialize the database
php init-db.php
```

### 3. Configuration

Update `config/database.php` with your database credentials:

```php
return [
    'host' => 'localhost',
    'port' => 3306,
    'db_name' => 'schoollive_db',
    'username' => 'root',
    'password' => 'your_password',
    'charset' => 'utf8mb4'
];
```

### 4. Web Server

Point your web server document root to the `api` folder, or use PHP's built-in server:

```bash
php -S localhost:8000 -t public
```

## API Endpoints

### Authentication
- `POST /api/login` - User login
- `POST /api/register` - User registration
- `POST /api/refresh` - Refresh JWT token

### Users
- `GET /api/users` - Get all users
- `GET /api/users/{id}` - Get user by ID
- `POST /api/users` - Create new user
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user

### Students
- `GET /api/students` - Get all students
- `GET /api/students/{id}` - Get student by ID
- `POST /api/students` - Create new student
- `PUT /api/students/{id}` - Update student
- `DELETE /api/students/{id}` - Delete student

### Classes & Attendance
- `GET /api/classes` - Get all classes
- `GET /api/attendance` - Get attendance records
- `POST /api/attendance` - Mark attendance

## Default Login

- **Username**: `superSA002`
- **Password**: `password123`
- **Role**: Super Admin

## Project Structure

```
api/
├── config/
│   ├── database.php        # Database configuration
│   └── jwt.php            # JWT configuration
├── database/
│   └── init_schema.sql    # Database initialization script
├── src/
│   ├── Controllers/       # API Controllers
│   │   ├── LoginController.php
│   │   ├── UserController.php
│   │   ├── StudentsController.php
│   │   ├── AcademicController.php
│   │   └── AttendanceController.php
│   ├── Core/             # Core classes
│   │   ├── Database.php  # Database connection
│   │   └── Router.php    # URL routing
│   ├── Middleware/       # Authentication middleware
│   │   └── AuthMiddleware.php
│   └── Models/          # Data models
│       ├── Model.php    # Base model
│       ├── UserModel.php
│       ├── StudentModel.php
│       ├── AcademicModel.php
│       └── AttendanceModel.php
├── public/
│   └── index.php        # Application entry point
├── composer.json        # Dependencies
├── init-db.php         # Database initialization script
└── README.md           # This file
```

## Database Schema

The system uses a comprehensive database schema supporting:

- **Tm_Schools**: Multi-school support
- **Tm_Roles**: Role-based access control
- **Tx_Users**: User management with school association
- **Tx_Classes**: Class management per school
- **Tx_Students**: Student records with family details
- **Tx_Employees**: Staff management
- **Tx_*_Attendance**: Attendance tracking for students and employees

## Security Features

- JWT-based authentication with access and refresh tokens
- Password hashing using PHP's `password_hash()`
- Role-based permissions
- SQL injection prevention with prepared statements
- CORS headers for cross-origin requests

## Authentication

All endpoints (except login and register) require a valid JWT token in the Authorization header:

```
Authorization: Bearer <your-jwt-token>
```

## Example Usage

### Login
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"superSA002","password":"password123"}'
```

### Get Users (with token)
```bash
curl -X GET http://localhost:8000/api/users \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE.md](../LICENSE.md) file for details.

## Support

For support and questions, please open an issue in the GitHub repository.
