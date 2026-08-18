<?php
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: '',
        'user' => getenv('DB_USER') ?: '',
        'pass' => getenv('DB_PASS') ?: '',
    ],
    'site' => [
        'name'          => 'Smarty Blog',
        'per_page'      => 6,   // articles per page on category listing
        'home_per_cat'  => 3,   // latest articles per category on home page
        'similar_count' => 3,   // similar articles on article page
    ],
];
