<?php

return [
    'ministries' => json_decode(file_get_contents(resource_path('data/ministries.json')), true),
    'seed_password' => env('SEED_USER_PASSWORD', 'TenderHub-dev-2026'),
];
