# Cloud Kitchen Backend API Documentation

## Overview

This is a comprehensive RESTful API for a cloud kitchen management system built with Laravel 12. The API provides endpoints for user authentication, menu management, order processing, payments, and reporting.

## Base URL

```
http://localhost:8000/api
```

## Authentication

The API uses Laravel Sanctum for token-based authentication. Include the token in the Authorization header:

```
Authorization: Bearer {token}
```

## Response Format

All API responses follow a consistent format:

### Success Response
```json
{
    "status": "success",
    "message": "Operation completed successfully",
    "data": { ... }
}
```

### Error Response
```json
{
    "status": "error",
    "message": "Error description",
    "errors": { ... } // Only for validation errors
}
```

## Endpoints

### Authentication

#### Register User
```http
POST /api/register
```

**Request Body:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response:**
```json
{
    "status": "success",
    "message": "Registration successful",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
    },
    "token": "1|abc123..."
}
```

#### Login
```http
POST /api/login
```

**Request Body:**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

#### Logout
```http
POST /api/logout
```
*Requires authentication*

#### Get Profile
```http
GET /api/profile
```
*Requires authentication*

### Menu Management

#### Get Menu Items
```http
GET /api/menu
```

**Query Parameters:**
- `available` (boolean): Filter only available items
- `page` (integer): Page number for pagination

**Response:**
```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "name": "Delicious Burger",
            "description": "Juicy beef burger with fresh vegetables",
            "price": "12.99",
            "available": true,
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        }
    ],
    "pagination": {
        "total": 20,
        "per_page": 10,
        "current_page": 1,
        "last_page": 2
    }
}
```

#### Create Menu Item
```http
POST /api/menu
```
*Requires authentication*

**Request Body:**
```json
{
    "name": "New Dish",
    "description": "Description of the dish",
    "price": "15.99",
    "available": true
}
```

#### Get Menu Item
```http
GET /api/menu/{id}
```

#### Update Menu Item
```http
PUT /api/menu/{id}
```
*Requires authentication*

#### Delete Menu Item
```http
DELETE /api/menu/{id}
```
*Requires authentication*

#### Restore Menu Item
```http
POST /api/menu/{id}/restore
```
*Requires authentication*

### Order Management

#### Get Orders
```http
GET /api/orders
```
*Requires authentication*

**Query Parameters:**
- `status` (string): Filter by status (pending, preparing, ready, completed, cancelled)
- `user_id` (integer): Filter by user ID
- `page` (integer): Page number for pagination

#### Create Order
```http
POST /api/orders
```
*Requires authentication*

**Request Body:**
```json
{
    "type": "single",
    "scheduled_for": "2024-01-01T15:30:00",
    "items": [
        {
            "menu_id": 1,
            "quantity": 2,
            "price": "12.99"
        },
        {
            "menu_id": 2,
            "quantity": 1,
            "price": "8.99"
        }
    ]
}
```

#### Get Order
```http
GET /api/orders/{id}
```
*Requires authentication*

#### Update Order
```http
PUT /api/orders/{id}
```
*Requires authentication*

**Request Body:**
```json
{
    "status": "preparing",
    "scheduled_for": "2024-01-01T16:00:00"
}
```

#### Delete Order
```http
DELETE /api/orders/{id}
```
*Requires authentication*

#### Get My Orders
```http
GET /api/my-orders
```
*Requires authentication*

### Payment Management

#### Process Payment
```http
POST /api/payments
```
*Requires authentication*

#### Get Payments
```http
GET /api/payments
```
*Requires authentication*

#### Get Payment
```http
GET /api/payments/{payment}
```
*Requires authentication*

#### Delete Payment
```http
DELETE /api/payments/{payment}
```
*Requires authentication*

### Invoice Management

#### Get Invoices
```http
GET /api/invoices
```
*Requires authentication*

#### Get Invoice
```http
GET /api/invoices/{invoice}
```
*Requires authentication*

#### Download Invoice
```http
GET /api/invoices/{order_id}/download
```
*Requires authentication*

#### Delete Invoice
```http
DELETE /api/invoices/{invoice}
```
*Requires authentication*

### Reports

#### Sales Summary
```http
GET /api/reports/sales-summary
```
*Requires authentication*

## Error Codes

- `200` - Success
- `201` - Created
- `401` - Unauthorized
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

## Order Status Flow

1. `pending` → Order received, awaiting processing
2. `preparing` → Order is being prepared
3. `ready` → Order is ready for pickup/delivery
4. `completed` → Order has been completed
5. `cancelled` → Order has been cancelled

## Rate Limiting

API endpoints are rate-limited to prevent abuse. Standard limits apply:
- 60 requests per minute per IP address
- 1000 requests per hour per authenticated user

## Testing

Use the provided test credentials:
- Email: `test@example.com`
- Password: `password`

Or register a new account using the `/api/register` endpoint.

## Support

For API support and questions, please contact the development team.
