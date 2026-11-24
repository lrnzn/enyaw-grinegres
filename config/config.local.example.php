<?php
// Copy this file to config.local.php on the live server and fill in real values.
// config.local.php is loaded automatically and overrides config.php.
return [
    'school_name' => 'Green Acres Christian School',
    'demo_mode'   => false,
    // Email to parents through Gmail (turn on 2-step verification, then create an App Password):
    'notifications' => [
        'email' => [
            'driver'     => 'smtp',
            'username'   => 'school.account@gmail.com',
            'password'   => 'abcdefghijklmnop',          // the 16-character App Password
            'from_name'  => 'Green Acres Christian School',
        ],
    ],
    // (Optional, paid) real SMS instead: 'notifications' => ['channels' => ['sms'], 'driver' => 'semaphore', 'semaphore' => ['api_key' => 'YOUR-KEY']],
    'debug'       => false,
    'db' => [
        'host' => 'localhost',
        'name' => 'your_database_name',
        'user' => 'your_database_user',
        'pass' => 'a-long-random-password',
    ],
];
