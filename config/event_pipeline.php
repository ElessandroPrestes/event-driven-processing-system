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
        'ingest' => [
            'exchange' => env('RABBITMQ_INGEST_EXCHANGE', 'eventflow.events.ingest'),
            'exchange_type' => env('RABBITMQ_INGEST_EXCHANGE_TYPE', 'topic'),
            'queue' => env('RABBITMQ_INGEST_QUEUE', 'eventflow.ingest'),
            'binding_key' => env('RABBITMQ_INGEST_BINDING_KEY', '#'),
            'dead_letter_exchange' => env('RABBITMQ_INGEST_DEAD_LETTER_EXCHANGE', 'eventflow.events.ingest.dlx'),
            'dead_letter_exchange_type' => env('RABBITMQ_INGEST_DEAD_LETTER_EXCHANGE_TYPE', 'direct'),
            'dead_letter_queue' => env('RABBITMQ_INGEST_DEAD_LETTER_QUEUE', 'eventflow.ingest.dead'),
            'dead_letter_routing_key' => env('RABBITMQ_INGEST_DEAD_LETTER_ROUTING_KEY', 'eventflow.ingest.dead'),
        ],
        'retry_exchange' => env('RABBITMQ_RETRY_EXCHANGE', 'eventflow.events.retry'),
        'retry_exchange_type' => env('RABBITMQ_RETRY_EXCHANGE_TYPE', 'direct'),
        'retry_queue' => env('RABBITMQ_RETRY_QUEUE', 'eventflow.processing.retry'),
        'retry_routing_key' => env('RABBITMQ_RETRY_ROUTING_KEY', 'eventflow.processing.retry'),
        'retry_return_routing_key' => env('RABBITMQ_RETRY_RETURN_ROUTING_KEY', 'eventflow.processing.ready'),
        'dead_letter_exchange' => env('RABBITMQ_DEAD_LETTER_EXCHANGE', 'eventflow.events.dlx'),
        'dead_letter_exchange_type' => env('RABBITMQ_DEAD_LETTER_EXCHANGE_TYPE', 'direct'),
        'dead_letter_queue' => env('RABBITMQ_DEAD_LETTER_QUEUE', 'eventflow.processing.dead'),
        'dead_letter_routing_key' => env('RABBITMQ_DEAD_LETTER_ROUTING_KEY', 'eventflow.processing.dead'),
        'durable' => (bool) env('RABBITMQ_DURABLE', true),
    ],

    'api' => [
        'idempotency_header' => 'Idempotency-Key',
        'pagination' => [
            'events' => [
                'default_per_page' => (int) env('EVENT_API_EVENTS_DEFAULT_PER_PAGE', 20),
                'max_per_page' => (int) env('EVENT_API_EVENTS_MAX_PER_PAGE', 100),
            ],
            'event_history' => [
                'default_per_page' => (int) env('EVENT_API_EVENT_HISTORY_DEFAULT_PER_PAGE', 20),
                'max_per_page' => (int) env('EVENT_API_EVENT_HISTORY_MAX_PER_PAGE', 100),
            ],
        ],
    ],

    'auth' => [
        'ingest' => [
            'header' => env('EVENT_INGEST_API_KEY_HEADER', 'X-Ingest-Api-Key'),
            'key' => env('EVENT_INGEST_API_KEY', ''),
        ],
        'operations' => [
            'header' => env('EVENT_OPERATIONS_API_KEY_HEADER', 'X-Operations-Api-Key'),
            'key' => env('EVENT_OPERATIONS_API_KEY', ''),
        ],
    ],

    'rate_limits' => [
        'ingest' => [
            'max_attempts' => (int) env('EVENT_INGEST_RATE_LIMIT_MAX_ATTEMPTS', 60),
            'decay_seconds' => (int) env('EVENT_INGEST_RATE_LIMIT_DECAY_SECONDS', 60),
        ],
        'operations' => [
            'max_attempts' => (int) env('EVENT_OPERATIONS_RATE_LIMIT_MAX_ATTEMPTS', 120),
            'decay_seconds' => (int) env('EVENT_OPERATIONS_RATE_LIMIT_DECAY_SECONDS', 60),
        ],
    ],

    'observability' => [
        'trace_header' => env('EVENT_TRACE_HEADER', 'X-Trace-Id'),
    ],

    'consumer' => [
        'max_attempts' => (int) env('EVENT_CONSUMER_MAX_ATTEMPTS', 3),
        'idle_timeout' => (int) env('EVENT_CONSUMER_IDLE_TIMEOUT', 5),
        'retry_base_delay_ms' => (int) env('EVENT_CONSUMER_RETRY_BASE_DELAY_MS', 5000),
        'retry_multiplier' => (float) env('EVENT_CONSUMER_RETRY_MULTIPLIER', 2.0),
        'retry_max_delay_ms' => (int) env('EVENT_CONSUMER_RETRY_MAX_DELAY_MS', 60000),
    ],
];
