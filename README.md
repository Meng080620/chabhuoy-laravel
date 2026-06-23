# Chabhuoy Marketplace API

A multi-vendor marketplace API built on **Laravel 13 / PHP 8.3**, with token auth
(Sanctum), role-scoped abilities, and a service + repository architecture.

Customers browse a catalogue, cart, and check out; vendors manage their own
products and fulfil their own order lines; admins moderate vendors and read
platform reports.

## Stack

- Laravel 13.8 · PHP 8.3 · PostgreSQL
- Laravel Sanctum (bearer tokens, ability-scoped per role)
- PHPUnit 12 · Pint

## Setup

```bash
composer install
cp .env.example .env && php artisan key:generate
# configure the pgsql connection in .env, then:
php artisan migrate
php artisan serve   # http://localhost:8000
```

## Tests

```bash
php artisan test          # full suite
./vendor/bin/pint         # format
```

## API documentation

The full API is described in [`docs/openapi.yaml`](docs/openapi.yaml)
(OpenAPI 3.1). View or explore it:

- **Swagger UI / Redoc** — paste the file into <https://editor.swagger.io>
- **Postman / Insomnia** — *Import → File → `docs/openapi.yaml`* generates a
  ready-to-run collection
- **Local Redoc** — `npx @redocly/cli preview-docs docs/openapi.yaml`

### Auth flow

1. `POST /api/register` or `POST /api/login` → returns a bearer `token`.
2. Send `Authorization: Bearer <token>` on authenticated routes.
3. Token abilities are scoped by role: customer (`cart:manage`, `orders:manage`),
   vendor (+`vendor:manage`), admin (`*`).

### Route map

| Area | Routes |
|------|--------|
| Auth | `register`, `login`, `logout`, `me` |
| Catalogue (public) | `GET products`, `GET products/{uuid}`, `GET categories`, `GET categories/{slug}` |
| Cart | `GET/PUT cart`, `DELETE cart/{productId}` |
| Orders | `GET/POST orders`, `GET orders/{uuid}` |
| Vendor | `apiResource vendor/products`, `GET vendor/orders`, `PATCH vendor/orders/{uuid}` |
| Admin | `GET admin/vendors`, `PATCH admin/vendors/{uuid}`, `GET admin/reports/sales` |

## Architecture notes

- **Public ids are uuids** for orders, products, and vendors — internal bigint
  keys are never exposed. Categories route by `slug`.
- **Checkout** runs in one transaction with row-level locks: stock can't
  oversell, payment is captured idempotently (a `payments` ledger keyed by a
  deterministic idempotency key), and the confirmation notification is queued
  only after commit.
- **Fulfilment is per order line.** An order can span vendors, so each vendor
  ships their own lines; the order-level status is a derived rollup.
- **Vendor suspension** is enforced live at every boundary — login, catalogue
  visibility, and checkout — rather than cached at any single point.
