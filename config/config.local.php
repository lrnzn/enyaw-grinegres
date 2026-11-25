<?php
// SGVMS settings for XAMPP (testing on your own computer).
// Put this file inside the project's "config" folder:  C:\xampp\htdocs\sgvms\config\config.local.php
// Fill in only the lines that have a  // FILL IN  note. Keep the quotes and the commas.
// Never share this file or upload it anywhere public.

return [
    'school_name' => 'Green Acres Christian School',

    // true = shows the demo shortcuts on the Gate station (good for testing).
    // Change to false before real students are used.
    'demo_mode'   => false,

    // true = shows detailed error messages (good for testing). Change to false when online.
    'debug'       => true,

    // ---- Database ----
    'db' => [
        'host' => 'localhost',
        'name' => 'sgvms',        // FILL IN: the database name you imported into (phpMyAdmin, left side)
        'user' => 'root',         // XAMPP default. Leave as is.
        'pass' => '',             // XAMPP default is empty. Leave as is unless you set a MySQL password.
    ],

    // ---- Email to parents through Gmail ----
    'notifications' => [
        'email' => [
            'driver'      => 'smtp',                         // leave as is. ('log' = do not send, only record)
            'username'    => 'greenacres.sgvms@gmail.com',        // FILL IN: the school's Gmail address, e.g. greenacres.notify@gmail.com
            'password'    => 'pmpu ofgx hoge jgqp',        // FILL IN: the 16-character App Password (not the Gmail password). Spaces are fine.
            'from_name'   => 'Green Acres Christian School', // the sender name parents see
            // 'from_email' => '',                           // leave out. It uses the Gmail address above.

            // Only if the first test says "could not start a secure (TLS) connection" or a certificate error:
            // remove the two slashes in front of the next line. Testing on XAMPP only. Remove it again when online.
            // 'verify_peer' => false,
        ],
    ],
];
