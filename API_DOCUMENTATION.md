# MarketCircle Backend API Documentation - COMPLETE VERSION

## Overview

This documentation provides comprehensive guidance for the frontend team to interact with the MarketCircle backend API. The backend is built with Laravel 11 and uses Laravel Sanctum for API authentication.

## Base Configuration

- **Base URL**: `https://fair-bat-perfectly.ngrok-free.app`
- **Authentication**: Laravel Sanctum (token-based)
- **Content-Type**: `application/json`
- **CORS**: Configured for `localhost:3000`, `localhost:5173`, and ngrok URLs

## Authentication

### Headers Required for Authenticated Requests
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### Guest Cart Management
For guest users (not authenticated), include:
```
X-Guest-Cart-Id: {cart_id}
```

## API Endpoints

### 1. Authentication Endpoints

#### Register User
```
POST /api/register
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone_number": "+1234567890",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "customer"
}
```

**Response (201):**
```json
{
  "status": "success",
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone_number": "+1234567890",
    "role": "customer",
    "created_at": "2025-07-01T10:00:00.000000Z",
    "updated_at": "2025-07-01T10:00:00.000000Z"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

#### Login User
```
POST /api/login
```

**Request Body:**
```json
{
  "identifier": "john@example.com",
  "password": "password123"
}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone_number": "+1234567890",
    "role": "customer",
    "created_at": "2025-07-01T10:00:00.000000Z",
    "updated_at": "2025-07-01T10:00:00.000000Z"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

#### Logout User
```
POST /api/logout
```
**Requires Authentication**

#### Get Current User
```
GET /api/user
```
**Requires Authentication**

### 2. Product Endpoints

#### Get All Products (Public)
```
GET /api/products
```

**Query Parameters:**
- `per_page`: Number of items per page (default: 15)
- `category`: Category slug for filtering
- `page`: Page number for pagination

**Example:**
```
GET /api/products?category=groceries&per_page=10&page=1&search=organic&featured=true
```

**Response (200):**
```json
{
  "message": "Products retrieved successfully.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "category_id": 1,
        "name": "Organic Apples",
        "slug": "organic-apples",
        "short_description": "Fresh organic apples",
        "description": "Delicious organic apples from local farms",
        "price_per_unit": "5.99",
        "discount_price": "4.99",
        "discount_percentage": 16.69,
        "discount_start_date": "2025-07-01T00:00:00.000000Z",
        "discount_end_date": "2025-07-31T23:59:59.000000Z",
        "unit_of_measure": "kg",
        "min_order_quantity": "0.50",
        "stock_quantity": "100.00",
        "is_featured": true,
        "is_active": true,
        "sku": "APPLE-ORG-001",
        "created_at": "2025-07-01T10:00:00.000000Z",
        "updated_at": "2025-07-01T10:00:00.000000Z",
        "main_image_url": "products/apple-main.jpg",
        "current_price": "4.99",
        "is_discounted": true,
        "discount_status": "active",
        "category": {
          "id": 1,
          "name": "Fruits",
          "slug": "fruits",
          "description": "Fresh fruits",
          "parent_id": null,
          "image_url": "categories/fruits.jpg",
          "is_active": true
        },
        "images": [
          {
            "id": 1,
            "product_id": 1,
            "image_url": "products/apple-main.jpg",
            "is_main_image": true
          },
          {
            "id": 2,
            "product_id": 1,
            "image_url": "products/apple-side.jpg",
            "is_main_image": false
          }
        ]
      }
    ],
    "first_page_url": "https://fair-bat-perfectly.ngrok-free.app/api/products?page=1",
    "from": 1,
    "last_page": 5,
    "last_page_url": "https://fair-bat-perfectly.ngrok-free.app/api/products?page=5",
    "links": [...],
    "next_page_url": "https://fair-bat-perfectly.ngrok-free.app/api/products?page=2",
    "path": "https://fair-bat-perfectly.ngrok-free.app/api/products",
    "per_page": 15,
    "prev_page_url": null,
    "to": 15,
    "total": 67
  }
}
```

#### Get Single Product (Public)
```
GET /api/products/{id}
```

**Response (200):**
```json
{
  "message": "Product retrieved successfully.",
  "data": {
    "id": 1,
    "category_id": 1,
    "name": "Organic Apples",
    "slug": "organic-apples",
    "short_description": "Fresh organic apples",
    "description": "Delicious organic apples from local farms...",
    "price_per_unit": "5.99",
    "discount_price": "4.99",
    "discount_percentage": 16.69,
    "discount_start_date": "2025-07-01T00:00:00.000000Z",
    "discount_end_date": "2025-07-31T23:59:59.000000Z",
    "unit_of_measure": "kg",
    "min_order_quantity": "0.50",
    "stock_quantity": "100.00",
    "is_featured": true,
    "is_active": true,
    "sku": "APPLE-ORG-001",
    "created_at": "2025-07-01T10:00:00.000000Z",
    "updated_at": "2025-07-01T10:00:00.000000Z",
    "main_image_url": "products/apple-main.jpg",
    "current_price": "4.99",
    "is_discounted": true,
    "discount_status": "active",
    "category": {
      "id": 1,
      "name": "Fruits",
      "slug": "fruits",
      "description": "Fresh fruits",
      "parent_id": null,
      "image_url": "categories/fruits.jpg",
      "is_active": true
    },
    "images": [
      {
        "id": 1,
        "product_id": 1,
        "image_url": "products/apple-main.jpg",
        "is_main_image": true
      },
      {
        "id": 2,
        "product_id": 1,
        "image_url": "products/apple-side.jpg",
        "is_main_image": false
      }
    ]
  }
}
```

#### Create Product (Admin Only)
```
POST /api/products
```
**Requires Admin Authentication**

**Request Body (multipart/form-data):**
```
category_id: 1
name: "Organic Bananas"
short_description: "Fresh organic bananas"
description: "Sweet organic bananas from Ecuador"
price_per_unit: 3.99
discount_price: 3.59
discount_percentage: 10
discount_start_date: "2025-07-01 00:00:00"
discount_end_date: "2025-07-31 23:59:59"
unit_of_measure: "kg"
min_order_quantity: 0.5
stock_quantity: 50
is_featured: true
is_active: true
sku: "BANANA-ORG-001"
images[]: [file1.jpg, file2.jpg]
main_image_index: 0
```

**Response (201):**
```json
{
  "message": "Product created successfully.",
  "data": {
    "id": 2,
    "category_id": 1,
    "name": "Organic Bananas",
    "slug": "organic-bananas",
    "short_description": "Fresh organic bananas",
    "description": "Sweet organic bananas from Ecuador",
    "price_per_unit": "3.99",
    "discount_price": "3.59",
    "discount_percentage": 10,
    "discount_start_date": "2025-07-01T00:00:00.000000Z",
    "discount_end_date": "2025-07-31T23:59:59.000000Z",
    "unit_of_measure": "kg",
    "min_order_quantity": "0.50",
    "stock_quantity": "50.00",
    "is_featured": true,
    "is_active": true,
    "sku": "BANANA-ORG-001",
    "created_at": "2025-07-01T10:00:00.000000Z",
    "updated_at": "2025-07-01T10:00:00.000000Z",
    "main_image_url": "products/banana-main.jpg",
    "current_price": "3.59",
    "is_discounted": true,
    "discount_status": "active",
    "category": {...},
    "images": [...]
  }
}
```

#### Update Product (Admin Only)
```
PUT /api/products/{id}
```
**Requires Admin Authentication**

**Request Body (multipart/form-data):**
```
category_id: 1
name: "Organic Bananas - Updated"
short_description: "Fresh organic bananas - Updated"
description: "Sweet organic bananas from Ecuador - Updated"
price_per_unit: 4.99
discount_price: 4.49
discount_percentage: 10
discount_start_date: "2025-07-01 00:00:00"
discount_end_date: "2025-07-31 23:59:59"
unit_of_measure: "kg"
min_order_quantity: 0.5
stock_quantity: 75
is_featured: true
is_active: true
sku: "BANANA-ORG-001"
images[]: [new_file1.jpg, new_file2.jpg]
main_image_index: 1
```

#### Delete Product (Admin Only)
```
DELETE /api/products/{id}
```
**Requires Admin Authentication**

**Response (200):**
```json
{
  "message": "Product deleted successfully."
}
```

### 3. Category Endpoints

#### Get All Categories (Public)
```
GET /api/categories
```

**Query Parameters:**
- `active_only`: Filter only active categories (`true`/`false`)
- `parent_only`: Filter only parent categories (`true`/`false`)

**Response (200):**
```json
{
  "message": "Categories retrieved successfully.",
  "data": [
    {
      "id": 1,
      "name": "Fruits",
      "slug": "fruits",
      "description": "Fresh fruits",
      "parent_id": null,
      "image_url": "categories/fruits.jpg",
      "is_active": true,
      "created_at": "2025-07-01T10:00:00.000000Z",
      "updated_at": "2025-07-01T10:00:00.000000Z",
      "parent": null,
      "children": [
        {
          "id": 2,
          "name": "Citrus Fruits",
          "slug": "citrus-fruits",
          "description": "Oranges, lemons, limes",
          "parent_id": 1,
          "image_url": "categories/citrus.jpg",
          "is_active": true,
          "created_at": "2025-07-01T10:00:00.000000Z",
          "updated_at": "2025-07-01T10:00:00.000000Z"
        }
      ]
    }
  ]
}
```

#### Get Single Category (Public)
```
GET /api/categories/{id}
```

**Response (200):**
```json
{
  "message": "Category retrieved successfully.",
  "data": {
    "id": 1,
    "name": "Fruits",
    "slug": "fruits",
    "description": "Fresh fruits",
    "parent_id": null,
    "image_url": "categories/fruits.jpg",
    "is_active": true,
    "created_at": "2025-07-01T10:00:00.000000Z",
    "updated_at": "2025-07-01T10:00:00.000000Z",
    "parent": null,
    "children": [...]
  }
}
```

#### Create Category (Admin Only)
```
POST /api/categories
```
**Requires Admin Authentication**

**Request Body (multipart/form-data):**
```
name: "Vegetables"
description: "Fresh vegetables"
parent_id: null
image: category_image.jpg
is_active: true
```

**Response (201):**
```json
{
  "message": "Category created successfully.",
  "data": {
    "id": 3,
    "name": "Vegetables",
    "slug": "vegetables",
    "description": "Fresh vegetables",
    "parent_id": null,
    "image_url": "categories/vegetables.jpg",
    "is_active": true,
    "created_at": "2025-07-01T10:00:00.000000Z",
    "updated_at": "2025-07-01T10:00:00.000000Z",
    "parent": null,
    "children": []
  }
}
```

#### Update Category (Admin Only)
```
PUT /api/categories/{id}
```
**Requires Admin Authentication**

#### Delete Category (Admin Only)
```
DELETE /api/categories/{id}
```
**Requires Admin Authentication**

### 4. Cart Endpoints

#### Get Cart
```
GET /api/cart
```
**Authentication Optional** (supports both authenticated users and guests)

**Headers for Guest Users:**
```
X-Guest-Cart-Id: {cart_id}
```

**Response (200):**
```json
{
  "message": "Cart retrieved successfully for authenticated user.",
  "data": {
    "id": 1,
    "user_id": 1,
    "status": "active",
    "created_at": "2025-07-01T10:00:00.000000Z",
    "updated_at": "2025-07-01T10:00:00.000000Z",
    "total_amount": "19.96",
    "items_count": 2,
    "items": [
      {
        "id": 1,
        "cart_id": 1,
        "product_id": 1,
        "product_name": "Organic Apples",
        "price_per_unit_at_addition": "4.99",
        "unit_of_measure_at_addition": "kg",
        "quantity": "2.00",
        "line_item_total": "9.98",
        "created_at": "2025-07-01T10:00:00.000000Z",
        "updated_at": "2025-07-01T10:00:00.000000Z",
        "product": {
          "id": 1,
          "name": "Organic Apples",
          "slug": "organic-apples",
          "price_per_unit": "5.99",
          "current_price": "4.99",
          "unit_of_measure": "kg",
          "stock_quantity": "100.00",
          "main_image_url": "products/apple-main.jpg",
          "is_active": true,
          "category": {...}
        }
      }
    ]
  },
  "guest_cart_id": null
}
```

**Response for Guest User (201 - New Cart Created):**
```json
{
  "message": "New guest cart created and retrieved successfully.",
  "data": {
    "id": 2,
    "user_id": null,
    "status": "active",
    "created_at": "2025-07-01T10:00:00.000000Z",
    "updated_at": "2025-07-01T10:00:00.000000Z",
    "total_amount": "0.00",
    "items_count": 0,
    "items": []
  },
  "guest_cart_id": 2
}
```

#### Add Item to Cart
```
POST /api/cart/add
```
**Authentication Optional**

**Request Body:**
```json
{
  "product_id": 1,
  "quantity": 2.5,
  "guest_cart_id": 2  // Include for guest users
}
```

**Response (200):**
```json
{
  "message": "Product added to cart successfully.",
  "data": {
    "cart": {
      "id": 1,
      "user_id": 1,
      "status": "active",
      "total_amount": "12.48",
      "items_count": 1,
      "items": [...]
    },
    "cart_item": {
      "id": 1,
      "cart_id": 1,
      "product_id": 1,
      "product_name": "Organic Apples",
      "price_per_unit_at_addition": "4.99",
      "unit_of_measure_at_addition": "kg",
      "quantity": "2.50",
      "line_item_total": "12.48",
      "created_at": "2025-07-01T10:00:00.000000Z",
      "updated_at": "2025-07-01T10:00:00.000000Z"
    }
  },
  "guest_cart_id": null
}
```

#### Update Cart Item
```
PUT /api/cart/update-item/{item_id}
```
**Authentication Optional**

**Request Body:**
```json
{
  "quantity": 3.0,
  "guest_cart_id": 2  // Include for guest users
}
```

**Response (200):**
```json
{
  "message": "Cart item updated successfully.",
  "data": {
    "cart": {...},
    "cart_item": {
      "id": 1,
      "cart_id": 1,
      "product_id": 1,
      "product_name": "Organic Apples",
      "price_per_unit_at_addition": "4.99",
      "unit_of_measure_at_addition": "kg",
      "quantity": "3.00",
      "line_item_total": "14.97",
      "created_at": "2025-07-01T10:00:00.000000Z",
      "updated_at": "2025-07-01T10:00:00.000000Z"
    }
  },
  "guest_cart_id": null
}
```

#### Remove Cart Item
```
DELETE /api/cart/remove-item/{item_id}
```
**Authentication Optional**

**Headers for Guest Users:**
```
X-Guest-Cart-Id: {cart_id}
```

**Response (200):**
```json
{
  "message": "Item removed from cart successfully.",
  "data": {
    "cart": {
      "id": 1,
      "user_id": 1,
      "status": "active",
      "total_amount": "0.00",
      "items_count": 0,
      "items": []
    }
  },
  "guest_cart_id": null
}
```

#### Clear Cart
```
POST /api/cart/clear
```
**Authentication Optional**

**Request Body for Guest Users:**
```json
{
  "guest_cart_id": 2
}
```

**Response (200):**
```json
{
  "message": "Cart cleared successfully.",
  "data": {
    "cart": {
      "id": 1,
      "user_id": 1,
      "status": "active",
      "total_amount": "0.00",
      "items_count": 0,
      "items": []
    }
  },
  "guest_cart_id": null
}
```

### 5. Address Management

#### Get User Addresses
```
GET /api/addresses
```
**Requires Authentication**

**Response (200):**
```json
{
  "message": "Addresses retrieved successfully.",
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "type": "home",
      "first_name": "John",
      "last_name": "Doe",
      "company": null,
      "address_line_1": "123 Main St",
      "address_line_2": "Apt 4B",
      "city": "New York",
      "state": "NY",
      "postal_code": "10001",
      "country": "USA",
      "phone_number": "+1234567890",
      "is_default": true,
      "created_at": "2025-07-01T10:00:00.000000Z",
      "updated_at": "2025-07-01T10:00:00.000000Z"
    }
  ]
}
```

#### Create Address
```
POST /api/addresses
```
**Requires Authentication**

**Request Body:**
```json
{
  "type": "home",
  "first_name": "John",
  "last_name": "Doe",
  "company": "Tech Corp",
  "address_line_1": "123 Main St",
  "address_line_2": "Apt 4B",
  "city": "New York",
  "state": "NY",
  "postal_code": "10001",
  "country": "USA",
  "phone_number": "+1234567890",
  "is_default": true
}
```

#### Update Address
```
PUT /api/addresses/{id}
```
**Requires Authentication**

#### Delete Address
```
DELETE /api/addresses/{id}
```
**Requires Authentication**

#### Set Default Address
```
POST /api/addresses/{id}/set-default
```
**Requires Authentication**

### 6. Delivery Options

#### Get Available Delivery Options
```
GET /api/delivery-options
```
**Authentication Optional**

**Query Parameters:**
- `postal_code`: Filter by delivery area
- `city`: Filter by city
- `state`: Filter by state

**Response (200):**
```json
{
  "message": "Delivery options retrieved successfully.",
  "data": [
    {
      "id": 1,
      "name": "Standard Delivery",
      "description": "Delivery within 2-3 business days",
      "price": "5.99",
      "estimated_days": 3,
      "is_active": true,
      "delivery_zones": [
        {
          "id": 1,
          "name": "Zone 1",
          "postal_codes": ["10001", "10002", "10003"],
          "cities": ["New York"],
          "states": ["NY"]
        }
      ]
    },
    {
      "id": 2,
      "name": "Express Delivery",
      "description": "Same-day delivery",
      "price": "12.99",
      "estimated_days": 0,
      "is_active": true,
      "delivery_zones": [...]
    }
  ]
}
```

#### Get Single Delivery Option
```
GET /api/delivery-options/{id}
```

#### Create Delivery Option (Admin Only)
```
POST /api/delivery-options
```
**Requires Admin Authentication**

#### Update Delivery Option (Admin Only)
```
PUT /api/delivery-options/{id}
```
**Requires Admin Authentication**

#### Delete Delivery Option (Admin Only)
```
DELETE /api/delivery-options/{id}
```
**Requires Admin Authentication**

### 7. Order Management

#### Get User Orders
```
GET /api/orders
```
**Requires Authentication**

**Query Parameters:**
- `status`: Filter by order status
- `per_page`: Number of orders per page
- `page`: Page number

**Response (200):**
```json
{
  "message": "Orders retrieved successfully.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "user_id": 1,
        "order_number": "ORD-2025-001",
        "status": "pending",
        "subtotal": "25.96",
        "tax_amount": "2.08",
        "delivery_fee": "5.99",
        "total_amount": "34.03",
        "currency": "USD",
        "payment_status": "pending",
        "payment_method": "card",
        "delivery_option_id": 1,
        "estimated_delivery_date": "2025-07-04T00:00:00.000000Z",
        "notes": "Please ring the doorbell",
        "created_at": "2025-07-01T10:00:00.000000Z",
        "updated_at": "2025-07-01T10:00:00.000000Z",
        "delivery_option": {
          "id": 1,
          "name": "Standard Delivery",
          "price": "5.99",
          "estimated_days": 3
        },
        "delivery_address": {
          "id": 1,
          "first_name": "John",
          "last_name": "Doe",
          "address_line_1": "123 Main St",
          "address_line_2": "Apt 4B",
          "city": "New York",
          "state": "NY",
          "postal_code": "10001",
          "country": "USA",
          "phone_number": "+1234567890"
        },
        "items": [
          {
            "id": 1,
            "order_id": 1,
            "product_id": 1,
            "product_name": "Organic Apples",
            "product_sku": "APPLE-ORG-001",
            "price_per_unit": "4.99",
            "quantity": "2.00",
            "unit_of_measure": "kg",
            "line_total": "9.98",
            "product_snapshot": {
              "name": "Organic Apples",
              "description": "Fresh organic apples",
              "image_url": "products/apple-main.jpg",
              "category": "Fruits"
            }
          }
        ]
      }
    ],
    "first_page_url": "...",
    "from": 1,
    "last_page": 3,
    "last_page_url": "...",
    "next_page_url": "...",
    "path": "...",
    "per_page": 10,
    "prev_page_url": null,
    "to": 10,
    "total": 25
  }
}
```

#### Get Single Order
```
GET /api/orders/{id}
```
**Requires Authentication**

#### Create Order
```
POST /api/orders
```
**Requires Authentication**

**Request Body:**
```json
{
  "delivery_address_id": 1,
  "delivery_option_id": 1,
  "payment_method": "card",
  "notes": "Please ring the doorbell",
  "cart_items": [
    {
      "product_id": 1,
      "quantity": 2.0,
      "price_per_unit": "4.99"
    }
  ]
}
```

**Response (201):**
```json
{
  "message": "Order created successfully.",
  "data": {
    "id": 1,
    "order_number": "ORD-2025-001",
    "status": "pending",
    "subtotal": "25.96",
    "tax_amount": "2.08",
    "delivery_fee": "5.99",
    "total_amount": "34.03",
    "currency": "USD",
    "payment_status": "pending",
    "payment_method": "card",
    "delivery_option_id": 1,
    "delivery_address_id": 1,
    "estimated_delivery_date": "2025-07-04T00:00:00.000000Z",
    "notes": "Please ring the doorbell",
    "created_at": "2025-07-01T10:00:00.000000Z",
    "updated_at": "2025-07-01T10:00:00.000000Z",
    "delivery_option": {...},
    "delivery_address": {...},
    "items": [...]
  }
}
```

#### Update Order Status (Admin Only)
```
PUT /api/orders/{id}/status
```
**Requires Admin Authentication**

**Request Body:**
```json
{
  "status": "processing",
  "notes": "Order is being prepared"
}
```

#### Cancel Order
```
POST /api/orders/{id}/cancel
```
**Requires Authentication**

**Request Body:**
```json
{
  "reason": "Changed my mind"
}
```

#### Get Order Tracking
```
GET /api/orders/{id}/tracking
```
**Requires Authentication**

**Response (200):**
```json
{
  "message": "Order tracking retrieved successfully.",
  "data": {
    "order_id": 1,
    "order_number": "ORD-2025-001",
    "current_status": "processing",
    "estimated_delivery_date": "2025-07-04T00:00:00.000000Z",
    "tracking_events": [
      {
        "status": "pending",
        "description": "Order placed successfully",
        "timestamp": "2025-07-01T10:00:00.000000Z"
      },
      {
        "status": "confirmed",
        "description": "Order confirmed and being prepared",
        "timestamp": "2025-07-01T10:30:00.000000Z"
      },
      {
        "status": "processing",
        "description": "Items are being picked and packed",
        "timestamp": "2025-07-01T11:00:00.000000Z"
      }
    ]
  }
}
```

### 8. Payment Methods

#### Get Available Payment Methods
```
GET /api/payment-methods
```
**Authentication Optional**

**Response (200):**
```json
{
  "message": "Payment methods retrieved successfully.",
  "data": [
    {
      "id": 1,
      "name": "Credit/Debit Card",
      "type": "card",
      "description": "Pay with your credit or debit card",
      "is_active": true,
      "supported_cards": ["visa", "mastercard", "amex"],
      "processing_fee": "2.9"
    },
    {
      "id": 2,
      "name": "Cash on Delivery",
      "type": "cod",
      "description": "Pay when your order is delivered",
      "is_active": true,
      "processing_fee": "0.0"
    },
    {
      "id": 3,
      "name": "Digital Wallet",
      "type": "wallet",
      "description": "Pay with your digital wallet",
      "is_active": true,
      "processing_fee": "1.5"
    }
  ]
}
```

#### Process Payment
```
POST /api/payments/process
```
**Requires Authentication**

**Request Body:**
```json
{
  "order_id": 1,
  "payment_method": "card",
  "payment_details": {
    "card_number": "4111111111111111",
    "expiry_month": "12",
    "expiry_year": "2025",
    "cvv": "123",
    "card_holder_name": "John Doe"
  }
}
```

#### Get Payment Status
```
GET /api/payments/{payment_id}/status
```
**Requires Authentication**

### 9. Customer Management (Protected)

#### Get All Customers (Admin Only)
```
GET /api/customers
```
**Requires Admin Authentication**

#### Get Single Customer
```
GET /api/customers/{id}
```
**Requires Authentication**

#### Create Customer Profile
```
POST /api/customers
```
**Requires Authentication**

#### Update Customer Profile
```
PUT /api/customers/{id}
```
**Requires Authentication**

#### Delete Customer Profile
```
DELETE /api/customers/{id}
```
**Requires Authentication**

### 10. Notifications

#### Get User Notifications
```
GET /api/notifications
```
**Requires Authentication**

**Response (200):**
```json
{
  "message": "Notifications retrieved successfully.",
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "type": "order_status",
      "title": "Order Update",
      "message": "Your order ORD-2025-001 has been confirmed",
      "data": {
        "order_id": 1,
        "order_number": "ORD-2025-001",
        "status": "confirmed"
      },
      "read_at": null,
      "created_at": "2025-07-01T10:30:00.000000Z",
      "updated_at": "2025-07-01T10:30:00.000000Z"
    }
  ]
}
```

#### Mark Notification as Read
```
POST /api/notifications/{id}/read
```
**Requires Authentication**

#### Mark All Notifications as Read
```
POST /api/notifications/read-all
```
**Requires Authentication**

### 11. Admin Dashboard (Admin Only)

#### Get Admin Dashboard
```
GET /api/admin/dashboard
```
**Requires Admin Authentication**

#### Get Admin Orders
```
GET /api/admin/orders
```
**Requires Admin Authentication**

#### Get Admin Analytics
```
GET /api/admin/analytics
```
**Requires Admin Authentication**

### 12. Utility Endpoints

#### API Test
```
GET /api/test
```

#### Health Check
```
GET /api/health
```

## Complete Data Models

### User Model
```javascript
{
  id: number,
  name: string,
  email: string | null,
  phone_number: string | null,
  role: 'customer' | 'admin',
  email_verified_at: string | null,
  created_at: string,
  updated_at: string
}
```

### Address Model
```javascript
{
  id: number,
  user_id: number,
  type: 'home' | 'work' | 'other',
  first_name: string,
  last_name: string,
  company: string | null,
  address_line_1: string,
  address_line_2: string | null,
  city: string,
  state: string,
  postal_code: string,
  country: string,
  phone_number: string,
  is_default: boolean,
  created_at: string,
  updated_at: string
}
```

### DeliveryOption Model
```javascript
{
  id: number,
  name: string,
  description: string,
  price: string, // decimal as string
  estimated_days: number,
  is_active: boolean,
  created_at: string,
  updated_at: string,
  delivery_zones: DeliveryZone[]
}
```

### DeliveryZone Model
```javascript
{
  id: number,
  delivery_option_id: number,
  name: string,
  postal_codes: string[], // JSON array
  cities: string[], // JSON array
  states: string[], // JSON array
  created_at: string,
  updated_at: string
}
```

### Order Model
```javascript
{
  id: number,
  user_id: number,
  order_number: string,
  status: 'pending' | 'confirmed' | 'processing' | 'shipped' | 'delivered' | 'cancelled',
  subtotal: string, // decimal as string
  tax_amount: string, // decimal as string
  delivery_fee: string, // decimal as string
  total_amount: string, // decimal as string
  currency: string,
  payment_status: 'pending' | 'paid' | 'failed' | 'refunded',
  payment_method: 'card' | 'cod' | 'wallet',
  delivery_option_id: number,
  delivery_address_id: number,
  estimated_delivery_date: string | null,
  actual_delivery_date: string | null,
  notes: string | null,
  created_at: string,
  updated_at: string,
  
  // Relationships
  user: User,
  delivery_option: DeliveryOption,
  delivery_address: Address,
  items: OrderItem[]
}
```

### OrderItem Model
```javascript
{
  id: number,
  order_id: number,
  product_id: number,
  product_name: string, // snapshot
  product_sku: string | null, // snapshot
  price_per_unit: string, // decimal as string - snapshot
  quantity: string, // decimal as string
  unit_of_measure: string, // snapshot
  line_total: string, // decimal as string
  product_snapshot: object, // JSON object with product details at order time
  created_at: string,
  updated_at: string
}
```

### PaymentMethod Model
```javascript
{
  id: number,
  name: string,
  type: 'card' | 'cod' | 'wallet',
  description: string,
  is_active: boolean,
  processing_fee: string, // decimal as string
  supported_cards: string[] | null, // JSON array for card types
  created_at: string,
  updated_at: string
}
```

### Notification Model
```javascript
{
  id: number,
  user_id: number,
  type: 'order_status' | 'promotion' | 'system',
  title: string,
  message: string,
  data: object | null, // JSON object with additional data
  read_at: string | null,
  created_at: string,
  updated_at: string
}
```

## Order Status Flow

```
pending → confirmed → processing → shipped → delivered
    ↓
cancelled (can be cancelled from pending, confirmed, or processing)
```

## Payment Status Flow

```
pending → paid → (refunded if needed)
    ↓
failed (can retry payment)
```

## Important Implementation Notes

### Order Creation Process
1. Validate cart items and availability
2. Calculate totals (subtotal, tax, delivery fee)
3. Create order with "pending" status
4. Process payment
5. Update order status based on payment result
6. Send confirmation notification
7. Clear user's cart

### Address Management
- Users can have multiple addresses
- One address can be set as default
- Delivery address is snapshotted at order time
- Address validation should be implemented on frontend

### Delivery Options
- Different delivery options for different zones
- Delivery fees can vary by location
- Estimated delivery dates calculated from order date + estimated days
- Some options might not be available for all locations

### Payment Processing
- Payment details should be handled securely
- Never store full credit card details
- Use payment gateway integration
- Handle payment failures gracefully

### Notifications
- Real-time notifications for order updates
- Email/SMS notifications (if configured)
- Push notifications for mobile apps
- Notification preferences management


