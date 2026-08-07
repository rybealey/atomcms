<?php

/**
 * PHPUnit's <env name="..." value="..." force="true"/> in phpunit.xml only
 * overwrites $_ENV and calls putenv() - it does not touch $_SERVER. When the
 * cms container is started via compose.yaml, APP_ENV, DB_* and
 * EMULATOR_DRIVER are set as real container environment variables, which
 * PHP's CLI SAPI
 * exposes through $_SERVER as well as getenv(). Laravel's env() resolution
 * (Illuminate\Support\Env, via vlucas/phpdotenv) checks $_SERVER before
 * $_ENV, so those container values silently win over phpunit.xml's
 * "force"d overrides - meaning `docker compose exec cms php artisan test`
 * would boot against the live "pixelrp" database and the "local" app
 * environment instead of the isolated pixelrp_test database, even though
 * phpunit.xml looks correct.
 *
 * This is exactly how the shared PlusEMU database got wiped by a
 * RefreshDatabase test earlier in this project: the test suite believed it
 * was pointed at an isolated database, but $_SERVER kept it on the real one.
 *
 * Fix: unset the keys phpunit.xml overrides from $_SERVER before Laravel
 * (or anything else) reads them, so Dotenv's reader falls through to the
 * $_ENV/getenv() values phpunit.xml already forced.
 */
foreach ([
    'APP_ENV',
    'APP_DEBUG',
    'APP_KEY',
    'BCRYPT_ROUNDS',
    'CACHE_STORE',
    'CACHE_DRIVER',
    'DB_CONNECTION',
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
    'EMULATOR_DRIVER',
    'MAIL_MAILER',
    'QUEUE_CONNECTION',
    'SESSION_DRIVER',
    'TELESCOPE_ENABLED',
] as $envKey) {
    unset($_SERVER[$envKey]);
}

require __DIR__ . '/../vendor/autoload.php';
