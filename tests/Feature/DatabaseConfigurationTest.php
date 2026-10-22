<?php

test('mysql SSL CA configuration avoids deprecated PDO constants', function () {
    $previousEnv = $_ENV['MYSQL_ATTR_SSL_CA'] ?? null;
    $hadPreviousEnv = array_key_exists('MYSQL_ATTR_SSL_CA', $_ENV);
    $_ENV['MYSQL_ATTR_SSL_CA'] = '/tmp/mysql-ca.pem';

    $deprecations = [];
    set_error_handler(function (int $severity, string $message) use (&$deprecations): bool {
        if ($severity === E_DEPRECATED) {
            $deprecations[] = $message;

            return true;
        }

        return false;
    });

    try {
        $database = require config_path('database.php');
    } finally {
        restore_error_handler();

        if ($hadPreviousEnv) {
            $_ENV['MYSQL_ATTR_SSL_CA'] = $previousEnv;
        } else {
            unset($_ENV['MYSQL_ATTR_SSL_CA']);
        }
    }

    $sslCaOption = PHP_VERSION_ID >= 80500
        ? \Pdo\Mysql::ATTR_SSL_CA
        : PDO::MYSQL_ATTR_SSL_CA;

    expect($deprecations)->toBe([])
        ->and($database['connections']['mysql']['options'])->toBe([$sslCaOption => '/tmp/mysql-ca.pem'])
        ->and($database['connections']['mariadb']['options'])->toBe([$sslCaOption => '/tmp/mysql-ca.pem']);
});
