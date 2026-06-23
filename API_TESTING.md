# Marketplace API — Testing Reference

> Multi-vendor marketplace REST API. This README is a hands-on testing guide: every endpoint, what it expects, and a copy-paste `curl` to exercise it.

**Stack:** Laravel · Sanctum (bearer tokens) · role-scoped abilities (customer / vendor / admin).

---

## Table of contents

- [Getting started](#getting-started)
- [Authentication](#authentication)
- [Endpoint list](#endpoint-list)
- [Request samples (curl)](#request-samples-curl)
  - [Auth](#auth)
  - [Products (public)](#products-public)
  - [Categories (public)](#categories-public)
  - [Cart (customer)](#cart-customer)
  - [Orders (customer)](#orders-customer)
  - [Vendor](#vendor)
  - [Admin](#admin)
- [Enum / value cheat sheet](#enum--value-cheat-sheet)
- [Typical test flow](#typical-test-flow)
- [Notes & gotchas](#notes--gotchas)

---

## Getting started

```bash
# 1. Serve the API
php artisan serve            # http://localhost:8000

# 2. Set shell variables used by every sample below
BASE=http://localhost:8000/api      # adjust to your host
TOKEN=                              # paste the token from /login or /register
```

All routes are prefixed with `/api`. Send `Accept: application/json` on every request so Laravel returns JSON (including validation errors) instead of HTML.

---

## Authentication

Auth is **Laravel Sanctum bearer tokens** — pass `Authorization: Bearer <token>` on protected routes.

Token abilities are **role-scoped** and issued automatically by `register` / `login`:

| Role | Abilities | Can reach |
|------|-----------|-----------|
| customer | `customer:*` | cart, orders, public routes |
| vendor | `vendor:manage` | `/vendor/*` (also needs an **active** vendor) |
| admin | `admin:manage` | `/admin/*` |

A token only carries its own role's abilities — a vendor token gets `403` on admin routes, and vice versa.

---

## Endpoint list

| # | Method | Endpoint | Auth | Purpose | Request (body / params) |
|---|--------|----------|------|---------|--------------------------|
| 1 | POST | `/register` | public (throttle 6/min) | Create a customer account, returns user + token | body: `name`, `email`, `password`, `password_confirmation` |
| 2 | POST | `/login` | public (throttle:login) | Exchange credentials for a token | body: `email`, `password` |
| 3 | POST | `/logout` | any token | Revoke the current access token | — (bearer token only) |
| 4 | GET | `/me` | any token | Current authenticated user | — |
| 5 | GET | `/products` | public | List/paginate products; filter by category/vendor/search | query: `category_id?`, `vendor_id?`, `search?`, `per_page?`=20 |
| 6 | GET | `/products/{product}` | public | Single product with category + vendor | path: `product` (id) |
| 7 | GET | `/categories` | public | Category tree: top-level categories + children (not paginated) | — |
| 8 | GET | `/categories/{category}` | public | Single category with immediate children | path: `category` (**slug**) |
| 9 | GET | `/cart` | customer | Show current cart lines | — |
| 10 | PUT | `/cart` | customer | Upsert a cart line (absolute quantity) | body: `product_id`, `quantity` (1–99) |
| 11 | DELETE | `/cart/{productId}` | customer | Remove a product from the cart | path: `productId` |
| 12 | GET | `/orders` | customer | List the caller's orders | query: `per_page?`=20 |
| 13 | POST | `/orders` | customer | Place an order from the cart | body: `payment_method` (`card`\|`qr`\|`cod`) |
| 14 | GET | `/orders/{order}` | customer | Show one of the caller's orders | path: `order` (id) |
| 15 | GET | `/vendor/products` | vendor | List this vendor's products | — |
| 16 | POST | `/vendor/products` | vendor | Create a product | body: `name`, `category_id`, `price`, `stock`, `description?`, `low_stock_threshold?`, `is_active?` |
| 17 | PUT/PATCH | `/vendor/products/{product}` | vendor | Update a product | path: `product` + any create field (partial) |
| 18 | DELETE | `/vendor/products/{product}` | vendor | Delete a product | path: `product` |
| 19 | GET | `/vendor/orders` | vendor | Orders containing this vendor's lines | query: `per_page?`=20 |
| 20 | PATCH | `/vendor/orders/{order}` | vendor | Advance this vendor's lines (shipped/delivered) | path: `order` + body: `status` (`shipped`\|`delivered`) |
| 21 | GET | `/admin/vendors` | admin | List vendors; `?status=pending` = approval queue | query: `status?` (`pending`\|`active`\|`suspended`), `per_page?`=20 |
| 22 | PATCH | `/admin/vendors/{vendor}` | admin | Set vendor status | path: `vendor` + body: `status` (`pending`\|`active`\|`suspended`) |
| 23 | GET | `/admin/reports/sales` | admin | Platform sales summary | — |

---

## Request samples (curl)

### Auth

**1. Register** — `password_confirmation` must match `password` (`confirmed`), email unique, min 8 chars.
```bash
curl -s -X POST $BASE/register \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name":"Test Customer","email":"customer@test.com","password":"password123","password_confirmation":"password123"}'
# 201 -> { "user": {...}, "token": "..." }
```

**2. Login**
```bash
curl -s -X POST $BASE/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"customer@test.com","password":"password123"}'
# 200 -> { "user": {...}, "token": "..." }   |  422 on bad credentials
```

**3. Logout**
```bash
curl -s -X POST $BASE/logout -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

**4. Me**
```bash
curl -s $BASE/me -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

### Products (public)

**5. List products** — optional filters: `category_id`, `vendor_id`, `search`, `per_page` (default 20).
```bash
curl -s "$BASE/products?search=shirt&category_id=1&per_page=10" -H "Accept: application/json"
```

**6. Show product**
```bash
curl -s $BASE/products/1 -H "Accept: application/json"
```

### Categories (public)

**7. Category tree** — top-level categories with children nested one level; not paginated.
```bash
curl -s $BASE/categories -H "Accept: application/json"
```

**8. Show category** — resolved by **slug** (not id); returns the category with its immediate children. Products live behind `GET /products?category_id={id}`.
```bash
curl -s $BASE/categories/electronics -H "Accept: application/json"
```

### Cart (customer)

**9. Show cart**
```bash
curl -s $BASE/cart -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

**10. Upsert cart line** — quantity is absolute, `1..99`; `product_id` must exist.
```bash
curl -s -X PUT $BASE/cart \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"product_id":1,"quantity":2}'
```

**11. Remove cart line**
```bash
curl -s -X DELETE $BASE/cart/1 -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

### Orders (customer)

**12. List orders**
```bash
curl -s "$BASE/orders?per_page=20" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

**13. Place order** — `payment_method` one of `card` | `qr` | `cod`. Builds from the current cart.
```bash
curl -s -X POST $BASE/orders \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"payment_method":"cod"}'
```

**14. Show order**
```bash
curl -s $BASE/orders/1 -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

### Vendor

> Needs a `vendor:manage` token **and** an active vendor (`EnsureVendorRole` rejects non-active vendors).

**15. List my products**
```bash
curl -s $BASE/vendor/products -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

**16. Create product** — `price` numeric, max 2 decimals; `category_id` must exist; `is_active`/`low_stock_threshold` optional.
```bash
curl -s -X POST $BASE/vendor/products \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"name":"Blue Tee","category_id":1,"description":"Cotton tee","price":19.99,"stock":100,"low_stock_threshold":5,"is_active":true}'
```

**17. Update product** (PATCH = partial)
```bash
curl -s -X PATCH $BASE/vendor/products/1 \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"price":17.50,"stock":80}'
```

**18. Delete product**
```bash
curl -s -X DELETE $BASE/vendor/products/1 -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

**19. List vendor orders**
```bash
curl -s $BASE/vendor/orders -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

**20. Fulfill vendor lines** — `status` only `shipped` or `delivered` (transitions enforced server-side; 404 if you have no line on this order).
```bash
curl -s -X PATCH $BASE/vendor/orders/1 \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"status":"shipped"}'
```

### Admin

> Needs an `admin:manage` token.

**21. List vendors** — `?status=pending|active|suspended` filters the queue; `per_page` default 20.
```bash
curl -s "$BASE/admin/vendors?status=pending" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

**22. Update vendor status** — `status` one of `pending` | `active` | `suspended`.
```bash
curl -s -X PATCH $BASE/admin/vendors/1 \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"status":"active"}'
```

**23. Sales report**
```bash
curl -s $BASE/admin/reports/sales -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
# -> { orders_count, gross_revenue, top_vendors[], active_vendors }
```

---

## Enum / value cheat sheet

| Field | Allowed values | Notes |
|-------|----------------|-------|
| `payment_method` | `card`, `qr`, `cod` | `cod` = no immediate capture |
| fulfillment `status` (per line) | `pending` → `shipped` → `delivered`; `cancelled` (terminal) | vendor endpoint accepts only `shipped` / `delivered` |
| vendor `status` | `pending`, `active`, `suspended` | non-active vendors rejected by `EnsureVendorRole` |

---

## Typical test flow

1. `POST /register` (customer) → save token → seed cart (`PUT /cart`) → `POST /orders`.
2. Login as a **vendor** (seeded/active) → `GET /vendor/orders` → `PATCH /vendor/orders/{id}` `{"status":"shipped"}`.
3. Login as **admin** → `PATCH /admin/vendors/{id}` `{"status":"active"}` → `GET /admin/reports/sales`.

---

## Notes & gotchas

- **`/register` only mints customers.** Vendor/admin accounts must be seeded, or set `role` + an active `Vendor` row directly in the DB.
- **Category `show` is by slug, not id** — `GET /categories/electronics`, not `/categories/1`. Listing is the full tree (unpaginated); products are fetched separately via `GET /products?category_id={id}`.
- **403 vs 404 on vendor orders:** `PATCH /vendor/orders/{id}` returns `404` (not `403`) when the vendor has no line on that order — so other customers' orders aren't leaked.
- **Validation errors** return `422` with a `{ "message", "errors": {...} }` body — send `Accept: application/json` to see them.
