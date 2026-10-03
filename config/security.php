<?php

declare(strict_types=1);

return [
    'session' => [
        'name'            => 'wylde_session',
        'lifetime'        => (int) env('SESSION_LIFETIME', 120) * 60,
        'secure'          => (bool) env('SESSION_SECURE', false),
        'httponly'        => true,
        'samesite'        => 'Lax',
        'use_strict_mode' => true,
        'cookie_path'     => '/',
        'regenerate_on_login' => true,
    ],

    'csrf' => [
        'token_name'   => '_token',
        'header_name'  => 'X-CSRF-Token',
        'lifetime'     => (int) env('CSRF_LIFETIME', 7200),
        'except_paths' => [],
    ],

    'login' => [
        'max_attempts'  => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_min'   => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
        'decay_minutes' => 30,
    ],

    'rate_limit' => [
        'max'    => (int) env('RATE_LIMIT_MAX', 60),
        'window' => (int) env('RATE_LIMIT_WINDOW', 60),
    ],

    'password' => [
        'algo'            => PASSWORD_DEFAULT,
        'min_length'      => 8,
        'require_mixed'   => true,
        'require_number'  => true,
    ],

    'headers' => [
        'X-Content-Type-Options'  => 'nosniff',
        'X-Frame-Options'         => 'SAMEORIGIN',
        'Referrer-Policy'         => 'strict-origin-when-cross-origin',
        'Permissions-Policy'      => 'geolocation=(), microphone=(), camera=(), payment=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ],

    // CSP déployée progressivement (cf. §13.5).
    'csp' => [
        'enabled'    => (bool) env('CSP_ENABLED', false),
        'report_only'=> (bool) env('CSP_REPORT_ONLY', true),
        'directives' => [
            "default-src 'self'",
            "img-src 'self' data: blob: https:",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "script-src 'self' https://cdn.jsdelivr.net",
            "font-src 'self' data: https://cdn.jsdelivr.net",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ],
    ],

    'uploads' => [
        // Hors document root : storage/uploads n'est pas servi par Apache.
        'dir'                  => 'products',
        'max_bytes'            => 5 * 1024 * 1024,
        'allowed_mime'         => [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
        ],
        'allowed_ext'          => ['jpg', 'jpeg', 'png', 'webp', 'avif'],
        // Refuser tout fichier potentiellement exécutable.
        'forbidden_ext'        => ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
                                   'js', 'html', 'htm', 'shtml', 'cgi', 'pl', 'py', 'sh', 'bat',
                                   'exe', 'com', 'dll', 'so', 'htaccess'],
        'max_width'            => 2000,
        'thumb_width'          => 600,
        'thumb_quality'        => 82,
        'webp_quality'         => 82,
        'max_images_per_product' => 12,
    ],
];
