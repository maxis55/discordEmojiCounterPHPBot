<?php
use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();


$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => env('DB_DRIVER', 'mysql'),
    'host'      => env('DB_HOST'),
    'port'      => env('DB_PORT', '3306'),
    'database'  => env('DB_DATABASE'),
    'username'  => env('DB_USERNAME'),
    'password'  => env('DB_PASSWORD'),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);