# E-commerce API

This project is an e-commerce API built with Laravel 11 and PHP 8.2. It provides endpoints for managing products, categories, orders, and user authentication.

## Table of Contents

1. [Installation](#installation)
   - [Normal Installation](#normal-installation)
   - [Installation with Laravel Sail](#installation-with-laravel-sail)
<!-- 2. [Database Setup](#database-setup)
   - [SQLite Setup](#sqlite-setup)
   - [MySQL Setup](#mysql-setup) -->
2. [API Documentation](#api-documentation)
3. [Code Structure](#code-structure)

## Installation

### Normal Installation

1. Clone the repository:
   ```
   git clone https://github.com/kh-hossam/e-commerce-api.git
   ```

2. Navigate to the project directory:
   ```
   cd your-repo-name
   ```

3. Install dependencies:
   ```
   composer install
   ```

4. Copy the `.env.example` file to `.env`:
   ```
   cp .env.example .env
   ```

5. Generate application key:
   ```
   php artisan key:generate
   ```
6. Database Setup

    #### SQLite Setup

    1. Create a new SQLite database file:
    ```
    touch database/database.sqlite
    ```

    #### MySQL Setup

    1. Create a new MySQL database.

    2. Update the `.env` file:
    ```
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=your_database_name
    DB_USERNAME=your_database_username
    DB_PASSWORD=your_database_password
    ```

    After setting up the database, run migrations and seeders:

    ```
    php artisan migrate --seed
    ```

### Installation with Laravel Sail

1. Clone the repository:
   ```
   git clone https://github.com/your-username/your-repo-name.git
   ```

2. Navigate to the project directory:
   ```
   cd your-repo-name
   ```

3. Install composer dependencies:
    ```
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        laravelsail/php82-composer:latest \
        composer install --ignore-platform-reqs
    ```

4. Start the Docker containers:
   ```
   ./vendor/bin/sail up -d
   ```

5. Install dependencies:
   ```
   ./vendor/bin/sail composer install
   ```

6. Copy the `.env.example` file to `.env`:
   ```
   cp .env.example .env
   ```

7. Generate application key:
   ```
   ./vendor/bin/sail artisan key:generate
   ```

8. Run the database migrations and seed the database:

    ```
    ./vendor/bin/sail php artisan migrate --seed
    ```

## API Documentation

A Postman collection for all API endpoints is available in the project's root directory.

Api Prefix: api/v1

run the project (or you could use whatever local environment you are using):
   ```
   php artisan serve
   ```

### Authentication

- `POST /api/v1/register`: Register a new user
- `POST /api/v1/login`: Log in a user
- `GET /api/v1/user`: Get authenticated user details
- `POST /api/v1/logout`: Log out the authenticated user

    #### Register/Login Body example

    ```json
    {
        "name" : "khaled",
        "email": "khaled@gmail.com",
        "password": "12345678",
        "passwrod_confirmation": "12345678"
    }

    {
        "email": "khaled@example.com",
        "password": "password"
    }
    ```

### Categories

- `GET /api/v1/categories`: List all categories
  - Query String: `page` (for pagination)
- `POST /api/v1/categories`: Create a new category
- `GET /api/v1/categories/{category}`: Get a specific category
- `PUT /api/v1/categories/{category}`: Update a category
- `DELETE /api/v1/categories/{category}`: Delete a category
    - note: category can't be removed if it contains products

    #### Create/Update category example

    ```json
    {
        "name": "Laptops"
    }
    ```

### Products

- `GET /api/v1/products`: List all products
  - Filters: `name`, `price_min`, `price_max`, `page` (for pagination)
- `POST /api/v1/products`: Create a new product
- `GET /api/v1/products/{product}`: Get a specific product
- `PUT /api/v1/products/{product}`: Update a product
- `DELETE /api/v1/products/{product}`: Delete a product
    - soft delete is used to keep history
    
    #### Create/Update Product Body example

    ```json
    {
        "name" : "Asus zephyrus g16",
        "price" : 2500,
        "stock" : 10,
        "category_id" : 1
    }
    ```

### Orders

- `GET /api/v1/orders`: List all orders for the authenticated user
  - Query String: `page` (for pagination)
- `POST /api/v1/orders`: Create a new order
- `GET /api/v1/orders/{order}`: Get a specific order
- `PUT /api/v1/orders/{order}`: Update an order
    - this update order api update both product stock and order quantity and handles 4 scenarios
        - product quantity increased  
        - product quantity decreased 
        - same product quantity
        - if product removed from request it will be removed from order and product stock is updated
- `DELETE /api/v1/orders/{order}`: Delete an order
    - soft delete is used to keep history

#### Note: in show, update and delete Apis we use Authorization through Policies to make sure that only user which owns the order is the one making the action
    #### Create/Update Order Body

    ```json
    {
        "products": [
            {
                "product_id": 1,
                "quantity": 1
            },
            {
                "product_id": 2,
                "quantity": 3
            }
        ]
    }
    ```

    ## Code Structure

The project follows a standard Laravel structure with the following key components:

- `app/Http/Controllers`: Contains the API controllers
- `app/Http/Requests`: Contains form request validation classes
- `app/Http/Resources`: Contains API resource classes for JSON responses
- `app/Models`: Contains Eloquent model classes
- `app/Services`: Contains service classes for business logic
- `app/Policies`: Contains authorization policies
- `app/Events`: Contains defined events
- `app/Listeners`: Contains defined listeners
- `app/Exceptions`: Contains custom exception classes
- `database/migrations`: Contains database migration files
- `database/factories`: Contains database factories classes
- `database/seeders`: Contains database seeder classes
- `routes/api.php`: Contains API route definitions
- `tests/Feature`: Contains Feature Tests for OrderFeatureTest, ProductFeatureTest
- `tests/Unit`: Contains Unit Test for OrderServiceTest

Key files:

- `ProductController.php`: Handles CRUD operations for products
- `OrderController.php`: Handles CRUD operations for orders
- `ProductService.php`: Contains business logic for product operations
- `OrderService.php`: Contains business logic for order operations
- `OrderFeatureTest.php`: Contains feature test for order operations
- `ProductFeatureTest.php`: Contains feature test for order operations
- `OrderServiceTest.php`: Contains unit test for order service

The project uses Laravel's built-in features such as Eloquent ORM, form request validation, API resources, and policies for authorization.
