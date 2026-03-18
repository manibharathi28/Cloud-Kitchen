# Cloud Kitchen Backend API

A comprehensive RESTful API for cloud kitchen management system built with Laravel 12, featuring modern PHP practices, queue-based processing, and comprehensive testing.

## 🚀 Features

- **Authentication & Authorization**: Laravel Sanctum-based token authentication
- **Menu Management**: CRUD operations with soft deletes and availability tracking
- **Order Processing**: Complete order lifecycle management with status tracking
- **Payment Integration**: Secure payment processing with queue-based handling
- **Invoice Generation**: Automated invoice creation and management
- **Real-time Notifications**: Queue-based email and database notifications
- **Comprehensive Testing**: Unit and feature tests with high coverage
- **API Documentation**: Detailed OpenAPI-style documentation
- **Modern Laravel 12**: Latest features including typed properties, enums, and more

## 📋 Requirements

- PHP 8.2+
- Composer
- SQLite (default) or MySQL/PostgreSQL
- Redis (for queues and caching)

## 🛠️ Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd cloud-kitchen-backend
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. **Start the development server**
   ```bash
   php artisan serve
   ```

6. **Start queue worker (in separate terminal)**
   ```bash
   php artisan queue:work
   ```

## 🧪 Testing

Run the test suite:

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Feature/AuthTest.php
```

## 📚 API Documentation

See the complete API documentation in `docs/api.md` or visit `/api/test` to verify the API is working.

### Quick Start

1. **Register a user**
   ```bash
   curl -X POST http://localhost:8000/api/register \
     -H "Content-Type: application/json" \
     -d '{"name":"John Doe","email":"john@example.com","password":"password123","password_confirmation":"password123"}'
   ```

2. **Login**
   ```bash
   curl -X POST http://localhost:8000/api/login \
     -H "Content-Type: application/json" \
     -d '{"email":"john@example.com","password":"password123"}'
   ```

3. **Use the token for authenticated requests**
   ```bash
   curl -X GET http://localhost:8000/api/profile \
     -H "Authorization: Bearer YOUR_TOKEN_HERE"
   ```

## 🏗️ Project Structure

```
app/
├── Http/
│   ├── Controllers/          # API controllers
│   ├── Requests/            # Form request validation
│   └── Resources/           # API resource transformers
├── Models/                  # Eloquent models
├── Jobs/                    # Queue jobs
├── Notifications/           # Notification classes
└── Exceptions/              # Exception handling

database/
├── factories/              # Model factories
├── migrations/              # Database migrations
└── seeders/                 # Database seeders

tests/
├── Feature/                # Feature tests
└── Unit/                   # Unit tests

docs/                       # API documentation
```

## 🔧 Configuration

### Environment Variables

Key environment variables in `.env`:

```env
APP_NAME="Cloud Kitchen API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

QUEUE_CONNECTION=database
CACHE_STORE=database
```

### Queue Configuration

The application uses database queues by default. To use Redis:

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## 📊 Models & Relationships

### User
- `orders()` - Has many orders
- `payments()` - Has many payments
- `invoices()` - Has many invoices

### Menu
- `orderItems()` - Has many order items
- Soft deletes enabled
- `available` scope for filtering

### Order
- `items()` - Has many order items
- `user()` - Belongs to user
- `payments()` - Has many payments
- `invoice()` - Has one invoice

### OrderItem
- `menu()` - Belongs to menu
- `order()` - Belongs to order
- `subtotal` accessor

## 🔄 Queue Jobs

- `ProcessOrderPayment` - Handles payment processing
- `SendOrderNotification` - Sends order status notifications

## 🔔 Notifications

- `OrderStatusChanged` - Notifies users of order status changes
- `NewOrderPlaced` - Notifies admin of new orders

## 🛡️ Security Features

- Token-based authentication with Laravel Sanctum
- Request validation with Form Requests
- SQL injection prevention with Eloquent ORM
- Rate limiting on API endpoints
- Proper error handling and logging

## 📈 Performance Optimizations

- Database query optimization with eager loading
- API response caching where appropriate
- Queue-based background processing
- Pagination for large datasets
- Soft deletes for data recovery

## 🧰 Development Tools

- Laravel Pint for code formatting
- Laravel Telescope for debugging (optional)
- Laravel Horizon for queue monitoring (optional)
- Comprehensive logging

## 📝 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Run the test suite
6. Submit a pull request

## 📞 Support

For questions and support, please open an issue in the repository or contact the development team.

---

**Built with ❤️ using Laravel 12**
