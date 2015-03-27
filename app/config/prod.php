<?php

// Timezone.
date_default_timezone_set('Asia/Jakarta');

// Cache
$app['cache.path'] = __DIR__ . '/../cache';

// Twig cache
$app['twig.options.cache'] = $app['cache.path'] . '/twig';


// Register the Postgres database add-on. Doctrine (db)
$dbopts = parse_url(getenv('DATABASE_URL'));

$app['db.options'] = array(
    'driver'   => 'pdo_pgsql',
    'host'     => $dbopts["host"],
    'port'     => $dbopts["port"],
    'dbname'   => ltrim($dbopts["path"],'/'),
    'user'     => $dbopts["user"],
    'password' => $dbopts["pass"],
);

define("GOOGLE_API_KEY", "AIzaSyB8p2nogJBWl2ukhwBCvm3ZbhwghJRzjR0"); // Place your Google API Key
