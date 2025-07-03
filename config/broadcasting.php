<?php

return [
    'default' => 'pusher',
    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => '9ebcfeb7c106c3456664',
            'secret' => 'f9c06e6027eeda56a8cb',
            'app_id' => '2012032',
            'options' => [
                'cluster' => 'ap2',
                'useTLS' => true,
                'curl_options' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                ],
            ],
        ],
    ],
];
