# Implementation and Testing 


## Implementation
- Keep controllers thin and business logic in OrderService.
- Validate order requests using StoreOrderRequest.
- Format order responses using OrderResource.
- Store unit price and tax percentage in order_items as historical snapshots.
- Use a database transaction for order creation.
- Lock product rows in a consistent ID order before checking and deducting stock.
- Dispatch SendOrderConfirmation only after the database transaction commits.
- Read the low-stock threshold from LOW_STOCK_THRESHOLD, with a default of 10.

## Testing
Run all automated tests:

```bash
php artisan test
```

List discovered tests:

```bash
php artisan test --list-tests
```

The test suite covers order creation, total calculations, insufficient stock, last-unit rejection, unknown products, validation, duplicate product lines, customer lookup/history, low-stock filtering, and simulated order-confirmation logging.

## Concurrency Note
The automated last-unit test verifies sequential rejection when stock is exhausted. A separate parallel-request demonstration is needed to demonstrate actual concurrent requests.