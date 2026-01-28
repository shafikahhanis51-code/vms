<?php
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', getenv('APP_TIMEZONE') ?: 'Asia/Kuala_Lumpur');
}
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set(APP_TIMEZONE);
}

if (!defined('VISITOR_CRYPTO_KEY')) {
    define('VISITOR_CRYPTO_KEY', getenv('VISITOR_CRYPTO_KEY') ?: 'ZGVmYXVsdC1jaGFuZ2UtdGhpcy1rZXktbGF0ZXIhIQ==');
}

if (!defined('OTP_EXPIRY_MINUTES')) {
    define('OTP_EXPIRY_MINUTES', 15);
}

if (!defined('VISIT_CATEGORY_DURATIONS')) {
    define('VISIT_CATEGORY_DURATIONS', json_encode([
        'family' => 120,
        'delivery' => 30,
        'friend' => 60,
        'service' => 90,
    ]));
}

if (!defined('BASE_URL')) {
    define('BASE_URL', rtrim(getenv('APP_BASE_URL') ?: 'http://localhost/VisitorManagement/', '/') . '/');
}
