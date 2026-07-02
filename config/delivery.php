<?php

return [
    // Flat per-delivery fee snapshotted onto each DeliveryAssignment at
    // creation time — later config changes never alter an in-flight job.
    'flat_fee' => (float) env('DELIVERY_FLAT_FEE', 3.00),

    // A rider can't accept more than this many non-terminal (accepted/picked_up)
    // assignments at once.
    'max_concurrent_orders' => (int) env('DELIVERY_MAX_CONCURRENT_ORDERS', 3),

    // A rider can't accept a COD job that would push cash_in_hand + cod_amount
    // over this ceiling.
    'max_cash_in_hand' => (float) env('DELIVERY_MAX_CASH_IN_HAND', 500.00),

    // Off by default — no rider client exists yet to prompt for/display an OTP.
    'otp_required' => (bool) env('DELIVERY_OTP_REQUIRED', false),

    // Require a proof-of-delivery photo on the delivered transition (6amMart
    // parity: OTP + photo). Off by default, same reason as otp_required — no
    // rider client exists yet to capture one.
    'proof_photo_required' => (bool) env('DELIVERY_PROOF_PHOTO_REQUIRED', false),

    // Accept-order proximity guard (6amMart accept_order() guard #3). A rider
    // may only accept a job while physically within radius_km of the zone
    // centre. Off by default — no zone geometry is seeded yet, mirroring how
    // otp_required stays off until a rider client can satisfy it. 6amMart uses
    // polygon zones; a single centre+radius is the faithful minimal port.
    'service_zone' => [
        'enabled' => (bool) env('DELIVERY_SERVICE_ZONE_ENABLED', false),
        'center_lat' => (float) env('DELIVERY_SERVICE_ZONE_LAT', 11.5564),   // Phnom Penh
        'center_lng' => (float) env('DELIVERY_SERVICE_ZONE_LNG', 104.9282),
        'radius_km' => (float) env('DELIVERY_SERVICE_ZONE_RADIUS_KM', 10.0),
    ],
];
