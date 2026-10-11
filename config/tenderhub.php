<?php

return [
    'ministries' => json_decode(file_get_contents(resource_path('data/ministries.json')), true),
    // The MOF field codes (kod bidang) 10 Creative Solutions is registered under; a tender needs ALL of its field codes to be among them to be registered (the Register form lets you proceed anyway).
    'company_field_codes' => json_decode(file_get_contents(resource_path('data/company-field-codes.json')), true),
    'seed_password' => env('SEED_USER_PASSWORD', 'TenderHub-dev-2026'),
    // Market Insights counts awards closing from this year on (earlier years have too few awards to be useful).
    'market_from_year' => 2023,
];
