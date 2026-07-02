<?php
// Regenerate: php docs/build_postman_collection.php docs
// Emits docs/chabhuoy-api.postman_collection.json (Postman v2.1; imports into Bruno too).
// Keep in sync with routes/api.php as endpoints change.
// Generates a Postman Collection v2.1 for the Chabhuoy API.
// Imports into Postman directly and into Bruno via its Postman importer.

function acceptHeader(): array {
    return [['key' => 'Accept', 'value' => 'application/json']];
}

function jsonHeaders(): array {
    return [
        ['key' => 'Content-Type', 'value' => 'application/json'],
        ['key' => 'Accept', 'value' => 'application/json'],
    ];
}

function url(string $path, array $query = []): array {
    $enabled = array_filter($query, fn ($q) => empty($q['disabled']));
    $raw = '{{baseUrl}}/' . $path;
    if ($enabled) {
        $raw .= '?' . implode('&', array_map(fn ($q) => $q['key'] . '=' . $q['value'], $enabled));
    }
    $u = ['raw' => $raw, 'host' => ['{{baseUrl}}'], 'path' => explode('/', $path)];
    if ($query) {
        $u['query'] = array_values($query);
    }
    return $u;
}

function jsonBody(array $data): array {
    return [
        'mode' => 'raw',
        'raw' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'options' => ['raw' => ['language' => 'json']],
    ];
}

function formBody(array $fields): array {
    return ['mode' => 'formdata', 'formdata' => $fields];
}

function text(string $key, string $value, bool $disabled = false): array {
    $f = ['key' => $key, 'value' => $value, 'type' => 'text'];
    if ($disabled) $f['disabled'] = true;
    return $f;
}

function fileField(string $key): array {
    return ['key' => $key, 'type' => 'file', 'src' => null];
}

function q(string $key, string $value = '', bool $disabled = true): array {
    $qq = ['key' => $key, 'value' => $value];
    if ($disabled) $qq['disabled'] = true;
    return $qq;
}

function captureToken(string $var): array {
    return [[
        'listen' => 'test',
        'script' => [
            'type' => 'text/javascript',
            'exec' => [
                "const json = pm.response.json();",
                "if (json && json.token) {",
                "  pm.collectionVariables.set('$var', json.token);",
                "  console.log('Saved $var');",
                "}",
            ],
        ],
    ]];
}

/**
 * @param array $opts  method, path, query, headers, body, event, description
 */
function req(string $name, array $opts): array {
    $request = [
        'method' => $opts['method'],
        'header' => $opts['headers'] ?? acceptHeader(),
        'url' => url($opts['path'], $opts['query'] ?? []),
    ];
    if (isset($opts['body'])) {
        $request['body'] = $opts['body'];
    }
    if (isset($opts['auth'])) {
        $request['auth'] = $opts['auth'];
    }
    if (isset($opts['description'])) {
        $request['description'] = $opts['description'];
    }
    $item = ['name' => $name, 'request' => $request];
    if (isset($opts['event'])) {
        $item['event'] = $opts['event'];
    }
    return $item;
}

function bearer(string $var): array {
    return ['type' => 'bearer', 'bearer' => [['key' => 'token', 'value' => '{{' . $var . '}}', 'type' => 'string']]];
}

function noauth(): array {
    return ['type' => 'noauth'];
}

function folder(string $name, string $description, array $auth, array $items): array {
    return ['name' => $name, 'description' => $description, 'auth' => $auth, 'item' => $items];
}

$listQuery = [q('page', '1'), q('per_page', '20'), q('search', '')];

$collection = [
    'info' => [
        'name' => 'Chabhuoy API',
        '_postman_id' => '6c2a7f10-1a2b-4c3d-9e5f-cha6hu0ya001',
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        'description' => implode("\n", [
            "# Chabhuoy Marketplace API",
            "",
            "Laravel 13 / PHP 8.3 backend. Bearer (Sanctum) auth. Every route is under `{{baseUrl}}` (default `http://localhost:8000/api`).",
            "",
            "## Quick start",
            "1. `php artisan migrate --seed` then `php artisan serve` (or set `baseUrl` to your host).",
            "2. Run **Auth → Login (Admin)** and **Login (Customer)** — each stores its bearer token into a collection variable automatically (see the request's Tests script).",
            "3. Seeded logins: `admin@example.com` / `password`, `customer@example.com` / `password`. Vendor & rider have no seeded credentials — register/promote a user and set `vendorEmail`/`riderEmail` (+ passwords) first.",
            "4. Folders are pre-wired to the right token: **Customer**→`customerToken`, **Vendor**→`vendorToken`, **Admin**→`adminToken`, **Rider**→`riderToken`. Storefront is public.",
            "",
            "## Path variables",
            "IDs differ by resource — set the collection variables from a list response before calling detail/mutation routes: `productUuid`, `productId` (numeric, cart only), `categorySlug`, `categoryId`, `orderUuid`, `userId` (numeric), `vendorUuid`, `addressId`, `bannerId`, `brandStoreId`, `deliveryManUuid`, `assignmentUuid`.",
            "",
            "## File uploads",
            "Image endpoints use `multipart/form-data`. Because PHP only populates uploaded files on POST, the banner/brand-store **update** and rider **update-order-status (with photo)** requests use `POST` + a `_method` spoof field — that's intentional, not a mistake.",
            "",
            "## Bruno",
            "Import this file via Bruno's *Import → Postman Collection*. Requests/bodies/variables come over cleanly; the token-capture Test scripts are Postman-flavoured and may need a one-line tweak to Bruno's `bru.setVar` API.",
        ]),
    ],
    'item' => [],
    'variable' => [
        ['key' => 'baseUrl', 'value' => 'http://localhost:8000/api'],
        ['key' => 'adminEmail', 'value' => 'admin@example.com'],
        ['key' => 'adminPassword', 'value' => 'password'],
        ['key' => 'customerEmail', 'value' => 'customer@example.com'],
        ['key' => 'customerPassword', 'value' => 'password'],
        ['key' => 'vendorEmail', 'value' => ''],
        ['key' => 'vendorPassword', 'value' => 'password'],
        ['key' => 'riderEmail', 'value' => ''],
        ['key' => 'riderPassword', 'value' => 'password'],
        ['key' => 'adminToken', 'value' => ''],
        ['key' => 'customerToken', 'value' => ''],
        ['key' => 'vendorToken', 'value' => ''],
        ['key' => 'riderToken', 'value' => ''],
        ['key' => 'productUuid', 'value' => ''],
        ['key' => 'productId', 'value' => '1'],
        ['key' => 'categorySlug', 'value' => ''],
        ['key' => 'categoryId', 'value' => '1'],
        ['key' => 'orderUuid', 'value' => ''],
        ['key' => 'userId', 'value' => '2'],
        ['key' => 'vendorUuid', 'value' => ''],
        ['key' => 'addressId', 'value' => '1'],
        ['key' => 'bannerId', 'value' => '1'],
        ['key' => 'brandStoreId', 'value' => '1'],
        ['key' => 'deliveryManUuid', 'value' => ''],
        ['key' => 'assignmentUuid', 'value' => ''],
    ],
];

// ---- Auth ----
$auth = folder('Auth', 'Register, login (per role), me, logout. Login/register store the bearer token into a collection variable via the Tests script.', noauth(), [
    req('Register (customer)', [
        'method' => 'POST', 'path' => 'register', 'headers' => jsonHeaders(),
        'body' => jsonBody(['name' => 'New Customer', 'email' => 'new.customer@example.com', 'password' => 'password', 'password_confirmation' => 'password']),
        'event' => captureToken('customerToken'),
        'description' => 'Creates a customer and returns `{ user, token }`. Token saved to `customerToken`.',
    ]),
    req('Login (Admin)', [
        'method' => 'POST', 'path' => 'login', 'headers' => jsonHeaders(),
        'body' => jsonBody(['email' => '{{adminEmail}}', 'password' => '{{adminPassword}}']),
        'event' => captureToken('adminToken'),
    ]),
    req('Login (Customer)', [
        'method' => 'POST', 'path' => 'login', 'headers' => jsonHeaders(),
        'body' => jsonBody(['email' => '{{customerEmail}}', 'password' => '{{customerPassword}}']),
        'event' => captureToken('customerToken'),
    ]),
    req('Login (Vendor)', [
        'method' => 'POST', 'path' => 'login', 'headers' => jsonHeaders(),
        'body' => jsonBody(['email' => '{{vendorEmail}}', 'password' => '{{vendorPassword}}']),
        'event' => captureToken('vendorToken'),
    ]),
    req('Login (Rider)', [
        'method' => 'POST', 'path' => 'login', 'headers' => jsonHeaders(),
        'body' => jsonBody(['email' => '{{riderEmail}}', 'password' => '{{riderPassword}}']),
        'event' => captureToken('riderToken'),
    ]),
    req('Me', ['method' => 'GET', 'path' => 'me', 'auth' => bearer('customerToken')]),
    req('Logout', ['method' => 'POST', 'path' => 'logout', 'auth' => bearer('customerToken')]),
]);

// ---- Storefront (public) ----
$store = folder('Storefront (public)', 'Public, unauthenticated storefront reads.', noauth(), [
    req('List products', ['method' => 'GET', 'path' => 'products', 'query' => $listQuery]),
    req('Get product', ['method' => 'GET', 'path' => 'products/{{productUuid}}']),
    req('List categories', ['method' => 'GET', 'path' => 'categories']),
    req('Get category', ['method' => 'GET', 'path' => 'categories/{{categorySlug}}', 'description' => 'Public category resolves by **slug**.']),
    req('List banners', ['method' => 'GET', 'path' => 'banners', 'query' => [q('type', 'hero')]]),
    req('List brand stores', ['method' => 'GET', 'path' => 'brand-stores']),
]);

// ---- Customer ----
$customer = folder('Customer', 'Cart, addresses, checkout, own orders. Auth: customerToken.', bearer('customerToken'), [
    req('Get cart', ['method' => 'GET', 'path' => 'cart']),
    req('Add / update cart item', ['method' => 'PUT', 'path' => 'cart', 'headers' => jsonHeaders(), 'body' => jsonBody(['product_id' => '{{productId}}', 'quantity' => 1])]),
    req('Remove cart item', ['method' => 'DELETE', 'path' => 'cart/{{productId}}']),
    req('List addresses', ['method' => 'GET', 'path' => 'addresses']),
    req('Create address', ['method' => 'POST', 'path' => 'addresses', 'headers' => jsonHeaders(), 'body' => jsonBody([
        'label' => 'Home', 'recipient_name' => 'Test Customer', 'phone' => '+85512345678',
        'line1' => '123 Street 271', 'line2' => null, 'city' => 'Phnom Penh',
        'postal_code' => '120101', 'country' => 'KH', 'is_default' => true,
    ])]),
    req('Update address', ['method' => 'PUT', 'path' => 'addresses/{{addressId}}', 'headers' => jsonHeaders(), 'body' => jsonBody([
        'recipient_name' => 'Test Customer', 'phone' => '+85599998888',
        'line1' => '45 Norodom Blvd', 'city' => 'Phnom Penh', 'postal_code' => '120101', 'country' => 'KH',
    ])]),
    req('Set default address', ['method' => 'PATCH', 'path' => 'addresses/{{addressId}}/default']),
    req('Delete address', ['method' => 'DELETE', 'path' => 'addresses/{{addressId}}']),
    req('Checkout (place order)', ['method' => 'POST', 'path' => 'orders', 'headers' => jsonHeaders(), 'body' => jsonBody(['payment_method' => 'card', 'address_id' => '{{addressId}}']), 'description' => 'payment_method: card | qr | cod. Charges via the PaymentGateway port, decrements stock, empties the cart.']),
    req('List my orders', ['method' => 'GET', 'path' => 'orders', 'query' => $listQuery]),
    req('Get my order', ['method' => 'GET', 'path' => 'orders/{{orderUuid}}']),
]);

// ---- Vendor ----
$vendor = folder('Vendor', 'Own catalogue + order fulfillment. Auth: vendorToken.', bearer('vendorToken'), [
    req('List my products', ['method' => 'GET', 'path' => 'vendor/products']),
    req('Create product', ['method' => 'POST', 'path' => 'vendor/products', 'headers' => jsonHeaders(), 'body' => jsonBody([
        'name' => 'Handwoven Basket', 'category_id' => '{{categoryId}}', 'description' => 'Natural rattan.',
        'price' => '24.50', 'stock' => 20, 'low_stock_threshold' => 5, 'is_active' => true,
    ])]),
    req('Update product', ['method' => 'PUT', 'path' => 'vendor/products/{{productUuid}}', 'headers' => jsonHeaders(), 'body' => jsonBody([
        'name' => 'Handwoven Basket (Large)', 'category_id' => '{{categoryId}}', 'price' => '29.00', 'stock' => 15,
    ])]),
    req('Delete product', ['method' => 'DELETE', 'path' => 'vendor/products/{{productUuid}}']),
    req('List vendor orders', ['method' => 'GET', 'path' => 'vendor/orders', 'query' => $listQuery]),
    req('Update order fulfillment', ['method' => 'PATCH', 'path' => 'vendor/orders/{{orderUuid}}', 'headers' => jsonHeaders(), 'body' => jsonBody([
        'status' => 'shipped', 'carrier' => 'J&T Express', 'tracking_number' => 'JT-000111',
    ]), 'description' => 'status: shipped | delivered. carrier/tracking allowed only when shipped.']),
    req('Vendor earnings', ['method' => 'GET', 'path' => 'vendor/earnings']),
]);

// ---- Admin ----
$admin = folder('Admin', 'Moderation, CMS, payouts, rider ops. Auth: adminToken.', bearer('adminToken'), [
    req('List products', ['method' => 'GET', 'path' => 'admin/products', 'query' => [q('status', 'active'), q('vendor_id', ''), q('search', ''), q('page', '1'), q('per_page', '20')]]),
    req('Update product visibility', ['method' => 'PATCH', 'path' => 'admin/products/{{productUuid}}', 'headers' => jsonHeaders(), 'body' => jsonBody(['is_active' => false])]),
    req('Upload product image', ['method' => 'POST', 'path' => 'admin/products/{{productUuid}}/image', 'body' => formBody([fileField('image')]), 'description' => 'multipart/form-data, field `image` (≤4 MB).']),
    req('Remove product image', ['method' => 'DELETE', 'path' => 'admin/products/{{productUuid}}/image']),
    req('List categories', ['method' => 'GET', 'path' => 'admin/categories']),
    req('Create category', ['method' => 'POST', 'path' => 'admin/categories', 'headers' => jsonHeaders(), 'body' => jsonBody(['name' => 'Textiles', 'parent_id' => null])]),
    req('Update category', ['method' => 'PUT', 'path' => 'admin/categories/{{categoryId}}', 'headers' => jsonHeaders(), 'body' => jsonBody(['name' => 'Home Textiles', 'parent_id' => null]), 'description' => 'Admin category binds by **numeric id**.']),
    req('Delete category', ['method' => 'DELETE', 'path' => 'admin/categories/{{categoryId}}']),
    req('List customers', ['method' => 'GET', 'path' => 'admin/customers', 'query' => [q('search', ''), q('per_page', '20')]]),
    req('Get customer', ['method' => 'GET', 'path' => 'admin/customers/{{userId}}', 'description' => 'Binds by **numeric user id**.']),
    req('List vendors', ['method' => 'GET', 'path' => 'admin/vendors', 'query' => $listQuery]),
    req('Update vendor status', ['method' => 'PATCH', 'path' => 'admin/vendors/{{vendorUuid}}', 'headers' => jsonHeaders(), 'body' => jsonBody(['status' => 'active']), 'description' => 'status: pending | active | suspended. Binds by vendor **uuid**.']),
    req('List orders', ['method' => 'GET', 'path' => 'admin/orders', 'query' => [q('status', ''), q('vendor_id', ''), q('search', ''), q('page', '1'), q('per_page', '20')]]),
    req('Cancel order', ['method' => 'PATCH', 'path' => 'admin/orders/{{orderUuid}}', 'headers' => jsonHeaders(), 'body' => jsonBody(['status' => 'cancelled']), 'description' => 'Only cancellation. A Paid order is refunded via the PaymentGateway port; held stock is restocked.']),
    req('List payouts', ['method' => 'GET', 'path' => 'admin/payouts', 'query' => [q('vendor_id', ''), q('per_page', '20')]]),
    req('Trigger vendor payout', ['method' => 'POST', 'path' => 'admin/payouts/{{vendorUuid}}', 'description' => 'Disburses the vendor balance via the DisbursementProvider port; 422 if nothing owed.']),
    req('List banners', ['method' => 'GET', 'path' => 'admin/banners']),
    req('Create banner', ['method' => 'POST', 'path' => 'admin/banners', 'body' => formBody([
        text('type', 'hero'), text('title', 'Mid-Year Sale'), text('subtitle', 'Up to 60% off'),
        text('link_url', 'https://example.com/sale'), text('cta_label', 'Shop now'), text('position', '0'), text('is_active', '1'), fileField('image'),
    ]), 'description' => 'multipart. type: hero|promo|eco|seasonal. is_active as 1/0.']),
    req('Update banner', ['method' => 'POST', 'path' => 'admin/banners/{{bannerId}}', 'body' => formBody([
        text('_method', 'PUT'), text('type', 'hero'), text('title', 'Mid-Year Sale (updated)'), text('position', '1'), text('is_active', '1'), fileField('image'),
    ]), 'description' => 'POST + _method=PUT so the image upload works (PHP reads files only on POST). Leave `image` empty to keep the current one.']),
    req('Delete banner', ['method' => 'DELETE', 'path' => 'admin/banners/{{bannerId}}']),
    req('List brand stores', ['method' => 'GET', 'path' => 'admin/brand-stores']),
    req('Create brand store', ['method' => 'POST', 'path' => 'admin/brand-stores', 'body' => formBody([
        text('name', 'Angkor Crafts'), text('caption', 'Delivery within 24 hours'), text('link_url', 'https://example.com/angkor'), text('position', '0'), text('is_active', '1'), fileField('logo'),
    ]), 'description' => 'multipart, field `logo` (≤2 MB).']),
    req('Update brand store', ['method' => 'POST', 'path' => 'admin/brand-stores/{{brandStoreId}}', 'body' => formBody([
        text('_method', 'PUT'), text('name', 'Angkor Crafts'), text('position', '1'), text('is_active', '1'), fileField('logo'),
    ]), 'description' => 'POST + _method=PUT for the logo upload.']),
    req('Delete brand store', ['method' => 'DELETE', 'path' => 'admin/brand-stores/{{brandStoreId}}']),
    req('Dashboard KPIs', ['method' => 'GET', 'path' => 'admin/dashboard']),
    req('Sales report', ['method' => 'GET', 'path' => 'admin/reports/sales']),
    req('List delivery men', ['method' => 'GET', 'path' => 'admin/delivery-men', 'query' => $listQuery]),
    req('Update delivery man status', ['method' => 'PATCH', 'path' => 'admin/delivery-men/{{deliveryManUuid}}', 'headers' => jsonHeaders(), 'body' => jsonBody(['status' => 'active']), 'description' => 'status: pending | active | suspended.']),
    req('List delivery earnings', ['method' => 'GET', 'path' => 'admin/delivery-earnings', 'query' => [q('delivery_man_id', ''), q('per_page', '20')]]),
    req('Trigger rider disbursement', ['method' => 'POST', 'path' => 'admin/delivery-earnings/{{deliveryManUuid}}', 'description' => 'Disburses the rider wallet via the DisbursementProvider port; 422 if nothing owed.']),
    req('List cash settlements', ['method' => 'GET', 'path' => 'admin/delivery-cash-settlements', 'query' => [q('delivery_man_id', ''), q('per_page', '20')]]),
]);

// ---- Rider ----
$rider = folder('Delivery-man (rider)', 'Rider job flow + presence + cash. Auth: riderToken. Rider features are config-gated (config/delivery.php) — off by default.', bearer('riderToken'), [
    req('Latest orders (pool)', ['method' => 'GET', 'path' => 'delivery-man/latest-orders', 'query' => [q('per_page', '20')]]),
    req('Current orders', ['method' => 'GET', 'path' => 'delivery-man/current-orders', 'query' => [q('per_page', '20')]]),
    req('All orders', ['method' => 'GET', 'path' => 'delivery-man/all-orders', 'query' => [q('per_page', '20')]]),
    req('Accept order', ['method' => 'PATCH', 'path' => 'delivery-man/accept-order/{{assignmentUuid}}', 'headers' => jsonHeaders(), 'body' => jsonBody(['lat' => 11.5564, 'lng' => 104.9282]), 'description' => 'Sends GPS; geofence + concurrency + cash-ceiling guards apply.']),
    req('Update order status', ['method' => 'POST', 'path' => 'delivery-man/update-order-status/{{assignmentUuid}}', 'body' => formBody([
        text('_method', 'PATCH'), text('status', 'delivered'), text('otp', '', true), fileField('proof_photo'),
    ]), 'description' => 'status: picked_up | delivered | returned. POST + _method=PATCH so an optional proof_photo (image) uploads. For a plain status change you can also send a JSON PATCH with just {"status":...}.']),
    req('Update active status', ['method' => 'PATCH', 'path' => 'delivery-man/update-active-status', 'headers' => jsonHeaders(), 'body' => jsonBody(['is_online' => true])]),
    req('Record location', ['method' => 'PATCH', 'path' => 'delivery-man/record-location-data', 'headers' => jsonHeaders(), 'body' => jsonBody(['lat' => 11.5564, 'lng' => 104.9282])]),
    req('Update FCM token', ['method' => 'PATCH', 'path' => 'delivery-man/update-fcm-token', 'headers' => jsonHeaders(), 'body' => jsonBody(['fcm_token' => 'fcm-sample-token-abc123'])]),
    req('Make collected-cash payment', ['method' => 'POST', 'path' => 'delivery-man/make-collected-cash-payment', 'headers' => jsonHeaders(), 'body' => jsonBody(['amount' => '45.00'])]),
    req('Earnings summary', ['method' => 'GET', 'path' => 'delivery-man/earnings']),
]);

$collection['item'] = [$auth, $store, $customer, $vendor, $admin, $rider];

$out = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$dir = $argv[1];
file_put_contents($dir . '/chabhuoy-api.postman_collection.json', $out . "\n");

// summary
$count = 0;
foreach ($collection['item'] as $f) { $count += count($f['item']); }
fwrite(STDERR, "folders=" . count($collection['item']) . " requests=$count bytes=" . strlen($out) . "\n");
