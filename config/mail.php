<?php

// return [
//     'driver' => env(' ', 'smtp'),
//     'host' => env('MAIL_HOST', 'sandbox.smtp.mailtrap.io'),
//     'port' => env('MAIL_PORT', 2525),
//     'from' => [
//         'address' => env('MAIL_FROM_ADDRESS', 'hello@demomailtrap.com'),
//         'name' => env('MAIL_FROM_NAME', 'hello'),
//     ],
//     'encryption' => env('MAIL_ENCRYPTION', 'tls'),
//     'username' => env('MAIL_USERNAME','acc9f13082b098'),
//     'password' => env('MAIL_PASSWORD','7b1edfe8ad2d3e'),
//     'sendmail' => '/usr/sbin/sendmail -bs',
//     'markdown' => [
//         'theme' => 'default',
//         'paths' => [resource_path('views/vendor/mail')],
//     ],
//     'verify_peer' => false,
//     'stream' => [
//         'ssl' => [
//             'allow_self_signed' => true,
//             'verify_peer' => false,
//             'verify_peer_name' => false,
//         ],
//     ],
// ];


return [

    'driver' => env('MAIL_MAILER', 'smtp'),
    'host' => env('MAIL_HOST', 'smtp.gmail.com'),
    'port' => env('MAIL_PORT', 587),
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'username' => env('MAIL_USERNAME'),
    'password' => env('MAIL_PASSWORD'),
    'sendmail' => '/usr/sbin/sendmail -bs',
    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];