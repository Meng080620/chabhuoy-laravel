# API Testing Reference

Multi-vendor marketplace API. All routes are prefixed with `/api`. Auth is **Laravel Sanctum bearer tokens** — pass `Authorization: Bearer <token>`.
Token abilities are role-scoped (`register`/`login` issue them automatically): customer → `customer:*`, vendor → `vendor:manage`, admin → `admin:manage`. A vendor token cannot hit admin routes, etc.

```bash
BASE=http://localhost:8000/api      # adjust to your host
TOKEN=                              # paste the token from /login or /register
```

## Endpoint list

| # | Method | Endpoint | Auth | Purpose | Request (body / params) |
|---|--------|----------|------|---------|--------------------------|
| 1 | POST | `/register` | public (throttle 6/min) | Create a customer account, returns user + token | body: `name`, `email`, `password`, `password_confirmation` |
| 2 | POST | `/login` | public (throttle:login) | Exchange credentials for a token | body: `email`, `password` |
| 3 | POST | `/logout` | any token | Revoke the current access token | — (bearer token only) |
| 4 | GET | `/me` | any token | Current authenticated user | — |
| 5 | GET | `/products` | public | List/paginate products; filter by category/vendor/search | query: `category_id?`, `vendor_id?`, `search?`, `per_page?`=20 |
| 6 | GET | `/products/{product}` | public | Single product with category + vendor | path: `product` (id) |
| 7 | GET | `/cart` | customer | Show current cart lines | — |
| 8 | PUT | `/cart` | customer | Upsert a cart line (absolute quantity) | body: `product_id`, `quantity` (1–99) |
| 9 | DELETE | `/cart/{productId}` | customer | Remove a product from the cart | path: `productId` |
| 10 | GET | `/orders` | customer | List the caller's orders | query: `per_page?`=20 |
| 11 | POST | `/orders` | customer | Place an order from the cart | body: `payment_method` (`card`\|`qr`\|`cod`) |
| 12 | GET | `/orders/{order}` | customer | Show one of the caller's orders | path: `order` (id) |
| 13 | GET | `/vendor/products` | vendor | List this vendor's products | — |
| 14 | POST | `/vendor/products` | vendor | Create a product | body: `name`, `category_id`, `price`, `stock`, `description?`, `low_stock_threshold?`, `is_active?` |
| 15 | PUT/PATCH | `/vendor/products/{product}` | vendor | Update a product | path: `product` + any create field (partial) |
| 16 | DELETE | `/vendor/products/{product}` | vendor | Delete a product | path: `product` |
| 17 | GET | `/vendor/orders` | vendor | Orders containing this vendor's lines | query: `per_page?`=20 |
| 18 | PATCH | `/vendor/orders/{order}` | vendor | Advance this vendor's lines (shipped/delivered) | path: `order` + body: `status` (`shipped`\|`delivered`) |
| 19 | GET | `/admin/vendors` | admin | List vendors; `?status=pending` = approval queue | query: `status?` (`pending`\|`active`\|`suspended`), `per_page?`=20 |
| 20 | PATCH | `/admin/vendors/{vendor}` | admin | Set vendor status | path: `vendor` + body: `status` (`pending`\|`active`\|`suspended`) |
| 21 | GET | `/admin/reports/sales` | admin | Platform sales summary | — |

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

### Cart (customer token)

**7. Show cart**
```bash
curl -s $BASE/cart -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

**8. Upsert cart line** — quantity is absolute, `1..99`; `product_id` must exist.
```bash
curl -s -X PUT $BASE/cart \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"product_id":1,"quantity":2}'
```

**9. Remove cart line**
```bash
curl -s -X DELETE $BASE/cart/1 -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

### Orders (customer token)

**10. List orders**
```bash
curl -s "$BASE/orders?per_page=20" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

**11. Place order** — `payment_method` one of `card` | `qr` | `cod`. Builds from the current cart.
```bash
curl -s -X POST $BASE/orders \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"payment_method":"cod"}'
```

**12. Show order**
```bash
curl -s $BASE/orders/1 -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

### Vendor (vendor token — needs `vendor:manage` + active vendor)

**13. List my products**
```bash
curl -s $BASE/vendor/products -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

**14. Create product** — `price` numeric, max 2 decimals; `category_id` must exist; `is_active`/`low_stock_threshold` optional.
```bash
curl -s -X POST $BASE/vendor/products \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"name":"Blue Tee","category_id":1,"description":"Cotton tee","price":19.99,"stock":100,"low_stock_threshold":5,"is_active":true}'
```

**15. Update product** (PATCH = partial)
```bash
curl -s -X PATCH $BASE/vendor/products/1 \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"price":17.50,"stock":80}'
```

**16. Delete product**
```bash
curl -s -X DELETE $BASE/vendor/products/1 -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

**17. List vendor orders**
```bash
curl -s $BASE/vendor/orders -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

**18. Fulfill vendor lines** — `status` only `shipped` or `delivered` (transitions enforced server-side; 404 if you have no line on this order).
```bash
curl -s -X PATCH $BASE/vendor/orders/1 \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"status":"shipped"}'
```

### Admin (admin token — needs `admin:manage`)

**19. List vendors** — `?status=pending|active|suspended` filters the queue; `per_page` default 20.
```bash
curl -s "$BASE/admin/vendors?status=pending" -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

**20. Update vendor status** — `status` one of `pending` | `active` | `suspended`.
```bash
curl -s -X PATCH $BASE/admin/vendors/1 \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"status":"active"}'
```

**21. Sales report**
```bash
curl -s $BASE/admin/reports/sales -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
# -> { orders_count, gross_revenue, top_vendors[], active_vendors }
```

---

## Enum / value cheat sheet
- **payment_method**: `card`, `qr`, `cod`  (cod = no immediate capture)
- **fulfillment status** (per line): `pending` → `shipped` → `delivered`; `cancelled` is terminal. Vendor endpoint accepts only `shipped`/`delivered`.
- **vendor status**: `pending`, `active`, `suspended`  (non-active vendors are rejected by `EnsureVendorRole`)

## Typical test flow
1. `POST /register` (customer) → save token → seed cart (`PUT /cart`) → `POST /orders`.
2. Login as a **vendor** (seeded/active) → `GET /vendor/orders` → `PATCH /vendor/orders/{id}` `{"status":"shipped"}`.
3. Login as **admin** → `PATCH /admin/vendors/{id}` `{"status":"active"}` → `GET /admin/reports/sales`.

> Vendor/admin accounts aren't created via `/register` (that only mints customers). Seed them, or set `role` + an active `Vendor` row directly in the DB.
