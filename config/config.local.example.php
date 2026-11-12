<?php
// Copy this file to config.local.php on the live server and fill in real values.
// config.local.php is loaded automatically and overrides config.php.
return [
    'school_name' => 'Your School Name',
    'demo_mode'   => false,
    // To send real SMS instead of simulating:
    // 'notifications' => ['driver' => 'semaphore', 'semaphore' => ['api_key' => 'YOUR-KEY', 'sender_name' => '']],
    'debug'       => false,
    'db' => [
        'host' => 'localhost',
        'name' => 'your_database_name',
        'user' => 'your_database_user',
        'pass' => 'a-long-random-password',
    ],
];
