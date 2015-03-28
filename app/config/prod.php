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

define("GOOGLE_API_KEY", "AIzaSyDIrox4lggueqtLPeC82MJX3ebg4U48nZw"); // Place your Google API Key
