<?php

return [
    'supported_events' => [
        'user.created',
        'payment.received',
        'invoice.generated',
        'notification.requested',
    ],

    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', 'rabbitmq'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'exchange' => env('RABBITMQ_EXCHANGE', 'eventflow.events'),
        'exchange_type' => env('RABBITMQ_EXCHANGE_TYPE', 'topic'),
        'queue' => env('RABBITMQ_QUEUE', 'eventflow.processing'),
        'durable' => (bool) env('RABBITMQ_DURABLE', true),
    ],

    'api' => [
        'idempotency_header' => 'Idempotency-Key',
    ],
];
