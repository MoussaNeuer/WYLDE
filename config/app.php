<?php

declare(strict_types=1);

return [
    'name'     => env('APP_NAME', 'WYLDE'),

    // Clé applicative (HMAC des empreintes IP dans audit_logs et
    // Request::ipHash). Sans elle, ip_hash serait calculé avec une clé
    // vide, donc forgeable par quiconque connaît l'IP.
    'key'      => env('APP_KEY', ''),

    'env'      => env('APP_ENV', 'production'),
    'debug'    => (bool) env('APP_DEBUG', false),
    'url'      => rtrim((string) env('APP_URL', 'http://localhost'), '/'),
    'timezone' => env('APP_TIMEZONE', 'Africa/Dakar'),
    'locale'   => env('APP_LOCALE', 'fr'),
    'fallback_locale' => 'fr',

    // Locales disponibles (bascule FR/EN par cookie + session, URLs identiques).
    'available_locales' => ['fr', 'en'],
    'locale_cookie'    => 'wylde_locale',
    'locale_session_key' => 'locale',

    'paths' => [
        'root'     => dirname(__DIR__),
        'app'      => dirname(__DIR__) . '/app',
        'views'    => dirname(__DIR__) . '/resources/views',
        'lang'     => dirname(__DIR__) . '/resources/lang',
        'public'   => dirname(__DIR__) . '/public',
        'uploads'  => dirname(__DIR__) . '/storage/uploads',
        'logs'     => dirname(__DIR__) . '/storage/logs',
        'cache'    => dirname(__DIR__) . '/storage/cache',
    ],

    // Montants : FCFA, DECIMAL(12,0), jamais de centimes.
    'currency' => [
        'code'      => env('CURRENCY', 'XOF'),
        'symbol'    => 'FCFA',
        'position'  => env('CURRENCY_POSITION', 'after'),
        'decimals'  => (int) env('CURRENCY_DECIMALS', 0),
        'separator' => env('CURRENCY_SEPARATOR', "\u{202F}"),
    ],

    'stock' => [
        'low_threshold' => (int) env('STOCK_LOW_THRESHOLD', 5),
    ],

    // Médias produits : formats acceptés et taille maximale.
    // Les fichiers sont écrits dans storage/uploads, hors document root,
    // et servis par /media/{chemin} (cf. §13.4).
    'uploads' => [
        'max_size'   => (int) env('UPLOAD_MAX_SIZE', 5 * 1024 * 1024),
        'max_width'  => (int) env('UPLOAD_MAX_WIDTH', 3000),
        'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'avif'],
        'folder'     => 'products',
    ],

    // Paiement. Aucune vérification automatique :
    // - cod  : paiement à la livraison, l'admin encaisse
    // - wave : le client paie via le lien global, l'admin bascule en « payée »
    'payment' => [
        'default_method' => 'cod',
        'methods'        => ['cod', 'wave'],
        'wave_link'      => env('WAVE_PAYMENT_LINK', ''),
    ],

    'pagination' => [
        'shop'  => 12,
        'admin' => 20,
    ],
];
