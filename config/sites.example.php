<?php

return [
    'demo_site' => [
        'name'               => 'Demo Site',
        // Generate with: bin2hex(random_bytes(32))
        'secret_key'         => 'REPLACE_WITH_bin2hex_random_bytes_32',
        'allowed_origins'    => ['http://localhost:8888'],
        'message_cooldown'   => 3,      // seconds between messages per user (0 = off)
        'message_ttl'        => 86400,  // seconds before messages are deleted (0 = never)
        'max_message_length' => 200,    // max chars per message
        'history_limit'      => 50,     // messages to load on chat open
        'theme'              => [
            'primary'    => '#4f46e5', // accent: buttons, own message bubbles
            'primary_fg' => '#ffffff', // text on primary-colored elements
            'bg'         => '#ffffff', // widget background
            'msg_bg'     => '#f0f0f0', // other users' bubble background
            'msg_fg'     => '#222222', // other users' bubble text + input text
            'meta'       => '#888888', // sender names, cooldown text
            'border'     => '#e5e7eb', // footer border + input border
        ],
    ],
    // Add more tenants here:
    // 'my_site' => [
    //     'name'               => 'My Site',
    //     'secret_key'         => 'REPLACE_WITH_bin2hex_random_bytes_32',
    //     'allowed_origins'    => ['https://mysite.com'],
    //     'message_cooldown'   => 5,
    //     'message_ttl'        => 0,
    //     'max_message_length' => 500,
    //     'history_limit'      => 100,
    // ],
];
