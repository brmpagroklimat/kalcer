<?php
// Config LOKAL (Docker). Untuk Hostinger pakai event-config.sample.php.
return [
    'db' => ['host' => 'db', 'name' => 'kalcer_event', 'user' => 'kalcer', 'pass' => 'devpass'],
    'setup_key' => 'dev-setup-key',
    'base_url' => 'http://localhost:8090/event',
    'uploads_dir' => '/var/www/site/event-uploads',
    'secure_cookie' => false,
    'smtp' => null,
];
