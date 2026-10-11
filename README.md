# E-commerce API

A RESTful e-commerce API built with Laravel, PHP, and PostgreSQL. It supports product browsing, authenticated shopping carts, promotions, checkout, order management, and cancellation.

## Requirements

* PHP 8.2+
* Composer
* PostgreSQL
* Laravel-compatible PHP extensions

## Setup

1. Install dependencies:

   ```bash
   composer install
   ```

2. Create your environment file:

   ```bash
   cp .env.example .env
   ```

3. Configure your database settings in `.env`:

   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=ecommerce
   DB_USERNAME=postgres
   DB_PASSWORD=your_password
   ```

4. Generate the application key:

   ```bash
   php artisan key:generate
   ```

5. Create the database named `ecommerce` in PostgreSQL.

6. Run migrations and seed sample data:

   ```bash
   php artisan migrate --seed
   ```

7. Start the development server:

   ```bash
   php artisan serve
   ```

The API is available at `http://127.0.0.1:8000/api`.

## Authentication

Login using `POST /api/login` with an email and password. Use the returned Sanctum token for protected endpoints:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

A seeded test account is available if configured by the database seeder:

* Email: `test@example.com`
* Password: `password`

Use test credentials only in local development.

## API endpoints

| Method | Endpoint                  | Description                                      | Authentication |
| ------ | ------------------------- | ------------------------------------------------ | -------------- |
| POST   | `/api/login`              | Log in and obtain a token                        | No             |
| GET    | `/api/products`           | List, search, filter, sort and paginate products | No             |
| GET    | `/api/products/{id}`      | Get a product                                    | No             |
| GET    | `/api/cart`               | View the authenticated customer's cart           | Yes            |
| POST   | `/api/cart/items`         | Add an item to the cart                          | Yes            |
| PATCH  | `/api/cart/items/{id}`    | Update a cart item's quantity                    | Yes            |
| DELETE | `/api/cart/items/{id}`    | Remove a cart item                               | Yes            |
| POST   | `/api/checkout`           | Create an order, optionally applying a promotion | Yes            |
| PATCH  | `/api/orders/{id}/cancel` | Cancel an eligible order                         | Yes            |

### Product filters

`GET /api/products` supports:

* `search` — search by product name
* `min_price` and `max_price` — price boundaries
* `availability` — `in_stock` or `out_of_stock`
* `sort_by` — `name`, `price`, `stock_quantity`, or `created_at`
* `sort_direction` — `asc` or `desc`
* `page` — pagination page

Example:

`GET /api/products?search=shirt&min_price=500&sort_by=price&sort_direction=asc`

Prices in this project are stored as integer minor units (for example, cents) to avoid floating-point arithmetic issues.

### Add to cart

`POST /api/cart/items`

```json
{
  "product_id": 1,
  "quantity": 2
}
```

### Checkout

`POST /api/checkout`

```json
{
  "promotion_code": "SAVE20"
}
```

The `promotion_code` field is optional. Prices, stock, discounts, and totals are calculated on the server; client-submitted totals are not trusted.

## Architecture

* Controllers handle HTTP requests, request validation, and responses.
* Services encapsulate authentication, promotion validation, and checkout business logic.
* Eloquent models represent products, carts, promotions, orders, and related records.
* Migrations define database tables, foreign keys, uniqueness constraints, and relationships.
* API authentication uses Laravel Sanctum bearer tokens.

### API Rate Limiting

Rate limits are applied to reduce repeated requests and help protect sensitive endpoints:

- **Login:** 5 attempts per minute per email and IP combination.
- **Checkout:** 10 requests per minute per authenticated user, falling back to IP for unauthenticated requests.

Requests exceeding a configured limit receive HTTP `429 Too Many Requests`. Rate limiting helps reduce abuse but does not replace authentication, authorization, or other security controls.

### Database Indexing

Added indexes to support common queries and reduce unnecessary row scans as the database grows:

- `orders.user_id`: supports retrieving orders belonging to a customer.
- `order_items.order_id`: supports loading order items for a given order.
- `promotion_usages(promotion_id, user_id)`: supports checking global and per-customer promotion usage.

Existing primary keys, unique constraints, and other indexes are retained. Indexes improve read performance for matching queries but add storage and write-maintenance overhead, so additional indexes should be introduced based on actual query patterns and query-plan analysis.

## Checkout and data integrity

Checkout runs inside a database transaction. It validates the cart and available inventory, recalculates prices, validates promotions, calculates totals, reduces stock, creates the order and order-item snapshots, records promotion usage, and clears the cart. A failure rolls back the transaction.

Product rows are locked during checkout to help prevent overselling when concurrent requests compete for inventory. Promotion usage limits must also be protected against concurrent checkouts.

Order items preserve the product name, SKU, price, and quantity at purchase time, so later product changes do not rewrite historical order details.

Cancellation is allowed only for eligible orders owned by the authenticated customer. Stock is restored as part of the cancellation transaction, and cancelled orders cannot be cancelled again.

## Testing

Run the automated test suite:

```bash
php artisan test
```

## Trade-offs and future improvements

Potential extensions include checkout idempotency keys, Redis caching, queued order events, rate limiting, containerized development, and additional concurrency integration tests.
