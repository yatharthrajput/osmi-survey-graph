<?php

define('APPLICATION_PATH', realpath(dirname(__DIR__)));

require APPLICATION_PATH . '/vendor/autoload.php';

if (file_exists(APPLICATION_PATH . '/.env')) {
    $dotenv = new Dotenv\Dotenv(APPLICATION_PATH);
    // Using overload to overwrite existing environment variables
    $dotenv->overload();
}

if (!defined('APP_MODE')) {
    $mode = getenv('APP_MODE') ? getenv('APP_MODE') : 'production';
    define('APP_MODE', $mode);
}

if (!defined('SLIM_MODE')) {
    $mode = getenv('SLIM_MODE') ? getenv('SLIM_MODE') : 'production';
    define('SLIM_MODE', $mode);
}

date_default_timezone_set('UTC');
error_reporting(getenv('OSMI_ERROR_REPORTING'));
ini_set('display_errors', getenv('OSMI_DISPLAY_ERRORS'));
ini_set('display_startup_errors', getenv('OSMI_DISPLAY_STARTUP_ERRORS'));

$settings = require_once APPLICATION_PATH . '/app/settings.php';
$container = require_once APPLICATION_PATH . '/app/dependencies.php';
