# Order and Payment Management API

A Laravel JSON API for managing a user's orders and simulated payments. It uses JWT bearer authentication and a configuration-backed Strategy registry so payment methods can be added independently of the payment endpoint.

## Table of Contents

- [Order and Payment Management API](#order-and-payment-management-api)
  - [Table of Contents](#table-of-contents)
  - [Requirements and setup](#requirements-and-setup)
  - [Authentication and API conventions](#authentication-and-api-conventions)
    - [Example requests and responses](#example-requests-and-responses)
  - [Payment gateway extension](#payment-gateway-extension)
  - [Technical choices](#technical-choices)
  - [Tests and Postman](#tests-and-postman)

## Requirements and setup

- PHP 8.3 or newer with PDO SQLite enabled
- Composer

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

The default database is SQLite (`database/database.sqlite`). Set `DB_CONNECTION`, database credentials, and the other standard Laravel database variables in `.env` to use another supported database.

Set `JWT_SECRET` to a long random secret in production. If it is empty, the generated Laravel `APP_KEY` is used. Tokens use HS256 and expire after `JWT_TTL_MINUTES` (60 by default). Configure provider credentials in `.env`; never commit real credentials.

## Authentication and API conventions

Register at `POST /api/auth/register` or log in at `POST /api/auth/login`. Both return an `access_token`, `token_type`, `expires_in`, and user. Send protected requests with:

```http
Authorization: Bearer <access_token>
Accept: application/json
```

Validation failures return `422` with field-level `errors`. Missing or invalid tokens return `401`. Resource lookups are limited to the authenticated user's data; inaccessible IDs return `404`.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/auth/register` | Create account and issue token |
| POST | `/api/auth/login` | Verify credentials and issue token |
| GET | `/api/orders?status=pending&per_page=15` | Paginated order list, optional status filter |
| POST | `/api/orders` | Create a pending order and calculate its total |
| GET | `/api/orders/{id}` | Retrieve an order and its items |
| PATCH / PUT | `/api/orders/{id}` | Update order details, status, and/or replace items |
| DELETE | `/api/orders/{id}` | Delete only when the order has no payments |
| GET | `/api/payments?order_id={id}&per_page=15` | Paginated payments, optionally filtered by order |
| POST | `/api/payments` | Process a payment for a confirmed order |

List endpoints default to 15 results and accept `per_page` from 1 to 100. New orders start as `pending`; update `status` to `confirmed` before processing payment. Supported statuses are `pending`, `confirmed`, and `cancelled`. Payment methods are `credit_card` and `paypal`; payment statuses are `pending`, `successful`, and `failed`.

### Example requests and responses

Register:

```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "Sam Example",
  "email": "sam@example.com",
  "password": "correct-horse-battery",
  "password_confirmation": "correct-horse-battery"
}
```

```json
{
  "token_type": "Bearer",
  "access_token": "<signed-jwt>",
  "expires_in": 3600,
  "user": { "id": 1, "name": "Sam Example", "email": "sam@example.com" }
}
```

Create an order (returns `201`; the total is calculated by the server):

```http
POST /api/orders
Authorization: Bearer <access_token>
Content-Type: application/json

{
  "customer_name": "Sam Example",
  "customer_email": "sam@example.com",
  "items": [
    { "product_name": "Notebook", "quantity": 2, "unit_price": "12.50" },
    { "product_name": "Pen", "quantity": 3, "unit_price": "1.20" }
  ]
}
```

```json
{
  "data": {
    "id": 1,
    "status": "pending",
    "total_amount": "28.60",
    "items": [
      { "product_name": "Notebook", "quantity": 2, "unit_price": "12.50" },
      { "product_name": "Pen", "quantity": 3, "unit_price": "1.20" }
    ]
  }
}
```

Confirm the order with `PATCH /api/orders/1` and `{"status":"confirmed"}`, then process payment:

```http
POST /api/payments
Authorization: Bearer <access_token>
Content-Type: application/json

{ "order_id": 1, "payment_method": "credit_card" }
```

A successful simulated payment returns `201`, with `status: "successful"`, its amount, payment method, and a generated transaction reference. Attempting payment before confirmation returns `409`:

```json
{ "message": "Payments can only be processed for confirmed orders." }
```

An unsupported method returns `422`:

```json
{
  "message": "The selected payment method is not supported.",
  "errors": { "payment_method": ["The selected payment method is not supported."] }
}
```

Deleting an order with a payment returns `409`; deleting an order without payments returns `204`.

## Payment gateway extension

The gateway design uses the **Strategy pattern**. `PaymentGateway` is the stable contract; `PaymentGatewayManager` looks up the method in `config/payment.php`, resolves its strategy, and validates its result. `PaymentController` does not contain provider-specific branches.

To add a method:

1. Implement `App\Payments\PaymentGateway`, including the `process(Order $order, array $credentials)` operation.
2. Add a key, driver class, and provider configuration under `gateways` in `config/payment.php`.
3. Add provider secrets to `.env` and reference them with `env()` only from the config file.
4. Add unit/feature coverage for success and failure behavior.

The included credit-card and PayPal strategies are **simulations**: they do not call a payment provider and always return success with a generated reference. Do not use this as a production payment integration. A real adapter should use the provider's official SDK, handle provider errors/timeouts, and never store card numbers or security codes. Credentials are passed to the strategy but intentionally unused by the simulations.

## Technical choices

- **Laravel 13 / PHP 8.3+:** required by the brief; routing, validation, ORM, migrations, transactions, password hashing, and test infrastructure come from the framework rather than custom implementations.
- **Eloquent models and migrations:** make order ownership and item/payment relationships explicit, enforce foreign keys in the database, and keep the persistence setup reproducible.
- **Firebase PHP-JWT:** small library focused on JWT signing/verification. The application owns the token claims and middleware, avoiding coupling domain routes to a separate authentication package.
- **SQLite by default:** no separate database service is needed for a local demonstration; Laravel can be configured for MySQL or PostgreSQL.
- **PHPUnit / Laravel feature testing:** exercises HTTP behavior, validation, authorization, rules, persistence, and gateway selection without external services.
- **Postman collection:** provides an importable, grouped API starting point with environment variables for the base URL and bearer token.
- **No payment SDK:** payments are simulations in this task. Installing provider packages before selecting a real provider would add unused dependencies and credentials without enabling real payment processing.

## Tests and Postman

Run the automated suite:

```powershell
php artisan test
```

Import [`postman/Order-Payment-API.postman_collection.json`](postman/Order-Payment-API.postman_collection.json) into Postman. Set `baseUrl` (default `http://localhost:8000`) and `token`; first call Register or Login and copy `access_token` into `token`.
