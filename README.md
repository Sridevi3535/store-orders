# Store Order & Inventory Mini-System (Laravel)

A small retail-counter system: record customer orders against a product catalog and keep stock in sync.

## Requirements
PHP 8.2+, Composer, MySQL (InnoDB), PHP extensions: zip, fileinfo, pdo_mysql, pdo_sqlite (for tests).

## Setup
1. `composer install`
2. `copy .env.example .env` then `php artisan key:generate`
3. Create MySQL database `store_orders` and set the `DB_*` values in `.env`
4. `php artisan migrate --seed`
5. Terminal 1: `php artisan serve`
6. Terminal 2: `php artisan queue:work`
7. UI: http://127.0.0.1:8000/billing
8. Tests: `php artisan test`

Env: `LOW_STOCK_THRESHOLD` (default 10), `QUEUE_CONNECTION=database`.

## API
Send `Accept: application/json`.

| Method | URL | Purpose |
|---|---|---|
| POST | /api/orders | Create order |
| GET | /api/orders?email= | Order history for a customer (paginated, newest first) |
| GET | /api/products/low-stock?threshold= | Products below the threshold (default from config) |
| GET | /api/products | Product list (used by the UI) |
| GET | /api/customers/lookup?email= | Name auto-fill (used by the UI) |

Create order body:

    {
      "customer_email": "thomas@example.com",
      "customer_name": "Thomas",
      "items": [{ "product_id": 1, "quantity": 2 }],
      "amount_given": 200
    }

Responses: 201 with the created order; 422 for validation errors or insufficient stock
(body includes product_id, requested, available); 404 for an unknown customer in order history.

## Design decisions
- **Schema:** customers, products, orders, order_items. `order_items` snapshots unit price and tax %, so later price changes never alter old bills.
- **Layering:** thin controllers; business logic in `OrderService`; validation in `StoreOrderRequest`; output via `OrderResource`; the domain error `InsufficientStockException` renders a clean 422.
- **Concurrency:** the whole create-order flow runs in one DB transaction. Product rows are locked with `SELECT ... FOR UPDATE` (ordered by id to avoid deadlocks), and stock is checked and decremented while locked. If two requests compete for the last unit, the second waits for the first to commit, sees stock 0 and gets 422. Requires InnoDB (MySQL default). This is demonstrated live in the video with parallel requests; the automated test covers the sequential last-unit case.
- **Queue:** `SendOrderConfirmation` is dispatched only after the transaction commits, so a rolled-back order never triggers an email. It has retries/backoff and logs a simulated email to `storage/logs/laravel.log`.
- **Money:** decimal(...,2); tax is computed per line and rounded to 2 decimals; grand total = subtotal + tax.

## Assumptions
- "Low stock" means stock strictly below the threshold.
- Email is case-insensitive (lowercased). A new customer needs a name; for an existing customer the stored name is kept.
- The same product in multiple lines is merged into one line.
- `amount_given` is optional; if sent, it must be >= grand total, and the balance is returned.
- Wireframe figures are illustrative (they do not add up exactly); totals here are computed from data.
- The bill is shown on the page; PDF generation and emailing a PDF are out of scope (confirmation is the simulated queued job).
- No authentication (counter-system assumption); Sanctum would be added for production.
- Prices and stock are managed through seeders; there is no catalog admin API.

## AI usage
Built with AI assistance (Claude). Prompt screenshots are in the `/prompts` folder.