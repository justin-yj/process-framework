<?php
declare(strict_types=1);

use App\Service;

ini_set("display_errors", "On");

include realpath( dirname( __FILE__ ) ) .'/../vendor/autoload.php';
set_error_handler("errorExceptionHandel", E_WARNING|E_NOTICE);

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ .'/../');
$dotenv->safeLoad();

$service = new Service();
$service->run();
