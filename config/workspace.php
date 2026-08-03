<?php

declare(strict_types=1);

return [
    'routing' => [
        // HR: Područje definira prvi slug, a stranica drugi segment URL-a.
        // EN: A workspace defines the first slug and a page defines the second URL segment.
        'root_path' => 'workspace',
    ],
    'defaults' => [
        'visibility' => 'restricted',
        'tree_visible' => true,
    ],
    'creation' => [
        'authenticated_users' => false,
    ],
    'shorts' => [
        // HR: Zadane vrijednosti javnog popisa sažetaka unutar svakog područja.
        // EN: Default values for each Workspace's public Shorts listing.
        'depth' => 1,
        'limit' => 10,
        'order' => 'hierarchy',
    ],
    'menu' => [
        'auto_register_top' => true,
        'auto_register_settings' => true,
    ],
];
