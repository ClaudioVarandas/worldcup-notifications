<?php

return [
    'teams' => [
        'symplworks' => [
            'url' => env('SLACK_SYMPLWORKS_WEBHOOK_URL'),
            'channel' => env('SLACK_SYMPLWORKS_WEBHOOK_CHANNEL')
        ],
        'laravel-portugal' => [
            'url' => env('SLACK_LARAVELPORTUGAL_WEBHOOK_URL'),
            'channel' => env('SLACK_LARAVELPORTUGAL_WEBHOOK_CHANNEL')
        ]
    ]
];