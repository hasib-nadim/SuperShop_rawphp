<?php 
// default config
$config = [
    // App
    'app_env' => 'production',
    'app_debug' => true,
    'app_url' => 'http://localhost:8080',
    'app_key' => 'lGxtyx28kWF7rn8iss80gMOL4a2c6NVUJoO2GRxBAhRSGd66wDgeOx2YYLlxB7K1',

    // Database
    'db_driver' => 'mysqli',
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_username' => 'root',
    'db_password' => '',
    'db_name' => 'ecom_db',

    // Mail (optional)
    'mail_driver' => 'smtp',
    'mail_host' => 'smtp.mailtrap.io',
    'mail_port' => 2525,
    'mail_username' => null,
    'mail_password' => null,
    'mail_encryption' => null,

    // Misc
    'log_level' => 'debug',
    'session_lifetime' => 120,
];

function env($key, $default = null)
{
    global $config;
    $lowerKey = strtolower($key);
    if (array_key_exists($lowerKey, $config)) {
        return $config[$lowerKey];
    }
    return $default;
}
// try to load .env from project root (two levels up from this file)
$envPath = realpath(__DIR__ . '/../../.env') ?: __DIR__ . '/../../.env';

if (is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') {
            continue;
        }
        // split on first '='
        if (!strpos($line, '=')) {
            continue;
        }
        list($rawKey, $rawVal) = explode('=', $line, 2);
        $key = trim($rawKey);
        $val = trim($rawVal);

        // remove surrounding quotes if present
        if ((strlen($val) >= 2) && (($val[0] === '"' && $val[-1] === '"') || ($val[0] === "'" && $val[-1] === "'"))) {
            $val = substr($val, 1, -1);
        }

        // store in config array with lowercase key
        $lower = strtolower($key);
        $config[$lower] = $val;
    }
}
