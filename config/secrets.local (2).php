<?php
/**
 * LIVE secrets template.
 * On the live server: copy this file to "secrets.live.php" and fill in the real values.
 * Never commit "secrets.live.php" to git.
 */

return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'REPLACE_WITH_LIVE_DB_NAME',
        'user'    => 'REPLACE_WITH_LIVE_DB_USER',
        'pass'    => 'REPLACE_WITH_LIVE_DB_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'  => 'Autowagen Master',   // Shown in nav, login, shop, print report titles — change per host (e.g. "Valatone Demo")
        'url'   => 'https://your-live-domain.com',
        'debug' => false,
    ],
];
