<?php

return [
    'ministries' => json_decode(file_get_contents(resource_path('data/ministries.json')), true),
    'seed_password' => env('SEED_USER_PASSWORD', 'TenderHub-dev-2026'),
    // Market Insights counts awards closing from this year on (earlier years have too few awards to be useful).
    'market_from_year' => 2023,
];
